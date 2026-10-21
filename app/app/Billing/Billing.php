<?php

namespace App\Billing;

use App\Models\ActivityLog;
use App\Models\Document;
use App\Models\Payment;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/** Every write to invoices, quotes and payments goes through here. */
class Billing
{
    /** @param list<array{label:string,tooth:?int,code:?string,quantity:int,unit_price_cents:int}> $lines */
    public function saveDraft(Document $doc, array $lines, ?string $body): Document
    {
        if (! $doc->isDraft()) {
            throw new RuntimeException('Un document validé ne se modifie plus. Annulez-le et refaites-en un.');
        }

        return DB::transaction(function () use ($doc, $lines, $body) {
            $doc->fill(['body' => $body]);
            $doc->total_cents = 0;
            $doc->save();
            $doc->lines()->delete();
            foreach (array_values($lines) as $i => $l) {
                $amount = $l['quantity'] * $l['unit_price_cents'];
                $doc->lines()->create([...$l, 'position' => $i, 'amount_cents' => $amount]);
                $doc->total_cents += $amount;
            }
            $doc->save();

            return $doc;
        });
    }

    /** Locks the document, gives it the next number of the year, and freezes the letterhead. */
    public function validate(Document $doc, User $by): Document
    {
        if (! $doc->isDraft()) {
            throw new RuntimeException('Ce document est déjà validé.');
        }
        if ($doc->lines()->count() === 0) {
            throw new RuntimeException('Ajoutez au moins une ligne avant de valider.');
        }

        return DB::transaction(function () use ($doc, $by) {
            $year = (int) CarbonImmutable::now()->format('Y');
            // Lock the type's rows for the year so two validations never get the same number.
            $last = Document::where('type', $doc->type)->where('year', $year)->lockForUpdate()->max('seq') ?? 0;
            $seq = $last + 1;
            $doc->update([
                'status' => 'validated', 'year' => $year, 'seq' => $seq,
                'number' => sprintf('%s-%d-%04d', Document::PREFIX[$doc->type], $year, $seq),
                'issued_on' => CarbonImmutable::today(), 'letterhead' => Letterhead::current(),
                'validated_by' => $by->id, 'validated_at' => now(),
            ]);
            ActivityLog::record('document.validated', "{$doc->title} · {$doc->patient->full_name} · ".Money::format($doc->total_cents), $doc);

            return $doc;
        });
    }

    /** A validated invoice is never deleted: it is cancelled, with a reason, and keeps its number. */
    public function cancel(Document $doc, string $reason): Document
    {
        if ($doc->status !== 'validated') {
            throw new RuntimeException('Seul un document validé peut être annulé.');
        }
        if ($doc->paid_cents > 0) {
            throw new RuntimeException('Cette facture a des paiements : remboursez-les avant de l’annuler.');
        }
        $doc->update(['status' => 'cancelled', 'cancel_reason' => $reason, 'cancelled_at' => now()]);
        ActivityLog::record('document.cancelled', "{$doc->title} annulée : $reason", $doc);

        return $doc;
    }

    public function pay(Document $doc, int $cents, string $method, ?string $reference, User $by): Payment
    {
        return DB::transaction(function () use ($doc, $cents, $method, $reference, $by) {
            $doc = Document::lockForUpdate()->findOrFail($doc->id);
            if (! $doc->isInvoice() || $doc->status !== 'validated') {
                throw new RuntimeException('On encaisse seulement une facture validée.');
            }
            if ($cents <= 0 || $cents > $doc->balance_cents) {
                throw new RuntimeException('Montant invalide : reste à payer '.Money::format($doc->balance_cents).'.');
            }
            $payment = $doc->payments()->create([
                'amount_cents' => $cents, 'method' => $method, 'reference' => $reference,
                'paid_on' => CarbonImmutable::today(), 'received_by' => $by->id,
            ]);
            $doc->increment('paid_cents', $cents);
            ActivityLog::record('payment.received', Money::format($cents).' ('.Payment::METHODS[$method].") · {$doc->number}", $doc);

            return $payment;
        });
    }

    /** A signed quote becomes a draft invoice with the same lines. */
    public function invoiceFromQuote(Document $quote, User $by): Document
    {
        if ($quote->type !== 'quote' || $quote->status !== 'validated') {
            throw new RuntimeException('Seul un devis validé peut devenir une facture.');
        }
        $invoice = Document::create(['type' => 'invoice', 'patient_id' => $quote->patient_id, 'quote_id' => $quote->id, 'created_by' => $by->id]);

        return $this->saveDraft($invoice, $quote->lines->map->only(['label', 'tooth', 'code', 'quantity', 'unit_price_cents'])->all(),
            $quote->body);
    }
}
