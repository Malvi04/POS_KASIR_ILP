<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Transaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'invoice_number',
        'offline_id',
        'shift_id',
        'user_id',
        'cashier_name',
        'terminal_code',
        'location_id',
        'branch',
        'member_id',
        'member_nik',
        'member_name',
        'payment_method',
        'subtotal',
        'discount_total',
        'promo_discount_total',
        'promo_enabled',
        'tax_total',
        'grand_total',
        'cash_received',
        'change_returned',
        'payment_reference',
        'split_cash',
        'split_non_cash',
        'status',
        'is_over_limit',
        'debt_status',
        'voided_at',
        'voided_by',
        'void_reason',
    ];

    protected $casts = [
        'subtotal' => 'float',
        'discount_total' => 'float',
        'tax_total' => 'float',
        'grand_total' => 'float',
        'cash_received' => 'float',
        'change_returned' => 'float',
        'split_cash' => 'float',
        'split_non_cash' => 'float',
        'is_over_limit' => 'boolean',
        'voided_at' => 'datetime',
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

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(TransactionItem::class);
    }

    public function isSuccess(): bool
    {
        return $this->status === 'SUCCESS';
    }

    public function isVoided(): bool
    {
        return $this->status === 'VOIDED';
    }
}
