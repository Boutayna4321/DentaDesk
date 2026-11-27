# DentaDesk — dental practice: website, booking, dashboard and AI assistant

**English** · [Français](README.fr.md)

A complete project for a dental practice in Casablanca: a public website, online booking
on real time slots, a dashboard for the dentist and the front desk, and an assistant that
understands **Darija** (Moroccan Arabic, in Latin or Arabic script), French, Arabic and English.

The assistant runs on **a model we trained ourselves**, hosted with the application.
No patient data is sent to an external API.

![DentaDesk](app/public/img/og.png)

> Demo project: the practice, the dentist and the patients are fictional.
> Demo video (2 min): [LinkedIn post](https://www.linkedin.com/feed/update/urn:li:activity:7518725908128694272/).
> Designed and built by Boutayna Metarfi, Master's in Software Engineering and Artificial Intelligence.

```
browser ──► Laravel 13 (Blade, Bootstrap 5.3, JS, FullCalendar, GSAP)
              │  slot engine · dialogue · auth · JSON API
              ├──► MySQL 8
              └──► nlu (Python 3.12, FastAPI, scikit-learn)  /parse  /faq  /interpret
```

## Running it

All you need is Docker (and Node 20+ to rebuild the front end).

```bash
docker compose up -d                       # app :8000, nlu :8001, mysql :3307
docker compose exec app composer install   # first time only
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate --seed
cd app && npm install && npm run build
```

- Website: http://localhost:8000
- Dashboard: http://localhost:8000/login
  - `dentiste@dentadesk.test` / `dentadesk2026` (dentist: sees everything, dental chart, notes)
  - `secretaire@dentadesk.test` / `dentadesk2026` (front desk: no medical notes)
- Model health: http://localhost:8001/health

On first start, the `nlu` service builds the dataset and trains the model (about twenty
seconds on a laptop CPU).

Demo data is generated around the moment the seeder runs, so the dashboard always opens
on a realistic day (patients done, one in the chair, the rest still to come).

## What's inside

**Website** — hero, booking bar (treatment → day → time → name + phone), treatments,
the practice, reviews, FAQ (read from the database), location and opening hours (read from
the database), assistant in the bottom-right corner. No prices shown: quotes come after
the examination.

**Slot engine** (`app/app/Booking`) — the day's opening hours, minus breaks, days off and
existing appointments, on a 15-minute grid, for the length of the treatment. Booking locks
the practitioner's row (`SELECT … FOR UPDATE`) and checks again; a unique index on a
generated column (`slot_lock`, NULL when the appointment is cancelled) is the last safety
net against double booking.

**Assistant** (`app/app/Assistant`) — a state machine. The model says what the patient
wants; Laravel decides what to do. Proposed times and answers always come from the
database, never from the model. It replies in the patient's own register (Latin-script
Darija, Arabic, French, English).

**Invoices & quotes** — the dentist writes the content (procedures from her price list,
tooth, coding, notes or treatment plan), saves a draft, then validates it: the document
gets the next gapless number of the year (F-2026-0042, D-2026-0007), is locked, and keeps
the letterhead as it was on the day it was validated. The front desk prints it (A4 or A5,
"Save as PDF" from the browser) and takes payment (cash, card, cheque, bank transfer,
partial payments). A validated document cannot be deleted, only cancelled with a reason.
An accepted quote becomes an invoice in one click. The amount is written out in French
words, as Moroccan invoices require ("Arrêtée la présente facture à la somme de : mille
deux cents dirhams"). Configurable header and footer (logo, INPE, ICE, IF, patente, RIB,
legal notes, colour). CNSS / CNOPS claim forms stay on paper, filled in by hand.

**Statistics** (dentist) — collected and billed revenue, outstanding balance, average
ticket, occupancy rate (booked time / opening time) overall and per day, no-show rate,
new patients, average lead time before an appointment, assistant conversion, where
bookings come from, most requested treatments, revenue collected per week.

**Team** (dentist) — create, edit, deactivate, reactivate; an account can only be deleted
if it has no history (otherwise it is deactivated). A deactivated account is logged out
immediately. Log of sensitive actions.

**Messages** — internal messaging between the dentist and the front desk, with a link to
the patient concerned, quick replies, read / unread, live-updating badge.

**Dashboard** — Today (schedule, statuses, requests, patient in the chair + dental chart),
an explanation of each patient message in French or English (see below), Calendar
(drag and drop, create, closures), Requests (confirm, decline, accept a reschedule, turn a
question into an FAQ entry), Patients, AI Assistant (model health, intents, corrections),
Hours & treatments.

## The AI in detail

| Component | Method | Why |
|---|---|---|
| Intent (12 classes) | TF-IDF character 2–5-grams + logistic regression | Darija is written ten different ways: `3andi`, `andi`, `عندي` share their n-grams |
| Entities | rules + lexicon (`ghedda`, `nhar jem3a`, `f l3chiya`, `m3a jouj w nos`, `wjee3`…) | a booked date must never be a guess |
| FAQ | cosine similarity over the practice's approved FAQ, threshold 0.45 | medical domain: no free-form generation |
| Decision | three confidence tiers | the model knows when it is unsure |

Results on **126 hand-written test sentences** (21 of them in English), never seen during
training (`nlu/reports/metrics.md`):

| features | F1 cross-validation (5 folds grouped by template) | F1 test |
|---|---|---|
| words 1–2 | 0.584 | 0.789 |
| **characters 2–5** | **0.665** | **0.844** |
| words + characters | 0.646 | 0.841 |

| tier | confidence | share of test | what the assistant does | accuracy |
|---|---|---|---|---|
| acts alone | ≥ 0.76 | 52 % | answers / books | 98 % |
| asks to confirm | 0.36 – 0.76 | 37 % | offers 3 buttons | right answer among the 3: 96 % |
| hands over | < 0.36 | 10 % | creates a request for the practice | — |

Overall accuracy 84.1 %. Before English was added: 86.7 % on the original 105 sentences,
with 70 % of messages handled alone. English (15/21 correct) made the model more careful:
it asks for confirmation more often instead of getting it wrong.

**Explaining Darija to the dentist.** A doctor from elsewhere will not necessarily read
"3andi wjee3 f dersi mn lbare7". No open model translates Latin-script Darija into French
or English well (AtlasIA's Terjman models go the other way, English → Darija). So under
each message, the dashboard shows:

- a sentence built from what the model understood (intent, day, time of day, treatment),
  in French or English: "The patient reports pain and wants to be seen as soon as
  possible, for Thursday 22 October in the morning.";
- a glossary of the Darija words it recognised (`wjee3` pain, `dersi` my molar,
  `mn lbare7` since yesterday…), 60 entries in `nlu/dentadesk_nlu/glossary.py`.

This is not a translation and the dashboard does not present it as one: nothing is
invented, and the original message stays displayed above it.

**Improvement loop.** In *AI Assistant*, the team corrects a misunderstood intent. Then:

```bash
docker compose exec app php artisan nlu:export      # corrections -> nlu/data/corrections.jsonl
docker compose exec nlu sh -c "python data/build_dataset.py && python -W ignore train.py"
docker compose restart nlu                            # loads the new version
```

A failed experiment, kept on purpose: adding the FAQ variants as `question_faq` examples
lowered the test score from 0.867 to 0.857 ("kayn parking?" is an FAQ question but an
`adresse` intent). Only human corrections are exported.

## Tests

```bash
docker compose exec app php artisan test                 # 65 tests: slots, dialogue, invoices, team, stats, messages
docker compose exec nlu python -W ignore -m pytest -q    # 56 tests: entities (4 languages), API, FAQ, glossary
```

## Choices and limits

- One active practitioner; the schema supports several.
- WhatsApp: pre-filled `wa.me` links. Automatic reminders (Meta API) are planned for v2.
- `php artisan serve` serves the app in development; in production, use PHP-FPM + Nginx
  on a VPS (the Python service needs more than shared hosting).
- Photos: Unsplash (free licence), sources in `app/public/img/SOURCES.json`.

---

Designed and built by [@boutayna_metarfi](https://instagram.com/boutayna_metarfi).
Demo project: the practice, the dentist and the patients are fictional.
