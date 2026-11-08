<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockRequestItem extends Model
{
    protected $fillable = [
        'request_id',
        'product_id',
        'qty_requested',
        'qty_approved',
    ];

    protected $casts = [
        'qty_requested' => 'decimal:2',
        'qty_approved' => 'decimal:2',
    ];

    public function request(): BelongsTo
    {
        return $this->belongsTo(StockRequest::class, 'request_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
