"""HTTP API used by the Laravel app. Stateless: the conversation lives in Laravel."""
from __future__ import annotations

from datetime import date

from fastapi import FastAPI
from pydantic import BaseModel, Field

from . import faq
from .entities import extract
from .glossary import gloss
from .model import load

app = FastAPI(title="snan. NLU", version="1.0")


class ParseIn(BaseModel):
    text: str = Field(min_length=1, max_length=1000)
    today: date | None = None


class FaqEntry(BaseModel):
    id: int
    texts: list[str]


class FaqIn(BaseModel):
    text: str = Field(min_length=1, max_length=1000)
    faqs: list[FaqEntry]


@app.get("/health")
def health() -> dict:
    m = load()
    return {"status": "ok", "model_version": m.version, "metrics": m.metrics,
            "thresholds": {"act": m.act_threshold, "clarify": m.clarify_threshold, "faq": faq.THRESHOLD}}


@app.post("/parse")
def parse(body: ParseIn) -> dict:
    model = load()
    result = model.predict(body.text)
    entities = extract(body.text, body.today or date.today())

    # Business rule over the model: pain words mean an emergency, whatever the phrasing.
    if entities.emergency and result["intent"] in ("prendre_rdv", "hors_sujet", "question_faq") or (
        entities.emergency and result["tier"] != "act"
    ):
        result.update(intent="urgence", tier="act", overridden_by="regle_douleur")

    return {**result, "entities": entities.to_dict(), "model_version": model.version}


@app.post("/faq")
def faq_match(body: FaqIn) -> dict:
    return {"match": faq.match(load(), body.text, [f.model_dump() for f in body.faqs]), "threshold": faq.THRESHOLD}


@app.post("/interpret")
def interpret(body: ParseIn) -> dict:
    """What the dashboard shows under a patient's message: intent, entities, glossed words."""
    return {**parse(body), "glossary": gloss(body.text)}
