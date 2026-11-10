<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VoidLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'transaction_id',
        'shift_id',
        'action_type',
        'supervisor_nik',
        'supervisor_name',
        'cashier_name',
        'item_sku',
        'item_name',
        'item_qty',
        'amount_voided',
        'reason',
        'ip_address',
    ];

    protected $casts = [
        'item_qty' => 'integer',
        'amount_voided' => 'float',
    ];

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(CashierShift::class);
    }
}
