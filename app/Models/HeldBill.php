<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HeldBill extends Model
{
    use HasFactory;

    protected $fillable = [
        'hold_code',
        'shift_id',
        'terminal_code',
        'customer_name',
        'member_id',
        'member_nik',
        'items_payload',
        'subtotal',
        'tax_total',
        'grand_total',
        'total_items',
        'note',
        'status',
        'held_at',
        'recalled_at',
    ];

    protected $casts = [
        'items_payload' => 'array',
        'subtotal' => 'float',
        'tax_total' => 'float',
        'grand_total' => 'float',
        'total_items' => 'integer',
        'held_at' => 'datetime',
        'recalled_at' => 'datetime',
    ];

    public function shift(): BelongsTo
    {
        return $this->belongsTo(CashierShift::class, 'shift_id');
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'member_id');
    }
}
