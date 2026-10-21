<?php

namespace App\Http\Controllers\Dashboard;

use App\Assistant\NluClient;
use App\Http\Controllers\Controller;
use App\Models\AssistantRequest;
use App\Models\Conversation;
use App\Models\ConversationMessage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** The model in production: health, what patients ask, and the corrections that retrain it. */
class AssistantLogController extends Controller
{
    public const INTENTS = ['prendre_rdv', 'urgence', 'annuler', 'deplacer', 'horaires', 'adresse', 'question_faq',
        'salutation', 'merci', 'affirmer', 'nier', 'hors_sujet'];

    public function index(NluClient $nlu): View
    {
        $since = now()->subDays(30);
        $messages = ConversationMessage::where('role', 'patient')->whereNotNull('intent')->where('created_at', '>=', $since);

        return view('dashboard.assistant', [
            'health' => $nlu->health(),
            'stats' => [
                'conversations' => Conversation::where('created_at', '>=', $since)->count(),
                'messages' => (clone $messages)->count(),
                'avgConfidence' => (clone $messages)->avg('confidence'),
                'bookings' => AssistantRequest::where('type', 'booking')->where('created_at', '>=', $since)->count(),
                'handovers' => AssistantRequest::where('type', 'unanswered')->where('created_at', '>=', $since)->count(),
                'corrections' => ConversationMessage::whereNotNull('corrected_intent')->count(),
            ],
            'byIntent' => (clone $messages)->selectRaw('intent, count(*) as n, avg(confidence) as conf')->groupBy('intent')->orderByDesc('n')->get(),
            'conversations' => Conversation::with(['messages', 'patient'])->latest('last_message_at')->take(20)->get(),
            'intents' => self::INTENTS,
        ]);
    }

    public function correct(Request $request, ConversationMessage $message): RedirectResponse
    {
        $message->update($request->validate(['corrected_intent' => ['required', Rule::in(self::INTENTS)]]));

        return back()->with('ok', 'Correction enregistrée. Elle servira au prochain entraînement (php artisan nlu:export).');
    }
}
