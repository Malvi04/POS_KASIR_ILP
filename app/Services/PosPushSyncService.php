<?php

namespace App\Services;

use App\Models\Product;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class PosPushSyncService
{
    public const LOCK_KEY = 'pos_sync_push_lock';
    public const LOCK_SECONDS = 120;

    /**
     * Execute background Push Sync from local cashier PostgreSQL to Central Warehouse Server.
     * Protected by `Cache::lock('pos_sync_push_lock', 120)` and supports `$maxBatches` (e.g. 1 batch for UI Monitoring).
     *
     * @return array{
     *     status: string,
     *     message: string,
     *     synced_count: int,
     *     failed_count: int,
     *     batches_processed: int,
     *     pending_remaining: int,
     *     connection_aborted: bool
     * }
     */
    public function sync(?int $batchSize = null, ?int $maxBatches = null, bool $force = false): array
    {
        $nodeRole = strtolower((string) config('pos_sync.node_role', 'central'));
        if ($nodeRole !== 'local' && !$force) {
            return [
                'status' => 'skipped_role',
                'message' => "Instance saat ini berperan sebagai [{$nodeRole}]. Push sync hanya berjalan pada node [local] (gunakan --force untuk memaksa).",
                'synced_count' => 0,
                'failed_count' => 0,
                'batches_processed' => 0,
                'pending_remaining' => 0,
                'connection_aborted' => false,
            ];
        }

        $centralUrl = rtrim((string) config('pos_sync.central_api_url', ''), '/');
        $centralToken = trim((string) config('pos_sync.central_api_token', ''));

        if ($centralUrl === '') {
            return [
                'status' => 'unconfigured',
                'message' => 'CENTRAL_API_URL belum dikonfigurasi.',
                'synced_count' => 0,
                'failed_count' => 0,
                'batches_processed' => 0,
                'pending_remaining' => Transaction::query()->whereNull('synced_at')->count(),
                'connection_aborted' => false,
            ];
        }

        $lock = Cache::lock(self::LOCK_KEY, self::LOCK_SECONDS);
        if (!$lock->get()) {
            return [
                'status' => 'locked',
                'message' => 'Proses push sync sedang berjalan di proses lain (Cache lock aktif).',
                'synced_count' => 0,
                'failed_count' => 0,
                'batches_processed' => 0,
                'pending_remaining' => Transaction::query()->whereNull('synced_at')->count(),
                'connection_aborted' => false,
            ];
        }

        try {
            $effectiveBatchSize = max(1, (int) ($batchSize ?: config('pos_sync.batch_size', 50)));
            $timeout = max(3, (int) config('pos_sync.timeout_seconds', 15));
            $retryTimes = max(1, (int) config('pos_sync.retry_times', 3));
            $retrySleepMs = max(100, (int) config('pos_sync.retry_sleep_ms', 500));

            $pendingTrxCount = Transaction::query()->whereNull('synced_at')->count();
            $hasPendingOperational = $this->hasPendingOperationalRecords();

            if ($pendingTrxCount === 0 && !$hasPendingOperational) {
                return [
                    'status' => 'idle',
                    'message' => 'Tidak ada transaksi baru untuk disinkronkan.',
                    'synced_count' => 0,
                    'failed_count' => 0,
                    'batches_processed' => 0,
                    'pending_remaining' => 0,
                    'connection_aborted' => false,
                ];
            }

            $totalSynced = 0;
            $totalFailed = 0;
            $batchesProcessed = 0;
            $connectionAborted = false;

            if ($pendingTrxCount > 0) {
                Transaction::query()
                ->whereNull('synced_at')
                ->with(['items.product', 'user', 'shift', 'location', 'member'])
                ->orderBy('id')
                ->chunkById($effectiveBatchSize, function (Collection $chunk) use (
                    $centralUrl,
                    $centralToken,
                    $timeout,
                    $retryTimes,
                    $retrySleepMs,
                    $maxBatches,
                    &$totalSynced,
                    &$totalFailed,
                    &$batchesProcessed,
                    &$connectionAborted
                ) {
                    $payloadTransactions = [];
                    $transactionsByUuid = [];

                    foreach ($chunk as $transaction) {
                        $mapped = $this->mapTransactionToUuidPayload($transaction);
                        if (!$mapped['valid']) {
                            $transaction->update([
                                'sync_error' => $mapped['error'],
                            ]);
                            $totalFailed++;
                            continue;
                        }

                        $payloadTransactions[] = $mapped['payload'];
                        $transactionsByUuid[$transaction->uuid] = $transaction;
                    }

                    $batchesProcessed++;

                    if (empty($payloadTransactions)) {
                        return ($maxBatches === null || $batchesProcessed < $maxBatches);
                    }

                    try {
                        $endpoint = "{$centralUrl}/api/v1/sync/transactions";

                        $response = Http::withToken($centralToken)
                            ->acceptJson()
                            ->asJson()
                            ->timeout($timeout)
                            ->retry($retryTimes, $retrySleepMs, function (Throwable $exception) {
                                return $exception instanceof ConnectionException;
                            }, throw: false)
                            ->post($endpoint, [
                                'transactions' => $payloadTransactions,
                            ]);

                        if ($response->successful()) {
                            $results = $response->json('results', []);
                            $now = now();

                            foreach ($results as $resItem) {
                                $trxUuid = (string) ($resItem['transaction_uuid'] ?? '');
                                $localTrx = $transactionsByUuid[$trxUuid] ?? null;
                                if (!$localTrx) {
                                    continue;
                                }

                                $status = strtolower((string) ($resItem['status'] ?? ''));
                                $isOk = !empty($resItem['success']) || in_array($status, ['synced', 'already_synced'], true);

                                if ($isOk) {
                                    $localTrx->update([
                                        'synced_at' => $now,
                                        'sync_error' => null,
                                    ]);
                                    if ($localTrx->shift && $localTrx->shift->synced_at === null) {
                                        $localTrx->shift->update([
                                            'synced_at' => $now,
                                            'sync_error' => null,
                                        ]);
                                    }
                                    $totalSynced++;
                                } else {
                                    $errMsg = (string) ($resItem['error'] ?? $resItem['message'] ?? 'Gagal diproses oleh Server Pusat.');
                                    $localTrx->update([
                                        'sync_error' => $errMsg,
                                    ]);
                                    $totalFailed++;
                                }
                            }
                        } else {
                            $httpErr = "HTTP {$response->status()}: " . ($response->json('message') ?? $response->body());
                            Log::warning("[POS Sync] Server Pusat menolak request sync: {$httpErr}");

                            foreach ($transactionsByUuid as $localTrx) {
                                $localTrx->update([
                                    'sync_error' => mb_substr($httpErr, 0, 1000),
                                ]);
                                $totalFailed++;
                            }
                        }
                    } catch (ConnectionException $e) {
                        $connMsg = 'Koneksi ke Server Pusat terputus (Offline): ' . $e->getMessage();
                        Log::warning("[POS Sync] {$connMsg}");

                        foreach ($transactionsByUuid as $localTrx) {
                            $localTrx->update([
                                'sync_error' => mb_substr($connMsg, 0, 1000),
                            ]);
                        }

                        $connectionAborted = true;
                        return false;
                    } catch (Throwable $e) {
                        $unexpectedMsg = 'Gagal sinkronisasi: ' . $e->getMessage();
                        Log::error("[POS Sync] {$unexpectedMsg}");

                        foreach ($transactionsByUuid as $localTrx) {
                            $localTrx->update([
                                'sync_error' => mb_substr($unexpectedMsg, 0, 1000),
                            ]);
                            $totalFailed++;
                        }
                    }

                    if ($maxBatches !== null && $batchesProcessed >= $maxBatches) {
                        return false;
                    }

                    return true;
                });
            }

            // Push standalone operational state (CashierShift, Attendance, AuditLog, GoodsReceipt verifications)
            if (!$connectionAborted && $this->hasPendingOperationalRecords()) {
                $opRes = $this->syncOperationalBatch(
                    $centralUrl,
                    $centralToken,
                    $effectiveBatchSize,
                    $timeout,
                    $retryTimes,
                    $retrySleepMs
                );
                $totalSynced += $opRes['synced'];
                $totalFailed += $opRes['failed'];
                if ($opRes['processed']) {
                    $batchesProcessed++;
                }
                if ($opRes['connection_aborted']) {
                    $connectionAborted = true;
                }
            }

            $remaining = Transaction::query()->whereNull('synced_at')->count();

            if ($connectionAborted) {
                return [
                    'status' => 'offline',
                    'message' => 'Koneksi ke Server Pusat tidak tersedia. Transaksi akan dikirim ulang pada jadwal berikutnya.',
                    'synced_count' => $totalSynced,
                    'failed_count' => $totalFailed,
                    'batches_processed' => $batchesProcessed,
                    'pending_remaining' => $remaining,
                    'connection_aborted' => true,
                ];
            }

            return [
                'status' => $totalFailed > 0 && $totalSynced === 0 ? 'failed' : 'success',
                'message' => "Sinkronisasi selesai: {$totalSynced} berhasil, {$totalFailed} gagal.",
                'synced_count' => $totalSynced,
                'failed_count' => $totalFailed,
                'batches_processed' => $batchesProcessed,
                'pending_remaining' => $remaining,
                'connection_aborted' => false,
            ];
        } finally {
            optional($lock)->release();
        }
    }

    /**
     * Map a local Transaction model and its items into a strictly UUID-referenced payload
     * without leaking any local auto-increment primary/foreign keys.
     *
     * @return array{valid: bool, payload?: array<string, mixed>, error?: string}
     */
    public function mapTransactionToUuidPayload(Transaction $transaction): array
    {
        $employeeUuid = $transaction->user?->uuid;
        if (!$employeeUuid && $transaction->shift?->user_id) {
            $employeeUuid = User::where('id', $transaction->shift->user_id)->value('uuid');
        }
        if (!$employeeUuid) {
            $employeeUuid = User::orderBy('id')->value('uuid');
        }

        if (!$employeeUuid) {
            return [
                'valid' => false,
                'error' => 'employee_uuid tidak ditemukan untuk transaksi ini.',
            ];
        }

        $itemsPayload = [];
        foreach ($transaction->items as $item) {
            $productUuid = $item->product?->uuid;
            if (!$productUuid && !empty($item->sku)) {
                $productUuid = Product::withTrashed()->where('sku', $item->sku)->value('uuid');
            }
            if (!$productUuid && !empty($item->barcode)) {
                $productUuid = Product::withTrashed()->where('barcode', $item->barcode)->value('uuid');
            }

            if (!$productUuid) {
                return [
                    'valid' => false,
                    'error' => "product_uuid tidak ditemukan untuk item SKU [{$item->sku}] ({$item->product_name}).",
                ];
            }

            $itemsPayload[] = [
                'item_uuid' => $item->uuid,
                'product_uuid' => $productUuid,
                'sku' => $item->sku,
                'barcode' => $item->barcode,
                'coupon_number' => $item->coupon_number,
                'product_name' => $item->product_name,
                'unit' => $item->unit ?? 'Pcs',
                'qty' => (int) $item->qty,
                'unit_price' => (float) $item->unit_price,
                'is_taxable' => (bool) $item->is_taxable,
                'tax_amount' => (float) $item->tax_amount,
                'is_expired_clearance' => (bool) $item->is_expired_clearance,
                'clearance_discount_percent' => (float) $item->clearance_discount_percent,
                'discount_amount' => (float) $item->discount_amount,
                'total_price' => (float) $item->total_price,
                'promo_name' => $item->promo_name,
                'promo_discount' => (float) ($item->promo_discount ?? 0),
            ];
        }

        if (empty($itemsPayload)) {
            return [
                'valid' => false,
                'error' => 'Transaksi tidak memiliki item detail untuk disinkronkan.',
            ];
        }

        $configuredTerminalCode = strtoupper(trim((string) config('pos_sync.terminal_code', '')));
        $configuredBranchUuid = trim((string) config('pos_sync.branch_uuid', ''));

        return [
            'valid' => true,
            'payload' => [
                'transaction_uuid' => $transaction->uuid,
                'invoice_number' => $transaction->invoice_number,
                'employee_uuid' => $employeeUuid,
                'employee_nik' => $transaction->user?->nik ?? $transaction->shift?->cashier_nik,
                'cashier_name' => $transaction->cashier_name,
                'shift_uuid' => $transaction->shift?->uuid,
                'shift_code' => $transaction->shift?->shift_code,
                'terminal_code' => $transaction->terminal_code ?: ($configuredTerminalCode !== '' ? $configuredTerminalCode : null),
                'location_uuid' => $transaction->location?->uuid ?: ($configuredBranchUuid !== '' ? $configuredBranchUuid : null),
                'branch' => $transaction->branch,
                'member_uuid' => $transaction->member?->uuid,
                'member_nik' => $transaction->member_nik,
                'member_name' => $transaction->member_name,
                'payment_method' => $transaction->payment_method,
                'subtotal' => (float) $transaction->subtotal,
                'discount_total' => (float) $transaction->discount_total,
                'promo_discount_total' => (float) ($transaction->promo_discount_total ?? 0),
                'promo_enabled' => (bool) ($transaction->promo_enabled ?? true),
                'tax_total' => (float) $transaction->tax_total,
                'grand_total' => (float) $transaction->grand_total,
                'cash_received' => (float) $transaction->cash_received,
                'change_returned' => (float) $transaction->change_returned,
                'payment_reference' => $transaction->payment_reference,
                'split_cash' => (float) $transaction->split_cash,
                'split_non_cash' => (float) $transaction->split_non_cash,
                'status' => $transaction->status ?? 'SUCCESS',
                'is_over_limit' => (bool) $transaction->is_over_limit,
                'debt_status' => $transaction->debt_status,
                'created_at' => ($transaction->created_at ?? now())->toIso8601String(),
                'items' => $itemsPayload,
            ],
        ];
    }

    /**
     * Check if any standalone operational records (shifts, attendances, audit logs, or verified goods receipts)
     * require synchronization to the Central Server.
     */
    protected function hasPendingOperationalRecords(): bool
    {
        if (\App\Models\CashierShift::query()->whereNull('synced_at')->orWhereColumn('updated_at', '>', 'synced_at')->exists()) {
            return true;
        }
        if (\Illuminate\Support\Facades\Schema::hasColumn('attendances', 'synced_at')) {
            if (\App\Models\Attendance::query()->whereNull('synced_at')->orWhereColumn('updated_at', '>', 'synced_at')->exists()) {
                return true;
            }
        }
        if (\Illuminate\Support\Facades\Schema::hasColumn('audit_logs', 'synced_at')) {
            if (\App\Models\AuditLog::query()->whereNull('synced_at')->exists()) {
                return true;
            }
        }
        if (\Illuminate\Support\Facades\Schema::hasColumn('goods_receipts', 'synced_at')) {
            if (\App\Models\GoodsReceipt::query()
                ->whereIn('status', ['APPROVED', 'DITERIMA_LENGKAP', 'DITERIMA_SELISIH', 'TIDAK_SAMPAI'])
                ->where(fn ($q) => $q->whereNull('synced_at')->orWhereColumn('updated_at', '>', 'synced_at'))
                ->exists()) {
                return true;
            }
        }

        return false;
    }

    /**
     * Push pending CashierShift, Attendance, AuditLog, and verified GoodsReceipt records to `/api/v1/sync/operational`.
     *
     * @return array{synced: int, failed: int, processed: bool, connection_aborted: bool}
     */
    protected function syncOperationalBatch(
        string $centralUrl,
        string $centralToken,
        int $batchSize,
        int $timeout,
        int $retryTimes,
        int $retrySleepMs
    ): array {
        $shifts = \App\Models\CashierShift::with('user')
            ->where(fn ($q) => $q->whereNull('synced_at')->orWhereColumn('updated_at', '>', 'synced_at'))
            ->orderBy('id')
            ->limit($batchSize)
            ->get();

        $attendances = \Illuminate\Support\Facades\Schema::hasColumn('attendances', 'synced_at')
            ? \App\Models\Attendance::with('user')
                ->where(fn ($q) => $q->whereNull('synced_at')->orWhereColumn('updated_at', '>', 'synced_at'))
                ->orderBy('id')
                ->limit($batchSize)
                ->get()
            : collect();

        $auditLogs = \Illuminate\Support\Facades\Schema::hasColumn('audit_logs', 'synced_at')
            ? \App\Models\AuditLog::query()
                ->whereNull('synced_at')
                ->orderBy('id')
                ->limit($batchSize)
                ->get()
            : collect();

        $goodsReceipts = \Illuminate\Support\Facades\Schema::hasColumn('goods_receipts', 'synced_at')
            ? \App\Models\GoodsReceipt::with(['items.product', 'receiver'])
                ->whereIn('status', ['APPROVED', 'DITERIMA_LENGKAP', 'DITERIMA_SELISIH', 'TIDAK_SAMPAI'])
                ->where(fn ($q) => $q->whereNull('synced_at')->orWhereColumn('updated_at', '>', 'synced_at'))
                ->orderBy('id')
                ->limit($batchSize)
                ->get()
            : collect();

        if ($shifts->isEmpty() && $attendances->isEmpty() && $auditLogs->isEmpty() && $goodsReceipts->isEmpty()) {
            return ['synced' => 0, 'failed' => 0, 'processed' => false, 'connection_aborted' => false];
        }

        $userUuidById = User::pluck('uuid', 'id')->all();

        $payload = [
            'shifts' => $shifts->map(fn ($s) => [
                'uuid' => $s->uuid,
                'shift_code' => $s->shift_code,
                'terminal_code' => $s->terminal_code,
                'employee_uuid' => $s->user?->uuid ?? ($s->user_id ? ($userUuidById[$s->user_id] ?? null) : null),
                'cashier_nik' => $s->cashier_nik,
                'cashier_name' => $s->cashier_name,
                'opening_cash' => (float) $s->opening_cash,
                'cash_in' => (float) $s->cash_in,
                'cash_out' => (float) $s->cash_out,
                'actual_cash' => $s->actual_cash !== null ? (float) $s->actual_cash : null,
                'variance' => $s->variance !== null ? (float) $s->variance : null,
                'variance_status' => $s->variance_status,
                'closing_notes' => $s->closing_notes,
                'status' => $s->status,
                'opened_at' => $s->opened_at?->toIso8601String(),
                'closed_at' => $s->closed_at?->toIso8601String(),
            ])->values()->all(),
            'attendances' => $attendances->map(fn ($a) => [
                'uuid' => $a->uuid,
                'employee_uuid' => $a->user?->uuid ?? ($a->user_id ? ($userUuidById[$a->user_id] ?? null) : null),
                'staff_name' => $a->staff_name,
                'terminal' => $a->terminal,
                'date' => $a->date instanceof \Carbon\CarbonInterface ? $a->date->toDateString() : (string) $a->date,
                'check_in' => $a->check_in,
                'check_out' => $a->check_out,
                'status' => $a->status,
                'notes' => $a->notes,
            ])->values()->all(),
            'audit_logs' => $auditLogs->map(fn ($l) => [
                'uuid' => $l->uuid,
                'log_id' => $l->log_id,
                'employee_uuid' => $l->user_id ? ($userUuidById[$l->user_id] ?? null) : null,
                'user_name' => $l->user_name,
                'user_role' => $l->user_role,
                'category' => $l->category,
                'action_name' => $l->action_name,
                'description' => $l->description,
                'reference' => $l->reference,
                'status' => $l->status,
                'source' => $l->source,
                'changes' => $l->changes,
                'created_at' => $l->created_at?->toIso8601String(),
            ])->values()->all(),
            'goods_receipts' => $goodsReceipts->map(fn ($gr) => [
                'uuid' => $gr->uuid,
                'status' => $gr->status,
                'verified_at' => $gr->verified_at?->toIso8601String(),
                'received_at' => $gr->received_at?->toIso8601String(),
                'employee_uuid' => $gr->receiver?->uuid ?? ($gr->received_by ? ($userUuidById[$gr->received_by] ?? null) : null),
                'notes' => $gr->notes,
                'items' => $gr->items->map(fn ($it) => [
                    'product_uuid' => $it->product?->uuid,
                    'qty_received' => (float) $it->qty_received,
                    'condition' => $it->condition,
                    'notes' => $it->notes,
                ])->filter(fn ($it) => !empty($it['product_uuid']))->values()->all(),
            ])->values()->all(),
        ];

        try {
            $response = Http::withToken($centralToken)
                ->acceptJson()
                ->asJson()
                ->timeout($timeout)
                ->retry($retryTimes, $retrySleepMs, function (Throwable $exception) {
                    return $exception instanceof ConnectionException;
                }, throw: false)
                ->post("{$centralUrl}/api/v1/sync/operational", $payload);

            if (!$response->successful()) {
                $httpErr = "HTTP {$response->status()}: " . ($response->json('message') ?? $response->body());
                Log::warning("[POS Operational Sync] Gagal: {$httpErr}");
                return [
                    'synced' => 0,
                    'failed' => $shifts->count() + $attendances->count() + $auditLogs->count() + $goodsReceipts->count(),
                    'processed' => true,
                    'connection_aborted' => false,
                ];
            }

            $now = now()->addSecond();
            $results = $response->json('results', []);
            $synced = 0;
            $failed = 0;

            $markSynced = function ($collection, array $resItems) use ($now, &$synced, &$failed) {
                $byUuid = $collection->keyBy('uuid');
                foreach ($resItems as $item) {
                    $model = $byUuid->get((string) ($item['uuid'] ?? ''));
                    if (!$model) {
                        continue;
                    }
                    if (!empty($item['success'])) {
                        $model->timestamps = false;
                        $model->forceFill(['synced_at' => $now, 'sync_error' => null])->saveQuietly();
                        $model->timestamps = true;
                        $synced++;
                    } else {
                        $model->timestamps = false;
                        $model->forceFill(['sync_error' => mb_substr((string) ($item['error'] ?? 'Gagal sync'), 0, 1000)])->saveQuietly();
                        $model->timestamps = true;
                        $failed++;
                    }
                }
            };

            $markSynced($shifts, $results['shifts'] ?? []);
            $markSynced($attendances, $results['attendances'] ?? []);
            $markSynced($auditLogs, $results['audit_logs'] ?? []);
            $markSynced($goodsReceipts, $results['goods_receipts'] ?? []);

            return ['synced' => $synced, 'failed' => $failed, 'processed' => true, 'connection_aborted' => false];
        } catch (ConnectionException $e) {
            Log::warning('[POS Operational Sync] Koneksi ke Server Pusat terputus: ' . $e->getMessage());
            return ['synced' => 0, 'failed' => 0, 'processed' => true, 'connection_aborted' => true];
        } catch (Throwable $e) {
            Log::error('[POS Operational Sync] Error: ' . $e->getMessage());
            return [
                'synced' => 0,
                'failed' => $shifts->count() + $attendances->count() + $auditLogs->count() + $goodsReceipts->count(),
                'processed' => true,
                'connection_aborted' => false,
            ];
        }
    }
}
