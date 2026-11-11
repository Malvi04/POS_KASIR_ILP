<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CouponUnit extends Model
{
    protected $fillable = [
        'product_id',
        'category', // bbm, beras, galon, pulsa
        'serial_number',
        'recipient_name',
        'recipient_nik',
        'recipient_dept',
        'vehicle_no',
        'volume',
        'status', // AVAILABLE, ISSUED, REDEEMED, EXPIRED
        'nominal_value',
        'notes',
    ];

    protected $casts = [
        'nominal_value' => 'decimal:2',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function redemptions(): HasMany
    {
        return $this->hasMany(CouponRedemption::class);
    }
}
