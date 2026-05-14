import json
import os
import torch
from flask import Flask, request, jsonify
from transformers import BertTokenizer, BertForSequenceClassification

MODEL_DIR = os.path.join(os.path.dirname(__file__))

with open(os.path.join(MODEL_DIR, "config.json"), encoding="utf-8") as file_handle:
    cfg = json.load(file_handle)

THRESHOLD = cfg["threshold"]
MAX_LEN = cfg["max_len"]
DEVICE = torch.device("cuda" if torch.cuda.is_available() else "cpu")

print("Loading model...")
tokenizer = BertTokenizer.from_pretrained(MODEL_DIR)
bin_path = os.path.join(MODEL_DIR, "pytorch_model.bin")
use_safetensors = not os.path.exists(bin_path)
model = BertForSequenceClassification.from_pretrained(
    MODEL_DIR,
    use_safetensors=use_safetensors,
).to(DEVICE)
model.eval()
print(f"Ready on {DEVICE}. Threshold={THRESHOLD}")

app = Flask(__name__)


def infer(text):
    enc = tokenizer(
        text,
        max_length=MAX_LEN,
        padding="max_length",
        truncation=True,
        return_tensors="pt",
    ).to(DEVICE)
    with torch.no_grad():
        prob = torch.softmax(model(**enc).logits, dim=1)[0, 1].item()
    return {
        "toxic_probability": round(prob, 4),
        "label": "toxic" if prob >= THRESHOLD else "clean",
        "is_toxic": bool(prob >= THRESHOLD),
        "threshold": THRESHOLD,
    }


@app.route("/health")
def health():
    return jsonify({"status": "ok", "device": str(DEVICE)})


@app.route("/predict", methods=["POST"])
def predict():
    data = request.get_json(force=True)
    if not data or "text" not in data:
        return jsonify({"error": "Missing `text` field"}), 400
    return jsonify(infer(str(data["text"]).strip()))


@app.route("/predict/batch", methods=["POST"])
def predict_batch():
    data = request.get_json(force=True)
    texts = data.get("texts", [])
    if not isinstance(texts, list):
        return jsonify({"error": 'Send {"texts": [str, ...]}' }), 400
    return jsonify({"results": [infer(text) for text in texts[:50]]})


if __name__ == "__main__":
    app.run(host="127.0.0.1", port=5001, debug=False)
