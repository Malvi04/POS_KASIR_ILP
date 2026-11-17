<?php

namespace App\Http\Controllers\Api\Manager;

use App\Http\Controllers\Controller;
use App\Models\CashierShift;
use App\Models\Product;
use App\Models\Transaction;
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

        // 1. Transactions Today (single aggregate query, range-based so created_at index is usable)
        $aggregateSql = "COUNT(*) as trx_count, COALESCE(SUM(CASE WHEN status IN ('SUCCESS', 'COMPLETED') THEN grand_total ELSE 0 END), 0) as sales_total";
        $todayAgg = Transaction::where('created_at', '>=', $today)
            ->where('created_at', '<', $tomorrow)
            ->selectRaw($aggregateSql)
            ->first();
        $trxCountToday = (int) ($todayAgg->trx_count ?? 0);
        $salesToday = (float) ($todayAgg->sales_total ?? 0);

        // 2. Low Stock & Critical Items
        $lowStockQuery = Product::where(function ($q) {
                $q->whereColumn('stock', '<=', 'min_stock')
                  ->orWhere('stock', '<=', 10);
            })
            ->orderBy('stock', 'asc');

        $lowStockCount = (clone $lowStockQuery)->count();
        $criticalProducts = (clone $lowStockQuery)->take(5)->get();

        $lowStockItems = $criticalProducts->map(function ($p) {
            $isZero = (int) $p->stock === 0;
            return [
                'id' => $p->id,
                'nama' => $p->name,
                'stok' => (int) $p->stock,
                'min_stock' => (int) $p->min_stock,
                'kategori' => $p->category,
                'statusText' => $isZero ? 'STOK KOSONG' : 'STOK KRITIS',
                'desc' => $isZero ? 'Segera lakukan PO / re-order vendor.' : 'Tersisa ' . (int)$p->stock . ' ' . $p->unit . ' di rak.',
            ];
        });

        // 3. Active Cashiers & Terminals
        $openShifts = CashierShift::where('status', 'OPEN')
            ->orderBy('opened_at', 'desc')
            ->get();

        $cashierList = [];
        if ($openShifts->count() > 0) {
            // One grouped query for all open shifts instead of one query per shift (N+1)
            $salesByShift = Transaction::whereIn('shift_id', $openShifts->pluck('id'))
                ->whereIn('status', ['SUCCESS', 'COMPLETED'])
                ->groupBy('shift_id')
                ->selectRaw('shift_id, SUM(grand_total) as total')
                ->pluck('total', 'shift_id');

            foreach ($openShifts as $shift) {
                $shiftSales = (float) ($salesByShift[$shift->id] ?? 0);

                $name = $shift->cashier_name ?: 'Kasir ' . $shift->terminal_code;
                $initials = collect(explode(' ', $name))
                    ->map(fn($w) => strtoupper(substr($w, 0, 1)))
                    ->take(2)
                    ->join('');

                $cashierList[] = [
                    'id' => $shift->id,
                    'name' => $name,
                    'initial' => $initials ?: 'KS',
                    'terminal' => $shift->terminal_code,
                    'total' => 'Rp ' . number_format($shiftSales ?: (float)$shift->opening_cash, 0, ',', '.'),
                    'status' => 'Aktif',
                    'color' => 'bg-green-500',
                ];
            }
        }

        // 4. Weekly Sales Chart
        $weeklySales = [];
        $dayNames = ['SEN', 'SEL', 'RAB', 'KAM', 'JUM', 'SAB', 'MIN'];
        $maxDailySales = 1;

        // One grouped query for the whole 7-day window instead of 7 separate queries
        $dailyTotals = Transaction::where('created_at', '>=', $today->copy()->subDays(6))
            ->where('created_at', '<', $tomorrow)
            ->whereIn('status', ['SUCCESS', 'COMPLETED'])
            ->groupBy(DB::raw('DATE(created_at)'))
            ->selectRaw('DATE(created_at) as sale_date, SUM(grand_total) as total')
            ->pluck('total', 'sale_date');

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

        // Normalize bar height
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
                'cashier_list' => $cashierList,
                'weekly_sales' => $weeklySales,
            ],
        ]);
    }
}
