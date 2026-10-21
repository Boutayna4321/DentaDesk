<?php

namespace App\Booking;

use App\Models\Appointment;
use App\Models\Practitioner;
use App\Models\Service;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Free start times for a service on a given day.
 *
 * free = opening spans − time off − blocking appointments, walked on a 15-minute grid,
 * keeping only starts where the whole service duration fits.
 */
class Availability
{
    public const STEP_MINUTES = 15;

    /** Patients cannot book a slot that starts in less than this. */
    public const LEAD_MINUTES = 60;

    /**
     * @return Collection<int, CarbonImmutable>
     */
    public function slots(Practitioner $practitioner, Service $service, CarbonImmutable $day, ?CarbonImmutable $now = null): Collection
    {
        $now ??= CarbonImmutable::now();
        $earliest = $now->addMinutes(self::LEAD_MINUTES);
        $day = $day->startOfDay();
        $busy = $this->busyIntervals($practitioner, $day, $day->endOfDay());

        $slots = collect();
        foreach ($this->openingSpans($practitioner, $day) as [$open, $close]) {
            for ($start = $open; $start->addMinutes($service->duration_minutes) <= $close; $start = $start->addMinutes(self::STEP_MINUTES)) {
                $end = $start->addMinutes($service->duration_minutes);
                if ($start < $earliest || $this->overlapsAny($start, $end, $busy)) {
                    continue;
                }
                $slots->push($start);
            }
        }

        return $slots;
    }

    /**
     * Next free slots across the coming days, optionally limited to a part of the day.
     *
     * @param  array{0:string,1:string}|null  $window  e.g. ['09:00', '12:00']
     * @return Collection<int, CarbonImmutable>
     */
    public function upcoming(Practitioner $practitioner, Service $service, CarbonImmutable $from, int $limit = 3, int $days = 14, ?array $window = null, ?CarbonImmutable $now = null): Collection
    {
        $found = collect();
        for ($i = 0; $i < $days && $found->count() < $limit; $i++) {
            $day = $from->startOfDay()->addDays($i);
            $slots = $this->slots($practitioner, $service, $day, $now);
            if ($window) {
                $slots = $slots->filter(fn (CarbonImmutable $s) => $s->format('H:i') >= $window[0] && $s->format('H:i') < $window[1]);
            }
            $found = $found->concat($slots);
        }

        return $found->take($limit)->values();
    }

    public function isFree(Practitioner $practitioner, Service $service, CarbonImmutable $start, bool $withinHours = true, ?int $ignoreAppointmentId = null): bool
    {
        $end = $start->addMinutes($service->duration_minutes);

        if ($withinHours) {
            $fits = collect($this->openingSpans($practitioner, $start->startOfDay()))
                ->contains(fn ($span) => $start >= $span[0] && $end <= $span[1]);
            if (! $fits) {
                return false;
            }
        }

        return ! $this->overlapsAny($start, $end, $this->busyIntervals($practitioner, $start->startOfDay(), $start->endOfDay(), $ignoreAppointmentId));
    }

    /** @return list<array{0:CarbonImmutable,1:CarbonImmutable}> */
    private function openingSpans(Practitioner $practitioner, CarbonImmutable $day): array
    {
        return $practitioner->workingHours()
            ->where('weekday', $day->isoWeekday())
            ->orderBy('opens_at')
            ->get()
            ->map(fn ($h) => [$day->setTimeFromTimeString($h->opens_at), $day->setTimeFromTimeString($h->closes_at)])
            ->all();
    }

    /** @return list<array{0:CarbonImmutable,1:CarbonImmutable}> */
    private function busyIntervals(Practitioner $practitioner, CarbonImmutable $from, CarbonImmutable $to, ?int $ignoreAppointmentId = null): array
    {
        $appointments = Appointment::query()
            ->where('practitioner_id', $practitioner->id)
            ->whereIn('status', Appointment::BLOCKING)
            ->where('starts_at', '<', $to)
            ->where('ends_at', '>', $from)
            ->when($ignoreAppointmentId, fn ($q) => $q->whereKeyNot($ignoreAppointmentId))
            ->get(['starts_at', 'ends_at'])
            ->map(fn ($a) => [$a->starts_at->toImmutable(), $a->ends_at->toImmutable()]);

        $timeOffs = $practitioner->timeOffs()
            ->where('starts_at', '<', $to)
            ->where('ends_at', '>', $from)
            ->get(['starts_at', 'ends_at'])
            ->map(fn ($t) => [$t->starts_at->toImmutable(), $t->ends_at->toImmutable()]);

        return $appointments->concat($timeOffs)->all();
    }

    private function overlapsAny(CarbonImmutable $start, CarbonImmutable $end, array $busy): bool
    {
        foreach ($busy as [$bStart, $bEnd]) {
            if ($start < $bEnd && $end > $bStart) {
                return true;
            }
        }

        return false;
    }
}
