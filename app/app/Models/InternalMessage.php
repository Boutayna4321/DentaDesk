<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['sender_id', 'recipient_id', 'body', 'patient_id', 'read_at'])]
class InternalMessage extends Model
{
    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    /** Messages between two people, in either direction. */
    public function scopeBetween(Builder $q, int $a, int $b): Builder
    {
        return $q->where(fn ($w) => $w->where(['sender_id' => $a, 'recipient_id' => $b])->orWhere(fn ($x) => $x->where(['sender_id' => $b, 'recipient_id' => $a])));
    }

    protected function casts(): array
    {
        return ['read_at' => 'datetime'];
    }
}
