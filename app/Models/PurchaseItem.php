<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PurchaseItem extends Model
{
    protected $fillable = [
        'purchase_id',
        'product_id',
        'unit',
        'conversion_factor',
        'qty',
        'received_qty',
        'purchase_price',
        'discount_amount',
        'subtotal',
        'ownership_type',
    ];

    protected $casts = [
        'conversion_factor' => 'integer',
        'qty' => 'decimal:2',
        'received_qty' => 'decimal:2',
        'purchase_price' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'subtotal' => 'decimal:2',
    ];

    public function purchase(): BelongsTo
    {
        return $this->belongsTo(Purchase::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function goodsReceiptItems(): HasMany
    {
        return $this->hasMany(GoodsReceiptItem::class, 'purchase_item_id');
    }

    public function getRemainingQtyAttribute(): float
    {
        return max(0.0, round((float) $this->qty - (float) $this->received_qty, 2));
    }
}
