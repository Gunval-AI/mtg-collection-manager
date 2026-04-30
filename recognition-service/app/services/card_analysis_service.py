import cv2
import numpy as np
from fastapi import HTTPException, UploadFile

from app.services.footer_analysis_service import analyze_footer
from app.services.set_symbol_service import analyze_set_symbol
from app.services.title_analysis_service import analyze_title

ALLOWED_MIME_TYPES = {
    "image/jpeg",
    "image/png",
    "image/webp",
}


async def analyze_uploaded_card_image(image: UploadFile) -> dict:
    validate_uploaded_image(image)

    file_bytes = await image.read()

    if not file_bytes:
        raise HTTPException(status_code=400, detail="Empty file.")

    decoded_image = decode_image(file_bytes)

    # Footer OCR is the main and fastest path.
    footer_detection = analyze_footer(decoded_image)

    title_detection = {"text": ""}
    symbol_detection = None

    # Title OCR and symbol matching are only fallback steps.
    if not footer_detection.get("is_complete"):
        title_detection = analyze_title(decoded_image)
        symbol_detection = analyze_set_symbol(decoded_image)

    final_set, final_method = resolve_final_set(
        footer_detection=footer_detection,
        symbol_detection=symbol_detection
    )

    return build_analysis_response(
        title_detection=title_detection,
        footer_detection=footer_detection,
        final_set=final_set,
        final_method=final_method
    )


def validate_uploaded_image(image: UploadFile) -> None:
    if not image.filename:
        raise HTTPException(status_code=400, detail="No file name provided.")

    if image.content_type not in ALLOWED_MIME_TYPES:
        raise HTTPException(
            status_code=400,
            detail=f"Unsupported file type: {image.content_type}"
        )


def decode_image(file_bytes: bytes) -> np.ndarray:
    np_buffer = np.frombuffer(file_bytes, dtype=np.uint8)
    decoded_image = cv2.imdecode(np_buffer, cv2.IMREAD_COLOR)

    if decoded_image is None:
        raise HTTPException(status_code=400, detail="Invalid or corrupted image file.")

    return decoded_image


def resolve_final_set(
    footer_detection: dict,
    symbol_detection: dict | None
) -> tuple[str | None, str | None]:
    footer_set_code = footer_detection.get("set_code")

    if footer_set_code:
        return footer_set_code, "footer_ocr"

    if symbol_detection and symbol_detection.get("accepted"):
        return symbol_detection.get("set"), "symbol_match"

    return None, None


def build_analysis_response(
    title_detection: dict,
    footer_detection: dict,
    final_set: str | None,
    final_method: str | None
) -> dict:
    return {
        "success": True,
        "title": title_detection.get("text", ""),
        "footer_detection": {
            "collector_number": footer_detection.get("collector_number"),
            "set_code": footer_detection.get("set_code"),
            "language": footer_detection.get("language"),
            "is_complete": footer_detection.get("is_complete", False),
        },
        "set_detection": {
            "final_set": final_set,
            "method": final_method,
        },
    }