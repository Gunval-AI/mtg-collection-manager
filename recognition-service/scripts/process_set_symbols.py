from pathlib import Path
import os

CAIRO_DLL_DIR = None

if os.name == "nt":
    tesseract_dir = r"C:\Program Files\Tesseract-OCR"
    CAIRO_DLL_DIR = os.add_dll_directory(tesseract_dir)
    os.environ["PATH"] = tesseract_dir + os.pathsep + os.environ["PATH"]

import cairosvg
import cv2
import numpy as np


BASE_DIR = Path(__file__).resolve().parents[1]

RAW_DIR = BASE_DIR / "references" / "set_symbols" / "raw"
PROCESSED_DIR = BASE_DIR / "references" / "set_symbols" / "processed"

TEMP_RENDER_SIZE = 512
OUTPUT_SIZE = 128
SYMBOL_SCALE = 0.80


def convert_svg_to_png_bytes(svg_path: Path) -> bytes:
    return cairosvg.svg2png(
        url=str(svg_path),
        output_width=TEMP_RENDER_SIZE,
        output_height=TEMP_RENDER_SIZE,
    )


def decode_png_bytes(png_bytes: bytes) -> np.ndarray:
    np_buffer = np.frombuffer(png_bytes, dtype=np.uint8)
    image = cv2.imdecode(np_buffer, cv2.IMREAD_UNCHANGED)

    if image is None:
        raise ValueError("Could not decode PNG bytes.")

    return image


def extract_symbol_mask(image: np.ndarray) -> np.ndarray:
    if image.shape[2] == 4:
        return image[:, :, 3]

    return cv2.cvtColor(image, cv2.COLOR_BGR2GRAY)


def crop_symbol(binary_image: np.ndarray) -> np.ndarray:
    coords = cv2.findNonZero(binary_image)

    if coords is None:
        raise ValueError("No symbol pixels found.")

    x, y, width, height = cv2.boundingRect(coords)

    return binary_image[y:y + height, x:x + width]


def center_symbol_on_canvas(symbol: np.ndarray) -> np.ndarray:
    canvas = np.zeros((OUTPUT_SIZE, OUTPUT_SIZE), dtype=np.uint8)

    scale = min(
        (OUTPUT_SIZE * SYMBOL_SCALE) / symbol.shape[1],
        (OUTPUT_SIZE * SYMBOL_SCALE) / symbol.shape[0],
    )

    new_width = max(1, int(symbol.shape[1] * scale))
    new_height = max(1, int(symbol.shape[0] * scale))

    resized_symbol = cv2.resize(
        symbol,
        (new_width, new_height),
        interpolation=cv2.INTER_AREA,
    )

    x_offset = (OUTPUT_SIZE - new_width) // 2
    y_offset = (OUTPUT_SIZE - new_height) // 2

    canvas[
        y_offset:y_offset + new_height,
        x_offset:x_offset + new_width,
    ] = resized_symbol

    return canvas


def prepare_symbol_image(image: np.ndarray) -> np.ndarray:
    gray_image = extract_symbol_mask(image)

    _, binary_image = cv2.threshold(
        gray_image,
        0,
        255,
        cv2.THRESH_BINARY + cv2.THRESH_OTSU,
    )

    # Normalize every set symbol to the same size for image matching.
    cropped_symbol = crop_symbol(binary_image)

    return center_symbol_on_canvas(cropped_symbol)


def process_symbol(svg_path: Path) -> None:
    png_bytes = convert_svg_to_png_bytes(svg_path)
    rendered_image = decode_png_bytes(png_bytes)
    processed_image = prepare_symbol_image(rendered_image)

    output_path = PROCESSED_DIR / f"{svg_path.stem.upper()}.png"
    cv2.imwrite(str(output_path), processed_image)

    print(f"[OK] {svg_path.name} -> {output_path.name}")


def main() -> None:
    PROCESSED_DIR.mkdir(parents=True, exist_ok=True)

    svg_files = sorted(RAW_DIR.glob("*.svg"))

    if not svg_files:
        print(f"No SVG files found in: {RAW_DIR}")
        return

    for svg_path in svg_files:
        process_symbol(svg_path)


if __name__ == "__main__":
    main()