<?php

namespace App\Http\Controllers\Api;

use App\Booking\Availability;
use App\Booking\Booker;
use App\Booking\SlotTaken;
use App\Http\Controllers\Controller;
use App\Models\Patient;
use App\Models\Practitioner;
use App\Models\Service;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** The booking bar on the public site. */
class BookingController extends Controller
{
    public function __construct(private Availability $availability) {}

    public function services(): JsonResponse
    {
        return response()->json(Service::where('bookable_online', true)->orderBy('position')
            ->get(['slug', 'name', 'summary', 'duration_minutes', 'is_emergency']));
    }

    /** The next open days with how many slots each still has. */
    public function days(Request $request): JsonResponse
    {
        $service = $this->service($request);
        $dr = Practitioner::firstOrFail();
        $days = [];
        for ($i = 0, $day = CarbonImmutable::today(); count($days) < 6 && $i < 21; $i++, $day = $day->addDay()) {
            $free = $this->availability->slots($dr, $service, $day)->count();
            if ($free > 0) {
                $days[] = ['date' => $day->toDateString(), 'label' => ucfirst($day->locale('fr')->translatedFormat('D j')), 'free' => $free];
            }
        }

        return response()->json($days);
    }

    public function slots(Request $request): JsonResponse
    {
        $request->validate(['date' => ['required', 'date_format:Y-m-d']]);
        $day = CarbonImmutable::parse($request->string('date'));
        $slots = $this->availability->slots(Practitioner::firstOrFail(), $this->service($request), $day);

        return response()->json(['date' => $day->toDateString(), 'slots' => $slots->map->format('H:i')->values()]);
    }

    public function store(Request $request, Booker $booker): JsonResponse
    {
        $data = $request->validate([
            'service' => ['required', 'exists:services,slug'],
            'starts_at' => ['required', 'date_format:Y-m-d H:i'],
            'first_name' => ['required', 'string', 'max:60'],
            'last_name' => ['nullable', 'string', 'max:60'],
            'phone' => ['required', 'string', 'max:20'],
        ]);
        $phone = Patient::normalizePhone($data['phone']);
        if (! preg_match('/^0[5-7]\d{8}$/', $phone)) {
            return response()->json(['message' => 'Numéro de téléphone marocain invalide.', 'errors' => ['phone' => ['Exemple : 06 12 34 56 78']]], 422);
        }

        $patient = Patient::firstOrCreate(['phone' => $phone], ['first_name' => $data['first_name'], 'last_name' => $data['last_name'] ?? null]);
        $service = Service::where('slug', $data['service'])->firstOrFail();

        try {
            $appointment = $booker->book($patient, Practitioner::firstOrFail(), $service, CarbonImmutable::parse($data['starts_at']), 'web');
        } catch (SlotTaken $e) {
            return response()->json(['message' => $e->getMessage()], 409);
        }

        return response()->json([
            'id' => $appointment->id,
            'service' => $service->name,
            'starts_at' => $appointment->starts_at->toIso8601String(),
            'label' => ucfirst($appointment->starts_at->locale('fr')->translatedFormat('l j F \à H:i')),
            'duration' => $service->duration_minutes,
            'address' => config('cabinet.address'),
            'whatsapp' => 'https://wa.me/'.config('cabinet.whatsapp').'?text='.rawurlencode(
                "Bonjour, j'ai réservé {$service->name} le {$appointment->starts_at->locale('fr')->translatedFormat('l j F à H:i')} au nom de {$patient->full_name}."
            ),
        ], 201);
    }

    private function service(Request $request): Service
    {
        return Service::where('slug', $request->string('service', 'consultation'))->where('bookable_online', true)->firstOrFail();
    }
}
