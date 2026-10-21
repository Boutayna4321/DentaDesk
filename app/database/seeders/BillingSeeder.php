<?php

namespace Database\Seeders;

use App\Billing\Billing;
use App\Billing\Letterhead;
use App\Models\Act;
use App\Models\Appointment;
use App\Models\Document;
use App\Models\Patient;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;

/** Price list, letterhead, and a believable history of invoices for the KPIs. */
class BillingSeeder extends Seeder
{
    public function run(): void
    {
        // Internal prices in dirhams (demo values). They never appear on the public site.
        $acts = [
            ['Consultation', 'C', 250], ['Contrôle', 'C', 200], ['Détartrage complet', 'D 12', 400], ['Composite 1 face', 'D 20', 500],
            ['Composite 2 faces', 'D 30', 700], ['Dévitalisation molaire', 'D 50', 1500], ['Extraction simple', 'D 15', 400],
            ['Extraction dent de sagesse', 'D 40', 1200], ['Radio panoramique', 'Z 20', 300], ['Blanchiment en cabinet', null, 2500],
            ['Couronne céramo-métallique', 'SPR 50', 3500], ['Implant (pose)', null, 9000], ['Bilan orthodontie', 'TO 10', 500],
            ['Urgence (soin de douleur)', 'D 10', 350], ['Soins enfant (séance)', 'D 10', 250],
        ];
        foreach ($acts as $i => [$label, $code, $dh]) {
            Act::create(['label' => $label, 'code' => $code, 'price_cents' => $dh * 100, 'position' => $i]);
        }

        Letterhead::save([
            'specialty' => 'Chirurgien-dentiste · Diplômée de la Faculté de médecine dentaire de Casablanca',
            'address' => config('cabinet.address').', 2e étage',
            // Fictitious identifiers for the demo.
            'inpe' => '000000000', 'ice' => '000000000000000', 'if' => '00000000', 'patente' => '00000000', 'cnom' => '0000',
            'footer' => 'Cabinet de démonstration · projet snan.',
            'quote_note' => 'Devis valable 3 mois. Pour une prise en charge, joignez-le à votre demande auprès de votre organisme.',
        ]);

        $billing = app(Billing::class);
        $dentist = User::where('role', 'dentist')->firstOrFail();
        $byService = fn (string $slug) => Act::where('label', 'like', match ($slug) {
            'detartrage' => 'Détartrage%', 'carie' => 'Composite 2%', 'extraction' => 'Extraction simple%', 'blanchiment' => 'Blanchiment%',
            'controle' => 'Contrôle%', 'urgence' => 'Urgence%', 'enfant' => 'Soins enfant%', 'orthodontie' => 'Bilan ortho%', default => 'Consultation%',
        })->first();

        mt_srand(7);
        $done = Appointment::with('service', 'patient')->where('status', 'done')
            ->where('starts_at', '>=', now()->subDays(75))->orderBy('starts_at')->get();
        foreach ($done as $i => $a) {
            $act = $byService($a->service->slug);
            $lines = [['label' => $act->label, 'tooth' => in_array($a->service->slug, ['carie', 'extraction']) ? [16, 26, 36, 46, 47][mt_rand(0, 4)] : null,
                'code' => $act->code, 'quantity' => 1, 'unit_price_cents' => $act->price_cents]];
            if (mt_rand(0, 3) === 0) {
                $pano = Act::where('label', 'Radio panoramique')->first();
                $lines[] = ['label' => $pano->label, 'tooth' => null, 'code' => $pano->code, 'quantity' => 1, 'unit_price_cents' => $pano->price_cents];
            }
            $doc = Document::create(['type' => 'invoice', 'patient_id' => $a->patient_id, 'appointment_id' => $a->id, 'created_by' => $dentist->id]);
            $billing->saveDraft($doc, $lines, null);
            $billing->validate($doc, $dentist);
            $day = $a->starts_at->toImmutable();
            $doc->update(['issued_on' => $day, 'validated_at' => $day->setTime(18, 0)]);

            // Most pay on the day; a few pay half, a few not yet.
            $roll = mt_rand(0, 9);
            if ($roll < 8 || $day->lt(now()->subDays(3))) {
                $amount = $roll === 7 ? intdiv($doc->total_cents, 2) : $doc->total_cents;
                $p = $billing->pay($doc, $amount, ['especes', 'especes', 'carte', 'cheque'][mt_rand(0, 3)], null, $dentist);
                $p->update(['paid_on' => $day]);
            }
        }

        // Salma's implant plan: a validated quote, then today's draft.
        $salma = Patient::where('first_name', 'Salma')->first();
        $quote = Document::create(['type' => 'quote', 'patient_id' => $salma->id, 'created_by' => $dentist->id]);
        $billing->saveDraft($quote, [
            ['label' => 'Extraction dent de sagesse', 'tooth' => 38, 'code' => 'D 40', 'quantity' => 1, 'unit_price_cents' => 120000],
            ['label' => 'Implant (pose)', 'tooth' => 17, 'code' => null, 'quantity' => 1, 'unit_price_cents' => 900000],
            ['label' => 'Couronne céramo-métallique', 'tooth' => 17, 'code' => 'SPR 50', 'quantity' => 1, 'unit_price_cents' => 350000],
        ], "Séance 1 : extraction de la 38.\nSéance 2 (après cicatrisation, environ 3 mois) : pose de l’implant en 17.\nSéance 3 (3 à 6 mois après) : couronne définitive.");
        $billing->validate($quote, $dentist);

        $today = Appointment::where('patient_id', $salma->id)->whereDate('starts_at', CarbonImmutable::today())->first();
        $draft = Document::create(['type' => 'invoice', 'patient_id' => $salma->id, 'appointment_id' => $today?->id, 'created_by' => $dentist->id]);
        $billing->saveDraft($draft, [['label' => 'Composite 2 faces', 'tooth' => 36, 'code' => 'D 30', 'quantity' => 1, 'unit_price_cents' => 70000]],
            'Carie 36 traitée (2e séance). Contrôle dans 6 mois.');
    }
}
