<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Promotion extends Model
{
    protected $fillable = [
        'code',
        'name',
        'condition',
        'type',
        'value',
        'badge_class',
        'period_start',
        'period_end',
        'status',
        'status_color',
    ];

    protected $casts = [
        'value' => 'decimal:2',
        'period_start' => 'date',
        'period_end' => 'date',
    ];
}
