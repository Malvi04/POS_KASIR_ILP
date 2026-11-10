<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockOpnameItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'stock_opname_id',
        'product_id',
        'product_sku',
        'product_name',
        'system_stock',
        'physical_stock',
        'variance',
        'unit_cost',
        'variance_value',
        'variance_reason',
        'notes',
        'is_audited',
    ];

    protected $casts = [
        'system_stock' => 'integer',
        'physical_stock' => 'integer',
        'variance' => 'integer',
        'unit_cost' => 'float',
        'variance_value' => 'float',
        'is_audited' => 'boolean',
    ];

    public function stockOpname(): BelongsTo
    {
        return $this->belongsTo(StockOpname::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
