# DentaDesk — dental practice: website, booking, dashboard, assistant

Project #05 of the DESIGN → CODE series. Goal: show a real full-stack + AI project
(Master's in Software Engineering & AI), not just a landing page.

## What the product does

1. **Public website** (Dr Nadia Alaoui, Maârif, Casablanca). Approved style: white + blue,
   large patient photo, emergency card, booking bar under the hero, assistant in the
   bottom-right corner. **No prices shown.**
2. **Booking**: treatment → day → free slot → name + phone → confirmation.
   Slots come from the slot engine, never from the front end.
3. **Assistant**: understands Darija (Latin + Arabic script), French, Arabic. Books,
   reschedules, answers the practice's FAQ. Anything it does not understand becomes a
   *request* in the dashboard. It never invents a time or an answer.
4. **Dashboard** (dentist, front desk) v1: Today, Calendar, Requests, Patients
   (record + FDI dental chart), Hours & days off, Treatments & durations.
   v2: WhatsApp reminders (Meta API), statistics, team management.

## Architecture

```
browser ──► Laravel 13 (Blade + Bootstrap 5.3 + vanilla JS, FullCalendar)
              │  slot engine, dialogue, auth, JSON API
              ├──► MySQL 8
              └──► nlu (Python, FastAPI): /parse  /faq
```

- **Laravel** owns the truth: slots, bookings, conversation state.
- **nlu** is stateless: text → intent + entities, question → best FAQ entry + score.

## Slot engine

- Slots = the day's opening hours − breaks − days off − existing appointments, on a
  15-minute grid, for the length of the treatment.
- Booking runs in a transaction that locks the practitioner's row
  (`SELECT … FOR UPDATE`), checks for overlap again, then inserts. A unique index on
  `(practitioner_id, starts_at)` acts as a safety net.

## AI (no external API)

| Component | Method | Why |
|---|---|---|
| Intent | our own trained model: TF-IDF character n-grams + logistic regression (baseline). Experiment: fine-tuning a multilingual encoder | Darija is written 10 different ways; character n-grams handle `3andi / andi / عندي` |
| Entities | lexicon + rules (ghedda, ba3d ghedda, nhar jem3a, f sba7, l3chiya…), treatments by synonyms | deterministic, testable |
| FAQ | similarity search over the practice's approved FAQ, with a threshold; below it → human request | medical domain: no free-form generation |
| Dialogue | state machine (treatment, day, time of day, slot, name, phone) on the Laravel side | controllable, explainable |

Intents: `prendre_rdv` (book), `urgence` (emergency), `annuler` (cancel),
`deplacer` (reschedule), `horaires` (hours), `adresse` (address), `question_faq`,
`salutation` (greeting), `merci` (thanks), `hors_sujet` (off-topic).

Dataset: built by us (templates + hand-written sentences + spelling variants),
train/val split, **test set written by hand and never generated**. Report: F1 per
intent, confusion matrix. Requests corrected in the dashboard can be exported to
retrain the model.

## Design choices (open to revision)

- Laravel 13 / PHP 8.4, everything in Docker (nothing was installed on the machine).
- Hand-written auth (not Breeze, which forces Tailwind).
- One active practitioner in v1; the schema supports several.
- WhatsApp v1: pre-filled `wa.me` link, no automatic sending.
