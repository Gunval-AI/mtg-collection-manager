from fastapi import FastAPI
from app.api.routes import router

app = FastAPI(title="MTG Recognition Service")

app.include_router(router)