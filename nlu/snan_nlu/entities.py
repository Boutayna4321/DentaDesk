"""Rule-based entity extraction: day, part of day, time, service, phone, name.

Deterministic on purpose: a date the clinic books must never be a guess. Every rule
works on normalised text (see normalize.py) and has a test in tests/test_entities.py.
"""
from __future__ import annotations

import re
from dataclasses import asdict, dataclass, field
from datetime import date, timedelta

from .normalize import normalize


def _alts(words: list[str]) -> str:
    # Longest first so "ba3d ghedda" wins over "ghedda".
    return "|".join(sorted((re.escape(normalize(w)) for w in words), key=len, reverse=True))


TODAY = ["lyouma", "lyoum", "lyom", "lioma", "liouma", "lyoma", "lyouma", "aujourdhui", "aujourd hui", "auj", "ce jour",
         "اليوم", "ليوما", "اليوما", "ليوم", "had nhar", "had nhar"]
TOMORROW = ["ghedda", "ghda", "ghadda", "gheda", "ghdda", "ghedwa", "ghdwa", "rhda", "demain", "غدا", "غدوه", "غدوة", "غدّا"]
AFTER_TOMORROW = ["ba3d ghedda", "b3d ghda", "ba3d ghda", "b3d ghedda", "apres demain", "après-demain", "بعد غدا", "بعد غدوه"]
NEXT_WEEK = ["simana jaya", "semaine jaya", "simana jaia", "la semaine prochaine", "semaine prochaine", "l osbou3 jay",
             "الاسبوع الجاي", "السيمانة الجاية", "الأسبوع المقبل"]

WEEKDAYS = {
    1: ["lundi", "tnin", "letnin", "ltnin", "l tnin", "etnin", "الاثنين", "الاتنين", "التنين", "الإثنين"],
    2: ["mardi", "tlat", "ttlat", "tlata nhar", "ttlata", "الثلاثاء", "التلات", "الثلاثا"],
    3: ["mercredi", "larb3", "lareb3", "larba3", "l arb3", "larbe3", "الاربعاء", "الأربعاء", "الاربع", "لاربع"],
    4: ["jeudi", "lkhmis", "khmis", "l khmis", "lekhmis", "الخميس", "لخميس"],
    5: ["vendredi", "jem3a", "jm3a", "jom3a", "joum3a", "jema3", "الجمعة", "الجمعه", "جمعه", "جمعة"],
    6: ["samedi", "sebt", "sbt", "ssebt", "s sebt", "essebt", "السبت", "سبت"],
    7: ["dimanche", "l7ed", "l7ad", "nhar l7ed", "7ed", "lahad", "الاحد", "الأحد", "الحد"],
}

MORNING = ["sba7", "sbah", "s sba7", "ssba7", "sbe7", "fsba7", "matin", "matinee", "le matin", "الصباح", "صباح", "فالصباح",
           "9bel lghda", "9bel ftour", "qbel lghda", "avant midi"]
AFTERNOON = ["3chiya", "l3chiya", "l3achiya", "l3echiya", "3achiya", "l3chia", "achiya", "apres midi", "après-midi", "aprem",
             "العشية", "العشيه", "بعد الظهر", "فالعشية", "mn b3d lghda", "ba3d lghda", "b3d lghda", "apres le dejeuner"]
EVENING = ["f lil", "flil", "lil", "le soir", "soir", "fin de journee", "l3chiya lkhra", "المسا", "المساء", "فالليل"]

# Darija hour words. A clinic is open 9h–19h, so 1..7 mean the afternoon.
HOUR_WORDS = {
    "tes3oud": 9, "tes3ud": 9, "t3oud": 9, "l3achra": 10, "l3ashra": 10, "3achra": 10, "lhadach": 11, "l7dach": 11,
    "7dach": 11, "tnach": 12, "ttnach": 12, "lwe7da": 13, "lwahda": 13, "we7da": 13, "jouj": 14, "joj": 14,
    "ttlata": 15, "tlata": 15, "rb3a": 16, "rab3a": 16, "lkhamsa": 17, "khamsa": 17, "sta": 18, "ssta": 18,
}

SERVICES = {
    "urgence": ["wjee3", "wje3", "wja3", "wjaa3", "ouja3", "kayderni", "kaydrni", "tayderni", "kaydarni", "derni", "dorni",
                "kaydorni", "douleur", "j ai mal", "jai mal", "tres mal", "mal a une dent", "mal au dent", "mal aux dent", "mal aux dents", "mal a la dent", "abces", "gonfle", "mnfou5",
                "ntfa5", "tkesrat", "therrsat", "therset", "dent cassee", "urgence", "urgent", "daruri", "وجع", "كيضرني",
                "تيضرني", "ضرني", "مضروب", "منفوخ", "تهرسات", "مستعجل", "سخفة", "sakhfa"],
    "detartrage": ["detartrage", "nettoyage", "tnqiya", "tn9iya", "tna9iya", "tnadif", "tnqya", "nqi snani", "n9i snani",
                   "jir", "tartre", "تنظيف", "تنقية", "الجير"],
    "blanchiment": ["blanchiment", "tbyed", "tbyad", "tabyid", "nbeyed", "nbyed", "byed snani", "dents blanches",
                    "blanchir", "تبييض", "تبياض", "نبيض"],
    "implant": ["implant", "implants", "zre3", "zar3", "zra3", "dent manquante", "زرع", "زراعة"],
    "orthodontie": ["orthodontie", "ortho", "appareil", "bagues", "aligneurs", "aligneur", "gouttieres", "tqwim", "ta9wim",
                    "tq9wim", "snan m3awjin", "m3awjin", "تقويم", "معوجين"],
    "enfant": ["wlidi", "weldi", "bnti", "bniti", "binti", "lwlad", "drari", "drri", "7fidi", "mon fils", "ma fille",
               "enfant", "mon enfant", "pediatrique", "ولدي", "بنتي", "الدراري", "ولادي"],
    "carie": ["carie", "caries", "sossa", "ssousa", "sousa", "plombage", "7chwa", "hchwa", "trou", "سوسة", "سوسه", "حشوة"],
    "extraction": ["9la3", "qla3", "n9le3", "n9la3", "nqle3", "extraction", "arracher", "enlever une dent", "قلع", "نقلع"],
    "controle": ["controle", "control", "check", "visite de controle", "nchouf snani", "تشييك", "مراقبة"],
}
# When the message names nothing specific, the booking defaults to a consultation.

_RE = {
    "after_tomorrow": re.compile(rf"(?<!\w)({_alts(AFTER_TOMORROW)})(?!\w)"),
    "tomorrow": re.compile(rf"(?<!\w)({_alts(TOMORROW)})(?!\w)"),
    "today": re.compile(rf"(?<!\w)({_alts(TODAY)})(?!\w)"),
    "next_week": re.compile(rf"(?<!\w)({_alts(NEXT_WEEK)})(?!\w)"),
    "morning": re.compile(rf"(?<!\w)({_alts(MORNING)})(?!\w)"),
    "afternoon": re.compile(rf"(?<!\w)({_alts(AFTERNOON)})(?!\w)"),
    "evening": re.compile(rf"(?<!\w)({_alts(EVENING)})(?!\w)"),
}
_WEEKDAY_RE = {d: re.compile(rf"(?<!\w)({_alts(ws)})(?!\w)") for d, ws in WEEKDAYS.items()}
_SERVICE_RE = {s: re.compile(rf"(?<!\w)({_alts(ws)})") for s, ws in SERVICES.items()}
# Darija "it hurts me/her/him": derni, dorni, kaderha, katdorni, tayderni, kaydorlha…
_PAIN_RE = re.compile(r"(?<!\w)(?:ka|ta|kay|tay|kat|tat)?d[oe]?r+(?:ni|ha|lha|li|na|kom)(?!\w)")
_HOUR_WORD_RE = re.compile(rf"(?:m3a|mea|f|fi|a|on|ma3)\s+({'|'.join(sorted(HOUR_WORDS, key=len, reverse=True))})(?:\s+(w\s*nos|w\s*ness|w\s*rbe3|w\s*rob3|و\s*نص))?(?!\w)")
_TIME_RE = re.compile(r"(?<![\d/])([01]?\d|2[0-3])\s*(?:h|:|heures?|h00)\s*([0-5]\d)?(?![\d/])")
_DATE_RE = re.compile(r"(?:le|nhar|يوم|نهار)\s+(\d{1,2})(?:\s*/\s*(\d{1,2}))?(?!\s*h)(?!\d)")
_PHONE_RE = re.compile(r"(?:\+?212|00212|0)\s*([5-7])(?:[\s.]*\d){8}")
_NAME_RE = re.compile(
    r"(?:smiti|smiyti|smiya dyali|smitii|ana smiti|je m appelle|je mappelle|mon nom est|c est|ana|moi c est|اسمي|سميتي|انا)\s+"
    r"([a-zء-ي]{2,20}(?:\s+[a-zء-ي]{2,20})?)"
)
_NAME_STOP = {"3andi", "andi", "bghit", "baghi", "baghya", "bghyt", "kan", "f", "m3a", "mn", "ghadi", "je", "veux", "voudrais",
              "ana", "la", "le", "malade", "mrid", "mrida", "hna", "عندي", "بغيت", "من", "في", "مريض"}


@dataclass
class Entities:
    date: str | None = None          # ISO yyyy-mm-dd
    date_text: str | None = None
    part_of_day: str | None = None   # morning | afternoon | evening
    time: str | None = None          # HH:MM
    service: str | None = None
    services: list[str] = field(default_factory=list)
    emergency: bool = False
    phone: str | None = None
    name: str | None = None

    def to_dict(self) -> dict:
        return asdict(self)


def _next_weekday(today: date, iso_weekday: int) -> date:
    days = (iso_weekday - today.isoweekday()) % 7
    return today + timedelta(days=days or 7)


def _resolve_day_of_month(today: date, day: int, month: int | None) -> date | None:
    """"le 25" means the next 25th; "25/10" means the next 25 October."""
    try:
        if month:
            d = date(today.year, month, day)
            return d if d >= today else date(today.year + 1, month, day)
        if day >= today.day:
            return date(today.year, today.month, day)
        nxt = (today.replace(day=1) + timedelta(days=32)).replace(day=1)
        return nxt.replace(day=day)
    except ValueError:
        return None


def extract(text: str, today: date) -> Entities:
    t = normalize(text)
    e = Entities()

    # Day: relative words first, then weekday names, then "le 25".
    if m := _RE["after_tomorrow"].search(t):
        e.date, e.date_text = (today + timedelta(days=2)).isoformat(), m.group(1)
        t_wo = t.replace(m.group(1), " ")
    else:
        t_wo = t
    if not e.date and (m := _RE["tomorrow"].search(t_wo)):
        e.date, e.date_text = (today + timedelta(days=1)).isoformat(), m.group(1)
    if not e.date and (m := _RE["today"].search(t_wo)):
        e.date, e.date_text = today.isoformat(), m.group(1)
    if not e.date:
        for wd, rx in _WEEKDAY_RE.items():
            if m := rx.search(t_wo):
                e.date, e.date_text = _next_weekday(today, wd).isoformat(), m.group(1)
                break
    if not e.date and (m := _DATE_RE.search(t_wo)):
        if d := _resolve_day_of_month(today, int(m.group(1)), int(m.group(2)) if m.group(2) else None):
            e.date, e.date_text = d.isoformat(), m.group(0)
    if not e.date and (m := _RE["next_week"].search(t_wo)):
        e.date, e.date_text = _next_weekday(today, 1).isoformat(), m.group(1)

    # Phone before time, so "0612345678" is not read as an hour.
    if m := _PHONE_RE.search(t):
        e.phone = re.sub(r"\D", "", m.group(0))
        if e.phone.startswith("212"):
            e.phone = "0" + e.phone[3:]
        elif e.phone.startswith("00212"):
            e.phone = "0" + e.phone[5:]
        t_time = t.replace(m.group(0), " ")
    else:
        t_time = t

    if m := _TIME_RE.search(t_time):
        h, mins = int(m.group(1)), int(m.group(2) or 0)
        if 1 <= h <= 7:
            h += 12
        e.time = f"{h:02d}:{mins:02d}"
    elif m := _HOUR_WORD_RE.search(t_time):
        h = HOUR_WORDS[m.group(1)]
        mins = 0
        if m.group(2):
            mins = 30 if ("nos" in m.group(2) or "ness" in m.group(2) or "نص" in m.group(2)) else 15
        e.time = f"{h:02d}:{mins:02d}"

    for part in ("morning", "afternoon", "evening"):
        if _RE[part].search(t):
            e.part_of_day = part
            break
    if not e.part_of_day and e.time:
        hour = int(e.time[:2])
        e.part_of_day = "morning" if hour < 13 else "afternoon" if hour < 17 else "evening"

    e.services = [s for s, rx in _SERVICE_RE.items() if rx.search(t)]
    if "urgence" not in e.services and _PAIN_RE.search(t):
        e.services.insert(0, "urgence")
    e.emergency = "urgence" in e.services
    # Emergency wins: a child in pain is booked as an emergency, not a check-up.
    e.service = "urgence" if e.emergency else (e.services[0] if e.services else None)

    if m := _NAME_RE.search(t):
        words = m.group(1).split()
        words = words[:1] if len(words) > 1 and words[1] in _NAME_STOP else words
        if words[0] not in _NAME_STOP and not any(ch.isdigit() for ch in words[0]):
            e.name = " ".join(w.capitalize() for w in words[:2])

    return e
