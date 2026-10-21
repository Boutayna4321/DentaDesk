<?php

namespace App\Http\Controllers\Dashboard;

use App\Booking\Availability;
use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\AssistantRequest;
use App\Models\Practitioner;
use App\Models\Service;
use Carbon\CarbonImmutable;
use Illuminate\View\View;

class TodayController extends Controller
{
    public function __invoke(Availability $availability): View
    {
        $now = CarbonImmutable::now();
        $today = $now->startOfDay();
        $dr = Practitioner::firstOrFail();

        $appointments = Appointment::with(['patient', 'service'])
            ->whereBetween('starts_at', [$today, $today->endOfDay()])
            ->where('status', '!=', 'cancelled')
            ->orderBy('starts_at')->get();

        $free = $availability->slots($dr, Service::where('slug', 'consultation')->firstOrFail(), $today, $now);
        $byAssistant = fn ($from, $to) => Appointment::where('source', 'assistant')->whereBetween('created_at', [$from, $to])->count();

        $current = $appointments->firstWhere('status', 'in_care')
            ?? $appointments->firstWhere('status', 'arrived')
            ?? $appointments->first(fn ($a) => $a->starts_at->isFuture() && $a->status === 'confirmed');

        $returning = Appointment::whereIn('patient_id', $appointments->pluck('patient_id'))
            ->where('status', 'done')->where('starts_at', '<', $today)->distinct()->pluck('patient_id');

        return view('dashboard.today', [
            'appointments' => $appointments,
            'returning' => $returning->flip(),
            'kpi' => [
                'today' => $appointments->count(),
                'confirmed' => $appointments->whereNotIn('status', ['pending'])->count(),
                'free' => $free,
                'assistant' => $byAssistant($now->subDays(7), $now),
                'assistantBefore' => $byAssistant($now->subDays(14), $now->subDays(7)),
                'noShows' => Appointment::where('status', 'no_show')->where('starts_at', '>=', $today->startOfMonth())->count(),
            ],
            'requests' => AssistantRequest::with('patient')->where('status', 'open')->latest()->take(3)->get(),
            'openRequests' => AssistantRequest::where('status', 'open')->count(),
            'current' => $current?->load(['patient.teeth', 'patient.appointments' => fn ($q) => $q->with('service')->where('status', 'done')->latest('starts_at')]),
            'now' => $now,
        ]);
    }
}
