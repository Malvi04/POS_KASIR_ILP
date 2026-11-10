<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CouponRedemption extends Model
{
    protected $fillable = [
        'coupon_unit_id',
        'transaction_id',
        'redeemed_at',
        'redeemed_by',
        'status',
    ];

    protected $casts = [
        'redeemed_at' => 'datetime',
    ];

    public function couponUnit(): BelongsTo
    {
        return $this->belongsTo(CouponUnit::class);
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }

    public function redeemer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'redeemed_by');
    }
}
