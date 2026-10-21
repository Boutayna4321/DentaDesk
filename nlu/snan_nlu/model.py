"""Load the trained intent model and turn its probabilities into a decision tier."""
from __future__ import annotations

from dataclasses import dataclass
from functools import lru_cache
from pathlib import Path

import joblib
import numpy as np

MODEL_PATH = Path(__file__).resolve().parent.parent / "models" / "intent.joblib"


@dataclass
class IntentModel:
    pipeline: object
    act_threshold: float
    clarify_threshold: float
    version: str
    metrics: dict

    def predict(self, text: str) -> dict:
        proba = self.pipeline.predict_proba([text])[0]
        order = np.argsort(-proba)
        classes = self.pipeline.classes_
        conf = float(proba[order[0]])
        tier = "act" if conf >= self.act_threshold else "clarify" if conf >= self.clarify_threshold else "handover"
        return {
            "intent": str(classes[order[0]]),
            "confidence": round(conf, 3),
            "tier": tier,
            "alternatives": [{"intent": str(classes[i]), "confidence": round(float(proba[i]), 3)} for i in order[:3]],
        }

    def vectorize(self, texts: list[str]):
        """Same features the classifier was trained on; reused for FAQ similarity."""
        return self.pipeline.named_steps["features"].transform(texts)


@lru_cache(maxsize=1)
def load() -> IntentModel:
    if not MODEL_PATH.exists():
        raise RuntimeError("No model yet. Run: python data/build_dataset.py && python train.py")
    bundle = joblib.load(MODEL_PATH)
    return IntentModel(bundle["pipeline"], bundle["act_threshold"], bundle["clarify_threshold"],
                       bundle["version"], bundle["metrics"])
