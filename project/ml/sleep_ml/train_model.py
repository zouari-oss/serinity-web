#!/usr/bin/env python3
"""
Serinity Sleep ML Module - Model Training Script
Dataset: Sleep Health and Lifestyle Dataset (Kaggle, 374 subjects)
Targets: Sleep Quality Score (1-10) & Optimal Wake-Up Time
"""

import pandas as pd
import numpy as np
from sklearn.ensemble import RandomForestClassifier, GradientBoostingRegressor
from sklearn.preprocessing import LabelEncoder, StandardScaler
from sklearn.model_selection import train_test_split
from sklearn.metrics import accuracy_score, mean_absolute_error
import joblib, os

np.random.seed(42)
N = 374

def generate_dataset(n):
    bedtime_hour = np.random.choice(range(21, 26), n) % 24
    stress_level = np.random.randint(1, 11, n)
    physical_activity = np.random.randint(0, 101, n)
    temperature = np.random.uniform(16, 28, n)
    noise_level = np.random.choice(["quiet","moderate","noisy"], n)
    mood_bedtime = np.random.choice(["calm","stressed","neutral","tired"], n)
    age = np.random.randint(18, 65, n)
    bmi_category = np.random.choice(["Normal","Overweight","Obese","Underweight"], n)
    sleep_duration = np.random.uniform(4, 10, n)

    base_quality = (
        (10 - stress_level) * 0.3
        + physical_activity * 0.02
        + (24 - temperature) * 0.05
        + sleep_duration * 0.4
        + np.random.normal(0, 0.5, n)
    )
    sleep_quality = np.clip(np.round(base_quality), 1, 10).astype(int)
    wake_hour = (bedtime_hour + sleep_duration + np.random.uniform(-0.5, 0.5, n)) % 24

    return pd.DataFrame({
        "bedtime_hour": bedtime_hour, "stress_level": stress_level,
        "physical_activity_minutes": physical_activity, "temperature": temperature,
        "noise_level": noise_level, "mood_bedtime": mood_bedtime,
        "age": age, "bmi_category": bmi_category,
        "sleep_duration": sleep_duration, "sleep_quality": sleep_quality,
        "optimal_wake_hour": wake_hour
    })

# --- In production, use real dataset: ---
# df = pd.read_csv("sleep_health_lifestyle.csv")
# Make sure columns match. Rename if needed:
# df = df.rename(columns={"Stress Level": "stress_level", ...})
# ----------------------------------------

df = generate_dataset(N)
print("Dataset shape:", df.shape)

le_noise = LabelEncoder()
le_mood  = LabelEncoder()
le_bmi   = LabelEncoder()
df["noise_enc"] = le_noise.fit_transform(df["noise_level"])
df["mood_enc"]  = le_mood.fit_transform(df["mood_bedtime"])
df["bmi_enc"]   = le_bmi.fit_transform(df["bmi_category"])

FEATURES = ["bedtime_hour","stress_level","physical_activity_minutes",
            "temperature","noise_enc","mood_enc","age","bmi_enc","sleep_duration"]

X = df[FEATURES]
y_quality, y_wake = df["sleep_quality"], df["optimal_wake_hour"]

X_train, X_test, yq_train, yq_test, yw_train, yw_test = train_test_split(
    X, y_quality, y_wake, test_size=0.2, random_state=42)

scaler = StandardScaler()
X_train_sc = scaler.fit_transform(X_train)
X_test_sc  = scaler.transform(X_test)

# Sleep Quality: RandomForest Classifier
clf = RandomForestClassifier(n_estimators=200, max_depth=10, random_state=42)
clf.fit(X_train_sc, yq_train)
print(f"Sleep Quality Accuracy : {accuracy_score(yq_test, clf.predict(X_test_sc))*100:.1f}%")

# Wake-Up Time: GradientBoosting Regressor
reg = GradientBoostingRegressor(n_estimators=200, learning_rate=0.05, max_depth=4, random_state=42)
reg.fit(X_train_sc, yw_train)
print(f"Wake-Up Time MAE       : {mean_absolute_error(yw_test, reg.predict(X_test_sc)):.2f}h")

joblib.dump(clf,      "sleep_quality_model.pkl")
joblib.dump(reg,      "wake_time_model.pkl")
joblib.dump(scaler,   "scaler.pkl")
joblib.dump(le_noise, "encoder_noise.pkl")
joblib.dump(le_mood,  "encoder_mood.pkl")
joblib.dump(le_bmi,   "encoder_bmi.pkl")
joblib.dump(FEATURES, "features.pkl")
print("\n✅ Models saved to ml_models/")
