<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['token', 'patient_id', 'state', 'last_message_at'])]
class Conversation extends Model
{
    public function messages(): HasMany
    {
        return $this->hasMany(ConversationMessage::class);
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    protected function casts(): array
    {
        return [
            'state' => 'array',
            'last_message_at' => 'datetime',
        ];
    }
}
