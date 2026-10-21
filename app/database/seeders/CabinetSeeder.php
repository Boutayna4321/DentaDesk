<?php

namespace Database\Seeders;

use App\Models\Faq;
use App\Models\Practitioner;
use App\Models\Service;
use App\Models\User;
use Illuminate\Database\Seeder;

/** What a real clinic would configure once: practitioner, hours, services, FAQ. */
class CabinetSeeder extends Seeder
{
    public function run(?User $dentist = null): void
    {
        $dr = Practitioner::create(['user_id' => $dentist?->id, 'name' => 'Dr Nadia Alaoui', 'title' => 'Chirurgien-dentiste']);

        foreach ([1, 2, 3, 4, 5] as $day) {
            $dr->workingHours()->createMany([
                ['weekday' => $day, 'opens_at' => '09:00', 'closes_at' => '13:00'],
                ['weekday' => $day, 'opens_at' => '14:30', 'closes_at' => '19:00'],
            ]);
        }
        $dr->workingHours()->create(['weekday' => 6, 'opens_at' => '09:00', 'closes_at' => '13:00']);

        $services = [
            ['consultation', 'Consultation', 'Premier rendez-vous : examen, radio si besoin, plan de soins écrit.', 30],
            ['controle', 'Contrôle', 'Visite de contrôle tous les six mois.', 30],
            ['detartrage', 'Détartrage', 'Détartrage et polissage, sans douleur.', 30],
            ['carie', 'Soin de carie', 'Carie, plombage composite de la couleur de la dent.', 45],
            ['extraction', 'Extraction', 'Dent abîmée ou dent de sagesse, sous anesthésie locale.', 45],
            ['blanchiment', 'Blanchiment', 'Un sourire plus clair, sans abîmer l’émail.', 60],
            ['implant', 'Bilan implant', 'Remplacer une dent manquante : examen et plan de traitement.', 45],
            ['orthodontie', 'Bilan orthodontie', 'Bagues ou aligneurs transparents, enfants et adultes.', 45],
            ['enfant', 'Soins enfant', 'Première visite en douceur, dès 3 ans.', 30],
            ['urgence', 'Urgence', 'Douleur, abcès, dent cassée : reçu le jour même.', 30],
        ];
        foreach ($services as $i => [$slug, $name, $summary, $minutes]) {
            Service::create([
                'slug' => $slug, 'name' => $name, 'summary' => $summary, 'duration_minutes' => $minutes,
                'is_emergency' => $slug === 'urgence', 'position' => $i,
            ]);
        }

        $faqs = [
            ['Vous acceptez la CNSS et les mutuelles ?',
                'Oui. Nous remplissons la feuille de soins CNSS, CNOPS et la plupart des mutuelles au cabinet. Apportez votre carte d’immatriculation.',
                "wach kat9blo cnss\nwach kat9blo la cnops\nwach kat3mro feuille de soins\nl'assurance\nla mutuelle\nwach kat9blo l'assurance\nواش كتقبلو CNSS\nالتعاضدية\nprise en charge assurance"],
            ['Peut-on payer par carte ou en plusieurs fois ?',
                'Oui, carte bancaire et espèces. Pour les traitements longs (implant, aligneurs), le paiement se fait en plusieurs fois, selon le devis.',
                "wach nkhles b carte\npaiement carte\nkhlas b tfrri9\nwach kaynin facilites f lkhlas\npaiement en plusieurs fois\nواش نخلص بالكارط\ncarte bancaire"],
            ['Vous soignez les enfants ?',
                'Oui, dès 3 ans. La première visite sert surtout à faire connaissance, sans soin forcé.',
                "wach katdaweo drari sghar\nwach katkhdmo m3a drari\ndentiste pour enfant\nwlidi 3ando 4 snin\nواش كتداويو الدراري الصغار\npédiatrique"],
            ['Combien coûte un soin ?',
                'Le prix dépend de ce que montre l’examen. Après la consultation, vous repartez avec un devis écrit, sans engagement.',
                "ch7al taman\nch7al kayswa\nbch7al\ncombien coûte\nquel est le prix\ntarif\nشحال كيسوا\nكم الثمن\nprix détartrage\nprix blanchiment\nprix implant"],
            ['Vous faites des devis ?',
                'Oui, un devis écrit et détaillé après la consultation. Vous le gardez et prenez le temps de décider.',
                "wach kat3tiw devis\ndevis gratuit\nbghit devis\nواش كتعطيو ديفي"],
            ['Vous avez la radio panoramique ?',
                'Oui, radio panoramique numérique sur place : pas besoin d’aller dans un centre de radiologie.',
                "wach kayn radio panoramique\nradio sur place\nwach kat3mlo radio\nواش كاينة الراديو"],
            ['Le blanchiment fait mal ? Combien de temps ?',
                'Une séance d’environ une heure. Une légère sensibilité peut durer un ou deux jours, rien de plus.',
                "wach tbyed kayderr\nblanchiment douleur\nle blanchiment fait mal\nch7al kaykhod blanchiment\nواش التبييض كيضر"],
            ['Combien de temps dure un implant ?',
                'Compter 3 à 6 mois entre la pose et la couronne définitive, le temps que l’os cicatrise. Bien entretenu, un implant dure des décennies.',
                "ch7al kaykhod implant\nimplant combien de temps\nwach implant kayderr\nزرع السنان شحال كياخد"],
            ['Vous faites les aligneurs transparents ?',
                'Oui, aligneurs transparents et bagues classiques. Le bilan orthodontie dit lequel convient.',
                "aligneurs transparents\ngouttières\nwach kaydirou appareil li ma kaybanch\nتقويم شفاف"],
            ['Que faut-il apporter au premier rendez-vous ?',
                'Votre CIN, la carte CNSS ou mutuelle, et vos anciennes radios si vous en avez.',
                "chno njib m3aya\nchno khassni njib\npremier rendez-vous documents\nشنو نجيب معايا"],
            ['Il y a un parking ?',
                'Parking payant en face du cabinet, et la station de tram Maârif est à 3 minutes à pied.',
                "kayn parking\nfin nparki\nfin nwe9ef tomobil\nواش كاين الباركينغ"],
            ['Le docteur parle quelles langues ?',
                'Darija, arabe, français et anglais.',
                "wach tbiba kat3ref tahder b l'anglais\nyou speak english\nquelles langues\nواش كتهضر بالفرنسية"],
        ];
        foreach ($faqs as [$q, $a, $v]) {
            Faq::create(['question' => $q, 'answer' => $a, 'variants' => $v]);
        }
    }
}
