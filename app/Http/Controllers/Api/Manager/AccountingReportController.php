<?php

namespace App\Http\Controllers\Api\Manager;

use App\Http\Controllers\Controller;
use App\Models\AppSetting;
use App\Models\CashierShift;
use App\Models\Location;
use App\Models\Product;
use App\Models\Transaction;
use App\Models\TransactionItem;
use App\Models\User;
use App\Services\LocationStockService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AccountingReportController extends Controller
{
    /**
     * Helper to resolve Date Range based on period (daily/harian, weekly/mingguan, monthly/bulanan, yearly/tahunan, custom).
     */
    protected function resolveReportDateRange(Request $request): array
    {
        $period = $request->query('period', '');
        $today = Carbon::today();

        if ($period === 'daily' || $period === 'harian') {
            $startDate = $request->query('date', $today->toDateString());
            $endDate = $startDate;
        } elseif ($period === 'weekly' || $period === 'mingguan') {
            $startDate = $request->query('start_date', $today->copy()->startOfWeek()->toDateString());
            $endDate = $request->query('end_date', $today->copy()->endOfWeek()->toDateString());
        } elseif ($period === 'monthly' || $period === 'bulanan') {
            $month = $request->query('month', $today->format('Y-m'));
            $startDate = Carbon::parse($month . '-01')->startOfMonth()->toDateString();
            $endDate = Carbon::parse($month . '-01')->endOfMonth()->toDateString();
        } elseif ($period === 'yearly' || $period === 'tahunan') {
            $year = $request->query('year', $today->format('Y'));
            $startDate = Carbon::parse($year . '-01-01')->startOfYear()->toDateString();
            $endDate = Carbon::parse($year . '-12-31')->endOfYear()->toDateString();
        } elseif ($request->filled('start_date') && $request->filled('end_date')) {
            $startDate = $request->query('start_date');
            $endDate = $request->query('end_date');
        } else {
            $startDate = $request->query('start_date', $today->copy()->startOfMonth()->toDateString());
            $endDate = $request->query('end_date', $today->toDateString());
        }

        return [$startDate, $endDate];
    }

    /**
     * 1. Laporan Penjualan (Sales Report):
     * Per-shift kasir, Per-cabang, dan Konsolidasi 3 Cabang (Harian, Mingguan, Bulanan, Tahunan).
     */
    public function sales(Request $request): JsonResponse
    {
        $period = $request->query('period', 'monthly'); // daily, weekly, monthly, yearly, custom
        $branch = $request->query('branch', 'all');
        $locationIdFilter = $request->integer('location_id') ?: null;
        $shiftId = $request->query('shift_id');
        $cashierNik = $request->query('cashier_nik');

        [$startDate, $endDate] = $this->resolveReportDateRange($request);

        // Build Base Query (never filter branch using terminal_code LIKE heuristic)
        $query = Transaction::query()
            ->whereDate('created_at', '>=', $startDate)
            ->whereDate('created_at', '<=', $endDate)
            ->whereIn('status', ['SUCCESS', 'COMPLETED']);

        if ($shiftId) {
            $query->where('shift_id', $shiftId);
        }

        if ($cashierNik) {
            $query->where('user_id', function ($q) use ($cashierNik) {
                $q->select('id')->from('users')->where('nik', $cashierNik);
            });
        }

        $rawTransactions = $query->with(['items', 'location', 'shift'])->orderByDesc('created_at')->get();
        $stockService = app(LocationStockService::class);
        $locations = Location::where('type', '!=', 'WAREHOUSE')->orderBy('code')->get();
        $filterResolution = $stockService->resolveReportFilterLocation($branch, $locationIdFilter);
        $filterRequested = $filterResolution['requested'];
        $filterLoc = $filterResolution['location'];

        $partitioned = $stockService->partitionTransactionsByStoreLocation(
            $rawTransactions,
            $locations,
            $filterLoc,
            $filterRequested
        );
        $transactions = $partitioned['store_transactions'];
        $byLocationId = $partitioned['by_location_id'];

        // 1. Overall Summary Metrics (strictly from mapped store transactions -> matches sum of branch_breakdown)
        $totalGrossSales = round((float) $transactions->sum('subtotal'), 2);
        $totalDiscounts = round((float) $transactions->sum('discount_total'), 2);
        $totalTax = round((float) $transactions->sum('tax_total'), 2);
        $totalGrandTotal = round((float) $transactions->sum('grand_total'), 2);
        $trxCount = $transactions->count();
        $averageBasket = $trxCount > 0 ? round($totalGrandTotal / $trxCount, 2) : 0;

        // Payment Method Breakdown
        $cashSales = round((float) $transactions->where('payment_method', 'TUNAI')->sum('grand_total'), 2);
        $qrisSales = round((float) $transactions->where('payment_method', 'QRIS')->sum('grand_total'), 2);
        $creditSales = round((float) $transactions->where('payment_method', 'KREDIT')->sum('grand_total'), 2);
        $mixedSales = round((float) $transactions->where('payment_method', 'CAMPURAN')->sum('grand_total'), 2);

        // 2. Breakdown Per-Shift Kasir (Batch load shifts to eliminate N+1 queries)
        $shiftsData = [];
        $shiftsGroup = $transactions->groupBy('shift_id');
        $shiftIds = $shiftsGroup->keys()->filter()->values()->all();
        $shiftsMap = empty($shiftIds) ? collect() : CashierShift::whereIn('id', $shiftIds)->get()->keyBy('id');
        foreach ($shiftsGroup as $sId => $txs) {
            $shiftRecord = $shiftsMap->get($sId);
            $shiftsData[] = [
                'shift_id' => $sId,
                'shift_code' => $shiftRecord?->shift_code ?? ('SHIFT-' . $sId),
                'cashier_name' => $txs->first()->cashier_name ?? $shiftRecord?->cashier_name ?? 'Kasir',
                'terminal_code' => $shiftRecord?->terminal_code ?? $txs->first()->terminal_code ?? 'POS-01',
                'transaction_count' => $txs->count(),
                'opening_cash' => (float) ($shiftRecord?->opening_cash ?? 0),
                'cash_sales' => (float) $txs->where('payment_method', 'TUNAI')->sum('grand_total'),
                'non_cash_sales' => (float) $txs->where('payment_method', '!=', 'TUNAI')->sum('grand_total'),
                'total_sales' => (float) $txs->sum('grand_total'),
                'variance' => (float) ($shiftRecord?->variance ?? 0),
                'status' => $shiftRecord?->status ?? 'CLOSED',
            ];
        }

        // 3. Breakdown Per-Cabang (Authoritative location_id & explicit historical fallback)
        $branchBreakdown = [];
        foreach ($locations as $idx => $loc) {
            $bName = $loc->name;
            $bCode = $loc->code ?: ('BR-' . str_pad((string) ($idx + 1), 3, '0', STR_PAD_LEFT));
            $bTxs = ($filterRequested && (!$filterLoc || $filterLoc->id !== $loc->id))
                ? collect()
                : ($byLocationId[$loc->id] ?? collect());

            $bCount = $bTxs->count();
            $bRevenue = round((float) $bTxs->sum('grand_total'), 2);

            $branchBreakdown[] = [
                'location_id' => $loc->id,
                'branch_code' => $bCode,
                'branch_name' => $bName,
                'transaction_count' => $bCount,
                'total_sales' => $bRevenue,
                'contribution_percent' => $totalGrandTotal > 0 ? round(($bRevenue / $totalGrandTotal) * 100, 2) : 0,
            ];
        }

        // 4. Daily Trend Data
        $dailyTrends = $transactions->groupBy(function ($tx) {
            return Carbon::parse($tx->created_at)->toDateString();
        })->map(function ($dayTxs, $day) {
            return [
                'date' => $day,
                'label' => Carbon::parse($day)->format('d M Y'),
                'transaction_count' => $dayTxs->count(),
                'total_sales' => (float) $dayTxs->sum('grand_total'),
            ];
        })->values();

        return response()->json([
            'success' => true,
            'filters' => [
                'period' => $period,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'branch' => $branch,
                'location_id' => $locationIdFilter,
                'shift_id' => $shiftId,
            ],
            'summary' => [
                'gross_sales' => $totalGrossSales,
                'total_discounts' => $totalDiscounts,
                'total_tax' => $totalTax,
                'grand_total' => $totalGrandTotal,
                'transaction_count' => $trxCount,
                'average_basket' => $averageBasket,
                'payment_breakdown' => [
                    'tunai' => $cashSales,
                    'qris' => $qrisSales,
                    'kredit_kasbon' => $creditSales,
                    'campuran' => $mixedSales,
                ],
            ],
            'shifts_breakdown' => $shiftsData,
            'branch_breakdown' => $branchBreakdown,
            'excluded_warehouse_transactions' => $stockService->summarizeExcludedTransactions($partitioned['warehouse_transactions']),
            'unmapped_transactions' => $stockService->summarizeExcludedTransactions($partitioned['unmapped_transactions']),
            'daily_trends' => $dailyTrends,
            'recent_transactions' => $transactions->take(20)->map(fn ($t) => [
                'id' => $t->id,
                'invoice_number' => $t->invoice_number,
                'location_id' => $t->location_id,
                'branch' => $t->location?->name ?? $t->branch,
                'cashier_name' => $t->cashier_name,
                'terminal_code' => $t->terminal_code,
                'payment_method' => $t->payment_method,
                'grand_total' => (float) $t->grand_total,
                'item_count' => $t->items->count(),
                'created_at' => $t->created_at->format('Y-m-d H:i:s'),
            ]),
        ]);
    }

    /**
     * 2. Laporan Laba/Rugi Bersih (Accounting P&L):
     * Dihitung otomatis berdasarkan HPP produk reguler & memperhitungkan bagi hasil komisi UMKM.
     */
    public function profitLoss(Request $request): JsonResponse
    {
        [$startDate, $endDate] = $this->resolveReportDateRange($request);
        $branch = $request->query('branch', 'all');
        $locationIdFilter = $request->integer('location_id') ?: null;

        $rawTransactions = Transaction::query()
            ->whereDate('created_at', '>=', $startDate)
            ->whereDate('created_at', '<=', $endDate)
            ->whereIn('status', ['SUCCESS', 'COMPLETED'])
            ->with(['items', 'location', 'shift'])
            ->get();

        $stockService = app(LocationStockService::class);
        $locations = Location::where('type', '!=', 'WAREHOUSE')->orderBy('code')->get();
        $filterResolution = $stockService->resolveReportFilterLocation($branch, $locationIdFilter);
        $partitioned = $stockService->partitionTransactionsByStoreLocation(
            $rawTransactions,
            $locations,
            $filterResolution['location'],
            $filterResolution['requested']
        );
        $transactions = $partitioned['store_transactions'];

        $grossSales = round((float) $transactions->sum('subtotal'), 2);
        $totalDiscounts = round((float) $transactions->sum('discount_total'), 2);
        $netSales = round($grossSales - $totalDiscounts, 2);
        $totalTax = round((float) $transactions->sum('tax_total'), 2);

        // Detailed COGS & UMKM Consignment Calculation
        $regularCogs = 0.0;
        $regularSales = 0.0;
        $consignmentSales = 0.0;
        $umkmPartnerShare = 0.0;
        $cooperativeCommission = 0.0;

        $allProducts = Product::all()->keyBy('id');

        foreach ($transactions as $tx) {
            foreach ($tx->items as $item) {
                $prod = $item->product_id ? ($allProducts[$item->product_id] ?? null) : null;
                $lineTotal = (float) $item->total_price;
                $qty = (int) $item->qty;

                if ($prod && $prod->is_consignment) {
                    $consignmentSales += $lineTotal;
                    $commPercent = (float) ($prod->commission_percent ?? 10.0);
                    $commAmount = round($lineTotal * ($commPercent / 100), 2);
                    $partnerShare = $lineTotal - $commAmount;

                    $cooperativeCommission += $commAmount;
                    $umkmPartnerShare += $partnerShare;
                } else {
                    $regularSales += $lineTotal;
                    $cost = $prod ? (float) $prod->cost_price : 0.0;
                    $regularCogs += ($cost * $qty);
                }
            }
        }

        $regularGrossProfit = max(0, $regularSales - $regularCogs);
        $totalGrossProfit = $regularGrossProfit + $cooperativeCommission;
        $netProfit = max(0, $totalGrossProfit - $totalDiscounts);
        $profitMarginPercent = ($grossSales > 0) ? round(($netProfit / $grossSales) * 100, 2) : 0;

        return response()->json([
            'success' => true,
            'period' => [
                'start_date' => $startDate,
                'end_date' => $endDate,
                'branch' => $branch,
                'location_id' => $locationIdFilter,
            ],
            'excluded_warehouse_transactions' => $stockService->summarizeExcludedTransactions($partitioned['warehouse_transactions']),
            'unmapped_transactions' => $stockService->summarizeExcludedTransactions($partitioned['unmapped_transactions']),
            'statement' => [
                // 1. Revenues
                'gross_sales' => $grossSales,
                'regular_product_sales' => $regularSales,
                'consignment_product_sales' => $consignmentSales,
                'total_discounts' => $totalDiscounts,
                'net_sales' => $netSales,

                // 2. Cost of Goods Sold & UMKM Sharing
                'regular_cogs' => $regularCogs,
                'umkm_partner_share' => $umkmPartnerShare,
                'cooperative_commission_earned' => $cooperativeCommission,

                // 3. Profitability
                'regular_gross_profit' => $regularGrossProfit,
                'total_gross_profit' => $totalGrossProfit,
                'collected_tax_ppn' => $totalTax,
                'net_profit' => $netProfit,
                'profit_margin_percent' => $profitMarginPercent,
            ],
        ]);
    }

    /**
     * 3. Analisis Barang Terlaris (Fast Moving Items):
     * Visualisasi peringkat produk terlaris & rekomendasi restock cerdas.
     */
    public function fastMoving(Request $request): JsonResponse
    {
        [$startDate, $endDate] = $this->resolveReportDateRange($request);
        $limit = $request->integer('limit', 10);
        $sortBy = $request->query('sort_by', 'qty'); // qty or revenue

        // Query top sold items from successful transactions
        $itemsQuery = TransactionItem::whereHas('transaction', function ($q) use ($startDate, $endDate) {
            $q->whereDate('created_at', '>=', $startDate)
              ->whereDate('created_at', '<=', $endDate)
              ->where('status', 'SUCCESS');
        })
        ->select(
            'product_id',
            'sku',
            'product_name',
            'unit',
            DB::raw('SUM(qty) as total_qty_sold'),
            DB::raw('SUM(total_price) as total_revenue'),
            DB::raw('COUNT(DISTINCT transaction_id) as transaction_appearances')
        )
        ->groupBy('product_id', 'sku', 'product_name', 'unit');

        if ($sortBy === 'revenue') {
            $itemsQuery->orderByDesc('total_revenue');
        } else {
            $itemsQuery->orderByDesc('total_qty_sold');
        }

        $topItems = $itemsQuery->take($limit)->get();

        $totalPeriodRevenue = (float) TransactionItem::whereHas('transaction', function ($q) use ($startDate, $endDate) {
            $q->whereDate('created_at', '>=', $startDate)
              ->whereDate('created_at', '<=', $endDate)
              ->where('status', 'SUCCESS');
        })->sum('total_price');

        // Enhance with real-time stock and restock recommendation
        $result = $topItems->map(function ($item, $idx) use ($totalPeriodRevenue) {
            $product = $item->product_id ? Product::find($item->product_id) : null;
            $currentStock = $product ? (int) $product->stock : 0;
            $minStock = $product ? (int) $product->min_stock : 10;
            $qtySold = (int) $item->total_qty_sold;
            $revenue = (float) $item->total_revenue;

            // Restock recommendation: Buffer 14 days
            $recommendedRestock = 0;
            if ($currentStock <= $minStock) {
                $recommendedRestock = max(20, ($minStock * 2) - $currentStock);
            }

            $revenueSharePercent = $totalPeriodRevenue > 0 ? round(($revenue / $totalPeriodRevenue) * 100, 2) : 0;

            return [
                'rank' => $idx + 1,
                'product_id' => $item->product_id,
                'sku' => $item->sku,
                'name' => $item->product_name,
                'unit' => $item->unit,
                'category' => $product?->category ?? 'Umum',
                'total_qty_sold' => $qtySold,
                'total_revenue' => $revenue,
                'revenue_share_percent' => $revenueSharePercent,
                'current_stock' => $currentStock,
                'min_stock' => $minStock,
                'is_low_stock' => $currentStock <= $minStock,
                'recommended_restock_qty' => $recommendedRestock,
                'velocity_status' => $idx < 3 ? 'SANGAT CEPAT (TOP 3)' : 'FAST MOVING',
            ];
        });

        return response()->json([
            'success' => true,
            'filters' => [
                'start_date' => $startDate,
                'end_date' => $endDate,
                'limit' => $limit,
                'sort_by' => $sortBy,
            ],
            'total_period_revenue' => $totalPeriodRevenue,
            'data' => $result,
        ]);
    }

    /**
     * 4. Laporan Rekonsiliasi Kas (Cash Count / Shift Closing):
     * Pencocokan fisik uang laci vs sistem guna mencegah selisih.
     */
    public function cashReconciliation(Request $request): JsonResponse
    {
        [$startDate, $endDate] = $this->resolveReportDateRange($request);
        $terminal = $request->query('terminal_code');
        $status = $request->query('status'); // SESUAI, LEBIH, KURANG
        $perPage = $request->integer('per_page', 20);

        $query = CashierShift::query()
            ->whereDate('opened_at', '>=', $startDate)
            ->whereDate('opened_at', '<=', $endDate)
            ->where('status', 'CLOSED')
            ->orderByDesc('closed_at');

        if ($terminal && $terminal !== 'all') {
            $query->where('terminal_code', $terminal);
        }

        if ($status && $status !== 'all') {
            $query->where('variance_status', strtoupper($status));
        }

        $shifts = $query->paginate($perPage);

        // Calculate Reconciliation KPI
        $allClosed = CashierShift::whereDate('opened_at', '>=', $startDate)
            ->whereDate('opened_at', '<=', $endDate)
            ->where('status', 'CLOSED')
            ->get();

        $totalAuditedShifts = $allClosed->count();
        $exactMatches = $allClosed->where('variance_status', 'SESUAI')->count();
        $shortageCount = $allClosed->where('variance_status', 'KURANG')->count();
        $overageCount = $allClosed->where('variance_status', 'LEBIH')->count();
        $accuracyRate = $totalAuditedShifts > 0 ? round(($exactMatches / $totalAuditedShifts) * 100, 2) : 100;

        $totalExpectedCash = (float) $allClosed->sum('expected_cash');
        $totalActualCash = (float) $allClosed->sum('actual_cash');
        $netVariance = $totalActualCash - $totalExpectedCash;

        return response()->json([
            'success' => true,
            'summary' => [
                'total_audited_shifts' => $totalAuditedShifts,
                'exact_matches' => $exactMatches,
                'shortage_count' => $shortageCount,
                'overage_count' => $overageCount,
                'accuracy_rate_percent' => $accuracyRate,
                'total_expected_cash' => $totalExpectedCash,
                'total_actual_cash' => $totalActualCash,
                'net_variance' => $netVariance,
            ],
            'data' => $shifts->items(),
            'pagination' => [
                'current_page' => $shifts->currentPage(),
                'last_page' => $shifts->lastPage(),
                'total' => $shifts->total(),
            ],
        ]);
    }

    /**
     * 5. Pengaturan Pajak Produk (Tax / Non-Tax Products Management):
     * Pengelompokan produk Kena Pajak / Bebas Pajak & bulk toggle oleh Manager/Admin.
     */
    public function productTaxList(Request $request): JsonResponse
    {
        $search = $request->query('search');
        $taxableFilter = $request->query('is_taxable'); // all, 1, 0
        $category = $request->query('category');
        $perPage = $request->integer('per_page', 25);

        $query = Product::query();

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'ILIKE', "%{$search}%")
                  ->orWhere('sku', 'ILIKE', "%{$search}%")
                  ->orWhere('barcode', 'ILIKE', "%{$search}%");
            });
        }

        if ($taxableFilter !== null && $taxableFilter !== 'all') {
            $query->where('is_taxable', (bool) $taxableFilter);
        }

        if ($category && $category !== 'Semua') {
            $query->where('category', $category);
        }

        $products = $query->orderBy('name')->paginate($perPage);

        $taxableCount = Product::where('is_taxable', true)->count();
        $nonTaxableCount = Product::where('is_taxable', false)->count();

        return response()->json([
            'success' => true,
            'summary' => [
                'taxable_products' => $taxableCount,
                'non_taxable_products' => $nonTaxableCount,
                'global_tax_enabled' => AppSetting::get('tax_enabled', '1') === '1',
                'global_tax_rate' => (float) AppSetting::get('tax_rate_percent', 11.0),
            ],
            'data' => $products->items(),
            'pagination' => [
                'current_page' => $products->currentPage(),
                'last_page' => $products->lastPage(),
                'total' => $products->total(),
            ],
        ]);
    }

    /**
     * Toggle or update a product's taxable status.
     */
    public function updateProductTax(Request $request, int $id): JsonResponse
    {
        $product = Product::findOrFail($id);

        $validated = $request->validate([
            'is_taxable' => 'required|boolean',
            'tax_rate' => 'nullable|numeric|min:0|max:100',
        ]);

        $product->is_taxable = $validated['is_taxable'];
        if (isset($validated['tax_rate'])) {
            $product->tax_rate = $validated['tax_rate'];
        }
        $product->save();

        $statusStr = $product->is_taxable ? 'Kena Pajak PPN (' . ($product->tax_rate ?? 11) . '%)' : 'Bebas Pajak (Non-Tax)';
        return response()->json([
            'success' => true,
            'message' => "Status pajak produk [{$product->name}] berhasil diubah menjadi: {$statusStr}.",
            'data' => [
                'id' => $product->id,
                'name' => $product->name,
                'is_taxable' => (bool) $product->is_taxable,
                'tax_rate' => (float) $product->tax_rate,
            ],
        ]);
    }
}
