<?php

namespace Tests\Feature\Dashboard;

use App\Models\Appointment;
use App\Models\AssistantRequest;
use App\Models\Faq;
use App\Models\Patient;
use App\Models\Practitioner;
use App\Models\Service;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\CabinetSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    private User $dentist;

    private User $secretary;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(CarbonImmutable::parse('2026-10-21 08:00'));
        Http::fake(['*' => Http::response(null, 500)]); // NLU offline: pages must still render
        $this->dentist = User::create(['name' => 'Dr Nadia Alaoui', 'email' => 'd@test', 'password' => 'x', 'role' => 'dentist']);
        $this->secretary = User::create(['name' => 'Khadija Amrani', 'email' => 's@test', 'password' => 'x', 'role' => 'secretary']);
        $this->seed(CabinetSeeder::class);
    }

    private function appointment(string $start, string $status = 'pending'): Appointment
    {
        $patient = Patient::firstOrCreate(['phone' => '0612345678'], ['first_name' => 'Salma']);
        $service = Service::where('slug', 'consultation')->first();

        return Appointment::create(['patient_id' => $patient->id, 'practitioner_id' => Practitioner::value('id'), 'service_id' => $service->id,
            'starts_at' => $start, 'ends_at' => CarbonImmutable::parse($start)->addMinutes(30), 'status' => $status, 'source' => 'assistant']);
    }

    public function test_guests_are_sent_to_login(): void
    {
        $this->get('/dashboard')->assertRedirect('/login');
    }

    public function test_every_page_renders(): void
    {
        $patient = $this->appointment('2026-10-21 10:00')->patient;
        foreach (['/dashboard', '/dashboard/agenda', '/dashboard/demandes', '/dashboard/patients', "/dashboard/patients/{$patient->id}",
            '/dashboard/assistant', '/dashboard/cabinet'] as $url) {
            $this->actingAs($this->dentist)->get($url)->assertOk();
        }
    }

    public function test_the_front_desk_cannot_see_or_edit_the_odontogram(): void
    {
        $patient = $this->appointment('2026-10-21 10:00')->patient;

        $this->actingAs($this->secretary)->get("/dashboard/patients/{$patient->id}")->assertOk()->assertDontSee('Schéma dentaire');
        $tooth = ['tooth' => 36, 'state' => 'present', 'surfaces' => ['O' => 'caries', 'D' => 'caries']];
        $this->actingAs($this->secretary)->postJson("/dashboard/patients/{$patient->id}/teeth", $tooth)->assertForbidden();
        $this->actingAs($this->dentist)->postJson("/dashboard/patients/{$patient->id}/teeth", $tooth)->assertOk()->assertJsonPath('label', 'Carie O, D');
        $this->assertEquals(['O' => 'caries', 'D' => 'caries'], $patient->teeth()->where('tooth', 36)->first()->surfaces);
    }

    public function test_confirming_a_booking_request(): void
    {
        $a = $this->appointment('2026-10-22 09:00');
        $r = AssistantRequest::create(['appointment_id' => $a->id, 'patient_id' => $a->patient_id, 'type' => 'booking', 'message' => 'm', 'summary' => 's']);

        $this->actingAs($this->secretary)->post("/dashboard/demandes/{$r->id}/confirm")->assertRedirect();

        $this->assertSame('confirmed', $a->fresh()->status);
        $this->assertSame(['done', $this->secretary->id], [$r->fresh()->status, $r->fresh()->handled_by]);
    }

    public function test_accepting_a_move_that_is_no_longer_free_is_refused(): void
    {
        $a = $this->appointment('2026-10-22 09:00', 'confirmed');
        Appointment::create(['patient_id' => Patient::create(['first_name' => 'Omar', 'phone' => '0687654321'])->id,
            'practitioner_id' => $a->practitioner_id, 'service_id' => $a->service_id, 'starts_at' => '2026-10-23 10:00', 'ends_at' => '2026-10-23 10:30', 'status' => 'confirmed']);
        $r = AssistantRequest::create(['appointment_id' => $a->id, 'type' => 'reschedule', 'message' => 'm', 'summary' => 's',
            'payload' => ['to' => '2026-10-23T10:00:00+00:00']]);

        $this->actingAs($this->dentist)->post("/dashboard/demandes/{$r->id}/accept")->assertSessionHas('error');

        $this->assertSame('2026-10-22 09:00', $a->fresh()->starts_at->format('Y-m-d H:i'));
        $this->assertSame('open', $r->fresh()->status);
    }

    public function test_an_unanswered_question_becomes_a_faq_variant(): void
    {
        $faq = Faq::first();
        $r = AssistantRequest::create(['type' => 'unanswered', 'message' => 'wach kayn ascenseur', 'summary' => 's']);

        $this->actingAs($this->secretary)->post("/dashboard/demandes/{$r->id}/faq", ['faq_id' => $faq->id]);

        $this->assertStringEndsWith('wach kayn ascenseur', $faq->fresh()->variants);
    }

    public function test_dragging_in_the_agenda_cannot_overlap(): void
    {
        $a = $this->appointment('2026-10-22 09:00', 'confirmed');
        $this->appointment('2026-10-22 11:00', 'confirmed')->update(['patient_id' => Patient::create(['first_name' => 'O', 'phone' => '0611111111'])->id]);

        $this->actingAs($this->dentist)->patchJson("/dashboard/appointments/{$a->id}/move", ['starts_at' => '2026-10-22T11:15:00'])->assertStatus(409);
        $this->actingAs($this->dentist)->patchJson("/dashboard/appointments/{$a->id}/move", ['starts_at' => '2026-10-22T12:00:00'])->assertOk();
    }

    public function test_changing_hours_changes_the_public_slots(): void
    {
        $this->actingAs($this->dentist)->put('/dashboard/cabinet/hours', ['hours' => [4 => [['opens_at' => '10:00', 'closes_at' => '12:00']]]]);

        $this->getJson('/api/slots?service=consultation&date=2026-10-22')->assertJsonPath('slots.0', '10:00');
        $this->getJson('/api/slots?service=consultation&date=2026-10-23')->assertJsonCount(0, 'slots');
    }

    public function test_tooth_changes_are_kept_in_history_and_a_healthy_tooth_is_not_stored(): void
    {
        $patient = $this->appointment('2026-10-21 10:00')->patient;
        $url = "/dashboard/patients/{$patient->id}/teeth";

        $this->actingAs($this->dentist)->postJson($url, ['tooth' => 26, 'state' => 'crown']);
        $this->actingAs($this->dentist)->postJson($url, ['tooth' => 17, 'state' => 'missing', 'surfaces' => ['O' => 'caries']]);
        $r = $this->actingAs($this->dentist)->postJson($url, ['tooth' => 26, 'state' => 'present'])->assertOk();

        $this->assertSame('Couronne → Saine', $r->json('history.0.what'));
        $this->assertSame([], $patient->teeth()->where('tooth', 17)->first()->surfaces ?? [], 'a missing tooth has no surfaces');
        $this->assertNull($patient->teeth()->where('tooth', 26)->first());
        $this->assertSame(3, \App\Models\ToothEvent::count());
    }

    public function test_reactivating_a_missed_appointment_whose_slot_was_taken_is_refused_cleanly(): void
    {
        $missed = $this->appointment('2026-10-22 09:00', 'no_show');
        $this->appointment('2026-10-22 09:00', 'confirmed')->update(['patient_id' => Patient::create(['first_name' => 'O', 'phone' => '0611111111'])->id]);

        $this->actingAs($this->dentist)->patchJson("/dashboard/appointments/{$missed->id}/status", ['status' => 'arrived'])->assertStatus(409);
        $this->assertSame('no_show', $missed->fresh()->status);
    }
}
