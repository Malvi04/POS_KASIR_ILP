<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CashHandoverHeader extends Model
{
    protected $fillable = [
        'handover_number',
        'cashier_shift_id',
        'received_by',
        'handover_at',
        'total_amount',
        'status',
    ];

    protected $casts = [
        'handover_at' => 'datetime',
        'total_amount' => 'decimal:2',
    ];

    public function shift(): BelongsTo
    {
        return $this->belongsTo(CashierShift::class, 'cashier_shift_id');
    }

    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }
}
