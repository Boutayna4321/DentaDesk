# Modèle d'intention · version 20261021-1115

Données : 2053 phrases d'entraînement (gabarits + variantes d'orthographe), validation croisée 5 plis groupés par gabarit, **105 phrases de test écrites à la main**, jamais vues pendant l'entraînement ni utilisées pour régler quoi que ce soit.

## Comparaison des représentations

| représentation | F1 macro validation croisée | F1 macro test |
|---|---|---|
| mots (1-2) | 0.612 | 0.804 |
| caractères (2-5) ✔ | 0.710 | 0.872 |
| mots + caractères | 0.689 | 0.854 |

Retenu : **caractères (2-5)**, régression logistique (C=8, classes équilibrées).

## Résultat sur le test écrit à la main

- Exactitude : **86.7%**  ·  F1 macro : **0.872**

Seuils choisis sur la validation croisée, puis appliqués au test :

| niveau | confiance | part du test | ce que fait l'assistant | justesse |
|---|---|---|---|---|
| agit seul | ≥ 0.58 | 70% | répond / réserve | intention juste 99% |
| demande confirmation | 0.26 – 0.58 | 25% | propose 3 boutons | bonne réponse parmi les 3 : 92% |
| passe la main | < 0.26 | 5% | crée une demande pour le cabinet | — |

```
              precision    recall  f1-score   support

     adresse       0.89      0.80      0.84        10
    affirmer       1.00      1.00      1.00         6
     annuler       0.90      0.90      0.90        10
    deplacer       0.77      1.00      0.87        10
    horaires       0.88      0.70      0.78        10
  hors_sujet       0.60      0.43      0.50         7
       merci       1.00      1.00      1.00         6
        nier       1.00      1.00      1.00         6
 prendre_rdv       0.82      0.75      0.78        12
question_faq       0.71      1.00      0.83        10
  salutation       1.00      1.00      1.00         6
     urgence       1.00      0.92      0.96        12

    accuracy                           0.87       105
   macro avg       0.88      0.87      0.87       105
weighted avg       0.87      0.87      0.86       105

```

## Matrice de confusion

| vrai \ prédit | adress | affirm | annule | deplac | horair | hors_s | merci | nier | prendr | questi | saluta | urgenc |
|---|---|---|---|---|---|---|---|---|---|---|---|---|
| **adresse** | 8 | · | · | · | 1 | 1 | · | · | · | · | · | · |
| **affirmer** | · | 6 | · | · | · | · | · | · | · | · | · | · |
| **annuler** | · | · | 9 | 1 | · | · | · | · | · | · | · | · |
| **deplacer** | · | · | · | 10 | · | · | · | · | · | · | · | · |
| **horaires** | · | · | · | · | 7 | · | · | · | 1 | 2 | · | · |
| **hors_sujet** | 1 | · | · | 1 | · | 3 | · | · | 1 | 1 | · | · |
| **merci** | · | · | · | · | · | · | 6 | · | · | · | · | · |
| **nier** | · | · | · | · | · | · | · | 6 | · | · | · | · |
| **prendre_rdv** | · | · | 1 | 1 | · | 1 | · | · | 9 | · | · | · |
| **question_faq** | · | · | · | · | · | · | · | · | · | 10 | · | · |
| **salutation** | · | · | · | · | · | · | · | · | · | · | 6 | · |
| **urgence** | · | · | · | · | · | · | · | · | · | 1 | · | 11 |

## Erreurs restantes

| message | attendu | prédit | confiance |
|---|---|---|---|
| momkin tchoufi liya chi w9t ghedda 9bel tnach | prendre_rdv | deplacer | 0.40 |
| kanqelleb 3la rendez-vous l mmi | prendre_rdv | hors_sujet | 0.48 |
| بغيت نجي عندكم الأسبوع الجاي | prendre_rdv | annuler | 0.29 |
| le plombage est tombé et ça fait très mal | urgence | question_faq | 0.77 |
| 3afak 7iydo rendez-vous dyali | annuler | deplacer | 0.36 |
| واش خدامين نهار الأحد | horaires | prendre_rdv | 0.22 |
| vous travaillez pendant les vacances ? | horaires | question_faq | 0.55 |
| شحال من ساعة كتحلو | horaires | question_faq | 0.44 |
| kifach nji mn casa port | adresse | hors_sujet | 0.48 |
| vous êtes à côté de quoi ? | adresse | horaires | 0.24 |
| vous connaissez un bon garagiste ? | hors_sujet | prendre_rdv | 0.36 |
| c'est quoi la capitale du Japon | hors_sujet | adresse | 0.57 |
| واش كتعرف شي طبيب ديال القلب | hors_sujet | question_faq | 0.27 |
| je veux acheter une voiture | hors_sujet | deplacer | 0.18 |
