import os
import re
import unicodedata
from glob import glob

import joblib
import pandas as pd
from sklearn.feature_extraction.text import TfidfVectorizer
from sklearn.linear_model import LogisticRegression
from sklearn.metrics import accuracy_score, classification_report
from sklearn.model_selection import train_test_split
from sklearn.pipeline import FeatureUnion, Pipeline


DATASET_PATHS = [
    "Final_Augmented_dataset_Diseases_and_Symptoms.csv",
    "Final_Augmented_dataset_Diseases_and_Symptoms",
]

BASE_DIR = os.path.dirname(os.path.abspath(__file__))
MODEL_DIR = "model"
MODEL_DIR_PATH = os.path.join(BASE_DIR, MODEL_DIR)
MODEL_PATH = os.path.join(MODEL_DIR_PATH, "psychological_ai_bundle.pkl")

os.makedirs(MODEL_DIR_PATH, exist_ok=True)


PSYCHOLOGICAL_DISEASE_KEYWORDS = [
    "depression",
    "depressive",
    "anxiety",
    "panic",
    "bipolar",
    "schizophrenia",
    "psychosis",
    "psychotic",
    "ptsd",
    "post traumatic",
    "ocd",
    "obsessive",
    "compulsive",
    "phobia",
    "burnout",
    "insomnia",
    "sleep disorder",
    "eating disorder",
    "anorexia",
    "bulimia",
    "adhd",
    "autism",
    "personality disorder",
    "borderline",
    "dementia",
    "delirium",
    "substance abuse",
    "addiction",
    "alcohol abuse",
    "drug abuse",
    "suicidal",
    "mania",
    "manic",
    "mood disorder",
    "mental",
    "psychiatric",
]


EXCLUDED_DISEASE_KEYWORDS = [
    "acute respiratory distress syndrome",
    "ards",
    "birth trauma",
    "stress incontinence",
    "poisoning",
    "open wound",
    "fever",
    "infection",
    "diabetes",
    "ulcer",
    "chest",
    "knee",
    "cheek",
]


def find_dataset_path() -> str:
    search_roots = [
        BASE_DIR,
        os.getcwd(),
        os.path.dirname(BASE_DIR),
    ]

    for root in search_roots:
        for path in DATASET_PATHS:
            absolute_path = os.path.join(root, path)

            if os.path.exists(absolute_path):
                return absolute_path

    glob_patterns = [
        "*Diseases*Symptoms*.csv",
        "*dataset*Diseases*Symptoms*.csv",
        "*.csv",
    ]

    for root in search_roots:
        for pattern in glob_patterns:
            matches = sorted(
                candidate
                for candidate in glob(os.path.join(root, pattern))
                if os.path.isfile(candidate)
            )

            if matches:
                return matches[0]

    raise FileNotFoundError(
        "Dataset not found. Put Final_Augmented_dataset_Diseases_and_Symptoms.csv "
        "in the same folder as train_model.py or project/ai-disease-model"
    )


def detect_target_column(df: pd.DataFrame) -> str:
    possible_targets = [
        "disease",
        "diseases",
        "Disease",
        "Diseases",
        "prognosis",
        "Prognosis",
        "diagnosis",
        "Diagnosis",
    ]

    for column in possible_targets:
        if column in df.columns:
            return column

    raise ValueError("Could not detect disease column.")


def normalize_text(value: str) -> str:
    normalized = unicodedata.normalize("NFKD", str(value))
    normalized = normalized.encode("ascii", "ignore").decode("ascii")
    normalized = normalized.lower().replace("_", " ")
    normalized = re.sub(r"[^a-z0-9\s]", " ", normalized)

    return " ".join(normalized.strip().split())


def is_psychological_disease(disease: str) -> bool:
    normalized = normalize_text(disease)

    if any(keyword in normalized for keyword in EXCLUDED_DISEASE_KEYWORDS):
        return False

    return any(keyword in normalized for keyword in PSYCHOLOGICAL_DISEASE_KEYWORDS)


def extract_active_symptoms(
    row: pd.Series,
    feature_columns: list[str],
    max_symptoms: int | None = None
) -> list[str]:
    symptoms = []

    for column in feature_columns:
        value = row[column]

        try:
            numeric_value = float(value)
        except (TypeError, ValueError):
            numeric_value = 0.0

        if numeric_value > 0:
            symptoms.append(normalize_text(column))

    if max_symptoms is not None:
        return symptoms[:max_symptoms]

    return symptoms


def format_symptom_phrase(symptoms: list[str]) -> str:
    if not symptoms:
        return ""

    if len(symptoms) == 1:
        return symptoms[0]

    if len(symptoms) == 2:
        return f"{symptoms[0]} and {symptoms[1]}"

    return ", ".join(symptoms[:-1]) + f", and {symptoms[-1]}"


def row_to_text(row: pd.Series, feature_columns: list[str]) -> str:
    symptoms = extract_active_symptoms(row, feature_columns)

    return normalize_text(" ".join(symptoms))


def row_to_prompt_variants(row: pd.Series, feature_columns: list[str]) -> list[str]:
    symptoms = extract_active_symptoms(row, feature_columns, max_symptoms=6)

    if not symptoms:
        return []

    phrase = format_symptom_phrase(symptoms)
    short_phrase = format_symptom_phrase(symptoms[:4])

    variants = [
        f"i have {phrase}",
        f"my symptoms are {short_phrase}",
    ]

    if len(symptoms) >= 3:
        variants.append(f"i feel {short_phrase}")

    return [normalize_text(variant) for variant in variants]


def create_text_model() -> Pipeline:
    return Pipeline([
        (
            "features",
            FeatureUnion([
                (
                    "word_tfidf",
                    TfidfVectorizer(
                        lowercase=True,
                        ngram_range=(1, 3),
                        max_features=40000,
                        sublinear_tf=True,
                        min_df=1,
                    )
                ),
                (
                    "char_tfidf",
                    TfidfVectorizer(
                        analyzer="char_wb",
                        ngram_range=(3, 5),
                        max_features=20000,
                        sublinear_tf=True,
                        min_df=1,
                    )
                ),
            ])
        ),
        (
            "classifier",
            LogisticRegression(
                max_iter=3000,
                C=2.0,
                class_weight="balanced",
                solver="lbfgs"
            )
        )
    ])


def add_natural_prompt_examples(
    psychological_df: pd.DataFrame,
    target_column: str
) -> pd.DataFrame:
    """
    Adds natural human prompts so the model understands real user language.
    This keeps api.py minimal and lets the model learn common phrasing.
    """

    existing_diseases = set(
        psychological_df[target_column]
        .astype(str)
        .str.lower()
        .str.strip()
    )

    examples = [
        ("anxiety", "i feel very anxious nervous worried and afraid all the time"),
        ("anxiety", "i feel anxious and i cannot calm down"),
        ("anxiety", "i worry too much and feel nervous every day"),
        ("anxiety", "i feel very anxious and i have panic attacks every night"),
        ("panic attack", "i have panic attacks every night"),
        ("panic attack", "my heart races and i feel intense panic"),
        ("panic disorder", "i keep having panic attacks and fear another one will happen"),
        ("panic disorder", "i feel sudden fear panic and intense anxiety"),
        ("depression", "i feel sad hopeless tired and empty"),
        ("depression", "i lost interest in everything and nothing makes me happy"),
        ("depression", "i feel depressed worthless and i cry often"),
        ("depression", "i feel sad hopeless tired and i lost interest in everything"),
        ("depression", "i do not enjoy anything anymore and feel empty"),
        ("depression", "i feel down every day and have no energy"),
        ("postpartum depression", "after giving birth i feel depressed hopeless and tired"),
        ("primary insomnia", "i cannot sleep at night and i wake up tired"),
        ("primary insomnia", "i have insomnia and difficulty sleeping every night"),
        ("primary insomnia", "i cannot sleep i feel stressed and exhausted all day"),
        ("primary insomnia", "i keep waking up at night and cannot rest"),
        ("acute stress reaction", "i feel stressed overwhelmed shocked and unable to relax"),
        ("acute stress reaction", "after a traumatic event i feel stressed anxious and scared"),
        ("post-traumatic stress disorder (ptsd)", "i have flashbacks nightmares fear and trauma memories"),
        ("post-traumatic stress disorder (ptsd)", "i keep remembering the traumatic event and cannot feel safe"),
        ("post-traumatic stress disorder (ptsd)", "i have nightmares flashbacks and avoid reminders of what happened"),
        ("obsessive compulsive disorder (ocd)", "i have intrusive thoughts and compulsions"),
        ("obsessive compulsive disorder (ocd)", "i repeat actions many times because of obsessive thoughts"),
        ("obsessive compulsive disorder (ocd)", "i cannot stop repeated thoughts and repeated checking"),
        ("psychotic disorder", "i hear voices and see things that others do not see"),
        ("psychotic disorder", "i feel paranoid and believe people are watching me"),
        ("psychotic disorder", "i hear things and feel disconnected from reality"),
        ("schizophrenia", "i hear voices feel paranoid and have strange beliefs"),
        ("schizophrenia", "i see things hear voices and feel disconnected from reality"),
        ("bipolar disorder", "my mood changes from very happy energetic to very depressed"),
        ("bipolar disorder", "i have manic episodes and then depression"),
        ("bipolar disorder", "sometimes i feel extremely energetic and then very sad"),
        ("bipolar disorder", "i switch from extreme energy to deep sadness"),
        ("attention deficit hyperactivity disorder (adhd)", "i cannot focus i am distracted and restless"),
        ("attention deficit hyperactivity disorder (adhd)", "i have attention problems and cannot concentrate"),
        ("attention deficit hyperactivity disorder (adhd)", "i am restless impulsive and cannot stay focused"),
        ("social phobia", "i feel intense fear when i meet people or speak in public"),
        ("social phobia", "i avoid social situations because i feel embarrassed and anxious"),
        ("social phobia", "i panic when i have to talk to people"),
        ("eating disorder", "i am afraid of gaining weight and i avoid eating"),
        ("eating disorder", "i have unhealthy eating habits and worry too much about my body"),
        ("alcohol abuse", "i cannot stop drinking alcohol even when it harms me"),
        ("drug abuse", "i cannot stop using drugs and it affects my life"),
        ("substance-related mental disorder", "substance use is affecting my mood and behavior"),
    ]

    rows = []

    for disease, text in examples:
        if disease.lower().strip() in existing_diseases:
            rows.append({
                "text": normalize_text(text),
                target_column: disease,
                "scope": "psychological",
            })

    if not rows:
        print("No natural prompt examples added. Disease names did not match dataset labels.")
        return psychological_df

    augmented_df = pd.DataFrame(rows)

    print(f"Added {len(augmented_df)} natural psychological prompt examples.")

    return pd.concat([psychological_df, augmented_df], ignore_index=True)


def clean_training_frame(df: pd.DataFrame, text_column: str, label_column: str) -> pd.DataFrame:
    cleaned = df.copy()
    cleaned[text_column] = cleaned[text_column].astype(str).map(normalize_text)
    cleaned[label_column] = cleaned[label_column].astype(str).str.strip()
    cleaned = cleaned[cleaned[text_column] != ""]
    cleaned = cleaned.drop_duplicates(subset=[text_column, label_column]).reset_index(drop=True)

    return cleaned


def oversample_small_classes(df: pd.DataFrame, text_column: str, label_column: str, min_examples: int = 8) -> pd.DataFrame:
    frames = [df]

    for _, group in df.groupby(label_column):
        group_size = len(group)

        if group_size >= min_examples:
            continue

        repetitions = (min_examples + group_size - 1) // group_size
        expanded_group = pd.concat([group] * repetitions, ignore_index=True).head(min_examples)
        frames.append(expanded_group)

    return pd.concat(frames, ignore_index=True).reset_index(drop=True)


def build_prompt_augmented_frame(
    df: pd.DataFrame,
    feature_columns: list[str],
    target_column: str,
    scope_column: str | None = None
) -> pd.DataFrame:
    rows = []

    for _, row in df.iterrows():
        prompt_variants = row_to_prompt_variants(row, feature_columns)

        for variant in prompt_variants:
            new_row = {
                "text": variant,
                target_column: row[target_column],
            }

            if scope_column is not None and scope_column in row:
                new_row[scope_column] = row[scope_column]

            rows.append(new_row)

    if not rows:
        return pd.DataFrame(columns=["text", target_column] + ([scope_column] if scope_column else []))

    return pd.DataFrame(rows)


def main() -> None:
    print("Loading dataset...")

    dataset_path = find_dataset_path()
    print(f"Using dataset: {dataset_path}")

    df = pd.read_csv(dataset_path)
    df.columns = [str(column).strip() for column in df.columns]
    df = df.dropna(how="all").fillna(0)

    target_column = detect_target_column(df)

    print(f"Target column detected: {target_column}")
    print(f"Original dataset shape: {df.shape}")

    df[target_column] = df[target_column].astype(str)

    feature_columns = [
        column for column in df.columns
        if column != target_column
    ]

    print("Converting symptom columns to text...")

    df = df.copy()
    df["text"] = df.apply(
        lambda row: row_to_text(row, feature_columns),
        axis=1
    )
    df = clean_training_frame(df, "text", target_column)

    df["scope"] = df[target_column].apply(
        lambda disease: "psychological"
        if is_psychological_disease(disease)
        else "non_psychological"
    )

    print("Scope distribution:")
    print(df["scope"].value_counts())

    prompt_augmented_scope_df = build_prompt_augmented_frame(
        df,
        feature_columns,
        target_column,
        "scope"
    )
    if not prompt_augmented_scope_df.empty:
        df = pd.concat(
            [df, prompt_augmented_scope_df],
            ignore_index=True
        )
        df = clean_training_frame(df, "text", target_column)

    psychological_df = df[df["scope"] == "psychological"].copy()

    if psychological_df.empty:
        raise ValueError("No psychological diseases found.")

    disease_counts = psychological_df[target_column].value_counts()
    valid_diseases = disease_counts[disease_counts >= 2].index

    psychological_df = psychological_df[
        psychological_df[target_column].isin(valid_diseases)
    ].copy()

    psychological_df = add_natural_prompt_examples(
        psychological_df,
        target_column
    )
    prompt_augmented_psychological_df = build_prompt_augmented_frame(
        psychological_df,
        feature_columns,
        target_column,
        "scope"
    )
    if not prompt_augmented_psychological_df.empty:
        psychological_df = pd.concat(
            [psychological_df, prompt_augmented_psychological_df],
            ignore_index=True
        )
    psychological_df = clean_training_frame(psychological_df, "text", target_column)
    psychological_df = oversample_small_classes(psychological_df, "text", target_column, min_examples=12)

    natural_scope_examples = psychological_df[["text", target_column, "scope"]].tail(80)

    df = pd.concat(
        [
            df[["text", target_column, "scope"]],
            natural_scope_examples,
        ],
        ignore_index=True
    )
    df = clean_training_frame(df, "text", target_column)

    print(f"Psychological dataset shape: {psychological_df.shape}")
    print(f"Number of psychological diseases: {psychological_df[target_column].nunique()}")

    print("Training scope model...")

    scope_x_train, scope_x_test, scope_y_train, scope_y_test = train_test_split(
        df["text"],
        df["scope"],
        test_size=0.2,
        random_state=42,
        stratify=df["scope"]
    )

    scope_model = create_text_model()
    scope_model.fit(scope_x_train, scope_y_train)

    scope_predictions = scope_model.predict(scope_x_test)

    print("Scope model accuracy:")
    print(accuracy_score(scope_y_test, scope_predictions))
    print(classification_report(scope_y_test, scope_predictions, zero_division=0))

    print("Training psychological disease model...")

    disease_x_train, disease_x_test, disease_y_train, disease_y_test = train_test_split(
        psychological_df["text"],
        psychological_df[target_column],
        test_size=0.2,
        random_state=42,
        stratify=psychological_df[target_column]
    )

    disease_model = create_text_model()
    disease_model.fit(disease_x_train, disease_y_train)

    disease_predictions = disease_model.predict(disease_x_test)

    print("Disease model accuracy:")
    print(accuracy_score(disease_y_test, disease_predictions))
    print(classification_report(disease_y_test, disease_predictions, zero_division=0))

    bundle = {
        "scope_model": scope_model,
        "disease_model": disease_model,
        "scope_threshold": 0.30,
        "disease_confidence_threshold": 0.45,
        "disease_margin_threshold": 0.12,
        "model_version": "3",
    }

    joblib.dump(bundle, MODEL_PATH)

    print("AI bundle saved successfully.")
    print(f"Created: {MODEL_PATH}")


if __name__ == "__main__":
    main()
