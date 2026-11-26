<?php

namespace App\Services;

use App\Models\Location;
use App\Models\PosTerminal;
use App\Models\Transaction;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PosInvoiceGeneratorService
{
    /**
     * Resolve authoritative local node branch & terminal identity when POS_NODE_ROLE=local.
     *
     * @return array{is_local: bool, terminal_code: string, branch_code: string, location: ?Location}
     */
    public function resolveLocalIdentity(
        LocationStockService $stockService,
        ?int $fallbackLocationId = null,
        ?string $fallbackBranch = null,
        ?string $fallbackTerminalCode = null,
        ?User $user = null
    ): array {
        $isLocal = strtolower((string) config('pos_sync.node_role', 'central')) === 'local';

        $configuredBranchUuid = trim((string) config('pos_sync.branch_uuid', ''));
        $configuredBranchCode = strtoupper(trim((string) config('pos_sync.branch_code', '')));
        $configuredTerminalCode = strtoupper(trim((string) config('pos_sync.terminal_code', '')));

        $location = null;
        if ($isLocal) {
            if ($configuredBranchUuid !== '' && Str::isUuid($configuredBranchUuid)) {
                $location = Location::where('uuid', $configuredBranchUuid)
                    ->where('type', '!=', 'WAREHOUSE')
                    ->first();
            }
            if (!$location && $configuredBranchCode !== '') {
                $location = $stockService->resolveLocation(null, $configuredBranchCode, null, null, 'STORE');
            }
        }

        if (!$location) {
            $location = $stockService->resolveLocation(
                $fallbackLocationId,
                $fallbackBranch,
                $fallbackTerminalCode,
                $user,
                'STORE'
            );
        }

        $effectiveTerminalCode = ($isLocal && $configuredTerminalCode !== '')
            ? $configuredTerminalCode
            : strtoupper(trim((string) ($fallbackTerminalCode ?: 'POS-01')));

        $effectiveBranchCode = ($isLocal && $configuredBranchCode !== '')
            ? (preg_replace('/[^A-Z0-9]/', '', $configuredBranchCode) ?: PosTerminal::canonicalBranchCode($location))
            : PosTerminal::canonicalBranchCode($location);

        return [
            'is_local' => $isLocal,
            'terminal_code' => $effectiveTerminalCode,
            'branch_code' => $effectiveBranchCode,
            'location' => $location,
        ];
    }

    /**
     * Generate a collision-free invoice number.
     * - On `local` nodes: `INV-{BRANCH_CODE}-{TERMINAL_CODE}-{YYYYMMDD}-{SEQUENCE}`
     *   (e.g. `INV-PST-KSR01-20261126-0001`, guaranteed distinct across PST-KSR01, PST-KSR02, CBG1-KSR01, CBG2-KSR01).
     * - On `central` nodes: preserves legacy `TRX-{YYYYMMDD}-{SEQ}-{RAND}` format for 100% backward compatibility.
     */
    public function generate(
        ?Location $location = null,
        ?string $terminalCode = null,
        ?CarbonInterface $date = null
    ): string {
        $isLocal = strtolower((string) config('pos_sync.node_role', 'central')) === 'local';
        $datePrefix = ($date ?? now())->format('Ymd');

        if (!$isLocal) {
            $maxId = (int) (Transaction::max('id') ?? 0);
            $randomSuffix = Str::upper(Str::random(3));
            $invoiceNumber = "TRX-{$datePrefix}-" . Str::padLeft((string) ($maxId + 1), 4, '0') . "-{$randomSuffix}";
            while (Transaction::where('invoice_number', $invoiceNumber)->exists()) {
                $maxId++;
                $randomSuffix = Str::upper(Str::random(4));
                $invoiceNumber = "TRX-{$datePrefix}-" . Str::padLeft((string) $maxId, 4, '0') . "-{$randomSuffix}";
            }

            return $invoiceNumber;
        }

        $configuredBranchCode = strtoupper(trim((string) config('pos_sync.branch_code', '')));
        $configuredTerminalCode = strtoupper(trim((string) config('pos_sync.terminal_code', '')));

        $branchCode = $configuredBranchCode !== ''
            ? (preg_replace('/[^A-Z0-9]/', '', $configuredBranchCode) ?: PosTerminal::canonicalBranchCode($location))
            : PosTerminal::canonicalBranchCode($location);

        $rawTerm = $configuredTerminalCode !== '' ? $configuredTerminalCode : (string) ($terminalCode ?: 'KSR01');
        $cleanTerm = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $rawTerm) ?: 'KSR01');

        $prefix = "INV-{$branchCode}-{$cleanTerm}-{$datePrefix}-";

        return DB::transaction(function () use ($prefix) {
            if (DB::getDriverName() === 'pgsql') {
                // Acquire transaction-scoped advisory lock per prefix to prevent parallel checkout sequence collisions
                $lockKey = unpack('l', pack('l', crc32($prefix)))[1];
                DB::select('SELECT pg_advisory_xact_lock(?)', [$lockKey]);
            }

            $latestInvoice = Transaction::query()
                ->where('invoice_number', 'like', $prefix . '%')
                ->orderByDesc('invoice_number')
                ->value('invoice_number');

            $nextSeq = 1;
            if (is_string($latestInvoice) && str_starts_with($latestInvoice, $prefix)) {
                $tail = substr($latestInvoice, strlen($prefix));
                if (preg_match('/^(\d+)/', $tail, $m)) {
                    $nextSeq = ((int) $m[1]) + 1;
                }
            }

            $candidate = $prefix . str_pad((string) $nextSeq, 4, '0', STR_PAD_LEFT);
            while (Transaction::where('invoice_number', $candidate)->exists()) {
                $nextSeq++;
                $candidate = $prefix . str_pad((string) $nextSeq, 4, '0', STR_PAD_LEFT);
            }

            return $candidate;
        });
    }
}
