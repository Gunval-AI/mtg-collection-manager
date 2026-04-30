from fastapi import APIRouter, UploadFile, File
from app.services.card_analysis_service import analyze_uploaded_card_image

router = APIRouter()


@router.get("/health")
def health():
    return {"status": "ok"}


@router.post("/analyze-card")
async def analyze_card(image: UploadFile = File(...)):
    return await analyze_uploaded_card_image(image)