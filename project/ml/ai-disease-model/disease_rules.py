from __future__ import annotations

from dataclasses import dataclass

from psychology_guard import normalize_prompt


@dataclass(frozen=True)
class RuleMatch:
    label: str
    confidence: float
    reason: str


DISEASE_RULES = (
    {
        "label": "psychotic disorder",
        "confidence": 0.95,
        "patterns": (
            "hear voices",
            "hearing voices",
            "see things",
            "seeing things",
            "hallucination",
            "hallucinations",
            "disconnected from reality",
            "people are watching me",
            "paranoid",
        ),
        "reason": "psychosis markers",
    },
    {
        "label": "post-traumatic stress disorder (ptsd)",
        "confidence": 0.93,
        "patterns": (
            "flashbacks",
            "nightmares",
            "traumatic event",
            "trauma memories",
            "avoid reminders",
            "cannot feel safe",
        ),
        "reason": "ptsd markers",
    },
    {
        "label": "obsessive compulsive disorder (ocd)",
        "confidence": 0.91,
        "patterns": (
            "intrusive thoughts",
            "obsessive thoughts",
            "compulsions",
            "repeated checking",
            "repeat actions many times",
            "cannot stop repeated thoughts",
        ),
        "reason": "ocd markers",
    },
    {
        "label": "bipolar disorder",
        "confidence": 0.90,
        "patterns": (
            "manic episodes",
            "extremely energetic",
            "very happy energetic to very depressed",
            "mood changes",
            "switch from extreme energy to deep sadness",
        ),
        "reason": "bipolar markers",
    },
    {
        "label": "primary insomnia",
        "confidence": 0.88,
        "patterns": (
            "cannot sleep",
            "cant sleep",
            "can't sleep",
            "difficulty sleeping",
            "wake up tired",
            "waking up at night",
            "insomnia",
        ),
        "reason": "insomnia markers",
    },
    {
        "label": "social phobia",
        "confidence": 0.87,
        "patterns": (
            "speak in public",
            "meet people",
            "social situations",
            "feel embarrassed and anxious",
            "panic when i have to talk to people",
        ),
        "reason": "social anxiety markers",
    },
    {
        "label": "panic disorder",
        "confidence": 0.86,
        "patterns": (
            "panic attacks",
            "sudden fear panic",
            "fear another one will happen",
            "intense panic",
            "heart races",
        ),
        "reason": "panic markers",
    },
    {
        "label": "anxiety",
        "confidence": 0.82,
        "patterns": (
            "anxious",
            "nervous",
            "worry too much",
            "overthinking",
            "cannot calm down",
            "worried and afraid all the time",
        ),
        "reason": "anxiety markers",
    },
    {
        "label": "depression",
        "confidence": 0.82,
        "patterns": (
            "feel sad",
            "hopeless",
            "worthless",
            "lost interest in everything",
            "nothing makes me happy",
            "feel empty",
            "no energy",
        ),
        "reason": "depression markers",
    },
    {
        "label": "attention deficit hyperactivity disorder (adhd)",
        "confidence": 0.80,
        "patterns": (
            "cannot focus",
            "attention problems",
            "cannot concentrate",
            "restless impulsive",
            "distracted and restless",
        ),
        "reason": "adhd markers",
    },
    {
        "label": "eating disorder",
        "confidence": 0.80,
        "patterns": (
            "afraid of gaining weight",
            "avoid eating",
            "unhealthy eating habits",
            "worry too much about my body",
        ),
        "reason": "eating disorder markers",
    },
    {
        "label": "alcohol abuse",
        "confidence": 0.78,
        "patterns": (
            "cannot stop drinking",
            "drinking alcohol even when it harms me",
            "alcohol is affecting my life",
        ),
        "reason": "alcohol abuse markers",
    },
    {
        "label": "drug abuse",
        "confidence": 0.78,
        "patterns": (
            "cannot stop using drugs",
            "drug use is affecting my life",
            "substance use is affecting my mood",
        ),
        "reason": "substance abuse markers",
    },
)


def resolve_rule_label(candidate: str, available_labels: list[str]) -> str | None:
    normalized_candidate = normalize_prompt(candidate)
    normalized_labels = {
        normalize_prompt(label): label
        for label in available_labels
    }

    if normalized_candidate in normalized_labels:
        return normalized_labels[normalized_candidate]

    for normalized_label, label in normalized_labels.items():
        if normalized_candidate in normalized_label or normalized_label in normalized_candidate:
            return label

    return None


def match_rule_based_disease(prompt: str, available_labels: list[str]) -> RuleMatch | None:
    normalized_prompt = normalize_prompt(prompt)

    for rule in DISEASE_RULES:
        matched_patterns = [
            pattern for pattern in rule["patterns"]
            if pattern in normalized_prompt
        ]

        minimum_matches = 1 if len(rule["patterns"]) <= 3 else 2
        if len(matched_patterns) < minimum_matches:
            continue

        resolved_label = resolve_rule_label(rule["label"], available_labels)

        if resolved_label is None:
            continue

        return RuleMatch(
            label=resolved_label,
            confidence=float(rule["confidence"]),
            reason=str(rule["reason"]),
        )

    return None
