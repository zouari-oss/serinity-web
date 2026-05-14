import os

import joblib
from flask import Flask, jsonify, request
from flask_cors import CORS

from disease_rules import match_rule_based_disease
from psychology_guard import is_psychological_prompt, normalize_prompt


BASE_DIR = os.path.dirname(os.path.abspath(__file__))
MODEL_PATH = os.path.join(BASE_DIR, "model", "psychological_ai_bundle.pkl")

app = Flask(__name__)
CORS(app)

bundle = joblib.load(MODEL_PATH)
scope_model = bundle["scope_model"]
disease_model = bundle["disease_model"]
scope_threshold = float(bundle.get("scope_threshold", 0.30))
disease_confidence_threshold = float(bundle.get("disease_confidence_threshold", 0.45))
disease_margin_threshold = float(bundle.get("disease_margin_threshold", 0.12))


def get_available_disease_labels() -> list[str]:
    classifier = disease_model.named_steps.get("classifier") if hasattr(disease_model, "named_steps") else None

    if classifier is not None and hasattr(classifier, "classes_"):
        return [str(label) for label in classifier.classes_]

    if hasattr(disease_model, "classes_"):
        return [str(label) for label in disease_model.classes_]

    return []


def predict_disease(prompt: str) -> tuple[str, float | None, str, list[dict[str, float | str]]]:
    available_labels = get_available_disease_labels()

    rule_match = match_rule_based_disease(prompt, available_labels)
    if rule_match is not None:
        return rule_match.label, rule_match.confidence, "rules", [
            {"label": rule_match.label, "confidence": rule_match.confidence}
        ]

    if hasattr(disease_model, "predict_proba"):
        probabilities = disease_model.predict_proba([prompt])[0]
        labels = available_labels

        if not labels or len(labels) != len(probabilities):
            labels = [str(index) for index in range(len(probabilities))]

        ranked_predictions = sorted(
            zip(labels, probabilities),
            key=lambda item: float(item[1]),
            reverse=True,
        )
        top_predictions = [
            {
                "label": str(label),
                "confidence": float(probability),
            }
            for label, probability in ranked_predictions[:3]
        ]

        top_label, top_probability = ranked_predictions[0]
        second_probability = float(ranked_predictions[1][1]) if len(ranked_predictions) > 1 else 0.0
        top_probability = float(top_probability)
        confidence_margin = top_probability - second_probability

        if top_probability >= disease_confidence_threshold and confidence_margin >= disease_margin_threshold:
            return str(top_label), top_probability, "model", top_predictions

        return str(top_label), top_probability, "low_confidence_model", top_predictions

    label = str(disease_model.predict([prompt])[0])

    return label, None, "model", [{"label": label, "confidence": 1.0}]


@app.route("/health", methods=["GET"])
def health():
    return jsonify({
        "status": "ok",
        "message": "Psychological AI API is running"
    })


@app.route("/predict-from-prompt", methods=["POST"])
def predict_from_prompt():
    try:
        data = request.get_json(force=True) or {}
        prompt = normalize_prompt(data.get("prompt", ""))

        if prompt == "":
            return jsonify({
                "success": False,
                "message": "Please describe what you are feeling.",
                "mentionedText": "",
                "probableDisease": None,
                "medicalScope": "empty"
            }), 400

        if not is_psychological_prompt(scope_model, prompt, scope_threshold):
            return jsonify({
                "success": False,
                "message": "This assistant only answers psychological symptom prompts.",
                "mentionedText": prompt,
                "probableDisease": None,
                "medicalScope": "non_psychological"
            }), 200

        probable_disease, confidence, prediction_source, top_predictions = predict_disease(prompt)

        return jsonify({
            "success": True,
            "mentionedText": prompt,
            "probableDisease": probable_disease,
            "medicalScope": "psychological_only",
            "confidence": confidence,
            "predictionSource": prediction_source,
            "topPredictions": top_predictions,
        })

    except Exception as error:
        return jsonify({
            "success": False,
            "message": str(error),
            "mentionedText": "",
            "probableDisease": None,
            "medicalScope": "error"
        }), 500


if __name__ == "__main__":
    app.run(
        host="127.0.0.1",
        port=5001,
        debug=True
    )
