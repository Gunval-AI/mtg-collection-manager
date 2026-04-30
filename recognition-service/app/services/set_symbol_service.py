import os

import cv2
import numpy as np

SYMBOL_DEBUG_OUTPUT_DIR = "debug_output/symbols"
REFERENCE_SYMBOLS_DIR = "references/set_symbols/processed"

SYMBOL_SIZE = (128, 128)
MAX_MATCH_DISTANCE = 0.20


def analyze_set_symbol(image: np.ndarray) -> dict | None:
    set_symbol_region = extract_set_symbol_region(image)

    if set_symbol_region.size == 0:
        return None

    # Symbol matching is only used when footer OCR fails.
    processed_symbol = preprocess_set_symbol(set_symbol_region)
    save_debug_images(set_symbol_region, processed_symbol)

    return match_set_symbol(processed_symbol)


def extract_set_symbol_region(image: np.ndarray) -> np.ndarray:
    height, width = image.shape[:2]

    y_start = int(height * 0.555)
    y_end = int(height * 0.635)

    x_start = int(width * 0.825)
    x_end = int(width * 0.925)

    return image[y_start:y_end, x_start:x_end]


def preprocess_set_symbol(symbol_region: np.ndarray) -> np.ndarray:
    height, width = symbol_region.shape[:2]

    symbol_region = symbol_region[int(height * 0.20):height, 0:width]

    gray = cv2.cvtColor(symbol_region, cv2.COLOR_BGR2GRAY)
    blurred = cv2.GaussianBlur(gray, (5, 5), 0)
    edges = cv2.Canny(blurred, 50, 150)

    cropped = crop_symbol_content(edges)

    return resize_on_black_canvas(cropped, SYMBOL_SIZE)


def save_debug_images(
    set_symbol_region: np.ndarray,
    processed_symbol: np.ndarray,
) -> None:
    os.makedirs(SYMBOL_DEBUG_OUTPUT_DIR, exist_ok=True)

    debug_images = {
        "set_symbol_region.png": set_symbol_region,
        "set_symbol_processed.png": processed_symbol,
    }

    for filename, image in debug_images.items():
        cv2.imwrite(os.path.join(SYMBOL_DEBUG_OUTPUT_DIR, filename), image)


def crop_symbol_content(binary: np.ndarray) -> np.ndarray:
    contours, _ = cv2.findContours(
        binary,
        cv2.RETR_EXTERNAL,
        cv2.CHAIN_APPROX_SIMPLE,
    )

    if not contours:
        return binary

    valid_contours = filter_symbol_contours(contours, binary.shape[:2])

    if not valid_contours:
        return binary

    image_height, image_width = binary.shape[:2]
    all_points = np.vstack(valid_contours)

    x, y, width, height = cv2.boundingRect(all_points)

    padding = 6

    x_start = max(x - padding, 0)
    y_start = max(y - padding, 0)
    x_end = min(x + width + padding, image_width)
    y_end = min(y + height + padding, image_height)

    return binary[y_start:y_end, x_start:x_end]


def filter_symbol_contours(
    contours: tuple,
    image_shape: tuple[int, int],
) -> list[np.ndarray]:
    image_height, image_width = image_shape
    valid_contours = []

    for contour in contours:
        x, y, width, height = cv2.boundingRect(contour)
        area = cv2.contourArea(contour)

        if area < 5:
            continue

        if width > image_width * 0.85 and height < image_height * 0.20:
            continue

        valid_contours.append(contour)

    return valid_contours


def resize_on_black_canvas(
    image: np.ndarray,
    target_size: tuple[int, int],
) -> np.ndarray:
    target_width, target_height = target_size
    height, width = image.shape[:2]

    if height == 0 or width == 0:
        return np.zeros((target_height, target_width), dtype=np.uint8)

    scale = min(target_width / width, target_height / height)

    new_width = max(1, int(width * scale))
    new_height = max(1, int(height * scale))

    resized = cv2.resize(
        image,
        (new_width, new_height),
        interpolation=cv2.INTER_AREA,
    )

    canvas = np.zeros((target_height, target_width), dtype=np.uint8)

    x_offset = (target_width - new_width) // 2
    y_offset = (target_height - new_height) // 2

    canvas[y_offset:y_offset + new_height, x_offset:x_offset + new_width] = resized

    return canvas


def normalize_reference_symbol(reference_symbol: np.ndarray) -> np.ndarray:
    edges = cv2.Canny(reference_symbol, 50, 150)
    cropped = crop_symbol_content(edges)

    return resize_on_black_canvas(cropped, SYMBOL_SIZE)


def match_set_symbol(processed_symbol: np.ndarray) -> dict | None:
    if not os.path.isdir(REFERENCE_SYMBOLS_DIR):
        return None

    processed_main = extract_main_contour(processed_symbol)

    if processed_main is None:
        return None

    candidates = build_symbol_candidates(processed_main)

    if not candidates:
        return None

    candidates.sort(key=lambda item: item["distance"])

    best_candidate = candidates[0]
    best_distance = best_candidate["distance"]

    return {
        "set": best_candidate["set"],
        "distance": best_distance,
        "confidence": max(0.0, 1.0 - best_distance),
        "compared_files": len(candidates),
        "accepted": best_distance <= MAX_MATCH_DISTANCE,
        "candidates": candidates,
    }


def build_symbol_candidates(processed_main: np.ndarray) -> list[dict]:
    candidates = []

    for filename in os.listdir(REFERENCE_SYMBOLS_DIR):
        if not filename.lower().endswith(".png"):
            continue

        set_code = os.path.splitext(filename)[0]

        reference_symbol = read_reference_symbol(filename)

        if reference_symbol is None:
            continue

        normalized_reference = normalize_reference_symbol(reference_symbol)
        reference_main = extract_main_contour(normalized_reference)

        if reference_main is None:
            continue

        distance = calculate_contour_distance(processed_main, reference_main)

        candidates.append({
            "set": set_code,
            "distance": distance,
        })

    return candidates


def read_reference_symbol(filename: str) -> np.ndarray | None:
    reference_path = os.path.join(REFERENCE_SYMBOLS_DIR, filename)

    return cv2.imread(reference_path, cv2.IMREAD_GRAYSCALE)


def extract_main_contour(image: np.ndarray) -> np.ndarray | None:
    contours, _ = cv2.findContours(
        image,
        cv2.RETR_EXTERNAL,
        cv2.CHAIN_APPROX_SIMPLE,
    )

    if not contours:
        return None

    return max(contours, key=cv2.contourArea)


def calculate_contour_distance(
    processed_main: np.ndarray,
    reference_main: np.ndarray,
) -> float:
    return float(cv2.matchShapes(
        processed_main,
        reference_main,
        cv2.CONTOURS_MATCH_I1,
        0.0,
    ))