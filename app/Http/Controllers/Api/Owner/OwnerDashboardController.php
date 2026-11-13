<?php

namespace App\Http\Controllers\Api\Owner;

use App\Http\Controllers\Controller;
use App\Models\Location;
use App\Models\Product;
use App\Models\Transaction;
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
        $cacheKey = 'owner_dashboard_summary_' . $today->toDateString();

        $data = \Illuminate\Support\Facades\Cache::remember($cacheKey, 15, function () use ($today) {
            // 1. Transactions Today
            $todayTrxs = Transaction::whereDate('created_at', $today)
                ->whereIn('status', ['SUCCESS', 'COMPLETED'])
                ->with('items')
                ->get();

            $totalSalesToday = (float) $todayTrxs->sum('grand_total');
            $totalTrxToday = $todayTrxs->count();

            // Estimate COGS in a single batch query (No N+1 loop)
            $neededSkus = [];
            foreach ($todayTrxs as $tx) {
                foreach ($tx->items as $item) {
                    if (empty($item->cost_price) && !empty($item->sku)) {
                        $neededSkus[$item->sku] = true;
                    }
                }
            }
            $skuCosts = empty($neededSkus) ? [] : Product::whereIn('sku', array_keys($neededSkus))->pluck('cost_price', 'sku')->all();

            $totalCogsToday = 0.0;
            foreach ($todayTrxs as $tx) {
                foreach ($tx->items as $item) {
                    $cost = $item->cost_price ?? null;
                    if (!$cost) {
                        $cost = $skuCosts[$item->sku] ?? ((float) $item->unit_price * 0.75);
                    }
                    $totalCogsToday += ((float) $cost * (int) ($item->quantity ?? $item->qty ?? 1));
                }
            }
            $totalProfitToday = max(0, $totalSalesToday - $totalCogsToday);

            // 2. Locations / Branches
            $locations = Location::all();
            if ($locations->isEmpty()) {
                // Seed defaults if empty
                $locations = collect([
                    Location::create(['code' => 'LOC-01', 'name' => 'Cabang 1 - Mill / Pabrik', 'type' => 'STORE', 'address' => 'Kawasan Industri Mill', 'manager' => 'Manager Mill', 'status' => 'AKTIF', 'is_active' => true]),
                    Location::create(['code' => 'LOC-02', 'name' => 'Cabang 2 - Kebun Barat', 'type' => 'STORE', 'address' => 'Afdeling Kebun Barat', 'manager' => 'Manager Barat', 'status' => 'AKTIF', 'is_active' => true]),
                    Location::create(['code' => 'LOC-03', 'name' => 'Cabang 3 - Kebun Timur', 'type' => 'STORE', 'address' => 'Afdeling Kebun Timur', 'manager' => 'Manager Timur', 'status' => 'AKTIF', 'is_active' => true]),
                    Location::create(['code' => 'LOC-DC', 'name' => 'DC Cikarang (Pusat Logistik)', 'type' => 'WAREHOUSE', 'address' => 'Hub Pergudangan Pusat', 'manager' => 'Kepala Gudang DC', 'status' => 'AKTIF', 'is_active' => true]),
                ]);
            }

            $stores = $locations->where('type', '!=', 'WAREHOUSE');
            $warehouse = $locations->firstWhere('type', 'WAREHOUSE') ?? $locations->first();

            // Calculate branch sales
            $branchSalesMap = [];
            $palette = [
                ['dot' => 'bg-[#0052cc]', 'bar' => 'bg-[#0052cc]'],
                ['dot' => 'bg-emerald-500', 'bar' => 'bg-emerald-500'],
                ['dot' => 'bg-amber-500', 'bar' => 'bg-amber-500'],
                ['dot' => 'bg-purple-500', 'bar' => 'bg-purple-500'],
            ];

            $idx = 0;
            foreach ($stores as $store) {
                // Check transactions matching branch/terminal
                $branchTrxs = $todayTrxs->filter(function ($tx) use ($store) {
                    return (isset($tx->terminal_code) && stripos($tx->terminal_code, $store->name) !== false) ||
                           (isset($tx->branch) && stripos($tx->branch, $store->name) !== false) ||
                           (isset($tx->branch_id) && $tx->branch_id == $store->id);
                });

                $rev = (float) $branchTrxs->sum('grand_total');
                $cnt = $branchTrxs->count();
                $color = $palette[$idx % count($palette)];

                $branchSalesMap[] = [
                    'id' => $store->id,
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
                $idx++;
            }

            // 3. Critical Products (Low Stock) - Fast targeted query with index
            $criticalQuery = Product::where(function ($q) {
                $q->whereColumn('stock', '<=', 'min_stock')
                  ->orWhere('stock', '<=', 10);
            })->orderBy('stock', 'asc');

            $lowStockCount = (clone $criticalQuery)->count();
            $totalProductsCount = Product::count();
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

            return [
                'total_sales_today' => $totalSalesToday,
                'total_trx_today' => $totalTrxToday,
                'total_profit_today' => $totalProfitToday,
                'branches' => $branchSalesMap,
                'warehouse' => [
                    'id' => $warehouse ? $warehouse->id : 4,
                    'code' => $warehouse ? $warehouse->code : 'LOC-DC',
                    'name' => $warehouse ? $warehouse->name : 'DC Cikarang (Pusat Logistik)',
                    'type' => 'Gudang Logistik',
                    'manager' => $warehouse ? ($warehouse->manager ?? 'Kepala Gudang DC') : 'Kepala Gudang DC',
                    'status' => 'OPERASIONAL DC',
                    'statusColor' => 'bg-blue-50 text-blue-700 border-blue-200',
                ],
                'critical_items' => $criticalItems,
                'low_stock_count' => $lowStockCount,
                'total_products' => $totalProductsCount,
                'total_branches' => $stores->count(),
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $data,
        ])->header('Cache-Control', 'private, max-age=5, stale-while-revalidate=15');
    }
}
