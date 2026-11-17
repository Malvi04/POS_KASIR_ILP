<?php

namespace App\Http\Controllers\Api\Manager;

use App\Http\Controllers\Controller;
use App\Models\AppSetting;
use App\Models\DebtMutation;
use App\Models\Location;
use App\Models\Member;
use App\Models\Product;
use App\Models\StockBatch;
use App\Models\StockCorrection;
use App\Models\StockOpname;
use App\Models\Transaction;
use App\Models\TransactionItem;
use App\Models\User;
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
        $startDate = $request->query('start_date', Carbon::now()->startOfMonth()->toDateString());
        $endDate = $request->query('end_date', Carbon::now()->toDateString());
        $branchFilter = $request->query('branch', 'all');

        $locations = Location::where('type', '!=', 'WAREHOUSE')->orderBy('code')->get();

        $cacheKey = "consolidated_report_{$startDate}_{$endDate}_{$branchFilter}";
        $responseData = \Illuminate\Support\Facades\Cache::remember($cacheKey, 30, function () use ($startDate, $endDate, $branchFilter, $locations) {
            // 1. Transaction & Sales Query
            $txQuery = Transaction::query()
                ->whereDate('created_at', '>=', $startDate)
                ->whereDate('created_at', '<=', $endDate)
                ->whereIn('status', ['SUCCESS', 'COMPLETED']);

            if ($branchFilter !== 'all') {
                $txQuery->where('terminal_code', 'LIKE', "%{$branchFilter}%");
            }

            $transactions = $txQuery->with('items')->get();

            $grossRevenue = (float) $transactions->sum('grand_total');
            $totalSubtotal = (float) $transactions->sum('subtotal');
            $totalDiscounts = (float) $transactions->sum('discount_total');
            $totalTax = (float) $transactions->sum('tax_total');
            $transactionCount = $transactions->count();
            $averageBasket = $transactionCount > 0 ? round($grossRevenue / $transactionCount, 2) : 0;

        // Payment Method Breakdown
        $cashSales = (float) $transactions->where('payment_method', 'TUNAI')->sum('grand_total');
        $qrisSales = (float) $transactions->where('payment_method', 'QRIS')->sum('grand_total');
        $creditSales = (float) $transactions->where('payment_method', 'KREDIT')->sum('grand_total');

        // Estimate COGS (HPP) in single batch query (No N+1 loop)
        $allSkus = [];
        foreach ($transactions as $tx) {
            foreach ($tx->items as $item) {
                if (empty($item->cost_price) && !empty($item->sku)) {
                    $allSkus[$item->sku] = true;
                }
            }
        }
        $skuCosts = empty($allSkus) ? [] : Product::whereIn('sku', array_keys($allSkus))->pluck('cost_price', 'sku')->all();

        $totalCogs = 0.0;
        foreach ($transactions as $tx) {
            foreach ($tx->items as $item) {
                $cost = $item->cost_price ?? null;
                if (!$cost) {
                    $cost = $skuCosts[$item->sku] ?? 0.0;
                }
                $totalCogs += ((float) $cost * (int) ($item->quantity ?? $item->qty ?? 1));
            }
        }

        $grossProfit = $totalSubtotal - $totalCogs;
        $netProfit = $grossProfit - $totalDiscounts;
        $profitMarginPercent = $grossRevenue > 0 ? round(($netProfit / $grossRevenue) * 100, 2) : 0;

        // 2. Real-time Member Debt & Credit across 3 Branches
        $memberQuery = Member::query();
        if ($branchFilter !== 'all') {
            $memberQuery->where('branch', $branchFilter);
        }
        $members = $memberQuery->get();

        $totalMembers = $members->count();
        $membersWithDebt = $members->where('current_debt', '>', 0)->count();
        $totalCreditLimit = (float) $members->sum('credit_limit');
        $totalOutstandingDebt = (float) $members->sum('current_debt');
        $totalRemainingCredit = (float) $members->sum('remaining_credit');
        $overallDebtUtilization = $totalCreditLimit > 0 ? round(($totalOutstandingDebt / $totalCreditLimit) * 100, 2) : 0;

        // 3. Stock Valuation & Inventory Health - Single Aggregation Query
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

        // 4. Branch Comparative Matrix (Real PostgreSQL Location, Member, StockBatch & Transaction Queries)
        $txUserIds = $transactions->pluck('user_id')->filter()->unique()->all();
        $userBranches = empty($txUserIds) ? collect() : User::whereIn('id', $txUserIds)->pluck('branch', 'id');

        $membersByBranch = Member::selectRaw('branch, COUNT(*) as member_count, COALESCE(SUM(current_debt), 0) as total_debt')
            ->groupBy('branch')
            ->get()
            ->keyBy('branch');

        $stockByLocation = StockBatch::selectRaw('location_id, COALESCE(SUM(remaining_qty * unit_cost), 0) as valuation')
            ->whereNotNull('location_id')
            ->groupBy('location_id')
            ->pluck('valuation', 'location_id');

        $branchBreakdown = [];
        foreach ($locations as $idx => $loc) {
            $bName = $loc->name;
            $bCode = $loc->code ?: ('BR-' . str_pad((string) ($idx + 1), 3, '0', STR_PAD_LEFT));
            $bMemberStat = $membersByBranch->get($bName);
            $bDebt = (float) ($bMemberStat->total_debt ?? 0);
            $bMemberCount = (int) ($bMemberStat->member_count ?? 0);

            $bTxs = $transactions->filter(function ($tx) use ($idx, $bName, $bCode, $userBranches) {
                $term = strtolower((string) $tx->terminal_code);
                $uBranch = strtolower((string) ($userBranches[$tx->user_id] ?? ''));
                return ($uBranch !== '' && (str_contains($uBranch, strtolower($bName)) || str_contains(strtolower($bName), $uBranch))) ||
                       str_contains($term, strtolower($bCode)) ||
                       str_contains($term, 'cabang-' . ($idx + 1)) ||
                       str_contains($term, 'pos-0' . ($idx + 1)) ||
                       str_contains($term, strtolower($bName));
            });

            $bTxCount = $bTxs->count();
            $bRevenue = (float) $bTxs->sum('grand_total');
            $bGrossProfit = $bRevenue > 0 ? round($bRevenue * ($profitMarginPercent / 100), 2) : 0.0;

            $branchBreakdown[] = [
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
            ->map(fn($c) => [
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

        $responseData = [
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
            ],
            'stock_valuation' => [
                'total_product_count' => $totalProductCount,
                'total_inventory_stock_qty' => $totalInventoryStockQty,
                'total_stock_asset_cost' => $totalStockAssetCost,
                'total_stock_retail_value' => $totalStockRetailValue,
                'low_stock_count' => $lowStockCount,
                'critical_expired_count' => $criticalExpiredCount,
            ],
            'branch_breakdown' => $branchBreakdown,
            'branch_comparison' => $branchBreakdown,
            'recent_stock_corrections' => $recentCorrections,
        ];

        return $responseData;
    });

        return response()->json(array_merge([
            'success' => true,
            'filters' => [
                'start_date' => $startDate,
                'end_date' => $endDate,
                'branch' => $branchFilter,
            ],
            'data' => $responseData,
        ], $responseData))->header('Cache-Control', 'private, max-age=10, stale-while-revalidate=30');
    }
}
