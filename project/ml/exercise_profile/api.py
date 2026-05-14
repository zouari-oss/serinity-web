from __future__ import annotations

from pathlib import Path
from typing import Any

import joblib
from fastapi import FastAPI, HTTPException
from pydantic import BaseModel, Field


BASE_DIR = Path(__file__).resolve().parent
MODEL_PATH = BASE_DIR / "model.joblib"
IDENTIFIER_COLUMNS = {"user_id"}

app = FastAPI(title="Exercise Profile API")


class PredictionRequest(BaseModel):
    features: dict[str, float] = Field(default_factory=dict)


def load_bundle() -> dict[str, Any]:
    if not MODEL_PATH.exists():
        raise FileNotFoundError(f"Model file not found at {MODEL_PATH}. Run train_model.py first.")

    bundle = joblib.load(MODEL_PATH)
    if not isinstance(bundle, dict):
        raise ValueError("The model bundle is invalid.")

    return bundle


@app.get("/health")
def health() -> dict[str, str]:
    return {"status": "ok"}


@app.post("/predict")
def predict(request: PredictionRequest) -> dict[str, Any]:
    try:
        bundle = load_bundle()
    except Exception as exc:  # noqa: BLE001
        raise HTTPException(status_code=503, detail=str(exc)) from exc

    model = bundle["model"]
    feature_columns = bundle["feature_columns"]
    # Ignore technical identifiers if they are ever sent by mistake. The
    # prediction row must mirror training and use behavioral features only.
    request_features = {
        column: value
        for column, value in request.features.items()
        if column not in IDENTIFIER_COLUMNS
    }
    row = [float(request_features.get(column, 0.0)) for column in feature_columns]

    profile = str(model.predict([row])[0]).lower()
    response: dict[str, Any] = {"profile": profile}

    if hasattr(model, "predict_proba"):
        probabilities = model.predict_proba([row])[0]
        labels = list(model.classes_)
        response["probabilities"] = {
            str(label).lower(): round(float(score), 4)
            for label, score in zip(labels, probabilities, strict=True)
        }

    return response
