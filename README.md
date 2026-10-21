# snan. — cabinet dentaire : site, réservation, dashboard et assistant IA

Un projet complet pour un cabinet dentaire à Casablanca : site vitrine, réservation en
ligne sur de vrais créneaux, dashboard pour la dentiste et la secrétaire, et un assistant
qui comprend la **darija** (en lettres latines ou arabes), le français et l'arabe.

L'assistant tourne sur **un modèle entraîné par nous**, hébergé avec l'application.
Aucune donnée patient n'est envoyée à une API externe.

```
navigateur ──► Laravel 13 (Blade, Bootstrap 5.3, JS, FullCalendar, GSAP)
                 │  moteur de créneaux · dialogue · auth · API JSON
                 ├──► MySQL 8
                 └──► nlu (Python 3.12, FastAPI, scikit-learn)  /parse  /faq
```

## Lancer le projet

Il faut seulement Docker (et Node 20+ pour reconstruire le front).

```bash
docker compose up -d                       # app :8000, nlu :8001, mysql :3307
docker compose exec app composer install   # première fois
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate --seed
cd app && npm install && npm run build
```

- Site : http://localhost:8000
- Dashboard : http://localhost:8000/login
  - `dentiste@snan.test` / `snan2026` (tout voir, schéma dentaire, notes)
  - `secretaire@snan.test` / `snan2026` (accueil : pas de notes médicales)
- Santé du modèle : http://localhost:8001/health

Au premier démarrage, le service `nlu` construit le dataset et entraîne le modèle (une
vingtaine de secondes sur un CPU de portable).

Les données de démo sont générées autour du moment où le seeder tourne : le dashboard
s'ouvre toujours sur une vraie journée (patients terminés, un au fauteuil, la suite).

## Ce qu'il y a dedans

**Site** — hero, barre de réservation (soin → jour → heure → nom + téléphone), soins,
le cabinet, avis, FAQ (lue depuis la base), accès et horaires (lus depuis la base),
assistant en bas à droite. Aucun prix affiché : le devis se fait après l'examen.

**Moteur de créneaux** (`app/app/Booking`) — horaires du jour, moins les pauses, les
congés et les rendez-vous, sur une grille de 15 min, pour la durée du soin. La
réservation verrouille la ligne du praticien (`SELECT … FOR UPDATE`) et revérifie ; un
index unique sur une colonne générée (`slot_lock`, NULL quand le RDV est annulé) est le
dernier filet contre le double booking.

**Assistant** (`app/app/Assistant`) — une machine à états. Le modèle dit ce que le patient
veut ; Laravel décide quoi faire. Les heures proposées et les réponses viennent toujours
de la base, jamais du modèle. Il répond dans le registre du patient (darija latine,
arabe, français).

**Dashboard** — Aujourd'hui (programme, statuts, demandes, patient au fauteuil + schéma
dentaire), Agenda (glisser-déposer, création, fermetures), Demandes (confirmer, refuser,
accepter un déplacement, transformer une question en FAQ), Patients, Assistant AI
(santé du modèle, intentions, corrections), Horaires & soins.

## L'IA, en détail

| Brique | Méthode | Pourquoi |
|---|---|---|
| Intention (12 classes) | TF-IDF n-grammes de caractères 2–5 + régression logistique | la darija s'écrit de dix façons : `3andi`, `andi`, `عندي` partagent leurs n-grammes |
| Entités | règles + lexique (`ghedda`, `nhar jem3a`, `f l3chiya`, `m3a jouj w nos`, `wjee3`…) | une date réservée ne doit jamais être une supposition |
| FAQ | similarité cosinus sur la FAQ validée du cabinet, seuil 0,45 | domaine médical : pas de génération libre |
| Décision | trois niveaux de confiance | le modèle sait quand il doute |

Résultats sur **105 phrases de test écrites à la main**, jamais vues à l'entraînement
(`nlu/reports/metrics.md`) :

| représentation | F1 validation croisée (5 plis groupés par gabarit) | F1 test |
|---|---|---|
| mots 1–2 | 0,612 | 0,804 |
| **caractères 2–5** | **0,710** | **0,872** |
| mots + caractères | 0,689 | 0,854 |

| niveau | confiance | part du test | ce que fait l'assistant | justesse |
|---|---|---|---|---|
| agit seul | ≥ 0,58 | 70 % | répond / réserve | 99 % |
| demande confirmation | 0,26 – 0,58 | 25 % | propose 3 boutons | bonne réponse parmi les 3 : 92 % |
| passe la main | < 0,26 | 5 % | crée une demande pour le cabinet | — |

**Boucle d'amélioration.** Dans *Assistant AI*, l'équipe corrige une intention mal
comprise. Ensuite :

```bash
docker compose exec app php artisan nlu:export      # corrections -> nlu/data/corrections.jsonl
docker compose exec nlu sh -c "python data/build_dataset.py && python -W ignore train.py"
docker compose restart nlu                            # charge la nouvelle version
```

Une expérience ratée, gardée volontairement : ajouter les variantes de la FAQ comme
exemples `question_faq` a fait baisser le test de 0,867 à 0,857 (« kayn parking ? » est
une question de FAQ mais une intention `adresse`). Seules les corrections humaines sont
exportées.

## Tests

```bash
docker compose exec app php artisan test                 # 28 tests : créneaux, dialogue, dashboard
docker compose exec nlu python -W ignore -m pytest -q    # 47 tests : entités, API, FAQ
```

## Choix et limites

- Un seul praticien actif ; le schéma en accepte plusieurs.
- WhatsApp : liens `wa.me` pré-remplis. Les rappels automatiques (API Meta) sont prévus en v2.
- `php artisan serve` sert l'app en développement ; en production, mettre PHP-FPM + Nginx
  derrière un VPS (le service Python demande plus qu'un hébergement mutualisé).
- Photos : Unsplash (licence libre), sources dans `app/public/img/SOURCES.json`.

---

Conçu et développé par [@boutayna_metarfi](https://instagram.com/boutayna_metarfi).
Projet de démonstration : le cabinet, la dentiste et les patients sont fictifs.
