<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SyncState extends Model
{
    protected $fillable = [
        'entity',
        'last_synced_at',
        'last_status',
        'last_error',
    ];

    protected $casts = [
        'last_synced_at' => 'datetime',
    ];
}
