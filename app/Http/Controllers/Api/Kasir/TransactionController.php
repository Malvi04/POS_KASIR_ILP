<?php

namespace App\Http\Controllers\Api\Kasir;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Api\Shared\NotificationController;
use App\Models\AppSetting;
use App\Models\CashMovement;
use App\Models\CashierShift;
use App\Models\Member;
use App\Models\Product;
use App\Models\Transaction;
use App\Models\TransactionItem;
use App\Models\User;
use App\Services\LocationStockService;
use App\Services\PromotionEngine;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Carbon\Carbon;

class TransactionController extends Controller
{
    /**
     * Checkout & Process Payment with Multi-Type Payment Support.
     */
    public function checkout(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'offline_id' => 'nullable|string|max:120',
            'shift_id' => 'nullable|integer|exists:cashier_shifts,id',
            'terminal_code' => 'required|string|max:20',
            'location_id' => 'nullable|integer|exists:locations,id',
            'branch' => 'nullable|string|max:100',
            'cashier_nik' => 'nullable|string|max:50',
            'cashier_name' => 'nullable|string|max:100',
            'payment_method' => 'required|in:TUNAI,QRIS,TRANSFER,KREDIT,CAMPURAN',
            'cash_received' => 'nullable|numeric|min:0',
            'payment_reference' => 'nullable|string|max:100', // Referensi QRIS / Transfer
            'split_cash' => 'nullable|numeric|min:0',
            'split_non_cash' => 'nullable|numeric|min:0',
            'member_nik' => 'nullable|string|max:50', // Wajib untuk kredit anggota
            'items' => 'required|array|min:1',
            'items.*.sku' => 'nullable|string',
            'items.*.barcode' => 'nullable|string',
            'items.*.name' => 'required|string',
            'items.*.qty' => 'required|integer|min:1',
            'items.*.price' => 'required|numeric|min:0',
            'items.*.member_price' => 'nullable|numeric|min:0',
            'items.*.unit' => 'nullable|string',
            'items.*.is_member' => 'nullable|boolean',
            'items.*.promo_disabled' => 'nullable|boolean', // Kasir menonaktifkan promo untuk baris ini
            'promo_enabled' => 'nullable|boolean', // Saklar promo per transaksi dari kasir
        ]);

        return DB::transaction(function () use ($validated, $request) {
            // 0. Idempotency check by client-supplied offline_id (prevents duplicate on lost response retry)
            if (!empty($validated['offline_id'])) {
                $dupTrx = Transaction::where('offline_id', $validated['offline_id'])->first();
                if ($dupTrx) {
                    return response()->json([
                        'success' => true,
                        'duplicate' => true,
                        'message' => "Transaksi [{$dupTrx->invoice_number}] sudah tercatat sebelumnya.",
                        'data' => [
                            'id' => $dupTrx->id,
                            'invoice_number' => $dupTrx->invoice_number,
                            'terminal_code' => $dupTrx->terminal_code,
                            'cashier_name' => $dupTrx->cashier_name,
                            'payment_method' => $dupTrx->payment_method,
                            'grand_total' => (float) $dupTrx->grand_total,
                            'change_returned' => (float) $dupTrx->change_returned,
                            'created_at' => $dupTrx->created_at?->toIso8601String(),
                        ],
                    ], 200);
                }
            }

            $authUser = Auth::user();

            // 1. Verify Active Shift
            $shift = null;
            if (!empty($validated['shift_id'])) {
                $shift = CashierShift::lockForUpdate()->find($validated['shift_id']);
                if ($shift && $shift->terminal_code !== $validated['terminal_code']) {
                    return response()->json([
                        'success' => false,
                        'error_code' => 'SHIFT_TERMINAL_MISMATCH',
                        'message' => "Shift {$shift->shift_code} berada di terminal {$shift->terminal_code}, bukan {$validated['terminal_code']}.",
                    ], 422);
                }
            }
            if (!$shift || $shift->status !== 'OPEN') {
                $shift = CashierShift::where('terminal_code', $validated['terminal_code'])
                    ->where('status', 'OPEN')
                    ->latest('id')
                    ->lockForUpdate()
                    ->first();
            }

            if (!$shift) {
                return response()->json([
                    'success' => false,
                    'error_code' => 'SHIFT_NOT_OPEN',
                    'message' => 'Tidak ada sesi shift kasir yang aktif pada terminal ini. Kasir wajib menginput modal awal sebelum transaksi penjualan pertama.',
                ], 422);
            }

            if ($authUser && $authUser->isKasir()) {
                $ownsShift = ($shift->user_id && $shift->user_id === $authUser->id)
                    || ($shift->cashier_nik && ($shift->cashier_nik === $authUser->nik || $shift->cashier_nik === $authUser->username));
                if (!$ownsShift) {
                    return response()->json([
                        'success' => false,
                        'error_code' => 'SHIFT_FORBIDDEN',
                        'message' => "Anda tidak diizinkan menggunakan sesi shift milik kasir lain ({$shift->cashier_name}).",
                    ], 403);
                }
            }

            $user = ($authUser && $authUser->isKasir())
                ? $authUser
                : User::where('nik', $validated['cashier_nik'] ?? $shift->cashier_nik)->first();
            $cashierName = ($authUser && $authUser->isKasir())
                ? $authUser->name
                : ($validated['cashier_name'] ?? $shift->cashier_name);

            $stockService = app(LocationStockService::class);
            $terminalExpected = $stockService->resolveExpectedLocationForTerminal($shift->terminal_code);
            $storeLocation = $stockService->resolveLocation(
                $validated['location_id'] ?? $shift->location_id,
                $validated['branch'] ?? $shift->branch,
                $shift->terminal_code,
                $user,
                'STORE'
            );

            if ((!empty($validated['location_id']) || !empty($validated['branch'])) && $terminalExpected && $storeLocation && $terminalExpected->id !== $storeLocation->id) {
                return response()->json([
                    'success' => false,
                    'error_code' => 'TERMINAL_LOCATION_MISMATCH',
                    'message' => "Terminal {$shift->terminal_code} terdaftar di {$terminalExpected->name}, tidak sesuai dengan lokasi {$storeLocation->name}.",
                ], 422);
            }

            if ($authUser && $authUser->isKasir()) {
                $locToVerify = $storeLocation ?: $terminalExpected;
                if ($locToVerify && !$stockService->isUserAllowedInLocation($authUser, $locToVerify)) {
                    return response()->json([
                        'success' => false,
                        'error_code' => 'LOCATION_FORBIDDEN',
                        'message' => "Akun Kasir Anda tidak diizinkan bertransaksi pada lokasi {$locToVerify->name}.",
                    ], 403);
                }
            }

            $useStoreStock = $storeLocation !== null && (
                $request->filled('location_id')
                || $request->filled('branch')
                || $stockService->hasStoreStocks()
            );

            // 2. Member validation if member_nik is passed
            $member = null;
            if (!empty($validated['member_nik'])) {
                $member = Member::where('nik', $validated['member_nik'])->lockForUpdate()->first();
                if (!$member) {
                    return response()->json([
                        'success' => false,
                        'error_code' => 'MEMBER_NOT_FOUND',
                        'message' => "NIK Anggota [{$validated['member_nik']}] tidak terdaftar dalam database koperasi.",
                    ], 404);
                }
            }

            // 3. Process Items, calculate subtotals, taxes, and auto-discounts
            $taxSettingVal = AppSetting::get('tax_enabled', '1');
            $isGlobalTaxActive = ($taxSettingVal === '1' || $taxSettingVal === 'true' || $taxSettingVal === true);
            $globalTaxRate = (float) AppSetting::get('tax_rate_percent', 11.0);

            $subtotal = 0;
            $discountTotal = 0;
            $taxTotal = 0;
            $processedItems = [];

            foreach ($validated['items'] as $rawItem) {
                $product = null;
                if (!empty($rawItem['barcode'])) {
                    $product = Product::where('barcode', $rawItem['barcode'])->lockForUpdate()->first();
                    if (!$product) {
                        $pUnit = \App\Models\ProductUnit::where('barcode', $rawItem['barcode'])->first();
                        if ($pUnit) {
                            $product = Product::lockForUpdate()->find($pUnit->product_id);
                            if (empty($rawItem['unit'])) {
                                $rawItem['unit'] = $pUnit->unit_name;
                            }
                        }
                    }
                }
                if (!$product && !empty($rawItem['sku'])) {
                    $product = Product::where('sku', $rawItem['sku'])->lockForUpdate()->first();
                }

                $qty = (int) $rawItem['qty'];
                $isMemberMode = (bool) ($rawItem['is_member'] ?? ($member !== null));

                if ($product) {
                    $itemUnit = $rawItem['unit'] ?? $product->unit;
                    $baseQtyToDeduct = $product->convertToBaseUnits($itemUnit, $qty);

                    $primaryBatchId = null;
                    $batchAllocations = null;

                    if ($useStoreStock && $storeLocation) {
                        $deductRes = $stockService->deductStock($product->id, $storeLocation->id, $baseQtyToDeduct, [
                            'movement_type' => 'SALE_OUT',
                            'reference_type' => Transaction::class,
                            'user_id' => $user?->id,
                            'notes' => "Penjualan POS {$shift->terminal_code} ({$storeLocation->name})",
                        ]);

                        if (!$deductRes['success']) {
                            return response()->json([
                                'success' => false,
                                'error_code' => $deductRes['error_code'] ?? 'STOCK_INSUFFICIENT',
                                'message' => "[{$product->name}] " . ($deductRes['message'] ?? 'Stok toko tidak mencukupi.'),
                            ], 422);
                        }

                        $primaryBatchId = $deductRes['primary_batch_id'] ?? null;
                        $batchAllocations = !empty($deductRes['allocations']) ? $deductRes['allocations'] : null;
                        $product->refresh();
                    } else {
                        // Legacy compatibility mode (no Stock Toko rows initialized yet)
                        if ($product->stock < $baseQtyToDeduct) {
                            return response()->json([
                                'success' => false,
                                'error_code' => 'STOCK_INSUFFICIENT',
                                'message' => "Stok produk [{$product->name}] tidak mencukupi. Sisa stok: {$product->stock} {$product->unit}, permintaan: {$qty} {$itemUnit} ({$baseQtyToDeduct} {$product->unit}).",
                            ], 422);
                        }

                        $product->stock -= $baseQtyToDeduct;
                        if ($product->stock <= 0) {
                            $product->status = 'HABIS';
                        } elseif ($product->stock <= $product->min_stock) {
                            $product->status = 'STOK_RENDAH';
                        }
                        $product->save();
                    }

                    // Auto-Discount Expired (H-14 warning rule)
                    $isExpiredClearance = $product->is_critical_expired;
                    $discountPercent = $product->effective_discount_percent;

                    $unitBasePrice = $isMemberMode ? ($product->member_price ?? $product->price) : $product->price;
                    $unitDiscount = $isExpiredClearance ? round($unitBasePrice * ($discountPercent / 100), 2) : 0;
                    $unitFinalPrice = max(0, $unitBasePrice - $unitDiscount);

                    $itemRate = ($product->tax_rate !== null && $product->tax_rate > 0) ? (float) $product->tax_rate : $globalTaxRate;

                    // Totals dihitung setelah Promo Engine berjalan (lihat langkah 3b)
                    $processedItems[] = [
                        'product_id' => $product->id,
                        'stock_batch_id' => $primaryBatchId,
                        'batch_allocations' => $batchAllocations,
                        'sku' => $product->sku,
                        'barcode' => $product->barcode,
                        'product_name' => $product->name,
                        'unit' => $product->unit,
                        'unit_price' => $unitFinalPrice,
                        'qty' => $qty,
                        'is_taxable' => (bool) $product->is_taxable,
                        'tax_amount' => 0,
                        'is_expired_clearance' => $isExpiredClearance,
                        'clearance_discount_percent' => $discountPercent,
                        'discount_amount' => round($unitDiscount * $qty, 2),
                        'total_price' => 0,
                        '_category' => $product->category,
                        '_tax_rate' => ($isGlobalTaxActive && $product->is_taxable) ? $itemRate : 0,
                        '_promo_disabled' => (bool) ($rawItem['promo_disabled'] ?? false),
                    ];
                } else {
                    // Fallback item if not in master db
                    $unitPrice = (float) $rawItem['price'];

                    $processedItems[] = [
                        'product_id' => null,
                        'sku' => $rawItem['sku'] ?? 'GEN-ITEM',
                        'barcode' => $rawItem['barcode'] ?? '-',
                        'product_name' => $rawItem['name'],
                        'unit' => $rawItem['unit'] ?? 'Pcs',
                        'unit_price' => $unitPrice,
                        'qty' => $qty,
                        'is_taxable' => true,
                        'tax_amount' => 0,
                        'is_expired_clearance' => false,
                        'clearance_discount_percent' => 0,
                        'discount_amount' => 0,
                        'total_price' => 0,
                        '_category' => null,
                        '_tax_rate' => $isGlobalTaxActive ? $globalTaxRate : 0,
                        '_promo_disabled' => true,
                    ];
                }
            }

            // 3b. Promo Engine (ala Alfamart) — diatur penuh oleh Manager.
            $promoSettings = PromotionEngine::settings();
            $allowCashierToggle = (bool) $promoSettings['allowCashierToggle'];
            $promoEnabled = $allowCashierToggle ? (bool) ($validated['promo_enabled'] ?? true) : true;

            $promoLines = app(PromotionEngine::class)->apply(
                array_map(fn ($it) => [
                    'product_id' => $it['product_id'],
                    'sku' => $it['sku'],
                    'category' => $it['_category'],
                    'unit_price' => $it['unit_price'],
                    'qty' => $it['qty'],
                    'is_clearance' => $it['is_expired_clearance'],
                    'promo_disabled' => $allowCashierToggle ? $it['_promo_disabled'] : ($it['product_id'] === null),
                ], $processedItems),
                $member !== null || collect($validated['items'])->contains(fn ($r) => !empty($r['is_member'])),
                $promoEnabled
            );

            $promoDiscountTotal = 0;
            foreach ($processedItems as $idx => &$it) {
                $promoDisc = (float) ($promoLines[$idx]['promo_discount'] ?? 0);
                $lineSubtotal = max(0, round($it['unit_price'] * $it['qty'] - $promoDisc, 2));
                $lineTax = $it['_tax_rate'] > 0 ? round($lineSubtotal * ($it['_tax_rate'] / 100), 2) : 0;

                $it['promotion_id'] = $promoLines[$idx]['promotion_id'] ?? null;
                $it['promo_name'] = $promoLines[$idx]['promo_name'] ?? null;
                $it['promo_discount'] = $promoDisc;
                $it['discount_amount'] = round($it['discount_amount'] + $promoDisc, 2);
                $it['total_price'] = $lineSubtotal;
                $it['tax_amount'] = $lineTax;

                $subtotal += $lineSubtotal;
                $discountTotal += $it['discount_amount'];
                $taxTotal += $lineTax;
                $promoDiscountTotal += $promoDisc;

                unset($it['_category'], $it['_tax_rate'], $it['_promo_disabled']);
            }
            unset($it);

            $grandTotal = round($subtotal + $taxTotal, 2);

            // 4. Payment Method Validations
            $method = strtoupper($validated['payment_method']);
            $cashReceived = (float) ($validated['cash_received'] ?? 0);
            $changeReturned = 0;
            $paymentReference = $validated['payment_reference'] ?? null;
            $splitCash = (float) ($validated['split_cash'] ?? 0);
            $splitNonCash = (float) ($validated['split_non_cash'] ?? 0);

            if ($method === 'TUNAI') {
                if ($cashReceived < $grandTotal) {
                    return response()->json([
                        'success' => false,
                        'error_code' => 'INSUFFICIENT_CASH',
                        'message' => 'Uang tunai yang diterima (Rp ' . number_format($cashReceived, 0, ',', '.') . ') kurang dari total tagihan (Rp ' . number_format($grandTotal, 0, ',', '.') . ').',
                    ], 422);
                }
                $changeReturned = round($cashReceived - $grandTotal, 2);
            } elseif ($method === 'QRIS' || $method === 'TRANSFER') {
                // QRIS / Transfer: Catat nomor referensi
                if (empty($paymentReference)) {
                    $paymentReference = 'REF-' . date('YmdHis') . '-' . Str::upper(Str::random(4));
                }
            } elseif ($method === 'KREDIT') {
                // Validasi Kredit Anggota (Kasbon Potong Gaji)
                if (!$member) {
                    return response()->json([
                        'success' => false,
                        'error_code' => 'MEMBER_REQUIRED',
                        'message' => 'Pembayaran Kredit Anggota mewajibkan penginputan NIK anggota koperasi.',
                    ], 422);
                }

                $eligibility = $member->checkCreditEligibility($grandTotal);
                if (!$eligibility['eligible']) {
                    return response()->json([
                        'success' => false,
                        'error_code' => 'CREDIT_LIMIT_EXCEEDED',
                        'message' => $eligibility['reason'],
                        'data' => [
                            'credit_limit' => $member->credit_limit,
                            'current_debt' => $member->current_debt,
                            'remaining_credit' => $member->remaining_credit,
                            'grand_total' => $grandTotal,
                        ],
                    ], 422);
                }
            } elseif ($method === 'CAMPURAN') {
                if ($splitCash + $splitNonCash < $grandTotal) {
                    return response()->json([
                        'success' => false,
                        'error_code' => 'SPLIT_INSUFFICIENT',
                        'message' => 'Total kombinasi pembayaran tunai dan non-tunai belum mencukupi total tagihan belanja.',
                    ], 422);
                }
            }

            // 5. Generate Collision-Resistant Unique Invoice Number
            $datePrefix = date('Ymd');
            $maxId = (int) (Transaction::max('id') ?? 0);
            $randomSuffix = Str::upper(Str::random(3));
            $invoiceNumber = "TRX-{$datePrefix}-" . Str::padLeft((string) ($maxId + 1), 4, '0') . "-{$randomSuffix}";
            while (Transaction::where('invoice_number', $invoiceNumber)->exists()) {
                $maxId++;
                $randomSuffix = Str::upper(Str::random(4));
                $invoiceNumber = "TRX-{$datePrefix}-" . Str::padLeft((string) $maxId, 4, '0') . "-{$randomSuffix}";
            }

            $transaction = Transaction::create([
                'invoice_number' => $invoiceNumber,
                'offline_id' => $validated['offline_id'] ?? null,
                'shift_id' => $shift->id,
                'user_id' => $user?->id,
                'cashier_name' => $cashierName,
                'terminal_code' => $shift->terminal_code,
                'location_id' => $storeLocation?->id,
                'branch' => $storeLocation?->name ?? $shift->branch,
                'member_id' => $member?->id,
                'member_nik' => $member?->nik,
                'member_name' => $member?->name,
                'payment_method' => $method,
                'subtotal' => $subtotal,
                'discount_total' => $discountTotal,
                'promo_discount_total' => $promoDiscountTotal,
                'promo_enabled' => $promoEnabled,
                'tax_total' => $taxTotal,
                'grand_total' => $grandTotal,
                'cash_received' => $method === 'TUNAI' ? $cashReceived : 0,
                'change_returned' => $changeReturned,
                'payment_reference' => $paymentReference,
                'split_cash' => $method === 'CAMPURAN' ? $splitCash : 0,
                'split_non_cash' => $method === 'CAMPURAN' ? $splitNonCash : 0,
                'status' => 'SUCCESS',
            ]);

            // 6. Save Transaction Items
            foreach ($processedItems as $itemData) {
                if ($itemData['product_id']) {
                    TransactionItem::create(array_merge($itemData, ['transaction_id' => $transaction->id]));
                }
            }

            // 6b. Deduct credit ceiling & record audit mutation if KREDIT
            if ($method === 'KREDIT' && $member) {
                $branchName = $user?->branch ?? ($shift->terminal_code ? "Terminal {$shift->terminal_code}" : 'Cabang 1 - Mill / Pabrik');
                $member->recordKasbon(
                    amount: $grandTotal,
                    invoiceNumber: $invoiceNumber,
                    transactionId: $transaction->id,
                    branch: $branchName,
                    processedBy: $cashierName,
                    notes: "Belanja Kasbon POS (Invoice: {$invoiceNumber})"
                );
            }

            // 7. Update Shift Financial Figures
            if ($method === 'TUNAI') {
                $shift->cash_sales_total += $grandTotal;
            } elseif ($method === 'QRIS' || $method === 'TRANSFER') {
                $shift->qris_sales_total += $grandTotal;
            } elseif ($method === 'KREDIT') {
                $shift->credit_sales_total += $grandTotal;
            } elseif ($method === 'CAMPURAN') {
                $shift->cash_sales_total += $splitCash;
                $shift->qris_sales_total += $splitNonCash;
            }

            $shift->total_sales += $grandTotal;
            $shift->transaction_count += 1;
            $shift->expected_cash = $shift->calculateExpectedCash();
            $shift->save();

            try {
                // If paid by member credit, notify manager and owner
                if ($method === 'KREDIT' && $member) {
                    NotificationController::notify(
                        role: 'MANAGER',
                        title: "Transaksi Kasbon Anggota: {$member->name}",
                        message: "Anggota {$member->name} (NIK: {$member->nik}) bertransaksi kasbon senilai Rp " . number_format($grandTotal, 0, ',', '.') . " di kasir {$cashierName}.",
                        category: 'MEMBER',
                        link: '/manager/members',
                        icon: 'credit_card',
                        color: 'blue',
                        referenceId: $invoiceNumber
                    );
                }

                // If high value transaction (> Rp 1.000.000), notify Owner
                if ($grandTotal >= 1000000) {
                    NotificationController::notify(
                        role: 'OWNER',
                        title: "Penjualan Bernilai Besar: Rp " . number_format($grandTotal, 0, ',', '.'),
                        message: "Nota {$invoiceNumber} di {$shift->terminal_code} mencatat penjualan Rp " . number_format($grandTotal, 0, ',', '.') . " ({$method}).",
                        category: 'SALES',
                        link: '/owner/reports',
                        icon: 'trending_up',
                        color: 'emerald',
                        referenceId: $invoiceNumber
                    );
                }

                // Invalidate real-time micro-cache
                \Illuminate\Support\Facades\Cache::forget('owner_dashboard_summary_' . \Carbon\Carbon::today()->toDateString());

                // Broadcast Realtime Events via Reverb WebSocket
                event(new \App\Events\TransactionCreatedEvent($transaction));
                event(new \App\Events\StockUpdatedEvent([
                    'location_id' => $shift->location_id ?? null,
                    'type' => 'checkout',
                    'invoice_number' => $invoiceNumber,
                    'items_count' => count($processedItems),
                ]));
            } catch (\Throwable $e) {}

            return response()->json([
                'success' => true,
                'message' => "Transaksi [{$invoiceNumber}] berhasil diselesaikan.",
                'data' => [
                    'id' => $transaction->id,
                    'invoice_number' => $transaction->invoice_number,
                    'terminal_code' => $transaction->terminal_code,
                    'cashier_name' => $transaction->cashier_name,
                    'payment_method' => $transaction->payment_method,
                    'payment_reference' => $transaction->payment_reference,
                    'subtotal' => (float) $transaction->subtotal,
                    'discount_total' => (float) $transaction->discount_total,
                    'promo_discount_total' => (float) $promoDiscountTotal,
                    'promo_enabled' => (bool) $promoEnabled,
                    'items' => array_map(fn ($it) => [
                        'sku' => $it['sku'],
                        'name' => $it['product_name'],
                        'qty' => $it['qty'],
                        'unit_price' => (float) $it['unit_price'],
                        'total_price' => (float) $it['total_price'],
                        'promotion_id' => $it['promotion_id'] ?? null,
                        'promo_name' => $it['promo_name'] ?? null,
                        'promo_discount' => (float) ($it['promo_discount'] ?? 0),
                    ], $processedItems),
                    'tax_total' => (float) $transaction->tax_total,
                    'grand_total' => (float) $transaction->grand_total,
                    'cash_received' => (float) $transaction->cash_received,
                    'change_returned' => (float) $transaction->change_returned,
                    'member' => $member ? [
                        'nik' => $member->nik,
                        'name' => $member->name,
                        'remaining_credit' => (float) $member->remaining_credit,
                    ] : null,
                    'created_at' => $transaction->created_at->toIso8601String(),
                    'items_count' => count($processedItems),
                ],
            ], 201);
        });
    }

    /**
     * Batch Sync Offline Transactions & Cash Movements.
     */
    public function syncOffline(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'offline_shifts' => 'nullable|array',
            'offline_shifts.*.offline_id' => 'required|string|max:120',
            'offline_shifts.*.action' => 'required|in:OPEN,CLOSE',
            'offline_shifts.*.shift_code' => 'nullable|string|max:80',
            'offline_shifts.*.terminal_code' => 'required|string|max:50',
            'offline_shifts.*.location_id' => 'nullable|integer|exists:locations,id',
            'offline_shifts.*.branch' => 'nullable|string|max:100',
            'offline_shifts.*.cashier_nik' => 'nullable|string|max:50',
            'offline_shifts.*.cashier_name' => 'nullable|string|max:100',
            'offline_shifts.*.opening_cash' => 'nullable|numeric|min:0',
            'offline_shifts.*.actual_cash' => 'nullable|numeric|min:0',
            'offline_shifts.*.closing_notes' => 'nullable|string|max:500',
            'offline_shifts.*.opened_at' => 'nullable|string',
            'offline_shifts.*.closed_at' => 'nullable|string',
            'transactions' => 'nullable|array',
            'transactions.*.offline_id' => 'required|string|max:120',
            'transactions.*.invoice_number' => 'required|string|max:100',
            'transactions.*.terminal_code' => 'required|string|max:50',
            'transactions.*.location_id' => 'nullable|integer|exists:locations,id',
            'transactions.*.branch' => 'nullable|string|max:100',
            'transactions.*.cashier_nik' => 'nullable|string|max:50',
            'transactions.*.cashier_name' => 'nullable|string|max:100',
            'transactions.*.payment_method' => 'required|string',
            'transactions.*.cash_received' => 'nullable|numeric|min:0',
            'transactions.*.change_returned' => 'nullable|numeric|min:0',
            'transactions.*.payment_reference' => 'nullable|string|max:120',
            'transactions.*.split_cash' => 'nullable|numeric|min:0',
            'transactions.*.split_non_cash' => 'nullable|numeric|min:0',
            'transactions.*.member_nik' => 'nullable|string|max:50',
            'transactions.*.created_at' => 'nullable|string',
            'transactions.*.subtotal' => 'nullable|numeric|min:0',
            'transactions.*.discount_total' => 'nullable|numeric|min:0',
            'transactions.*.tax_total' => 'nullable|numeric|min:0',
            'transactions.*.grand_total' => 'required|numeric|min:0',
            'transactions.*.items' => 'required|array|min:1',
            'cash_movements' => 'nullable|array',
            'cash_movements.*.offline_id' => 'required|string|max:120',
            'cash_movements.*.shift_code' => 'nullable|string|max:80',
            'cash_movements.*.terminal_code' => 'required|string|max:50',
            'cash_movements.*.location_id' => 'nullable|integer|exists:locations,id',
            'cash_movements.*.branch' => 'nullable|string|max:100',
            'cash_movements.*.cashier_nik' => 'nullable|string|max:50',
            'cash_movements.*.type' => 'required|string',
            'cash_movements.*.amount' => 'required|numeric|min:1',
            'cash_movements.*.reason' => 'nullable|string|max:255',
            'cash_movements.*.created_at' => 'nullable|string',
        ]);

        $authUser = Auth::user();
        $stockService = app(LocationStockService::class);
        $shiftsToSync = $validated['offline_shifts'] ?? [];
        $transactionsToSync = $validated['transactions'] ?? [];
        $movementsToSync = $validated['cash_movements'] ?? [];

        // Pre-flight identity & location security guard across all items before mutating DB
        foreach (array_merge($shiftsToSync, $transactionsToSync, $movementsToSync) as $payloadItem) {
            $authError = $this->validateOfflineSyncPayloadAuth($authUser, $payloadItem, $stockService);
            if ($authError !== null) {
                return $authError;
            }
        }

        $openShifts = array_values(array_filter($shiftsToSync, fn ($s) => strtoupper($s['action'] ?? '') === 'OPEN'));
        $closeShifts = array_values(array_filter($shiftsToSync, fn ($s) => strtoupper($s['action'] ?? '') === 'CLOSE'));

        $shiftResults = [];
        foreach ($openShifts as $shiftData) {
            $shiftResults[] = $this->syncOfflineShiftItem($shiftData, $authUser, $stockService);
        }

        $trxResults = [];
        foreach ($transactionsToSync as $trxData) {
            $trxResults[] = $this->syncOfflineTransactionItem($trxData, $authUser, $stockService);
        }

        $movResults = [];
        foreach ($movementsToSync as $movData) {
            $movResults[] = $this->syncOfflineCashMovementItem($movData, $authUser, $stockService);
        }

        foreach ($closeShifts as $shiftData) {
            $shiftResults[] = $this->syncOfflineShiftItem($shiftData, $authUser, $stockService);
        }

        $syncedCount = count(array_filter($trxResults, fn ($r) => $r['status'] === 'SYNCED'));
        $duplicateCount = count(array_filter($trxResults, fn ($r) => $r['status'] === 'ALREADY_SYNCED'));
        $failedCount = count(array_filter($trxResults, fn ($r) => $r['status'] === 'FAILED'))
            + count(array_filter($movResults, fn ($r) => $r['status'] === 'FAILED'))
            + count(array_filter($shiftResults, fn ($r) => $r['status'] === 'FAILED'));

        if ($syncedCount > 0) {
            try {
                NotificationController::notify(
                    role: 'MANAGER',
                    title: "Sinkronisasi Offline Selesai: {$syncedCount} Transaksi",
                    message: "Sistem berhasil menyinkronkan {$syncedCount} transaksi kasir yang tercatat saat offline ke database server utama.",
                    category: 'SALES',
                    link: '/kasir/transactions',
                    icon: 'cloud_sync',
                    color: 'emerald',
                    referenceId: 'SYNC-' . date('YmdHis')
                );
            } catch (\Throwable $e) {}
        }

        return response()->json([
            'success' => true,
            'partial' => $failedCount > 0,
            'message' => "Sinkronisasi diproses ({$syncedCount} transaksi baru, {$duplicateCount} duplikat, {$failedCount} gagal).",
            'synced_count' => $syncedCount,
            'duplicate_count' => $duplicateCount,
            'failed_count' => $failedCount,
            'results' => [
                'shifts' => $shiftResults,
                'transactions' => $trxResults,
                'cash_movements' => $movResults,
            ],
        ]);
    }

    /**
     * Ensure authenticated Kasir cannot forge cashier identity, terminal, or store location during offline sync.
     */
    protected function validateOfflineSyncPayloadAuth(?User $authUser, array $item, LocationStockService $stockService): ?JsonResponse
    {
        $terminalCode = $item['terminal_code'] ?? '';
        $terminalExpected = $stockService->resolveExpectedLocationForTerminal($terminalCode);
        $explicitLoc = (!empty($item['location_id']) || !empty($item['branch']))
            ? $stockService->resolveLocation($item['location_id'] ?? null, $item['branch'] ?? null, null, null, 'STORE')
            : null;

        if ((!empty($item['location_id']) || !empty($item['branch'])) && $terminalExpected && $explicitLoc && $terminalExpected->id !== $explicitLoc->id) {
            return response()->json([
                'success' => false,
                'error_code' => 'TERMINAL_LOCATION_MISMATCH',
                'message' => "Terminal {$terminalCode} terdaftar di {$terminalExpected->name}, tidak cocok dengan lokasi {$explicitLoc->name}.",
            ], 422);
        }

        if ($authUser && $authUser->isKasir()) {
            if (!empty($item['cashier_nik']) && $item['cashier_nik'] !== $authUser->nik && $item['cashier_nik'] !== $authUser->username) {
                return response()->json([
                    'success' => false,
                    'error_code' => 'IDENTITY_MISMATCH',
                    'message' => "Identitas kasir pada payload sinkronisasi ({$item['cashier_nik']}) tidak sesuai dengan sesi akun Anda ({$authUser->nik}).",
                ], 403);
            }

            $targetLoc = $explicitLoc ?: $terminalExpected;
            if ($targetLoc && !$stockService->isUserAllowedInLocation($authUser, $targetLoc)) {
                return response()->json([
                    'success' => false,
                    'error_code' => 'LOCATION_FORBIDDEN',
                    'message' => "Akun Kasir Anda tidak diizinkan menyinkronkan data untuk lokasi {$targetLoc->name}.",
                ], 403);
            }
        }

        return null;
    }

    /**
     * Process a single offline shift OPEN or CLOSE item idempotently.
     */
    protected function syncOfflineShiftItem(array $shiftData, ?User $authUser, LocationStockService $stockService): array
    {
        $offlineId = $shiftData['offline_id'];
        $action = strtoupper($shiftData['action']);
        $terminalCode = $shiftData['terminal_code'];

        try {
            return DB::transaction(function () use ($shiftData, $offlineId, $action, $terminalCode, $authUser, $stockService) {
                if ($action === 'OPEN') {
                    $existingByOffId = CashierShift::where('offline_id', $offlineId)->first();
                    if (!$existingByOffId && !empty($shiftData['shift_code'])) {
                        $existingByOffId = CashierShift::where('shift_code', $shiftData['shift_code'])->first();
                    }
                    if ($existingByOffId) {
                        return [
                            'offline_id' => $offlineId,
                            'shift_code' => $existingByOffId->shift_code,
                            'server_id' => $existingByOffId->id,
                            'action' => 'OPEN',
                            'status' => 'ALREADY_SYNCED',
                        ];
                    }

                    $openOnTerminal = CashierShift::where('terminal_code', $terminalCode)->where('status', 'OPEN')->first();
                    if ($openOnTerminal) {
                        return [
                            'offline_id' => $offlineId,
                            'shift_code' => $openOnTerminal->shift_code,
                            'server_id' => $openOnTerminal->id,
                            'action' => 'OPEN',
                            'status' => 'ALREADY_SYNCED',
                        ];
                    }

                    $user = ($authUser && $authUser->isKasir())
                        ? $authUser
                        : ((!empty($shiftData['cashier_nik']) ? User::where('nik', $shiftData['cashier_nik'])->first() : null) ?? $authUser);

                    $resolvedLoc = $stockService->resolveLocation(
                        $shiftData['location_id'] ?? null,
                        $shiftData['branch'] ?? null,
                        $terminalCode,
                        $user,
                        'STORE'
                    );

                    $shiftCode = !empty($shiftData['shift_code'])
                        ? $shiftData['shift_code']
                        : ('CS-' . date('Ymd') . '-' . strtoupper(Str::slug($terminalCode, '')) . '-' . Str::upper(Str::random(4)));

                    $openingCash = (float) ($shiftData['opening_cash'] ?? 0);
                    $openedAt = !empty($shiftData['opened_at']) ? Carbon::parse($shiftData['opened_at']) : now();

                    $shift = CashierShift::create([
                        'shift_code' => $shiftCode,
                        'offline_id' => $offlineId,
                        'terminal_code' => $terminalCode,
                        'location_id' => $resolvedLoc?->id,
                        'branch' => $resolvedLoc?->name ?? ($shiftData['branch'] ?? $user?->branch ?? 'Toko Pusat'),
                        'user_id' => $user?->id,
                        'cashier_nik' => $user?->nik ?? ($shiftData['cashier_nik'] ?? 'OFFLINE'),
                        'cashier_name' => $user?->name ?? ($shiftData['cashier_name'] ?? 'Kasir Offline'),
                        'opening_cash' => $openingCash,
                        'cash_sales_total' => 0,
                        'qris_sales_total' => 0,
                        'credit_sales_total' => 0,
                        'total_sales' => 0,
                        'transaction_count' => 0,
                        'cash_in' => 0,
                        'cash_out' => 0,
                        'expected_cash' => $openingCash,
                        'status' => 'OPEN',
                        'opened_at' => $openedAt,
                    ]);

                    return [
                        'offline_id' => $offlineId,
                        'shift_code' => $shift->shift_code,
                        'server_id' => $shift->id,
                        'action' => 'OPEN',
                        'status' => 'SYNCED',
                    ];
                }

                // action === 'CLOSE'
                $shift = !empty($shiftData['shift_code'])
                    ? CashierShift::where('shift_code', $shiftData['shift_code'])->lockForUpdate()->first()
                    : null;
                if (!$shift) {
                    $shift = CashierShift::where('terminal_code', $terminalCode)
                        ->where('status', 'OPEN')
                        ->latest('id')
                        ->lockForUpdate()
                        ->first();
                }

                if (!$shift) {
                    return [
                        'offline_id' => $offlineId,
                        'action' => 'CLOSE',
                        'status' => 'FAILED',
                        'message' => "Shift aktif pada terminal {$terminalCode} tidak ditemukan untuk ditutup.",
                    ];
                }

                if ($shift->status === 'CLOSED') {
                    return [
                        'offline_id' => $offlineId,
                        'shift_code' => $shift->shift_code,
                        'server_id' => $shift->id,
                        'action' => 'CLOSE',
                        'status' => 'ALREADY_SYNCED',
                    ];
                }

                $actualCash = (float) ($shiftData['actual_cash'] ?? $shift->expected_cash);
                $shift->closeShift($actualCash, $shiftData['closing_notes'] ?? null);
                if (!empty($shiftData['closed_at'])) {
                    $shift->closed_at = Carbon::parse($shiftData['closed_at']);
                    $shift->save();
                }

                return [
                    'offline_id' => $offlineId,
                    'shift_code' => $shift->shift_code,
                    'server_id' => $shift->id,
                    'action' => 'CLOSE',
                    'status' => 'SYNCED',
                ];
            });
        } catch (\Throwable $e) {
            return [
                'offline_id' => $offlineId,
                'action' => $action,
                'status' => 'FAILED',
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Process a single offline transaction atomically with idempotency and location-scoped FEFO/FIFO deduction.
     */
    protected function syncOfflineTransactionItem(array $trxData, ?User $authUser, LocationStockService $stockService): array
    {
        $offlineId = $trxData['offline_id'];
        $invoiceNumber = $trxData['invoice_number'];

        try {
            return DB::transaction(function () use ($trxData, $offlineId, $invoiceNumber, $authUser, $stockService) {
                $existing = Transaction::where('offline_id', $offlineId)
                    ->orWhere('invoice_number', $invoiceNumber)
                    ->first();
                if ($existing) {
                    return [
                        'offline_id' => $offlineId,
                        'invoice_number' => $existing->invoice_number,
                        'server_id' => $existing->id,
                        'status' => 'ALREADY_SYNCED',
                    ];
                }

                $terminalCode = $trxData['terminal_code'];
                $shift = CashierShift::where('terminal_code', $terminalCode)
                    ->where('status', 'OPEN')
                    ->latest('id')
                    ->lockForUpdate()
                    ->first()
                    ?? CashierShift::where('terminal_code', $terminalCode)
                        ->latest('id')
                        ->lockForUpdate()
                        ->first();

                $resolvedUser = ($authUser && $authUser->isKasir())
                    ? $authUser
                    : ((!empty($trxData['cashier_nik']) ? User::where('nik', $trxData['cashier_nik'])->first() : null) ?? $authUser);

                $storeLocation = $stockService->resolveLocation(
                    $trxData['location_id'] ?? $shift?->location_id,
                    $trxData['branch'] ?? $shift?->branch,
                    $terminalCode,
                    $resolvedUser,
                    'STORE'
                );

                if (!$shift) {
                    $shiftCode = 'CS-' . date('Ymd') . '-' . strtoupper(Str::slug($terminalCode, '')) . '-OFFLINE';
                    while (CashierShift::where('shift_code', $shiftCode)->exists()) {
                        $shiftCode .= '-' . Str::upper(Str::random(3));
                    }
                    $shift = CashierShift::create([
                        'shift_code' => $shiftCode,
                        'terminal_code' => $terminalCode,
                        'location_id' => $storeLocation?->id,
                        'branch' => $storeLocation?->name ?? ($trxData['branch'] ?? $resolvedUser?->branch ?? 'Toko Pusat'),
                        'user_id' => $resolvedUser?->id,
                        'cashier_nik' => $resolvedUser?->nik ?? ($trxData['cashier_nik'] ?? 'OFFLINE'),
                        'cashier_name' => $resolvedUser?->name ?? ($trxData['cashier_name'] ?? 'Kasir Offline'),
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
                        'opened_at' => !empty($trxData['created_at']) ? Carbon::parse($trxData['created_at']) : now(),
                    ]);
                }

                $cashierName = ($authUser && $authUser->isKasir())
                    ? $authUser->name
                    : ($trxData['cashier_name'] ?? $shift->cashier_name);

                $useStoreStock = $storeLocation !== null && (
                    !empty($trxData['location_id'])
                    || !empty($trxData['branch'])
                    || $stockService->hasStoreStocks()
                );

                $member = null;
                if (!empty($trxData['member_nik'])) {
                    $member = Member::where('nik', $trxData['member_nik'])->lockForUpdate()->first();
                }

                $processedItems = [];
                $subtotal = 0;
                $hasStockConflict = false;

                foreach ($trxData['items'] as $rawItem) {
                    $product = null;
                    if (!empty($rawItem['barcode'])) {
                        $product = Product::where('barcode', $rawItem['barcode'])->lockForUpdate()->first();
                    }
                    if (!$product && !empty($rawItem['sku'])) {
                        $product = Product::where('sku', $rawItem['sku'])->lockForUpdate()->first();
                    }

                    $qty = max(1, (int) ($rawItem['qty'] ?? 1));
                    $unitPrice = (float) ($rawItem['price'] ?? 0);
                    $itemTotal = (float) ($rawItem['total'] ?? ($unitPrice * $qty));

                    if (!$product) {
                        $fallbackSku = !empty($rawItem['sku']) ? $rawItem['sku'] : ('GEN-' . Str::upper(Str::random(6)));
                        $product = Product::firstOrCreate(
                            ['sku' => $fallbackSku],
                            [
                                'barcode' => (!empty($rawItem['barcode']) && $rawItem['barcode'] !== '-') ? $rawItem['barcode'] : ('BAR-' . Str::upper(Str::random(8))),
                                'name' => $rawItem['name'] ?? 'Item Offline',
                                'category' => 'Umum',
                                'unit' => $rawItem['unit'] ?? 'Pcs',
                                'price' => $unitPrice,
                                'cost_price' => 0,
                                'stock' => 0,
                                'status' => 'TERSEDIA',
                            ]
                        );
                    }

                    $itemUnit = $rawItem['unit'] ?? $product->unit;
                    $baseQty = $product->convertToBaseUnits($itemUnit, $qty);
                    $primaryBatchId = null;
                    $batchAllocations = null;

                    if ($useStoreStock && $storeLocation && \App\Models\ProductStock::where('product_id', $product->id)->where('location_id', $storeLocation->id)->exists()) {
                        $beforeLocStock = (int) \App\Models\ProductStock::where('product_id', $product->id)
                            ->where('location_id', $storeLocation->id)
                            ->value('stock');
                        if ($beforeLocStock < $baseQty) {
                            $hasStockConflict = true;
                        }
                        $deductRes = $stockService->deductStock($product->id, $storeLocation->id, $baseQty, [
                            'movement_type' => 'SALE_OUT',
                            'allow_negative' => true,
                            'reference_type' => Transaction::class,
                            'reference_number' => $invoiceNumber,
                            'user_id' => $resolvedUser?->id,
                            'notes' => "Sinkronisasi offline {$invoiceNumber} ({$storeLocation->name})",
                        ]);
                        if ($deductRes['success']) {
                            $primaryBatchId = $deductRes['primary_batch_id'] ?? null;
                            $batchAllocations = !empty($deductRes['allocations']) ? $deductRes['allocations'] : null;
                        }
                        $product->refresh();
                    } else {
                        if ((int) $product->stock < $baseQty) {
                            $hasStockConflict = true;
                        }
                        $product->stock = max(0, $product->stock - $baseQty);
                        if ($product->stock <= 0) {
                            $product->status = 'HABIS';
                        } elseif ($product->stock <= $product->min_stock) {
                            $product->status = 'STOK_RENDAH';
                        }
                        $product->save();
                    }

                    $processedItems[] = [
                        'product_id' => $product->id,
                        'stock_batch_id' => $primaryBatchId,
                        'batch_allocations' => $batchAllocations,
                        'sku' => $product->sku,
                        'barcode' => $product->barcode,
                        'product_name' => $product->name,
                        'unit' => $itemUnit,
                        'unit_price' => $unitPrice,
                        'qty' => $qty,
                        'is_taxable' => (bool) $product->is_taxable,
                        'tax_amount' => (float) ($rawItem['tax_amount'] ?? 0),
                        'is_expired_clearance' => (bool) ($rawItem['is_expired_clearance'] ?? false),
                        'clearance_discount_percent' => (float) ($rawItem['clearance_discount_percent'] ?? 0),
                        'discount_amount' => (float) ($rawItem['discount_amount'] ?? 0),
                        'total_price' => $itemTotal,
                    ];

                    $subtotal += $itemTotal;
                }

                $grandTotal = (float) $trxData['grand_total'];
                $method = strtoupper($trxData['payment_method']);
                $createdAt = !empty($trxData['created_at']) ? Carbon::parse($trxData['created_at']) : now();

                $transaction = new Transaction([
                    'invoice_number' => $invoiceNumber,
                    'offline_id' => $offlineId,
                    'shift_id' => $shift->id,
                    'user_id' => $resolvedUser?->id,
                    'cashier_name' => $cashierName,
                    'terminal_code' => $shift->terminal_code,
                    'location_id' => $storeLocation?->id,
                    'branch' => $storeLocation?->name ?? $shift->branch,
                    'member_id' => $member?->id,
                    'member_nik' => $member?->nik,
                    'member_name' => $member?->name,
                    'payment_method' => $method,
                    'subtotal' => (float) ($trxData['subtotal'] ?? $subtotal),
                    'discount_total' => (float) ($trxData['discount_total'] ?? 0),
                    'tax_total' => (float) ($trxData['tax_total'] ?? 0),
                    'grand_total' => $grandTotal,
                    'cash_received' => (float) ($trxData['cash_received'] ?? ($method === 'TUNAI' ? $grandTotal : 0)),
                    'change_returned' => (float) ($trxData['change_returned'] ?? 0),
                    'payment_reference' => $trxData['payment_reference'] ?? ('OFFLINE-REF-' . Str::upper(Str::random(6))),
                    'split_cash' => (float) ($trxData['split_cash'] ?? 0),
                    'split_non_cash' => (float) ($trxData['split_non_cash'] ?? 0),
                    'status' => 'SUCCESS',
                ]);
                $transaction->created_at = $createdAt;
                $transaction->updated_at = now();
                $transaction->save();

                foreach ($processedItems as $itemData) {
                    TransactionItem::create(array_merge($itemData, ['transaction_id' => $transaction->id]));
                }

                if ($method === 'KREDIT' && $member) {
                    $branchName = $storeLocation?->name ?? ($resolvedUser?->branch ?? "Terminal {$shift->terminal_code}");
                    $member->recordKasbon(
                        amount: $grandTotal,
                        invoiceNumber: $invoiceNumber,
                        transactionId: $transaction->id,
                        branch: $branchName,
                        processedBy: $cashierName,
                        notes: "Belanja Kasbon POS [Offline Sync] (Invoice: {$invoiceNumber})"
                    );
                }

                if ($method === 'TUNAI') {
                    $shift->cash_sales_total += $grandTotal;
                } elseif ($method === 'QRIS' || $method === 'TRANSFER') {
                    $shift->qris_sales_total += $grandTotal;
                } elseif ($method === 'KREDIT') {
                    $shift->credit_sales_total += $grandTotal;
                } elseif ($method === 'CAMPURAN') {
                    $shift->cash_sales_total += (float) ($trxData['split_cash'] ?? 0);
                    $shift->qris_sales_total += (float) ($trxData['split_non_cash'] ?? 0);
                }

                $shift->total_sales += $grandTotal;
                $shift->transaction_count += 1;
                $shift->expected_cash = $shift->calculateExpectedCash();
                if ($shift->status === 'CLOSED' && $shift->actual_cash !== null) {
                    $shift->variance = round((float) $shift->actual_cash - (float) $shift->expected_cash, 2);
                }
                $shift->save();

                return [
                    'offline_id' => $offlineId,
                    'invoice_number' => $invoiceNumber,
                    'server_id' => $transaction->id,
                    'status' => 'SYNCED',
                    'stock_conflict' => $hasStockConflict,
                ];
            });
        } catch (\Throwable $e) {
            return [
                'offline_id' => $offlineId,
                'invoice_number' => $invoiceNumber,
                'status' => 'FAILED',
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Process a single offline cash movement idempotently by offline_id.
     */
    protected function syncOfflineCashMovementItem(array $movData, ?User $authUser, LocationStockService $stockService): array
    {
        $offlineId = $movData['offline_id'];

        try {
            return DB::transaction(function () use ($movData, $offlineId, $authUser, $stockService) {
                $existing = CashMovement::where('offline_id', $offlineId)->first();
                if ($existing) {
                    return [
                        'offline_id' => $offlineId,
                        'server_id' => $existing->id,
                        'status' => 'ALREADY_SYNCED',
                    ];
                }

                $termCode = $movData['terminal_code'];
                $shift = !empty($movData['shift_code'])
                    ? CashierShift::where('shift_code', $movData['shift_code'])->lockForUpdate()->first()
                    : null;
                if (!$shift) {
                    $shift = CashierShift::where('terminal_code', $termCode)->where('status', 'OPEN')->latest('id')->lockForUpdate()->first()
                        ?? CashierShift::where('terminal_code', $termCode)->latest('id')->lockForUpdate()->first();
                }

                if (!$shift) {
                    return [
                        'offline_id' => $offlineId,
                        'status' => 'FAILED',
                        'message' => "Tidak ditemukan sesi shift pada terminal {$termCode} untuk mutasi kas.",
                    ];
                }

                $storeLoc = $stockService->resolveLocation(
                    $movData['location_id'] ?? $shift->location_id,
                    $movData['branch'] ?? $shift->branch,
                    $termCode,
                    $authUser,
                    'STORE'
                );

                $amount = (float) $movData['amount'];
                $type = strtoupper($movData['type']);
                $isIncoming = in_array($type, ['IN', 'KAS_MASUK']);

                $movement = CashMovement::create([
                    'offline_id' => $offlineId,
                    'shift_id' => $shift->id,
                    'location_id' => $storeLoc?->id ?? $shift->location_id,
                    'terminal_code' => $termCode,
                    'type' => $type,
                    'amount' => $amount,
                    'reason' => $movData['reason'] ?? 'Mutasi Kas Offline',
                    'user_id' => $authUser?->id ?? $shift->user_id,
                    'cashier_nik' => $authUser?->nik ?? ($movData['cashier_nik'] ?? $shift->cashier_nik),
                    'occurred_at' => !empty($movData['created_at']) ? Carbon::parse($movData['created_at']) : now(),
                ]);

                if ($isIncoming) {
                    $shift->cash_in += $amount;
                } else {
                    $shift->cash_out += $amount;
                }
                $shift->expected_cash = $shift->calculateExpectedCash();
                if ($shift->status === 'CLOSED' && $shift->actual_cash !== null) {
                    $shift->variance = round((float) $shift->actual_cash - (float) $shift->expected_cash, 2);
                }
                $shift->save();

                return [
                    'offline_id' => $offlineId,
                    'server_id' => $movement->id,
                    'status' => 'SYNCED',
                ];
            });
        } catch (\Throwable $e) {
            return [
                'offline_id' => $offlineId,
                'status' => 'FAILED',
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Show Transaction by Invoice Number or ID for receipt printing.
     */
    public function show(string $idOrInvoice): JsonResponse
    {
        $transaction = Transaction::with(['items', 'shift', 'member'])
            ->where('invoice_number', $idOrInvoice)
            ->orWhere('id', is_numeric($idOrInvoice) ? (int) $idOrInvoice : 0)
            ->first();

        if (!$transaction) {
            return response()->json([
                'success' => false,
                'message' => 'Transaksi tidak ditemukan.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $transaction->id,
                'invoice_number' => $transaction->invoice_number,
                'terminal_code' => $transaction->terminal_code,
                'branch' => $transaction->branch,
                'cashier_name' => $transaction->cashier_name,
                'payment_method' => $transaction->payment_method,
                'payment_reference' => $transaction->payment_reference,
                'subtotal' => (float) $transaction->subtotal,
                'discount_total' => (float) $transaction->discount_total,
                'promo_discount_total' => (float) ($transaction->promo_discount_total ?? 0),
                'tax_total' => (float) $transaction->tax_total,
                'grand_total' => (float) $transaction->grand_total,
                'cash_received' => (float) $transaction->cash_received,
                'change_returned' => (float) $transaction->change_returned,
                'status' => $transaction->status,
                'created_at' => $transaction->created_at->toIso8601String(),
                'member' => $transaction->member ? [
                    'nik' => $transaction->member->nik,
                    'name' => $transaction->member->name,
                    'remaining_credit' => (float) $transaction->member->remaining_credit,
                ] : null,
                'items' => $transaction->items->map(fn($it) => [
                    'sku' => $it->sku,
                    'name' => $it->product_name,
                    'unit' => $it->unit,
                    'unit_price' => (float) $it->unit_price,
                    'qty' => (int) $it->qty,
                    'is_expired_clearance' => (bool) $it->is_expired_clearance,
                    'clearance_discount_percent' => (float) $it->clearance_discount_percent,
                    'discount_amount' => (float) $it->discount_amount,
                    'total_price' => (float) $it->total_price,
                    'promotion_id' => $it->promotion_id,
                    'promo_name' => $it->promo_name,
                    'promo_discount' => (float) ($it->promo_discount ?? 0),
                ]),
            ],
        ]);
    }

    /**
     * List transactions for Cashier History & Audit with filters.
     */
    public function index(Request $request): JsonResponse
    {
        $search = trim($request->query('search', ''));
        $method = trim($request->query('payment_method', ''));
        $status = trim($request->query('status', ''));
        $dateFilter = trim($request->query('date', 'all')); // today, week, month, all
        $perPage = max(5, min(100, (int) $request->query('per_page', 50)));

        $query = Transaction::with(['items', 'member', 'shift'])->latest('id');

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('invoice_number', 'like', "%{$search}%")
                  ->orWhere('cashier_name', 'like', "%{$search}%")
                  ->orWhere('terminal_code', 'like', "%{$search}%")
                  ->orWhereHas('items', function ($iq) use ($search) {
                      $iq->where('product_name', 'like', "%{$search}%")
                         ->orWhere('sku', 'like', "%{$search}%");
                  });
            });
        }

        if ($method !== '' && $method !== 'all') {
            $query->where('payment_method', $method);
        }

        if ($status !== '' && $status !== 'all') {
            $upperStatus = strtoupper($status);
            if (in_array($upperStatus, ['SELESAI', 'SUCCESS', 'COMPLETED', 'LUNAS'])) {
                $query->whereIn('status', ['SUCCESS', 'COMPLETED', 'SELESAI']);
            } elseif (in_array($upperStatus, ['DIBATALKAN', 'VOID', 'VOIDED'])) {
                $query->whereIn('status', ['VOIDED', 'DIBATALKAN']);
            } else {
                $query->where('status', $status);
            }
        }

        if ($dateFilter === 'today' || $dateFilter === 'harian') {
            $query->whereDate('created_at', today());
        } elseif ($dateFilter === 'yesterday') {
            $query->whereDate('created_at', today()->subDay());
        } elseif ($dateFilter === 'week' || $dateFilter === 'mingguan') {
            $query->whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()]);
        } elseif ($dateFilter === 'month' || $dateFilter === 'bulanan') {
            $query->whereMonth('created_at', now()->month)->whereYear('created_at', now()->year);
        } elseif ($dateFilter === 'year' || $dateFilter === 'tahunan') {
            $query->whereYear('created_at', now()->year);
        } elseif (preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFilter)) {
            $query->whereDate('created_at', $dateFilter);
        }

        // Summary calculations
        $kpiQuery = clone $query;
        $allMatching = $kpiQuery->get();
        $totalOmzet = (float) $allMatching->whereNotIn('status', ['VOIDED', 'DIBATALKAN'])->sum('grand_total');
        $totalCash = (float) $allMatching->whereNotIn('status', ['VOIDED', 'DIBATALKAN'])->where('payment_method', 'TUNAI')->sum('grand_total')
                   + (float) $allMatching->whereNotIn('status', ['VOIDED', 'DIBATALKAN'])->where('payment_method', 'CAMPURAN')->sum('split_cash');
        $totalNonCash = max(0, $totalOmzet - $totalCash);
        $totalTransactions = $allMatching->count();
        $totalVoided = $allMatching->whereIn('status', ['VOIDED', 'DIBATALKAN'])->count();

        $paginated = $query->paginate($perPage);

        $transactions = collect($paginated->items())->map(function ($t) {
            $createdWib = $t->created_at ? $t->created_at->setTimezone('Asia/Jakarta') : now('Asia/Jakarta');
            $statusNormalized = in_array($t->status, ['SUCCESS', 'COMPLETED']) ? 'SELESAI' : ($t->status === 'VOIDED' ? 'DIBATALKAN' : $t->status);
            return [
                'id' => $t->invoice_number,
                'rawId' => $t->id,
                'invoice_number' => $t->invoice_number,
                'time' => $createdWib->format('H:i') . ' WIB',
                'date' => $createdWib->translatedFormat('d M Y'),
                'date_full' => $createdWib->translatedFormat('d F Y'),
                'datetime' => $createdWib->translatedFormat('d M Y') . ', ' . $createdWib->format('H:i') . ' WIB',
                'date_iso' => $createdWib->format('Y-m-d'),
                'rawDate' => $createdWib->format('Y-m-d'),
                'terminal' => $t->terminal_code,
                'cashier' => $t->cashier_name,
                'cashierNik' => $t->shift?->cashier_nik ?? $t->member_nik,
                'method' => $t->payment_method,
                'methodLabel' => match($t->payment_method) {
                    'TUNAI' => 'Uang Tunai',
                    'QRIS' => 'QRIS Dinamis',
                    'TRANSFER' => 'Transfer Bank',
                    'KREDIT' => 'Kredit Anggota',
                    'CAMPURAN' => 'Tunai + QRIS',
                    default => $t->payment_method
                },
                'cashPortion' => (float) ($t->payment_method === 'TUNAI' ? $t->grand_total : ($t->payment_method === 'CAMPURAN' ? $t->split_cash : 0)),
                'nonCashPortion' => (float) ($t->payment_method === 'TUNAI' ? 0 : ($t->payment_method === 'CAMPURAN' ? $t->split_non_cash : $t->grand_total)),
                'subtotal' => (float) $t->subtotal,
                'discount' => (float) $t->discount_total,
                'tax' => (float) $t->tax_total,
                'total' => (float) $t->grand_total,
                'cashGiven' => (float) $t->cash_received,
                'change' => (float) $t->change_returned,
                'status' => $statusNormalized,
                'statusCode' => $t->status,
                'rawStatus' => $t->status,
                'syncStatus' => 'SYNCED',
                'member' => $t->member ? [
                    'nik' => $t->member->nik,
                    'name' => $t->member->name,
                    'remaining_credit' => (float) $t->member->remaining_credit,
                ] : null,
                'items' => $t->items->map(fn($it) => [
                    'id' => $it->id,
                    'sku' => $it->sku,
                    'name' => $it->product_name,
                    'unit' => $it->unit ?? 'Pcs',
                    'qty' => (int) $it->qty,
                    'price' => (float) $it->unit_price,
                    'total' => (float) $it->total_price,
                    'is_expired_clearance' => (bool) $it->is_expired_clearance,
                    'clearance_discount_percent' => (float) $it->clearance_discount_percent,
                    'promo_name' => $it->promo_name,
                    'promo_discount' => (float) ($it->promo_discount ?? 0),
                ]),
            ];
        });

        return response()->json([
            'success' => true,
            'summary' => [
                'total_omzet' => $totalOmzet,
                'total_cash' => $totalCash,
                'total_non_cash' => $totalNonCash,
                'total_transactions' => $totalTransactions,
                'total_voided' => $totalVoided,
            ],
            'data' => $transactions,
            'pagination' => [
                'current_page' => $paginated->currentPage(),
                'last_page' => $paginated->lastPage(),
                'per_page' => $paginated->perPage(),
                'total' => $paginated->total(),
            ]
        ]);
    }
}
