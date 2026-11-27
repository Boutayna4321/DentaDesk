"""Train the intent classifier and write the evaluation report.

1. Compare three feature sets with 5-fold cross-validation grouped by template
   (variants of one template never sit on both sides of a fold):
     word n-grams | character n-grams | both
2. From the out-of-fold probabilities, pick two thresholds:
     act      the assistant acts alone above it (>= 90% precision)
     clarify  between clarify and act it asks the patient with buttons (top 3 intents);
              below clarify it hands over to the clinic
3. Refit on everything, score once on the handwritten test set, save the model.

Usage: python train.py   ->  models/intent.joblib, reports/metrics.md
"""
from __future__ import annotations

import json
import warnings
from datetime import datetime, timezone
from pathlib import Path

import joblib
import numpy as np
from sklearn.feature_extraction.text import TfidfVectorizer
from sklearn.linear_model import LogisticRegression
from sklearn.model_selection import GroupKFold, cross_val_predict
from sklearn.metrics import accuracy_score, classification_report, confusion_matrix, f1_score
from sklearn.pipeline import FeatureUnion, Pipeline

from dentadesk_nlu.normalize import normalize

ROOT = Path(__file__).resolve().parent
DATA, MODELS, REPORTS = ROOT / "data", ROOT / "models", ROOT / "reports"


warnings.filterwarnings("ignore", message="Unknown solver options")


def load(name: str) -> tuple[list[str], list[str], list[str]]:
    rows = [json.loads(line) for line in (DATA / name).read_text(encoding="utf-8").splitlines() if line.strip()]
    return [r["text"] for r in rows], [r["intent"] for r in rows], [r.get("group", r["text"]) for r in rows]


def words() -> TfidfVectorizer:
    return TfidfVectorizer(preprocessor=normalize, analyzer="word", ngram_range=(1, 2), min_df=1, sublinear_tf=True)


def chars() -> TfidfVectorizer:
    # char_wb: n-grams inside word boundaries. "3andi", "andi" and "3ndi" share most of them.
    return TfidfVectorizer(preprocessor=normalize, analyzer="char_wb", ngram_range=(2, 5), min_df=2, sublinear_tf=True)


def classifier() -> LogisticRegression:
    return LogisticRegression(C=8.0, max_iter=3000, class_weight="balanced")


CANDIDATES = {
    "words (1-2)": lambda: Pipeline([("features", words()), ("clf", classifier())]),
    "characters (2-5)": lambda: Pipeline([("features", chars()), ("clf", classifier())]),
    "words + characters": lambda: Pipeline([
        ("features", FeatureUnion([("word", words()), ("char", chars())])),
        ("clf", classifier()),
    ]),
}


def pick_thresholds(proba: np.ndarray, classes: np.ndarray, labels: list[str]) -> tuple[float, float]:
    y = np.array(labels)
    order = np.argsort(-proba, 1)
    conf = proba.max(1)
    top1 = classes[order[:, 0]] == y
    top3 = np.array([y[i] in classes[order[i, :3]] for i in range(len(y))])
    grid = np.arange(0.10, 0.90, 0.02)
    act = next((round(float(t), 2) for t in grid if (conf >= t).any() and top1[conf >= t].mean() >= 0.90), 0.90)
    clarify = next((round(float(t), 2) for t in grid if (conf >= t).any() and top3[conf >= t].mean() >= 0.95), act)
    return act, min(clarify, act)


def confusion_table(labels: list[str], y_true: list[str], y_pred: list[str]) -> str:
    m = confusion_matrix(y_true, y_pred, labels=labels)
    short = [lbl[:6] for lbl in labels]
    lines = ["| true \\ predicted | " + " | ".join(short) + " |", "|---|" + "---|" * len(labels)]
    for lbl, row in zip(labels, m):
        lines.append(f"| **{lbl}** | " + " | ".join(str(v) if v else "·" for v in row) + " |")
    return "\n".join(lines)


def main() -> None:
    x_tr, y_tr, g_tr = load("train.jsonl")
    x_va, y_va, g_va = load("val.jsonl")
    x, y, groups = x_tr + x_va, y_tr + y_va, g_tr + g_va
    x_test, y_test, _ = load("handwritten_test.jsonl")
    cv = GroupKFold(n_splits=5)

    comparison, oof = [], {}
    for name, make in CANDIDATES.items():
        proba = cross_val_predict(make(), x, y, groups=groups, cv=cv, method="predict_proba")
        classes = np.array(sorted(set(y)))
        cv_f1 = f1_score(y, classes[proba.argmax(1)], average="macro")
        test_f1 = f1_score(y_test, make().fit(x, y).predict(x_test), average="macro")
        comparison.append((name, cv_f1, test_f1))
        oof[name] = (proba, classes)
        print(f"{name:<20} CV F1 {cv_f1:.3f}   test F1 {test_f1:.3f}")

    best_name = max(comparison, key=lambda r: r[1])[0]
    act, clarify = pick_thresholds(*oof[best_name], y)

    final = CANDIDATES[best_name]().fit(x, y)
    proba = final.predict_proba(x_test)
    y_pred = list(final.classes_[proba.argmax(1)])
    conf = proba.max(1)
    labels = sorted(set(y))
    order = np.argsort(-proba, 1)
    in_top3 = np.array([t in final.classes_[order[i, :3]] for i, t in enumerate(y_test)])

    acc = accuracy_score(y_test, y_pred)
    macro = f1_score(y_test, y_pred, average="macro")
    tier_act, tier_clarify = conf >= act, (conf >= clarify) & (conf < act)
    tier_handover = conf < clarify
    right = np.array(y_pred) == np.array(y_test)

    version = datetime.now(timezone.utc).strftime("%Y%m%d-%H%M")
    MODELS.mkdir(exist_ok=True)
    joblib.dump({
        "pipeline": final, "act_threshold": act, "clarify_threshold": clarify, "version": version,
        "features": best_name, "metrics": {"test_accuracy": round(acc, 3), "test_macro_f1": round(macro, 3)},
    }, MODELS / "intent.joblib")

    errors = [(t, y, p, c) for t, y, p, c in zip(x_test, y_test, y_pred, conf) if y != p]
    report = [
        f"# Intent model · version {version}",
        "",
        f"Data: {len(x)} training sentences (templates + spelling variants), 5-fold cross-validation "
        f"grouped by template, **{len(x_test)} hand-written test sentences**, never seen during "
        "training nor used to tune anything.",
        "",
        "## Feature comparison",
        "",
        "| features | macro F1 cross-validation | macro F1 test |",
        "|---|---|---|",
        *[f"| {n}{' ✔' if n == best_name else ''} | {v:.3f} | {t:.3f} |" for n, v, t in comparison],
        "",
        f"Kept: **{best_name}**, logistic regression (C=8, balanced classes).",
        "",
        "## Result on the hand-written test set",
        "",
        f"- Accuracy: **{acc:.1%}**  ·  macro F1: **{macro:.3f}**",
        "",
        "Thresholds chosen on cross-validation, then applied to the test set:",
        "",
        "| tier | confidence | share of test | what the assistant does | accuracy |",
        "|---|---|---|---|---|",
        f"| acts alone | ≥ {act} | {tier_act.mean():.0%} | answers / books | correct intent {right[tier_act].mean() if tier_act.any() else 0:.0%} |",
        f"| asks to confirm | {clarify} – {act} | {tier_clarify.mean():.0%} | offers 3 buttons | right answer among the 3: {in_top3[tier_clarify].mean() if tier_clarify.any() else 0:.0%} |",
        f"| hands over | < {clarify} | {tier_handover.mean():.0%} | creates a request for the practice | — |",
        "",
        "```",
        classification_report(y_test, y_pred, labels=labels, zero_division=0),
        "```",
        "",
        "## Confusion matrix",
        "",
        confusion_table(labels, y_test, y_pred),
        "",
        "## Remaining errors",
        "",
        "| message | expected | predicted | confidence |",
        "|---|---|---|---|",
        *[f"| {t} | {y} | {p} | {c:.2f} |" for t, y, p, c in errors],
        "",
    ]
    REPORTS.mkdir(exist_ok=True)
    (REPORTS / "metrics.md").write_text("\n".join(report), encoding="utf-8")
    print(f"\nkept {best_name}, act {act}, clarify {clarify}, test accuracy {acc:.3f}, macro F1 {macro:.3f}, errors {len(errors)}")


if __name__ == "__main__":
    main()
