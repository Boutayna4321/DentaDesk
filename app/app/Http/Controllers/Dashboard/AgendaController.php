<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\Practitioner;
use App\Models\Service;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AgendaController extends Controller
{
    public function index(): View
    {
        $dr = Practitioner::firstOrFail();

        return view('dashboard.agenda', [
            'services' => Service::orderBy('position')->get(),
            'businessHours' => $dr->workingHours->map(fn ($h) => [
                'daysOfWeek' => [$h->weekday % 7], 'startTime' => substr($h->opens_at, 0, 5), 'endTime' => substr($h->closes_at, 0, 5),
            ])->values(),
        ]);
    }

    public function events(Request $request): JsonResponse
    {
        $from = CarbonImmutable::parse($request->string('start'))->setTimezone(config('app.timezone'));
        $to = CarbonImmutable::parse($request->string('end'))->setTimezone(config('app.timezone'));

        $appointments = Appointment::with(['patient', 'service'])
            ->where('status', '!=', 'cancelled')
            ->where('starts_at', '<', $to)->where('ends_at', '>', $from)
            ->get()
            ->map(fn (Appointment $a) => [
                'id' => $a->id,
                'title' => $a->patient->full_name,
                'start' => $a->starts_at->format('Y-m-d\TH:i:s'),
                'end' => $a->ends_at->format('Y-m-d\TH:i:s'),
                'classNames' => ['st-'.$a->status, $a->service->is_emergency ? 'is-urgent' : ''],
                'extendedProps' => [
                    'service' => $a->service->name, 'status' => $a->status, 'statusLabel' => $a->status_label,
                    'source' => $a->source_label, 'phone' => $a->patient->phone, 'patientUrl' => route('dash.patients.show', $a->patient),
                    'reason' => $a->reason,
                ],
            ]);

        $offs = Practitioner::firstOrFail()->timeOffs()->where('starts_at', '<', $to)->where('ends_at', '>', $from)->get()
            ->map(fn ($t) => [
                'start' => $t->starts_at->format('Y-m-d\TH:i:s'), 'end' => $t->ends_at->format('Y-m-d\TH:i:s'),
                'display' => 'background', 'classNames' => ['time-off'], 'title' => $t->reason ?? 'Fermé',
            ]);

        return response()->json($appointments->concat($offs)->values());
    }
}
