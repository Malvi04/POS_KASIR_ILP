<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StoreReturnItem extends Model
{
    protected $fillable = [
        'store_return_id',
        'product_id',
        'qty',
        'disposition',
        'claim_price',
    ];

    protected $casts = [
        'qty' => 'decimal:2',
        'claim_price' => 'decimal:2',
    ];

    public function storeReturn(): BelongsTo
    {
        return $this->belongsTo(StoreReturnHeader::class, 'store_return_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
