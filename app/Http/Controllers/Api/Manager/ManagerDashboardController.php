<?php

namespace App\Http\Controllers\Api\Manager;

use App\Http\Controllers\Controller;
use App\Models\CashierShift;
use App\Models\Location;
use App\Models\Product;
use App\Models\ProductStock;
use App\Models\Transaction;
use App\Services\LocationStockService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ManagerDashboardController extends Controller
{
    /**
     * Get aggregated managerial dashboard summary.
     */
    public function summary(Request $request): JsonResponse
    {
        $today = Carbon::today();
        $tomorrow = $today->copy()->addDay();
        $branchFilter = $request->query('branch', 'all');
        $locationIdFilter = $request->integer('location_id') ?: null;

        $stockService = app(LocationStockService::class);
        $filterResolution = $stockService->resolveReportFilterLocation($branchFilter, $locationIdFilter);
        $filterRequested = $filterResolution['requested'];
        $filterLoc = $filterResolution['location'];

        $allLocations = Location::orderBy('code')->get();
        $storeLocations = $allLocations->where('type', '!=', 'WAREHOUSE')->values();
        $warehouseLocation = $allLocations->firstWhere('type', 'WAREHOUSE');

        // 1. Transactions Today (authoritative location partitioning; excludes Warehouse from store sales)
        $rawTodayTxs = Transaction::where('created_at', '>=', $today)
            ->where('created_at', '<', $tomorrow)
            ->whereIn('status', ['SUCCESS', 'COMPLETED'])
            ->with(['location', 'shift'])
            ->get();

        $todayPartitioned = $stockService->partitionTransactionsByStoreLocation(
            $rawTodayTxs,
            $storeLocations,
            $filterLoc,
            $filterRequested
        );
        $todayStoreTxs = $todayPartitioned['store_transactions'];
        $trxCountToday = $todayStoreTxs->count();
        $salesToday = round((float) $todayStoreTxs->sum('grand_total'), 2);

        // 2. Low Stock & Per-Location Stock Breakdown (SQL-aggregated on PostgreSQL — zero memory bloat on 60k+ rows)
        $hasLocationStocks = ProductStock::exists();
        $warehouseStockSummary = null;
        $storeStocksSummary = [];

        if ($hasLocationStocks) {
            $lowCondSql = '(product_stocks.stock <= COALESCE(product_stocks.min_stock, products.min_stock, 10) OR product_stocks.stock <= 10)';

            $aggByLocation = DB::table('product_stocks')
                ->join('products', function ($join) {
                    $join->on('products.id', '=', 'product_stocks.product_id')
                        ->whereNull('products.deleted_at');
                })
                ->join('locations', 'locations.id', '=', 'product_stocks.location_id')
                ->where('product_stocks.is_active', true)
                ->groupBy('product_stocks.location_id')
                ->selectRaw(
                    "product_stocks.location_id,
                    COUNT(*) as total_skus,
                    COALESCE(SUM(product_stocks.stock), 0) as total_units,
                    COALESCE(SUM(CASE WHEN product_stocks.stock <= 0 THEN 1 ELSE 0 END), 0) as out_of_stock_count,
                    COALESCE(SUM(CASE WHEN {$lowCondSql} THEN 1 ELSE 0 END), 0) as low_stock_count"
                )
                ->get()
                ->keyBy('location_id');

            $buildLocSummary = function (Location $loc) use ($aggByLocation): array {
                $row = $aggByLocation->get($loc->id);
                return [
                    'location_id' => $loc->id,
                    'location_code' => $loc->code,
                    'location_name' => $loc->name,
                    'location_type' => $loc->type,
                    'total_skus' => (int) ($row->total_skus ?? 0),
                    'total_units' => (int) ($row->total_units ?? 0),
                    'out_of_stock_count' => (int) ($row->out_of_stock_count ?? 0),
                    'low_stock_count' => (int) ($row->low_stock_count ?? 0),
                ];
            };

            if ($warehouseLocation) {
                $warehouseStockSummary = $buildLocSummary($warehouseLocation);
            }
            foreach ($storeLocations as $sLoc) {
                $storeStocksSummary[] = $buildLocSummary($sLoc);
            }

            if ($filterRequested && !$filterLoc) {
                $lowStockCount = 0;
                $lowStockItems = collect();
            } else {
                if ($filterRequested && $filterLoc) {
                    $lowStockCount = (int) ($aggByLocation->get($filterLoc->id)->low_stock_count ?? 0);
                } else {
                    $lowStockCount = (int) $aggByLocation->sum('low_stock_count');
                }

                $lowQuery = ProductStock::query()
                    ->select('product_stocks.*')
                    ->join('products', function ($join) {
                        $join->on('products.id', '=', 'product_stocks.product_id')
                            ->whereNull('products.deleted_at');
                    })
                    ->join('locations', 'locations.id', '=', 'product_stocks.location_id')
                    ->where('product_stocks.is_active', true)
                    ->whereRaw($lowCondSql);

                if ($filterRequested && $filterLoc) {
                    $lowQuery->where('product_stocks.location_id', $filterLoc->id);
                }

                $lowStockItems = $lowQuery
                    ->with(['product', 'location'])
                    ->orderBy('product_stocks.stock', 'asc')
                    ->orderBy('product_stocks.id', 'asc')
                    ->limit(5)
                    ->get()
                    ->map(function (ProductStock $ps) {
                        $p = $ps->product;
                        $loc = $ps->location;
                        $isZero = (int) $ps->stock === 0;
                        $minStock = (int) ($ps->min_stock ?? $p->min_stock ?? 10);
                        return [
                            'id' => $p->id,
                            'product_stock_id' => $ps->id,
                            'location_id' => $loc->id,
                            'location_code' => $loc->code,
                            'location_name' => $loc->name,
                            'location_type' => $loc->type,
                            'nama' => $p->name,
                            'stok' => (int) $ps->stock,
                            'min_stock' => $minStock,
                            'kategori' => $p->category,
                            'statusText' => $isZero ? 'STOK KOSONG' : 'STOK KRITIS',
                            'desc' => $isZero
                                ? "[{$loc->name}] Stok habis — segera lakukan distribusi/re-order."
                                : "[{$loc->name}] Tersisa " . (int) $ps->stock . ' ' . $p->unit . '.',
                        ];
                    })->values();
            }
        } else {
            // Legacy compatibility fallback ONLY when no product_stocks rows have been distributed yet
            $lowStockQuery = Product::where(function ($q) {
                    $q->whereColumn('stock', '<=', 'min_stock')
                      ->orWhere('stock', '<=', 10);
                })
                ->orderBy('stock', 'asc');

            $lowStockCount = $filterRequested ? 0 : (clone $lowStockQuery)->count();
            $criticalProducts = $filterRequested ? collect() : (clone $lowStockQuery)->take(5)->get();

            $lowStockItems = $criticalProducts->map(function ($p) {
                $isZero = (int) $p->stock === 0;
                return [
                    'id' => $p->id,
                    'location_id' => null,
                    'location_code' => 'GLOBAL',
                    'location_name' => 'Global Stock Master',
                    'location_type' => 'MASTER',
                    'nama' => $p->name,
                    'stok' => (int) $p->stock,
                    'min_stock' => (int) $p->min_stock,
                    'kategori' => $p->category,
                    'statusText' => $isZero ? 'STOK KOSONG' : 'STOK KRITIS',
                    'desc' => $isZero ? 'Segera lakukan PO / re-order vendor.' : 'Tersisa ' . (int) $p->stock . ' ' . $p->unit . ' (Master Global).',
                ];
            })->values();
        }

        // Explicit Global Stock Master definition (kept separate from per-store availability)
        $globalMasterAgg = Product::selectRaw('COUNT(*) as sku_count, COALESCE(SUM(stock), 0) as total_units')->first();
        $globalStockMaster = [
            'total_skus' => (int) ($globalMasterAgg->sku_count ?? 0),
            'total_units' => (int) ($globalMasterAgg->total_units ?? 0),
            'definition' => 'Agregat Stock Master seluruh lokasi (Gudang Pusat + semua Toko); bukan stok tersedia di masing-masing toko.',
        ];

        // 3. Active Cashiers & Terminals (Open Shifts + Cashiers Logged Into Portal Today)
        $openShiftsQuery = CashierShift::with('location')
            ->where('status', 'OPEN')
            ->orderBy('opened_at', 'desc');
        if ($filterRequested && $filterLoc) {
            $openShiftsQuery->where('location_id', $filterLoc->id);
        }
        $openShifts = $openShiftsQuery->get();

        $cashierList = [];
        $representedUserIds = [];
        $representedNames = [];

        if ($openShifts->count() > 0) {
            $salesByShift = Transaction::whereIn('shift_id', $openShifts->pluck('id'))
                ->whereIn('status', ['SUCCESS', 'COMPLETED'])
                ->groupBy('shift_id')
                ->selectRaw('shift_id, SUM(grand_total) as total')
                ->pluck('total', 'shift_id');

            foreach ($openShifts as $shift) {
                $shiftSales = (float) ($salesByShift[$shift->id] ?? 0);

                $name = $shift->cashier_name ?: 'Kasir ' . $shift->terminal_code;
                $initials = collect(explode(' ', $name))
                    ->map(fn ($w) => strtoupper(substr($w, 0, 1)))
                    ->take(2)
                    ->join('');

                if ($shift->user_id) {
                    $representedUserIds[$shift->user_id] = true;
                }
                $representedNames[strtolower(trim($name))] = true;

                $cashierList[] = [
                    'id' => $shift->id,
                    'name' => $name,
                    'initial' => $initials ?: 'KS',
                    'terminal' => $shift->terminal_code . ' • ' . ($shift->location?->name ?? $shift->branch ?? 'Toko Pusat'),
                    'location_id' => $shift->location_id,
                    'branch' => $shift->location?->name ?? $shift->branch,
                    'total' => 'Rp ' . number_format($shiftSales, 0, ',', '.') . ($shiftSales <= 0 && (float) $shift->opening_cash > 0 ? ' (Modal Rp ' . number_format((float) $shift->opening_cash, 0, ',', '.') . ')' : ''),
                    'status' => 'Aktif',
                    'color' => 'bg-green-500',
                ];
            }
        }

        // Include cashiers who logged in today (active check-in without check-out) even before opening a shift
        $activeAttendances = \App\Models\Attendance::with('user')
            ->whereDate('date', $today)
            ->where('status', 'HADIR')
            ->where(function ($q) {
                $q->whereNull('check_out')->orWhere('check_out', '')->orWhere('check_out', '-');
            })
            ->orderByDesc('id')
            ->get();

        foreach ($activeAttendances as $att) {
            if ($att->user && !$att->user->isKasir()) {
                continue;
            }
            if ($att->user_id && isset($representedUserIds[$att->user_id])) {
                continue;
            }
            $normName = strtolower(trim((string) $att->staff_name));
            if ($normName !== '' && isset($representedNames[$normName])) {
                continue;
            }

            $attLoc = $att->branch ? $stockService->resolveLocation(null, $att->branch, null, null, 'STORE') : null;
            if ($filterRequested && $filterLoc && $attLoc && $attLoc->id !== $filterLoc->id) {
                continue;
            }

            if ($att->user_id) {
                $representedUserIds[$att->user_id] = true;
            }
            if ($normName !== '') {
                $representedNames[$normName] = true;
            }

            $name = $att->staff_name ?: 'Kasir';
            $initials = collect(explode(' ', $name))
                ->map(fn ($w) => strtoupper(substr($w, 0, 1)))
                ->take(2)
                ->join('');
            $termDisplay = ($att->terminal && $att->terminal !== '-') ? $att->terminal : 'Login Portal';
            $branchDisplay = $attLoc?->name ?? ($att->branch ?: 'Cabang Pusat');

            $cashierList[] = [
                'id' => 'att-' . $att->id,
                'name' => $name,
                'initial' => $initials ?: 'KS',
                'terminal' => $termDisplay . ' • ' . $branchDisplay,
                'location_id' => $attLoc?->id,
                'branch' => $branchDisplay,
                'total' => 'Masuk ' . ($att->check_in ?: '-') . ' WIB',
                'status' => 'Aktif',
                'color' => 'bg-emerald-500',
            ];
        }

        // 4. Weekly Sales Chart (authoritative store partitioning)
        $weeklySales = [];
        $dayNames = ['SEN', 'SEL', 'RAB', 'KAM', 'JUM', 'SAB', 'MIN'];
        $maxDailySales = 1;

        $rawWeeklyTxs = Transaction::where('created_at', '>=', $today->copy()->subDays(6))
            ->where('created_at', '<', $tomorrow)
            ->whereIn('status', ['SUCCESS', 'COMPLETED'])
            ->with(['location', 'shift'])
            ->get();
        $weeklyPartitioned = $stockService->partitionTransactionsByStoreLocation(
            $rawWeeklyTxs,
            $storeLocations,
            $filterLoc,
            $filterRequested
        );
        $dailyTotals = $weeklyPartitioned['store_transactions']
            ->groupBy(fn ($tx) => Carbon::parse($tx->created_at)->toDateString())
            ->map(fn ($dayTxs) => (float) $dayTxs->sum('grand_total'));

        for ($i = 6; $i >= 0; $i--) {
            $date = Carbon::today()->subDays($i);
            $dayTotal = (float) ($dailyTotals[$date->toDateString()] ?? 0);

            if ($dayTotal > $maxDailySales) {
                $maxDailySales = $dayTotal;
            }

            $weeklySales[] = [
                'date' => $date->toDateString(),
                'day' => $dayNames[$date->dayOfWeekIso - 1] ?? 'HRI',
                'sales' => $dayTotal,
                'height_percent' => 0,
            ];
        }

        foreach ($weeklySales as &$day) {
            $day['height_percent'] = $day['sales'] > 0
                ? max(10, min(100, (int) (($day['sales'] / $maxDailySales) * 100)))
                : 0;
        }

        return response()->json([
            'success' => true,
            'data' => [
                'sales_today' => (float) $salesToday,
                'trx_count_today' => (int) $trxCountToday,
                'low_stock_count' => (int) $lowStockCount,
                'low_stock_items' => $lowStockItems,
                'warehouse_stock_summary' => $warehouseStockSummary,
                'store_stocks_summary' => $storeStocksSummary,
                'global_stock_master' => $globalStockMaster,
                'cashier_list' => $cashierList,
                'weekly_sales' => $weeklySales,
            ],
        ]);
    }
}
