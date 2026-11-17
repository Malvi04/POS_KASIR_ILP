<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockBatch extends Model
{
    protected $fillable = [
        'batch_number',
        'product_id',
        'location_id',
        'supplier_id',
        'purchase_id',
        'goods_receipt_id',
        'goods_receipt_item_id',
        'ownership_type',
        'initial_qty',
        'remaining_qty',
        'unit_cost',
        'received_at',
        'expired_date',
        'notes',
    ];

    protected $casts = [
        'initial_qty' => 'decimal:2',
        'remaining_qty' => 'decimal:2',
        'unit_cost' => 'decimal:2',
        'received_at' => 'datetime',
        'expired_date' => 'date',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function purchase(): BelongsTo
    {
        return $this->belongsTo(Purchase::class);
    }

    public function goodsReceipt(): BelongsTo
    {
        return $this->belongsTo(GoodsReceipt::class);
    }

    public function scopeAvailable(Builder $query): Builder
    {
        return $query->where('remaining_qty', '>', 0);
    }

    public function scopeOutright(Builder $query): Builder
    {
        return $query->where('ownership_type', 'OUTRIGHT');
    }

    /**
     * FIFO ordering: earliest received batch first.
     */
    public function scopeFifo(Builder $query): Builder
    {
        return $query->orderBy('received_at', 'asc')->orderBy('id', 'asc');
    }

    /**
     * FEFO ordering: earliest expiration date first (null expiration last), then FIFO.
     */
    public function scopeFefo(Builder $query): Builder
    {
        return $query->orderByRaw('CASE WHEN expired_date IS NULL THEN 1 ELSE 0 END ASC')
            ->orderBy('expired_date', 'asc')
            ->orderBy('received_at', 'asc')
            ->orderBy('id', 'asc');
    }
}
