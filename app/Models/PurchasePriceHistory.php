<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchasePriceHistory extends Model
{
    protected $fillable = [
        'product_id',
        'supplier_id',
        'purchase_id',
        'purchase_item_id',
        'goods_receipt_id',
        'recorded_by',
        'purchase_number',
        'unit',
        'conversion_factor',
        'raw_unit_price',
        'previous_price',
        'new_price',
        'price_diff',
        'change_percent',
        'change_type',
        'recorded_at',
        'notes',
    ];

    protected $casts = [
        'conversion_factor' => 'integer',
        'raw_unit_price' => 'decimal:2',
        'previous_price' => 'decimal:2',
        'new_price' => 'decimal:2',
        'price_diff' => 'decimal:2',
        'change_percent' => 'decimal:2',
        'recorded_at' => 'datetime',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function purchase(): BelongsTo
    {
        return $this->belongsTo(Purchase::class);
    }

    public function purchaseItem(): BelongsTo
    {
        return $this->belongsTo(PurchaseItem::class);
    }

    public function goodsReceipt(): BelongsTo
    {
        return $this->belongsTo(GoodsReceipt::class);
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
