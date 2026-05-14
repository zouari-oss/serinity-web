#!/usr/bin/env python3
"""
Serinity Sleep ML API - Flask REST Server
Run:  python app.py
URL:  http://127.0.0.1:5001
POST /api/sleep/predict  →  predictions
GET  /api/sleep/health   →  status check
"""

from flask import Flask, request, jsonify
from flask_cors import CORS
import joblib, numpy as np, os

app = Flask(__name__)
CORS(app)

MODEL_DIR = os.path.join(os.path.dirname(__file__))

clf      = joblib.load(f"{MODEL_DIR}/sleep_quality_model.pkl")
reg      = joblib.load(f"{MODEL_DIR}/wake_time_model.pkl")
scaler   = joblib.load(f"{MODEL_DIR}/scaler.pkl")
le_noise = joblib.load(f"{MODEL_DIR}/encoder_noise.pkl")
le_mood  = joblib.load(f"{MODEL_DIR}/encoder_mood.pkl")
le_bmi   = joblib.load(f"{MODEL_DIR}/encoder_bmi.pkl")
FEATURES = joblib.load(f"{MODEL_DIR}/features.pkl")

REQUIRED = ["bedtime_hour","stress_level","physical_activity_minutes",
            "temperature","noise_level","mood_bedtime","age","bmi_category","sleep_duration"]

def encode_safe(encoder, value, fallback=0):
    try:
        return int(encoder.transform([value])[0])
    except ValueError:
        return fallback

def quality_info(score):
    table = [
        (1,3,  "Très mauvaise","#e74c3c","😴","Votre sommeil est très perturbé. Consultez un spécialiste."),
        (4,5,  "Mauvaise",     "#e67e22","😪","Améliorez votre environnement et limitez les écrans."),
        (6,7,  "Moyenne",      "#f39c12","😐","Essayez une routine relaxation 30 min avant de dormir."),
        (8,9,  "Bonne",        "#27ae60","😊","Continuez ! Maintenez un horaire de sommeil régulier."),
        (10,10,"Excellente",   "#1abc9c","🌟","Hygiène de sommeil optimale. Continuez ainsi !"),
    ]
    for lo,hi,label,color,icon,advice in table:
        if lo <= score <= hi:
            return {"score":score,"label":label,"color":color,"icon":icon,"advice":advice}
    return {"score":score,"label":"?","color":"#95a5a6","icon":"❓","advice":""}

def tips(data):
    out = []
    if float(data.get("stress_level",5)) >= 7:
        out.append("🧘 Méditation ou respiration 4-7-8 avant de dormir.")
    if data.get("noise_level") == "noisy":
        out.append("🔇 Bouchons d'oreilles ou bruit blanc recommandés.")
    t = float(data.get("temperature",20))
    if t > 24:
        out.append("❄️  Chambre trop chaude — idéal : 18-20°C.")
    elif t < 17:
        out.append("🌡️  Chambre trop froide — idéal : 18-20°C.")
    if float(data.get("physical_activity_minutes",30)) < 20:
        out.append("🏃 30 min d'activité physique par jour améliorent le sommeil.")
    if data.get("mood_bedtime") in ["stressed","anxious"]:
        out.append("📖 Lecture légère ou musique douce pour calmer l'esprit.")
    return out or ["✅ Vos habitudes semblent saines. Maintenez-les !"]

@app.route("/api/sleep/predict", methods=["POST"])
def predict():
    data = request.get_json()
    if not data:
        return jsonify({"error":"No JSON body"}), 400
    missing = [f for f in REQUIRED if f not in data]
    if missing:
        return jsonify({"error":f"Champs manquants: {missing}"}), 422
    try:
        X = np.array([[
            float(data["bedtime_hour"]),
            float(data["stress_level"]),
            float(data["physical_activity_minutes"]),
            float(data["temperature"]),
            encode_safe(le_noise, data["noise_level"]),
            encode_safe(le_mood,  data["mood_bedtime"]),
            float(data["age"]),
            encode_safe(le_bmi,   data["bmi_category"]),
            float(data["sleep_duration"]),
        ]])
        Xsc = scaler.transform(X)
        q   = int(clf.predict(Xsc)[0])
        w   = float(reg.predict(Xsc)[0])
        wh  = int(w) % 24
        wm  = int((w % 1) * 60)
        imp = clf.feature_importances_
        top = [{"feature": FEATURES[i], "importance": round(float(imp[i]),3)}
               for i in np.argsort(imp)[::-1][:3]]
        return jsonify({
            "success":       True,
            "sleep_quality": quality_info(q),
            "wake_time":     {"time":f"{wh:02d}h{wm:02d}","hour":wh,"minute":wm,
                              "cycles":round(float(data["sleep_duration"])/1.5,1)},
            "insights":      {"top_factors":top,"tips":tips(data)},
        })
    except Exception as e:
        return jsonify({"error":str(e)}), 500

@app.route("/api/sleep/health", methods=["GET"])
def health():
    return jsonify({"status":"ok","service":"Serinity Sleep ML API"})

if __name__ == "__main__":
    app.run(host="0.0.0.0", port=5001, debug=False)
