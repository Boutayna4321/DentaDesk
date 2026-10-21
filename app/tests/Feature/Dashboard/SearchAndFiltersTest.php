<?php

namespace Tests\Feature\Dashboard;

use App\Models\Appointment;
use App\Models\Patient;
use App\Models\Practitioner;
use App\Models\Service;
use App\Models\User;
use Database\Seeders\CabinetSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SearchAndFiltersTest extends TestCase
{
    use RefreshDatabase;

    private User $dentist;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-10-21 10:00');
        $this->seed(CabinetSeeder::class);
        $this->dentist = User::create(['name' => 'D', 'email' => 'd@test', 'password' => 'x', 'role' => 'dentist']);
    }

    private function visit(Patient $p, string $at, string $status): void
    {
        Appointment::create(['patient_id' => $p->id, 'practitioner_id' => Practitioner::value('id'), 'service_id' => Service::value('id'),
            'starts_at' => $at, 'ends_at' => \Carbon\CarbonImmutable::parse($at)->addMinutes(30), 'status' => $status]);
    }

    public function test_search_finds_patients_by_full_name_and_by_phone(): void
    {
        Patient::create(['first_name' => 'Salma', 'last_name' => 'Bennani', 'phone' => '0612345678']);
        Patient::create(['first_name' => 'Omar', 'last_name' => 'El Fassi', 'phone' => '0687654321']);

        $this->actingAs($this->dentist)->getJson('/dashboard/recherche?q=salma benn')->assertJsonPath('patients.0.name', 'Salma Bennani')->assertJsonCount(1, 'patients');
        $this->actingAs($this->dentist)->getJson('/dashboard/recherche?q=06 87 65')->assertJsonPath('patients.0.name', 'Omar El Fassi');
        $this->actingAs($this->dentist)->getJson('/dashboard/recherche?q=s')->assertJsonCount(0, 'patients');
    }

    public function test_the_recall_list_shows_patients_not_seen_for_six_months_with_nothing_booked(): void
    {
        $due = Patient::create(['first_name' => 'Due', 'phone' => '0600000001']);
        $recent = Patient::create(['first_name' => 'Recent', 'phone' => '0600000002']);
        $booked = Patient::create(['first_name' => 'Booked', 'phone' => '0600000003']);
        $this->visit($due, '2026-02-10 10:00', 'done');
        $this->visit($recent, '2026-09-10 10:00', 'done');
        $this->visit($booked, '2026-01-10 10:00', 'done');
        $this->visit($booked, '2026-10-28 10:00', 'confirmed');

        $this->actingAs($this->dentist)->get('/dashboard/patients?only[]=a_rappeler')
            ->assertSee('Due')->assertDontSee('Recent')->assertDontSee('Booked');
    }

    public function test_coverage_filter_includes_patients_without_coverage_when_asked(): void
    {
        Patient::create(['first_name' => 'Cnss', 'phone' => '0600000001', 'coverage' => 'cnss']);
        Patient::create(['first_name' => 'Nothing', 'phone' => '0600000002']);

        $this->actingAs($this->dentist)->get('/dashboard/patients?couverture[]=aucune')->assertSee('Nothing')->assertDontSee('Cnss');
    }
}
