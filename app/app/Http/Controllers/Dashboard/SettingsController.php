<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Practitioner;
use App\Models\Service;
use App\Models\TimeOff;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class SettingsController extends Controller
{
    public function index(): View
    {
        $dr = Practitioner::firstOrFail();

        return view('dashboard.settings', [
            'hours' => $dr->workingHours()->orderBy('opens_at')->get()->groupBy('weekday'),
            'timeOffs' => $dr->timeOffs()->where('ends_at', '>', now())->orderBy('starts_at')->get(),
            'services' => Service::orderBy('position')->get(),
        ]);
    }

    /** hours[weekday][span] = {opens_at, closes_at}; an empty span is a closed half-day. */
    public function hours(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'hours' => ['array'],
            'hours.*.*.opens_at' => ['nullable', 'date_format:H:i'],
            'hours.*.*.closes_at' => ['nullable', 'date_format:H:i', 'after:hours.*.*.opens_at'],
        ]);
        $dr = Practitioner::firstOrFail();

        DB::transaction(function () use ($dr, $data) {
            $dr->workingHours()->delete();
            foreach ($data['hours'] ?? [] as $weekday => $spans) {
                foreach ($spans as $span) {
                    if (! empty($span['opens_at']) && ! empty($span['closes_at'])) {
                        $dr->workingHours()->create(['weekday' => $weekday, 'opens_at' => $span['opens_at'], 'closes_at' => $span['closes_at']]);
                    }
                }
            }
        });

        return back()->with('ok', 'Horaires enregistrés. Le site et l’assistant les utilisent tout de suite.');
    }

    public function addTimeOff(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', 'after:starts_at'],
            'reason' => ['nullable', 'string', 'max:100'],
        ]);
        Practitioner::firstOrFail()->timeOffs()->create($data);

        return back()->with('ok', 'Fermeture ajoutée.');
    }

    public function removeTimeOff(TimeOff $timeOff): RedirectResponse
    {
        $timeOff->delete();

        return back()->with('ok', 'Fermeture supprimée.');
    }

    public function services(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'services' => ['array'],
            'services.*.duration_minutes' => ['required', 'integer', 'min:15', 'max:240', 'multiple_of:15'],
            'services.*.bookable_online' => ['nullable', 'boolean'],
        ]);
        foreach ($data['services'] ?? [] as $id => $values) {
            Service::whereKey($id)->update([
                'duration_minutes' => $values['duration_minutes'],
                'bookable_online' => (bool) ($values['bookable_online'] ?? false),
            ]);
        }

        return back()->with('ok', 'Soins mis à jour.');
    }
}
