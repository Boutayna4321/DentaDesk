<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\InternalMessage;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Dentist ↔ front desk chat. Polled by the panel while it is open. */
class MessageController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $me = $request->user();
        $people = User::where('is_active', true)->whereKeyNot($me->id)->orderBy('name')->get()
            ->map(fn (User $u) => [
                'id' => $u->id, 'name' => $u->name, 'initials' => $u->initials, 'role' => $u->role_label,
                'unread' => InternalMessage::where(['sender_id' => $u->id, 'recipient_id' => $me->id])->whereNull('read_at')->count(),
            ]);
        $with = (int) $request->query('with', $people->sortByDesc('unread')->first()['id'] ?? 0);

        $messages = InternalMessage::with('patient')->between($me->id, $with)->latest('id')->take(60)->get()->reverse()->values();
        InternalMessage::where(['sender_id' => $with, 'recipient_id' => $me->id])->whereNull('read_at')->update(['read_at' => now()]);

        return response()->json([
            'with' => $with,
            'people' => $people->values(),
            'messages' => $messages->map(fn (InternalMessage $m) => [
                'id' => $m->id, 'mine' => $m->sender_id === $me->id, 'body' => $m->body,
                'at' => $m->created_at->isToday() ? $m->created_at->format('H:i') : $m->created_at->translatedFormat('j M H:i'),
                'read' => $m->read_at !== null,
                'patient' => $m->patient ? ['name' => $m->patient->full_name, 'url' => route('dash.patients.show', $m->patient)] : null,
            ]),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'to' => ['required', 'exists:users,id'],
            'body' => ['required', 'string', 'max:2000'],
            'patient_id' => ['nullable', 'exists:patients,id'],
        ]);
        abort_if((int) $data['to'] === $request->user()->id, 422);
        $m = InternalMessage::create(['sender_id' => $request->user()->id, 'recipient_id' => $data['to'], 'body' => trim($data['body']), 'patient_id' => $data['patient_id'] ?? null]);

        return response()->json(['id' => $m->id], 201);
    }
}
