<?php

namespace App\Booking;

use App\Models\Appointment;
use App\Models\Patient;
use App\Models\Practitioner;
use App\Models\Service;
use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * The only place that writes appointments.
 *
 * Two patients clicking the same slot at the same second: the practitioner row is
 * locked, so the second request waits, re-checks, and gets SlotTaken. The unique
 * index on (practitioner_id, slot_lock) catches anything that slips past.
 */
class Booker
{
    public function __construct(private Availability $availability) {}

    public function book(
        Patient $patient,
        Practitioner $practitioner,
        Service $service,
        CarbonImmutable $start,
        string $source,
        string $status = 'pending',
        ?string $reason = null,
        bool $withinHours = true,
    ): Appointment {
        try {
            return DB::transaction(function () use ($patient, $practitioner, $service, $start, $source, $status, $reason, $withinHours) {
                Practitioner::whereKey($practitioner->id)->lockForUpdate()->first();

                $tooSoon = $withinHours && $start < CarbonImmutable::now()->addMinutes(Availability::LEAD_MINUTES);
                if ($tooSoon || ! $this->availability->isFree($practitioner, $service, $start, $withinHours)) {
                    throw new SlotTaken;
                }

                return Appointment::create([
                    'patient_id' => $patient->id,
                    'practitioner_id' => $practitioner->id,
                    'service_id' => $service->id,
                    'starts_at' => $start,
                    'ends_at' => $start->addMinutes($service->duration_minutes),
                    'status' => $status,
                    'source' => $source,
                    'reason' => $reason,
                ]);
            });
        } catch (UniqueConstraintViolationException) {
            throw new SlotTaken;
        }
    }

    public function move(Appointment $appointment, CarbonImmutable $start, bool $withinHours = true): Appointment
    {
        try {
            return DB::transaction(function () use ($appointment, $start, $withinHours) {
                Practitioner::whereKey($appointment->practitioner_id)->lockForUpdate()->first();

                if (! $this->availability->isFree($appointment->practitioner, $appointment->service, $start, $withinHours, $appointment->id)) {
                    throw new SlotTaken;
                }

                $appointment->update([
                    'starts_at' => $start,
                    'ends_at' => $start->addMinutes($appointment->service->duration_minutes),
                ]);

                return $appointment;
            });
        } catch (UniqueConstraintViolationException) {
            throw new SlotTaken;
        }
    }
}
