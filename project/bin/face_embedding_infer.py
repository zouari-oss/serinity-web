#!/usr/bin/env python3
import json
import sys


def fail(message: str, code: int = 1) -> None:
    sys.stderr.write(message + "\n")
    raise SystemExit(code)


def normalize(values):
    norm = sum(v * v for v in values) ** 0.5
    if norm <= 0:
        return []
    return [v / norm for v in values]


def main():
    try:
        payload = json.load(sys.stdin)
    except Exception as exc:
        fail(f"Invalid JSON payload: {exc}")

    model_path = payload.get("model_path")
    tensor = payload.get("tensor")
    if not isinstance(model_path, str) or not model_path:
        fail("Missing model_path in payload.")
    if not isinstance(tensor, list):
        fail("Missing tensor in payload.")

    try:
        import onnxruntime as ort
    except Exception as exc:
        fail(f"Python dependency error (onnxruntime): {exc}")

    try:
        import numpy as np
    except Exception as exc:
        fail(f"Python dependency error (numpy): {exc}")

    try:
        session = ort.InferenceSession(model_path, providers=["CPUExecutionProvider"])
        input_name = session.get_inputs()[0].name
        input_array = np.array(tensor, dtype=np.float32)
        outputs = session.run(None, {input_name: input_array})
    except Exception as exc:
        fail(f"ONNX inference failed: {exc}")

    if not outputs:
        fail("ONNX inference returned no outputs.")

    try:
        embedding = np.asarray(outputs[0], dtype=np.float32).reshape(-1).tolist()
        embedding = [float(v) for v in embedding]
    except Exception:
        fail("ONNX output contained non-numeric values.")

    embedding = normalize(embedding)
    if not embedding:
        fail("Failed to normalize embedding output.")

    json.dump({"embedding": embedding}, sys.stdout)


if __name__ == "__main__":
    main()
