<?php

namespace Tests\Feature\Assistant;

use App\Models\Appointment;
use App\Models\AssistantRequest;
use App\Models\Patient;
use Carbon\CarbonImmutable;
use Database\Seeders\CabinetSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Whole conversations through the HTTP endpoint. The NLU service is faked with what it
 * really returns for these messages, so these tests pin the dialogue, not the model.
 */
class DialogueTest extends TestCase
{
    use RefreshDatabase;

    private array $nlu = [];

    private bool $nluDown = false;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(CarbonImmutable::parse('2026-10-21 08:00')); // Wednesday
        $this->seed(CabinetSeeder::class);

        Http::fake(function (Request $request) {
            if ($this->nluDown) {
                return Http::response(null, 500);
            }
            if (str_ends_with($request->url(), '/faq')) {
                return Http::response(['match' => $this->nlu['faq'] ?? null]);
            }

            return Http::response($this->nlu[$request['text']] ?? $this->parsed('hors_sujet', 0.9));
        });
    }

    private function parsed(string $intent, float $confidence, array $entities = [], string $tier = 'act', array $alternatives = []): array
    {
        return [
            'intent' => $intent, 'confidence' => $confidence, 'tier' => $tier,
            'alternatives' => $alternatives ?: [['intent' => $intent, 'confidence' => $confidence]],
            'entities' => array_merge(['date' => null, 'part_of_day' => null, 'time' => null, 'service' => null,
                'emergency' => false, 'phone' => null, 'name' => null], $entities),
        ];
    }

    private function say(string $message, ?string $token = null, ?string $value = null): array
    {
        return $this->postJson('/api/assistant', array_filter(['message' => $message, 'token' => $token, 'value' => $value]))
            ->assertOk()->json();
    }

    public function test_an_emergency_in_darija_ends_with_a_pending_booking(): void
    {
        $first = 'Salam, 3andi wjee3 f dersi, kayn chi blassa ghedda f sba7?';
        $this->nlu[$first] = $this->parsed('urgence', 0.97, ['date' => '2026-10-22', 'part_of_day' => 'morning', 'service' => 'urgence', 'emergency' => true]);

        $r = $this->say($first);
        $this->assertStringContainsString('Sme7 lia 3la lwje3', $r['text'], 'answers in darija');
        $this->assertSame('2026-10-22 09:00', CarbonImmutable::parse(substr($r['chips'][0]['value'], 5))->format('Y-m-d H:i'));

        $r = $this->say('Jeu 22 · 09:00', $r['token'], $r['chips'][0]['value']);
        $this->assertStringContainsString('Chno smitek', $r['text']);

        $this->nlu['Youssef Amrani'] = $this->parsed('hors_sujet', 0.4, tier: 'clarify');
        $r = $this->say('Youssef Amrani', $r['token']);
        $this->assertStringContainsString('3tini ra9m', $r['text']);

        $this->nlu['06 61 22 33 44'] = $this->parsed('hors_sujet', 0.5, ['phone' => '0661223344'], 'clarify');
        $r = $this->say('06 61 22 33 44', $r['token']);

        $this->assertStringContainsString('Safi Youssef', $r['text']);
        $appointment = Appointment::sole();
        $this->assertSame(['pending', 'assistant', 'urgence'], [$appointment->status, $appointment->source, $appointment->service->slug]);
        $this->assertSame('2026-10-22 09:00', $appointment->starts_at->format('Y-m-d H:i'));
        $this->assertSame('Amrani', Patient::sole()->last_name);
        $this->assertSame($first, AssistantRequest::sole()->message, 'the clinic sees the original message');
    }

    public function test_a_precise_free_time_skips_the_slot_list(): void
    {
        $text = 'Je voudrais un détartrage demain à 10h30';
        $this->nlu[$text] = $this->parsed('prendre_rdv', 0.93, ['date' => '2026-10-22', 'time' => '10:30', 'service' => 'detartrage']);

        $r = $this->say($text);

        $this->assertSame('Parfait, demain à 10:30. À quel nom je réserve ?', $r['text']);
    }

    public function test_doubt_turns_into_buttons_not_a_guess(): void
    {
        $text = 'rendez-vous dyal bnti';
        $this->nlu[$text] = $this->parsed('deplacer', 0.41, tier: 'clarify', alternatives: [
            ['intent' => 'deplacer', 'confidence' => 0.41], ['intent' => 'prendre_rdv', 'confidence' => 0.33], ['intent' => 'annuler', 'confidence' => 0.2],
        ]);

        $r = $this->say($text);

        $this->assertSame(['intent:deplacer', 'intent:prendre_rdv', 'intent:annuler'], array_column($r['chips'], 'value'));

        $r = $this->say('Nakhod rendez-vous', $r['token'], 'intent:prendre_rdv');
        $this->assertStringStartsWith('Hadi a9rab blayes l consultation', $r['text']);
    }

    public function test_faq_answers_come_from_the_clinic_text(): void
    {
        $text = 'wach kat9blo cnss?';
        $this->nlu[$text] = $this->parsed('question_faq', 0.9);
        $this->nlu['faq'] = ['id' => \App\Models\Faq::where('question', 'like', '%CNSS%')->value('id'), 'score' => 0.82];

        $r = $this->say($text);

        $this->assertStringStartsWith('Oui. Nous remplissons la feuille de soins CNSS', $r['text']);
    }

    public function test_unknown_question_goes_to_the_clinic_with_the_phone(): void
    {
        $text = 'Wach kayn ascenseur?';
        $this->nlu[$text] = $this->parsed('question_faq', 0.7);

        $r = $this->say($text);
        $this->assertStringContainsString('ndouz message dyalek l cabinet', $r['text']);

        $this->nlu['0612345678'] = $this->parsed('hors_sujet', 0.3, ['phone' => '0612345678'], 'handover');
        $r = $this->say('0612345678', $r['token']);

        $this->assertStringContainsString('wsel l cabinet', $r['text']);
        $request = AssistantRequest::sole();
        $this->assertSame(['unanswered', $text, 'open'], [$request->type, $request->message, $request->status]);
    }

    public function test_cancel_by_phone_frees_the_slot(): void
    {
        $patient = Patient::create(['first_name' => 'Salma', 'phone' => '0612340003']);
        $appointment = Appointment::create(['patient_id' => $patient->id, 'practitioner_id' => \App\Models\Practitioner::value('id'), 'service_id' => \App\Models\Service::value('id'),
            'starts_at' => '2026-10-23 10:00', 'ends_at' => '2026-10-23 10:30', 'status' => 'confirmed']);

        $this->nlu['bghit nlghi rendez-vous'] = $this->parsed('annuler', 0.95);
        $r = $this->say('bghit nlghi rendez-vous');
        $this->assertStringContainsString('3tini ra9m li drti bih', $r['text']);

        $this->nlu['0612340003'] = $this->parsed('hors_sujet', 0.3, ['phone' => '0612340003'], 'handover');
        $r = $this->say('0612340003', $r['token']);
        $this->assertSame(['yes', 'no'], array_column($r['chips'], 'value'));

        $r = $this->say('Iyeh', $r['token'], 'yes');

        $this->assertStringContainsString('tlgha', $r['text']);
        $this->assertSame('cancelled', $appointment->fresh()->status);
    }

    public function test_hours_are_read_from_the_database(): void
    {
        $this->nlu['Quels sont vos horaires ?'] = $this->parsed('horaires', 0.95);

        $r = $this->say('Quels sont vos horaires ?');

        $this->assertSame('Le cabinet est ouvert : lundi–vendredi 09:00–13:00, 14:30–19:00 · samedi 09:00–13:00.', $r['text']);
    }

    public function test_when_the_nlu_is_down_the_patient_gets_the_phone_number(): void
    {
        $this->nluDown = true;

        $r = $this->say('salam bghit rendez-vous');

        $this->assertStringContainsString(config('cabinet.phone'), $r['text']);
    }

    public function test_website_booking_and_its_double_click(): void
    {
        $payload = ['service' => 'consultation', 'starts_at' => '2026-10-22 11:00', 'first_name' => 'Omar', 'phone' => '+212 6 87 65 43 21'];

        $this->postJson('/api/appointments', $payload)->assertCreated()->assertJsonPath('label', 'Jeudi 22 octobre à 11:00');
        $this->postJson('/api/appointments', $payload)->assertStatus(409);
        $this->assertSame('0687654321', Patient::sole()->phone);
    }

    public function test_an_english_speaker_gets_english_answers(): void
    {
        $text = 'Hi, my tooth hurts a lot, can I come tomorrow morning?';
        $this->nlu[$text] = $this->parsed('urgence', 0.95, ['date' => '2026-10-22', 'part_of_day' => 'morning', 'service' => 'urgence', 'emergency' => true]);

        $r = $this->say($text);

        $this->assertStringStartsWith('Sorry about the pain.', $r['text']);
        $this->assertSame('Tomorrow · 09:00', $r['chips'][0]['label']);

        $r = $this->say('Tomorrow · 09:00', $r['token'], $r['chips'][0]['value']);
        $this->assertSame('Great, tomorrow at 09:00. What name should I book it under?', $r['text']);
    }

    public function test_chatting_with_the_assistant_does_not_use_up_the_booking_limit(): void
    {
        for ($i = 0; $i < 8; $i++) {
            $this->say('salam');
        }

        $this->postJson('/api/appointments', ['service' => 'consultation', 'starts_at' => '2026-10-22 11:00', 'first_name' => 'Omar', 'phone' => '0687654321'])
            ->assertCreated();
    }
}
