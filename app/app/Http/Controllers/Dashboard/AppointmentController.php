<?php

namespace App\Http\Controllers\Dashboard;

use App\Booking\Booker;
use App\Booking\SlotTaken;
use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\Patient;
use App\Models\Practitioner;
use App\Models\Service;
use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AppointmentController extends Controller
{
    /** Booked by the front desk: may sit outside opening hours, never on top of someone. */
    public function store(Request $request, Booker $booker): JsonResponse|RedirectResponse
    {
        $data = $request->validate([
            'patient_id' => ['nullable', 'exists:patients,id'],
            'first_name' => ['required_without:patient_id', 'nullable', 'string', 'max:60'],
            'last_name' => ['nullable', 'string', 'max:60'],
            'phone' => ['required_without:patient_id', 'nullable', 'string', 'max:20'],
            'service' => ['required', 'exists:services,slug'],
            'starts_at' => ['required', 'date'],
            'reason' => ['nullable', 'string', 'max:250'],
        ]);

        $patient = isset($data['patient_id'])
            ? Patient::findOrFail($data['patient_id'])
            : Patient::firstOrCreate(['phone' => Patient::normalizePhone($data['phone'])], ['first_name' => $data['first_name'], 'last_name' => $data['last_name'] ?? null]);

        try {
            $appointment = $booker->book($patient, Practitioner::firstOrFail(), Service::where('slug', $data['service'])->firstOrFail(),
                CarbonImmutable::parse($data['starts_at']), 'phone', 'confirmed', $data['reason'] ?? null, withinHours: false);
        } catch (SlotTaken $e) {
            return $request->expectsJson() ? response()->json(['message' => $e->getMessage()], 409) : back()->with('error', $e->getMessage());
        }

        return $request->expectsJson()
            ? response()->json(['id' => $appointment->id], 201)
            : back()->with('ok', 'Rendez-vous ajouté pour '.$patient->full_name.'.');
    }

    public function status(Request $request, Appointment $appointment): JsonResponse|RedirectResponse
    {
        $data = $request->validate(['status' => ['required', Rule::in(array_keys(Appointment::STATUSES))]]);
        try {
            $appointment->update($data);
        } catch (UniqueConstraintViolationException) {
            // A cancelled or missed appointment freed its slot, and someone else has it now.
            $message = 'Ce créneau a été repris par un autre rendez-vous : déplacez celui-ci avant de le réactiver.';

            return $request->expectsJson() ? response()->json(['message' => $message], 409) : back()->with('error', $message);
        }

        return $request->expectsJson() ? response()->json(['status' => $appointment->status]) : back();
    }

    public function move(Request $request, Appointment $appointment, Booker $booker): JsonResponse
    {
        $data = $request->validate(['starts_at' => ['required', 'date']]);
        try {
            $booker->move($appointment, CarbonImmutable::parse($data['starts_at'])->setTimezone(config('app.timezone')), withinHours: false);
        } catch (SlotTaken $e) {
            return response()->json(['message' => $e->getMessage()], 409);
        }

        return response()->json(['starts_at' => $appointment->starts_at->toIso8601String()]);
    }

    public function note(Request $request, Appointment $appointment): RedirectResponse
    {
        $appointment->update($request->validate(['visit_note' => ['nullable', 'string', 'max:2000']]));

        return back()->with('ok', 'Note enregistrée.');
    }
}
