<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CashierShift extends Model
{
    use HasFactory;

    protected $fillable = [
        'shift_code',
        'terminal_code',
        'user_id',
        'cashier_nik',
        'cashier_name',
        'opening_cash',
        'cash_sales_total',
        'qris_sales_total',
        'credit_sales_total',
        'total_sales',
        'transaction_count',
        'cash_in',
        'cash_out',
        'expected_cash',
        'actual_cash',
        'variance',
        'variance_status',
        'closing_notes',
        'status',
        'opened_at',
        'closed_at',
    ];

    protected $casts = [
        'opening_cash' => 'float',
        'cash_sales_total' => 'float',
        'qris_sales_total' => 'float',
        'credit_sales_total' => 'float',
        'total_sales' => 'float',
        'transaction_count' => 'integer',
        'cash_in' => 'float',
        'cash_out' => 'float',
        'expected_cash' => 'float',
        'actual_cash' => 'float',
        'variance' => 'float',
        'opened_at' => 'datetime',
        'closed_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class, 'shift_id');
    }

    public function heldBills(): HasMany
    {
        return $this->hasMany(HeldBill::class, 'shift_id');
    }

    public function calculateExpectedCash(): float
    {
        return round($this->opening_cash + $this->cash_sales_total + $this->cash_in - $this->cash_out, 2);
    }

    public function closeShift(float $actualCash, ?string $notes = null): void
    {
        $expected = $this->calculateExpectedCash();
        $variance = round($actualCash - $expected, 2);

        $status = 'SESUAI';
        if ($variance < 0) {
            $status = 'KURANG';
        } elseif ($variance > 0) {
            $status = 'LEBIH';
        }

        $this->update([
            'expected_cash' => $expected,
            'actual_cash' => $actualCash,
            'variance' => $variance,
            'variance_status' => $status,
            'closing_notes' => $notes,
            'status' => 'CLOSED',
            'closed_at' => now(),
        ]);
    }
}
