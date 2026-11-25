"""Answer from the clinic's own FAQ, or say nothing.

Each FAQ entry has its question plus the other ways patients ask it. A message is
compared to all of them (cosine on character n-grams); the best entry wins only above
the threshold. Below it, the clinic answers by hand and can add the message as a new
variant, so the same question is answered alone next time.
"""
from __future__ import annotations

import hashlib
import json

import numpy as np
from sklearn.preprocessing import normalize as l2

from .model import IntentModel

THRESHOLD = 0.45
_cache: dict[str, tuple[list[int], np.ndarray]] = {}


def _index(model: IntentModel, faqs: list[dict]) -> tuple[list[int], np.ndarray]:
    key = hashlib.sha1((model.version + json.dumps(faqs, sort_keys=True)).encode()).hexdigest()
    if key not in _cache:
        ids, texts = [], []
        for f in faqs:
            for t in f["texts"]:
                if t.strip():
                    ids.append(f["id"])
                    texts.append(t)
        _cache.clear()
        _cache[key] = (ids, l2(model.vectorize(texts)) if texts else np.zeros((0, 0)))
    return _cache[key]


def match(model: IntentModel, text: str, faqs: list[dict]) -> dict | None:
    ids, matrix = _index(model, faqs)
    if not ids:
        return None
    scores = (matrix @ l2(model.vectorize([text])).T).toarray().ravel()
    best = int(scores.argmax())
    score = float(scores[best])
    return {"id": ids[best], "score": round(score, 3)} if score >= THRESHOLD else None
