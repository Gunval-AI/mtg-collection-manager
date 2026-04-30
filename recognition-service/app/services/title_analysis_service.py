import os
import re

import cv2
import numpy as np
import pytesseract

DEBUG_OUTPUT_DIR = "debug_output"

TITLE_OCR_CONFIG = (
    "--psm 6 "
    "--oem 3 "
    "-c preserve_interword_spaces=1"
)

VALID_TITLE_CHARS = {
    " ", ",", "-", "'",
    "á", "é", "í", "ó", "ú",
    "Á", "É", "Í", "Ó", "Ú",
    "ñ", "Ñ",
}

NOISE_WORDS = {
    "SS", "PI", "SO", "RE", "FE", "OO", "EM", "EOE",
    "tit", "TIT", "tt", "TT", "ii", "II",
}

if os.name == "nt":
    pytesseract.pytesseract.tesseract_cmd = r"C:\Program Files\Tesseract-OCR\tesseract.exe"


def analyze_title(image: np.ndarray) -> dict:
    name_region = extract_name_region(image)

    if name_region.size == 0:
        return {
            "text": "",
            "error": "Failed to extract name region.",
        }

    # The title is only analyzed when footer OCR fails.
    ocr_variants = preprocess_name_region_for_ocr(name_region)
    save_debug_images(name_region, ocr_variants)

    detected_text = extract_text_from_name_region(ocr_variants)

    return {
        "text": normalize_ocr_text(detected_text),
    }


def extract_name_region(image: np.ndarray) -> np.ndarray:
    height, width = image.shape[:2]

    y_start = int(height * 0.045)
    y_end = int(height * 0.11)

    x_start = int(width * 0.06)
    x_end = int(width * 0.82)

    return image[y_start:y_end, x_start:x_end]


def resize_to_target_height(image: np.ndarray, target_height: int = 220) -> np.ndarray:
    current_height = image.shape[0]

    if current_height == 0:
        return image

    scale = target_height / current_height

    return cv2.resize(
        image,
        None,
        fx=scale,
        fy=scale,
        interpolation=cv2.INTER_CUBIC,
    )


def preprocess_name_region_for_ocr(name_region: np.ndarray) -> dict[str, np.ndarray]:
    gray = cv2.cvtColor(name_region, cv2.COLOR_BGR2GRAY)
    resized = resize_to_target_height(gray, target_height=220)
    blurred = cv2.GaussianBlur(resized, (5, 5), 0)

    _, binary = cv2.threshold(
        blurred,
        0,
        255,
        cv2.THRESH_BINARY + cv2.THRESH_OTSU,
    )

    kernel = np.ones((3, 3), np.uint8)
    closed = cv2.morphologyEx(binary, cv2.MORPH_CLOSE, kernel)
    inverted = cv2.bitwise_not(closed)

    return {
        "closed": closed,
        "inverted": inverted,
        "gray": blurred,
    }


def save_debug_images(
    name_region: np.ndarray,
    ocr_variants: dict[str, np.ndarray],
) -> None:
    os.makedirs(DEBUG_OUTPUT_DIR, exist_ok=True)

    debug_images = {
        "name_region.png": name_region,
        "preprocessed_name_region_closed.png": ocr_variants["closed"],
        "preprocessed_name_region_inverted.png": ocr_variants["inverted"],
        "preprocessed_name_region_gray.png": ocr_variants["gray"],
    }

    for filename, image in debug_images.items():
        cv2.imwrite(os.path.join(DEBUG_OUTPUT_DIR, filename), image)


def extract_text_from_name_region(ocr_variants: dict[str, np.ndarray]) -> str:
    candidates = []

    for variant_name, variant_image in ocr_variants.items():
        detected_text = pytesseract.image_to_string(
            variant_image,
            lang="eng+spa",
            config=TITLE_OCR_CONFIG,
        )

        cleaned = " ".join(detected_text.strip().split())

        if cleaned:
            candidates.append((variant_name, cleaned))

    if not candidates:
        return ""

    return max(candidates, key=lambda item: score_text(item[1]))[1]


def score_text(text: str) -> int:
    normalized = normalize_ocr_text(text)

    if not normalized:
        return -9999

    score = 0
    score += len(normalized) * 2
    score += sum(1 for character in normalized if character.isalpha()) * 3
    score -= count_invalid_title_chars(normalized) * 5

    return score


def count_invalid_title_chars(text: str) -> int:
    return sum(
        1
        for character in text
        if not character.isalpha()
        and not character.isdigit()
        and character not in VALID_TITLE_CHARS
    )


def normalize_ocr_text(text: str) -> str:
    cleaned = " ".join(text.strip().split())

    if not cleaned:
        return ""

    cleaned = keep_best_text_part(cleaned)
    cleaned = remove_invalid_title_edges(cleaned)
    cleaned = remove_invalid_title_chars(cleaned)
    cleaned = remove_trailing_noise_words(cleaned)

    return cleaned.strip(" |.:;,_-()[]=+*")


def keep_best_text_part(text: str) -> str:
    cleaned = text.replace("—", "|").replace("•", "|")

    if "|" not in cleaned:
        return cleaned

    parts = [part.strip() for part in cleaned.split("|") if part.strip()]

    if not parts:
        return cleaned

    return max(parts, key=count_letters)


def remove_invalid_title_edges(text: str) -> str:
    return re.sub(r"^[^A-Za-zÁÉÍÓÚáéíóúÑñ]+", "", text)


def remove_invalid_title_chars(text: str) -> str:
    cleaned = re.sub(
        r"[^A-Za-zÁÉÍÓÚáéíóúÑñ0-9 ,'\-]",
        " ",
        text,
    )

    return " ".join(cleaned.split())


def remove_trailing_noise_words(text: str) -> str:
    words = text.split()

    while len(words) > 1:
        last = words[-1].strip(" ,.'-")

        if is_noise_word(last):
            words.pop()
            continue

        break

    return " ".join(words)


def is_noise_word(word: str) -> bool:
    if not word:
        return True

    if word in NOISE_WORDS:
        return True

    if word.isdigit():
        return True

    if len(word) <= 2 and word.upper() == word:
        return True

    if len(word) == 1:
        return True

    return False


def count_letters(text: str) -> int:
    return sum(1 for character in text if character.isalpha())