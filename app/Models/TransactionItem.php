<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TransactionItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'transaction_id',
        'product_id',
        'sku',
        'barcode',
        'product_name',
        'unit',
        'unit_price',
        'qty',
        'is_taxable',
        'tax_amount',
        'is_expired_clearance',
        'clearance_discount_percent',
        'discount_amount',
        'total_price',
    ];

    protected $casts = [
        'unit_price' => 'float',
        'qty' => 'integer',
        'is_taxable' => 'boolean',
        'tax_amount' => 'float',
        'is_expired_clearance' => 'boolean',
        'clearance_discount_percent' => 'float',
        'discount_amount' => 'float',
        'total_price' => 'float',
    ];

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
