<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupplierProduct extends Model
{
    public const STATUS_AKTIF = 'AKTIF';
    public const STATUS_TIDAK_AKTIF = 'TIDAK_AKTIF';

    protected $fillable = [
        'supplier_id',
        'product_id',
        'first_purchase_at',
        'last_purchase_at',
        'last_purchase_price',
        'last_purchase_id',
        'total_transactions',
        'supply_status',
    ];

    protected $casts = [
        'first_purchase_at' => 'datetime',
        'last_purchase_at' => 'datetime',
        'last_purchase_price' => 'decimal:2',
        'total_transactions' => 'integer',
    ];

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function lastPurchase(): BelongsTo
    {
        return $this->belongsTo(Purchase::class, 'last_purchase_id');
    }
}
