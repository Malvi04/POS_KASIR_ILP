<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\SyncTransactionBatchRequest;
use App\Models\CashierShift;
use App\Models\Location;
use App\Models\Member;
use App\Models\Product;
use App\Models\ProductStock;
use App\Models\Transaction;
use App\Models\TransactionItem;
use App\Models\User;
use Carbon\Carbon;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

class TransactionSyncController extends Controller
{
    /**
     * Receive and process a batch of offline-first POS transactions from local cashier nodes.
     * Enforces per-transaction DB::transaction(), UUID resolution, idempotency, and atomic stock decrement.
     */
    public function syncTransactions(SyncTransactionBatchRequest $request): JsonResponse
    {
        $batch = $request->validated('transactions', []);

        $results = [];
        $syncedCount = 0;
        $duplicateCount = 0;
        $failedCount = 0;

        foreach ($batch as $trxPayload) {
            $trxUuid = (string) $trxPayload['transaction_uuid'];

            try {
                $outcome = DB::transaction(function () use ($trxPayload, $trxUuid) {
                    // 1. Append-Only & Idempotent Check by UUID
                    $existing = Transaction::where('uuid', $trxUuid)->first();
                    if ($existing) {
                        return [
                            'transaction_uuid' => $trxUuid,
                            'success' => true,
                            'status' => 'already_synced',
                            'central_transaction_id' => $existing->id,
                            'invoice_number' => $existing->invoice_number,
                            'was_recently_created' => false,
                        ];
                    }

                    // 2. Resolve Employee (User) UUID -> Central Auto-Increment ID
                    $employeeUuid = (string) $trxPayload['employee_uuid'];
                    $employee = User::where('uuid', $employeeUuid)->first();
                    if (!$employee) {
                        throw new DomainException("employee_uuid [{$employeeUuid}] tidak ditemukan di database Server Pusat.");
                    }

                    // 3. Resolve All Item Product UUIDs -> Central Auto-Increment IDs before writing
                    $itemsPayload = $trxPayload['items'] ?? [];
                    $resolvedProducts = [];
                    $missingProductUuids = [];

                    foreach ($itemsPayload as $item) {
                        $pUuid = (string) $item['product_uuid'];
                        if (!isset($resolvedProducts[$pUuid])) {
                            $product = Product::where('uuid', $pUuid)->lockForUpdate()->first();
                            if (!$product) {
                                $missingProductUuids[] = $pUuid;
                                continue;
                            }
                            $resolvedProducts[$pUuid] = $product;
                        }
                    }

                    if (!empty($missingProductUuids)) {
                        $missingList = implode(', ', array_unique($missingProductUuids));
                        throw new DomainException("product_uuid [{$missingList}] tidak ditemukan di database Server Pusat.");
                    }

                    // 4. Resolve Optional Location & Member UUIDs
                    $location = null;
                    if (!empty($trxPayload['location_uuid'])) {
                        $location = Location::where('uuid', $trxPayload['location_uuid'])->first();
                    }
                    if (!$location && !empty($trxPayload['branch'])) {
                        $location = Location::where('name', $trxPayload['branch'])
                            ->orWhere('code', $trxPayload['branch'])
                            ->first();
                    }

                    $member = null;
                    if (!empty($trxPayload['member_uuid'])) {
                        $member = Member::where('uuid', $trxPayload['member_uuid'])->first();
                    }
                    if (!$member && !empty($trxPayload['member_nik'])) {
                        $member = Member::where('nik', $trxPayload['member_nik'])->first();
                    }

                    // 5. Resolve or Auto-Provision CashierShift on Central Server (satisfies NOT NULL transactions.shift_id FK)
                    $shift = $this->resolveCentralShift($trxPayload, $employee, $location);

                    // 6. Ensure Unique Invoice Number on Central Server
                    $requestedInvoice = trim((string) ($trxPayload['invoice_number'] ?? ''));
                    if ($requestedInvoice === '') {
                        $requestedInvoice = 'SYNC-' . date('Ymd') . '-' . Str::upper(substr(str_replace('-', '', $trxUuid), -8));
                    }
                    $invoiceNumber = $requestedInvoice;
                    if (Transaction::where('invoice_number', $invoiceNumber)->exists()) {
                        $invoiceNumber = $requestedInvoice . '-' . Str::upper(substr(str_replace('-', '', $trxUuid), -6));
                    }

                    $createdAtLocal = !empty($trxPayload['created_at'])
                        ? Carbon::parse($trxPayload['created_at'])
                        : now();
                    $now = now();

                    $grandTotal = (float) ($trxPayload['grand_total'] ?? 0);
                    $subtotal = (float) ($trxPayload['subtotal'] ?? $grandTotal);
                    $discountTotal = (float) ($trxPayload['discount_total'] ?? 0);
                    $promoDiscountTotal = (float) ($trxPayload['promo_discount_total'] ?? 0);
                    $taxTotal = (float) ($trxPayload['tax_total'] ?? 0);
                    $paymentMethod = strtoupper((string) ($trxPayload['payment_method'] ?? 'TUNAI'));

                    // 7. Create Transaction (firstOrCreate by uuid for strict idempotency)
                    $transaction = Transaction::firstOrCreate(
                        ['uuid' => $trxUuid],
                        [
                            'invoice_number' => $invoiceNumber,
                            'offline_id' => $trxUuid,
                            'shift_id' => $shift->id,
                            'user_id' => $employee->id,
                            'cashier_name' => $trxPayload['cashier_name'] ?? $employee->name,
                            'terminal_code' => $trxPayload['terminal_code'] ?? $shift->terminal_code,
                            'location_id' => $location?->id ?? $shift->location_id,
                            'branch' => $trxPayload['branch'] ?? $location?->name ?? $shift->branch ?? $employee->branch ?? 'Pusat',
                            'member_id' => $member?->id,
                            'member_nik' => $trxPayload['member_nik'] ?? $member?->nik,
                            'member_name' => $trxPayload['member_name'] ?? $member?->name,
                            'payment_method' => $paymentMethod,
                            'subtotal' => $subtotal,
                            'discount_total' => $discountTotal,
                            'promo_discount_total' => $promoDiscountTotal,
                            'promo_enabled' => (bool) ($trxPayload['promo_enabled'] ?? true),
                            'tax_total' => $taxTotal,
                            'grand_total' => $grandTotal,
                            'cash_received' => (float) ($trxPayload['cash_received'] ?? $grandTotal),
                            'change_returned' => (float) ($trxPayload['change_returned'] ?? 0),
                            'payment_reference' => $trxPayload['payment_reference'] ?? null,
                            'split_cash' => (float) ($trxPayload['split_cash'] ?? 0),
                            'split_non_cash' => (float) ($trxPayload['split_non_cash'] ?? 0),
                            'status' => $trxPayload['status'] ?? 'SUCCESS',
                            'is_over_limit' => (bool) ($trxPayload['is_over_limit'] ?? false),
                            'debt_status' => $trxPayload['debt_status'] ?? null,
                            'synced_at' => $now,
                            'sync_error' => null,
                        ]
                    );

                    // Fix timestamps to preserve original local transaction timestamp if newly created
                    if ($transaction->wasRecentlyCreated) {
                        Transaction::where('id', $transaction->id)->update([
                            'created_at' => $createdAtLocal,
                            'updated_at' => $now,
                        ]);

                        // 8. Insert Transaction Items & Execute Atomic Stock Decrement ONLY on First Creation
                        foreach ($itemsPayload as $item) {
                            $pUuid = (string) $item['product_uuid'];
                            /** @var Product $product */
                            $product = $resolvedProducts[$pUuid];
                            $qty = (int) $item['qty'];
                            $unitPrice = (float) $item['unit_price'];
                            $itemDiscount = (float) ($item['discount_amount'] ?? 0);
                            $itemTax = (float) ($item['tax_amount'] ?? 0);
                            $itemTotal = isset($item['total_price'])
                                ? (float) $item['total_price']
                                : max(0, round(($unitPrice * $qty) - $itemDiscount, 2));

                            $itemUuid = !empty($item['item_uuid']) ? (string) $item['item_uuid'] : (string) Str::uuid7();
                            if (TransactionItem::where('uuid', $itemUuid)->exists()) {
                                $itemUuid = (string) Str::uuid7();
                            }

                            TransactionItem::create([
                                'uuid' => $itemUuid,
                                'transaction_id' => $transaction->id,
                                'product_id' => $product->id,
                                'sku' => $item['sku'] ?? $product->sku,
                                'barcode' => $item['barcode'] ?? $product->barcode,
                                'coupon_number' => $item['coupon_number'] ?? null,
                                'product_name' => $item['product_name'] ?? $product->name,
                                'unit' => $item['unit'] ?? $product->unit ?? 'Pcs',
                                'unit_price' => $unitPrice,
                                'qty' => $qty,
                                'is_taxable' => (bool) ($item['is_taxable'] ?? $product->is_taxable),
                                'tax_amount' => $itemTax,
                                'is_expired_clearance' => (bool) ($item['is_expired_clearance'] ?? false),
                                'clearance_discount_percent' => (float) ($item['clearance_discount_percent'] ?? 0),
                                'discount_amount' => $itemDiscount,
                                'total_price' => $itemTotal,
                                'promo_name' => $item['promo_name'] ?? null,
                                'promo_discount' => (float) ($item['promo_discount'] ?? 0),
                            ]);

                            // Atomic Stock Decrement on Central Product Master
                            Product::where('uuid', $pUuid)->decrement('stock', $qty, [
                                'qty_produk' => DB::raw('"qty_produk" - ' . $qty),
                            ]);

                            // Also decrement per-location ProductStock if store stock exists for this location
                            $targetLocationId = $location?->id ?? $shift->location_id;
                            if ($targetLocationId) {
                                ProductStock::where('product_id', $product->id)
                                    ->where('location_id', $targetLocationId)
                                    ->decrement('stock', $qty);
                            }
                        }

                        // Update central shift aggregates atomically
                        if ($paymentMethod === 'TUNAI') {
                            $shift->cash_sales_total += $grandTotal;
                        } elseif (in_array($paymentMethod, ['QRIS', 'TRANSFER'], true)) {
                            $shift->qris_sales_total += $grandTotal;
                        } elseif ($paymentMethod === 'KREDIT') {
                            $shift->credit_sales_total += $grandTotal;
                        } elseif ($paymentMethod === 'CAMPURAN') {
                            $shift->cash_sales_total += (float) ($trxPayload['split_cash'] ?? 0);
                            $shift->qris_sales_total += (float) ($trxPayload['split_non_cash'] ?? 0);
                        }
                        $shift->total_sales += $grandTotal;
                        $shift->transaction_count += 1;
                        $shift->expected_cash = $shift->calculateExpectedCash();
                        $shift->save();

                        return [
                            'transaction_uuid' => $trxUuid,
                            'success' => true,
                            'status' => 'synced',
                            'central_transaction_id' => $transaction->id,
                            'invoice_number' => $transaction->invoice_number,
                            'was_recently_created' => true,
                        ];
                    }

                    return [
                        'transaction_uuid' => $trxUuid,
                        'success' => true,
                        'status' => 'already_synced',
                        'central_transaction_id' => $transaction->id,
                        'invoice_number' => $transaction->invoice_number,
                        'was_recently_created' => false,
                    ];
                });

                $results[] = $outcome;
                if (($outcome['status'] ?? '') === 'synced') {
                    $syncedCount++;
                } else {
                    $duplicateCount++;
                }
            } catch (DomainException $e) {
                $failedCount++;
                $results[] = [
                    'transaction_uuid' => $trxUuid,
                    'success' => false,
                    'status' => 'failed',
                    'error' => $e->getMessage(),
                ];
            } catch (Throwable $e) {
                $failedCount++;
                $results[] = [
                    'transaction_uuid' => $trxUuid,
                    'success' => false,
                    'status' => 'failed',
                    'error' => 'Gagal memproses transaksi di Server Pusat: ' . $e->getMessage(),
                ];
            }
        }

        return response()->json([
            'success' => true,
            'summary' => [
                'total_received' => count($batch),
                'synced' => $syncedCount,
                'already_synced' => $duplicateCount,
                'failed' => $failedCount,
            ],
            'results' => $results,
        ], 200);
    }

    /**
     * Resolve or create a CashierShift record on the Central Server to satisfy transactions.shift_id FK.
     */
    protected function resolveCentralShift(array $trxPayload, User $employee, ?Location $location): CashierShift
    {
        $shiftUuid = !empty($trxPayload['shift_uuid']) ? (string) $trxPayload['shift_uuid'] : null;

        if ($shiftUuid) {
            $byUuid = CashierShift::where('uuid', $shiftUuid)->first();
            if ($byUuid) {
                return $byUuid;
            }
        }

        $terminalCode = (string) ($trxPayload['terminal_code'] ?? 'POS-01');
        $requestedShiftCode = trim((string) ($trxPayload['shift_code'] ?? ''));

        if ($requestedShiftCode !== '') {
            $byCode = CashierShift::where('shift_code', $requestedShiftCode)->first();
            if ($byCode) {
                return $byCode;
            }
        }

        if (!$shiftUuid) {
            $openShift = CashierShift::where('user_id', $employee->id)
                ->where('terminal_code', $terminalCode)
                ->where('status', 'OPEN')
                ->latest('id')
                ->first();

            if ($openShift) {
                return $openShift;
            }
        }

        $shiftCode = $requestedShiftCode !== ''
            ? $requestedShiftCode
            : ('CS-SYNC-' . date('Ymd') . '-' . Str::upper(Str::random(6)));

        if (CashierShift::where('shift_code', $shiftCode)->exists()) {
            $shiftCode .= '-' . Str::upper(Str::random(4));
        }

        return CashierShift::create([
            'uuid' => $shiftUuid ?: (string) Str::uuid7(),
            'shift_code' => $shiftCode,
            'terminal_code' => $terminalCode,
            'location_id' => $location?->id,
            'branch' => $trxPayload['branch'] ?? $location?->name ?? $employee->branch ?? 'Pusat',
            'user_id' => $employee->id,
            'cashier_nik' => $employee->nik ?? $employee->username ?? 'KASIR',
            'cashier_name' => $trxPayload['cashier_name'] ?? $employee->name,
            'opening_cash' => 0,
            'cash_sales_total' => 0,
            'qris_sales_total' => 0,
            'credit_sales_total' => 0,
            'total_sales' => 0,
            'transaction_count' => 0,
            'cash_in' => 0,
            'cash_out' => 0,
            'expected_cash' => 0,
            'status' => 'OPEN',
            'opened_at' => !empty($trxPayload['created_at']) ? Carbon::parse($trxPayload['created_at']) : now(),
            'synced_at' => now(),
        ]);
    }
}
