<?php

namespace App\Http\Controllers\Dashboard;

use App\Billing\AmountInWords;
use App\Billing\Billing;
use App\Billing\Letterhead;
use App\Billing\Money;
use App\Http\Controllers\Controller;
use App\Models\Act;
use App\Models\Appointment;
use App\Models\Document;
use App\Models\Patient;
use App\Models\Payment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use RuntimeException;

/**
 * Invoices and quotes. The dentist writes and validates them; the front desk prints and
 * takes payment. CNSS / CNOPS forms stay on paper.
 */
class DocumentController extends Controller
{
    public function __construct(private Billing $billing) {}

    public function index(Request $request): View
    {
        $tab = $request->query('tab', 'collect');
        $query = Document::with('patient')->orderByRaw('issued_on IS NULL DESC')->orderByDesc('issued_on')->orderByDesc('id');
        $query = match ($tab) {
            'collect' => $query->where('type', 'invoice')->where('status', 'validated')->whereColumn('paid_cents', '<', 'total_cents'),
            'drafts' => $query->where('status', 'draft'),
            'quotes' => $query->where('type', 'quote')->where('status', '!=', 'draft'),
            default => $query->where('status', '!=', 'draft'),
        };

        return view('dashboard.documents', [
            'tab' => $tab,
            'documents' => $query->paginate(25)->withQueryString(),
            'counts' => [
                'collect' => Document::where('type', 'invoice')->where('status', 'validated')->whereColumn('paid_cents', '<', 'total_cents')->count(),
                'drafts' => Document::where('status', 'draft')->count(),
            ],
            'today' => Payment::whereDate('paid_on', today())->sum('amount_cents'),
        ]);
    }

    public function create(Request $request): RedirectResponse
    {
        Gate::authorize('admin');
        $data = $request->validate([
            'patient_id' => ['required', 'exists:patients,id'],
            'type' => ['required', Rule::in(array_keys(Document::TYPES))],
            'appointment_id' => ['nullable', 'exists:appointments,id'],
        ]);
        $doc = Document::create($data + ['created_by' => $request->user()->id]);

        // An invoice made from a visit starts with the visit's care, at the catalogue price.
        if ($doc->appointment_id && ($a = Appointment::with('service')->find($doc->appointment_id))) {
            $act = Act::where('label', 'like', $a->service->name.'%')->first();
            $this->billing->saveDraft($doc, [[
                'label' => $act->label ?? $a->service->name, 'tooth' => null, 'code' => $act?->code,
                'quantity' => 1, 'unit_price_cents' => $act->price_cents ?? 0,
            ]], null);
        }

        return redirect()->route('dash.documents.edit', $doc);
    }

    public function edit(Document $document): View|RedirectResponse
    {
        Gate::authorize('admin');
        if (! $document->isDraft()) {
            return redirect()->route('dash.documents.show', $document);
        }

        return view('dashboard.document-edit', [
            'doc' => $document->load('lines', 'patient'),
            'acts' => Act::where('is_active', true)->orderBy('position')->get(),
        ]);
    }

    public function update(Request $request, Document $document): RedirectResponse
    {
        Gate::authorize('admin');
        $data = $request->validate([
            'body' => ['nullable', 'string', 'max:5000'],
            'lines' => ['array'],
            'lines.*.label' => ['required', 'string', 'max:160'],
            'lines.*.tooth' => ['nullable', 'integer', 'between:11,48'],
            'lines.*.code' => ['nullable', 'string', 'max:20'],
            'lines.*.quantity' => ['required', 'integer', 'min:1', 'max:99'],
            'lines.*.unit_price' => ['required', 'string', 'max:20'],
        ]);
        $lines = collect($data['lines'] ?? [])->map(fn ($l) => [
            'label' => $l['label'], 'tooth' => $l['tooth'] ?? null, 'code' => $l['code'] ?? null,
            'quantity' => (int) $l['quantity'], 'unit_price_cents' => Money::parse($l['unit_price']),
        ])->all();

        try {
            $this->billing->saveDraft($document, $lines, $data['body'] ?? null);
            if ($request->input('action') === 'validate') {
                $this->billing->validate($document, $request->user());

                return redirect()->route('dash.documents.show', $document)->with('ok', "{$document->title} validé(e). Prêt à imprimer.");
            }
        } catch (RuntimeException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return $request->input('action') === 'preview'
            ? redirect()->route('dash.documents.print', $document)
            : back()->with('ok', 'Brouillon enregistré.');
    }

    public function show(Document $document): View
    {
        return view('dashboard.document', [
            'doc' => $document->load('lines', 'patient', 'payments.receiver', 'author'),
            'methods' => Payment::METHODS,
        ]);
    }

    /** The page that goes to the printer (or to "Save as PDF"). */
    public function print(Document $document): View
    {
        $document->load('lines', 'patient', 'payments');

        return view('documents.print', [
            'doc' => $document,
            'head' => $document->letterhead ?? Letterhead::current(),
            'words' => AmountInWords::dirhams($document->total_cents),
        ]);
    }

    public function pay(Request $request, Document $document): RedirectResponse
    {
        $data = $request->validate([
            'amount' => ['required', 'string', 'max:20'],
            'method' => ['required', Rule::in(array_keys(Payment::METHODS))],
            'reference' => ['nullable', 'string', 'max:60'],
        ]);
        try {
            $this->billing->pay($document, Money::parse($data['amount']), $data['method'], $data['reference'] ?? null, $request->user());
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('ok', 'Paiement enregistré.');
    }

    public function cancel(Request $request, Document $document): RedirectResponse
    {
        Gate::authorize('admin');
        $reason = $request->validate(['reason' => ['required', 'string', 'max:200']])['reason'];
        try {
            $this->billing->cancel($document, $reason);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('ok', 'Document annulé. Son numéro reste réservé.');
    }

    public function convert(Request $request, Document $document): RedirectResponse
    {
        Gate::authorize('admin');
        try {
            $invoice = $this->billing->invoiceFromQuote($document, $request->user());
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('dash.documents.edit', $invoice)->with('ok', 'Facture préparée depuis le devis.');
    }

    public function destroy(Document $document): RedirectResponse
    {
        Gate::authorize('admin');
        abort_unless($document->isDraft(), 403, 'Seul un brouillon peut être supprimé.');
        $patient = $document->patient;
        $document->delete();

        return redirect()->route('dash.patients.show', $patient)->with('ok', 'Brouillon supprimé.');
    }
}
