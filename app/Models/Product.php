<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'sku',
        'barcode',
        'name',
        'category',
        'unit',
        'cost_price',
        'price',
        'member_price',
        'stock',
        'min_stock',
        'is_taxable',
        'tax_rate',
        'expired_date',
        'clearance_discount_percent',
        'status',
        'is_consignment',
        'supplier_id',
        'commission_percent',
        'rack_location',
    ];

    protected $casts = [
        'cost_price' => 'float',
        'price' => 'float',
        'member_price' => 'float',
        'stock' => 'integer',
        'min_stock' => 'integer',
        'is_taxable' => 'boolean',
        'tax_rate' => 'float',
        'expired_date' => 'date',
        'clearance_discount_percent' => 'float',
        'is_consignment' => 'boolean',
        'commission_percent' => 'float',
    ];

    protected $appends = [
        'is_near_expired',
        'is_warning_expired',
        'is_critical_expired',
        'is_expired',
        'is_low_stock',
        'is_out_of_stock',
        'days_until_expiration',
        'effective_discount_percent',
        'effective_clearance_discount',
        'final_price',
        'final_member_price',
        'stock_alert_level',
        'expiration_alert_level',
    ];

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function units(): HasMany
    {
        return $this->hasMany(ProductUnit::class);
    }

    /**
     * Convert given unit quantity to base retail quantity (e.g. 2 Dus -> 48 Pcs).
     */
    public function convertToBaseUnits(string $unitName, int $qty): int
    {
        if (strcasecmp($this->unit, $unitName) === 0) {
            return $qty;
        }

        $productUnit = $this->units()->whereRaw('LOWER(unit_name) = ?', [strtolower($unitName)])->first();
        if ($productUnit && $productUnit->conversion_factor > 0) {
            return $qty * $productUnit->conversion_factor;
        }

        return $qty;
    }

    public function getDaysUntilExpirationAttribute(): ?int
    {
        if (!$this->expired_date) {
            return null;
        }

        $today = Carbon::today();
        $exp = Carbon::parse($this->expired_date)->startOfDay();
        return (int) $today->diffInDays($exp, false);
    }

    /**
     * Yellow Alert: Mendekati kadaluwarsa H-30 hingga H-15.
     */
    public function getIsWarningExpiredAttribute(): bool
    {
        $days = $this->days_until_expiration;
        return $days !== null && $days >= 15 && $days <= 30;
    }

    /**
     * Red Alert: Sangat kritis H-14 ke bawah (otomatis memicu diskon di kasir).
     */
    public function getIsCriticalExpiredAttribute(): bool
    {
        $days = $this->days_until_expiration;
        return $days !== null && $days >= 0 && $days <= 14;
    }

    public function getIsNearExpiredAttribute(): bool
    {
        return $this->is_critical_expired;
    }

    public function getIsExpiredAttribute(): bool
    {
        $days = $this->days_until_expiration;
        return $days !== null && $days < 0;
    }

    public function getExpirationAlertLevelAttribute(): string
    {
        if ($this->is_expired) return 'EXPIRED'; // Hitam/Ungu Kadaluwarsa
        if ($this->is_critical_expired) return 'CRITICAL'; // Merah (<= H-14)
        if ($this->is_warning_expired) return 'WARNING'; // Kuning (H-15 s/d H-30)
        return 'NORMAL'; // Hijau / Aman
    }

    /**
     * Stock Threshold Alert
     */
    public function getIsLowStockAttribute(): bool
    {
        return $this->stock > 0 && $this->stock <= $this->min_stock;
    }

    public function getIsOutOfStockAttribute(): bool
    {
        return $this->stock <= 0;
    }

    public function getStockAlertLevelAttribute(): string
    {
        if ($this->is_out_of_stock) return 'HABIS';
        if ($this->is_low_stock) return 'STOK_RENDAH';
        return 'TERSEDIA';
    }

    public function getEffectiveDiscountPercentAttribute(): float
    {
        if ($this->is_critical_expired) {
            return (float) ($this->clearance_discount_percent > 0 ? $this->clearance_discount_percent : 20.0);
        }
        return 0.0;
    }

    public function getEffectiveClearanceDiscountAttribute(): float
    {
        if ($this->is_critical_expired) {
            $discountPct = $this->effective_discount_percent;
            return round($this->price * ($discountPct / 100), 2);
        }
        return 0.0;
    }

    public function getFinalPriceAttribute(): float
    {
        $discount = $this->effective_clearance_discount;
        return max(0, $this->price - $discount);
    }

    public function getFinalMemberPriceAttribute(): float
    {
        $base = $this->member_price ?? $this->price;
        if ($this->is_critical_expired) {
            $discount = round($base * ($this->effective_discount_percent / 100), 2);
            return max(0, $base - $discount);
        }
        return $base;
    }
}
