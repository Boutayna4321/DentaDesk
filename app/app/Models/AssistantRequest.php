<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['conversation_id', 'patient_id', 'appointment_id', 'type', 'message', 'summary', 'payload', 'status', 'handled_by'])]
class AssistantRequest extends Model
{
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }

    protected function casts(): array
    {
        return ['payload' => 'array'];
    }
}
