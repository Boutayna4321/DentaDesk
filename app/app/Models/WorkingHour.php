<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['practitioner_id', 'weekday', 'opens_at', 'closes_at'])]
class WorkingHour extends Model
{
    public $timestamps = false;
}
