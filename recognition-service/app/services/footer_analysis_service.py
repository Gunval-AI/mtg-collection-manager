import os
import re

import cv2
import numpy as np
import pytesseract

FOOTER_DEBUG_OUTPUT_DIR = "debug_output/footer"

KNOWN_SETS = {"TMT", "TLA", "ECL"}
KNOWN_LANGUAGES = {"SP", "EN"}

FOOTER_OCR_CONFIG_LINE = (
    "--psm 7 "
    "--oem 3 "
    "-c preserve_interword_spaces=1 "
    "-c tessedit_char_whitelist=ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789 .•/-"
)

FOOTER_OCR_CONFIG_FULL = (
    "--psm 6 "
    "--oem 3 "
    "-c preserve_interword_spaces=1 "
    "-c tessedit_char_whitelist=ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789 .•/-"
)

if os.name == "nt":
    pytesseract.pytesseract.tesseract_cmd = r"C:\Program Files\Tesseract-OCR\tesseract.exe"


def analyze_footer(image: np.ndarray) -> dict:
    footer_region = extract_footer_region(image)

    if footer_region.size == 0:
        return empty_footer_result("Failed to extract footer region.")

    save_debug_image("footer_region.png", footer_region)

    candidates = []

    # First pass: OCR by detected footer lines.
    for variant_name, processed in preprocess_footer_variants(footer_region).items():
        save_debug_image(f"footer_processed_{variant_name}.png", processed)

        line_regions = extract_line_regions(processed)

        for index, line_region in enumerate(line_regions):
            save_debug_image(f"{variant_name}_line_{index}.png", line_region)

            text = ocr_footer_line(line_region)
            strong = extract_strong_pattern(text)

            if strong:
                return complete_footer_result(strong, text, "strong_pattern")

            candidates.append({
                "variant": variant_name,
                "line_index": index,
                "raw_text": text,
                **parse_footer_text(text),
            })

    # Second pass: OCR over the whole footer if line OCR was not enough.
    full_text_candidates = run_full_footer_ocr(footer_region)

    if full_text_candidates and full_text_candidates[0].get("is_complete"):
        return full_text_candidates[0]

    candidates.extend(full_text_candidates)

    best = choose_best_footer_candidate(candidates)

    return build_footer_result(best)


def complete_footer_result(data: dict, raw_text: str, method: str) -> dict:
    return {
        **data,
        "is_complete": True,
        "method": method,
        "raw_text": raw_text,
    }


def build_footer_result(best: dict) -> dict:
    collector_number = best.get("collector_number")
    set_code = best.get("set_code")
    language = best.get("language")

    return {
        "collector_number": collector_number,
        "set_code": set_code,
        "language": language,
        "is_complete": bool(collector_number and set_code and language),
        "score": best.get("score", 0),
        "raw_text": best.get("raw_text", ""),
    }


def empty_footer_result(error: str) -> dict:
    return {
        "collector_number": None,
        "set_code": None,
        "language": None,
        "is_complete": False,
        "score": 0,
        "raw_text": "",
        "error": error,
    }


def save_debug_image(filename: str, image: np.ndarray) -> None:
    os.makedirs(FOOTER_DEBUG_OUTPUT_DIR, exist_ok=True)
    cv2.imwrite(os.path.join(FOOTER_DEBUG_OUTPUT_DIR, filename), image)


def extract_footer_region(image: np.ndarray) -> np.ndarray:
    height, width = image.shape[:2]

    y_start = int(height * 0.910)
    y_end = int(height * 0.995)

    x_start = 0
    x_end = int(width * 0.62)

    return image[y_start:y_end, x_start:x_end]


def preprocess_footer_variants(footer_region: np.ndarray) -> dict[str, np.ndarray]:
    gray = cv2.cvtColor(footer_region, cv2.COLOR_BGR2GRAY)

    resized = cv2.resize(
        gray,
        None,
        fx=3,
        fy=3,
        interpolation=cv2.INTER_CUBIC,
    )

    blurred = cv2.GaussianBlur(resized, (3, 3), 0)

    _, fixed = cv2.threshold(
        blurred,
        150,
        255,
        cv2.THRESH_BINARY,
    )

    _, otsu = cv2.threshold(
        blurred,
        0,
        255,
        cv2.THRESH_BINARY + cv2.THRESH_OTSU,
    )

    adaptive = cv2.adaptiveThreshold(
        blurred,
        255,
        cv2.ADAPTIVE_THRESH_GAUSSIAN_C,
        cv2.THRESH_BINARY,
        31,
        8,
    )

    return {
        "fixed_inv": clean_binary(cv2.bitwise_not(fixed)),
        "otsu_inv": clean_binary(cv2.bitwise_not(otsu)),
        "adaptive_inv": clean_binary(cv2.bitwise_not(adaptive)),
    }


def clean_binary(binary: np.ndarray) -> np.ndarray:
    kernel = np.ones((2, 2), np.uint8)

    opened = cv2.morphologyEx(binary, cv2.MORPH_OPEN, kernel)
    closed = cv2.morphologyEx(opened, cv2.MORPH_CLOSE, kernel)

    return closed


def extract_line_regions(processed_footer: np.ndarray) -> list[np.ndarray]:
    height, width = processed_footer.shape[:2]

    projection = np.sum(processed_footer > 0, axis=1)
    threshold = max(3, int(width * 0.015))

    line_ranges = find_line_ranges(
        projection=projection,
        threshold=threshold,
        min_height=int(height * 0.08),
    )

    merged_ranges = merge_close_ranges(
        line_ranges,
        max_gap=int(height * 0.06),
    )

    return crop_line_regions(
        processed_footer=processed_footer,
        line_ranges=merged_ranges,
    )


def find_line_ranges(
    projection: np.ndarray,
    threshold: int,
    min_height: int,
) -> list[tuple[int, int]]:
    line_ranges = []
    in_line = False
    start = 0

    for y, value in enumerate(projection):
        if value > threshold and not in_line:
            start = y
            in_line = True

        elif value <= threshold and in_line:
            end = y
            in_line = False

            if end - start > min_height:
                line_ranges.append((start, end))

    if in_line:
        line_ranges.append((start, len(projection) - 1))

    return line_ranges


def crop_line_regions(
    processed_footer: np.ndarray,
    line_ranges: list[tuple[int, int]],
) -> list[np.ndarray]:
    height, width = processed_footer.shape[:2]
    line_regions = []

    for start, end in line_ranges:
        padding_y = int(height * 0.04)

        y1 = max(0, start - padding_y)
        y2 = min(height, end + padding_y)

        line = processed_footer[y1:y2, :]

        x1, x2 = find_text_x_bounds(line)

        if x2 <= x1:
            continue

        padding_x = int(width * 0.02)

        x1 = max(0, x1 - padding_x)
        x2 = min(width, x2 + padding_x)

        cropped = line[:, x1:x2]

        if cropped.shape[0] > 0 and cropped.shape[1] > 0:
            line_regions.append(cropped)

    return line_regions[:4]


def merge_close_ranges(
    ranges: list[tuple[int, int]],
    max_gap: int,
) -> list[tuple[int, int]]:
    if not ranges:
        return []

    merged = [ranges[0]]

    for start, end in ranges[1:]:
        previous_start, previous_end = merged[-1]

        if start - previous_end <= max_gap:
            merged[-1] = (previous_start, end)
        else:
            merged.append((start, end))

    return merged


def find_text_x_bounds(line: np.ndarray) -> tuple[int, int]:
    projection = np.sum(line > 0, axis=0)
    active = np.where(projection > 0)[0]

    if len(active) == 0:
        return 0, 0

    return int(active[0]), int(active[-1])


def ocr_footer_line(line_region: np.ndarray) -> str:
    text = pytesseract.image_to_string(
        line_region,
        config=FOOTER_OCR_CONFIG_LINE,
    )

    return normalize_footer_text(text)


def run_full_footer_ocr(footer_region: np.ndarray) -> list[dict]:
    candidates = []

    for variant_name, processed in preprocess_footer_variants(footer_region).items():
        text = pytesseract.image_to_string(
            processed,
            config=FOOTER_OCR_CONFIG_FULL,
        )

        normalized = normalize_footer_text(text)
        strong = extract_strong_pattern(normalized)

        if strong:
            return [
                complete_footer_result(strong, normalized, "strong_pattern")
            ]

        candidates.append({
            "variant": f"{variant_name}_full",
            "line_index": None,
            "raw_text": normalized,
            **parse_footer_text(normalized),
        })

    return candidates


def parse_footer_text(text: str) -> dict:
    normalized = normalize_footer_text(text)

    collector_number = extract_collector_number(normalized)
    set_code = extract_set_code(normalized)
    language = extract_language(normalized)

    score = score_footer_candidate(
        collector_number=collector_number,
        set_code=set_code,
        language=language,
        raw_text=normalized,
    )

    return {
        "collector_number": collector_number,
        "set_code": set_code,
        "language": language,
        "score": score,
    }


def extract_strong_pattern(text: str) -> dict | None:
    if not text:
        return None

    compact = text.replace(" ", "")
    compact = normalize_known_compact_patterns(compact)

    match = re.search(
        r"([CRUM]?\d{3,4})([A-Z]{3})(SP|EN)",
        compact,
    )

    if not match:
        match = re.search(
            r"([CRUM]?\d{3,4}).{0,10}?([A-Z]{3}).{0,5}?(SP|EN)",
            compact,
        )

    if not match:
        return None

    set_code = match.group(2)
    language = match.group(3)

    if set_code not in KNOWN_SETS:
        return None

    numbers = re.findall(r"\d{3,4}", compact)

    if not numbers:
        return None

    best_number = max(numbers, key=len)

    if len(best_number) > 4:
        best_number = best_number[:4]

    return {
        "collector_number": best_number.zfill(4),
        "set_code": set_code,
        "language": language,
    }


def normalize_known_compact_patterns(text: str) -> str:
    return (
        text
        .replace("TLASP", "TLASP ")
        .replace("TMTSP", "TMTSP ")
        .replace("ECLSP", "ECLSP ")
    )


def extract_collector_number(text: str) -> str | None:
    matches = re.findall(r"\b\d{1,4}\b", text)

    if not matches:
        return None

    return max(matches, key=len).zfill(4)


def extract_set_code(text: str) -> str | None:
    for set_code in KNOWN_SETS:
        if set_code in text:
            return set_code

    corrections = {
        "IMT": "TMT",
        "TMI": "TMT",
        "TNT": "TMT",
        "TM": "TMT",
        "LA": "TLA",
        "TLI": "TLA",
        "TL": "TLA",
        "ECI": "ECL",
        "ELL": "ECL",
        "EL": "ECL",
    }

    for wrong, corrected in corrections.items():
        if wrong in text:
            return corrected

    matches = re.findall(r"\b[A-Z]{3}\b", text)

    for match in matches:
        if match in KNOWN_SETS:
            return match

    return None


def extract_language(text: str) -> str | None:
    for language in KNOWN_LANGUAGES:
        if re.search(rf"\b{language}\b", text):
            return language

    corrections = {
        "5P": "SP",
        "S P": "SP",
        "EP": "SP",
        "FP": "SP",
        "FN": "EN",
        "E N": "EN",
    }

    for wrong, corrected in corrections.items():
        if wrong in text:
            return corrected

    return None


def score_footer_candidate(
    collector_number: str | None,
    set_code: str | None,
    language: str | None,
    raw_text: str,
) -> int:
    score = 0

    if collector_number:
        score += 40

        if len(collector_number) == 4:
            score += 10

    if set_code:
        score += 35

        if set_code in KNOWN_SETS:
            score += 10

    if language:
        score += 25

    if collector_number and set_code:
        score += 20

    if set_code and language:
        score += 20

    if collector_number and set_code and language:
        score += 50

    if raw_text:
        score += min(len(raw_text), 20)

    return score


def choose_best_footer_candidate(candidates: list[dict]) -> dict:
    if not candidates:
        return {
            "collector_number": None,
            "set_code": None,
            "language": None,
            "score": 0,
            "raw_text": "",
        }

    combined = combine_candidates(candidates)
    best_candidate = max(candidates, key=lambda candidate: candidate.get("score", 0))

    if combined["score"] >= best_candidate.get("score", 0):
        return combined

    return best_candidate


def combine_candidates(candidates: list[dict]) -> dict:
    collector_number = pick_most_common(
        [candidate.get("collector_number") for candidate in candidates]
    )

    set_code = pick_most_common(
        [candidate.get("set_code") for candidate in candidates]
    )

    language = pick_most_common(
        [candidate.get("language") for candidate in candidates]
    )

    raw_text = " | ".join(
        candidate.get("raw_text", "")
        for candidate in candidates
        if candidate.get("raw_text")
    )[:300]

    score = score_footer_candidate(
        collector_number=collector_number,
        set_code=set_code,
        language=language,
        raw_text=raw_text,
    )

    return {
        "collector_number": collector_number,
        "set_code": set_code,
        "language": language,
        "score": score,
        "raw_text": raw_text,
        "variant": "combined",
        "line_index": None,
    }


def pick_most_common(values: list[str | None]) -> str | None:
    filtered = [value for value in values if value]

    if not filtered:
        return None

    counts = {}

    for value in filtered:
        counts[value] = counts.get(value, 0) + 1

    return max(counts.items(), key=lambda item: item[1])[0]


def normalize_footer_text(text: str) -> str:
    cleaned = " ".join(text.strip().split())

    cleaned = cleaned.replace("·", " ")
    cleaned = cleaned.replace("•", " ")
    cleaned = cleaned.replace("|", " ")
    cleaned = cleaned.replace("\\", " ")
    cleaned = cleaned.replace("_", " ")
    cleaned = cleaned.replace("©", " ")

    cleaned = re.sub(r"\s+", " ", cleaned)

    return cleaned.strip().upper()