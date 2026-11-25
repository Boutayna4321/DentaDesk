from fastapi.testclient import TestClient

from dentadesk_nlu.api import app

client = TestClient(app)

FAQS = [
    {"id": 1, "texts": ["Vous acceptez la CNSS ?", "wach kat9blo cnss", "feuille de soins cnss", "واش كتقبلو CNSS"]},
    {"id": 2, "texts": ["Vous soignez les enfants ?", "wach katdaweo drari sghar", "dentiste pour enfant"]},
    {"id": 3, "texts": ["Peut-on payer par carte ?", "wach nkhles b carte", "paiement carte bancaire"]},
]


def test_health_exposes_model_version_and_thresholds():
    body = client.get("/health").json()
    assert body["status"] == "ok"
    assert 0 < body["thresholds"]["clarify"] <= body["thresholds"]["act"] < 1


def test_parse_booking_with_entities():
    body = client.post("/parse", json={"text": "bghit rendez-vous ghedda f sba7", "today": "2026-10-21"}).json()
    assert body["intent"] == "prendre_rdv"
    assert body["entities"]["date"] == "2026-10-22"
    assert body["entities"]["part_of_day"] == "morning"
    assert len(body["alternatives"]) == 3


def test_pain_always_means_emergency():
    body = client.post("/parse", json={"text": "salam wach kayn chi blassa, dersi kayderni", "today": "2026-10-21"}).json()
    assert body["intent"] == "urgence"
    assert body["entities"]["emergency"] is True


def test_faq_finds_the_right_entry_across_spellings():
    for text, expected in [("wach kat9blou la cnss", 1), ("vous prenez la CNSS", 1), ("momkin nkhelles b la carte?", 3)]:
        match = client.post("/faq", json={"text": text, "faqs": FAQS}).json()["match"]
        assert match and match["id"] == expected, text


def test_faq_stays_silent_when_nothing_matches():
    assert client.post("/faq", json={"text": "quel temps fait-il à Rabat", "faqs": FAQS}).json()["match"] is None


def test_empty_text_is_rejected():
    assert client.post("/parse", json={"text": ""}).status_code == 422


def test_interpret_glosses_darija_words_in_order():
    body = client.post("/interpret", json={"text": "Salam, 3andi wjee3 f dersi mn lbare7, kayn chi blassa ghedda?", "today": "2026-10-21"}).json()
    assert body["intent"] == "urgence"
    words = [g["word"] for g in body["glossary"]]
    assert words == ["wjee3", "dersi", "mn lbare7", "blassa", "ghedda"]
    assert body["glossary"][0]["en"] == "pain"


def test_interpret_knows_nothing_it_was_not_taught():
    assert client.post("/interpret", json={"text": "Vous acceptez la carte ?"}).json()["glossary"] == []
