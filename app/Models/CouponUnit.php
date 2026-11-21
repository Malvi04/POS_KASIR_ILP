<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CouponUnit extends Model
{
    protected $fillable = [
        'product_id',
        'location_id',
        'branch',
        'quota',
        'allocated_by',
        'category', // bbm, beras, galon, pulsa, produk
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

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function allocatedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'allocated_by');
    }

    public function redemptions(): HasMany
    {
        return $this->hasMany(CouponRedemption::class);
    }
}
