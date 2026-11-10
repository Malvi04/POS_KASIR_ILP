<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StoreReturnHeader extends Model
{
    protected $fillable = [
        'return_number',
        'from_location_id',
        'to_location_id',
        'created_by',
        'returned_at',
        'status',
        'direction',
        'condition_type',
        'notes',
        'driver_name',
        'vehicle_plate',
        'subtotal',
        'truck_fee',
        'net_amount',
    ];

    protected $casts = [
        'returned_at' => 'datetime',
        'subtotal' => 'decimal:2',
        'truck_fee' => 'decimal:2',
        'net_amount' => 'decimal:2',
    ];

    public function fromLocation(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'from_location_id');
    }

    public function toLocation(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'to_location_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(StoreReturnItem::class, 'store_return_id');
    }
}
