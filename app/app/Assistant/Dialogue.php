<?php

namespace App\Assistant;

use App\Booking\Availability;
use App\Booking\Booker;
use App\Booking\OpeningHours;
use App\Booking\SlotTaken;
use App\Models\Appointment;
use App\Models\AssistantRequest;
use App\Models\Conversation;
use App\Models\Faq;
use App\Models\Patient;
use App\Models\Practitioner;
use App\Models\Service;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

/**
 * The conversation, as a small state machine.
 *
 * The NLU service says what the patient means; this class decides what to do with it.
 * Slots, bookings and answers always come from the database, never from the model.
 *
 * state: lang, step (slot|day|name|phone|confirm_cancel|move_slot|clarify), service,
 *        date, part, offered[], slot, name, purpose (book|handover|cancel|move),
 *        reason (the message that started the flow), appointment_id, pending
 */
class Dialogue
{
    private array $state;

    private Conversation $conversation;

    private string $lang;

    public function __construct(
        private NluClient $nlu,
        private Availability $availability,
        private Booker $booker,
    ) {}

    public function handle(Conversation $conversation, string $text, ?string $value = null): Reply
    {
        $this->conversation = $conversation;
        $this->state = $conversation->state ?? [];
        // A button press repeats our own label; it says nothing about the patient's language.
        $this->lang = $value === null ? Phrases::detect($text, $this->state['lang'] ?? 'fr') : ($this->state['lang'] ?? 'fr');
        $this->state['lang'] = $this->lang;

        $nlu = null;
        if ($value === null) {
            $nlu = $this->nlu->parse($text, CarbonImmutable::today()->toDateString());
        }
        $stepBefore = $this->state['step'] ?? null;
        $message = $conversation->messages()->create([
            'role' => 'patient',
            'body' => $text,
            'intent' => $nlu['intent'] ?? null,
            'confidence' => $nlu['confidence'] ?? null,
            'entities' => $nlu['entities'] ?? null,
        ]);

        if ($value === null && $nlu === null) {
            $reply = new Reply(Phrases::say('unavailable', $this->lang, ['phone' => config('cabinet.phone')]));
        } else {
            $reply = $this->respond($text, $value, $nlu ?? ['intent' => null, 'tier' => 'act', 'entities' => [], 'alternatives' => []]);
        }

        // An answer to "your name?" or "your number?" is not a request: keep it out of the intent stats.
        if (in_array($stepBefore, ['name', 'phone'], true) || $value !== null) {
            $message->update(['intent' => null, 'confidence' => null]);
        }
        $conversation->messages()->create(['role' => 'assistant', 'body' => $reply->text]);
        $conversation->update(['state' => $this->state, 'last_message_at' => now()]);

        return $reply;
    }

    private function respond(string $text, ?string $value, array $nlu): Reply
    {
        $e = $nlu['entities'] ?? [];
        $intent = $nlu['intent'];
        $tier = $nlu['tier'] ?? 'act';

        // 1. Buttons carry their meaning in the value; no guessing needed.
        if ($value !== null) {
            if (str_starts_with($value, 'intent:')) {
                $pending = $this->state['pending'] ?? [];
                $this->state['pending'] = null;
                $this->state['step'] = null;

                return $this->route(substr($value, 7), 'act', $pending['entities'] ?? [], $pending['text'] ?? $text);
            }
            if (str_starts_with($value, 'slot:')) {
                $at = CarbonImmutable::parse(substr($value, 5));

                return ($this->state['step'] ?? null) === 'move_slot' ? $this->requestMove($at) : $this->choose($at);
            }
            if ($value === 'other_day') {
                $this->state['step'] = 'day';

                return new Reply(Phrases::say('other_day', $this->lang));
            }
            if (in_array($value, ['yes', 'no'], true) && ($this->state['step'] ?? null) === 'confirm_cancel') {
                return $this->confirmCancel($value === 'yes');
            }
        }

        // 2. An answer to the question we just asked.
        switch ($this->state['step'] ?? null) {
            case 'slot':
            case 'move_slot':
                if ($picked = $this->matchOffered($e)) {
                    return $this->state['step'] === 'move_slot' ? $this->requestMove($picked) : $this->choose($picked);
                }
                if ($intent === 'nier' && $tier === 'act') {
                    $this->state['step'] = 'day';

                    return new Reply(Phrases::say('other_day', $this->lang));
                }
                if (! empty($e['date']) || ! empty($e['part_of_day'])) {
                    return $this->offer($e['date'] ?? null, $e['part_of_day'] ?? null, $e['time'] ?? null);
                }
                if ($tier === 'act' && ! in_array($intent, ['affirmer', 'nier', 'prendre_rdv'], true)) {
                    break; // the patient changed the subject
                }

                return new Reply(Phrases::say('pick_slot', $this->lang), $this->slotChips($this->state['offered'] ?? []));

            case 'day':
                if (! empty($e['date']) || ! empty($e['part_of_day'])) {
                    return $this->offer($e['date'] ?? null, $e['part_of_day'] ?? null, $e['time'] ?? null);
                }
                break;

            case 'name':
                if ($name = $this->readName($text, $e)) {
                    $this->state['name'] = $name;
                    if (! empty($e['phone'])) {
                        return $this->withPhone($e['phone']);
                    }
                    $this->state['step'] = 'phone';

                    return new Reply(Phrases::say('ask_phone', $this->lang, ['name' => strtok($name, ' ')]));
                }

                return new Reply(Phrases::say('ask_name', $this->lang, ['slot' => $this->chosenLabel()]));

            case 'phone':
                $phone = $e['phone'] ?? $this->readPhone($text);
                if (! $phone) {
                    return new Reply(Phrases::say('bad_phone', $this->lang));
                }

                return $this->withPhone($phone);

            case 'confirm_cancel':
                if (in_array($intent, ['affirmer', 'nier'], true)) {
                    return $this->confirmCancel($intent === 'affirmer');
                }
                break;
        }

        // 3. A new request.
        return $this->route($intent, $tier, $e, $text, $nlu['alternatives'] ?? []);
    }

    private function route(?string $intent, string $tier, array $e, string $text, array $alternatives = []): Reply
    {
        if ($tier === 'handover') {
            return $this->handover($text);
        }
        if ($tier === 'clarify') {
            $choices = collect($alternatives)->pluck('intent')
                ->filter(fn ($i) => in_array($i, ['prendre_rdv', 'urgence', 'deplacer', 'annuler', 'horaires', 'adresse', 'question_faq'], true))
                ->take(3)->values();
            if ($choices->count() >= 2) {
                $this->state['step'] = 'clarify';
                $this->state['pending'] = ['text' => $text, 'entities' => $e];

                return new Reply(Phrases::say('clarify', $this->lang), $choices->map(fn ($i) => Phrases::chip($i, $this->lang))->all());
            }
        }

        $this->state['step'] = null;

        return match ($intent) {
            'salutation' => new Reply(Phrases::say('greet', $this->lang, ['cabinet' => config('cabinet.name')]), Phrases::menu($this->lang)),
            'merci', 'affirmer' => new Reply(Phrases::say('thanks', $this->lang)),
            'prendre_rdv', 'urgence' => $this->startBooking($intent, $e, $text),
            'annuler', 'deplacer' => $this->findExisting($intent === 'annuler' ? 'cancel' : 'move', $e),
            'horaires' => new Reply(Phrases::say('hours', $this->lang, ['hours' => $this->hoursText()])),
            'adresse' => new Reply(Phrases::say('address', $this->lang, [
                'address' => config('cabinet.address'), 'hint' => config('cabinet.address_hint'), 'maps' => config('cabinet.maps_url'),
            ])),
            'question_faq' => $this->answerFaq($text),
            default => new Reply(Phrases::say('off_topic', $this->lang), Phrases::menu($this->lang)),
        };
    }

    // ── Booking ─────────────────────────────────────────────────────────────

    private function startBooking(string $intent, array $e, string $text): Reply
    {
        $slug = $intent === 'urgence' || ! empty($e['emergency']) ? 'urgence' : ($e['service'] ?? null);
        $service = Service::where('slug', $slug)->where('bookable_online', true)->first()
            ?? Service::where('slug', 'consultation')->first();

        $this->state = array_merge($this->state, [
            'purpose' => 'book', 'service' => $service->slug, 'reason' => $text,
            'date' => $e['date'] ?? null, 'part' => $e['part_of_day'] ?? null, 'slot' => null,
        ]);

        return $this->offer($e['date'] ?? null, $e['part_of_day'] ?? null, $e['time'] ?? null);
    }

    private function offer(?string $date, ?string $part, ?string $time, ?string $lead = null): Reply
    {
        $service = $this->service();
        $dr = $this->practitioner();
        $urgent = $service->is_emergency;

        // A precise "demain à 10h30" that is free goes straight to the name question.
        if ($date && $time && ($this->state['step'] ?? null) !== 'move_slot') {
            $at = CarbonImmutable::parse("$date $time");
            if ($at->isFuture() && $this->availability->isFree($dr, $service, $at)) {
                return $this->choose($at);
            }
        }

        $window = ['morning' => ['08:00', '13:00'], 'afternoon' => ['13:00', '17:00'], 'evening' => ['17:00', '21:00']][$part] ?? null;
        $from = $date ? CarbonImmutable::parse($date) : CarbonImmutable::today();
        $slots = $this->availability->upcoming($dr, $service, $from, limit: 3, days: $date ? 1 : 14, window: $window);

        $intro = $lead ?? Phrases::say($urgent ? 'offer_urgent' : 'offer', $this->lang, [
            'service' => mb_strtolower($service->name), 'when' => Phrases::when($date, $part, $this->lang),
        ]);

        if ($slots->isEmpty() && ($date || $window)) {
            $intro = Phrases::say('none_then', $this->lang, ['when' => Phrases::when($date, $part, $this->lang)]);
            $slots = $this->availability->upcoming($dr, $service, $date ? $from->addDay() : $from, limit: 3);
        }
        if ($slots->isEmpty()) {
            return new Reply(Phrases::say('none_at_all', $this->lang, ['phone' => config('cabinet.phone')]));
        }

        $this->state['offered'] = $slots->map->toIso8601String()->all();
        $this->state['step'] = ($this->state['purpose'] ?? null) === 'move' ? 'move_slot' : 'slot';
        if ($urgent) {
            $intro .= "\n".Phrases::say('urgent_call', $this->lang, ['phone' => config('cabinet.emergency_phone')]);
        }

        return new Reply($intro, $this->slotChips($this->state['offered']));
    }

    private function choose(CarbonImmutable $at): Reply
    {
        if (! $this->availability->isFree($this->practitioner(), $this->service(), $at) || $at->isPast()) {
            return $this->offer(null, null, null, Phrases::say('slot_taken', $this->lang));
        }
        $this->state['slot'] = $at->toIso8601String();

        if ($patient = $this->conversation->patient) {
            return $this->book($patient);
        }
        $this->state['step'] = 'name';

        return new Reply(Phrases::say('ask_name', $this->lang, ['slot' => Phrases::slot($at, $this->lang)]));
    }

    private function book(Patient $patient): Reply
    {
        $at = CarbonImmutable::parse($this->state['slot']);
        $service = $this->service();

        try {
            $appointment = $this->booker->book($patient, $this->practitioner(), $service, $at, 'assistant', 'pending', Str::limit($this->state['reason'] ?? '', 250));
        } catch (SlotTaken) {
            return $this->offer(null, null, null, Phrases::say('slot_taken', $this->lang));
        }

        AssistantRequest::create([
            'conversation_id' => $this->conversation->id,
            'patient_id' => $patient->id,
            'appointment_id' => $appointment->id,
            'type' => 'booking',
            'message' => $this->state['reason'] ?? '',
            'summary' => $service->name.' · '.Phrases::slot($at, 'fr'),
            'payload' => ['starts_at' => $at->toIso8601String()],
        ]);

        $this->conversation->patient()->associate($patient);
        $this->state = ['lang' => $this->lang];

        return new Reply(
            Phrases::say('booked', $this->lang, ['name' => $patient->first_name, 'service' => mb_strtolower($service->name), 'slot' => Phrases::slot($at, $this->lang)]),
            booking: ['id' => $appointment->id, 'starts_at' => $at->toIso8601String(), 'service' => $service->name],
        );
    }

    // ── Existing appointments ───────────────────────────────────────────────

    private function findExisting(string $purpose, array $e): Reply
    {
        $this->state['purpose'] = $purpose;
        if ($patient = $this->conversation->patient) {
            return $this->withPatientFound($patient);
        }
        if (! empty($e['phone'])) {
            return $this->withPhone($e['phone']);
        }
        $this->state['step'] = 'phone';

        return new Reply(Phrases::say('ask_existing_phone', $this->lang));
    }

    private function withPatientFound(Patient $patient): Reply
    {
        $appointment = $patient->appointments()
            ->whereIn('status', ['pending', 'confirmed'])
            ->where('starts_at', '>', now())
            ->orderBy('starts_at')->with('service')->first();

        if (! $appointment) {
            $this->state['step'] = null;

            return new Reply(Phrases::say('no_appointment', $this->lang), [Phrases::chip('prendre_rdv', $this->lang)]);
        }

        $this->state['appointment_id'] = $appointment->id;
        $vars = ['service' => $appointment->service->name, 'slot' => Phrases::slot($appointment->starts_at->toImmutable(), $this->lang)];

        if ($this->state['purpose'] === 'cancel') {
            $this->state['step'] = 'confirm_cancel';

            return new Reply(Phrases::say('confirm_cancel', $this->lang, $vars), [
                Phrases::chip('yes', $this->lang, 'yes'), Phrases::chip('no', $this->lang, 'no'),
            ]);
        }

        $this->state['service'] = $appointment->service->slug;

        return $this->offer(null, null, null, Phrases::say('move_offer', $this->lang, $vars));
    }

    private function confirmCancel(bool $yes): Reply
    {
        $appointment = Appointment::find($this->state['appointment_id'] ?? 0);
        $this->state['step'] = null;
        if (! $yes || ! $appointment) {
            return new Reply(Phrases::say('kept', $this->lang));
        }

        $appointment->update(['status' => 'cancelled']);
        AssistantRequest::create([
            'conversation_id' => $this->conversation->id,
            'patient_id' => $appointment->patient_id,
            'appointment_id' => $appointment->id,
            'type' => 'cancel',
            'message' => $this->lastPatientMessage(),
            'summary' => 'Annulé par le patient · '.Phrases::slot($appointment->starts_at->toImmutable(), 'fr'),
            'status' => 'done',
        ]);

        return new Reply(Phrases::say('cancelled', $this->lang), [Phrases::chip('prendre_rdv', $this->lang)]);
    }

    private function requestMove(CarbonImmutable $to): Reply
    {
        $appointment = Appointment::find($this->state['appointment_id'] ?? 0);
        $this->state['step'] = null;
        if (! $appointment) {
            return new Reply(Phrases::say('no_appointment', $this->lang));
        }

        AssistantRequest::create([
            'conversation_id' => $this->conversation->id,
            'patient_id' => $appointment->patient_id,
            'appointment_id' => $appointment->id,
            'type' => 'reschedule',
            'message' => $this->lastPatientMessage(),
            'summary' => 'Déplacer '.Phrases::slot($appointment->starts_at->toImmutable(), 'fr').' → '.Phrases::slot($to, 'fr'),
            'payload' => ['from' => $appointment->starts_at->toIso8601String(), 'to' => $to->toIso8601String()],
        ]);

        return new Reply(Phrases::say('move_requested', $this->lang, ['slot' => Phrases::slot($to, $this->lang)]));
    }

    // ── Questions and handover ──────────────────────────────────────────────

    private function answerFaq(string $text): Reply
    {
        $faqs = Faq::where('is_active', true)->get();
        $index = $faqs->map(fn (Faq $f) => [
            'id' => $f->id,
            'texts' => array_values(array_filter([$f->question, ...preg_split('/\R/u', (string) $f->variants)])),
        ])->all();

        $match = $this->nlu->faq($text, $index);
        if ($match && ($faq = $faqs->firstWhere('id', $match['id']))) {
            return new Reply($faq->answer, [Phrases::chip('prendre_rdv', $this->lang)]);
        }

        return $this->handover($text);
    }

    private function handover(string $text): Reply
    {
        $this->state['purpose'] = 'handover';
        $this->state['reason'] = $text;
        if ($patient = $this->conversation->patient) {
            return $this->createHandover($patient);
        }
        $this->state['step'] = 'phone';

        return new Reply(Phrases::say('handover', $this->lang));
    }

    private function createHandover(Patient $patient): Reply
    {
        AssistantRequest::create([
            'conversation_id' => $this->conversation->id,
            'patient_id' => $patient->id,
            'type' => 'unanswered',
            'message' => $this->state['reason'] ?? $this->lastPatientMessage(),
            'summary' => 'Question sans réponse sûre de l’assistant',
        ]);
        $this->state = ['lang' => $this->lang];

        return new Reply(Phrases::say('handover_done', $this->lang));
    }

    // ── Helpers ─────────────────────────────────────────────────────────────

    private function withPhone(string $phone): Reply
    {
        $phone = Patient::normalizePhone($phone);
        $purpose = $this->state['purpose'] ?? 'book';

        if ($purpose === 'cancel' || $purpose === 'move') {
            $patient = Patient::where('phone', $phone)->first();
            if (! $patient) {
                $this->state['step'] = null;

                return new Reply(Phrases::say('no_appointment', $this->lang), [Phrases::chip('prendre_rdv', $this->lang)]);
            }
            $this->conversation->patient()->associate($patient);

            return $this->withPatientFound($patient);
        }

        [$first, $last] = array_pad(explode(' ', $this->state['name'] ?? 'Patient', 2), 2, null);
        $patient = Patient::firstOrCreate(['phone' => $phone], ['first_name' => $first, 'last_name' => $last]);
        $this->conversation->patient()->associate($patient);

        return $purpose === 'handover' ? $this->createHandover($patient) : $this->book($patient);
    }

    private function matchOffered(array $e): ?CarbonImmutable
    {
        if (empty($e['time'])) {
            return null;
        }
        foreach ($this->state['offered'] ?? [] as $iso) {
            $at = CarbonImmutable::parse($iso);
            if ($at->format('H:i') === $e['time'] && (empty($e['date']) || $at->toDateString() === $e['date'])) {
                return $at;
            }
        }

        return null;
    }

    private function readName(string $text, array $e): ?string
    {
        if (! empty($e['name'])) {
            return $e['name'];
        }
        $clean = trim(preg_replace('/[^\p{L}\s\'-]/u', ' ', $text));
        $words = preg_split('/\s+/u', $clean, -1, PREG_SPLIT_NO_EMPTY);

        return count($words) >= 1 && count($words) <= 3 && mb_strlen($clean) <= 40 && ! preg_match('/\d/', $text)
            ? mb_convert_case($clean, MB_CASE_TITLE)
            : null;
    }

    private function readPhone(string $text): ?string
    {
        $digits = Patient::normalizePhone($text);

        return preg_match('/^0[5-7]\d{8}$/', $digits) ? $digits : null;
    }

    private function slotChips(array $isoSlots): array
    {
        $chips = array_map(fn ($iso) => [
            'label' => Phrases::slotChip(CarbonImmutable::parse($iso), $this->lang),
            'value' => 'slot:'.$iso,
        ], $isoSlots);
        $chips[] = Phrases::chip('other_day', $this->lang, 'other_day');

        return $chips;
    }

    private function chosenLabel(): string
    {
        return isset($this->state['slot']) ? Phrases::slot(CarbonImmutable::parse($this->state['slot']), $this->lang) : '';
    }

    private function hoursText(): string
    {
        return OpeningHours::summary($this->practitioner());
    }

    private function lastPatientMessage(): string
    {
        return (string) $this->conversation->messages()->where('role', 'patient')->latest('id')->value('body');
    }

    private function service(): Service
    {
        return Service::where('slug', $this->state['service'] ?? 'consultation')->first() ?? Service::where('slug', 'consultation')->firstOrFail();
    }

    private function practitioner(): Practitioner
    {
        return Practitioner::query()->orderBy('id')->firstOrFail();
    }
}
