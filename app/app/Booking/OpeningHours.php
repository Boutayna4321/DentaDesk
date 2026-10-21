<?php

namespace App\Booking;

use App\Models\Practitioner;

class OpeningHours
{
    private const DAYS = [1 => 'lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi', 'samedi', 'dimanche'];

    /**
     * Rows ready to print: consecutive days with the same hours are merged.
     *
     * @return list<array{days:string,hours:string}>
     */
    public static function rows(Practitioner $practitioner): array
    {
        $byDay = $practitioner->workingHours()->orderBy('weekday')->orderBy('opens_at')->get()
            ->groupBy('weekday')
            ->map(fn ($spans) => $spans->map(fn ($s) => substr($s->opens_at, 0, 5).'–'.substr($s->closes_at, 0, 5))->implode(', '));

        $runs = [];
        foreach ($byDay as $day => $hours) {
            $last = array_key_last($runs);
            if ($last !== null && $runs[$last]['hours'] === $hours && $runs[$last]['to'] === $day - 1) {
                $runs[$last]['to'] = $day;
            } else {
                $runs[] = ['from' => $day, 'to' => $day, 'hours' => $hours];
            }
        }

        return array_map(fn ($r) => [
            'days' => $r['from'] === $r['to'] ? self::DAYS[$r['from']] : self::DAYS[$r['from']].'–'.self::DAYS[$r['to']],
            'hours' => $r['hours'],
        ], $runs);
    }

    /** "lundi–vendredi 09:00–13:00, 14:30–19:00 · samedi 09:00–13:00" */
    public static function summary(Practitioner $practitioner): string
    {
        return collect(self::rows($practitioner))->map(fn ($r) => $r['days'].' '.$r['hours'])->implode(' · ');
    }
}
