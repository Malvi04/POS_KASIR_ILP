<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StockOpname extends Model
{
    use HasFactory;

    protected $fillable = [
        'opname_code',
        'title',
        'branch',
        'conducted_by',
        'status',
        'total_items_audited',
        'total_items_variance',
        'total_variance_value',
        'notes',
        'completed_at',
    ];

    protected $casts = [
        'total_items_audited' => 'integer',
        'total_items_variance' => 'integer',
        'total_variance_value' => 'float',
        'completed_at' => 'datetime',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(StockOpnameItem::class);
    }
}
