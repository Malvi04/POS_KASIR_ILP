<?php

namespace App\Http\Controllers\Api\Shared;

use App\Http\Controllers\Controller;
use App\Models\Location;
use App\Models\Product;
use App\Models\ProductStock;
use App\Models\ProductUnit;
use App\Services\LocationStockService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ProductController extends Controller
{
    public function __construct(
        protected LocationStockService $stockService
    ) {}

    /**
     * List products for Manager/Owner Stock Master with location stock breakdown & KPI.
     */
    public function index(Request $request): JsonResponse
    {
        $search = trim($request->query('search', ''));
        $category = trim($request->query('category', ''));
        $stockFilter = trim($request->query('stock_filter', ''));
        $expiredFilter = trim($request->query('expired_filter', ''));
        $consignmentFilter = trim($request->query('consignment', ''));
        $supplierId = $request->query('supplier_id');
        $perPage = max(10, min(100, (int) $request->query('per_page', 20)));

        // Optional location filter (e.g. GDG-001 for Kirim Barang, or branch for Kasir Inventory)
        $locationCode = $request->query('location_code');
        $locationIdParam = $request->integer('location_id') ?: null;
        $branchParam = $request->query('branch');
        $onlyRegisteredInLoc = $request->boolean('only_registered', false);
        $authUser = Auth::user();

        $targetLocation = null;
        if ($locationIdParam || $locationCode || $branchParam) {
            $targetLocation = $this->stockService->resolveLocation(
                $locationIdParam,
                $locationCode ?: $branchParam
            );
        }

        // Security guard: Kasir must NOT access full Stock Master or another store/warehouse inventory
        if ($authUser && $authUser->isKasir()) {
            if (!$onlyRegisteredInLoc) {
                return response()->json([
                    'success' => false,
                    'message' => 'Akses Stock Master ditolak untuk Kasir. Gunakan katalog Stock Toko lokasi Anda.',
                ], 403);
            }

            if ($targetLocation) {
                if ($targetLocation->type === 'WAREHOUSE' || !$this->stockService->isUserAllowedInLocation($authUser, $targetLocation)) {
                    return response()->json([
                        'success' => false,
                        'message' => "Akun Kasir tidak memiliki akses ke inventaris lokasi {$targetLocation->name}.",
                    ], 403);
                }
            } else {
                $targetLocation = $this->stockService->resolveLocation(null, $authUser->branch, null, $authUser, 'STORE');
            }
        }

        $query = Product::with(['supplier', 'units', 'supplierProducts.supplier', 'productStocks.location']);

        if ($targetLocation && $onlyRegisteredInLoc) {
            $query->whereHas('productStocks', function ($q) use ($targetLocation) {
                $q->where('location_id', $targetLocation->id)->where('is_active', true);
            });
        }

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
            $query->whereNotNull('expired_date')
                ->whereBetween('expired_date', [$today->toDateString(), $today->copy()->addDays(14)->toDateString()]);
        } elseif ($expiredFilter === 'WARNING') {
            $query->whereNotNull('expired_date')
                ->whereBetween('expired_date', [$today->copy()->addDays(15)->toDateString(), $today->copy()->addDays(30)->toDateString()]);
        } elseif ($expiredFilter === 'EXPIRED') {
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

        $kpi = \Illuminate\Support\Facades\Cache::remember('products_kpi_summary_' . $today->toDateString(), 60, function () use ($today) {
            $raw = Product::selectRaw(
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

            return $raw ? [
                'total_products' => (int) $raw->total_products,
                'low_stock_count' => (int) $raw->low_stock_count,
                'out_of_stock_count' => (int) $raw->out_of_stock_count,
                'critical_expired_count' => (int) $raw->critical_expired_count,
                'warning_expired_count' => (int) $raw->warning_expired_count,
                'consignment_count' => (int) $raw->consignment_count,
                'total_stock_units' => (int) $raw->total_stock_units,
                'total_inventory_value' => (float) $raw->total_inventory_value,
            ] : [];
        });

        $paginated = $query->orderBy('name', 'asc')->paginate($perPage);

        $categories = \Illuminate\Support\Facades\Cache::remember('products_categories_distinct', 300, function () {
            return array_merge(['Semua'], Product::distinct()->whereNotNull('category')->orderBy('category')->pluck('category')->toArray());
        });

        $useLocStock = $targetLocation !== null && ProductStock::where('location_id', $targetLocation->id)->exists();

        return response()->json([
            'success' => true,
            'location' => $targetLocation ? [
                'id' => $targetLocation->id,
                'code' => $targetLocation->code,
                'name' => $targetLocation->name,
                'type' => $targetLocation->type,
            ] : null,
            'summary' => [
                'total_products' => (int) ($kpi['total_products'] ?? 0),
                'low_stock_count' => (int) ($kpi['low_stock_count'] ?? 0),
                'out_of_stock_count' => (int) ($kpi['out_of_stock_count'] ?? 0),
                'critical_expired_count' => (int) ($kpi['critical_expired_count'] ?? 0),
                'warning_expired_count' => (int) ($kpi['warning_expired_count'] ?? 0),
                'consignment_count' => (int) ($kpi['consignment_count'] ?? 0),
                'total_stock_units' => (int) ($kpi['total_stock_units'] ?? 0),
                'total_inventory_value' => (float) ($kpi['total_inventory_value'] ?? 0.0),
            ],
            'categories' => $categories,
            'data' => collect($paginated->items())->map(fn ($p) => $this->formatProductPayload(
                $p,
                $useLocStock ? $targetLocation->id : null,
                $targetLocation
            )),
            'meta' => [
                'current_page' => $paginated->currentPage(),
                'last_page' => $paginated->lastPage(),
                'per_page' => $paginated->perPage(),
                'total' => $paginated->total(),
            ],
        ]);
    }

    /**
     * Fast search for POS cashier products (Stock Toko scoped by active store location).
     * Initial / default display is strictly capped at max 100 items.
     */
    public function search(Request $request): JsonResponse
    {
        $q = trim($request->query('q', ''));
        $category = $request->query('category');
        $limit = min(max(1, (int) $request->query('limit', $request->query('per_page', 100))), 100);

        $locContext = $this->resolvePosStoreContext($request);
        if ($locContext['error']) {
            return $locContext['error'];
        }

        /** @var Location|null $location */
        $location = $locContext['location'];
        $strictStoreMode = $locContext['strict'];

        if ($strictStoreMode && !$location) {
            return response()->json([
                'success' => false,
                'message' => 'Lokasi toko aktif kasir tidak ditemukan.',
                'data' => [],
            ], 422);
        }

        $query = Product::with(['supplier', 'units']);

        if ($location && $strictStoreMode) {
            $query->with(['productStocks' => fn ($sq) => $sq->where('location_id', $location->id)])
                ->whereHas('productStocks', function ($sq) use ($location) {
                    $sq->where('location_id', $location->id)->where('is_active', true);
                });
        }

        if ($category && $category !== 'Semua' && $category !== 'all') {
            $query->where('category', $category);
        }

        if ($q === '') {
            $paginated = $query->orderBy('name', 'asc')->paginate($limit);
            $items = collect($paginated->items())->map(
                fn ($p) => $this->formatProductPayload($p, $strictStoreMode ? $location?->id : null, $location)
            )->values()->all();

            return response()->json([
                'success' => true,
                'catalog_mode' => $strictStoreMode ? 'STOCK_TOKO' : 'LEGACY',
                'location' => $location ? [
                    'id' => $location->id,
                    'code' => $location->code,
                    'name' => $location->name,
                ] : null,
                'is_exact_barcode' => false,
                'count' => count($items),
                'data' => $items,
                'meta' => [
                    'current_page' => $paginated->currentPage(),
                    'last_page' => $paginated->lastPage(),
                    'per_page' => $paginated->perPage(),
                    'total' => $paginated->total(),
                ],
            ]);
        }

        // Exact barcode match first
        if (is_numeric($q) && strlen($q) >= 6) {
            $exactBarcode = (clone $query)->where('barcode', $q)->first();
            if ($exactBarcode) {
                return response()->json([
                    'success' => true,
                    'catalog_mode' => $strictStoreMode ? 'STOCK_TOKO' : 'LEGACY',
                    'location' => $location ? [
                        'id' => $location->id,
                        'code' => $location->code,
                        'name' => $location->name,
                    ] : null,
                    'is_exact_barcode' => true,
                    'count' => 1,
                    'data' => [$this->formatProductPayload($exactBarcode, $strictStoreMode ? $location?->id : null, $location)],
                ]);
            }
        }

        $lowerQ = strtolower($q);
        $query->where(function ($sub) use ($q, $lowerQ) {
            $sub->where('barcode', 'like', "{$q}%")
                ->orWhere('sku', 'like', "{$q}%")
                ->orWhereRaw('LOWER(name) LIKE ?', ["{$lowerQ}%"])
                ->orWhere('barcode', 'like', "%{$q}%")
                ->orWhere('sku', 'like', "%{$q}%")
                ->orWhereRaw('LOWER(name) LIKE ?', ["%{$lowerQ}%"]);
        });

        $paginated = $query->orderBy('name', 'asc')->paginate($limit);
        $items = collect($paginated->items())->map(
            fn ($p) => $this->formatProductPayload($p, $strictStoreMode ? $location?->id : null, $location)
        )->values()->all();

        return response()->json([
            'success' => true,
            'catalog_mode' => $strictStoreMode ? 'STOCK_TOKO' : 'LEGACY',
            'location' => $location ? [
                'id' => $location->id,
                'code' => $location->code,
                'name' => $location->name,
            ] : null,
            'is_exact_barcode' => false,
            'count' => count($items),
            'data' => $items,
            'meta' => [
                'current_page' => $paginated->currentPage(),
                'last_page' => $paginated->lastPage(),
                'per_page' => $paginated->perPage(),
                'total' => $paginated->total(),
            ],
        ]);
    }

    /**
     * Incremental cursor/pagination sync for Stock Toko offline cache on the cashier browser.
     * Only syncs products registered in the active store location (never the 17,000 Stock Master).
     */
    public function syncCatalog(Request $request): JsonResponse
    {
        $perPage = min(max(10, (int) $request->query('per_page', 100)), 100);
        $afterId = (int) $request->query('after_id', 0);
        $since = $request->query('since');

        $locContext = $this->resolvePosStoreContext($request, forceStrict: true);
        if ($locContext['error']) {
            return $locContext['error'];
        }

        /** @var Location|null $location */
        $location = $locContext['location'];
        if (!$location) {
            return response()->json([
                'success' => false,
                'message' => 'Lokasi toko aktif wajib ditentukan untuk sinkronisasi Stock Toko.',
                'data' => [],
            ], 422);
        }

        $query = Product::with([
            'supplier',
            'units',
            'productStocks' => fn ($sq) => $sq->where('location_id', $location->id),
        ])->whereHas('productStocks', function ($sq) use ($location, $since) {
            $sq->where('location_id', $location->id)->where('is_active', true);
            if ($since) {
                try {
                    $sinceDt = Carbon::parse($since);
                    $sq->where(function ($sub) use ($sinceDt) {
                        $sub->where('product_stocks.updated_at', '>=', $sinceDt)
                            ->orWhere('products.updated_at', '>=', $sinceDt);
                    });
                } catch (\Throwable $e) {
                }
            }
        });

        if ($afterId > 0) {
            $query->where('id', '>', $afterId);
            $items = $query->orderBy('id', 'asc')->limit($perPage + 1)->get();
            $hasMore = $items->count() > $perPage;
            $slice = $items->take($perPage);
            $lastId = $slice->last()?->id ?? $afterId;
            $totalStoreSku = ProductStock::where('location_id', $location->id)->where('is_active', true)->count();

            return response()->json([
                'success' => true,
                'catalog_mode' => 'STOCK_TOKO',
                'location' => [
                    'id' => $location->id,
                    'code' => $location->code,
                    'name' => $location->name,
                ],
                'server_time' => now()->toIso8601String(),
                'has_more' => $hasMore,
                'next_after_id' => $hasMore ? $lastId : null,
                'total_store_sku' => $totalStoreSku,
                'data' => $slice->map(fn ($p) => $this->formatProductPayload($p, $location->id, $location))->values()->all(),
            ]);
        }

        $paginated = $query->orderBy('id', 'asc')->paginate($perPage);
        $items = collect($paginated->items())->map(
            fn ($p) => $this->formatProductPayload($p, $location->id, $location)
        )->values()->all();

        return response()->json([
            'success' => true,
            'catalog_mode' => 'STOCK_TOKO',
            'location' => [
                'id' => $location->id,
                'code' => $location->code,
                'name' => $location->name,
            ],
            'server_time' => now()->toIso8601String(),
            'has_more' => $paginated->hasMorePages(),
            'next_after_id' => !empty($items) && $paginated->hasMorePages() ? end($items)['id'] : null,
            'total_store_sku' => $paginated->total(),
            'data' => $items,
            'meta' => [
                'current_page' => $paginated->currentPage(),
                'last_page' => $paginated->lastPage(),
                'per_page' => $paginated->perPage(),
                'total' => $paginated->total(),
            ],
        ]);
    }

    /**
     * Instant lookup by Barcode for barcode scanner (Stock Toko scoped).
     */
    public function lookupBarcode(Request $request, string $barcode): JsonResponse
    {
        $barcode = trim($barcode);
        $locContext = $this->resolvePosStoreContext($request);
        if ($locContext['error']) {
            return $locContext['error'];
        }

        /** @var Location|null $location */
        $location = $locContext['location'];
        $strictStoreMode = $locContext['strict'];

        $productQuery = Product::with(['supplier', 'units']);
        if ($location && $strictStoreMode) {
            $productQuery->with(['productStocks' => fn ($sq) => $sq->where('location_id', $location->id)])
                ->whereHas('productStocks', fn ($sq) => $sq->where('location_id', $location->id)->where('is_active', true));
        }

        $product = (clone $productQuery)->where('barcode', $barcode)->first();

        if (!$product) {
            $unit = ProductUnit::where('barcode', $barcode)->first();
            if ($unit) {
                $product = (clone $productQuery)->where('id', $unit->product_id)->first();
            }
        }

        if (!$product) {
            $locMsg = ($strictStoreMode && $location) ? " pada Stock Toko [{$location->name}]" : ' dalam sistem katalog';
            return response()->json([
                'success' => false,
                'message' => "Produk dengan barcode [{$barcode}] tidak ditemukan{$locMsg}.",
                'data' => null,
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Produk berhasil dipindai.',
            'catalog_mode' => $strictStoreMode ? 'STOCK_TOKO' : 'LEGACY',
            'location' => $location ? [
                'id' => $location->id,
                'code' => $location->code,
                'name' => $location->name,
            ] : null,
            'data' => $this->formatProductPayload($product, $strictStoreMode ? $location?->id : null, $location),
        ]);
    }

    /**
     * List distinct categories for quick tab filters (scoped to active store Stock Toko).
     */
    public function categories(Request $request): JsonResponse
    {
        $locContext = $this->resolvePosStoreContext($request);
        if ($locContext['error']) {
            return $locContext['error'];
        }

        $location = $locContext['location'];
        $strictStoreMode = $locContext['strict'];

        $query = Product::distinct()->whereNotNull('category');
        if ($location && $strictStoreMode) {
            $query->whereHas('productStocks', fn ($sq) => $sq->where('location_id', $location->id)->where('is_active', true));
        }

        $categories = $query->orderBy('category')->pluck('category');

        return response()->json([
            'success' => true,
            'data' => array_merge(['Semua'], $categories->toArray()),
        ]);
    }

    /**
     * Store new product in Stock Master. Initial stock (if > 0) belongs to Gudang Pusat (not Store Stock).
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'sku' => 'nullable|string|max:50|unique:products,sku',
            'ilp_id' => 'nullable|string|max:50',
            'barcode' => 'required|string|max:50|unique:products,barcode',
            'name' => 'required|string|max:150',
            'category' => 'required|string|max:50',
            'unit' => 'required|string|max:20',
            'cost_price' => 'required|numeric|min:0',
            'last_cost_price' => 'nullable|numeric|min:0',
            'price' => 'required|numeric|min:0',
            'member_price' => 'nullable|numeric|min:0',
            'min_qty1' => 'nullable|numeric|min:0',
            'min_qty2' => 'nullable|numeric|min:0',
            'min_qty3' => 'nullable|numeric|min:0',
            'harga_level1' => 'nullable|numeric|min:0',
            'stock' => 'required|integer|min:0',
            'min_stock' => 'required|integer|min:0',
            'reorder_point' => 'nullable|integer|min:0',
            'markup_percent' => 'nullable|numeric|min:0',
            'ko_sup' => 'nullable|string|max:50',
            'act' => 'nullable|string|max:10',
            'masa' => 'nullable|string|max:50',
            'dump' => 'nullable|string',
            'is_taxable' => 'boolean',
            'tax_rate' => 'nullable|numeric|min:0',
            'expired_date' => 'nullable|date',
            'clearance_discount_percent' => 'nullable|numeric|min:0|max:100',
            'rack_location' => 'nullable|string|max:100',
            'location_id' => 'nullable|integer|exists:locations,id',
            'is_consignment' => 'boolean',
            'supplier_id' => 'nullable|exists:suppliers,id',
            'commission_percent' => 'nullable|numeric|min:0|max:100',
            'units' => 'nullable|array',
            'units.*.unit_name' => 'required|string',
            'units.*.conversion_factor' => 'required|integer|min:2',
            'units.*.price' => 'required|numeric|min:0',
            'units.*.barcode' => 'nullable|string',
        ]);

        return DB::transaction(function () use ($validated, $request) {
            $sku = $validated['sku'] ?? ('PRD-' . Str::padLeft((string) (Product::max('id') + 1), 3, '0'));
            $initialStock = (int) $validated['stock'];

            $product = Product::create([
                'sku' => $sku,
                'ilp_id' => $validated['ilp_id'] ?? null,
                'barcode' => $validated['barcode'],
                'name' => $validated['name'],
                'category' => $validated['category'],
                'unit' => $validated['unit'],
                'cost_price' => (float) $validated['cost_price'],
                'last_cost_price' => !empty($validated['last_cost_price']) ? (float) $validated['last_cost_price'] : (float) $validated['cost_price'],
                'price' => (float) $validated['price'],
                'member_price' => !empty($validated['member_price']) ? (float) $validated['member_price'] : (!empty($validated['harga_level1']) ? (float) $validated['harga_level1'] : (float) $validated['price']),
                'min_qty1' => !empty($validated['min_qty1']) ? (float) $validated['min_qty1'] : 1.0,
                'min_qty2' => !empty($validated['min_qty2']) ? (float) $validated['min_qty2'] : null,
                'min_qty3' => !empty($validated['min_qty3']) ? (float) $validated['min_qty3'] : null,
                'harga_level1' => !empty($validated['harga_level1']) ? (float) $validated['harga_level1'] : (!empty($validated['member_price']) ? (float) $validated['member_price'] : (float) $validated['price']),
                'stock' => $initialStock,
                'min_stock' => (int) $validated['min_stock'],
                'reorder_point' => !empty($validated['reorder_point']) ? (int) $validated['reorder_point'] : ((int) $validated['min_stock'] * 2),
                'markup_percent' => !empty($validated['markup_percent']) ? (float) $validated['markup_percent'] : 0.0,
                'ko_sup' => $validated['ko_sup'] ?? null,
                'act' => $validated['act'] ?? 'Y',
                'masa' => $validated['masa'] ?? null,
                'dump' => $validated['dump'] ?? null,
                'is_taxable' => (bool) ($validated['is_taxable'] ?? true),
                'tax_rate' => (float) ($validated['tax_rate'] ?? 11.0),
                'expired_date' => $validated['expired_date'] ?? null,
                'clearance_discount_percent' => (float) ($validated['clearance_discount_percent'] ?? 20.0),
                'rack_location' => $validated['rack_location'] ?? null,
                'is_consignment' => (bool) ($validated['is_consignment'] ?? false),
                'supplier_id' => $validated['supplier_id'] ?? null,
                'commission_percent' => (float) ($validated['commission_percent'] ?? 0.0),
                'status' => $initialStock <= 0 ? 'HABIS' : ($initialStock <= (int) $validated['min_stock'] ? 'STOK_RENDAH' : 'TERSEDIA'),
            ]);

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

            // Place initial stock into Gudang Pusat (never auto-register into Store Stock)
            if ($initialStock > 0) {
                $targetLocId = $validated['location_id'] ?? $this->stockService->getWarehouseLocation()?->id;
                if ($targetLocId) {
                    $this->stockService->addStock($product->id, $targetLocId, $initialStock, [
                        'movement_type' => 'PURCHASE_IN',
                        'unit_cost' => (float) $validated['cost_price'],
                        'expired_date' => $validated['expired_date'] ?? null,
                        'user_id' => Auth::id(),
                        'notes' => 'Stok awal pendaftaran Master Produk di Gudang Pusat',
                    ]);
                }
            }

            return response()->json([
                'success' => true,
                'message' => "Produk [{$product->name}] berhasil disimpan ke katalog master inventaris.",
                'data' => $this->formatProductPayload($product->fresh(['supplier', 'units', 'productStocks.location'])),
            ], 201);
        });
    }

    /**
     * Update product details in Stock Master.
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
            'ilp_id' => 'nullable|string|max:50',
            'name' => 'required|string|max:150',
            'category' => 'required|string|max:50',
            'unit' => 'required|string|max:20',
            'cost_price' => 'required|numeric|min:0',
            'last_cost_price' => 'nullable|numeric|min:0',
            'price' => 'required|numeric|min:0',
            'member_price' => 'nullable|numeric|min:0',
            'min_qty1' => 'nullable|numeric|min:0',
            'min_qty2' => 'nullable|numeric|min:0',
            'min_qty3' => 'nullable|numeric|min:0',
            'harga_level1' => 'nullable|numeric|min:0',
            'stock' => 'required|integer|min:0',
            'min_stock' => 'required|integer|min:0',
            'reorder_point' => 'nullable|integer|min:0',
            'markup_percent' => 'nullable|numeric|min:0',
            'ko_sup' => 'nullable|string|max:50',
            'act' => 'nullable|string|max:10',
            'masa' => 'nullable|string|max:50',
            'dump' => 'nullable|string',
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
            'data' => $this->formatProductPayload($product->fresh(['supplier', 'units', 'productStocks.location'])),
        ]);
    }

    /**
     * Delete product from Stock Master.
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
     * Resolve active Store context and determine whether strict Stock Toko filtering applies.
     */
    private function resolvePosStoreContext(Request $request, bool $forceStrict = false): array
    {
        $locationIdParam = $request->integer('location_id') ?: ($request->header('X-Location-Id') ? (int) $request->header('X-Location-Id') : null);
        $branchParam = $request->query('branch') ?? $request->header('X-Branch');
        $terminalParam = $request->query('terminal_code') ?? $request->header('X-Terminal-Code');
        $user = Auth::user();

        if ($locationIdParam) {
            $rawLoc = Location::find($locationIdParam);
            if (!$rawLoc) {
                return [
                    'location' => null,
                    'strict' => true,
                    'error' => response()->json(['success' => false, 'message' => 'Lokasi tidak ditemukan.', 'data' => []], 422),
                ];
            }
            if ($rawLoc->type === 'WAREHOUSE') {
                return [
                    'location' => null,
                    'strict' => true,
                    'error' => response()->json(['success' => false, 'message' => 'Gudang Pusat tidak dapat digunakan sebagai katalog kasir POS.', 'data' => []], 422),
                ];
            }
        }

        if ($branchParam && strtolower(trim($branchParam)) === 'gudang pusat') {
            return [
                'location' => null,
                'strict' => true,
                'error' => response()->json(['success' => false, 'message' => 'Gudang Pusat bukan lokasi penjualan kasir.', 'data' => []], 422),
            ];
        }

        $hasExplicitContext = $locationIdParam || !empty($branchParam) || !empty($terminalParam) || $user !== null;
        $strict = $forceStrict || $hasExplicitContext || $this->stockService->hasStoreStocks();

        $location = $this->stockService->resolveLocation(
            $locationIdParam,
            $branchParam,
            $terminalParam,
            $user,
            'STORE'
        );

        // Validate terminal vs explicit requested location mismatch
        if (!empty($terminalParam) && ($locationIdParam || !empty($branchParam)) && $location) {
            $terminalExpected = $this->stockService->resolveExpectedLocationForTerminal($terminalParam);
            if ($terminalExpected && $terminalExpected->id !== $location->id) {
                return [
                    'location' => $location,
                    'strict' => true,
                    'error' => response()->json([
                        'success' => false,
                        'message' => "Terminal {$terminalParam} terdaftar di {$terminalExpected->name}, tidak sesuai dengan lokasi {$location->name}.",
                        'data' => [],
                    ], 422),
                ];
            }
        }

        if ($strict && $location && $user && !$this->stockService->isUserAllowedInLocation($user, $location)) {
            return [
                'location' => $location,
                'strict' => true,
                'error' => response()->json(['success' => false, 'message' => "Akun Anda tidak memiliki akses ke lokasi {$location->name}.", 'data' => []], 403),
            ];
        }

        return [
            'location' => $location,
            'strict' => $strict,
            'error' => null,
        ];
    }

    /**
     * Format product payload. When $locationId is provided, stock & stock_status reflect Stock Toko at that location.
     */
    private function formatProductPayload(Product $p, ?int $locationId = null, ?Location $location = null): array
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

        $locStock = $locationId ? $p->stockAtLocation($locationId) : null;
        $effectiveStock = $locationId ? (int) ($locStock?->stock ?? 0) : (int) $p->stock;
        $effectiveMinStock = ($locStock && $locStock->min_stock !== null) ? (int) $locStock->min_stock : (int) $p->min_stock;
        $stockStatus = $effectiveStock <= 0
            ? 'HABIS'
            : ($effectiveStock <= $effectiveMinStock ? 'STOK_RENDAH' : 'TERSEDIA');

        $branchStock = [
            'GDG-001' => 0,
            'CAB-001' => 0,
            'CAB-002' => 0,
            'CAB-003' => 0,
        ];
        if ($p->relationLoaded('productStocks')) {
            foreach ($p->productStocks as $ps) {
                if ($ps->location) {
                    $branchStock[$ps->location->code] = (int) $ps->stock;
                }
            }
        }

        return [
            'id' => $p->id,
            'ilp_id' => $p->ilp_id ?: (string) $p->id,
            'sku' => $p->sku,
            'barcode' => $p->barcode,
            'name' => $p->name,
            'category' => $p->category,
            'unit' => $p->unit,
            'min_qty1' => $p->min_qty1 !== null ? (float) $p->min_qty1 : 1.0,
            'min_qty2' => $p->min_qty2 !== null ? (float) $p->min_qty2 : null,
            'min_qty3' => $p->min_qty3 !== null ? (float) $p->min_qty3 : null,
            'harga_level1' => $p->harga_level1 !== null ? (float) $p->harga_level1 : (float) ($p->member_price ?? $p->price),
            'reorder_point' => $p->reorder_point !== null ? (int) $p->reorder_point : ((int) $p->min_stock * 2),
            'markup_percent' => $p->markup_percent !== null ? (float) $p->markup_percent : 0.0,
            'ko_sup' => $p->ko_sup ?: ($p->supplier?->code ?? ''),
            'act' => $p->act ?: ($p->status === 'NONAKTIF' ? 'N' : 'Y'),
            'masa' => $p->masa ?? '',
            'dump' => $p->dump ?? '',
            'rack_location' => $p->rack_location ?? 'Lorong Umum',
            'cost_price' => (float) $p->cost_price,
            'last_cost_price' => $p->last_cost_price !== null ? (float) $p->last_cost_price : (float) $p->cost_price,
            'raw_last_cost_price' => $p->last_cost_price !== null ? (float) $p->last_cost_price : null,
            'original_price' => (float) $p->price,
            'original_member_price' => (float) ($p->member_price ?? $p->price),
            // Location-scoped Stock (Stock Toko or Master aggregate)
            'stock' => $effectiveStock,
            'master_stock' => (int) $p->stock,
            'min_stock' => $effectiveMinStock,
            'is_in_stock' => $effectiveStock > 0,
            'is_low_stock' => $effectiveStock > 0 && $effectiveStock <= $effectiveMinStock,
            'is_out_of_stock' => $effectiveStock <= 0,
            'stock_status' => $stockStatus,
            'location_id' => $locationId,
            'location_code' => $location?->code,
            'location_name' => $location?->name,
            'stock_version' => $locStock?->version ?? 1,
            'branch_stock' => $branchStock,
            // Expired Date & Two-Tier Alarm System
            'expired_date' => $p->expired_date ? $p->expired_date->toDateString() : null,
            'days_until_expiration' => $p->days_until_expiration,
            'is_near_expired' => (bool) $p->is_critical_expired,
            'is_warning_expired' => (bool) $p->is_warning_expired,
            'is_critical_expired' => (bool) $p->is_critical_expired,
            'is_expired' => (bool) $p->is_expired,
            'expiration_alert_level' => $alarmLevel,
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
            'extra_attributes' => $p->extra_attributes ?? [],
            'supplied_by' => $p->relationLoaded('supplierProducts')
                ? $p->supplierProducts
                    ->sortByDesc('last_purchase_at')
                    ->map(fn ($sp) => [
                        'supplier_id' => $sp->supplier_id,
                        'supplier_code' => $sp->supplier?->code ?? '-',
                        'supplier_name' => $sp->supplier?->name ?? 'Supplier',
                        'supplier_status' => $sp->supplier?->status ?? 'AKTIF',
                        'last_purchase_price' => (float) $sp->last_purchase_price,
                        'last_purchase_at' => $sp->last_purchase_at?->toIso8601String(),
                        'total_transactions' => (int) $sp->total_transactions,
                        'supply_status' => $sp->supply_status,
                    ])->values()->all()
                : [],
            'units' => $p->units->map(fn ($u) => [
                'id' => $u->id,
                'unit_name' => $u->unit_name,
                'conversion_factor' => (int) $u->conversion_factor,
                'price' => (float) $u->price,
                'barcode' => $u->barcode,
            ]),
        ];
    }
}
