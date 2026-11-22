<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class Member extends Model
{
    use HasFactory;

    protected $fillable = [
        'customer_id',
        'member_code',
        'kode_out',
        'nik',
        'name',
        'department',
        'division',
        'address',
        'phone',
        'fax',
        'contact',
        'tempo_bayar',
        'idtk',
        'credit_limit',
        'no_rekening',
        'branch',
        'join_date',
        'current_debt',
        'status',
        'notes',
    ];

    protected $casts = [
        'credit_limit' => 'float',
        'current_debt' => 'float',
        'join_date' => 'date',
    ];

    protected $appends = [
        'remaining_credit',
        'is_active',
        'debt_utilization_percent',
        'is_over_limit',
        'excess_debt_amount',
        'debt_status',
    ];

    public function debtMutations(): HasMany
    {
        return $this->hasMany(DebtMutation::class)->orderBy('id', 'desc');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class)->orderBy('id', 'desc');
    }

    public function getRemainingCreditAttribute(): float
    {
        return max(0, (float) $this->credit_limit - (float) $this->current_debt);
    }

    public function getIsActiveAttribute(): bool
    {
        return strtoupper($this->status ?? '') === 'AKTIF';
    }

    public function getIsOverLimitAttribute(): bool
    {
        return (float) $this->current_debt > (float) $this->credit_limit;
    }

    public function getExcessDebtAmountAttribute(): float
    {
        return max(0, (float) $this->current_debt - (float) $this->credit_limit);
    }

    public function getDebtStatusAttribute(): string
    {
        if ((float) $this->current_debt <= 0) {
            return 'LUNAS';
        }
        if ((float) $this->current_debt > (float) $this->credit_limit) {
            return 'MELEBIHI_LIMIT';
        }
        return 'DALAM_LIMIT';
    }

    public function getDebtUtilizationPercentAttribute(): float
    {
        if ($this->credit_limit <= 0) {
            return $this->current_debt > 0 ? 100 : 0;
        }
        return round(($this->current_debt / $this->credit_limit) * 100, 1);
    }

    public function checkCreditEligibility(float $amount): array
    {
        if (!$this->is_active) {
            return [
                'eligible' => false,
                'is_over_limit' => false,
                'debt_status' => 'DIBLOKIR',
                'reason' => 'Status keanggotaan tidak aktif atau diblokir oleh bagian keuangan koperasi.',
                'remaining_credit' => $this->remaining_credit,
                'excess_amount' => 0,
            ];
        }

        $projectedDebt = (float) $this->current_debt + $amount;
        $isOverLimit = $projectedDebt > (float) $this->credit_limit;
        $excessAmount = $isOverLimit ? ($projectedDebt - (float) $this->credit_limit) : 0;

        if ($isOverLimit) {
            return [
                'eligible' => true,
                'is_over_limit' => true,
                'debt_status' => 'MELEBIHI_LIMIT',
                'reason' => "Total belanja melebihi batas plafon kredit (Kelebihan: Rp " . number_format($excessAmount, 0, ',', '.') . "). Transaksi tetap dapat dilanjutkan dengan status 'MELEBIHI LIMIT PIUTANG'.",
                'remaining_credit' => $this->remaining_credit,
                'projected_remaining' => 0,
                'excess_amount' => $excessAmount,
            ];
        }

        return [
            'eligible' => true,
            'is_over_limit' => false,
            'debt_status' => 'DALAM_LIMIT',
            'reason' => 'Plafon kredit mencukupi.',
            'remaining_credit' => $this->remaining_credit,
            'projected_remaining' => max(0, (float) $this->credit_limit - $projectedDebt),
            'excess_amount' => 0,
        ];
    }

    /**
     * Atomically record a kasbon POS transaction deduction and log audit mutation.
     */
    public function recordKasbon(
        float $amount,
        ?string $invoiceNumber = null,
        ?int $transactionId = null,
        string $branch = 'Cabang Pusat',
        string $processedBy = 'Kasir Sistem',
        string $notes = 'Transaksi Kasbon POS Potong Gaji',
        bool $isOverLimit = false,
        ?string $debtStatus = null
    ): DebtMutation {
        $previousDebt = (float) $this->current_debt;
        $newDebt = $previousDebt + $amount;

        $this->current_debt = $newDebt;
        $this->save();

        if ($debtStatus === null) {
            $debtStatus = ($newDebt > (float) $this->credit_limit) ? 'MELEBIHI_LIMIT' : 'DALAM_LIMIT';
        }
        if (!$isOverLimit && $newDebt > (float) $this->credit_limit) {
            $isOverLimit = true;
        }

        return DebtMutation::create([
            'member_id' => $this->id,
            'member_nik' => $this->nik,
            'transaction_id' => $transactionId,
            'invoice_number' => $invoiceNumber,
            'branch' => $branch,
            'mutation_type' => 'KASBON_POS',
            'amount' => $amount,
            'previous_debt' => $previousDebt,
            'new_debt' => $newDebt,
            'previous_limit' => (float) $this->credit_limit,
            'new_limit' => (float) $this->credit_limit,
            'is_over_limit' => $isOverLimit,
            'debt_status' => $debtStatus,
            'notes' => $notes,
            'processed_by' => $processedBy,
        ]);
    }

    /**
     * Atomically adjust monthly credit limit with audit trail.
     */
    public function adjustCreditLimit(
        float $newLimit,
        string $processedBy = 'Manager Koperasi',
        string $notes = 'Penyesuaian Plafon Kredit Bulanan'
    ): DebtMutation {
        $previousLimit = (float) $this->credit_limit;
        $this->credit_limit = $newLimit;
        $this->save();

        return DebtMutation::create([
            'member_id' => $this->id,
            'member_nik' => $this->nik,
            'transaction_id' => null,
            'invoice_number' => null,
            'branch' => $this->branch ?? 'Cabang Pusat',
            'mutation_type' => 'LIMIT_ADJUSTMENT',
            'amount' => abs($newLimit - $previousLimit),
            'previous_debt' => (float) $this->current_debt,
            'new_debt' => (float) $this->current_debt,
            'previous_limit' => $previousLimit,
            'new_limit' => $newLimit,
            'notes' => $notes,
            'processed_by' => $processedBy,
        ]);
    }

    /**
     * Atomically record settlement / repayment / payroll deduction.
     */
    public function recordSettlement(
        float $amount,
        string $processedBy = 'Finance HRD PT ILP',
        string $type = 'PAYMENT_SETTLEMENT',
        string $notes = 'Pelunasan / Pemotongan Kasbon Anggota'
    ): DebtMutation {
        $previousDebt = (float) $this->current_debt;
        $newDebt = max(0, $previousDebt - $amount);

        $this->current_debt = $newDebt;
        $this->save();

        return DebtMutation::create([
            'member_id' => $this->id,
            'member_nik' => $this->nik,
            'transaction_id' => null,
            'invoice_number' => null,
            'branch' => $this->branch ?? 'Pusat',
            'mutation_type' => $type,
            'amount' => $amount,
            'previous_debt' => $previousDebt,
            'new_debt' => $newDebt,
            'previous_limit' => (float) $this->credit_limit,
            'new_limit' => (float) $this->credit_limit,
            'notes' => $notes,
            'processed_by' => $processedBy,
        ]);
    }
}
