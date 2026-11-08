<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GoodsReceiptItem extends Model
{
    protected $fillable = [
        'goods_receipt_id',
        'purchase_item_id',
        'product_id',
        'qty_sent',
        'qty_received',
        'actual_unit_cost',
        'condition',
        'ownership_type',
        'notes',
    ];

    protected $casts = [
        'qty_sent' => 'decimal:2',
        'qty_received' => 'decimal:2',
        'actual_unit_cost' => 'decimal:2',
    ];

    public function goodsReceipt(): BelongsTo
    {
        return $this->belongsTo(GoodsReceipt::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
