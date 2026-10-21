<?php

namespace Tests\Feature\Dashboard;

use App\Models\Appointment;
use App\Models\Patient;
use App\Models\Practitioner;
use App\Models\Service;
use App\Models\User;
use App\Reports\PracticeStats;
use Database\Seeders\CabinetSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StatsTest extends TestCase
{
    use RefreshDatabase;

    public function test_occupancy_and_no_show_rate(): void
    {
        $this->travelTo('2026-10-21 20:00'); // Wednesday evening
        $this->seed(CabinetSeeder::class);
        $dr = Practitioner::first();
        $service = Service::where('slug', 'consultation')->first();
        $p = Patient::create(['first_name' => 'A', 'phone' => '0600000001']);
        // Two 30-min visits on Wednesday 21, one of them a no-show.
        foreach (['10:00' => 'done', '11:00' => 'no_show'] as $t => $status) {
            $start = \Carbon\CarbonImmutable::parse("2026-10-21 $t");
            Appointment::create(['patient_id' => $p->id, 'practitioner_id' => $dr->id, 'service_id' => $service->id,
                'starts_at' => $start, 'ends_at' => $start->addMinutes(30), 'status' => $status]);
        }

        $s = new PracticeStats('30j');

        $this->assertSame(0.5, $s->patients()['noShowRate']);
        // 30 days back from Wednesday 21 Oct holds five Wednesdays of 510 open minutes each.
        $this->assertEqualsWithDelta(60 / (5 * 510), $s->occupancy()['byWeekday'][3], 0.0001);
    }

    public function test_only_the_dentist_sees_the_numbers(): void
    {
        $this->seed(CabinetSeeder::class);
        $secretary = User::create(['name' => 'K', 'email' => 's@test', 'password' => 'x', 'role' => 'secretary']);
        $dentist = User::create(['name' => 'D', 'email' => 'd@test', 'password' => 'x', 'role' => 'dentist']);

        $this->actingAs($secretary)->get('/dashboard/statistiques')->assertForbidden();
        $this->actingAs($dentist)->get('/dashboard/statistiques?p=12m')->assertOk()->assertSee('Encaissé par semaine');
    }
}
