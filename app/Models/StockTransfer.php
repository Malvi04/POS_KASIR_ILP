<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StockTransfer extends Model
{
    public const DRAFT = 'DRAFT';
    public const IN_TRANSIT = 'IN_TRANSIT';
    public const RECEIVED = 'RECEIVED';
    public const RECEIVED_DISCREPANCY = 'RECEIVED_DISCREPANCY';
    public const CANCELLED = 'CANCELLED';

    protected $guarded = [];

    protected $casts = [
        'transfer_date' => 'date',
        'shipped_at' => 'datetime',
        'received_at' => 'datetime',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(StockTransferItem::class, 'transfer_id');
    }

    public function fromLocation(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'from_location_id');
    }

    public function toLocation(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'to_location_id');
    }
}
