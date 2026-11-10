<?php

namespace App\Http\Controllers\Api\Manager;

use App\Http\Controllers\Controller;
use App\Models\ConsignmentSettlement;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\TransactionItem;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ConsignmentController extends Controller
{
    /**
     * List Mitra UMKM Lokal (Consignment Partners).
     */
    public function indexPartners(Request $request): JsonResponse
    {
        $search = trim($request->query('search', ''));
        $status = trim($request->query('status', ''));

        $query = Supplier::withCount('products');

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('code', 'like', "%{$search}%")
                  ->orWhere('name', 'like', "%{$search}%")
                  ->orWhere('owner_name', 'like', "%{$search}%");
            });
        }

        if ($status !== '' && $status !== 'all') {
            $query->where('status', strtoupper($status));
        }

        $partners = $query->orderBy('name', 'asc')->get();

        // Summary metrics
        $summary = [
            'total_partners' => Supplier::count(),
            'active_partners' => Supplier::where('status', 'AKTIF')->count(),
            'total_consignment_skus' => Product::where('is_consignment', true)->count(),
            'average_commission' => round((float) Supplier::avg('commission_percent'), 1),
        ];

        return response()->json([
            'success' => true,
            'summary' => $summary,
            'data' => $partners,
        ]);
    }

    /**
     * Create new Mitra UMKM Konsinyasi.
     */
    public function storePartner(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:150',
            'owner_name' => 'nullable|string|max:100',
            'phone' => 'nullable|string|max:30',
            'address' => 'nullable|string',
            'category' => 'nullable|string|max:50',
            'commission_percent' => 'required|numeric|min:0|max:100',
            'settlement_period' => 'required|in:MINGGUAN,BULANAN',
            'bank_name' => 'nullable|string|max:50',
            'bank_account' => 'nullable|string|max:50',
            'notes' => 'nullable|string',
        ]);

        $code = 'UMKM-' . Str::padLeft((string) (Supplier::max('id') + 1), 3, '0');

        $partner = Supplier::create(array_merge($validated, [
            'code' => $code,
            'status' => 'AKTIF',
        ]));

        return response()->json([
            'success' => true,
            'message' => "Mitra UMKM [{$partner->name}] berhasil didaftarkan.",
            'data' => $partner,
        ], 201);
    }

    /**
     * Update Mitra UMKM.
     */
    public function updatePartner(Request $request, int $id): JsonResponse
    {
        $partner = Supplier::find($id);
        if (!$partner) {
            return response()->json(['success' => false, 'message' => 'Mitra UMKM tidak ditemukan.'], 404);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:150',
            'owner_name' => 'nullable|string|max:100',
            'phone' => 'nullable|string|max:30',
            'address' => 'nullable|string',
            'category' => 'nullable|string|max:50',
            'commission_percent' => 'required|numeric|min:0|max:100',
            'settlement_period' => 'required|in:MINGGUAN,BULANAN',
            'bank_name' => 'nullable|string|max:50',
            'bank_account' => 'nullable|string|max:50',
            'status' => 'required|in:AKTIF,NONAKTIF',
            'notes' => 'nullable|string',
        ]);

        $partner->update($validated);

        return response()->json([
            'success' => true,
            'message' => "Data Mitra [{$partner->name}] berhasil diperbarui.",
            'data' => $partner,
        ]);
    }

    /**
     * Delete Mitra UMKM.
     */
    public function destroyPartner(int $id): JsonResponse
    {
        $partner = Supplier::find($id);
        if (!$partner) {
            return response()->json(['success' => false, 'message' => 'Mitra UMKM tidak ditemukan.'], 404);
        }

        $partner->delete();
        return response()->json(['success' => true, 'message' => 'Mitra UMKM berhasil dihapus.']);
    }

    /**
     * Laporan Konsinyasi: Pemisahan Otomatis Omzet Penjualan UMKM dengan Komisi Koperasi.
     */
    public function report(Request $request): JsonResponse
    {
        $supplierId = $request->query('supplier_id');
        $month = $request->query('month', Carbon::now()->format('Y-m'));

        try {
            $startDate = Carbon::createFromFormat('Y-m', $month)->startOfMonth();
            $endDate = Carbon::createFromFormat('Y-m', $month)->endOfMonth();
        } catch (\Exception $e) {
            $startDate = Carbon::now()->startOfMonth();
            $endDate = Carbon::now()->endOfMonth();
        }

        // Retrieve consignment products
        $prodQuery = Product::with('supplier')->where('is_consignment', true);
        if ($supplierId && $supplierId !== 'all') {
            $prodQuery->where('supplier_id', $supplierId);
        }
        $products = $prodQuery->get();

        $rows = [];
        $totalGross = 0;
        $totalCoopCommission = 0;
        $totalUmkmShare = 0;
        $totalSoldUnits = 0;

        foreach ($products as $p) {
            // Find sales items for this product
            $soldItems = TransactionItem::where('product_id', $p->id)
                ->whereBetween('created_at', [$startDate, $endDate])
                ->get();

            $qtySold = $soldItems->sum('qty');
            $grossSales = (float) $soldItems->sum('total_price');

            // Commission percent from product or supplier
            $commPct = $p->commission_percent > 0 ? $p->commission_percent : ($p->supplier?->commission_percent ?? 15.0);
            $coopCommission = round($grossSales * ($commPct / 100), 2);
            $umkmNetShare = max(0, $grossSales - $coopCommission);

            $rows[] = [
                'product_id' => $p->id,
                'sku' => $p->sku,
                'name' => $p->name,
                'supplier_id' => $p->supplier_id,
                'supplier_name' => $p->supplier?->name ?? 'UMKM Titipan',
                'stock' => $p->stock,
                'price' => $p->price,
                'commission_percent' => $commPct,
                'units_sold' => $qtySold,
                'gross_sales' => $grossSales,
                'coop_commission' => $coopCommission,
                'umkm_net_share' => $umkmNetShare,
            ];

            $totalGross += $grossSales;
            $totalCoopCommission += $coopCommission;
            $totalUmkmShare += $umkmNetShare;
            $totalSoldUnits += $qtySold;
        }

        return response()->json([
            'success' => true,
            'period' => [
                'month' => $month,
                'label' => $startDate->translatedFormat('F Y'),
            ],
            'summary' => [
                'total_gross_sales' => $totalGross,
                'total_coop_commission' => $totalCoopCommission,
                'total_umkm_net_share' => $totalUmkmShare,
                'total_sold_units' => $totalSoldUnits,
                'active_consignment_skus' => count($rows),
            ],
            'data' => $rows,
            'suppliers' => Supplier::orderBy('name')->get(['id', 'code', 'name', 'commission_percent']),
        ]);
    }

    /**
     * Create Consignment Settlement for Supplier.
     */
    public function createSettlement(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'supplier_id' => 'required|exists:suppliers,id',
            'period_month' => 'required|string|regex:/^\d{4}-\d{2}$/',
            'notes' => 'nullable|string',
        ]);

        $supplier = Supplier::findOrFail($validated['supplier_id']);
        $startDate = Carbon::createFromFormat('Y-m', $validated['period_month'])->startOfMonth();
        $endDate = Carbon::createFromFormat('Y-m', $validated['period_month'])->endOfMonth();

        $productIds = Product::where('supplier_id', $supplier->id)->pluck('id');
        $items = TransactionItem::whereIn('product_id', $productIds)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->get();

        $gross = (float) $items->sum('total_price');
        $commPct = (float) $supplier->commission_percent;
        $comm = round($gross * ($commPct / 100), 2);
        $net = max(0, $gross - $comm);
        $count = $items->sum('qty');

        $settlement = ConsignmentSettlement::create([
            'settlement_number' => 'SETTLE-' . date('Ym') . '-' . Str::upper(Str::random(5)),
            'supplier_id' => $supplier->id,
            'period_start' => $startDate->toDateString(),
            'period_end' => $endDate->toDateString(),
            'gross_sales' => $gross,
            'coop_commission' => $comm,
            'umkm_net_share' => $net,
            'items_sold_count' => $count,
            'payment_status' => 'PAID',
            'paid_at' => now(),
            'notes' => $validated['notes'] ?? "Settlement omzet konsinyasi {$supplier->name} ({$validated['period_month']})",
        ]);

        return response()->json([
            'success' => true,
            'message' => "Bagi hasil konsinyasi senilai Rp " . number_format($net, 0, ',', '.') . " untuk [{$supplier->name}] berhasil dibukukan.",
            'data' => $settlement,
        ]);
    }
}
