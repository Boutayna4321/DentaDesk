<?php

namespace Tests\Feature\Booking;

use App\Booking\Availability;
use App\Booking\Booker;
use App\Booking\SlotTaken;
use App\Models\Appointment;
use App\Models\Patient;
use App\Models\Practitioner;
use App\Models\Service;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AvailabilityTest extends TestCase
{
    use RefreshDatabase;

    private Practitioner $dr;

    private Service $consult;

    private Patient $patient;

    // Wednesday 21 Oct 2026, 08:00 — the day starts with nothing booked.
    private CarbonImmutable $now;

    protected function setUp(): void
    {
        parent::setUp();

        $this->now = CarbonImmutable::parse('2026-10-21 08:00');
        CarbonImmutable::setTestNow($this->now);

        $this->dr = Practitioner::create(['name' => 'Dr Nadia Alaoui']);
        // Wednesday: 09:00–13:00 then 14:30–19:00
        $this->dr->workingHours()->createMany([
            ['weekday' => 3, 'opens_at' => '09:00', 'closes_at' => '13:00'],
            ['weekday' => 3, 'opens_at' => '14:30', 'closes_at' => '19:00'],
        ]);
        $this->consult = Service::create(['slug' => 'consultation', 'name' => 'Consultation', 'summary' => '-', 'duration_minutes' => 30]);
        $this->patient = Patient::create(['first_name' => 'Salma', 'phone' => '0612345678']);
    }

    private function times($slots): array
    {
        return $slots->map->format('H:i')->all();
    }

    public function test_slots_follow_opening_hours_and_skip_lunch(): void
    {
        $slots = $this->times(app(Availability::class)->slots($this->dr, $this->consult, $this->now));

        $this->assertSame('09:00', $slots[0]);
        $this->assertContains('12:30', $slots);
        $this->assertNotContains('12:45', $slots, 'a 30 min service cannot start at 12:45 when the morning ends at 13:00');
        $this->assertNotContains('13:30', $slots);
        $this->assertContains('14:30', $slots);
        $this->assertSame('18:30', end($slots));
    }

    public function test_closed_day_has_no_slots(): void
    {
        $sunday = CarbonImmutable::parse('2026-10-25');

        $this->assertCount(0, app(Availability::class)->slots($this->dr, $this->consult, $sunday));
    }

    public function test_booked_appointment_blocks_overlapping_starts(): void
    {
        app(Booker::class)->book($this->patient, $this->dr, $this->consult, $this->now->setTime(10, 0), 'web');

        $slots = $this->times(app(Availability::class)->slots($this->dr, $this->consult, $this->now));

        $this->assertNotContains('09:45', $slots);
        $this->assertNotContains('10:00', $slots);
        $this->assertNotContains('10:15', $slots);
        $this->assertContains('09:30', $slots);
        $this->assertContains('10:30', $slots);
    }

    public function test_cancelling_frees_the_slot_and_it_can_be_booked_again(): void
    {
        $booker = app(Booker::class);
        $first = $booker->book($this->patient, $this->dr, $this->consult, $this->now->setTime(10, 0), 'web');
        $first->update(['status' => 'cancelled']);

        $other = Patient::create(['first_name' => 'Omar', 'phone' => '0687654321']);
        $second = $booker->book($other, $this->dr, $this->consult, $this->now->setTime(10, 0), 'assistant');

        $this->assertSame('pending', $second->status);
        $this->assertSame(2, Appointment::count());
    }

    public function test_double_booking_is_refused(): void
    {
        $booker = app(Booker::class);
        $booker->book($this->patient, $this->dr, $this->consult, $this->now->setTime(10, 0), 'web');

        $this->expectException(SlotTaken::class);
        $booker->book(Patient::create(['first_name' => 'Omar', 'phone' => '0687654321']), $this->dr, $this->consult, $this->now->setTime(10, 15), 'web');
    }

    public function test_unique_index_is_the_last_line_of_defence(): void
    {
        $this->expectException(\Illuminate\Database\UniqueConstraintViolationException::class);

        foreach (['0611111111', '0622222222'] as $phone) {
            Appointment::create([
                'patient_id' => Patient::create(['first_name' => 'X', 'phone' => $phone])->id,
                'practitioner_id' => $this->dr->id,
                'service_id' => $this->consult->id,
                'starts_at' => '2026-10-21 11:00',
                'ends_at' => '2026-10-21 11:30',
            ]);
        }
    }

    public function test_time_off_removes_slots(): void
    {
        $this->dr->timeOffs()->create(['starts_at' => '2026-10-21 14:30', 'ends_at' => '2026-10-21 19:00', 'reason' => 'Congrès']);

        $slots = $this->times(app(Availability::class)->slots($this->dr, $this->consult, $this->now));

        $this->assertSame('12:30', end($slots));
    }

    public function test_patients_cannot_book_less_than_an_hour_ahead(): void
    {
        $at = CarbonImmutable::parse('2026-10-21 09:40');
        CarbonImmutable::setTestNow($at);

        $slots = $this->times(app(Availability::class)->slots($this->dr, $this->consult, $at));
        $this->assertSame('10:45', $slots[0]);

        $this->expectException(SlotTaken::class);
        app(Booker::class)->book($this->patient, $this->dr, $this->consult, $at->setTime(10, 0), 'web');
    }

    public function test_staff_can_book_outside_hours_but_never_on_top_of_someone(): void
    {
        $booker = app(Booker::class);
        $late = $booker->book($this->patient, $this->dr, $this->consult, $this->now->setTime(19, 30), 'phone', 'confirmed', withinHours: false);
        $this->assertSame('19:30', $late->starts_at->format('H:i'));

        $this->expectException(SlotTaken::class);
        $booker->book(Patient::create(['first_name' => 'Omar', 'phone' => '0687654321']), $this->dr, $this->consult, $this->now->setTime(19, 45), 'phone', withinHours: false);
    }

    public function test_upcoming_respects_a_part_of_the_day(): void
    {
        $afternoon = app(Availability::class)->upcoming($this->dr, $this->consult, $this->now, limit: 2, window: ['14:00', '18:00']);

        $this->assertSame(['14:30', '14:45'], $this->times($afternoon));
    }

    public function test_moving_an_appointment_ignores_its_own_slot(): void
    {
        $booker = app(Booker::class);
        $a = $booker->book($this->patient, $this->dr, $this->consult, $this->now->setTime(10, 0), 'web');

        $booker->move($a, $this->now->setTime(10, 15));

        $this->assertSame('10:15', $a->fresh()->starts_at->format('H:i'));
    }
}
