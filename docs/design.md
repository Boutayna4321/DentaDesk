# DentaDesk — cabinet dentaire : site, réservation, dashboard, assistant

Projet #05 de la série DESIGN → CODE. But : montrer un vrai projet full-stack + IA
(master génie logiciel & IA), pas seulement une landing page.

## Ce que fait le produit

1. **Site public** (Dr Nadia Alaoui, Maârif, Casablanca). Style validé : blanc + bleu,
   grande photo patient, carte urgence, barre de réservation sous le hero, assistant
   en bas à droite. **Aucun prix affiché.**
2. **Réservation** : soin → jour → créneau libre → nom + téléphone → confirmation.
   Les créneaux viennent du moteur de créneaux, jamais du front.
3. **Assistant** : comprend darija (latin + arabe), français, arabe. Réserve, déplace,
   répond à la FAQ du cabinet. Ce qu'il ne comprend pas devient une *demande* dans le
   dashboard. Il n'invente jamais d'heure ni de réponse.
4. **Dashboard** (dentiste, secrétaire) v1 : Aujourd'hui, Agenda, Demandes, Patients
   (dossier + schéma dentaire FDI), Horaires & congés, Soins & durées.
   v2 : rappels WhatsApp (API Meta), statistiques, gestion d'équipe.

## Architecture

```
navigateur ──► Laravel 13 (Blade + Bootstrap 5.3 + JS vanilla, FullCalendar)
                 │  moteur de créneaux, dialogue, auth, API JSON
                 ├──► MySQL 8
                 └──► nlu (Python, FastAPI) : /parse  /faq
```

- **Laravel** possède la vérité : créneaux, réservations, état de la conversation.
- **nlu** est sans état : texte → intention + entités, question → meilleure FAQ + score.

## Moteur de créneaux

- Créneaux = horaires du jour − pauses − congés − RDV existants, sur une grille de
  15 min, pour la durée du soin.
- Réservation dans une transaction qui verrouille la ligne du praticien
  (`SELECT … FOR UPDATE`), revérifie le chevauchement, puis insère. Index unique
  `(practitioner_id, starts_at)` en filet de sécurité.

## IA (pas d'API externe)

| Brique | Méthode | Pourquoi |
|---|---|---|
| Intention | modèle entraîné par nous : TF-IDF n-grammes de caractères + régression logistique (baseline). Expérience : fine-tuning d'un encodeur multilingue | la darija s'écrit de 10 façons ; les n-grammes de caractères tiennent `3andi / andi / عندي` |
| Entités | lexique + règles (ghedda, ba3d ghedda, nhar jem3a, f sba7, l3chiya…), soins par synonymes | déterministe, testable |
| FAQ | recherche par similarité sur la FAQ validée du cabinet, seuil ; sous le seuil → demande humaine | domaine médical : pas de génération libre |
| Dialogue | machine à états (soin, jour, moment, créneau, nom, téléphone) côté Laravel | contrôlable, explicable |

Intentions : `prendre_rdv`, `urgence`, `annuler`, `deplacer`, `horaires`,
`adresse`, `question_faq`, `salutation`, `merci`, `hors_sujet`.

Dataset : construit par nous (gabarits + phrases écrites à la main + variantes
d'orthographe), split train/val, **test écrit à la main et jamais généré**. Rapport :
F1 par intention, matrice de confusion. Les demandes corrigées dans le dashboard
peuvent être exportées pour réentraîner.

## Choix faits par Claude (à revoir si besoin)

- Laravel 13 / PHP 8.4, tout en Docker (rien n'était installé sur la machine).
- Auth faite à la main (pas Breeze, qui impose Tailwind).
- Un seul praticien actif en v1, le schéma en accepte plusieurs.
- WhatsApp v1 : lien `wa.me` pré-rempli, pas d'envoi automatique.
