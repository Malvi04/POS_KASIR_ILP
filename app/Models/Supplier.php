<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Supplier extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'owner_name',
        'phone',
        'address',
        'category',
        'commission_percent',
        'settlement_period',
        'bank_name',
        'bank_account',
        'status',
        'notes',
    ];

    protected $casts = [
        'commission_percent' => 'float',
    ];

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function settlements(): HasMany
    {
        return $this->hasMany(ConsignmentSettlement::class)->orderBy('id', 'desc');
    }
}
