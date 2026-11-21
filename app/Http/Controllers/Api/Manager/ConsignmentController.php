<?php

namespace App\Http\Controllers\Api\Manager;

use App\Events\StockUpdatedEvent;
use App\Http\Controllers\Api\Shared\NotificationController;
use App\Http\Controllers\Controller;
use App\Models\ConsignmentSettlement;
use App\Models\GoodsReceipt;
use App\Models\GoodsReceiptItem;
use App\Models\Location;
use App\Models\Product;
use App\Models\ProductStock;
use App\Models\Supplier;
use App\Models\SupplierProduct;
use App\Models\TransactionItem;
use App\Services\LocationStockService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ConsignmentController extends Controller
{
    public function __construct(
        protected LocationStockService $stockService
    ) {}
    /**
     * List Mitra UMKM Lokal (Consignment Partners).
     */
    public function indexPartners(Request $request): JsonResponse
    {
        $search = trim($request->query('search', ''));
        $status = trim($request->query('status', ''));

        $query = Supplier::consignment()->withCount('products');

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
            'total_partners' => Supplier::consignment()->count(),
            'active_partners' => Supplier::consignment()->where('status', 'AKTIF')->count(),
            'total_consignment_skus' => Product::where('is_consignment', true)->count(),
            'average_commission' => round((float) Supplier::consignment()->avg('commission_percent'), 1),
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

        $cleanName = trim((string) preg_replace('/\s+/', ' ', $validated['name']));
        $lockKey = 'supplier_create:' . hash('sha256', mb_strtolower($cleanName));

        $result = Cache::lock($lockKey, 10)->block(5, function () use ($validated, $cleanName) {
            return DB::transaction(function () use ($validated, $cleanName) {
                $recentDuplicate = Supplier::whereRaw('LOWER(TRIM(name)) = ?', [mb_strtolower($cleanName)])
                    ->where('created_at', '>=', now()->subSeconds(10))
                    ->lockForUpdate()
                    ->first();

                if ($recentDuplicate) {
                    return ['duplicate' => true, 'partner' => $recentDuplicate];
                }

                $nextNum = ((int) Supplier::max('id')) + 1;
                $code = 'UMKM-' . Str::padLeft((string) $nextNum, 3, '0');
                while (Supplier::where('code', $code)->exists()) {
                    $nextNum++;
                    $code = 'UMKM-' . Str::padLeft((string) $nextNum, 3, '0');
                }

                $partner = Supplier::create(array_merge($validated, [
                    'name' => $cleanName,
                    'pic_name' => $validated['owner_name'] ?? null,
                    'code' => $code,
                    'status' => 'AKTIF',
                    'supplier_type' => Supplier::TYPE_CONSIGNMENT,
                ]));

                return ['duplicate' => false, 'partner' => $partner];
            });
        });

        if ($result['duplicate']) {
            $existing = $result['partner'];
            return response()->json([
                'success' => false,
                'message' => "Mitra / Supplier dengan nama [{$existing->name}] sudah terdaftar ({$existing->code}).",
                'errors' => [
                    'name' => ["Mitra / Supplier dengan nama [{$existing->name}] sudah terdaftar ({$existing->code})."],
                ],
                'data' => $existing,
            ], 422);
        }

        $partner = $result['partner'];

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

    /**
     * List Produk Mitra UMKM (Consignment Products) with multi-branch stock breakdown.
     */
    public function indexProducts(Request $request): JsonResponse
    {
        $this->stockService->ensureDefaultLocations();

        $search = trim((string) $request->query('search', ''));
        $supplierId = $request->query('supplier_id');
        $category = trim((string) $request->query('category', ''));

        $query = Product::with(['supplier', 'units', 'productStocks.location'])
            ->where(function ($q) {
                $q->where('is_consignment', true)
                  ->orWhereHas('supplier', fn ($sq) => $sq->where('supplier_type', Supplier::TYPE_CONSIGNMENT));
            });

        if ($supplierId && $supplierId !== 'all') {
            $query->where('supplier_id', (int) $supplierId);
        }

        if ($category !== '' && $category !== 'all' && $category !== 'Semua') {
            $query->where('category', $category);
        }

        if ($search !== '') {
            $lower = strtolower($search);
            $query->where(function ($sub) use ($search, $lower) {
                $sub->where('sku', 'like', "%{$search}%")
                    ->orWhere('barcode', 'like', "%{$search}%")
                    ->orWhereRaw('LOWER(name) LIKE ?', ["%{$lower}%"])
                    ->orWhereHas('supplier', fn ($sq) => $sq->whereRaw('LOWER(name) LIKE ?', ["%{$lower}%"]));
            });
        }

        $products = $query->orderByDesc('id')->get();
        $warehouse = $this->stockService->getWarehouseLocation();
        $stores = $this->stockService->getStoreLocations();

        $formatted = $products->map(fn (Product $p) => $this->formatConsignmentProduct($p, $warehouse, $stores))->values();

        $totalWarehouseStock = $formatted->sum('warehouse_stock');
        $totalBranchStock = $formatted->sum('branch_stock_total');
        $totalCostValue = $formatted->sum(fn ($it) => $it['stock'] * $it['cost_price']);
        $totalRetailValue = $formatted->sum(fn ($it) => $it['stock'] * $it['price']);

        return response()->json([
            'success' => true,
            'summary' => [
                'total_products' => $formatted->count(),
                'total_warehouse_stock' => (int) $totalWarehouseStock,
                'total_branch_stock' => (int) $totalBranchStock,
                'total_stock' => (int) ($totalWarehouseStock + $totalBranchStock),
                'total_cost_value' => round((float) $totalCostValue, 2),
                'total_retail_value' => round((float) $totalRetailValue, 2),
            ],
            'warehouse' => $warehouse ? [
                'id' => $warehouse->id,
                'code' => $warehouse->code,
                'name' => $warehouse->name,
            ] : null,
            'stores' => $stores->map(fn (Location $loc) => [
                'id' => $loc->id,
                'code' => $loc->code,
                'name' => $loc->name,
            ])->values(),
            'partners' => Supplier::consignment()->orderBy('name')->get([
                'id', 'code', 'name', 'owner_name', 'category', 'commission_percent', 'status'
            ]),
            'data' => $formatted,
        ]);
    }

    /**
     * Store a new Produk Mitra UMKM (Beli & Jual) and optionally allocate stock to Gudang Pusat or Cabang.
     */
    public function storeProduct(Request $request): JsonResponse
    {
        $this->stockService->ensureDefaultLocations();

        $validated = $request->validate([
            'supplier_id' => 'required|exists:suppliers,id',
            'name' => 'required|string|max:150',
            'sku' => 'nullable|string|max:50|unique:products,sku',
            'barcode' => 'nullable|string|max:50|unique:products,barcode',
            'category' => 'nullable|string|max:50',
            'unit' => 'nullable|string|max:20',
            'cost_price' => 'required|numeric|min:0',
            'price' => 'required|numeric|min:0',
            'member_price' => 'nullable|numeric|min:0',
            'commission_percent' => 'nullable|numeric|min:0|max:100',
            'stock' => 'required|integer|min:0',
            'min_stock' => 'nullable|integer|min:0',
            'expired_date' => 'nullable|date',
            'rack_location' => 'nullable|string|max:100',
            'target_location' => 'nullable|string|max:50', // GUDANG_PUSAT | CAB-001 | CAB-002 | CAB-003 | SEMUA_CABANG
        ]);

        return DB::transaction(function () use ($validated) {
            $partner = Supplier::findOrFail($validated['supplier_id']);

            // Generate unique SKU if empty
            $sku = trim((string) ($validated['sku'] ?? ''));
            if ($sku === '') {
                $nextNum = ((int) Product::max('id')) + 1;
                $sku = 'UMKM-' . str_pad((string) $nextNum, 3, '0', STR_PAD_LEFT);
                while (Product::where('sku', $sku)->exists()) {
                    $nextNum++;
                    $sku = 'UMKM-' . str_pad((string) $nextNum, 3, '0', STR_PAD_LEFT);
                }
            }

            // Generate unique Barcode if empty
            $barcode = trim((string) ($validated['barcode'] ?? ''));
            if ($barcode === '') {
                do {
                    $barcode = '899' . random_int(1000000000, 9999999999);
                } while (Product::where('barcode', $barcode)->exists());
            }

            $costPrice = (float) $validated['cost_price'];
            $price = (float) $validated['price'];
            $memberPrice = isset($validated['member_price']) && $validated['member_price'] !== '' && (float) $validated['member_price'] > 0
                ? (float) $validated['member_price']
                : $price;

            if (isset($validated['commission_percent']) && $validated['commission_percent'] !== '') {
                $commissionPercent = (float) $validated['commission_percent'];
            } elseif ($costPrice > 0 && $price >= $costPrice) {
                $commissionPercent = round((($price - $costPrice) / $costPrice) * 100, 2);
            } else {
                $commissionPercent = (float) ($partner->commission_percent ?? 15.0);
            }

            $initialStock = (int) $validated['stock'];
            $minStock = (int) ($validated['min_stock'] ?? 5);
            $category = !empty($validated['category']) ? trim($validated['category']) : ($partner->category ?: 'Produk UMKM');
            $unit = !empty($validated['unit']) ? trim($validated['unit']) : 'Pcs';

            $product = Product::create([
                'sku' => strtoupper($sku),
                'barcode' => $barcode,
                'name' => trim($validated['name']),
                'brand' => $partner->name,
                'category' => $category,
                'unit' => $unit,
                'cost_price' => $costPrice,
                'last_cost_price' => $costPrice,
                'price' => $price,
                'member_price' => $memberPrice,
                'harga_level1' => $memberPrice,
                'stock' => $initialStock,
                'min_stock' => $minStock,
                'reorder_point' => $minStock * 2,
                'is_taxable' => true,
                'tax_rate' => 11.0,
                'expired_date' => $validated['expired_date'] ?? null,
                'clearance_discount_percent' => 20.0,
                'rack_location' => $validated['rack_location'] ?? 'Rak Mitra UMKM',
                'is_consignment' => true,
                'supplier_id' => $partner->id,
                'ko_sup' => $partner->code,
                'commission_percent' => $commissionPercent,
                'status' => $initialStock <= 0 ? 'HABIS' : ($initialStock <= $minStock ? 'STOK_RENDAH' : 'TERSEDIA'),
            ]);

            // Sync relation in supplier_products
            SupplierProduct::updateOrCreate(
                [
                    'supplier_id' => $partner->id,
                    'product_id' => $product->id,
                ],
                [
                    'first_purchase_at' => now(),
                    'last_purchase_at' => now(),
                    'last_purchase_price' => $costPrice,
                    'total_transactions' => 1,
                    'supply_status' => 'AKTIF',
                ]
            );

            // Allocate initial stock
            $targetLocKey = strtoupper(trim((string) ($validated['target_location'] ?? 'GUDANG_PUSAT')));
            $warehouse = $this->stockService->getWarehouseLocation();
            $stores = $this->stockService->getStoreLocations();

            if ($initialStock > 0) {
                if ($targetLocKey === 'SEMUA_CABANG' && $stores->isNotEmpty()) {
                    foreach ($stores as $storeLoc) {
                        $this->stockService->addStock($product->id, $storeLoc->id, $initialStock, [
                            'movement_type' => 'PURCHASE_IN',
                            'supplier_id' => $partner->id,
                            'ownership_type' => 'CONSIGNMENT',
                            'unit_cost' => $costPrice,
                            'expired_date' => $validated['expired_date'] ?? null,
                            'user_id' => Auth::id(),
                            'notes' => "Alokasi stok awal produk UMKM [{$partner->name}] ke {$storeLoc->name}",
                        ]);
                    }
                } elseif ($targetLocKey !== 'GUDANG_PUSAT' && $targetLocKey !== 'GDG-001') {
                    $resolvedLoc = $this->stockService->resolveLocation(null, $targetLocKey, null, null, 'STORE') ?? $warehouse;
                    if ($resolvedLoc) {
                        $this->stockService->addStock($product->id, $resolvedLoc->id, $initialStock, [
                            'movement_type' => 'PURCHASE_IN',
                            'supplier_id' => $partner->id,
                            'ownership_type' => 'CONSIGNMENT',
                            'unit_cost' => $costPrice,
                            'expired_date' => $validated['expired_date'] ?? null,
                            'user_id' => Auth::id(),
                            'notes' => "Stok awal produk UMKM [{$partner->name}] di {$resolvedLoc->name}",
                        ]);
                    }
                } elseif ($warehouse) {
                    $this->stockService->addStock($product->id, $warehouse->id, $initialStock, [
                        'movement_type' => 'PURCHASE_IN',
                        'supplier_id' => $partner->id,
                        'ownership_type' => 'CONSIGNMENT',
                        'unit_cost' => $costPrice,
                        'expired_date' => $validated['expired_date'] ?? null,
                        'user_id' => Auth::id(),
                        'notes' => "Stok awal produk UMKM [{$partner->name}] di Gudang Pusat",
                    ]);
                }
            } elseif ($warehouse) {
                // Ensure warehouse product_stocks record exists even with 0 stock
                ProductStock::firstOrCreate(
                    ['product_id' => $product->id, 'location_id' => $warehouse->id],
                    ['stock' => 0, 'is_active' => true, 'version' => 1]
                );
            }

            Cache::forget('products_kpi_summary_' . Carbon::today()->toDateString());

            $freshProduct = $product->fresh(['supplier', 'units', 'productStocks.location']);

            return response()->json([
                'success' => true,
                'message' => "Produk Mitra UMKM [{$product->name}] dari [{$partner->name}] berhasil ditambahkan.",
                'data' => $this->formatConsignmentProduct($freshProduct, $warehouse, $stores),
            ], 201);
        });
    }

    /**
     * Update Produk Mitra UMKM details.
     */
    public function updateProduct(Request $request, int $id): JsonResponse
    {
        $product = Product::find($id);
        if (!$product) {
            return response()->json(['success' => false, 'message' => 'Produk UMKM tidak ditemukan.'], 404);
        }

        $validated = $request->validate([
            'supplier_id' => 'required|exists:suppliers,id',
            'name' => 'required|string|max:150',
            'sku' => "required|string|max:50|unique:products,sku,{$id}",
            'barcode' => "required|string|max:50|unique:products,barcode,{$id}",
            'category' => 'required|string|max:50',
            'unit' => 'required|string|max:20',
            'cost_price' => 'required|numeric|min:0',
            'price' => 'required|numeric|min:0',
            'member_price' => 'nullable|numeric|min:0',
            'commission_percent' => 'nullable|numeric|min:0|max:100',
            'min_stock' => 'nullable|integer|min:0',
            'expired_date' => 'nullable|date',
            'rack_location' => 'nullable|string|max:100',
        ]);

        return DB::transaction(function () use ($product, $validated) {
            $partner = Supplier::findOrFail($validated['supplier_id']);
            $costPrice = (float) $validated['cost_price'];
            $price = (float) $validated['price'];
            $memberPrice = isset($validated['member_price']) && $validated['member_price'] !== '' && (float) $validated['member_price'] > 0
                ? (float) $validated['member_price']
                : $price;

            if (isset($validated['commission_percent']) && $validated['commission_percent'] !== '') {
                $commissionPercent = (float) $validated['commission_percent'];
            } elseif ($costPrice > 0 && $price >= $costPrice) {
                $commissionPercent = round((($price - $costPrice) / $costPrice) * 100, 2);
            } else {
                $commissionPercent = (float) ($partner->commission_percent ?? 15.0);
            }

            $product->update([
                'supplier_id' => $partner->id,
                'ko_sup' => $partner->code,
                'brand' => $partner->name,
                'name' => trim($validated['name']),
                'sku' => strtoupper(trim($validated['sku'])),
                'barcode' => trim($validated['barcode']),
                'category' => trim($validated['category']),
                'unit' => trim($validated['unit']),
                'cost_price' => $costPrice,
                'last_cost_price' => $costPrice,
                'price' => $price,
                'member_price' => $memberPrice,
                'harga_level1' => $memberPrice,
                'commission_percent' => $commissionPercent,
                'min_stock' => (int) ($validated['min_stock'] ?? $product->min_stock),
                'expired_date' => $validated['expired_date'] ?? null,
                'rack_location' => $validated['rack_location'] ?? $product->rack_location,
                'is_consignment' => true,
            ]);

            SupplierProduct::updateOrCreate(
                [
                    'supplier_id' => $partner->id,
                    'product_id' => $product->id,
                ],
                [
                    'last_purchase_at' => now(),
                    'last_purchase_price' => $costPrice,
                    'supply_status' => 'AKTIF',
                ]
            );

            $warehouse = $this->stockService->getWarehouseLocation();
            $stores = $this->stockService->getStoreLocations();
            $fresh = $product->fresh(['supplier', 'units', 'productStocks.location']);

            return response()->json([
                'success' => true,
                'message' => "Data Produk UMKM [{$product->name}] berhasil diperbarui.",
                'data' => $this->formatConsignmentProduct($fresh, $warehouse, $stores),
            ]);
        });
    }

    /**
     * Restock / Tambah Pembelian Stok Produk dari Mitra UMKM.
     */
    public function restockProduct(Request $request, int $id): JsonResponse
    {
        $product = Product::with('supplier')->find($id);
        if (!$product) {
            return response()->json(['success' => false, 'message' => 'Produk UMKM tidak ditemukan.'], 404);
        }

        $validated = $request->validate([
            'qty' => 'required|integer|min:1',
            'cost_price' => 'nullable|numeric|min:0',
            'price' => 'nullable|numeric|min:0',
            'expired_date' => 'nullable|date',
            'target_location' => 'nullable|string|max:50', // GUDANG_PUSAT | CAB-001 | CAB-002 | CAB-003 | SEMUA_CABANG
            'notes' => 'nullable|string|max:255',
        ]);

        return DB::transaction(function () use ($product, $validated) {
            $qty = (int) $validated['qty'];
            $costPrice = isset($validated['cost_price']) && $validated['cost_price'] !== ''
                ? (float) $validated['cost_price']
                : (float) $product->cost_price;
            $price = isset($validated['price']) && $validated['price'] !== ''
                ? (float) $validated['price']
                : (float) $product->price;

            $product->cost_price = $costPrice;
            $product->last_cost_price = $costPrice;
            $product->price = $price;
            if (!empty($validated['expired_date'])) {
                $product->expired_date = $validated['expired_date'];
            }
            $product->save();

            $targetKey = strtoupper(trim((string) ($validated['target_location'] ?? 'GUDANG_PUSAT')));
            $warehouse = $this->stockService->getWarehouseLocation();
            $stores = $this->stockService->getStoreLocations();
            $targetLabel = 'Gudang Pusat';

            if ($targetKey === 'SEMUA_CABANG' && $stores->isNotEmpty()) {
                foreach ($stores as $storeLoc) {
                    $this->stockService->addStock($product->id, $storeLoc->id, $qty, [
                        'movement_type' => 'PURCHASE_IN',
                        'supplier_id' => $product->supplier_id,
                        'ownership_type' => 'CONSIGNMENT',
                        'unit_cost' => $costPrice,
                        'expired_date' => $validated['expired_date'] ?? ($product->expired_date?->toDateString()),
                        'user_id' => Auth::id(),
                        'notes' => $validated['notes'] ?? "Tambah stok produk UMKM dari mitra ke {$storeLoc->name}",
                    ]);
                }
                $targetLabel = 'Semua Cabang (@ ' . $qty . ' ' . $product->unit . ')';
            } elseif ($targetKey !== 'GUDANG_PUSAT' && $targetKey !== 'GDG-001') {
                $resolvedLoc = $this->stockService->resolveLocation(null, $targetKey, null, null, 'STORE') ?? $warehouse;
                if ($resolvedLoc) {
                    $this->stockService->addStock($product->id, $resolvedLoc->id, $qty, [
                        'movement_type' => 'PURCHASE_IN',
                        'supplier_id' => $product->supplier_id,
                        'ownership_type' => 'CONSIGNMENT',
                        'unit_cost' => $costPrice,
                        'expired_date' => $validated['expired_date'] ?? ($product->expired_date?->toDateString()),
                        'user_id' => Auth::id(),
                        'notes' => $validated['notes'] ?? "Tambah stok produk UMKM dari mitra ke {$resolvedLoc->name}",
                    ]);
                    $targetLabel = $resolvedLoc->name;
                }
            } elseif ($warehouse) {
                $this->stockService->addStock($product->id, $warehouse->id, $qty, [
                    'movement_type' => 'PURCHASE_IN',
                    'supplier_id' => $product->supplier_id,
                    'ownership_type' => 'CONSIGNMENT',
                    'unit_cost' => $costPrice,
                    'expired_date' => $validated['expired_date'] ?? ($product->expired_date?->toDateString()),
                    'user_id' => Auth::id(),
                    'notes' => $validated['notes'] ?? "Tambah stok produk UMKM dari mitra ke Gudang Pusat",
                ]);
                $targetLabel = $warehouse->name;
            }

            if ($product->supplier_id) {
                $sp = SupplierProduct::firstOrNew([
                    'supplier_id' => $product->supplier_id,
                    'product_id' => $product->id,
                ]);
                if (!$sp->exists) {
                    $sp->first_purchase_at = now();
                }
                $sp->last_purchase_at = now();
                $sp->last_purchase_price = $costPrice;
                $sp->total_transactions = ((int) $sp->total_transactions) + 1;
                $sp->supply_status = 'AKTIF';
                $sp->save();
            }

            $fresh = $product->fresh(['supplier', 'units', 'productStocks.location']);

            return response()->json([
                'success' => true,
                'message' => "Stok [{$product->name}] berhasil ditambah +{$qty} {$product->unit} ke {$targetLabel}.",
                'data' => $this->formatConsignmentProduct($fresh, $warehouse, $stores),
            ]);
        });
    }

    /**
     * Delete Produk Mitra UMKM.
     */
    public function destroyProduct(int $id): JsonResponse
    {
        $product = Product::find($id);
        if (!$product) {
            return response()->json(['success' => false, 'message' => 'Produk UMKM tidak ditemukan.'], 404);
        }

        $name = $product->name;

        DB::transaction(function () use ($product, $id) {
            DB::table('transaction_items')->where('product_id', $id)->update(['product_id' => null]);
            DB::table('stock_opname_items')->where('product_id', $id)->update(['product_id' => null]);
            DB::table('sales_return_items')->where('product_id', $id)->update(['product_id' => null]);
            DB::table('coupon_units')->where('product_id', $id)->update(['product_id' => null]);

            $product->units()->delete();
            $product->productStocks()->delete();
            $product->supplierProducts()->delete();
            $product->stockBatches()->delete();
            $product->stockMovements()->delete();
            $product->priceHistories()->delete();

            $product->delete();
        });

        Cache::forget('products_kpi_summary_' . Carbon::today()->toDateString());

        return response()->json([
            'success' => true,
            'message' => "Produk UMKM [{$name}] berhasil dihapus.",
        ]);
    }

    /**
     * Kirim Stok Produk Mitra UMKM ke Cabang (Toko Pusat / Cabang A / Cabang B / Semua Cabang).
     */
    public function sendStockToBranch(Request $request): JsonResponse
    {
        $this->stockService->ensureDefaultLocations();

        $validated = $request->validate([
            'destination_store' => 'required|string|max:100', // CAB-001 | CAB-002 | CAB-003 | Toko Pusat | Cabang A | Cabang B | SEMUA_CABANG
            'source_mode' => 'nullable|in:WAREHOUSE,DIRECT_PARTNER',
            'delivery_mode' => 'nullable|in:DIRECT_ACTIVE,SURAT_JALAN',
            'driver_name' => 'nullable|string|max:100',
            'plate_number' => 'nullable|string|max:50',
            'notes' => 'nullable|string|max:500',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|integer|exists:products,id',
            'items.*.qty_sent' => 'required|integer|min:1',
        ]);

        return DB::transaction(function () use ($validated) {
            $warehouse = $this->stockService->getWarehouseLocation();
            $allStores = $this->stockService->getStoreLocations();
            $destInput = trim($validated['destination_store']);
            $sourceMode = $validated['source_mode'] ?? 'WAREHOUSE';
            $deliveryMode = $validated['delivery_mode'] ?? 'DIRECT_ACTIVE';

            // Determine target store location(s)
            $targetStores = collect();
            if (strtoupper($destInput) === 'SEMUA_CABANG') {
                $targetStores = $allStores;
            } else {
                $resolved = $this->stockService->resolveLocation(null, $destInput, null, null, 'STORE');
                if ($resolved) {
                    $targetStores->push($resolved);
                }
            }

            if ($targetStores->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => "Lokasi cabang tujuan [{$destInput}] tidak ditemukan.",
                ], 422);
            }

            // Pre-validate items & warehouse stock if source_mode === WAREHOUSE
            $storeCount = $targetStores->count();
            $itemsToProcess = [];
            foreach ($validated['items'] as $rawItem) {
                $product = Product::with('supplier')->lockForUpdate()->find($rawItem['product_id']);
                if (!$product) {
                    continue;
                }
                $qtyPerStore = (int) $rawItem['qty_sent'];
                $totalQtyNeeded = $qtyPerStore * $storeCount;

                if ($sourceMode === 'WAREHOUSE' && $warehouse) {
                    $whStockRow = ProductStock::where('product_id', $product->id)
                        ->where('location_id', $warehouse->id)
                        ->first();
                    $whAvailable = $whStockRow ? (int) $whStockRow->stock : 0;

                    // If warehouse has enough stock, we deduct from warehouse.
                    // If warehouse has partial or 0 stock, check if we should return error or top-up from partner:
                    if ($whAvailable < $totalQtyNeeded) {
                        return response()->json([
                            'success' => false,
                            'error_code' => 'WAREHOUSE_STOCK_INSUFFICIENT',
                            'message' => "Stok Gudang Pusat untuk [{$product->name}] hanya tersedia {$whAvailable} {$product->unit} (dibutuhkan {$totalQtyNeeded} {$product->unit}). Pilih sumber 'Pasokan Langsung dari Mitra UMKM' atau tambah stok gudang terlebih dahulu.",
                        ], 422);
                    }
                }

                $itemsToProcess[] = [
                    'product' => $product,
                    'qty' => $qtyPerStore,
                ];
            }

            $createdReceipts = [];
            $isDirectActive = ($deliveryMode === 'DIRECT_ACTIVE');

            foreach ($targetStores as $toLoc) {
                $year = date('Y');
                $nextCount = GoodsReceipt::count() + 1;
                $docNo = 'SJ-UMKM-' . $year . '-' . str_pad((string) $nextCount, 4, '0', STR_PAD_LEFT);
                while (GoodsReceipt::where('receipt_number', $docNo)->exists()) {
                    $nextCount++;
                    $docNo = 'SJ-UMKM-' . $year . '-' . str_pad((string) $nextCount, 4, '0', STR_PAD_LEFT) . '-' . Str::upper(Str::random(2));
                }

                $originLabel = $sourceMode === 'DIRECT_PARTNER'
                    ? 'Pasokan Langsung Mitra UMKM'
                    : ($warehouse?->name ?? 'Gudang Pusat (DC)');

                $receipt = GoodsReceipt::create([
                    'receipt_number' => $docNo,
                    'sent_by' => Auth::id(),
                    'received_by' => $isDirectActive ? Auth::id() : null,
                    'from_location_id' => $sourceMode === 'WAREHOUSE' ? $warehouse?->id : null,
                    'to_location_id' => $toLoc->id,
                    'origin_source' => $originLabel,
                    'destination_store' => $toLoc->name,
                    'driver_name' => !empty($validated['driver_name']) ? trim($validated['driver_name']) : 'Kurir Distribusi UMKM',
                    'plate_number' => !empty($validated['plate_number']) ? strtoupper(trim($validated['plate_number'])) : '-',
                    'status' => $isDirectActive ? 'APPROVED' : 'BELUM_SAMPAI',
                    'received_at' => now(),
                    'verified_at' => $isDirectActive ? now() : null,
                    'notes' => $validated['notes'] ?? "Distribusi stok produk Mitra UMKM ke {$toLoc->name}",
                ]);

                foreach ($itemsToProcess as $row) {
                    /** @var Product $prod */
                    $prod = $row['product'];
                    $qty = (int) $row['qty'];
                    $allocations = [];
                    $itemExpired = $prod->expired_date ? $prod->expired_date->toDateString() : null;

                    if ($sourceMode === 'WAREHOUSE' && $warehouse) {
                        $deductRes = $this->stockService->deductStock($prod->id, $warehouse->id, $qty, [
                            'movement_type' => 'TRANSFER_OUT',
                            'reference_type' => GoodsReceipt::class,
                            'reference_id' => $receipt->id,
                            'reference_number' => $receipt->receipt_number,
                            'user_id' => Auth::id(),
                            'notes' => "Kirim stok produk UMKM {$receipt->receipt_number} ke {$toLoc->name}",
                        ]);
                        $allocations = $deductRes['allocations'] ?? [];
                        if (!empty($allocations[0]['expired_date'])) {
                            $itemExpired = $allocations[0]['expired_date'];
                        }
                    }

                    $grItem = GoodsReceiptItem::create([
                        'goods_receipt_id' => $receipt->id,
                        'product_id' => $prod->id,
                        'qty_sent' => $qty,
                        'qty_received' => $isDirectActive ? $qty : 0,
                        'actual_unit_cost' => (float) $prod->cost_price,
                        'condition' => 'BAIK',
                        'expired_date' => $itemExpired,
                        'batch_number' => $allocations[0]['batch_number'] ?? null,
                        'batch_allocations' => !empty($allocations) ? $allocations : null,
                        'notes' => "Produk Mitra UMKM: " . ($prod->supplier?->name ?? 'UMKM'),
                    ]);

                    // If DIRECT_ACTIVE, immediately credit destination branch ProductStock so cashier can load & transact right away
                    if ($isDirectActive) {
                        $this->stockService->addStock($prod->id, $toLoc->id, $qty, [
                            'movement_type' => 'TRANSFER_IN',
                            'supplier_id' => $prod->supplier_id,
                            'ownership_type' => 'CONSIGNMENT',
                            'goods_receipt_id' => $receipt->id,
                            'goods_receipt_item_id' => $grItem->id,
                            'unit_cost' => (float) $prod->cost_price,
                            'expired_date' => $itemExpired,
                            'reference_type' => GoodsReceipt::class,
                            'reference_id' => $receipt->id,
                            'reference_number' => $receipt->receipt_number,
                            'user_id' => Auth::id(),
                            'notes' => "Stok Produk UMKM masuk ke {$toLoc->name} ({$receipt->receipt_number})",
                        ]);
                    }
                }

                $createdReceipts[] = [
                    'id' => $receipt->id,
                    'receipt_number' => $receipt->receipt_number,
                    'destination_store' => $toLoc->name,
                    'status' => $receipt->status,
                ];

                try {
                    event(new StockUpdatedEvent([
                        'location_id' => $toLoc->id,
                        'type' => 'umkm_stock_transferred',
                        'receipt_number' => $receipt->receipt_number,
                    ]));

                    $totalUnits = collect($itemsToProcess)->sum('qty');
                    NotificationController::notify(
                        role: 'KASIR',
                        title: "Stok Produk UMKM Masuk ({$toLoc->name})",
                        message: "Manager mengirim {$totalUnits} unit produk Mitra UMKM ({$receipt->receipt_number}) ke {$toLoc->name}.",
                        category: 'TRANSFER',
                        link: '/kasir/produk/umkm',
                        icon: 'storefront',
                        color: 'emerald',
                        referenceId: $receipt->receipt_number
                    );
                } catch (\Throwable $e) {}
            }

            $destSummary = $targetStores->pluck('name')->implode(', ');
            $docNumbers = collect($createdReceipts)->pluck('receipt_number')->implode(', ');

            return response()->json([
                'success' => true,
                'message' => $isDirectActive
                    ? "Stok produk UMKM berhasil dikirim dan langsung aktif di {$destSummary} ({$docNumbers}). Siap di-load dan ditransaksikan oleh Kasir!"
                    : "Surat jalan pengiriman produk UMKM ({$docNumbers}) berhasil dikirim ke {$destSummary}.",
                'data' => $createdReceipts,
            ], 201);
        });
    }

    /**
     * Format single UMKM product with warehouse and per-branch stock breakdown.
     */
    protected function formatConsignmentProduct(Product $p, ?Location $warehouse, $stores): array
    {
        $branchStock = [];
        $allocatedBranches = [];
        $stocks = $p->relationLoaded('productStocks') ? $p->productStocks : $p->productStocks()->with('location')->get();
        foreach ($stocks as $ps) {
            if ($ps->location && $ps->is_active !== false) {
                if ($ps->location->type !== 'WAREHOUSE' && (int) $ps->stock <= 0 && $ps->first_received_at === null) {
                    continue;
                }
                $branchStock[$ps->location->code] = (int) $ps->stock;
                if ($ps->location->type !== 'WAREHOUSE') {
                    $allocatedBranches[] = $ps->location->code;
                }
            }
        }

        $whCode = $warehouse?->code ?? 'GDG-001';
        $warehouseStock = (int) ($branchStock[$whCode] ?? (empty($branchStock) ? $p->stock : 0));

        $storeBreakdown = [];
        $branchStockTotal = 0;
        foreach ($stores as $st) {
            $isSentToStore = array_key_exists($st->code, $branchStock);
            $qty = $isSentToStore ? (int) $branchStock[$st->code] : 0;
            $storeBreakdown[$st->code] = [
                'location_id' => $st->id,
                'code' => $st->code,
                'name' => $st->name,
                'stock' => $qty,
                'is_sent' => $isSentToStore,
            ];
            $branchStockTotal += $qty;
        }

        $totalStock = $warehouseStock + $branchStockTotal;
        $commPct = $p->commission_percent > 0 ? (float) $p->commission_percent : (float) ($p->supplier?->commission_percent ?? 15.0);

        return [
            'id' => $p->id,
            'sku' => $p->sku,
            'barcode' => $p->barcode,
            'name' => $p->name,
            'category' => $p->category ?: 'Produk UMKM',
            'unit' => $p->unit ?: 'Pcs',
            'supplier_id' => $p->supplier_id,
            'supplier_name' => $p->supplier?->name ?? 'Mitra UMKM Lokal',
            'supplier_code' => $p->supplier?->code ?? '-',
            'cost_price' => (float) $p->cost_price,
            'price' => (float) $p->price,
            'member_price' => (float) ($p->member_price ?? $p->price),
            'commission_percent' => $commPct,
            'margin_per_unit' => max(0, (float) $p->price - (float) $p->cost_price),
            'stock' => $totalStock,
            'warehouse_stock' => $warehouseStock,
            'branch_stock_total' => $branchStockTotal,
            'branch_stock' => $branchStock,
            'allocated_branches' => array_values(array_unique($allocatedBranches)),
            'store_breakdown' => $storeBreakdown,
            'min_stock' => (int) $p->min_stock,
            'status' => $totalStock <= 0 ? 'HABIS' : ($totalStock <= (int) $p->min_stock ? 'STOK_RENDAH' : 'TERSEDIA'),
            'expired_date' => $p->expired_date ? $p->expired_date->toDateString() : null,
            'rack_location' => $p->rack_location ?: 'Rak Mitra UMKM',
            'is_consignment' => true,
        ];
    }
}
