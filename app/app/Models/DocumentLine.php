<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['document_id', 'position', 'label', 'tooth', 'code', 'quantity', 'unit_price_cents', 'amount_cents'])]
class DocumentLine extends Model
{
    public $timestamps = false;
}
