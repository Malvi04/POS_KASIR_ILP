<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CashMovement extends Model
{
    use HasFactory;

    protected $fillable = [
        'offline_id',
        'shift_id',
        'location_id',
        'terminal_code',
        'type',
        'amount',
        'reason',
        'user_id',
        'cashier_nik',
        'occurred_at',
    ];

    protected $casts = [
        'amount' => 'float',
        'occurred_at' => 'datetime',
    ];

    public function shift(): BelongsTo
    {
        return $this->belongsTo(CashierShift::class, 'shift_id');
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'location_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
