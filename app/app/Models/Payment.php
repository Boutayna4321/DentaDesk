<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['document_id', 'amount_cents', 'method', 'reference', 'paid_on', 'received_by'])]
class Payment extends Model
{
    public const METHODS = ['especes' => 'Espèces', 'carte' => 'Carte bancaire', 'cheque' => 'Chèque', 'virement' => 'Virement'];

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    protected function casts(): array
    {
        return ['paid_on' => 'date'];
    }
}
