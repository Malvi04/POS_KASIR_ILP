<?php

namespace App\Http\Controllers\Api\Owner;

use App\Http\Controllers\Controller;
use App\Models\Location;
use App\Models\Product;
use App\Models\ProductStock;
use App\Models\Transaction;
use App\Services\LocationStockService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OwnerDashboardController extends Controller
{
    /**
     * Get real-time executive dashboard summary for Owner.
     */
    public function summary(Request $request): JsonResponse
    {
        $today = Carbon::today();
        $startDate = $request->query('start_date', $request->query('date', $today->toDateString()));
        $endDate = $request->query('end_date', $request->query('date', $today->toDateString()));
        $cacheKey = 'owner_dashboard_summary_' . $startDate . '_' . $endDate;
        $cacheTtl = app()->runningUnitTests() ? 0 : 15;

        $computeSummary = function () use ($startDate, $endDate) {
            $stockService = app(LocationStockService::class);
            $locations = Location::orderBy('code')->get();
            $stores = $locations->where('type', '!=', 'WAREHOUSE')->values();
            $warehouse = $locations->firstWhere('type', 'WAREHOUSE') ?? $locations->first();

            // 1. Transactions in period (no join duplicates; partitioned by official location_id)
            $rawTrxs = Transaction::whereDate('created_at', '>=', $startDate)
                ->whereDate('created_at', '<=', $endDate)
                ->whereIn('status', ['SUCCESS', 'COMPLETED'])
                ->with(['items', 'location', 'shift'])
                ->get();

            $partitioned = $stockService->partitionTransactionsByStoreLocation($rawTrxs, $stores);
            $storeTrxs = $partitioned['store_transactions'];
            $byLocationId = $partitioned['by_location_id'];

            $totalSalesToday = round((float) $storeTrxs->sum('grand_total'), 2);
            $totalTrxToday = $storeTrxs->count();
            $totalCogsToday = $stockService->calculateTransactionsCogs($storeTrxs);
            $totalProfitToday = max(0, round($totalSalesToday - $totalCogsToday, 2));

            // 2. Branch breakdown strictly keyed by store location_id
            $branchSalesMap = [];
            $palette = [
                ['dot' => 'bg-[#0052cc]', 'bar' => 'bg-[#0052cc]'],
                ['dot' => 'bg-emerald-500', 'bar' => 'bg-emerald-500'],
                ['dot' => 'bg-amber-500', 'bar' => 'bg-amber-500'],
                ['dot' => 'bg-purple-500', 'bar' => 'bg-purple-500'],
            ];

            foreach ($stores->values() as $idx => $store) {
                $branchTrxs = $byLocationId[$store->id] ?? collect();
                $rev = round((float) $branchTrxs->sum('grand_total'), 2);
                $cnt = $branchTrxs->count();
                $color = $palette[$idx % count($palette)];

                $branchSalesMap[] = [
                    'id' => $store->id,
                    'location_id' => $store->id,
                    'code' => $store->code,
                    'name' => $store->name,
                    'type' => 'Cabang Toko',
                    'manager' => $store->manager ?? 'Manager Cabang',
                    'rawSales' => $rev,
                    'trxCount' => $cnt,
                    'status' => $store->status ?? 'AKTIF',
                    'statusColor' => ($store->status ?? 'AKTIF') === 'AKTIF' ? 'bg-emerald-50 text-emerald-700 border-emerald-200' : 'bg-slate-100 text-slate-600 border-slate-200',
                    'percentage' => $totalSalesToday > 0 ? round(($rev / $totalSalesToday) * 100) : 0,
                    'avgTicket' => $cnt > 0 ? round($rev / $cnt) : 0,
                    'colorDot' => $color['dot'],
                    'barColor' => $color['bar'],
                ];
            }

            // 3. Active Cashiers & Terminals across all branches (Open Shifts + Today's Portal Logins)
            $openShifts = \App\Models\CashierShift::with('location')
                ->where('status', 'OPEN')
                ->orderByDesc('opened_at')
                ->get();

            $activeCashiers = [];
            $seenUserIds = [];
            $seenNames = [];

            if ($openShifts->isNotEmpty()) {
                $salesByShift = Transaction::whereIn('shift_id', $openShifts->pluck('id'))
                    ->whereIn('status', ['SUCCESS', 'COMPLETED'])
                    ->groupBy('shift_id')
                    ->selectRaw('shift_id, SUM(grand_total) as total')
                    ->pluck('total', 'shift_id');

                foreach ($openShifts as $shift) {
                    if ($shift->user_id) {
                        $seenUserIds[$shift->user_id] = true;
                    }
                    $name = $shift->cashier_name ?: ('Kasir ' . $shift->terminal_code);
                    $seenNames[strtolower(trim($name))] = true;
                    $shiftSales = (float) ($salesByShift[$shift->id] ?? 0);

                    $activeCashiers[] = [
                        'id' => $shift->id,
                        'name' => $name,
                        'terminal' => $shift->terminal_code,
                        'branch' => $shift->location?->name ?? $shift->branch ?? 'Cabang Pusat',
                        'location_id' => $shift->location_id,
                        'opened_at' => $shift->opened_at ? $shift->opened_at->format('H:i') : '-',
                        'opening_cash' => (float) $shift->opening_cash,
                        'sales_today' => $shiftSales,
                        'status' => 'SESI AKTIF',
                    ];
                }
            }

            $todayAttendances = \App\Models\Attendance::with('user')
                ->whereDate('date', Carbon::today())
                ->where('status', 'HADIR')
                ->where(function ($q) {
                    $q->whereNull('check_out')->orWhere('check_out', '')->orWhere('check_out', '-');
                })
                ->orderByDesc('id')
                ->get();

            foreach ($todayAttendances as $att) {
                if ($att->user && !$att->user->isKasir()) {
                    continue;
                }
                if ($att->user_id && isset($seenUserIds[$att->user_id])) {
                    continue;
                }
                $normName = strtolower(trim((string) $att->staff_name));
                if ($normName !== '' && isset($seenNames[$normName])) {
                    continue;
                }
                if ($att->user_id) {
                    $seenUserIds[$att->user_id] = true;
                }
                if ($normName !== '') {
                    $seenNames[$normName] = true;
                }

                $attLoc = $att->branch ? $stockService->resolveLocation(null, $att->branch, null, null, 'STORE') : null;
                $activeCashiers[] = [
                    'id' => 'att-' . $att->id,
                    'name' => $att->staff_name ?: 'Kasir',
                    'terminal' => ($att->terminal && $att->terminal !== '-') ? $att->terminal : 'Login Portal',
                    'branch' => $attLoc?->name ?? ($att->branch ?: 'Cabang Pusat'),
                    'location_id' => $attLoc?->id,
                    'opened_at' => $att->check_in ?: '-',
                    'opening_cash' => 0,
                    'sales_today' => 0,
                    'status' => 'ONLINE PORTAL',
                ];
            }

            // 4. Critical Products (Location-aware ProductStock via fast SQL, fallback to Stock Master when empty)
            $totalProductsCount = Product::count();
            $hasLocStocks = ProductStock::exists();
            if ($hasLocStocks) {
                $lowCondSql = '(product_stocks.stock <= 0 OR product_stocks.stock <= COALESCE(product_stocks.min_stock, products.min_stock, 10))';
                $lowStockCount = (int) \Illuminate\Support\Facades\DB::table('product_stocks')
                    ->join('products', function ($join) {
                        $join->on('products.id', '=', 'product_stocks.product_id')
                            ->whereNull('products.deleted_at');
                    })
                    ->join('locations', 'locations.id', '=', 'product_stocks.location_id')
                    ->where('product_stocks.is_active', true)
                    ->whereRaw($lowCondSql)
                    ->count();

                $criticalItems = ProductStock::query()
                    ->select('product_stocks.*')
                    ->join('products', function ($join) {
                        $join->on('products.id', '=', 'product_stocks.product_id')
                            ->whereNull('products.deleted_at');
                    })
                    ->join('locations', 'locations.id', '=', 'product_stocks.location_id')
                    ->where('product_stocks.is_active', true)
                    ->whereRaw($lowCondSql)
                    ->with(['product', 'location'])
                    ->orderBy('product_stocks.stock', 'asc')
                    ->orderBy('product_stocks.id', 'asc')
                    ->limit(5)
                    ->get()
                    ->map(function ($ps) {
                        $p = $ps->product;
                        $minStock = (int) ($ps->min_stock ?? $p->min_stock ?? 10);
                        return [
                            'sku' => $p->sku,
                            'nama' => $p->name,
                            'location' => $ps->location?->name ?? '-',
                            'location_code' => $ps->location?->code ?? '-',
                            'vendor' => $p->category ?? 'Vendor Utama',
                            'stok' => (int) $ps->stock,
                            'min' => $minStock,
                            'unit' => $p->unit ?? 'Pcs',
                            'status' => (int) $ps->stock <= 0 ? 'Habis' : 'Menipis',
                        ];
                    })->values();
            } else {
                $criticalQuery = Product::where(function ($q) {
                    $q->whereColumn('stock', '<=', 'min_stock')
                      ->orWhere('stock', '<=', 10);
                })->orderBy('stock', 'asc');
                $lowStockCount = (clone $criticalQuery)->count();
                $criticalItems = (clone $criticalQuery)->take(5)->get(['sku', 'name', 'category', 'stock', 'min_stock', 'unit'])->map(function ($p) {
                    return [
                        'sku' => $p->sku,
                        'nama' => $p->name,
                        'vendor' => $p->category ?? 'Vendor Utama',
                        'stok' => (int) $p->stock,
                        'min' => (int) ($p->min_stock ?? 10),
                        'unit' => $p->unit ?? 'Pcs',
                        'status' => (int) $p->stock <= 0 ? 'Habis' : 'Menipis',
                    ];
                })->values();
            }

            return [
                'period' => ['start_date' => $startDate, 'end_date' => $endDate],
                'total_sales_today' => $totalSalesToday,
                'total_trx_today' => $totalTrxToday,
                'total_profit_today' => $totalProfitToday,
                'branches' => $branchSalesMap,
                'excluded_warehouse_transactions' => $stockService->summarizeExcludedTransactions($partitioned['warehouse_transactions']),
                'unmapped_transactions' => $stockService->summarizeExcludedTransactions($partitioned['unmapped_transactions']),
                'warehouse' => $warehouse ? [
                    'id' => $warehouse->id,
                    'code' => $warehouse->code,
                    'name' => $warehouse->name,
                    'type' => 'Gudang Logistik',
                    'manager' => $warehouse->manager ?? '-',
                    'status' => $warehouse->status ?? 'AKTIF',
                    'statusColor' => 'bg-blue-50 text-blue-700 border-blue-200',
                ] : [
                    'id' => null,
                    'code' => '-',
                    'name' => 'Belum Ada Gudang Terdaftar',
                    'type' => 'Gudang Logistik',
                    'manager' => '-',
                    'status' => 'BELUM DIATUR',
                    'statusColor' => 'bg-slate-100 text-slate-600 border-slate-200',
                ],
                'critical_items' => $criticalItems,
                'low_stock_count' => $lowStockCount,
                'total_products' => $totalProductsCount,
                'total_branches' => $stores->count(),
                'active_cashiers_count' => count($activeCashiers),
                'active_cashiers' => $activeCashiers,
            ];
        };

        $data = $cacheTtl > 0
            ? \Illuminate\Support\Facades\Cache::remember($cacheKey, $cacheTtl, $computeSummary)
            : $computeSummary();

        return response()->json([
            'success' => true,
            'data' => $data,
        ])->header('Cache-Control', 'private, max-age=5, stale-while-revalidate=15');
    }
}
