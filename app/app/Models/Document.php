<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['type', 'status', 'year', 'seq', 'number', 'patient_id', 'appointment_id', 'quote_id', 'issued_on', 'body',
    'total_cents', 'paid_cents', 'letterhead', 'created_by', 'validated_by', 'validated_at', 'cancel_reason', 'cancelled_at'])]
class Document extends Model
{
    /** Same as the column defaults, so a new model knows it is a draft before being reloaded. */
    protected $attributes = ['status' => 'draft', 'total_cents' => 0, 'paid_cents' => 0];

    public const TYPES = ['invoice' => 'Facture', 'quote' => 'Devis'];

    public const PREFIX = ['invoice' => 'F', 'quote' => 'D'];

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(DocumentLine::class)->orderBy('position');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class)->orderBy('paid_on');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }

    public function isInvoice(): bool
    {
        return $this->type === 'invoice';
    }

    public function getBalanceCentsAttribute(): int
    {
        return max(0, $this->total_cents - $this->paid_cents);
    }

    /** draft | to_collect | partial | paid | cancelled, for invoices; quotes stop at validated. */
    public function getPaymentStateAttribute(): string
    {
        return match (true) {
            $this->status !== 'validated' => $this->status,
            ! $this->isInvoice() => 'validated',
            $this->paid_cents === 0 => 'to_collect',
            $this->paid_cents < $this->total_cents => 'partial',
            default => 'paid',
        };
    }

    public function getTitleAttribute(): string
    {
        return self::TYPES[$this->type].($this->number ? ' N° '.$this->number : ' (brouillon)');
    }

    protected function casts(): array
    {
        return [
            'issued_on' => 'date',
            'validated_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'letterhead' => 'array',
        ];
    }
}
