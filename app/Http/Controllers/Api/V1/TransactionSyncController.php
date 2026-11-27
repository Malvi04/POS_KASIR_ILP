<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\SyncTransactionBatchRequest;
use App\Models\CashierShift;
use App\Models\Location;
use App\Models\Member;
use App\Models\PosTerminal;
use App\Models\Product;
use App\Models\ProductStock;
use App\Models\StockMovement;
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
     * Enforces authoritative branch_id & terminal_code from PosTerminal token, per-transaction DB::transaction(),
     * UUID resolution, idempotency, and atomic per-branch stock decrement.
     */
    public function syncTransactions(SyncTransactionBatchRequest $request): JsonResponse
    {
        /** @var PosTerminal|null $terminal */
        $terminal = $request->attributes->get('pos_terminal');
        if (!$terminal || !$terminal->branch) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized: Identitas terminal kasir tidak ditemukan.',
            ], 401);
        }

        $terminal->forceFill([
            'last_push_at' => now(),
        ])->saveQuietly();

        /** @var Location $authoritativeBranch */
        $authoritativeBranch = $terminal->branch;
        $authoritativeBranchId = (int) $terminal->branch_id;
        $authoritativeTerminalCode = (string) $terminal->terminal_code;

        $batch = $request->validated('transactions', []);

        $results = [];
        $syncedCount = 0;
        $duplicateCount = 0;
        $failedCount = 0;

        foreach ($batch as $trxPayload) {
            $trxUuid = (string) $trxPayload['transaction_uuid'];

            try {
                $outcome = DB::transaction(function () use (
                    $trxPayload,
                    $trxUuid,
                    $authoritativeBranch,
                    $authoritativeBranchId,
                    $authoritativeTerminalCode
                ) {
                    // 1. Append-Only & Idempotent Check by UUID
                    $existing = Transaction::where('uuid', $trxUuid)->first();
                    if ($existing) {
                        return [
                            'transaction_uuid' => $trxUuid,
                            'success' => true,
                            'status' => 'already_synced',
                            'central_transaction_id' => $existing->id,
                            'invoice_number' => $existing->invoice_number,
                            'branch_id' => $existing->location_id,
                            'terminal_code' => $existing->terminal_code,
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
                            $product = Product::withTrashed()->where('uuid', $pUuid)->lockForUpdate()->first();
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

                    // 4. Resolve Optional Member UUID / NIK
                    $member = null;
                    if (!empty($trxPayload['member_uuid'])) {
                        $member = Member::where('uuid', $trxPayload['member_uuid'])->first();
                    }
                    if (!$member && !empty($trxPayload['member_nik'])) {
                        $member = Member::where('nik', $trxPayload['member_nik'])->first();
                    }

                    // 5. Resolve or Auto-Provision CashierShift scoped strictly to authoritative (branch_id, terminal_code)
                    $shift = $this->resolveCentralShift(
                        $trxPayload,
                        $employee,
                        $authoritativeBranch,
                        $authoritativeTerminalCode
                    );

                    // 6. Ensure Unique Invoice Number on Central Server
                    $branchShortCode = PosTerminal::canonicalBranchCode($authoritativeBranch);
                    $requestedInvoice = trim((string) ($trxPayload['invoice_number'] ?? ''));
                    if ($requestedInvoice === '') {
                        $requestedInvoice = "INV-{$branchShortCode}-{$authoritativeTerminalCode}-" . date('Ymd') . '-' . Str::upper(substr(str_replace('-', '', $trxUuid), -6));
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

                    // 7. Create Transaction using AUTHORITATIVE branch_id (`location_id`) & `terminal_code` from token
                    $transaction = Transaction::firstOrCreate(
                        ['uuid' => $trxUuid],
                        [
                            'invoice_number' => $invoiceNumber,
                            'offline_id' => $trxUuid,
                            'shift_id' => $shift->id,
                            'user_id' => $employee->id,
                            'cashier_name' => $trxPayload['cashier_name'] ?? $employee->name,
                            'terminal_code' => $authoritativeTerminalCode,
                            'location_id' => $authoritativeBranchId,
                            'branch' => $authoritativeBranch->name,
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

                        // 8. Insert Transaction Items & Execute Atomic Per-Branch Stock Decrement ONLY on First Creation
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

                            // Atomic Per-Branch Stock Decrement strictly on `$terminal->branch_id`
                            $this->decrementAuthoritativeBranchStock(
                                $product,
                                $authoritativeBranchId,
                                $qty,
                                $transaction,
                                $employee,
                                $authoritativeTerminalCode,
                                $authoritativeBranch->name,
                                $now
                            );
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
                            'branch_id' => $authoritativeBranchId,
                            'terminal_code' => $authoritativeTerminalCode,
                            'was_recently_created' => true,
                        ];
                    }

                    return [
                        'transaction_uuid' => $trxUuid,
                        'success' => true,
                        'status' => 'already_synced',
                        'central_transaction_id' => $transaction->id,
                        'invoice_number' => $transaction->invoice_number,
                        'branch_id' => $transaction->location_id,
                        'terminal_code' => $transaction->terminal_code,
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
            'terminal' => [
                'uuid' => $terminal->uuid,
                'terminal_code' => $authoritativeTerminalCode,
                'branch_id' => $authoritativeBranchId,
                'branch_name' => $authoritativeBranch->name,
            ],
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
     * Atomically decrement stock strictly on `$branchId` (`product_stocks`) without touching any other branch's stock,
     * log audit `StockMovement`, and keep global `products.stock` aggregate synchronized.
     */
    protected function decrementAuthoritativeBranchStock(
        Product $product,
        int $branchId,
        int $qty,
        Transaction $transaction,
        User $employee,
        string $terminalCode,
        string $branchName,
        Carbon $now
    ): void {
        $branchStock = ProductStock::where('product_id', $product->id)
            ->where('location_id', $branchId)
            ->lockForUpdate()
            ->first();

        if (!$branchStock) {
            // If no location stocks exist at all for this product (standalone/legacy product), seed from product->stock;
            // otherwise (other branches already have isolated stocks), initialize this branch at 0 so other branches' stocks stay untouched.
            $hasAnyLocationStocks = ProductStock::where('product_id', $product->id)->exists();
            $initialBranchStock = $hasAnyLocationStocks ? 0 : (int) $product->stock;

            $branchStock = ProductStock::create([
                'product_id' => $product->id,
                'location_id' => $branchId,
                'stock' => $initialBranchStock,
                'min_stock' => (int) ($product->min_stock ?? 5),
                'is_active' => true,
                'version' => 1,
            ]);
        }

        $stockBefore = (int) $branchStock->stock;

        // Atomic SQL decrement scoped strictly to (product_id, branch_id)
        ProductStock::where('product_id', $product->id)
            ->where('location_id', $branchId)
            ->decrement('stock', $qty, [
                'last_sold_at' => $now,
                'updated_at' => $now,
                'version' => DB::raw('"version" + 1'),
            ]);

        $stockAfter = $stockBefore - $qty;

        StockMovement::create([
            'product_id' => $product->id,
            'location_id' => $branchId,
            'movement_type' => 'SALE_OUT',
            'qty_change' => -$qty,
            'stock_before' => $stockBefore,
            'stock_after' => $stockAfter,
            'reference_type' => Transaction::class,
            'reference_id' => $transaction->id,
            'reference_number' => $transaction->invoice_number,
            'user_id' => $employee->id,
            'notes' => "Push Sync POS {$terminalCode} ({$branchName})",
        ]);

        // Recompute global aggregate products.stock from all location rows (other branches remain untouched)
        $aggregateStock = (int) ProductStock::where('product_id', $product->id)->sum('stock');
        $newStatus = $aggregateStock <= 0
            ? 'HABIS'
            : ($aggregateStock <= (int) ($product->min_stock ?? 5) ? 'STOK_RENDAH' : 'TERSEDIA');

        Product::withTrashed()->where('id', $product->id)->update([
            'stock' => $aggregateStock,
            'qty_produk' => $aggregateStock,
            'status' => $newStatus,
            'updated_at' => $now,
        ]);
    }

    /**
     * Resolve or create a CashierShift record on the Central Server scoped strictly to authoritative (branch_id, terminal_code).
     */
    protected function resolveCentralShift(
        array $trxPayload,
        User $employee,
        Location $authoritativeBranch,
        string $authoritativeTerminalCode
    ): CashierShift {
        $shiftUuid = !empty($trxPayload['shift_uuid']) ? (string) $trxPayload['shift_uuid'] : null;

        if ($shiftUuid) {
            $byUuid = CashierShift::where('uuid', $shiftUuid)
                ->where('location_id', $authoritativeBranch->id)
                ->first();
            if ($byUuid) {
                return $byUuid;
            }
        }

        $requestedShiftCode = trim((string) ($trxPayload['shift_code'] ?? ''));
        if ($requestedShiftCode !== '') {
            $byCode = CashierShift::where('shift_code', $requestedShiftCode)
                ->where('location_id', $authoritativeBranch->id)
                ->where('terminal_code', $authoritativeTerminalCode)
                ->first();
            if ($byCode) {
                return $byCode;
            }
        }

        if (!$shiftUuid) {
            $openShift = CashierShift::where('user_id', $employee->id)
                ->where('location_id', $authoritativeBranch->id)
                ->where('terminal_code', $authoritativeTerminalCode)
                ->where('status', 'OPEN')
                ->latest('id')
                ->first();

            if ($openShift) {
                return $openShift;
            }
        }

        $branchShortCode = PosTerminal::canonicalBranchCode($authoritativeBranch);
        $shiftCode = $requestedShiftCode !== ''
            ? $requestedShiftCode
            : ("CS-SYNC-{$branchShortCode}-{$authoritativeTerminalCode}-" . date('Ymd') . '-' . Str::upper(Str::random(5)));

        if (CashierShift::where('shift_code', $shiftCode)->exists()) {
            $shiftCode = "{$shiftCode}-{$branchShortCode}-" . Str::upper(Str::random(4));
        }

        if ($shiftUuid && CashierShift::where('uuid', $shiftUuid)->exists()) {
            $shiftUuid = (string) Str::uuid7();
        }

        return CashierShift::create([
            'uuid' => $shiftUuid ?: (string) Str::uuid7(),
            'shift_code' => $shiftCode,
            'terminal_code' => $authoritativeTerminalCode,
            'location_id' => $authoritativeBranch->id,
            'branch' => $authoritativeBranch->name,
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

    /**
     * Receive operational cashier events from local cashier nodes:
     * - `shifts`: CashierShift OPEN / CLOSED / cash drawer updates
     * - `attendances`: Cashier login / shift check-in & check-out
     * - `audit_logs`: Cashier portal login, shift audit, supervisor overrides
     * - `goods_receipts`: Verified store goods receipts (`APPROVED` / `DITERIMA_*` / `TIDAK_SAMPAI`)
     */
    public function syncOperational(\Illuminate\Http\Request $request): JsonResponse
    {
        /** @var PosTerminal|null $terminal */
        $terminal = $request->attributes->get('pos_terminal');
        if (!$terminal || !$terminal->branch) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized: Identitas terminal kasir tidak ditemukan.',
            ], 401);
        }

        $terminal->forceFill([
            'last_push_at' => now(),
        ])->saveQuietly();

        /** @var Location $authoritativeBranch */
        $authoritativeBranch = $terminal->branch;
        $authoritativeBranchId = (int) $terminal->branch_id;
        $authoritativeTerminalCode = (string) $terminal->terminal_code;
        $now = now();

        $validated = $request->validate([
            'shifts' => ['nullable', 'array'],
            'shifts.*.uuid' => ['required', 'uuid'],
            'shifts.*.shift_code' => ['required', 'string', 'max:100'],
            'shifts.*.terminal_code' => ['nullable', 'string', 'max:50'],
            'shifts.*.employee_uuid' => ['nullable', 'uuid'],
            'shifts.*.cashier_nik' => ['nullable', 'string', 'max:50'],
            'shifts.*.cashier_name' => ['nullable', 'string', 'max:150'],
            'shifts.*.opening_cash' => ['nullable', 'numeric', 'min:0'],
            'shifts.*.cash_in' => ['nullable', 'numeric', 'min:0'],
            'shifts.*.cash_out' => ['nullable', 'numeric', 'min:0'],
            'shifts.*.actual_cash' => ['nullable', 'numeric'],
            'shifts.*.variance' => ['nullable', 'numeric'],
            'shifts.*.variance_status' => ['nullable', 'string', 'max:30'],
            'shifts.*.closing_notes' => ['nullable', 'string', 'max:500'],
            'shifts.*.status' => ['required', 'string', 'max:20'],
            'shifts.*.opened_at' => ['nullable', 'date'],
            'shifts.*.closed_at' => ['nullable', 'date'],

            'attendances' => ['nullable', 'array'],
            'attendances.*.uuid' => ['required', 'uuid'],
            'attendances.*.employee_uuid' => ['nullable', 'uuid'],
            'attendances.*.staff_name' => ['required', 'string', 'max:150'],
            'attendances.*.terminal' => ['nullable', 'string', 'max:50'],
            'attendances.*.date' => ['required', 'date'],
            'attendances.*.check_in' => ['nullable', 'string', 'max:20'],
            'attendances.*.check_out' => ['nullable', 'string', 'max:20'],
            'attendances.*.status' => ['nullable', 'string', 'max:30'],
            'attendances.*.notes' => ['nullable', 'string', 'max:500'],

            'audit_logs' => ['nullable', 'array'],
            'audit_logs.*.uuid' => ['required', 'uuid'],
            'audit_logs.*.log_id' => ['nullable', 'string', 'max:80'],
            'audit_logs.*.employee_uuid' => ['nullable', 'uuid'],
            'audit_logs.*.user_name' => ['required', 'string', 'max:150'],
            'audit_logs.*.user_role' => ['nullable', 'string', 'max:60'],
            'audit_logs.*.category' => ['required', 'string', 'max:100'],
            'audit_logs.*.action_name' => ['required', 'string', 'max:150'],
            'audit_logs.*.description' => ['required', 'string'],
            'audit_logs.*.reference' => ['nullable', 'string', 'max:100'],
            'audit_logs.*.status' => ['nullable', 'string', 'max:50'],
            'audit_logs.*.source' => ['nullable', 'string', 'max:120'],
            'audit_logs.*.changes' => ['nullable', 'array'],
            'audit_logs.*.created_at' => ['nullable', 'date'],

            'goods_receipts' => ['nullable', 'array'],
            'goods_receipts.*.uuid' => ['required', 'uuid'],
            'goods_receipts.*.status' => ['required', 'string', 'max:50'],
            'goods_receipts.*.verified_at' => ['nullable', 'date'],
            'goods_receipts.*.received_at' => ['nullable', 'date'],
            'goods_receipts.*.employee_uuid' => ['nullable', 'uuid'],
            'goods_receipts.*.notes' => ['nullable', 'string', 'max:500'],
            'goods_receipts.*.items' => ['nullable', 'array'],
            'goods_receipts.*.items.*.product_uuid' => ['required', 'uuid'],
            'goods_receipts.*.items.*.qty_received' => ['required', 'numeric', 'min:0'],
            'goods_receipts.*.items.*.condition' => ['nullable', 'string', 'max:50'],
            'goods_receipts.*.items.*.notes' => ['nullable', 'string', 'max:255'],
        ]);

        $shiftResults = [];
        foreach ($validated['shifts'] ?? [] as $sPayload) {
            $sUuid = (string) $sPayload['uuid'];
            try {
                DB::transaction(function () use ($sPayload, $sUuid, $authoritativeBranch, $authoritativeBranchId, $authoritativeTerminalCode, $now, &$shiftResults) {
                    $emp = !empty($sPayload['employee_uuid'])
                        ? User::where('uuid', $sPayload['employee_uuid'])->first()
                        : null;
                    if (!$emp && !empty($sPayload['cashier_nik'])) {
                        $emp = User::where('nik', $sPayload['cashier_nik'])
                            ->orWhere('username', $sPayload['cashier_nik'])
                            ->first();
                    }

                    $uiTerminal = !empty($sPayload['terminal_code'])
                        ? strtoupper(trim((string) $sPayload['terminal_code']))
                        : $authoritativeTerminalCode;

                    $existing = CashierShift::where('uuid', $sUuid)->lockForUpdate()->first();
                    $openingCash = (float) ($sPayload['opening_cash'] ?? 0);
                    $cashIn = (float) ($sPayload['cash_in'] ?? 0);
                    $cashOut = (float) ($sPayload['cash_out'] ?? 0);
                    $status = strtoupper((string) ($sPayload['status'] ?? 'OPEN'));
                    $openedAt = !empty($sPayload['opened_at']) ? Carbon::parse($sPayload['opened_at']) : $now;
                    $closedAt = !empty($sPayload['closed_at']) ? Carbon::parse($sPayload['closed_at']) : ($status === 'CLOSED' ? $now : null);

                    if (!$existing) {
                        $shiftCode = trim((string) $sPayload['shift_code']);
                        if (CashierShift::where('shift_code', $shiftCode)->exists()) {
                            $shiftCode .= '-' . PosTerminal::canonicalBranchCode($authoritativeBranch) . '-' . Str::upper(substr(str_replace('-', '', $sUuid), -4));
                        }

                        // Close any stale open shift on the same branch + terminal before opening the synced shift
                        if ($status === 'OPEN') {
                            CashierShift::where('location_id', $authoritativeBranchId)
                                ->where('terminal_code', $uiTerminal)
                                ->where('status', 'OPEN')
                                ->where('uuid', '!=', $sUuid)
                                ->update(['status' => 'CLOSED', 'closed_at' => $openedAt]);
                        }

                        $created = CashierShift::create([
                            'uuid' => $sUuid,
                            'shift_code' => $shiftCode,
                            'terminal_code' => $uiTerminal,
                            'location_id' => $authoritativeBranchId,
                            'branch' => $authoritativeBranch->name,
                            'user_id' => $emp?->id,
                            'cashier_nik' => $sPayload['cashier_nik'] ?? $emp?->nik ?? $emp?->username ?? 'KASIR',
                            'cashier_name' => $sPayload['cashier_name'] ?? $emp?->name ?? 'Kasir',
                            'opening_cash' => $openingCash,
                            'cash_sales_total' => 0,
                            'qris_sales_total' => 0,
                            'credit_sales_total' => 0,
                            'total_sales' => 0,
                            'transaction_count' => 0,
                            'cash_in' => $cashIn,
                            'cash_out' => $cashOut,
                            'expected_cash' => round($openingCash + $cashIn - $cashOut, 2),
                            'actual_cash' => isset($sPayload['actual_cash']) ? (float) $sPayload['actual_cash'] : null,
                            'variance' => isset($sPayload['variance']) ? (float) $sPayload['variance'] : null,
                            'variance_status' => $sPayload['variance_status'] ?? null,
                            'closing_notes' => $sPayload['closing_notes'] ?? null,
                            'status' => $status,
                            'opened_at' => $openedAt,
                            'closed_at' => $closedAt,
                            'synced_at' => $now,
                        ]);

                        try {
                            \App\Http\Controllers\Api\Shared\NotificationController::notify(
                                role: 'MANAGER',
                                title: "Shift Kasir Aktif ({$authoritativeBranch->name}): {$uiTerminal}",
                                message: "Kasir {$created->cashier_name} membuka sesi {$created->shift_code} dengan modal awal Rp " . number_format($created->opening_cash, 0, ',', '.') . '.',
                                category: 'CASHIER',
                                link: '/dashboard',
                                icon: 'point_of_sale',
                                color: 'blue',
                                referenceId: $created->shift_code
                            );
                            \App\Http\Controllers\Api\Shared\NotificationController::notify(
                                role: 'OWNER',
                                title: "Sesi Kasir Dibuka ({$authoritativeBranch->name})",
                                message: "Kasir {$created->cashier_name} ({$uiTerminal}) aktif bertugas.",
                                category: 'CASHIER',
                                link: '/owner',
                                icon: 'point_of_sale',
                                color: 'emerald',
                                referenceId: $created->shift_code
                            );
                        } catch (Throwable $e) {}
                    } else {
                        $wasOpen = $existing->status === 'OPEN';
                        $existing->opening_cash = $openingCash;
                        $existing->cash_in = $cashIn;
                        $existing->cash_out = $cashOut;
                        $existing->expected_cash = $existing->calculateExpectedCash();
                        if (array_key_exists('actual_cash', $sPayload) && $sPayload['actual_cash'] !== null) {
                            $existing->actual_cash = (float) $sPayload['actual_cash'];
                            $existing->variance = round((float) $existing->actual_cash - (float) $existing->expected_cash, 2);
                            $existing->variance_status = $existing->variance < 0 ? 'KURANG' : ($existing->variance > 0 ? 'LEBIH' : 'SESUAI');
                        }
                        if (!empty($sPayload['closing_notes'])) {
                            $existing->closing_notes = $sPayload['closing_notes'];
                        }
                        $existing->status = $status;
                        if ($closedAt) {
                            $existing->closed_at = $closedAt;
                        }
                        $existing->synced_at = $now;
                        $existing->save();

                        if ($wasOpen && $status === 'CLOSED') {
                            try {
                                \App\Http\Controllers\Api\Shared\NotificationController::notify(
                                    role: 'MANAGER',
                                    title: "Tutup Shift ({$authoritativeBranch->name}): {$existing->terminal_code}",
                                    message: "Kasir {$existing->cashier_name} menutup sesi {$existing->shift_code}. Omzet: Rp " . number_format($existing->total_sales, 0, ',', '.'),
                                    category: 'CASHIER',
                                    link: '/reports',
                                    icon: 'savings',
                                    color: 'emerald',
                                    referenceId: $existing->shift_code
                                );
                            } catch (Throwable $e) {}
                        }
                    }

                    if ($status === 'CLOSED') {
                        CashierShift::where('location_id', $authoritativeBranchId)
                            ->where('terminal_code', $uiTerminal)
                            ->where('status', 'OPEN')
                            ->where('uuid', '!=', $sUuid)
                            ->update(['status' => 'CLOSED', 'closed_at' => $closedAt ?: $now]);
                    }

                    \Illuminate\Support\Facades\Cache::forget('owner_dashboard_summary_' . Carbon::today()->toDateString() . '_' . Carbon::today()->toDateString());

                    $shiftResults[] = ['uuid' => $sUuid, 'success' => true, 'status' => 'synced'];
                });
            } catch (Throwable $e) {
                $shiftResults[] = ['uuid' => $sUuid, 'success' => false, 'status' => 'failed', 'error' => $e->getMessage()];
            }
        }

        $attendanceResults = [];
        foreach ($validated['attendances'] ?? [] as $aPayload) {
            $aUuid = (string) $aPayload['uuid'];
            try {
                DB::transaction(function () use ($aPayload, $aUuid, $authoritativeBranch, $authoritativeTerminalCode, $now, &$attendanceResults) {
                    $emp = !empty($aPayload['employee_uuid'])
                        ? User::where('uuid', $aPayload['employee_uuid'])->first()
                        : User::where('name', $aPayload['staff_name'])->first();

                    $attDate = Carbon::parse($aPayload['date'])->toDateString();
                    $uiTerm = !empty($aPayload['terminal']) ? (string) $aPayload['terminal'] : $authoritativeTerminalCode;

                    $att = \App\Models\Attendance::where('uuid', $aUuid)->first();
                    if (!$att && $emp) {
                        $att = \App\Models\Attendance::whereDate('date', $attDate)
                            ->where('user_id', $emp->id)
                            ->latest('id')
                            ->first();
                    }

                    if (!$att) {
                        \App\Models\Attendance::create([
                            'uuid' => $aUuid,
                            'user_id' => $emp?->id,
                            'staff_name' => $emp?->name ?? $aPayload['staff_name'],
                            'terminal' => $uiTerm,
                            'branch' => $authoritativeBranch->name,
                            'date' => $attDate,
                            'check_in' => $aPayload['check_in'] ?? $now->format('H:i'),
                            'check_out' => $aPayload['check_out'] ?? '-',
                            'status' => $aPayload['status'] ?? 'HADIR',
                            'notes' => $aPayload['notes'] ?? null,
                            'synced_at' => $now,
                        ]);
                    } else {
                        $att->user_id = $emp?->id ?? $att->user_id;
                        $att->staff_name = $emp?->name ?? $aPayload['staff_name'];
                        if ($uiTerm !== '-') {
                            $att->terminal = $uiTerm;
                        }
                        $att->branch = $authoritativeBranch->name;
                        if (!empty($aPayload['check_in']) && $aPayload['check_in'] !== '-') {
                            $att->check_in = $aPayload['check_in'];
                        }
                        $att->check_out = $aPayload['check_out'] ?? $att->check_out;
                        $att->status = $aPayload['status'] ?? $att->status ?? 'HADIR';
                        if (!empty($aPayload['notes'])) {
                            $att->notes = $aPayload['notes'];
                        }
                        $att->synced_at = $now;
                        $att->save();
                    }

                    $attendanceResults[] = ['uuid' => $aUuid, 'success' => true, 'status' => 'synced'];
                });
            } catch (Throwable $e) {
                $attendanceResults[] = ['uuid' => $aUuid, 'success' => false, 'status' => 'failed', 'error' => $e->getMessage()];
            }
        }

        $auditResults = [];
        foreach ($validated['audit_logs'] ?? [] as $lPayload) {
            $lUuid = (string) $lPayload['uuid'];
            try {
                $existingLog = \App\Models\AuditLog::where('uuid', $lUuid)->first();
                if (!$existingLog) {
                    $emp = !empty($lPayload['employee_uuid'])
                        ? User::where('uuid', $lPayload['employee_uuid'])->first()
                        : null;

                    $logId = $lPayload['log_id'] ?? ('LOG-' . date('Ymd') . '-' . str_pad((string) random_int(1000, 9999), 4, '0', STR_PAD_LEFT));
                    if (\App\Models\AuditLog::where('log_id', $logId)->exists()) {
                        $logId .= '-' . Str::upper(substr(str_replace('-', '', $lUuid), -4));
                    }

                    $createdLog = \App\Models\AuditLog::create([
                        'uuid' => $lUuid,
                        'log_id' => $logId,
                        'user_id' => $emp?->id,
                        'user_name' => $emp?->name ?? $lPayload['user_name'],
                        'user_role' => $emp?->role ?? ($lPayload['user_role'] ?? 'Kasir'),
                        'location' => $authoritativeBranch->name,
                        'category' => $lPayload['category'],
                        'action_name' => $lPayload['action_name'],
                        'description' => $lPayload['description'],
                        'reference' => $lPayload['reference'] ?? '-',
                        'status' => $lPayload['status'] ?? 'BERHASIL',
                        'source' => $lPayload['source'] ?? ($authoritativeTerminalCode . ' / ' . $authoritativeBranch->name),
                        'changes' => $lPayload['changes'] ?? [],
                        'synced_at' => $now,
                    ]);

                    if (!empty($lPayload['created_at'])) {
                        \App\Models\AuditLog::where('id', $createdLog->id)->update([
                            'created_at' => Carbon::parse($lPayload['created_at']),
                        ]);
                    }
                }
                $auditResults[] = ['uuid' => $lUuid, 'success' => true, 'status' => 'synced'];
            } catch (Throwable $e) {
                $auditResults[] = ['uuid' => $lUuid, 'success' => false, 'status' => 'failed', 'error' => $e->getMessage()];
            }
        }

        $grResults = [];
        $servedLocationIds = \App\Http\Controllers\Api\V1\MasterDataSyncController::resolveServedStoreLocationIds(
            $request->input('served_location_uuids', []),
            $authoritativeBranchId
        );
        foreach ($validated['goods_receipts'] ?? [] as $grPayload) {
            $grUuid = (string) $grPayload['uuid'];
            try {
                DB::transaction(function () use ($grPayload, $grUuid, $servedLocationIds, $now, &$grResults) {
                    $receipt = \App\Models\GoodsReceipt::with('items.product')
                        ->where('uuid', $grUuid)
                        ->whereIn('to_location_id', $servedLocationIds)
                        ->lockForUpdate()
                        ->first();

                    if (!$receipt) {
                        $grResults[] = ['uuid' => $grUuid, 'success' => true, 'status' => 'already_synced'];
                        return;
                    }

                    $targetStatus = strtoupper((string) $grPayload['status']);
                    if (in_array($targetStatus, ['APPROVED', 'DITERIMA_LENGKAP', 'DITERIMA_SELISIH'], true) && $receipt->verified_at === null) {
                        $stockService = app(\App\Services\LocationStockService::class);
                        $payloadItemsByProdUuid = collect($grPayload['items'] ?? [])->keyBy('product_uuid');

                        foreach ($receipt->items as $item) {
                            $pUuid = $item->product?->uuid;
                            $matched = $pUuid ? $payloadItemsByProdUuid->get($pUuid) : null;
                            $qtyRec = $matched !== null
                                ? (int) round((float) $matched['qty_received'])
                                : (int) round((float) $item->qty_sent);

                            $item->qty_received = $qtyRec;
                            $item->condition = $matched['condition'] ?? 'BAIK';
                            if (!empty($matched['notes'])) {
                                $item->notes = $matched['notes'];
                            }
                            $item->save();

                            if ($qtyRec > 0) {
                                $stockService->addStock($item->product_id, (int) $receipt->to_location_id, $qtyRec, [
                                    'movement_type' => 'TRANSFER_IN',
                                    'unit_cost' => (float) $item->actual_unit_cost,
                                    'expired_date' => $item->expired_date?->toDateString(),
                                    'batch_number' => $item->batch_number,
                                    'reference_type' => \App\Models\GoodsReceipt::class,
                                    'reference_id' => $receipt->id,
                                    'reference_number' => $receipt->receipt_number,
                                    'notes' => "Verifikasi barang masuk sync {$receipt->receipt_number}",
                                ]);
                            }
                        }

                        $emp = !empty($grPayload['employee_uuid'])
                            ? User::where('uuid', $grPayload['employee_uuid'])->first()
                            : null;

                        $receipt->status = $targetStatus;
                        $receipt->verified_at = !empty($grPayload['verified_at']) ? Carbon::parse($grPayload['verified_at']) : $now;
                        $receipt->received_at = !empty($grPayload['received_at']) ? Carbon::parse($grPayload['received_at']) : $now;
                        $receipt->received_by = $emp?->id ?? $receipt->received_by;
                        if (!empty($grPayload['notes'])) {
                            $receipt->notes = $grPayload['notes'];
                        }
                        $receipt->synced_at = $now;
                        $receipt->save();
                    }

                    $grResults[] = ['uuid' => $grUuid, 'success' => true, 'status' => 'synced'];
                });
            } catch (Throwable $e) {
                $grResults[] = ['uuid' => $grUuid, 'success' => false, 'status' => 'failed', 'error' => $e->getMessage()];
            }
        }

        return response()->json([
            'success' => true,
            'results' => [
                'shifts' => $shiftResults,
                'attendances' => $attendanceResults,
                'audit_logs' => $auditResults,
                'goods_receipts' => $grResults,
            ],
        ]);
    }
}
