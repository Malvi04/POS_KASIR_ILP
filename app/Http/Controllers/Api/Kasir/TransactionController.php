<?php

namespace App\Http\Controllers\Api\Kasir;

use App\Http\Controllers\Controller;
use App\Models\AppSetting;
use App\Models\CashierShift;
use App\Models\Member;
use App\Models\Product;
use App\Models\Transaction;
use App\Models\TransactionItem;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class TransactionController extends Controller
{
    /**
     * Checkout & Process Payment with Multi-Type Payment Support.
     */
    public function checkout(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'shift_id' => 'nullable|integer|exists:cashier_shifts,id',
            'terminal_code' => 'required|string|max:20',
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
        ]);

        return DB::transaction(function () use ($validated, $request) {
            // 1. Verify Active Shift
            $shift = null;
            if (!empty($validated['shift_id'])) {
                $shift = CashierShift::lockForUpdate()->find($validated['shift_id']);
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

            $user = User::where('nik', $validated['cashier_nik'] ?? $shift->cashier_nik)->first();
            $cashierName = $validated['cashier_name'] ?? $shift->cashier_name;

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

                    // Check stock in base unit
                    if ($product->stock < $baseQtyToDeduct) {
                        return response()->json([
                            'success' => false,
                            'error_code' => 'STOCK_INSUFFICIENT',
                            'message' => "Stok produk [{$product->name}] tidak mencukupi. Sisa stok: {$product->stock} {$product->unit}, permintaan: {$qty} {$itemUnit} ({$baseQtyToDeduct} {$product->unit}).",
                        ], 422);
                    }

                    // Auto-Discount Expired (H-14 warning rule)
                    $isExpiredClearance = $product->is_critical_expired;
                    $discountPercent = $product->effective_discount_percent;

                    $unitBasePrice = $isMemberMode ? ($product->member_price ?? $product->price) : $product->price;
                    $unitDiscount = $isExpiredClearance ? round($unitBasePrice * ($discountPercent / 100), 2) : 0;
                    $unitFinalPrice = max(0, $unitBasePrice - $unitDiscount);

                    $lineSubtotal = round($unitFinalPrice * $qty, 2);
                    $lineDiscount = round($unitDiscount * $qty, 2);

                    // Tax calculation
                    $lineTax = 0;
                    if ($isGlobalTaxActive && $product->is_taxable) {
                        $itemRate = ($product->tax_rate !== null && $product->tax_rate > 0) ? (float) $product->tax_rate : $globalTaxRate;
                        $lineTax = round($lineSubtotal * ($itemRate / 100), 2);
                    }

                    // Deduct stock accurately in base units (Multi-Satuan Unit Conversion)
                    $product->stock -= $baseQtyToDeduct;
                    if ($product->stock <= 0) {
                        $product->status = 'HABIS';
                    } elseif ($product->stock <= $product->min_stock) {
                        $product->status = 'STOK_RENDAH';
                    }
                    $product->save();

                    $subtotal += $lineSubtotal;
                    $discountTotal += $lineDiscount;
                    $taxTotal += $lineTax;

                    $processedItems[] = [
                        'product_id' => $product->id,
                        'sku' => $product->sku,
                        'barcode' => $product->barcode,
                        'product_name' => $product->name,
                        'unit' => $product->unit,
                        'unit_price' => $unitFinalPrice,
                        'qty' => $qty,
                        'is_taxable' => (bool) $product->is_taxable,
                        'tax_amount' => $lineTax,
                        'is_expired_clearance' => $isExpiredClearance,
                        'clearance_discount_percent' => $discountPercent,
                        'discount_amount' => $lineDiscount,
                        'total_price' => $lineSubtotal,
                    ];
                } else {
                    // Fallback item if not in master db
                    $unitPrice = (float) $rawItem['price'];
                    $lineSubtotal = round($unitPrice * $qty, 2);
                    $lineTax = $isGlobalTaxActive ? round($lineSubtotal * ($globalTaxRate / 100), 2) : 0;

                    $subtotal += $lineSubtotal;
                    $taxTotal += $lineTax;

                    $processedItems[] = [
                        'product_id' => null,
                        'sku' => $rawItem['sku'] ?? 'GEN-ITEM',
                        'barcode' => $rawItem['barcode'] ?? '-',
                        'product_name' => $rawItem['name'],
                        'unit' => $rawItem['unit'] ?? 'Pcs',
                        'unit_price' => $unitPrice,
                        'qty' => $qty,
                        'is_taxable' => true,
                        'tax_amount' => $lineTax,
                        'is_expired_clearance' => false,
                        'clearance_discount_percent' => 0,
                        'discount_amount' => 0,
                        'total_price' => $lineSubtotal,
                    ];
                }
            }

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

            // 5. Generate Invoice Number & Save Transaction
            $invoiceNumber = 'TRX-' . date('Ymd') . '-' . Str::padLeft((string) (Transaction::whereDate('created_at', today())->count() + 1), 4, '0');

            $transaction = Transaction::create([
                'invoice_number' => $invoiceNumber,
                'shift_id' => $shift->id,
                'user_id' => $user?->id,
                'cashier_name' => $cashierName,
                'terminal_code' => $shift->terminal_code,
                'member_id' => $member?->id,
                'member_nik' => $member?->nik,
                'member_name' => $member?->name,
                'payment_method' => $method,
                'subtotal' => $subtotal,
                'discount_total' => $discountTotal,
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
                'cashier_name' => $transaction->cashier_name,
                'payment_method' => $transaction->payment_method,
                'payment_reference' => $transaction->payment_reference,
                'subtotal' => (float) $transaction->subtotal,
                'discount_total' => (float) $transaction->discount_total,
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
            $query->where('status', $status);
        }

        if ($dateFilter === 'today') {
            $query->whereDate('created_at', today());
        } elseif ($dateFilter === 'week') {
            $query->whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()]);
        } elseif ($dateFilter === 'month') {
            $query->whereMonth('created_at', now()->month)->whereYear('created_at', now()->year);
        }

        // Summary calculations
        $kpiQuery = clone $query;
        $allMatching = $kpiQuery->get();
        $totalOmzet = (float) $allMatching->where('status', '!=', 'VOIDED')->sum('grand_total');
        $totalCash = (float) $allMatching->where('status', '!=', 'VOIDED')->where('payment_method', 'TUNAI')->sum('grand_total')
                   + (float) $allMatching->where('status', '!=', 'VOIDED')->where('payment_method', 'CAMPURAN')->sum('split_cash');
        $totalNonCash = max(0, $totalOmzet - $totalCash);
        $totalTransactions = $allMatching->count();
        $totalVoided = $allMatching->where('status', 'VOIDED')->count();

        $paginated = $query->paginate($perPage);

        $transactions = collect($paginated->items())->map(function ($t) {
            return [
                'id' => $t->invoice_number,
                'rawId' => $t->id,
                'invoice_number' => $t->invoice_number,
                'time' => $t->created_at->format('H:i') . ' WIB',
                'date' => $t->created_at->translatedFormat('d F Y'),
                'terminal' => $t->terminal_code,
                'cashier' => $t->cashier_name,
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
                'status' => $t->status,
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
