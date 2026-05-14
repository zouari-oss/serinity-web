from __future__ import annotations

import csv
import json
from pathlib import Path

import joblib
from sklearn.ensemble import RandomForestClassifier
from sklearn.metrics import accuracy_score
from sklearn.model_selection import train_test_split


BASE_DIR = Path(__file__).resolve().parent
DATASET_PATH = BASE_DIR / "dataset.csv"
FEATURE_COLUMNS_PATH = BASE_DIR / "feature_columns.json"
MODEL_PATH = BASE_DIR / "model.joblib"
IDENTIFIER_COLUMNS = {"user_id"}


def infer_label(row: dict[str, str]) -> str:
    total_sessions = int(float(row.get("total_sessions", 0) or 0))
    completed_sessions = int(float(row.get("completed_sessions", 0) or 0))
    completion_rate = float(row.get("completion_rate", 0) or 0)
    avg_duration = float(row.get("avg_duration_minutes", 0) or 0)
    avg_engagement = float(row.get("avg_engagement_ratio", 0) or 0)
    calm_ratio = float(row.get("calm_type_ratio", 0) or 0)
    active_ratio = float(row.get("active_type_ratio", 0) or 0)
    feedback_positive = float(row.get("feedback_positive_score", 0) or 0)

    if total_sessions < 3:
        return "balanced"
    if calm_ratio >= 0.5 and avg_duration <= 12 and avg_engagement <= 0.9:
        return "calm"
    # Mirror the Symfony rule so Python fallback labeling stays consistent with
    # the labels generated from real Exercise module behavior.
    if (
        active_ratio >= 0.45
        and avg_duration >= 12
        and avg_engagement >= 0.75
        and completion_rate >= 0.45
        and completed_sessions >= 2
    ):
        return "active"
    if feedback_positive >= 0.7 and avg_engagement >= 0.85 and completion_rate >= 0.6:
        return "active"

    return "balanced"


def load_feature_columns() -> list[str]:
    with FEATURE_COLUMNS_PATH.open("r", encoding="utf-8") as handle:
        columns = json.load(handle)

    if not isinstance(columns, list) or not all(isinstance(column, str) for column in columns):
        raise ValueError("feature_columns.json must contain a JSON array of strings.")

    # Keep technical identifiers such as user_id out of X so the model learns
    # exercise behavior rather than memorizing a person's identity.
    sanitized_columns = [column for column in columns if column not in IDENTIFIER_COLUMNS]
    if len(sanitized_columns) != len(columns):
        raise ValueError("feature_columns.json must not contain identifier columns such as user_id.")
    if not sanitized_columns:
        raise ValueError("feature_columns.json must contain at least one behavioral feature column.")
    if any(column.strip() == "" for column in sanitized_columns):
        raise ValueError("feature_columns.json must not contain empty feature names.")

    # Behavioral metrics such as completion rate, engagement, exercise-type
    # ratios, and feedback scores are intentionally allowed here. They are not
    # technical identifiers: they describe real user exercise behavior, which
    # is exactly what this student-project model is meant to learn from.

    return sanitized_columns


def load_dataset(feature_columns: list[str]) -> tuple[list[list[float]], list[str]]:
    if not DATASET_PATH.exists():
        raise FileNotFoundError(f"Dataset not found at {DATASET_PATH}. Run the Symfony export command first.")

    rows: list[list[float]] = []
    labels: list[str] = []

    with DATASET_PATH.open("r", encoding="utf-8", newline="") as handle:
        reader = csv.DictReader(handle)
        for row in reader:
            # Even if user_id is exported for debugging in the CSV, only the
            # approved behavioral feature columns are loaded into X.
            rows.append([float(row.get(column, 0) or 0) for column in feature_columns])
            label = (row.get("target_label") or "").strip().lower()
            labels.append(label if label else infer_label(row))

    if not rows:
        raise ValueError("Dataset is empty. Export more exercise history before training.")

    return rows, labels


def train() -> None:
    feature_columns = load_feature_columns()
    x_rows, y_labels = load_dataset(feature_columns)

    model = RandomForestClassifier(
        n_estimators=80,
        max_depth=5,
        random_state=42,
    )

    if len(x_rows) >= 6 and len(set(y_labels)) > 1:
        x_train, x_test, y_train, y_test = train_test_split(
            x_rows,
            y_labels,
            test_size=0.3,
            random_state=42,
            stratify=y_labels,
        )
        model.fit(x_train, y_train)
        predictions = model.predict(x_test)
        accuracy = accuracy_score(y_test, predictions)
        print(f"Validation accuracy: {accuracy:.3f}")
    else:
        model.fit(x_rows, y_labels)
        print("Dataset is small, so the model was trained on all rows.")

    joblib.dump(
        {
            "model": model,
            "feature_columns": feature_columns,
            "labels": sorted(set(y_labels)),
        },
        MODEL_PATH,
    )
    print(f"Saved model to {MODEL_PATH}")


if __name__ == "__main__":
    train()
