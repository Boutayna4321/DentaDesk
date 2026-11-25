"""Text normalisation shared by training, inference and entity rules.

Darija written in Latin script uses digits as letters (3 = ع, 7 = ح, 9 = ق), so digits
inside words are kept. Accents are stripped so "détartrage" and "detartrage" meet.
"""
import re
import unicodedata

_AR_DIACRITICS = re.compile(r"[ؐ-ًؚ-ٰٟۖ-ۭـ]")
_AR_MAP = str.maketrans({"أ": "ا", "إ": "ا", "آ": "ا", "ٱ": "ا", "ى": "ي", "ة": "ه", "ؤ": "و", "ئ": "ي"})
_REPEAT = re.compile(r"(\D)\1{2,}")
_LONE_COLON = re.compile(r"(?<!\d):|:(?!\d)")  # keep 16:45, drop "Salam: ..."
_SPACES = re.compile(r"\s+")
_PUNCT = re.compile(r"[!?.,;()\"«»…'’`\-_/\\،؟؛]+")


def strip_accents(text: str) -> str:
    # Only Latin accents: NFD would also split Arabic letters like آ.
    out = []
    for ch in text:
        if "À" <= ch <= "ɏ":
            ch = "".join(c for c in unicodedata.normalize("NFD", ch) if not unicodedata.combining(c))
        out.append(ch)
    return "".join(out)


def normalize(text: str) -> str:
    text = text.lower().strip()
    text = strip_accents(text)
    text = _AR_DIACRITICS.sub("", text).translate(_AR_MAP)
    text = _REPEAT.sub(r"\1\1", text)  # "salaaam" -> "salaam", keeps "tt" in "ttlat"
    text = _PUNCT.sub(" ", text)
    text = _LONE_COLON.sub(" ", text)
    return _SPACES.sub(" ", text).strip()
