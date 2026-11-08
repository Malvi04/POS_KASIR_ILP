<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DebtMutation extends Model
{
    use HasFactory;

    protected $fillable = [
        'member_id',
        'member_nik',
        'transaction_id',
        'invoice_number',
        'branch',
        'mutation_type',
        'amount',
        'previous_debt',
        'new_debt',
        'previous_limit',
        'new_limit',
        'notes',
        'processed_by',
    ];

    protected $casts = [
        'amount' => 'float',
        'previous_debt' => 'float',
        'new_debt' => 'float',
        'previous_limit' => 'float',
        'new_limit' => 'float',
        'created_at' => 'datetime',
    ];

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }
}
