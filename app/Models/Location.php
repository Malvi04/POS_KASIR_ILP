<?php

namespace App\Models;

use App\Traits\HasUuidV7;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Location extends Model
{
    use HasUuidV7;

    protected $fillable = [
        'uuid',
        'code',
        'name',
        'type', // STORE, WAREHOUSE, FACTORY, PLANTATION
        'address',
        'manager',
        'manager_email',
        'phone',
        'status',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_location_access');
    }

    public function purchases(): HasMany
    {
        return $this->hasMany(Purchase::class);
    }

    public function stockTransfersFrom(): HasMany
    {
        return $this->hasMany(StockTransfer::class, 'from_location_id');
    }

    public function stockTransfersTo(): HasMany
    {
        return $this->hasMany(StockTransfer::class, 'to_location_id');
    }

    public function productStocks(): HasMany
    {
        return $this->hasMany(ProductStock::class);
    }

    public function stockBatches(): HasMany
    {
        return $this->hasMany(StockBatch::class);
    }

    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    public function terminals(): HasMany
    {
        return $this->hasMany(PosTerminal::class, 'branch_id');
    }

    public function scopeStores($query)
    {
        return $query->where('type', '!=', 'WAREHOUSE');
    }

    public function scopeWarehouses($query)
    {
        return $query->where('type', 'WAREHOUSE');
    }
}
