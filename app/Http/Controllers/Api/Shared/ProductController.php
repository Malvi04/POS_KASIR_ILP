<?php

namespace App\Http\Controllers\Api\Shared;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\Supplier;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ProductController extends Controller
{
    /**
     * List products for Manager Inventory with filters (Stock Alert, Expired Alert, Category, Consignment) & KPI.
     */
    public function index(Request $request): JsonResponse
    {
        $search = trim($request->query('search', ''));
        $category = trim($request->query('category', ''));
        $stockFilter = trim($request->query('stock_filter', '')); // all, STOK_RENDAH, HABIS, TERSEDIA
        $expiredFilter = trim($request->query('expired_filter', '')); // all, WARNING (H-30 s/d H-15), CRITICAL (<= H-14), EXPIRED (< 0)
        $consignmentFilter = trim($request->query('consignment', '')); // all, only_consignment, only_regular
        $supplierId = $request->query('supplier_id');
        $perPage = max(10, min(100, (int) $request->query('per_page', 20)));

        $query = Product::with(['supplier', 'units']);

        if ($search !== '') {
            $query->where(function ($sub) use ($search) {
                $sub->where('barcode', 'like', "%{$search}%")
                    ->orWhere('sku', 'like', "%{$search}%")
                    ->orWhereRaw('LOWER(name) LIKE ?', ['%' . strtolower($search) . '%']);
            });
        }

        if ($category !== '' && $category !== 'all' && $category !== 'Semua') {
            $query->where('category', $category);
        }

        if ($stockFilter === 'STOK_RENDAH') {
            $query->whereColumn('stock', '<=', 'min_stock')->where('stock', '>', 0);
        } elseif ($stockFilter === 'HABIS') {
            $query->where('stock', '<=', 0);
        } elseif ($stockFilter === 'TERSEDIA') {
            $query->whereColumn('stock', '>', 'min_stock');
        }

        $today = Carbon::today();
        if ($expiredFilter === 'CRITICAL') {
            // H-14 s/d H-0 (Merah)
            $query->whereNotNull('expired_date')
                ->whereBetween('expired_date', [$today->toDateString(), $today->copy()->addDays(14)->toDateString()]);
        } elseif ($expiredFilter === 'WARNING') {
            // H-30 s/d H-15 (Kuning)
            $query->whereNotNull('expired_date')
                ->whereBetween('expired_date', [$today->copy()->addDays(15)->toDateString(), $today->copy()->addDays(30)->toDateString()]);
        } elseif ($expiredFilter === 'EXPIRED') {
            // < 0
            $query->whereNotNull('expired_date')->where('expired_date', '<', $today->toDateString());
        }

        if ($consignmentFilter === 'only_consignment') {
            $query->where('is_consignment', true);
        } elseif ($consignmentFilter === 'only_regular') {
            $query->where('is_consignment', false);
        }

        if ($supplierId) {
            $query->where('supplier_id', $supplierId);
        }

        // Summary KPI Metrics (single aggregate query instead of 8 separate table scans)
        $kpi = Product::selectRaw(
            'COUNT(*) as total_products,'
            . ' COALESCE(SUM(CASE WHEN stock <= min_stock AND stock > 0 THEN 1 ELSE 0 END), 0) as low_stock_count,'
            . ' COALESCE(SUM(CASE WHEN stock <= 0 THEN 1 ELSE 0 END), 0) as out_of_stock_count,'
            . ' COALESCE(SUM(CASE WHEN expired_date IS NOT NULL AND expired_date BETWEEN ? AND ? THEN 1 ELSE 0 END), 0) as critical_expired_count,'
            . ' COALESCE(SUM(CASE WHEN expired_date IS NOT NULL AND expired_date BETWEEN ? AND ? THEN 1 ELSE 0 END), 0) as warning_expired_count,'
            . ' COALESCE(SUM(CASE WHEN is_consignment THEN 1 ELSE 0 END), 0) as consignment_count,'
            . ' COALESCE(SUM(stock), 0) as total_stock_units,'
            . ' COALESCE(SUM(stock * cost_price), 0) as total_inventory_value',
            [
                $today->toDateString(),
                $today->copy()->addDays(14)->toDateString(),
                $today->copy()->addDays(15)->toDateString(),
                $today->copy()->addDays(30)->toDateString(),
            ]
        )->first();

        $totalProducts = (int) $kpi->total_products;
        $lowStockCount = (int) $kpi->low_stock_count;
        $outOfStockCount = (int) $kpi->out_of_stock_count;
        $criticalExpiredCount = (int) $kpi->critical_expired_count;
        $warningExpiredCount = (int) $kpi->warning_expired_count;
        $consignmentCount = (int) $kpi->consignment_count;
        $totalStockUnits = (int) $kpi->total_stock_units;
        $totalInventoryValue = (float) $kpi->total_inventory_value;

        $paginated = $query->orderBy('name', 'asc')->paginate($perPage);

        return response()->json([
            'success' => true,
            'summary' => [
                'total_products' => $totalProducts,
                'low_stock_count' => $lowStockCount,
                'out_of_stock_count' => $outOfStockCount,
                'critical_expired_count' => $criticalExpiredCount,
                'warning_expired_count' => $warningExpiredCount,
                'consignment_count' => $consignmentCount,
                'total_stock_units' => $totalStockUnits,
                'total_inventory_value' => $totalInventoryValue,
            ],
            'categories' => array_merge(['Semua'], Product::distinct()->whereNotNull('category')->orderBy('category')->pluck('category')->toArray()),
            'data' => collect($paginated->items())->map(fn($p) => $this->formatProductPayload($p)),
            'meta' => [
                'current_page' => $paginated->currentPage(),
                'last_page' => $paginated->lastPage(),
                'per_page' => $paginated->perPage(),
                'total' => $paginated->total(),
            ],
        ]);
    }

    /**
     * Fast search for POS cashier products by barcode, SKU, or name.
     */
    public function search(Request $request): JsonResponse
    {
        $q = trim($request->query('q', ''));
        $category = $request->query('category');
        $limit = min((int) $request->query('limit', 30), 100);

        $query = Product::with(['supplier', 'units']);

        if ($category && $category !== 'Semua') {
            $query->where('category', $category);
        }

        if ($q !== '') {
            // First check if input looks like an exact barcode
            if (is_numeric($q) && strlen($q) >= 6) {
                $exactBarcode = (clone $query)->where('barcode', $q)->first();
                if ($exactBarcode) {
                    return response()->json([
                        'success' => true,
                        'is_exact_barcode' => true,
                        'count' => 1,
                        'data' => [$this->formatProductPayload($exactBarcode)],
                    ]);
                }
            }

            $query->where(function ($sub) use ($q) {
                $sub->where('barcode', 'like', "%{$q}%")
                    ->orWhere('sku', 'like', "%{$q}%")
                    ->orWhereRaw('LOWER(name) LIKE ?', ['%' . strtolower($search = $q) . '%']);
            });
        }

        $products = $query->orderBy('name', 'asc')
            ->limit($limit)
            ->get();

        return response()->json([
            'success' => true,
            'is_exact_barcode' => false,
            'count' => $products->count(),
            'data' => $products->map(fn($p) => $this->formatProductPayload($p)),
        ]);
    }

    /**
     * Instant lookup by Barcode for barcode scanner.
     */
    public function lookupBarcode(string $barcode): JsonResponse
    {
        $barcode = trim($barcode);
        $product = Product::with(['supplier', 'units'])->where('barcode', $barcode)->first();

        // Also check if barcode matches a product unit (e.g. barcode for 1 Dus)
        if (!$product) {
            $unit = ProductUnit::with('product')->where('barcode', $barcode)->first();
            if ($unit && $unit->product) {
                $product = $unit->product;
            }
        }

        if (!$product) {
            return response()->json([
                'success' => false,
                'message' => "Produk dengan barcode [{$barcode}] tidak ditemukan dalam sistem katalog.",
                'data' => null,
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Produk berhasil dipindai.',
            'data' => $this->formatProductPayload($product),
        ]);
    }

    /**
     * List distinct categories for quick tab filters.
     */
    public function categories(): JsonResponse
    {
        $categories = Product::distinct()
            ->whereNotNull('category')
            ->orderBy('category')
            ->pluck('category');

        return response()->json([
            'success' => true,
            'data' => array_merge(['Semua'], $categories->toArray()),
        ]);
    }

    /**
     * Store new product with multi-satuan & consignment support.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'sku' => 'nullable|string|max:50|unique:products,sku',
            'barcode' => 'required|string|max:50|unique:products,barcode',
            'name' => 'required|string|max:150',
            'category' => 'required|string|max:50',
            'unit' => 'required|string|max:20', // Satuan dasar eceran (Pcs, Botol, Pack)
            'cost_price' => 'required|numeric|min:0',
            'price' => 'required|numeric|min:0',
            'member_price' => 'nullable|numeric|min:0',
            'stock' => 'required|integer|min:0',
            'min_stock' => 'required|integer|min:0',
            'is_taxable' => 'boolean',
            'tax_rate' => 'nullable|numeric|min:0',
            'expired_date' => 'nullable|date',
            'clearance_discount_percent' => 'nullable|numeric|min:0|max:100',
            'rack_location' => 'nullable|string|max:100',
            // Konsinyasi UMKM
            'is_consignment' => 'boolean',
            'supplier_id' => 'nullable|exists:suppliers,id',
            'commission_percent' => 'nullable|numeric|min:0|max:100',
            // Multi-Satuan (Units)
            'units' => 'nullable|array',
            'units.*.unit_name' => 'required|string',
            'units.*.conversion_factor' => 'required|integer|min:2',
            'units.*.price' => 'required|numeric|min:0',
            'units.*.barcode' => 'nullable|string',
        ]);

        return DB::transaction(function () use ($validated) {
            $sku = $validated['sku'] ?? ('PRD-' . Str::padLeft((string) (Product::max('id') + 1), 3, '0'));

            $product = Product::create([
                'sku' => $sku,
                'barcode' => $validated['barcode'],
                'name' => $validated['name'],
                'category' => $validated['category'],
                'unit' => $validated['unit'],
                'cost_price' => (float) $validated['cost_price'],
                'price' => (float) $validated['price'],
                'member_price' => !empty($validated['member_price']) ? (float) $validated['member_price'] : (float) $validated['price'],
                'stock' => (int) $validated['stock'],
                'min_stock' => (int) $validated['min_stock'],
                'is_taxable' => (bool) ($validated['is_taxable'] ?? true),
                'tax_rate' => (float) ($validated['tax_rate'] ?? 11.0),
                'expired_date' => $validated['expired_date'] ?? null,
                'clearance_discount_percent' => (float) ($validated['clearance_discount_percent'] ?? 20.0),
                'rack_location' => $validated['rack_location'] ?? null,
                'is_consignment' => (bool) ($validated['is_consignment'] ?? false),
                'supplier_id' => $validated['supplier_id'] ?? null,
                'commission_percent' => (float) ($validated['commission_percent'] ?? 0.0),
                'status' => (int) $validated['stock'] <= 0 ? 'HABIS' : ((int) $validated['stock'] <= (int) $validated['min_stock'] ? 'STOK_RENDAH' : 'TERSEDIA'),
            ]);

            // Save multi-units if provided
            if (!empty($validated['units'])) {
                foreach ($validated['units'] as $u) {
                    ProductUnit::create([
                        'product_id' => $product->id,
                        'unit_name' => $u['unit_name'],
                        'conversion_factor' => (int) $u['conversion_factor'],
                        'barcode' => $u['barcode'] ?? null,
                        'price' => (float) $u['price'],
                        'member_price' => $u['price'],
                    ]);
                }
            }

            return response()->json([
                'success' => true,
                'message' => "Produk [{$product->name}] berhasil disimpan ke katalog master inventaris.",
                'data' => $this->formatProductPayload($product->fresh(['supplier', 'units'])),
            ], 201);
        });
    }

    /**
     * Update product details.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $product = Product::find($id);
        if (!$product) {
            return response()->json([
                'success' => false,
                'message' => 'Produk tidak ditemukan.',
            ], 404);
        }

        $validated = $request->validate([
            'barcode' => "required|string|max:50|unique:products,barcode,{$id}",
            'name' => 'required|string|max:150',
            'category' => 'required|string|max:50',
            'unit' => 'required|string|max:20',
            'cost_price' => 'required|numeric|min:0',
            'price' => 'required|numeric|min:0',
            'member_price' => 'nullable|numeric|min:0',
            'stock' => 'required|integer|min:0',
            'min_stock' => 'required|integer|min:0',
            'is_taxable' => 'boolean',
            'tax_rate' => 'nullable|numeric|min:0',
            'expired_date' => 'nullable|date',
            'clearance_discount_percent' => 'nullable|numeric|min:0|max:100',
            'rack_location' => 'nullable|string|max:100',
            'is_consignment' => 'boolean',
            'supplier_id' => 'nullable|exists:suppliers,id',
            'commission_percent' => 'nullable|numeric|min:0|max:100',
        ]);

        $validated['status'] = (int) $validated['stock'] <= 0 ? 'HABIS' : ((int) $validated['stock'] <= (int) $validated['min_stock'] ? 'STOK_RENDAH' : 'TERSEDIA');

        $product->update($validated);

        return response()->json([
            'success' => true,
            'message' => "Data produk [{$product->name}] berhasil diperbarui.",
            'data' => $this->formatProductPayload($product->fresh(['supplier', 'units'])),
        ]);
    }

    /**
     * Delete product.
     */
    public function destroy(int $id): JsonResponse
    {
        $product = Product::find($id);
        if (!$product) {
            return response()->json([
                'success' => false,
                'message' => 'Produk tidak ditemukan.',
            ], 404);
        }

        $name = $product->name;
        $product->delete();

        return response()->json([
            'success' => true,
            'message' => "Produk [{$name}] berhasil dihapus dari inventaris.",
        ]);
    }

    /**
     * Add Unit Conversion to Product (e.g. 1 Dus = 24 Pcs).
     */
    public function addUnit(Request $request, int $id): JsonResponse
    {
        $product = Product::find($id);
        if (!$product) {
            return response()->json(['success' => false, 'message' => 'Produk tidak ditemukan.'], 404);
        }

        $validated = $request->validate([
            'unit_name' => 'required|string|max:50',
            'conversion_factor' => 'required|integer|min:2',
            'price' => 'required|numeric|min:0',
            'barcode' => 'nullable|string|max:50',
        ]);

        $unit = ProductUnit::updateOrCreate(
            ['product_id' => $product->id, 'unit_name' => $validated['unit_name']],
            [
                'conversion_factor' => $validated['conversion_factor'],
                'price' => $validated['price'],
                'barcode' => $validated['barcode'] ?? null,
            ]
        );

        return response()->json([
            'success' => true,
            'message' => "Satuan grosir [{$unit->unit_name} = {$unit->conversion_factor} {$product->unit}] berhasil ditambahkan.",
            'data' => $unit,
        ]);
    }

    /**
     * Delete Unit Conversion.
     */
    public function removeUnit(int $productId, int $unitId): JsonResponse
    {
        $unit = ProductUnit::where('product_id', $productId)->where('id', $unitId)->first();
        if (!$unit) {
            return response()->json(['success' => false, 'message' => 'Satuan konversi tidak ditemukan.'], 404);
        }
        $unit->delete();
        return response()->json(['success' => true, 'message' => 'Satuan konversi berhasil dihapus.']);
    }

    /**
     * Formats product response with real-time stock, multi-unit, tax status, alarm expired, and consignment info.
     */
    private function formatProductPayload(Product $p): array
    {
        $alarmLevel = $p->expiration_alert_level;
        $badge = null;
        if ($alarmLevel === 'CRITICAL') {
            $badge = "Kritis (H-{$p->days_until_expiration}) • Auto Diskon {$p->effective_discount_percent}%";
        } elseif ($alarmLevel === 'WARNING') {
            $badge = "Perhatian (H-{$p->days_until_expiration})";
        } elseif ($alarmLevel === 'EXPIRED') {
            $badge = "KEDALUWARSA";
        }

        return [
            'id' => $p->id,
            'sku' => $p->sku,
            'barcode' => $p->barcode,
            'name' => $p->name,
            'category' => $p->category,
            'unit' => $p->unit,
            'rack_location' => $p->rack_location ?? 'Lorong Umum',
            // Cost & Prices
            'cost_price' => (float) $p->cost_price,
            'original_price' => (float) $p->price,
            'original_member_price' => (float) ($p->member_price ?? $p->price),
            // Real-time Stock & Threshold Alert
            'stock' => (int) $p->stock,
            'min_stock' => (int) $p->min_stock,
            'is_in_stock' => $p->stock > 0,
            'is_low_stock' => (bool) $p->is_low_stock,
            'is_out_of_stock' => (bool) $p->is_out_of_stock,
            'stock_status' => $p->stock_alert_level,
            // Expired Date & Two-Tier Alarm System
            'expired_date' => $p->expired_date ? $p->expired_date->toDateString() : null,
            'days_until_expiration' => $p->days_until_expiration,
            'is_near_expired' => (bool) $p->is_critical_expired,
            'is_warning_expired' => (bool) $p->is_warning_expired, // H-30 s/d H-15 (Kuning)
            'is_critical_expired' => (bool) $p->is_critical_expired, // <= H-14 (Merah)
            'is_expired' => (bool) $p->is_expired,
            'expiration_alert_level' => $alarmLevel, // NORMAL, WARNING, CRITICAL, EXPIRED
            'clearance_discount_percent' => (float) $p->effective_discount_percent,
            'clearance_discount_amount' => (float) $p->effective_clearance_discount,
            'clearance_warning_badge' => $badge,
            // Effective selling prices
            'price' => (float) $p->final_price,
            'member_price' => (float) $p->final_member_price,
            // Tax Status
            'is_taxable' => (bool) $p->is_taxable,
            'tax_rate' => (float) $p->tax_rate,
            'tax_label' => $p->is_taxable ? 'PPN ' . (float) $p->tax_rate . '%' : 'Bebas Pajak (Non-PPN)',
            // Konsinyasi UMKM
            'is_consignment' => (bool) $p->is_consignment,
            'supplier_id' => $p->supplier_id,
            'supplier_name' => $p->supplier?->name ?? ($p->is_consignment ? 'Mitra UMKM Lokal' : 'Internal Koperasi'),
            'commission_percent' => (float) $p->commission_percent,
            // Multi-Satuan (Unit Conversions)
            'units' => $p->units->map(fn($u) => [
                'id' => $u->id,
                'unit_name' => $u->unit_name,
                'conversion_factor' => (int) $u->conversion_factor,
                'price' => (float) $u->price,
                'barcode' => $u->barcode,
            ]),
        ];
    }
}
