# Post LinkedIn · snan.

Vidéo LinkedIn : `demo/out/snan-demo-fr-linkedin.mp4` (16:9) + musique « Technology Corporate » (The_Mountain, Pixabay), export final dans `exports/`.
Vidéo Instagram : `demo/out/snan-demo-fr.mp4` (4:5, sans son, musique ajoutée dans Instagram).
Publier la vidéo directement sur LinkedIn (pas un lien YouTube) : elle se lance seule dans le fil.
Le lien vers le code ou la démo va dans le premier commentaire, pas dans le post.

---

## Version française

Un patient écrit à minuit : « 3andi wjee3 f dersi, kayn chi blassa ghedda ? ».
Le lendemain matin, son rendez-vous est déjà dans l'agenda du cabinet.

C'est snan., le logiciel d'un cabinet dentaire à Casablanca que j'ai conçu et développé de A à Z (projet de démonstration). La vidéo suit un patient, Youssef, pendant une journée au cabinet.

Côté patient
→ Un site qui réserve sur les vrais créneaux libres. Deux personnes ne peuvent jamais prendre la même heure, même si elles cliquent à la même seconde.
→ Un assistant qui comprend la darija, le français, l'arabe et l'anglais, et qui répond dans la langue du patient.

Côté secrétariat
→ La journée sur un seul écran : qui est au fauteuil, qui attend, ce que l'assistant a réservé pendant la nuit.
→ Chaque demande écrite en darija est expliquée en français ou en anglais, avec un petit glossaire des mots. Un clic pour confirmer.
→ Un agenda de la semaine en glisser-déposer, avec le statut de chaque rendez-vous.

Côté dentiste
→ Le dossier d'un patient en deux secondes avec la recherche (Ctrl K).
→ Un schéma dentaire dessiné comme une vraie bouche : 5 faces par dent, carie, couronne, implant, dent dévitalisée, et l'historique de chaque modification. Sur téléphone, une arcade à la fois et des dents à la taille du doigt.
→ Des factures et des devis qu'elle rédige elle-même à partir de ses tarifs. Numérotés sans trou, verrouillés une fois validés, montant écrit en toutes lettres. La secrétaire imprime et encaisse.
→ Des statistiques qui se lisent : 4 phrases à retenir, le remplissage heure par heure, ce qui rapporte vraiment, ce qui reste à encaisser.

Côté équipe
→ Une messagerie interne dentiste ↔ secrétariat.
→ Des comptes avec des droits différents, qu'on peut activer, désactiver ou supprimer, et un journal de ce que chacun a fait.

La partie dont je suis le plus fière, c'est l'IA.
Pas d'API externe : les données des patients restent au cabinet. J'ai construit le jeu de données, entraîné le modèle et mesuré ses erreurs sur 126 phrases écrites à la main. Résultat : 84 % de bonnes réponses.
Quand il hésite, il pose une question. Quand il ne sait pas, il passe la main au cabinet au lieu d'inventer.

Côté technique : Laravel 13, PHP 8.4, MySQL 8, Python (scikit-learn, FastAPI), JavaScript, Bootstrap 5, Docker. 128 tests automatisés.

Master en Génie logiciel & Intelligence artificielle : ce projet, c'est ce que j'ai appris, mis au service d'un vrai problème.

Si vous travaillez dans la santé ou si vous développez ce genre d'outil, qu'est-ce que vous ajouteriez ?

#Laravel #PHP #MySQL #Python #ScikitLearn #FastAPI #MachineLearning #NLP #IntelligenceArtificielle #JavaScript #Bootstrap #Docker #FullStack #GenieLogiciel #Darija

---

## English version

A patient types "3andi wjee3 f dersi, kayn chi blassa ghedda?" (Moroccan Darija for "my tooth hurts, any slot tomorrow?").
10 seconds later, the appointment is in the practice's calendar.

I designed and built snan., complete software for a dental practice in Casablanca (demo project):

→ a website that books real time slots, with no double booking possible
→ an assistant that understands Darija, French, Arabic and English
→ the dentist's dashboard: today, calendar, patient files, a dental chart drawn like a mouth
→ numbered invoices and quotes, printed and collected by the front desk
→ statistics you can read in 4 sentences

The part I'm proudest of is the AI.
No external API. I built the dataset, trained the model and measured its mistakes on 126 hand-written sentences: 84% right.
When it hesitates, it asks. When it doesn't know, it hands the message to the practice instead of making something up.
And when the dentist doesn't read Darija, every message is explained in French or English.

Stack: Laravel 13, MySQL, Python (scikit-learn, FastAPI), Bootstrap, Docker. 128 automated tests.

Master's in Software Engineering & AI: this project is what I learned, put to work on a real problem.

What would you add?

#Laravel #MachineLearning #NLP #FullStack #AI

---

## Premier commentaire (à poster juste après)

FR : Le détail technique (architecture, évaluation du modèle, choix et limites) : [lien GitHub / README]
EN : Technical write-up (architecture, model evaluation, trade-offs): [GitHub link / README]

## Conseils de publication

- Mardi à jeudi, entre 8 h et 10 h (heure du Maroc).
- Répondre aux commentaires dans la première heure.
- Mettre la vidéo en « À la une » du profil.
