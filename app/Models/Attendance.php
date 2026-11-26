<?php

namespace App\Models;

use App\Traits\HasUuidV7;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Attendance extends Model
{
    use HasUuidV7;

    protected $fillable = [
        'uuid',
        'staff_name',
        'user_id',
        'terminal',
        'branch',
        'date',
        'check_in',
        'check_out',
        'status',
        'notes',
        'synced_at',
        'sync_error',
    ];

    protected $casts = [
        'date' => 'date',
        'synced_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
