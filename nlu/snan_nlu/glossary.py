"""Darija words a non-Moroccan dentist may not know, with their meaning in French and English.

Used by /interpret to gloss a patient's message word by word. A gloss, not a translation:
only words listed here are explained, and nothing is guessed.
"""
from __future__ import annotations

import re

from .normalize import normalize

# (spellings, French, English). The first spelling is the one shown.
ENTRIES: list[tuple[list[str], str, str]] = [
    (["wjee3", "wje3", "wja3", "lwje3", "ouja3", "وجع"], "douleur", "pain"),
    (["kayderni", "kaderni", "tayderni", "derni", "dorni", "kaydorni", "كيضرني", "ضرني"], "ça me fait mal", "it hurts me"),
    (["kaderha", "katderha", "derha", "dorha"], "ça lui fait mal (à elle)", "it hurts her"),
    (["kaydro", "derrou", "dorou"], "ça lui fait mal (à lui)", "it hurts him"),
    (["dersi", "derssa", "dersa", "ضرسي", "ضرسة"], "ma molaire / une molaire", "my molar / a molar"),
    (["snani", "snan", "snn", "سناني", "سنان"], "mes dents / les dents", "my teeth / teeth"),
    (["snna", "sna", "سنة"], "une dent", "a tooth"),
    (["fommi", "fmi", "فمي"], "ma bouche", "my mouth"),
    (["mnfou5", "mnfoukh", "ntfa5", "tfekh", "kaytfekh", "منفوخ"], "gonflé", "swollen"),
    (["tkesrat", "therrsat", "therset", "therrset", "تهرسات"], "s’est cassée", "broke"),
    (["kaysil dem", "dem", "الدم"], "saigne / sang", "bleeding / blood"),
    (["sakhfa", "سخفة"], "malaise, évanouissement", "dizzy spell, fainting"),
    (["ma n3ast", "ma n3asstch", "ma n3astch", "ما نعستش"], "je n’ai pas dormi", "I couldn’t sleep"),
    (["sossa", "ssousa", "sousa", "سوسة"], "carie", "cavity"),
    (["7chwa", "hchwa", "حشوة"], "plombage", "filling"),
    (["n9le3", "n9la3", "9la3", "qla3", "نقلع", "قلع"], "arracher (une dent)", "pull (a tooth)"),
    (["tbyed", "tbyad", "nbeyed", "تبييض"], "blanchiment", "whitening"),
    (["tnqiya", "tn9iya", "tnadif", "تنظيف"], "nettoyage, détartrage", "cleaning"),
    (["mow3id", "maw3id", "موعد"], "rendez-vous", "appointment"),
    (["blassa", "blasa", "بلاصة"], "une place, un créneau", "a slot"),
    (["ghedda", "ghda", "غدا"], "demain", "tomorrow"),
    (["ba3d ghedda", "b3d ghda", "بعد غدا"], "après-demain", "the day after tomorrow"),
    (["lyouma", "lyoum", "اليوم"], "aujourd’hui", "today"),
    (["lbare7", "lbareh", "البارح"], "hier", "yesterday"),
    (["mn lbare7", "mn lbareh"], "depuis hier", "since yesterday"),
    (["sba7", "sbah", "الصباح"], "le matin", "the morning"),
    (["l3chiya", "3chiya", "l3achiya", "العشية"], "l’après-midi", "the afternoon"),
    (["f lil", "flil"], "le soir", "in the evening"),
    (["simana jaya", "semaine jaya"], "la semaine prochaine", "next week"),
    (["jem3a", "jm3a", "الجمعة"], "vendredi", "Friday"),
    (["sebt", "sbt", "السبت"], "samedi", "Saturday"),
    (["l7ed", "الأحد", "الحد"], "dimanche", "Sunday"),
    (["tnin", "letnin", "الاثنين"], "lundi", "Monday"),
    (["tlat", "ttlat", "الثلاثاء"], "mardi", "Tuesday"),
    (["larb3", "lareb3", "الأربعاء"], "mercredi", "Wednesday"),
    (["lkhmis", "khmis", "الخميس"], "jeudi", "Thursday"),
    (["wlidi", "weldi", "ولدي"], "mon fils", "my son"),
    (["bnti", "bniti", "binti", "بنتي"], "ma fille", "my daughter"),
    (["mmi", "ommi", "مي", "أمي"], "ma mère", "my mother"),
    (["bba", "baba", "بابا"], "mon père", "my father"),
    (["rajli", "rajel dyali", "راجلي"], "mon mari", "my husband"),
    (["mrati", "مراتي"], "ma femme", "my wife"),
    (["bghit", "bghyt", "baghi", "baghya", "بغيت"], "je veux", "I want"),
    (["nbeddel", "nbdel", "bdel", "نبدل"], "changer, déplacer", "change, move"),
    (["nlghi", "lghi", "نلغي"], "annuler", "cancel"),
    (["ma nqderch", "ma n9derch", "ما نقدرش"], "je ne peux pas", "I can’t"),
    (["ma ghadich nji", "ما غاديش نجي"], "je ne viendrai pas", "I won’t come"),
    (["3afak", "عافاك"], "s’il vous plaît", "please"),
    (["daruri", "darouri", "ضروري"], "urgent, nécessaire", "urgent, necessary"),
    (["kat9blo", "kat9blou", "katqblo", "كتقبلو"], "vous acceptez", "do you accept"),
    (["ch7al", "chhal", "شحال"], "combien", "how much / how many"),
    (["fin", "فين"], "où", "where"),
    (["imta", "إمتى"], "quand", "when"),
    (["ascenseur", "asanseur"], "ascenseur", "lift / elevator"),
    (["droj", "دروج"], "escaliers", "stairs"),
    (["tla3", "tle3", "طلع"], "monter", "go up"),
    (["mrida", "mrid", "مريض"], "malade", "sick"),
    (["ra9mi", "ra9m", "رقمي"], "mon numéro", "my number"),
    (["smiti", "سميتي"], "je m’appelle", "my name is"),
]

_INDEX = [
    (re.compile(rf"(?<!\w){re.escape(normalize(sp))}(?!\w)"), spellings[0], fr, en)
    for spellings, fr, en in ENTRIES
    for sp in spellings
]


def gloss(text: str) -> list[dict]:
    t = normalize(text)
    seen, out = set(), []
    # Longest spellings first, so "mn lbare7" wins over "lbare7".
    for rx, word, fr, en in sorted(_INDEX, key=lambda r: -len(r[0].pattern)):
        if (m := rx.search(t)) and word not in seen:
            # Skip a short entry fully inside a longer one already found ("lbare7" in "mn lbare7").
            if any(m.group(0) in prev["match"] and m.group(0) != prev["match"] for prev in out):
                continue
            seen.add(word)
            out.append({"word": word, "match": m.group(0), "fr": fr, "en": en, "at": m.start()})
    return [{k: v for k, v in g.items() if k != "at"} for g in sorted(out, key=lambda g: g["at"])]
