<?php

namespace App\Http\Controllers\Dashboard;

use App\Booking\Booker;
use App\Booking\SlotTaken;
use App\Http\Controllers\Controller;
use App\Models\AssistantRequest;
use App\Models\Faq;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** What the assistant could not, or should not, settle alone. */
class RequestController extends Controller
{
    public function index(): View
    {
        $with = ['patient', 'appointment.service', 'conversation.messages'];

        return view('dashboard.requests', [
            'open' => AssistantRequest::with($with)->where('status', 'open')->latest()->get(),
            'handled' => AssistantRequest::with(['patient'])->where('status', '!=', 'open')->latest('updated_at')->take(15)->get(),
            'faqs' => Faq::where('is_active', true)->orderBy('question')->get(['id', 'question']),
        ]);
    }

    public function act(Request $request, AssistantRequest $assistantRequest, string $action, Booker $booker): RedirectResponse
    {
        $r = $assistantRequest;
        $done = fn (string $status = 'done') => $r->update(['status' => $status, 'handled_by' => $request->user()->id]);

        switch ($action) {
            case 'confirm':
                $r->appointment?->update(['status' => 'confirmed']);
                $done();
                $message = 'Rendez-vous confirmé.';
                break;

            case 'decline':
                if ($r->type === 'booking') {
                    $r->appointment?->update(['status' => 'cancelled']);
                }
                $done('dismissed');
                $message = 'Demande refusée.';
                break;

            case 'accept':
                try {
                    $booker->move($r->appointment, CarbonImmutable::parse($r->payload['to'])->setTimezone(config('app.timezone')));
                } catch (SlotTaken) {
                    return back()->with('error', 'Le nouveau créneau n’est plus libre. Proposez-en un autre au patient.');
                }
                $done();
                $message = 'Rendez-vous déplacé.';
                break;

            case 'faq':
                // The question the assistant missed becomes a new way of asking an existing FAQ,
                // or a new FAQ. Next time it answers alone.
                $data = $request->validate([
                    'faq_id' => ['nullable', 'exists:faqs,id'],
                    'question' => ['required_without:faq_id', 'nullable', 'string', 'max:200'],
                    'answer' => ['required_without:faq_id', 'nullable', 'string', 'max:1000'],
                ]);
                if (! empty($data['faq_id'])) {
                    $faq = Faq::find($data['faq_id']);
                    $faq->update(['variants' => trim($faq->variants."\n".$r->message)]);
                } else {
                    Faq::create(['question' => $data['question'], 'answer' => $data['answer'], 'variants' => $r->message]);
                }
                $done();
                $message = 'Ajouté à la FAQ : l’assistant répondra seul la prochaine fois.';
                break;

            default:
                $done();
                $message = 'Marqué comme traité.';
        }

        return back()->with('ok', $message);
    }
}
