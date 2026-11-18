<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductStock extends Model
{
    protected $fillable = [
        'product_id',
        'location_id',
        'stock',
        'min_stock',
        'is_active',
        'first_received_at',
        'last_received_at',
        'last_sold_at',
        'version',
    ];

    protected $casts = [
        'stock' => 'integer',
        'min_stock' => 'integer',
        'is_active' => 'boolean',
        'first_received_at' => 'datetime',
        'last_received_at' => 'datetime',
        'last_sold_at' => 'datetime',
        'version' => 'integer',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }
}
