from datetime import date

import pytest

from snan_nlu.entities import extract

WED = date(2026, 10, 21)  # a Wednesday


@pytest.mark.parametrize("text,expected", [
    ("kayn chi blassa ghedda?", "2026-10-22"),
    ("Est-ce possible demain matin ?", "2026-10-22"),
    ("واش كاين شي بلاصة غدا", "2026-10-22"),
    ("ba3d ghedda f l3chiya", "2026-10-23"),
    ("après-demain svp", "2026-10-23"),
    ("lyouma ila mkn", "2026-10-21"),
    ("nhar jem3a", "2026-10-23"),
    ("vendredi prochain", "2026-10-23"),
    ("السبت", "2026-10-24"),
    ("larb3", "2026-10-28"),  # same weekday as today means next week
    ("le 25", "2026-10-25"),
    ("le 3", "2026-11-03"),
    ("nhar 2/11", "2026-11-02"),
    ("simana jaya", "2026-10-26"),
])
def test_dates(text, expected):
    assert extract(text, WED).date == expected


@pytest.mark.parametrize("text,part", [
    ("ghedda f sba7", "morning"),
    ("demain matin", "morning"),
    ("f l3chiya", "afternoon"),
    ("l'après-midi", "afternoon"),
    ("فالعشية", "afternoon"),
    ("f lil ila mkn", "evening"),
])
def test_part_of_day(text, part):
    assert extract(text, WED).part_of_day == part


@pytest.mark.parametrize("text,time", [
    ("à 10h30", "10:30"),
    ("f 15h", "15:00"),
    ("m3a l3achra", "10:00"),
    ("m3a jouj w nos", "14:30"),
    ("a 3h", "15:00"),  # 3 o'clock at a clinic is 15:00
    ("16:45", "16:45"),
])
def test_time(text, time):
    assert extract(text, WED).time == time


@pytest.mark.parametrize("text,service", [
    ("3andi wjee3 f dersi", "urgence"),
    ("j'ai très mal à une dent", "urgence"),
    ("bghit ndir détartrage", "detartrage"),
    ("bghit tbyed snani", "blanchiment"),
    ("appareil dentaire l bnti", "orthodontie"),
    ("wlidi 3ando sossa", "enfant"),
    ("bnti kaderha snha bzaf", "urgence"),
    ("بغيت نقلع ضرسة", "extraction"),
    ("salam", None),
])
def test_service(text, service):
    assert extract(text, WED).service == service


def test_phone_is_not_read_as_time():
    e = extract("ra9mi 06 12 34 56 78 ghedda m3a l3achra", WED)
    assert e.phone == "0612345678"
    assert e.time == "10:00"


def test_international_phone():
    assert extract("+212 661-234-567", WED).phone == "0661234567"


@pytest.mark.parametrize("text,name", [
    ("smiti salma bennani", "Salma Bennani"),
    ("je m'appelle Youssef", "Youssef"),
    ("ana karim", "Karim"),
    ("ana baghi rendez-vous", None),
])
def test_name(text, name):
    assert extract(text, WED).name == name
