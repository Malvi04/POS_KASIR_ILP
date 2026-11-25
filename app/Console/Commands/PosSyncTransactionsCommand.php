<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class PosSyncTransactionsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'pos:sync-transactions
                            {--batch= : Jumlah transaksi per batch pengiriman}
                            {--force : Jalankan meskipun POS_NODE_ROLE bukan local}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sinkronisasi transaksi kasir lokal yang belum terkirim (synced_at IS NULL) ke Server Pusat melalui REST API berbasis UUIDv7';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $nodeRole = strtolower((string) config('pos_sync.node_role', 'central'));
        if ($nodeRole !== 'local' && !$this->option('force')) {
            $this->info("Instance saat ini berperan sebagai [{$nodeRole}]. Sync hanya berjalan pada node [local] (gunakan --force untuk memaksa).");
            return self::SUCCESS;
        }

        $centralUrl = rtrim((string) config('pos_sync.central_api_url', ''), '/');
        $centralToken = (string) config('pos_sync.central_api_token', '');
        $batchSize = max(1, (int) ($this->option('batch') ?: config('pos_sync.batch_size', 50)));
        $timeout = max(3, (int) config('pos_sync.timeout_seconds', 15));
        $retryTimes = max(1, (int) config('pos_sync.retry_times', 3));
        $retrySleepMs = max(100, (int) config('pos_sync.retry_sleep_ms', 500));

        if ($centralUrl === '') {
            $this->warn('CENTRAL_API_URL belum dikonfigurasi.');
            return self::SUCCESS;
        }

        $pendingCount = Transaction::query()->whereNull('synced_at')->count();
        if ($pendingCount === 0) {
            $this->info('Tidak ada transaksi baru untuk disinkronkan.');
            return self::SUCCESS;
        }

        $this->info("Memulai sinkronisasi {$pendingCount} transaksi tertunda ke {$centralUrl} (batch size: {$batchSize})...");

        $totalSynced = 0;
        $totalFailed = 0;
        $connectionAborted = false;

        Transaction::query()
            ->whereNull('synced_at')
            ->with(['items.product', 'user', 'shift', 'location', 'member'])
            ->orderBy('id')
            ->chunkById($batchSize, function (Collection $chunk) use (
                $centralUrl,
                $centralToken,
                $timeout,
                $retryTimes,
                $retrySleepMs,
                &$totalSynced,
                &$totalFailed,
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

                if (empty($payloadTransactions)) {
                    return true;
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
                    return false; // Stop chunk iteration gracefully when offline
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

                return true;
            });

        if ($connectionAborted) {
            $this->warn("Koneksi ke Server Pusat tidak tersedia. Transaksi akan dikirim ulang pada jadwal berikutnya.");
            return self::SUCCESS;
        }

        $this->info("Sinkronisasi selesai: {$totalSynced} berhasil, {$totalFailed} gagal.");
        return self::SUCCESS;
    }

    /**
     * Map a local Transaction model and its items into a strictly UUID-referenced payload
     * without leaking any local auto-increment primary/foreign keys.
     *
     * @return array{valid: bool, payload?: array<string, mixed>, error?: string}
     */
    protected function mapTransactionToUuidPayload(Transaction $transaction): array
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
                $productUuid = Product::where('sku', $item->sku)->value('uuid');
            }
            if (!$productUuid && !empty($item->barcode)) {
                $productUuid = Product::where('barcode', $item->barcode)->value('uuid');
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
                'terminal_code' => $transaction->terminal_code,
                'location_uuid' => $transaction->location?->uuid,
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
}
