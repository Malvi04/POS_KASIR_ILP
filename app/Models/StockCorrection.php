<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockCorrection extends Model
{
    use HasFactory;

    protected $table = 'stock_corrections';

    protected $fillable = [
        'correction_code',
        'product_id',
        'branch',
        'type',
        'qty',
        'stock_before',
        'stock_after',
        'reason',
        'user_id',
        'supervisor_name',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'qty' => 'integer',
            'stock_before' => 'integer',
            'stock_after' => 'integer',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
