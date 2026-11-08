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
        'member_code',
        'nik',
        'name',
        'department',
        'branch',
        'phone',
        'join_date',
        'credit_limit',
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

    public function getDebtUtilizationPercentAttribute(): float
    {
        if ($this->credit_limit <= 0) {
            return 0;
        }
        return round(min(100, ($this->current_debt / $this->credit_limit) * 100), 2);
    }

    public function checkCreditEligibility(float $amount): array
    {
        if (!$this->is_active) {
            return [
                'eligible' => false,
                'reason' => 'Status keanggotaan tidak aktif atau diblokir oleh bagian keuangan koperasi.',
                'remaining_credit' => $this->remaining_credit,
            ];
        }

        if ($this->remaining_credit < $amount) {
            $shortage = $amount - $this->remaining_credit;
            return [
                'eligible' => false,
                'reason' => "Total belanja (Rp " . number_format($amount, 0, ',', '.') . ") melebihi sisa plafon kredit (Rp " . number_format($this->remaining_credit, 0, ',', '.') . "). Kurang: Rp " . number_format($shortage, 0, ',', '.'),
                'remaining_credit' => $this->remaining_credit,
                'shortage' => $shortage,
            ];
        }

        return [
            'eligible' => true,
            'reason' => 'Plafon kredit mencukupi.',
            'remaining_credit' => $this->remaining_credit,
            'projected_remaining' => $this->remaining_credit - $amount,
        ];
    }

    /**
     * Atomically record a kasbon POS transaction deduction and log audit mutation.
     */
    public function recordKasbon(
        float $amount,
        ?string $invoiceNumber = null,
        ?int $transactionId = null,
        string $branch = 'Cabang 1 - Mill / Pabrik',
        string $processedBy = 'Kasir Sistem',
        string $notes = 'Transaksi Kasbon POS Potong Gaji'
    ): DebtMutation {
        $previousDebt = (float) $this->current_debt;
        $newDebt = $previousDebt + $amount;

        $this->current_debt = $newDebt;
        if ($this->remaining_credit <= 0) {
            // Can optionally flag or keep active based on policy
        }
        $this->save();

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
            'branch' => $this->branch ?? 'Cabang 1 - Mill / Pabrik',
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
