<?php

namespace App\Models;

use App\Traits\HasUuidV7;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Supplier extends Model
{
    use HasFactory, HasUuidV7;

    public const TYPE_REGULAR = 'REGULAR';
    public const TYPE_CONSIGNMENT = 'CONSIGNMENT';

    protected $fillable = [
        'uuid',
        'code',
        'name',
        'company_name',
        'owner_name',
        'pic_name',
        'phone',
        'email',
        'address',
        'city',
        'category',
        'business_categories',
        'commission_percent',
        'settlement_period',
        'bank_name',
        'bank_account',
        'status',
        'supplier_type',
        'notes',
    ];

    protected $casts = [
        'commission_percent' => 'float',
        'business_categories' => 'array',
    ];

    /**
     * Direct consignment products (legacy/consignment supplier_id on products table).
     */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    /**
     * Automated supplier-product supply relations built from confirmed purchase receipts.
     */
    public function supplierProducts(): HasMany
    {
        return $this->hasMany(SupplierProduct::class);
    }

    /**
     * Products supplied by this supplier through regular purchases.
     */
    public function suppliedProducts(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'supplier_products')
            ->withPivot([
                'first_purchase_at',
                'last_purchase_at',
                'last_purchase_price',
                'last_purchase_id',
                'total_transactions',
                'supply_status',
            ])
            ->withTimestamps();
    }

    public function purchases(): HasMany
    {
        return $this->hasMany(Purchase::class)->orderByDesc('purchase_date')->orderByDesc('id');
    }

    public function priceHistories(): HasMany
    {
        return $this->hasMany(PurchasePriceHistory::class)->orderByDesc('recorded_at')->orderByDesc('id');
    }

    public function settlements(): HasMany
    {
        return $this->hasMany(ConsignmentSettlement::class)->orderBy('id', 'desc');
    }

    public function scopeRegular(Builder $query): Builder
    {
        return $query->where(function (Builder $q) {
            $q->where('supplier_type', self::TYPE_REGULAR)
                ->orWhereNull('supplier_type');
        })->where('code', 'not like', 'UMKM-%');
    }

    public function scopeConsignment(Builder $query): Builder
    {
        return $query->where(function (Builder $q) {
            $q->where('supplier_type', self::TYPE_CONSIGNMENT)
                ->orWhere('code', 'like', 'UMKM-%');
        });
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'AKTIF');
    }

    public function getEffectivePicAttribute(): ?string
    {
        return $this->pic_name ?: $this->owner_name;
    }

    public function getBusinessCategoriesListAttribute(): array
    {
        $list = is_array($this->business_categories) ? $this->business_categories : [];
        $cleaned = collect($list)
            ->map(fn ($v) => trim((string) $v))
            ->filter(fn ($v) => $v !== '')
            ->unique(fn ($v) => mb_strtolower($v))
            ->values()
            ->all();

        if (!empty($cleaned)) {
            return $cleaned;
        }

        $fallback = trim((string) ($this->category ?? ''));
        if ($fallback === '' || strcasecmp($fallback, 'Makanan Ringan') === 0) {
            return [];
        }

        return collect(explode(',', $fallback))
            ->map(fn ($v) => trim($v))
            ->filter(fn ($v) => $v !== '')
            ->unique(fn ($v) => mb_strtolower($v))
            ->values()
            ->all();
    }

    public function getCategorySummaryAttribute(): string
    {
        $list = $this->business_categories_list;
        if (!empty($list)) {
            return implode(', ', $list);
        }

        return $this->category ?: 'Supplier Umum';
    }
}
