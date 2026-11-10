<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StockRequest extends Model
{
    protected $fillable = [
        'request_number',
        'from_location_id',
        'to_location_id',
        'requested_by',
        'requested_at',
        'status',
        'notes',
    ];

    protected $casts = [
        'requested_at' => 'datetime',
    ];

    public function fromLocation(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'from_location_id');
    }

    public function toLocation(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'to_location_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(StockRequestItem::class, 'request_id');
    }
}
