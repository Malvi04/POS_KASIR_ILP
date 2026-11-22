<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'id_produk',
        'sku',
        'kode_barcode',
        'kode_produk',
        'qty_produk',
        'nama_produk',
        'satuan_unit',
        'harga_beli',
        'harga_jual_umum',
        'harga_jual_member',
        'produk_disc',
        'minimum_stock',
        'ppn_out',
        'ppn_chk',
        'harga_beli_terakhir',
        'status_barang',
        'kode_suplier',
        'manufacturing_date',
        'exp_date',
        'ilp_id',
        'barcode',
        'name',
        'brand',
        'category',
        'unit',
        'cost_price',
        'last_cost_price',
        'price',
        'member_price',
        'min_qty1',
        'min_qty2',
        'min_qty3',
        'harga_level1',
        'stock',
        'min_stock',
        'reorder_point',
        'is_taxable',
        'tax_rate',
        'expired_date',
        'clearance_discount_percent',
        'markup_percent',
        'status',
        'ko_sup',
        'act',
        'masa',
        'dump',
        'is_consignment',
        'supplier_id',
        'commission_percent',
        'rack_location',
        'extra_attributes',
    ];

    protected $casts = [
        'qty_produk' => 'float',
        'harga_beli' => 'float',
        'harga_jual_umum' => 'float',
        'harga_jual_member' => 'float',
        'produk_disc' => 'float',
        'minimum_stock' => 'integer',
        'ppn_out' => 'float',
        'harga_beli_terakhir' => 'float',
        'manufacturing_date' => 'date',
        'exp_date' => 'date',
        'cost_price' => 'float',
        'last_cost_price' => 'float',
        'price' => 'float',
        'member_price' => 'float',
        'min_qty1' => 'float',
        'min_qty2' => 'float',
        'min_qty3' => 'float',
        'harga_level1' => 'float',
        'stock' => 'integer',
        'min_stock' => 'integer',
        'reorder_point' => 'integer',
        'is_taxable' => 'boolean',
        'tax_rate' => 'float',
        'expired_date' => 'date',
        'clearance_discount_percent' => 'float',
        'markup_percent' => 'float',
        'is_consignment' => 'boolean',
        'commission_percent' => 'float',
        'extra_attributes' => 'array',
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

    protected static function booted(): void
    {
        static::saving(function (Product $product) {
            // Bidirectional synchronization between 19 standard columns and legacy columns
            if ($product->isDirty('nama_produk') && !$product->isDirty('name')) {
                $product->name = $product->nama_produk;
            } elseif ($product->isDirty('name') && !$product->isDirty('nama_produk')) {
                $product->nama_produk = $product->name;
            }

            if ($product->isDirty('kode_barcode') && !$product->isDirty('barcode')) {
                $product->barcode = $product->kode_barcode;
            } elseif ($product->isDirty('barcode') && !$product->isDirty('kode_barcode')) {
                $product->kode_barcode = $product->barcode;
            }

            if ($product->isDirty('kode_produk') && !$product->isDirty('sku')) {
                $product->sku = $product->kode_produk;
            } elseif ($product->isDirty('sku') && !$product->isDirty('kode_produk')) {
                $product->kode_produk = $product->sku;
            }

            if ($product->isDirty('qty_produk') && !$product->isDirty('stock')) {
                $product->stock = (int) $product->qty_produk;
            } elseif ($product->isDirty('stock') && !$product->isDirty('qty_produk')) {
                $product->qty_produk = $product->stock;
            }

            if ($product->isDirty('satuan_unit') && !$product->isDirty('unit')) {
                $product->unit = $product->satuan_unit;
            } elseif ($product->isDirty('unit') && !$product->isDirty('satuan_unit')) {
                $product->satuan_unit = $product->unit;
            }

            if ($product->isDirty('harga_beli') && !$product->isDirty('cost_price')) {
                $product->cost_price = $product->harga_beli;
            } elseif ($product->isDirty('cost_price') && !$product->isDirty('harga_beli')) {
                $product->harga_beli = $product->cost_price;
            }

            if ($product->isDirty('harga_jual_umum') && !$product->isDirty('price')) {
                $product->price = $product->harga_jual_umum;
            } elseif ($product->isDirty('price') && !$product->isDirty('harga_jual_umum')) {
                $product->harga_jual_umum = $product->price;
            }

            if ($product->isDirty('harga_jual_member') && !$product->isDirty('member_price')) {
                $product->member_price = $product->harga_jual_member;
            } elseif ($product->isDirty('member_price') && !$product->isDirty('harga_jual_member')) {
                $product->harga_jual_member = $product->member_price;
            }

            if ($product->isDirty('produk_disc') && !$product->isDirty('clearance_discount_percent')) {
                $product->clearance_discount_percent = $product->produk_disc;
            } elseif ($product->isDirty('clearance_discount_percent') && !$product->isDirty('produk_disc')) {
                $product->produk_disc = $product->clearance_discount_percent;
            }

            if ($product->isDirty('minimum_stock') && !$product->isDirty('min_stock')) {
                $product->min_stock = (int) $product->minimum_stock;
            } elseif ($product->isDirty('min_stock') && !$product->isDirty('minimum_stock')) {
                $product->minimum_stock = $product->min_stock;
            }

            if ($product->isDirty('ppn_out') && !$product->isDirty('tax_rate')) {
                $product->tax_rate = $product->ppn_out;
            } elseif ($product->isDirty('tax_rate') && !$product->isDirty('ppn_out')) {
                $product->ppn_out = $product->tax_rate;
            }

            if ($product->isDirty('ppn_chk')) {
                $product->is_taxable = in_array(strtoupper((string) $product->ppn_chk), ['Y', '1', 'TRUE']);
            } elseif ($product->isDirty('is_taxable')) {
                $product->ppn_chk = $product->is_taxable ? 'Y' : 'N';
            }

            if ($product->isDirty('harga_beli_terakhir') && !$product->isDirty('last_cost_price')) {
                $product->last_cost_price = $product->harga_beli_terakhir;
            } elseif ($product->isDirty('last_cost_price') && !$product->isDirty('harga_beli_terakhir')) {
                $product->harga_beli_terakhir = $product->last_cost_price;
            }

            if ($product->isDirty('status_barang') && !$product->isDirty('status')) {
                $product->status = $product->status_barang;
            } elseif ($product->isDirty('status') && !$product->isDirty('status_barang')) {
                $product->status_barang = $product->status;
            }

            if ($product->isDirty('kode_suplier') && !$product->isDirty('ko_sup')) {
                $product->ko_sup = $product->kode_suplier;
            } elseif ($product->isDirty('ko_sup') && !$product->isDirty('kode_suplier')) {
                $product->kode_suplier = $product->ko_sup;
            }

            if ($product->isDirty('exp_date') && !$product->isDirty('expired_date')) {
                $product->expired_date = $product->exp_date;
            } elseif ($product->isDirty('expired_date') && !$product->isDirty('exp_date')) {
                $product->exp_date = $product->expired_date;
            }

            if ($product->isDirty('id_produk') && !$product->isDirty('ilp_id')) {
                $product->ilp_id = $product->id_produk;
            } elseif ($product->isDirty('ilp_id') && !$product->isDirty('id_produk')) {
                $product->id_produk = $product->ilp_id;
            }

            // Fallback ensure both pairs are never empty
            $product->name = $product->name ?? $product->nama_produk ?? 'Produk';
            $product->nama_produk = $product->nama_produk ?? $product->name;
            $product->sku = $product->sku ?? $product->kode_produk ?? '';
            $product->kode_produk = $product->kode_produk ?? $product->sku;
            $product->barcode = $product->barcode ?? $product->kode_barcode ?? $product->sku;
            $product->kode_barcode = $product->kode_barcode ?? $product->barcode;
            $product->stock = $product->stock ?? (int) ($product->qty_produk ?? 0);
            $product->qty_produk = $product->qty_produk ?? $product->stock;
            $product->unit = $product->unit ?? $product->satuan_unit ?? 'Pcs';
            $product->satuan_unit = $product->satuan_unit ?? $product->unit;
            $product->cost_price = $product->cost_price ?? $product->harga_beli ?? 0;
            $product->harga_beli = $product->harga_beli ?? $product->cost_price;
            $product->price = $product->price ?? $product->harga_jual_umum ?? 0;
            $product->harga_jual_umum = $product->harga_jual_umum ?? $product->price;
            $product->member_price = $product->member_price ?? $product->harga_jual_member ?? $product->price;
            $product->harga_jual_member = $product->harga_jual_member ?? $product->member_price;
            $product->min_stock = $product->min_stock ?? (int) ($product->minimum_stock ?? 5);
            $product->minimum_stock = $product->minimum_stock ?? $product->min_stock;
            $product->status = $product->status ?? $product->status_barang ?? 'TERSEDIA';
            $product->status_barang = $product->status_barang ?? $product->status;
        });
    }

    public function supplierProducts(): HasMany
    {
        return $this->hasMany(SupplierProduct::class);
    }

    public function supplyingSuppliers(): BelongsToMany
    {
        return $this->belongsToMany(Supplier::class, 'supplier_products')
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

    public function units(): HasMany
    {
        return $this->hasMany(ProductUnit::class);
    }

    public function stockBatches(): HasMany
    {
        return $this->hasMany(StockBatch::class);
    }

    public function productStocks(): HasMany
    {
        return $this->hasMany(ProductStock::class);
    }

    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    public function stockAtLocation(int $locationId): ?ProductStock
    {
        if ($this->relationLoaded('productStocks')) {
            return $this->productStocks->firstWhere('location_id', $locationId);
        }

        return $this->productStocks()->where('location_id', $locationId)->first();
    }

    public function priceHistories(): HasMany
    {
        return $this->hasMany(PurchasePriceHistory::class)->orderByDesc('recorded_at')->orderByDesc('id');
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
            return round(((float) ($this->price ?? 0)) * ($discountPct / 100), 2);
        }
        return 0.0;
    }

    public function getFinalPriceAttribute(): float
    {
        $discount = $this->effective_clearance_discount;
        return max(0, ((float) ($this->price ?? 0)) - $discount);
    }

    public function getFinalMemberPriceAttribute(): float
    {
        $base = (float) ($this->member_price ?? $this->price ?? 0);
        if ($this->is_critical_expired) {
            $discount = round($base * ($this->effective_discount_percent / 100), 2);
            return max(0, $base - $discount);
        }
        return $base;
    }

    public function getHargaLevel1Attribute($value): ?float
    {
        if ($value !== null && (float) $value > 0) {
            return (float) $value;
        }
        return $this->member_price !== null ? (float) $this->member_price : (float) $this->price;
    }
}
