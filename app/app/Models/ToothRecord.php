<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['patient_id', 'tooth', 'state', 'note'])]
class ToothRecord extends Model
{
    public const STATES = ['to_treat' => 'À traiter', 'treated' => 'Soignée', 'missing' => 'Absente'];
}
