<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConsignmentSettlement extends Model
{
    use HasFactory;

    protected $fillable = [
        'settlement_number',
        'supplier_id',
        'period_start',
        'period_end',
        'gross_sales',
        'coop_commission',
        'umkm_net_share',
        'items_sold_count',
        'payment_status',
        'paid_at',
        'notes',
    ];

    protected $casts = [
        'period_start' => 'date',
        'period_end' => 'date',
        'gross_sales' => 'float',
        'coop_commission' => 'float',
        'umkm_net_share' => 'float',
        'items_sold_count' => 'integer',
        'paid_at' => 'datetime',
    ];

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }
}
