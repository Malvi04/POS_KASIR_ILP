<?php

namespace App\Http\Controllers\Api\Manager;

use App\Http\Controllers\Controller;
use App\Models\AppSetting;
use App\Models\DebtMutation;
use App\Models\Location;
use App\Models\Member;
use App\Models\Product;
use App\Models\ProductStock;
use App\Models\StockBatch;
use App\Models\StockCorrection;
use App\Models\StockOpname;
use App\Models\Transaction;
use App\Models\TransactionItem;
use App\Models\User;
use App\Services\LocationStockService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ConsolidatedReportController extends Controller
{
    /**
     * Real-time Consolidated 3-Branch Business Performance & P&L (Laba/Rugi)
     * For Owner & Superadmin: completely independent of active cashier shifts.
     */
    public function index(Request $request): JsonResponse
    {
        $today = Carbon::today();
        $period = $request->query('period', '');

        if ($period === 'daily' || $period === 'harian') {
            $startDate = $request->query('date', $today->toDateString());
            $endDate = $startDate;
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
            $startDate = $request->query('start_date', Carbon::now()->startOfMonth()->toDateString());
            $endDate = $request->query('end_date', Carbon::now()->toDateString());
        }

        $branchFilter = $request->query('branch', 'all');
        $locationIdFilter = $request->integer('location_id') ?: null;

        $stockService = app(LocationStockService::class);
        $filterResolution = $stockService->resolveReportFilterLocation($branchFilter, $locationIdFilter);
        $filterRequested = $filterResolution['requested'];
        $filterLoc = $filterResolution['location'];

        $locations = Location::where('type', '!=', 'WAREHOUSE')->orderBy('code')->get();
        $cacheKey = "consolidated_report_{$startDate}_{$endDate}_{$branchFilter}_{$locationIdFilter}";
        $cacheTtl = app()->runningUnitTests() ? 0 : 30;

        $computeReport = function () use ($startDate, $endDate, $branchFilter, $filterRequested, $filterLoc, $locations, $stockService) {
            // 1. Query base transactions without duplicate joins & classify deterministically by location_id
            $rawTransactions = Transaction::query()
                ->whereDate('created_at', '>=', $startDate)
                ->whereDate('created_at', '<=', $endDate)
                ->whereIn('status', ['SUCCESS', 'COMPLETED'])
                ->with(['items', 'location', 'shift'])
                ->get();

            $partitioned = $stockService->partitionTransactionsByStoreLocation(
                $rawTransactions,
                $locations,
                $filterLoc,
                $filterRequested
            );
            $transactions = $partitioned['store_transactions'];
            $byLocationId = $partitioned['by_location_id'];
            $warehouseTxs = $partitioned['warehouse_transactions'];
            $unmappedTxs = $partitioned['unmapped_transactions'];

            $grossRevenue = round((float) $transactions->sum('grand_total'), 2);
            $totalSubtotal = round((float) $transactions->sum('subtotal'), 2);
            $totalDiscounts = round((float) $transactions->sum('discount_total'), 2);
            $totalTax = round((float) $transactions->sum('tax_total'), 2);
            $transactionCount = $transactions->count();
            $averageBasket = $transactionCount > 0 ? round($grossRevenue / $transactionCount, 2) : 0;

            // Payment Method Breakdown
            $cashSales = round((float) $transactions->where('payment_method', 'TUNAI')->sum('grand_total'), 2);
            $qrisSales = round((float) $transactions->where('payment_method', 'QRIS')->sum('grand_total'), 2);
            $creditSales = round((float) $transactions->where('payment_method', 'KREDIT')->sum('grand_total'), 2);

            // Compute COGS cleanly via shared service
            $totalCogs = $stockService->calculateTransactionsCogs($transactions);
            $grossProfit = round($totalSubtotal - $totalCogs, 2);
            $netProfit = round($grossProfit - $totalDiscounts, 2);
            $profitMarginPercent = $grossRevenue > 0 ? round(($netProfit / $grossRevenue) * 100, 2) : 0;

            // 2. Real-time Member Debt & Credit across Store Branches
            $memberQuery = Member::query();
            if ($filterRequested) {
                if ($filterLoc && $filterLoc->type !== 'WAREHOUSE') {
                    $memberQuery->where('branch', $filterLoc->name);
                } else {
                    $memberQuery->whereRaw('1 = 0');
                }
            }
            $members = $memberQuery->get();

            $totalMembers = $members->count();
            $membersWithDebt = $members->where('current_debt', '>', 0)->count();
            $totalCreditLimit = (float) $members->sum('credit_limit');
            $totalOutstandingDebt = (float) $members->sum('current_debt');
            $totalRemainingCredit = (float) $members->sum('remaining_credit');
            $overallDebtUtilization = $totalCreditLimit > 0 ? round(($totalOutstandingDebt / $totalCreditLimit) * 100, 2) : 0;

            // 3. Stock Valuation & Inventory Health (Store product_stocks + Stock Master summary)
            $stockAgg = Product::selectRaw('
                COUNT(*) as total_count,
                COALESCE(SUM(stock), 0) as total_qty,
                COALESCE(SUM(stock * cost_price), 0) as total_asset_cost,
                COALESCE(SUM(stock * price), 0) as total_retail_value,
                COALESCE(SUM(CASE WHEN stock <= min_stock THEN 1 ELSE 0 END), 0) as low_stock_count
            ')->first();

            $totalProductCount = (int) ($stockAgg->total_count ?? 0);
            $totalInventoryStockQty = (int) ($stockAgg->total_qty ?? 0);
            $totalStockAssetCost = (float) ($stockAgg->total_asset_cost ?? 0);
            $totalStockRetailValue = (float) ($stockAgg->total_retail_value ?? 0);
            $lowStockCount = (int) ($stockAgg->low_stock_count ?? 0);
            $criticalExpiredCount = Product::whereNotNull('expired_date')
                ->whereDate('expired_date', '<=', Carbon::now()->addDays(14)->toDateString())
                ->count();

            // 4. Branch Comparative Matrix using authoritative location_id partitioning
            $membersByBranch = Member::selectRaw('branch, COUNT(*) as member_count, COALESCE(SUM(current_debt), 0) as total_debt')
                ->groupBy('branch')
                ->get()
                ->keyBy('branch');

            $stockByLocation = ProductStock::join('products', 'products.id', '=', 'product_stocks.product_id')
                ->whereNotNull('product_stocks.location_id')
                ->groupBy('product_stocks.location_id')
                ->selectRaw('product_stocks.location_id, COALESCE(SUM(product_stocks.stock * products.cost_price), 0) as valuation')
                ->pluck('valuation', 'product_stocks.location_id');

            if ($stockByLocation->isEmpty()) {
                $stockByLocation = StockBatch::selectRaw('location_id, COALESCE(SUM(remaining_qty * unit_cost), 0) as valuation')
                    ->whereNotNull('location_id')
                    ->groupBy('location_id')
                    ->pluck('valuation', 'location_id');
            }

            $warehouseLoc = $stockService->getWarehouseLocation();
            $warehouseStockValuation = $warehouseLoc ? round((float) ($stockByLocation[$warehouseLoc->id] ?? 0), 2) : 0.0;

            $branchBreakdown = [];
            foreach ($locations as $idx => $loc) {
                $bName = $loc->name;
                $bCode = $loc->code ?: ('BR-' . str_pad((string) ($idx + 1), 3, '0', STR_PAD_LEFT));
                $bMemberStat = $membersByBranch->get($bName);
                $bDebt = (float) ($bMemberStat->total_debt ?? 0);
                $bMemberCount = (int) ($bMemberStat->member_count ?? 0);

                $bTxs = ($filterRequested && (!$filterLoc || $filterLoc->id !== $loc->id))
                    ? collect()
                    : ($byLocationId[$loc->id] ?? collect());

                $bTxCount = $bTxs->count();
                $bRevenue = round((float) $bTxs->sum('grand_total'), 2);
                $bSubtotal = (float) $bTxs->sum('subtotal');
                $bCogs = $stockService->calculateTransactionsCogs($bTxs);
                $bGrossProfit = round($bSubtotal - $bCogs, 2);

                $branchBreakdown[] = [
                    'location_id' => $loc->id,
                    'branch_code' => $bCode,
                    'branch_name' => $bName,
                    'total_revenue' => $bRevenue,
                    'transaction_count' => $bTxCount,
                    'gross_profit' => $bGrossProfit,
                    'member_count' => $bMemberCount,
                    'member_debt' => $bDebt,
                    'stock_valuation_share' => round((float) ($stockByLocation[$loc->id] ?? 0), 2),
                    'status' => $loc->status ?? ($loc->is_active ? 'AKTIF' : 'NONAKTIF'),
                ];
            }

            // 5. Stock Mutations & Corrections Activity (Supervisor Audits)
            $recentCorrections = StockCorrection::with('product')
                ->orderByDesc('created_at')
                ->take(10)
                ->get()
                ->map(fn ($c) => [
                    'code' => $c->correction_code,
                    'product_name' => $c->product?->name ?? 'Produk',
                    'branch' => $c->branch,
                    'type' => $c->type,
                    'qty' => $c->qty,
                    'stock_before' => $c->stock_before,
                    'stock_after' => $c->stock_after,
                    'reason' => $c->reason,
                    'supervisor_name' => $c->supervisor_name,
                    'created_at' => $c->created_at->format('Y-m-d H:i'),
                ]);

            return [
                'period' => [
                    'start_date' => $startDate,
                    'end_date' => $endDate,
                ],
                'financial_summary' => [
                    'gross_revenue' => $grossRevenue,
                    'total_subtotal' => $totalSubtotal,
                    'total_cogs' => $totalCogs,
                    'gross_profit' => $grossProfit,
                    'total_discounts' => $totalDiscounts,
                    'total_tax' => $totalTax,
                    'net_profit' => $netProfit,
                    'profit_margin_percent' => $profitMarginPercent,
                    'transaction_count' => $transactionCount,
                    'average_basket' => $averageBasket,
                    'payment_breakdown' => [
                        'cash' => $cashSales,
                        'qris' => $qrisSales,
                        'credit_kasbon' => $creditSales,
                    ],
                ],
                'member_debt_summary' => [
                    'total_members' => $totalMembers,
                    'members_with_debt' => $membersWithDebt,
                    'total_credit_limit' => $totalCreditLimit,
                    'total_outstanding_debt' => $totalOutstandingDebt,
                    'total_remaining_credit' => $totalRemainingCredit,
                    'overall_debt_utilization_percent' => $overallDebtUtilization,
                ],
                'member_debt_overview' => [
                    'total_members' => $totalMembers,
                    'members_with_debt' => $membersWithDebt,
                    'total_credit_limit' => $totalCreditLimit,
                    'total_outstanding_debt' => $totalOutstandingDebt,
                    'total_remaining_credit' => $totalRemainingCredit,
                    'overall_debt_utilization_percent' => $overallDebtUtilization,
                ],
                'inventory_valuation' => [
                    'total_skus' => $totalProductCount,
                    'total_stock_qty' => $totalInventoryStockQty,
                    'total_asset_cost_value' => $totalStockAssetCost,
                    'total_retail_market_value' => $totalStockRetailValue,
                    'potential_gross_margin' => $totalStockRetailValue - $totalStockAssetCost,
                    'low_stock_items' => $lowStockCount,
                    'critical_expired_items' => $criticalExpiredCount,
                    'warehouse_stock_valuation' => $warehouseStockValuation,
                ],
                'stock_valuation' => [
                    'total_product_count' => $totalProductCount,
                    'total_inventory_stock_qty' => $totalInventoryStockQty,
                    'total_stock_asset_cost' => $totalStockAssetCost,
                    'total_stock_retail_value' => $totalStockRetailValue,
                    'low_stock_count' => $lowStockCount,
                    'critical_expired_count' => $criticalExpiredCount,
                    'warehouse_stock_valuation' => $warehouseStockValuation,
                ],
                'branch_breakdown' => $branchBreakdown,
                'branch_comparison' => $branchBreakdown,
                'excluded_warehouse_transactions' => $stockService->summarizeExcludedTransactions($warehouseTxs),
                'unmapped_transactions' => $stockService->summarizeExcludedTransactions($unmappedTxs),
                'recent_stock_corrections' => $recentCorrections,
            ];
        };

        $responseData = $cacheTtl > 0
            ? \Illuminate\Support\Facades\Cache::remember($cacheKey, $cacheTtl, $computeReport)
            : $computeReport();

        return response()->json(array_merge([
            'success' => true,
            'filters' => [
                'start_date' => $startDate,
                'end_date' => $endDate,
                'branch' => $branchFilter,
                'location_id' => $locationIdFilter,
            ],
            'data' => $responseData,
        ], $responseData))->header('Cache-Control', 'private, max-age=10, stale-while-revalidate=30');
    }
}
