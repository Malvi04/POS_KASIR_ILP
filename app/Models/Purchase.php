<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Purchase extends Model
{
    public const STATUS_DRAFT = 'DRAFT';
    public const STATUS_MENUNGGU = 'MENUNGGU_PENERIMAAN';
    public const STATUS_SEBAGIAN = 'DITERIMA_SEBAGIAN';
    public const STATUS_SELESAI = 'SELESAI';
    public const STATUS_DIBATALKAN = 'DIBATALKAN';

    protected $fillable = [
        'purchase_number',
        'supplier_id',
        'supplier_invoice_number',
        'location_id',
        'warehouse_name',
        'created_by',
        'purchase_date',
        'status',
        'total_amount',
        'notes',
    ];

    protected $casts = [
        'purchase_date' => 'date',
        'total_amount' => 'decimal:2',
    ];

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseItem::class);
    }

    public function goodsReceipts(): HasMany
    {
        return $this->hasMany(GoodsReceipt::class)->orderByDesc('received_at')->orderByDesc('id');
    }

    public function priceHistories(): HasMany
    {
        return $this->hasMany(PurchasePriceHistory::class);
    }

    public function stockBatches(): HasMany
    {
        return $this->hasMany(StockBatch::class);
    }

    /**
     * Normalize legacy status aliases (ORDERED/PENDING -> MENUNGGU_PENERIMAAN, RECEIVED -> SELESAI).
     */
    public function getNormalizedStatusAttribute(): string
    {
        return match (strtoupper((string) $this->status)) {
            'DRAFT' => self::STATUS_DRAFT,
            'PENDING', 'ORDERED', 'MENUNGGU_PENERIMAAN' => self::STATUS_MENUNGGU,
            'DITERIMA_SEBAGIAN', 'PARTIAL' => self::STATUS_SEBAGIAN,
            'RECEIVED', 'SELESAI', 'COMPLETED' => self::STATUS_SELESAI,
            'CANCELLED', 'DIBATALKAN' => self::STATUS_DIBATALKAN,
            default => strtoupper((string) $this->status),
        };
    }
}
