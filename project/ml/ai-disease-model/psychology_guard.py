import re
import unicodedata


PSYCHOLOGICAL_HINT_PATTERNS = (
    "anxiety",
    "anxious",
    "panic",
    "depress",
    "sad",
    "hopeless",
    "stress",
    "stressed",
    "trauma",
    "ptsd",
    "ocd",
    "intrusive thoughts",
    "compulsion",
    "paranoid",
    "paranoia",
    "hear voices",
    "hallucination",
    "bipolar",
    "manic",
    "suicid",
    "self harm",
    "insomnia",
    "cannot sleep",
    "can't sleep",
    "mental health",
    "psychiatric",
    "overthinking",
    "burnout",
)


def normalize_prompt(prompt: str) -> str:
    normalized = unicodedata.normalize("NFKD", str(prompt))
    normalized = normalized.encode("ascii", "ignore").decode("ascii")
    normalized = normalized.lower().strip()

    return re.sub(r"\s+", " ", normalized)


def has_psychological_keywords(prompt: str) -> bool:
    normalized = normalize_prompt(prompt)

    return any(pattern in normalized for pattern in PSYCHOLOGICAL_HINT_PATTERNS)


def is_psychological_prompt(scope_model, prompt: str, threshold: float = 0.30) -> bool:
    """
    Decides if a prompt is psychological using the trained scope model.
    Falls back to keyword hints for short natural-language prompts.
    """

    normalized_prompt = normalize_prompt(prompt)

    if normalized_prompt == "":
        return False

    if hasattr(scope_model, "predict_proba"):
        classes = list(scope_model.classes_)

        if "psychological" in classes:
            psychological_index = classes.index("psychological")
            psychological_probability = float(
                scope_model.predict_proba([normalized_prompt])[0][psychological_index]
            )

            if psychological_probability >= threshold:
                return True

    scope_prediction = scope_model.predict([normalized_prompt])[0]

    if scope_prediction == "psychological":
        return True

    return has_psychological_keywords(normalized_prompt)
