<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['patient_id', 'practitioner_id', 'service_id', 'starts_at', 'ends_at', 'status', 'source', 'reason', 'visit_note'])]
class Appointment extends Model
{
    public const STATUSES = [
        'pending' => 'À confirmer',
        'confirmed' => 'Confirmé',
        'arrived' => "Salle d'attente",
        'in_care' => 'En soin',
        'done' => 'Terminé',
        'no_show' => 'Absent',
        'cancelled' => 'Annulé',
    ];

    public const SOURCES = [
        'phone' => 'Téléphone',
        'web' => 'Site web',
        'assistant' => 'Assistant',
    ];

    /** Statuses that keep the slot taken. */
    public const BLOCKING = ['pending', 'confirmed', 'arrived', 'in_care', 'done'];

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function practitioner(): BelongsTo
    {
        return $this->belongsTo(Practitioner::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function getStatusLabelAttribute(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    public function getSourceLabelAttribute(): string
    {
        return self::SOURCES[$this->source] ?? $this->source;
    }

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }
}
