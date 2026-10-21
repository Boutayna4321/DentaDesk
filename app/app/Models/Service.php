<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['slug', 'name', 'summary', 'duration_minutes', 'is_emergency', 'bookable_online', 'position'])]
class Service extends Model
{
    protected function casts(): array
    {
        return [
            'is_emergency' => 'boolean',
            'bookable_online' => 'boolean',
        ];
    }
}
