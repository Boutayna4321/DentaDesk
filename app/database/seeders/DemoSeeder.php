<?php

namespace Database\Seeders;

use App\Booking\Availability;
use App\Models\Appointment;
use App\Models\AssistantRequest;
use App\Models\Conversation;
use App\Models\Patient;
use App\Models\Practitioner;
use App\Models\Service;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * A believable day at the clinic, built around the moment the seeder runs, so the
 * dashboard always opens on a live "today": some patients done, one in the chair,
 * one in the waiting room, the rest to come.
 */
class DemoSeeder extends Seeder
{
    private Practitioner $dr;

    public function run(): void
    {
        mt_srand(21);
        $this->dr = Practitioner::firstOrFail();
        $now = CarbonImmutable::now();
        $today = $now->startOfDay();

        $people = [
            ['Karim', 'Tazi', '0612340001', 'cnss', null, '1988-03-14'],
            ['Hiba', 'Lahlou', '0612340002', 'mutuelle', null, '1999-07-02'],
            ['Salma', 'Bennani', '0612340003', 'cnss', 'Pénicilline', '1992-01-20'],
            ['Omar', 'El Fassi', '0612340004', 'cnops', null, '1975-11-30'],
            ['Rania', 'Squalli', '0612340005', null, null, '2001-05-09'],
            ['Mehdi', 'Berrada', '0612340006', 'cnss', null, '1983-09-17'],
            ['Nour', 'Alami', '0612340007', 'cnss', null, '2018-04-22'],
            ['Yassine', 'Chraibi', '0612340008', 'mutuelle', 'Latex', '1990-12-01'],
            ['Leila', 'Benjelloun', '0612340009', 'cnops', null, '1968-02-11'],
            ['Imane', 'Kettani', '0612340010', 'cnss', null, '1995-08-08'],
            ['Anas', 'Ouazzani', '0612340011', null, null, '1987-06-25'],
            ['Sara', 'Idrissi', '0612340012', 'cnss', null, '2003-10-13'],
            ['Hamza', 'Bennis', '0612340013', 'mutuelle', null, '1979-03-03'],
            ['Ghita', 'Sefrioui', '0612340014', 'cnss', 'Aspirine', '1993-12-19'],
            ['Adam', 'Kabbaj', '0612340015', 'cnss', null, '2016-01-30'],
            ['Zineb', 'Tahiri', '0612340016', 'cnops', null, '1985-04-04'],
        ];
        $patients = collect($people)->mapWithKeys(fn ($p) => [$p[0] => Patient::create([
            'first_name' => $p[0], 'last_name' => $p[1], 'phone' => $p[2], 'coverage' => $p[3],
            'allergies' => $p[4], 'birth_date' => $p[5],
        ])]);

        // Today's chair.
        $day = [
            ['09:00', 'Karim', 'detartrage', 'phone', null],
            ['09:30', 'Hiba', 'orthodontie', 'web', 'Contrôle aligneurs'],
            ['10:30', 'Salma', 'carie', 'assistant', 'Carie 36 · 2e séance'],
            ['11:15', 'Omar', 'consultation', 'web', null],
            ['12:00', 'Rania', 'blanchiment', 'assistant', '1re visite, voudrait un devis'],
            ['14:30', 'Mehdi', 'urgence', 'assistant', 'Douleur molaire depuis 2 jours'],
            ['15:30', 'Nour', 'enfant', 'phone', 'Contrôle enfant'],
            ['16:30', 'Yassine', 'detartrage', 'web', null],
            ['17:30', 'Leila', 'controle', 'phone', null],
        ];
        foreach ($day as [$time, $who, $slug, $source, $reason]) {
            $start = $today->setTimeFromTimeString($time);
            $this->appointment($patients[$who], $slug, $start, $this->statusFor($start, $now, $who), $source, $reason);
        }

        // Salma's history and odontogram, so the patient file has something to show.
        $this->appointment($patients['Salma'], 'carie', $today->subDays(13)->setTime(10, 30), 'done', 'assistant', 'Carie 36 · 1re séance',
            'Carie profonde sur 36, curetage et pansement provisoire. Composite à la séance suivante.');
        $this->appointment($patients['Salma'], 'detartrage', $today->subMonths(7)->setTime(16, 0), 'done', 'web', null, 'Détartrage complet. RAS.');
        foreach ([
            [36, 'present', ['O' => 'caries', 'D' => 'caries'], 'Carie profonde, composite prévu'],
            [15, 'present', ['O' => 'filling', 'M' => 'filling'], 'Composite 2024'],
            [26, 'crown', [], 'Couronne céramique 2022'],
            [46, 'root_canal', ['O' => 'filling'], 'Dévitalisée 2023'],
            [17, 'missing', [], 'Implant prévu (devis)'],
            [38, 'to_extract', [], 'Dent de sagesse incluse'],
            [24, 'present', ['V' => 'caries'], 'Collet sensible'],
        ] as [$tooth, $state, $surfaces, $note]) {
            $patients['Salma']->teeth()->create(['tooth' => $tooth, 'state' => $state, 'surfaces' => $surfaces ?: null, 'note' => $note]);
        }

        // Most of today's patients have been here before; Omar and Rania have not.
        foreach (['Karim', 'Hiba', 'Mehdi', 'Nour', 'Yassine', 'Leila'] as $who) {
            for ($k = 0, $n = mt_rand(1, 3); $k < $n; $k++) {
                $slug = ['controle', 'detartrage', 'carie'][mt_rand(0, 2)];
                $at = $today->subDays(mt_rand(20, 400))->setTime(mt_rand(9, 17), [0, 30][mt_rand(0, 1)]);
                if (app(Availability::class)->isFree($this->dr, Service::where('slug', $slug)->first(), $at, withinHours: false)) {
                    $this->appointment($patients[$who], $slug, $at, 'done', ['phone', 'web'][mt_rand(0, 1)]);
                }
            }
        }
        // Twelve weeks of history, so the agenda and the statistics look like a running practice.
        $first = ['Amine', 'Yasmine', 'Mohamed', 'Khadija', 'Ayoub', 'Fatima', 'Reda', 'Meryem', 'Ilyas', 'Nada', 'Othmane', 'Houda',
            'Badr', 'Soukaina', 'Zakaria', 'Asmae', 'Hicham', 'Loubna', 'Youssef', 'Ikram', 'Taha', 'Wiam', 'Saad', 'Chaimae', 'Mehdi', 'Hajar'];
        $last = ['El Amrani', 'Benali', 'Chafik', 'Lamrani', 'Bouzid', 'Tazi', 'Berrada', 'Filali', 'Sqalli', 'Bennani', 'Alaoui', 'Haddad',
            'Naciri', 'Kadiri', 'Bekkali', 'Fassi', 'Zniber', 'Mernissi', 'Guessous', 'Lahlou'];
        $pool = $patients->values()->all();
        for ($k = 0; $k < 60; $k++) {
            $pool[] = Patient::create([
                'first_name' => $first[mt_rand(0, count($first) - 1)], 'last_name' => $last[mt_rand(0, count($last) - 1)],
                'phone' => sprintf('06%08d', 30000000 + $k * 137), 'coverage' => ['cnss', 'cnss', 'cnops', 'mutuelle', null][mt_rand(0, 4)],
                'birth_date' => $today->subYears(mt_rand(5, 75))->subDays(mt_rand(0, 360)),
            ]);
        }
        $availabilityPast = app(Availability::class);
        $routine = Service::whereIn('slug', ['controle', 'detartrage', 'consultation', 'carie', 'carie', 'enfant', 'extraction', 'blanchiment', 'orthodontie', 'urgence'])->get();
        foreach (range(84, 1) as $back) {
            $d = $today->subDays($back);
            // Busier in recent weeks: the practice is growing.
            $wanted = mt_rand(5, 8) + ($back < 42 ? 2 : 0);
            for ($n = 0; $n < $wanted; $n++) {
                $service = $routine->random();
                $slots = $availabilityPast->slots($this->dr, $service, $d, $d->subDay());
                if ($slots->isEmpty()) {
                    break;
                }
                // Newer patients appear later: each patient's first visit is spread across the 12 weeks.
                $who = $pool[mt_rand(0, min(count($pool) - 1, 16 + intdiv((84 - $back) * 60, 84)))];
                $this->appointment($who, $service->slug, $slots->random(), mt_rand(0, 24) === 0 ? 'no_show' : 'done', ['phone', 'web', 'assistant', 'assistant'][mt_rand(0, 3)])
                    ->forceFill(['created_at' => $d->subDays(mt_rand(1, 9))->setTime(mt_rand(8, 22), mt_rand(0, 59))])->saveQuietly();
            }
        }

        // The next ten opening days, two to five bookings each.
        $availability = app(Availability::class);
        $services = Service::where('is_emergency', false)->get();
        $everyone = collect($pool);
        for ($i = 1; $i <= 14; $i++) {
            $d = $today->addDays($i);
            foreach ($everyone->shuffle()->take(max(1, 9 - $i / 2 + mt_rand(-1, 2))) as $patient) {
                $service = $services->random();
                $slots = $availability->slots($this->dr, $service, $d, $now);
                if ($slots->isEmpty()) {
                    break;
                }
                $a = $this->appointment($patient, $service->slug, $slots->random(), mt_rand(0, 3) ? 'confirmed' : 'pending',
                    ['phone', 'web', 'assistant', 'assistant'][mt_rand(0, 3)]);
                $a->forceFill(['created_at' => $now->subHours(mt_rand(2, 330))])->saveQuietly();
            }
        }

        $this->requests($today, $now);
    }

    private function statusFor(CarbonImmutable $start, CarbonImmutable $now, string $who): string
    {
        $end = $start->addMinutes(45);
        if ($end <= $now) {
            return $who === 'Nour' ? 'no_show' : 'done';
        }
        if ($start <= $now) {
            return 'in_care';
        }

        return $start->diffInMinutes($now, true) <= 40 ? 'arrived' : 'confirmed';
    }

    private function appointment(Patient $patient, string $slug, CarbonImmutable $start, string $status, string $source, ?string $reason = null, ?string $note = null): Appointment
    {
        $service = Service::where('slug', $slug)->firstOrFail();

        return Appointment::create([
            'patient_id' => $patient->id, 'practitioner_id' => $this->dr->id, 'service_id' => $service->id,
            'starts_at' => $start, 'ends_at' => $start->addMinutes($service->duration_minutes),
            'status' => $status, 'source' => $source, 'reason' => $reason, 'visit_note' => $note,
        ]);
    }

    /** Three conversations waiting for the clinic, like the morning after a busy night. */
    private function requests(CarbonImmutable $today, CarbonImmutable $now): void
    {
        $youssef = Patient::create(['first_name' => 'Youssef', 'last_name' => 'Amrani', 'phone' => '0661223344', 'coverage' => 'cnss']);
        $slot = app(Availability::class)->upcoming($this->dr, Service::where('slug', 'urgence')->first(), $today, limit: 1, now: $now)->first()
            ?? $today->addDay()->setTime(9, 0);
        $booking = $this->appointment($youssef, 'urgence', $slot, 'pending', 'assistant', '3andi wjee3 f dersi mn lbare7');
        $this->conversation($youssef, [
            ['patient', 'Salam, 3andi wjee3 f dersi mn lbare7, kayn chi blassa lyouma?', 'urgence', 0.97],
            ['assistant', 'Sme7 lia 3la lwje3. Hadi a9rab blayes:', null, null],
            ['patient', $slot->format('H:i'), 'affirmer', 0.61],
            ['assistant', 'Mzyan. Chno smitek 3afak?', null, null],
            ['patient', 'Youssef Amrani', null, null],
            ['assistant', '3tini ra9m dyalek Youssef, bach l cabinet yconfirmi m3ak f WhatsApp.', null, null],
            ['patient', '0661223344', null, null],
        ], $now->subMinutes(12), fn (Conversation $c) => AssistantRequest::create([
            'conversation_id' => $c->id, 'patient_id' => $youssef->id, 'appointment_id' => $booking->id, 'type' => 'booking',
            'message' => 'Salam, 3andi wjee3 f dersi mn lbare7, kayn chi blassa lyouma?',
            'summary' => 'Urgence · '.$slot->locale('fr')->translatedFormat('l j F').' à '.$slot->format('H:i'),
            'payload' => ['starts_at' => $slot->toIso8601String()],
        ]));

        $fatima = Patient::create(['first_name' => 'Fatima Zahra', 'last_name' => 'Kadiri', 'phone' => '0677889900', 'coverage' => 'cnops']);
        $enfant = Service::where('slug', 'enfant')->first();
        $kidSlot = collect([
            app(Availability::class)->upcoming($this->dr, $enfant, $today->addDays(2), limit: 1, window: ['16:00', '19:00'], now: $now)->first(),
            app(Availability::class)->upcoming($this->dr, $enfant, $today->addDays(4), limit: 1, window: ['09:00', '12:00'], now: $now)->first(),
        ])->filter()->values();
        if ($kidSlot->count() === 2) {
            $kid = $this->appointment($fatima, 'enfant', $kidSlot[0], 'confirmed', 'phone', 'Pour sa fille Lina, 7 ans');
            $this->conversation($fatima, [
                ['patient', 'Bghit nbeddel rendez-vous dyal bnti, ma nqderch f dak nhar', 'deplacer', 0.88],
                ['assistant', 'L9it rendez-vous dyalek. Hadi blayes khawyin:', null, null],
            ], $now->subMinutes(40), fn (Conversation $c) => AssistantRequest::create([
                'conversation_id' => $c->id, 'patient_id' => $fatima->id, 'appointment_id' => $kid->id, 'type' => 'reschedule',
                'message' => 'Bghit nbeddel rendez-vous dyal bnti, ma nqderch f dak nhar',
                'summary' => 'Déplacer '.$kidSlot[0]->locale('fr')->translatedFormat('D j M H:i').' → '.$kidSlot[1]->locale('fr')->translatedFormat('D j M H:i'),
                'payload' => ['from' => $kidSlot[0]->toIso8601String(), 'to' => $kidSlot[1]->toIso8601String()],
            ]));
        }

        $ahmed = Patient::create(['first_name' => 'Ahmed', 'last_name' => 'Rami', 'phone' => '0655443322']);
        $this->conversation($ahmed, [
            ['patient', 'Wach l3iyada fiha ascenseur? mmi ma kat9derch tla3 droj', 'question_faq', 0.71],
            ['assistant', 'Ma bghitch njaweb b chi 7aja machi s7i7a. Ghadi ndouz message dyalek l cabinet.', null, null],
        ], $now->subHours(10), fn (Conversation $c) => AssistantRequest::create([
            'conversation_id' => $c->id, 'patient_id' => $ahmed->id, 'type' => 'unanswered',
            'message' => 'Wach l3iyada fiha ascenseur? mmi ma kat9derch tla3 droj',
            'summary' => 'Question sans réponse sûre de l’assistant',
        ]));
    }

    private function conversation(Patient $patient, array $lines, CarbonImmutable $at, callable $then): void
    {
        $c = Conversation::create(['token' => (string) Str::uuid(), 'patient_id' => $patient->id, 'state' => ['lang' => 'da'], 'last_message_at' => $at]);
        foreach ($lines as [$role, $body, $intent, $conf]) {
            $c->messages()->create(['role' => $role, 'body' => $body, 'intent' => $intent, 'confidence' => $conf])
                ->forceFill(['created_at' => $at, 'updated_at' => $at])->saveQuietly();
        }
        $then($c)->forceFill(['created_at' => $at, 'updated_at' => $at])->saveQuietly();
    }
}
