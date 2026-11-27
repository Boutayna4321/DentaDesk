# Intent model · version 20261021-1129

Data: 2642 training sentences (templates + spelling variants), 5-fold cross-validation grouped by template, **126 hand-written test sentences**, never seen during training nor used to tune anything.

## Feature comparison

| features | macro F1 cross-validation | macro F1 test |
|---|---|---|
| words (1-2) | 0.584 | 0.789 |
| characters (2-5) ✔ | 0.665 | 0.844 |
| words + characters | 0.646 | 0.841 |

Kept: **characters (2-5)**, logistic regression (C=8, balanced classes).

## Result on the hand-written test set

- Accuracy: **84.1%**  ·  macro F1: **0.844**

Thresholds chosen on cross-validation, then applied to the test set:

| tier | confidence | share of test | what the assistant does | accuracy |
|---|---|---|---|---|
| acts alone | ≥ 0.76 | 52% | answers / books | correct intent 98% |
| asks to confirm | 0.36 – 0.76 | 37% | offers 3 buttons | right answer among the 3: 96% |
| hands over | < 0.36 | 10% | creates a request for the practice | — |

```
              precision    recall  f1-score   support

     adresse       0.82      0.75      0.78        12
    affirmer       1.00      1.00      1.00         7
     annuler       0.91      0.83      0.87        12
    deplacer       0.80      1.00      0.89        12
    horaires       1.00      0.67      0.80        12
  hors_sujet       0.50      0.25      0.33         8
       merci       1.00      1.00      1.00         7
        nier       1.00      1.00      1.00         7
 prendre_rdv       0.65      0.73      0.69        15
question_faq       0.67      1.00      0.80        12
  salutation       1.00      1.00      1.00         7
     urgence       1.00      0.93      0.97        15

    accuracy                           0.84       126
   macro avg       0.86      0.85      0.84       126
weighted avg       0.85      0.84      0.83       126

```

## Confusion matrix

| true \ predicted | adress | affirm | annule | deplac | horair | hors_s | merci | nier | prendr | questi | saluta | urgenc |
|---|---|---|---|---|---|---|---|---|---|---|---|---|
| **adresse** | 9 | · | · | · | · | 1 | · | · | · | 2 | · | · |
| **affirmer** | · | 7 | · | · | · | · | · | · | · | · | · | · |
| **annuler** | · | · | 10 | 1 | · | · | · | · | 1 | · | · | · |
| **deplacer** | · | · | · | 12 | · | · | · | · | · | · | · | · |
| **horaires** | · | · | · | · | 8 | · | · | · | 2 | 2 | · | · |
| **hors_sujet** | 2 | · | · | · | · | 2 | · | · | 3 | 1 | · | · |
| **merci** | · | · | · | · | · | · | 7 | · | · | · | · | · |
| **nier** | · | · | · | · | · | · | · | 7 | · | · | · | · |
| **prendre_rdv** | · | · | 1 | 2 | · | 1 | · | · | 11 | · | · | · |
| **question_faq** | · | · | · | · | · | · | · | · | · | 12 | · | · |
| **salutation** | · | · | · | · | · | · | · | · | · | · | 7 | · |
| **urgence** | · | · | · | · | · | · | · | · | · | 1 | · | 14 |

## Remaining errors

| message | expected | predicted | confidence |
|---|---|---|---|
| momkin tchoufi liya chi w9t ghedda 9bel tnach | prendre_rdv | deplacer | 0.44 |
| kanqelleb 3la rendez-vous l mmi | prendre_rdv | hors_sujet | 0.64 |
| بغيت نجي عندكم الأسبوع الجاي | prendre_rdv | annuler | 0.27 |
| le plombage est tombé et ça fait très mal | urgence | question_faq | 0.87 |
| 3afak 7iydo rendez-vous dyali | annuler | deplacer | 0.40 |
| واش خدامين نهار الأحد | horaires | prendre_rdv | 0.23 |
| vous travaillez pendant les vacances ? | horaires | question_faq | 0.53 |
| شحال من ساعة كتحلو | horaires | question_faq | 0.46 |
| kifach nji mn casa port | adresse | hors_sujet | 0.61 |
| vous êtes à côté de quoi ? | adresse | question_faq | 0.50 |
| vous connaissez un bon garagiste ? | hors_sujet | prendre_rdv | 0.39 |
| bghit nesowlek 3la lfoot | hors_sujet | prendre_rdv | 0.65 |
| c'est quoi la capitale du Japon | hors_sujet | adresse | 0.30 |
| واش كتعرف شي طبيب ديال القلب | hors_sujet | question_faq | 0.24 |
| je veux acheter une voiture | hors_sujet | prendre_rdv | 0.23 |
| could you fit me in on Monday morning | prendre_rdv | deplacer | 0.37 |
| please drop my appointment, I'm travelling | annuler | prendre_rdv | 0.49 |
| do you work on Saturdays | horaires | prendre_rdv | 0.27 |
| how can I find your office | adresse | question_faq | 0.21 |
| can you recommend a hotel in Marrakech | hors_sujet | adresse | 0.21 |
