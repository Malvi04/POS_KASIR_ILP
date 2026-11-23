<?php

namespace App\Http\Controllers\Api\Shared;

use App\Http\Controllers\Controller;
use App\Models\CouponUnit;
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
        $perPage = max(10, min(500, (int) $request->query('per_page', 20)));

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
                $locationCode ?: $branchParam,
                null,
                $authUser,
                null
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
            } elseif ($locationIdParam || $locationCode || $branchParam) {
                return response()->json([
                    'success' => false,
                    'message' => 'Lokasi yang diminta tidak ditemukan atau tidak diizinkan untuk Kasir.',
                ], 403);
            } else {
                $primaryBranch = trim(explode(',', $authUser->branch ?? 'Cabang Pusat')[0]);
                $targetLocation = $this->stockService->resolveLocation(null, $primaryBranch, null, $authUser, 'STORE');
            }
        }

        $query = Product::with(['supplier', 'units', 'supplierProducts.supplier', 'productStocks.location']);

        if ($targetLocation && ($onlyRegisteredInLoc || $locationCode || $branchParam)) {
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
            if (strtolower($category) === 'kupon' || str_contains(strtolower($category), 'kupon')) {
                $query->where(function ($sub) {
                    $sub->where('category', 'like', '%Kupon%')
                        ->orWhere('category', 'like', '%Voucher%')
                        ->orWhere('sku', 'like', 'KPN%');
                });
            } else {
                $query->where('category', $category);
            }
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

        $kpiKey = 'products_kpi_summary_' . ($targetLocation ? $targetLocation->id : 'all') . '_' . $today->toDateString();
        $kpi = \Illuminate\Support\Facades\Cache::remember($kpiKey, 60, function () use ($today, $targetLocation) {
            if ($targetLocation) {
                $raw = ProductStock::where('location_id', $targetLocation->id)
                    ->where('is_active', true)
                    ->join('products', 'products.id', '=', 'product_stocks.product_id')
                    ->selectRaw(
                        'COUNT(*) as total_products,'
                        . ' COALESCE(SUM(CASE WHEN product_stocks.stock <= product_stocks.min_stock AND product_stocks.stock > 0 THEN 1 ELSE 0 END), 0) as low_stock_count,'
                        . ' COALESCE(SUM(CASE WHEN product_stocks.stock <= 0 THEN 1 ELSE 0 END), 0) as out_of_stock_count,'
                        . ' COALESCE(SUM(CASE WHEN products.expired_date IS NOT NULL AND products.expired_date BETWEEN ? AND ? THEN 1 ELSE 0 END), 0) as critical_expired_count,'
                        . ' COALESCE(SUM(CASE WHEN products.expired_date IS NOT NULL AND products.expired_date BETWEEN ? AND ? THEN 1 ELSE 0 END), 0) as warning_expired_count,'
                        . ' COALESCE(SUM(CASE WHEN products.is_consignment THEN 1 ELSE 0 END), 0) as consignment_count,'
                        . ' COALESCE(SUM(product_stocks.stock), 0) as total_stock_units,'
                        . ' COALESCE(SUM(product_stocks.stock * products.cost_price), 0) as total_inventory_value',
                        [
                            $today->toDateString(),
                            $today->copy()->addDays(14)->toDateString(),
                            $today->copy()->addDays(15)->toDateString(),
                            $today->copy()->addDays(30)->toDateString(),
                        ]
                    )->first();
            } else {
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
            }

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

        // Filter Kupon category specifically for POS coupon unit sales
        if ($category && (strtolower($category) === 'kupon' || str_contains(strtolower($category), 'kupon'))) {
            $couponQuery = CouponUnit::with('product')
                ->whereIn('status', ['AVAILABLE', 'ISSUED', 'TERVALIDASI', 'SIAP_AMBIL']);

            if ($location) {
                $couponQuery->where(function ($cq) use ($location) {
                    $cq->where('location_id', $location->id)
                      ->orWhere('branch', $location->name);
                });
            } else {
                $couponQuery->where(function ($cq) {
                    $cq->whereNotNull('location_id')->where('location_id', '!=', 1);
                })->where('branch', '!=', 'Gudang Pusat');
            }

            if ($q !== '') {
                $lowerQ = strtolower($q);
                $couponQuery->where(function ($cq) use ($q, $lowerQ) {
                    $cq->where('serial_number', 'like', "%{$q}%")
                       ->orWhere('recipient_name', 'like', "%{$q}%")
                       ->orWhere('notes', 'like', "%{$q}%")
                       ->orWhere('category', 'like', "%{$q}%")
                       ->orWhere('volume', 'like', "%{$q}%");
                });
            }

            $coupons = $couponQuery->latest('id')->take($limit)->get();
            $items = $coupons->map(fn ($c) => $this->formatCouponAsProductPayload($c, $location))->values()->all();

            return response()->json([
                'success' => true,
                'catalog_mode' => 'KUPON',
                'location' => $location ? [
                    'id' => $location->id,
                    'code' => $location->code,
                    'name' => $location->name,
                ] : null,
                'is_exact_barcode' => false,
                'count' => count($items),
                'data' => $items,
                'meta' => [
                    'current_page' => 1,
                    'last_page' => 1,
                    'per_page' => $limit,
                    'total' => count($items),
                ],
            ]);
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

        // Also search active coupons if query matches coupon serial/notes/category
        if ($q !== '') {
            $matchingCoupons = CouponUnit::with('product')
                ->whereIn('status', ['AVAILABLE', 'ISSUED', 'TERVALIDASI', 'SIAP_AMBIL'])
                ->where(function ($cq) use ($q, $lowerQ) {
                    $cq->where('serial_number', 'like', "%{$q}%")
                       ->orWhere('notes', 'like', "%{$q}%")
                       ->orWhere('category', 'like', "%{$q}%")
                       ->orWhere('volume', 'like', "%{$q}%");
                })
                ->take(10)
                ->get();

            if ($matchingCoupons->isNotEmpty()) {
                $couponItems = $matchingCoupons->map(fn ($c) => $this->formatCouponAsProductPayload($c, $location))->all();
                $items = array_merge($couponItems, $items);
            }
        }

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

        if ($afterId === 0 && !$since) {
            $couponQuery = CouponUnit::with('product')
                ->whereIn('status', ['AVAILABLE', 'ISSUED', 'TERVALIDASI', 'SIAP_AMBIL']);
            if ($location) {
                $couponQuery->where(function ($cq) use ($location) {
                    $cq->where('location_id', $location->id)
                      ->orWhere('branch', $location->name);
                });
            } else {
                $couponQuery->where(function ($cq) {
                    $cq->whereNotNull('location_id')->where('location_id', '!=', 1);
                })->where('branch', '!=', 'Gudang Pusat');
            }
            $activeCoupons = $couponQuery->get();
            if ($activeCoupons->isNotEmpty()) {
                $couponPayloads = $activeCoupons->map(fn ($c) => $this->formatCouponAsProductPayload($c, $location))->all();
                $items = array_merge($items, $couponPayloads);
            }
        }

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
            $cleanBarcode = ltrim($barcode, '#');
            $coupon = CouponUnit::with('product')
                ->where(function ($q) use ($barcode, $cleanBarcode) {
                    $q->where('serial_number', $barcode)
                      ->orWhere('serial_number', '#' . $cleanBarcode)
                      ->orWhere('serial_number', $cleanBarcode);
                })
                ->whereIn('status', ['AVAILABLE', 'ISSUED', 'TERVALIDASI', 'SIAP_AMBIL'])
                ->first();

            if ($coupon) {
                if ($location && $location->type === 'STORE') {
                    $isMatch = ((int) $coupon->location_id === (int) $location->id) || (strcasecmp((string) $coupon->branch, (string) $location->name) === 0);
                    if (!$isMatch) {
                        return response()->json([
                            'success' => false,
                            'message' => "Kupon [{$coupon->serial_number}] belum diterima di gerai {$location->name} (masih berada di " . ($coupon->branch ?: 'Gudang Pusat') . "). Harap lakukan pengiriman dan penerimaan di menu Barang Masuk terlebih dahulu.",
                            'data' => null,
                        ], 404);
                    }
                }

                return response()->json([
                    'success' => true,
                    'message' => "Kupon [{$coupon->serial_number}] berhasil dipindai.",
                    'catalog_mode' => 'KUPON',
                    'location' => $location ? [
                        'id' => $location->id,
                        'code' => $location->code,
                        'name' => $location->name,
                    ] : null,
                    'data' => $this->formatCouponAsProductPayload($coupon, $location),
                ]);
            }

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

        $categories = $query->orderBy('category')->pluck('category')->toArray();
        $couponQuery = CouponUnit::whereIn('status', ['AVAILABLE', 'ISSUED', 'TERVALIDASI', 'SIAP_AMBIL']);
        if ($location) {
            $couponQuery->where(function ($cq) use ($location) {
                $cq->where('location_id', $location->id)
                  ->orWhere('branch', $location->name);
            });
        } else {
            $couponQuery->where(function ($cq) {
                $cq->whereNotNull('location_id')->where('location_id', '!=', 1);
            })->where('branch', '!=', 'Gudang Pusat');
        }
        $hasCoupons = $couponQuery->exists();
        if ($hasCoupons && !in_array('Kupon', $categories)) {
            $categories[] = 'Kupon';
        }

        return response()->json([
            'success' => true,
            'data' => array_merge(['Semua'], $categories),
        ]);
    }

    /**
     * List all active coupons for Cashier POS.
     */
    public function couponProducts(Request $request): JsonResponse
    {
        $locContext = $this->resolvePosStoreContext($request);
        $location = $locContext['location'] ?? null;

        $couponQuery = CouponUnit::with('product')
            ->whereIn('status', ['AVAILABLE', 'ISSUED', 'TERVALIDASI', 'SIAP_AMBIL']);

        if ($location) {
            $couponQuery->where(function ($cq) use ($location) {
                $cq->where('location_id', $location->id)
                  ->orWhere('branch', $location->name);
            });
        } else {
            $couponQuery->where(function ($cq) {
                $cq->whereNotNull('location_id')->where('location_id', '!=', 1);
            })->where('branch', '!=', 'Gudang Pusat');
        }

        $coupons = $couponQuery->latest('id')->get();

        $items = $coupons->map(fn ($c) => $this->formatCouponAsProductPayload($c, $location))->values()->all();

        return response()->json([
            'success' => true,
            'count' => count($items),
            'data' => $items,
        ]);
    }

    /**
     * Format a CouponUnit into the standardized POS Product payload format.
     */
    public function formatCouponAsProductPayload(CouponUnit $c, ?Location $location = null): array
    {
        $categoryInfo = match(strtolower($c->category)) {
            'bbm' => ['label' => 'BBM & Armada Toko', 'name' => 'Kupon BBM (' . ($c->volume ?: 'Armada') . ')'],
            'beras' => ['label' => 'Beras Subsidi', 'name' => 'Kupon Beras Subsidi (' . ($c->volume ?: '5kg') . ')'],
            'galon' => ['label' => 'Galon Air Mineral', 'name' => 'Kupon Galon Air (' . ($c->volume ?: '19L') . ')'],
            'pulsa' => ['label' => 'Pulsa Operasional', 'name' => 'Kupon Pulsa (' . ($c->volume ?: 'Operasional') . ')'],
            'produk' => ['label' => 'Produk Khusus', 'name' => 'Kupon Produk (' . ($c->product?->name ?: ($c->volume ?: 'Khusus')) . ')'],
            default => ['label' => ucfirst($c->category), 'name' => 'Kupon ' . ucfirst($c->category) . ' (' . ($c->volume ?: '1 Unit') . ')']
        };

        $cleanSerial = preg_replace('/[^A-Za-z0-9\-]/', '', $c->serial_number);
        $nominal = (float) ($c->nominal_value > 0 ? $c->nominal_value : ($c->product?->price ?? 50000.0));
        $displayName = $c->notes ? (Str::startsWith($c->notes, 'Kupon') ? $c->notes : 'Kupon ' . $c->notes) : $categoryInfo['name'];

        return [
            'id' => $c->product_id ?: (900000 + $c->id),
            'ilp_id' => 'KPN-' . $c->id,
            'sku' => $cleanSerial,
            'barcode' => $c->serial_number,
            'name' => $displayName,
            'id_produk' => 'KPN-' . $c->id,
            'kode_barcode' => $c->serial_number,
            'kode_produk' => $cleanSerial,
            'qty_produk' => 1,
            'nama_produk' => $displayName,
            'satuan_unit' => 'Lembar',
            'harga_beli' => $nominal,
            'harga_jual_umum' => $nominal,
            'harga_jual_member' => $nominal,
            'produk_disc' => 0,
            'minimum_stock' => 1,
            'ppn_out' => 0,
            'ppn_chk' => 'N',
            'harga_beli_terakhir' => $nominal,
            'status_barang' => 'TERSEDIA',
            'kode_suplier' => 'ILP-KUPON',
            'manufacturing_date' => null,
            'exp_date' => null,
            'brand' => 'Koperasi ILP',
            'category' => 'Kupon',
            'unit' => 'Lembar',
            'cost_price' => $nominal,
            'last_cost_price' => $nominal,
            'raw_last_cost_price' => $nominal,
            'original_price' => $nominal,
            'original_member_price' => $nominal,
            'stock' => 1,
            'master_stock' => 1,
            'warehouse_stock' => 1,
            'min_stock' => 1,
            'is_in_stock' => true,
            'is_low_stock' => false,
            'is_out_of_stock' => false,
            'stock_status' => 'TERSEDIA',
            'location_id' => $location?->id,
            'location_code' => $location?->code,
            'location_name' => $location?->name,
            'stock_version' => 1,
            'branch_stock' => [],
            'allocated_branches' => [],
            'price' => $nominal,
            'member_price' => $nominal,
            'is_taxable' => false,
            'tax_rate' => 0,
            'tax_label' => 'Bebas Pajak (Kupon)',
            'is_consignment' => false,
            'is_coupon' => true,
            'coupon_number' => $c->serial_number,
            'coupon_id' => $c->id,
            'serial_number' => $c->serial_number,
            'product_type' => 'KUPON',
            'product_type_label' => 'Kupon ' . $categoryInfo['label'],
            'recipient_name' => $c->recipient_name,
            'recipient_nik' => $c->recipient_nik,
            'recipient_dept' => $c->recipient_dept,
            'volume' => $c->volume,
            'units' => [],
        ];
    }

    /**
     * List Produk Mitra UMKM for Cashier (with branch stock & availability status).
     */
    public function umkmProducts(Request $request): JsonResponse
    {
        $this->stockService->ensureDefaultLocations();

        $locContext = $this->resolvePosStoreContext($request);
        $location = $locContext['location']
            ?? $this->stockService->resolveLocation(null, 'Toko Pusat', null, Auth::user(), 'STORE')
            ?? Location::where('type', '!=', 'WAREHOUSE')->first();

        $warehouse = $this->stockService->getWarehouseLocation();
        $search = trim((string) $request->query('q', $request->query('search', '')));
        $supplierId = $request->query('supplier_id');
        $category = trim((string) $request->query('category', ''));

        $query = Product::with(['supplier', 'units', 'productStocks.location'])
            ->where(function ($q) {
                $q->where('is_consignment', true)
                  ->orWhereHas('supplier', fn ($sq) => $sq->where('supplier_type', \App\Models\Supplier::TYPE_CONSIGNMENT));
            });

        // Strict Branch Isolation: Only display UMKM products that have actually been sent/allocated to this branch
        if ($location) {
            $query->whereHas('productStocks', function ($sq) use ($location) {
                $sq->where('location_id', $location->id)
                   ->where('is_active', true)
                   ->where(function ($q2) {
                       $q2->where('stock', '>', 0)
                          ->orWhereNotNull('first_received_at');
                   });
            });
        }

        if ($supplierId && $supplierId !== 'all') {
            $query->where('supplier_id', (int) $supplierId);
        }

        if ($category !== '' && $category !== 'all' && $category !== 'Semua') {
            if (strtolower($category) === 'kupon' || str_contains(strtolower($category), 'kupon')) {
                $query->where(function ($sub) {
                    $sub->where('category', 'like', '%Kupon%')
                        ->orWhere('category', 'like', '%Voucher%')
                        ->orWhere('sku', 'like', 'KPN%');
                });
            } else {
                $query->where('category', $category);
            }
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

        $products = $query->orderBy('name', 'asc')->get();
        $whCode = $warehouse?->code ?? 'GDG-001';

        $items = $products->map(function (Product $p) use ($location, $whCode) {
            $payload = $this->formatProductPayload($p, $location?->id, $location);
            $locPs = $location ? $p->stockAtLocation($location->id) : null;
            $branchQty = $locPs ? (int) $locPs->stock : 0;
            $whQty = (int) ($payload['branch_stock'][$whCode] ?? (empty($payload['branch_stock']) ? $p->stock : 0));

            $payload['branch_stock_qty'] = $branchQty;
            $payload['warehouse_stock_qty'] = $whQty;
            $payload['is_registered_in_branch'] = $locPs !== null && (bool) $locPs->is_active;
            $payload['can_transact'] = $branchQty > 0;
            $payload['is_consignment'] = true;

            return $payload;
        })->values();

        $partners = \App\Models\Supplier::consignment()
            ->orderBy('name')
            ->get(['id', 'code', 'name', 'owner_name', 'category', 'commission_percent', 'status']);

        return response()->json([
            'success' => true,
            'location' => $location ? [
                'id' => $location->id,
                'code' => $location->code,
                'name' => $location->name,
            ] : null,
            'summary' => [
                'total_umkm_skus' => $items->count(),
                'ready_to_transact_count' => $items->where('can_transact', true)->count(),
                'total_branch_stock' => (int) $items->sum('branch_stock_qty'),
                'total_warehouse_stock' => (int) $items->sum('warehouse_stock_qty'),
                'total_partners' => $partners->count(),
            ],
            'partners' => $partners,
            'data' => $items,
        ]);
    }

    /**
     * Load / Synchronize Produk Mitra UMKM into the Cashier's Active Store Catalog (Stock Toko)
     * so all UMKM products are registered and ready for POS transactions.
     */
    public function loadUmkmProducts(Request $request): JsonResponse
    {
        $this->stockService->ensureDefaultLocations();

        $locContext = $this->resolvePosStoreContext($request);
        $location = $locContext['location']
            ?? $this->stockService->resolveLocation(null, $request->input('branch') ?: 'Toko Pusat', $request->input('terminal_code'), Auth::user(), 'STORE')
            ?? Location::where('type', '!=', 'WAREHOUSE')->first();

        if (!$location) {
            return response()->json([
                'success' => false,
                'message' => 'Lokasi cabang kasir tidak ditemukan.',
            ], 422);
        }

        $targetProductId = $request->integer('product_id') ?: null;
        $warehouse = $this->stockService->getWarehouseLocation();

        return DB::transaction(function () use ($location, $warehouse, $targetProductId) {
            $transferredUnits = 0;

            // 1. Clean up any unshipped 0-stock ProductStock rows at this branch (never received stock)
            ProductStock::where('location_id', $location->id)
                ->where('stock', '<=', 0)
                ->whereNull('first_received_at')
                ->whereHas('product', fn ($pq) => $pq->where('is_consignment', true))
                ->delete();

            // 2. Query ONLY UMKM products that have actually been sent/allocated to this branch
            $branchUmkmQuery = Product::with(['supplier', 'units', 'productStocks.location'])
                ->where(function ($q) {
                    $q->where('is_consignment', true)
                      ->orWhereHas('supplier', fn ($sq) => $sq->where('supplier_type', \App\Models\Supplier::TYPE_CONSIGNMENT));
                })
                ->whereHas('productStocks', function ($sq) use ($location) {
                    $sq->where('location_id', $location->id)
                       ->where('is_active', true)
                       ->where(function ($q2) {
                           $q2->where('stock', '>', 0)
                              ->orWhereNotNull('first_received_at');
                       });
                });

            if ($targetProductId) {
                $branchUmkmQuery->where('id', $targetProductId);
            }

            $allUmkm = $branchUmkmQuery->orderBy('name', 'asc')->get();

            $whCode = $warehouse?->code ?? 'GDG-001';
            $items = $allUmkm->map(function (Product $p) use ($location, $whCode) {
                $payload = $this->formatProductPayload($p, $location->id, $location);
                $locPs = $p->stockAtLocation($location->id);
                $branchQty = $locPs ? (int) $locPs->stock : 0;
                $whQty = (int) ($payload['branch_stock'][$whCode] ?? 0);

                $payload['branch_stock_qty'] = $branchQty;
                $payload['warehouse_stock_qty'] = $whQty;
                $payload['is_registered_in_branch'] = $locPs !== null && (bool) $locPs->is_active;
                $payload['can_transact'] = $branchQty > 0;
                $payload['is_consignment'] = true;

                return $payload;
            })->values();

            $readyCount = $items->where('can_transact', true)->count();
            $message = $items->isEmpty()
                ? "Belum ada produk Mitra UMKM yang dikirim ke {$location->name}."
                : "Berhasil memuat {$items->count()} Produk Mitra UMKM ke katalog kasir {$location->name} ({$readyCount} SKU siap transaksi).";

            return response()->json([
                'success' => true,
                'message' => $message,
                'location' => [
                    'id' => $location->id,
                    'code' => $location->code,
                    'name' => $location->name,
                ],
                'summary' => [
                    'total_umkm_skus' => $items->count(),
                    'ready_to_transact_count' => $readyCount,
                    'total_branch_stock' => (int) $items->sum('branch_stock_qty'),
                    'total_warehouse_stock' => (int) $items->sum('warehouse_stock_qty'),
                    'transferred_units' => $transferredUnits,
                ],
                'data' => $items,
            ]);
        });
    }

    /**
     * Store new product in Stock Master. Initial stock (if > 0) belongs to Gudang Pusat (not Store Stock).
     */
    public function store(Request $request): JsonResponse
    {
        $request->merge([
            'sku' => $request->input('sku') ?: $request->input('kode_produk') ?: $request->input('SKU'),
            'barcode' => $request->input('barcode') ?: $request->input('kode_barcode') ?: $request->input('Kode Barcode'),
            'name' => $request->input('name') ?: $request->input('nama_produk') ?: $request->input('NamaProduk'),
            'category' => $request->input('category') ?: 'Kebutuhan Pokok',
            'unit' => $request->input('unit') ?: $request->input('satuan_unit') ?: $request->input('SatuanUnit') ?: 'Pcs',
            'cost_price' => $request->input('cost_price') ?? $request->input('harga_beli') ?? $request->input('HargaBeli') ?? 0,
            'price' => $request->input('price') ?? $request->input('harga_jual_umum') ?? $request->input('HargaJualUmum') ?? 0,
            'member_price' => $request->input('member_price') ?? $request->input('harga_jual_member') ?? $request->input('HargaJualMember') ?? $request->input('price'),
            'stock' => $request->input('stock') ?? $request->input('qty_produk') ?? $request->input('QtyProduk') ?? 0,
            'min_stock' => $request->input('min_stock') ?? $request->input('minimum_stock') ?? $request->input('MinimumStock') ?? 5,
            'tax_rate' => $request->input('tax_rate') ?? $request->input('ppn_out') ?? $request->input('PPNOut') ?? 11,
            'last_cost_price' => $request->input('last_cost_price') ?? $request->input('harga_beli_terakhir') ?? $request->input('HargaBeliTerakhir') ?? 0,
            'ko_sup' => $request->input('ko_sup') ?: $request->input('kode_suplier') ?: $request->input('KodeSuplier'),
            'expired_date' => $request->input('expired_date') ?: $request->input('exp_date') ?: $request->input('ExpDate'),
        ]);

        $validated = $request->validate([
            'sku' => 'nullable|string|max:50|unique:products,sku',
            'ilp_id' => 'nullable|string|max:50',
            'barcode' => 'required|string|max:50|unique:products,barcode',
            'name' => 'required|string|max:150',
            'brand' => 'nullable|string|max:100',
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
            // 19 Kolom Standar Master Produk Gudang
            'id_produk' => 'nullable|string|max:100',
            'kode_barcode' => 'nullable|string|max:100',
            'kode_produk' => 'nullable|string|max:100',
            'qty_produk' => 'nullable|numeric|min:0',
            'nama_produk' => 'nullable|string|max:255',
            'satuan_unit' => 'nullable|string|max:50',
            'harga_beli' => 'nullable|numeric|min:0',
            'harga_jual_umum' => 'nullable|numeric|min:0',
            'harga_jual_member' => 'nullable|numeric|min:0',
            'produk_disc' => 'nullable|numeric|min:0|max:100',
            'minimum_stock' => 'nullable|integer|min:0',
            'ppn_out' => 'nullable|numeric|min:0',
            'ppn_chk' => 'nullable|string|max:10',
            'harga_beli_terakhir' => 'nullable|numeric|min:0',
            'status_barang' => 'nullable|string|max:50',
            'kode_suplier' => 'nullable|string|max:100',
            'manufacturing_date' => 'nullable|date',
            'exp_date' => 'nullable|date',
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
                // 19 Kolom Standar
                'id_produk' => $validated['id_produk'] ?? $validated['ilp_id'] ?? null,
                'kode_barcode' => $validated['kode_barcode'] ?? $validated['barcode'],
                'kode_produk' => $validated['kode_produk'] ?? $sku,
                'qty_produk' => $validated['qty_produk'] ?? $initialStock,
                'nama_produk' => $validated['nama_produk'] ?? $validated['name'],
                'satuan_unit' => $validated['satuan_unit'] ?? $validated['unit'],
                'harga_beli' => $validated['harga_beli'] ?? (float) $validated['cost_price'],
                'harga_jual_umum' => $validated['harga_jual_umum'] ?? (float) $validated['price'],
                'harga_jual_member' => $validated['harga_jual_member'] ?? (!empty($validated['member_price']) ? (float) $validated['member_price'] : (float) $validated['price']),
                'produk_disc' => $validated['produk_disc'] ?? (float) ($validated['clearance_discount_percent'] ?? 0.0),
                'minimum_stock' => $validated['minimum_stock'] ?? (int) $validated['min_stock'],
                'ppn_out' => $validated['ppn_out'] ?? (float) ($validated['tax_rate'] ?? 11.0),
                'ppn_chk' => $validated['ppn_chk'] ?? (!empty($validated['is_taxable']) ? 'Y' : 'N'),
                'harga_beli_terakhir' => $validated['harga_beli_terakhir'] ?? (!empty($validated['last_cost_price']) ? (float) $validated['last_cost_price'] : (float) $validated['cost_price']),
                'status_barang' => $validated['status_barang'] ?? ($initialStock <= 0 ? 'HABIS' : ($initialStock <= (int) $validated['min_stock'] ? 'STOK_RENDAH' : 'TERSEDIA')),
                'kode_suplier' => $validated['kode_suplier'] ?? $validated['ko_sup'] ?? null,
                'manufacturing_date' => $validated['manufacturing_date'] ?? null,
                'exp_date' => $validated['exp_date'] ?? $validated['expired_date'] ?? null,

                // Legacy Attributes
                'sku' => $sku,
                'ilp_id' => $validated['ilp_id'] ?? $validated['id_produk'] ?? null,
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
            $warehouseLoc = $this->stockService->getWarehouseLocation();
            if ($initialStock > 0) {
                $targetLocId = $validated['location_id'] ?? $warehouseLoc?->id;
                if ($targetLocId) {
                    $this->stockService->addStock($product->id, $targetLocId, $initialStock, [
                        'movement_type' => 'PURCHASE_IN',
                        'unit_cost' => (float) $validated['cost_price'],
                        'expired_date' => $validated['expired_date'] ?? null,
                        'user_id' => Auth::id(),
                        'notes' => 'Stok awal pendaftaran Master Produk di Gudang Pusat',
                    ]);
                }
            } elseif ($warehouseLoc) {
                ProductStock::firstOrCreate(
                    ['product_id' => $product->id, 'location_id' => $warehouseLoc->id],
                    ['stock' => 0, 'is_active' => true, 'version' => 1]
                );
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

        $request->merge([
            'sku' => $request->input('sku') ?: $request->input('kode_produk') ?: $request->input('SKU'),
            'barcode' => $request->input('barcode') ?: $request->input('kode_barcode') ?: $request->input('Kode Barcode'),
            'name' => $request->input('name') ?: $request->input('nama_produk') ?: $request->input('NamaProduk'),
            'category' => $request->input('category') ?: ($product->category ?: 'Kebutuhan Pokok'),
            'unit' => $request->input('unit') ?: $request->input('satuan_unit') ?: $request->input('SatuanUnit') ?: 'Pcs',
            'cost_price' => $request->input('cost_price') ?? $request->input('harga_beli') ?? $request->input('HargaBeli') ?? $product->cost_price,
            'price' => $request->input('price') ?? $request->input('harga_jual_umum') ?? $request->input('HargaJualUmum') ?? $product->price,
            'member_price' => $request->input('member_price') ?? $request->input('harga_jual_member') ?? $request->input('HargaJualMember') ?? ($product->member_price ?? $product->price),
            'stock' => $request->input('stock') ?? $request->input('qty_produk') ?? $request->input('QtyProduk') ?? $product->stock,
            'min_stock' => $request->input('min_stock') ?? $request->input('minimum_stock') ?? $request->input('MinimumStock') ?? $product->min_stock,
            'tax_rate' => $request->input('tax_rate') ?? $request->input('ppn_out') ?? $request->input('PPNOut') ?? ($product->tax_rate ?? 11),
            'last_cost_price' => $request->input('last_cost_price') ?? $request->input('harga_beli_terakhir') ?? $request->input('HargaBeliTerakhir') ?? $product->last_cost_price,
            'ko_sup' => $request->input('ko_sup') ?: $request->input('kode_suplier') ?: $request->input('KodeSuplier'),
            'expired_date' => $request->input('expired_date') ?: $request->input('exp_date') ?: $request->input('ExpDate'),
        ]);

        $validated = $request->validate([
            'sku' => "nullable|string|max:50|unique:products,sku,{$id}",
            'barcode' => "required|string|max:50|unique:products,barcode,{$id}",
            'ilp_id' => 'nullable|string|max:50',
            'name' => 'required|string|max:150',
            'brand' => 'nullable|string|max:100',
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
            // 19 Kolom Standar Master Produk Gudang
            'id_produk' => 'nullable|string|max:100',
            'kode_barcode' => 'nullable|string|max:100',
            'kode_produk' => 'nullable|string|max:100',
            'qty_produk' => 'nullable|numeric|min:0',
            'nama_produk' => 'nullable|string|max:255',
            'satuan_unit' => 'nullable|string|max:50',
            'harga_beli' => 'nullable|numeric|min:0',
            'harga_jual_umum' => 'nullable|numeric|min:0',
            'harga_jual_member' => 'nullable|numeric|min:0',
            'produk_disc' => 'nullable|numeric|min:0|max:100',
            'minimum_stock' => 'nullable|integer|min:0',
            'ppn_out' => 'nullable|numeric|min:0',
            'ppn_chk' => 'nullable|string|max:10',
            'harga_beli_terakhir' => 'nullable|numeric|min:0',
            'status_barang' => 'nullable|string|max:50',
            'kode_suplier' => 'nullable|string|max:100',
            'manufacturing_date' => 'nullable|date',
            'exp_date' => 'nullable|date',
        ]);

        $validated['status'] = (int) $validated['stock'] <= 0 ? 'HABIS' : ((int) $validated['stock'] <= (int) $validated['min_stock'] ? 'STOK_RENDAH' : 'TERSEDIA');

        // Explicitly set 19 columns if not already in validated
        if (!isset($validated['id_produk']) && !empty($validated['ilp_id'])) $validated['id_produk'] = $validated['ilp_id'];
        if (!isset($validated['kode_barcode']) && !empty($validated['barcode'])) $validated['kode_barcode'] = $validated['barcode'];
        if (!isset($validated['kode_produk']) && !empty($validated['sku'])) $validated['kode_produk'] = $validated['sku'];
        if (!isset($validated['qty_produk']) && isset($validated['stock'])) $validated['qty_produk'] = $validated['stock'];
        if (!isset($validated['nama_produk']) && !empty($validated['name'])) $validated['nama_produk'] = $validated['name'];
        if (!isset($validated['satuan_unit']) && !empty($validated['unit'])) $validated['satuan_unit'] = $validated['unit'];
        if (!isset($validated['harga_beli']) && isset($validated['cost_price'])) $validated['harga_beli'] = $validated['cost_price'];
        if (!isset($validated['harga_jual_umum']) && isset($validated['price'])) $validated['harga_jual_umum'] = $validated['price'];
        if (!isset($validated['harga_jual_member']) && isset($validated['member_price'])) $validated['harga_jual_member'] = $validated['member_price'];
        if (!isset($validated['minimum_stock']) && isset($validated['min_stock'])) $validated['minimum_stock'] = $validated['min_stock'];
        if (!isset($validated['status_barang']) && isset($validated['status'])) $validated['status_barang'] = $validated['status'];
        if (!isset($validated['kode_suplier']) && !empty($validated['ko_sup'])) $validated['kode_suplier'] = $validated['ko_sup'];
        if (!isset($validated['exp_date']) && !empty($validated['expired_date'])) $validated['exp_date'] = $validated['expired_date'];

        $product->update($validated);

        if (isset($validated['stock'])) {
            $wh = Location::where('type', 'WAREHOUSE')->first();
            if ($wh) {
                ProductStock::updateOrCreate(
                    ['product_id' => $product->id, 'location_id' => $wh->id],
                    ['stock' => (int) $validated['stock'], 'is_active' => true]
                );
            }
        }

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

        DB::transaction(function () use ($product, $id) {
            // Nullify references in historical tables to prevent foreign key errors and preserve history
            DB::table('transaction_items')->where('product_id', $id)->update(['product_id' => null]);
            DB::table('stock_opname_items')->where('product_id', $id)->update(['product_id' => null]);
            DB::table('sales_return_items')->where('product_id', $id)->update(['product_id' => null]);
            DB::table('coupon_units')->where('product_id', $id)->update(['product_id' => null]);

            // Clean up child dependencies safely
            $product->units()->delete();
            $product->productStocks()->delete();
            $product->supplierProducts()->delete();
            $product->stockBatches()->delete();
            $product->stockMovements()->delete();
            $product->priceHistories()->delete();

            $product->delete();
        });

        \Illuminate\Support\Facades\Cache::forget('products_kpi_summary_' . Carbon::today()->toDateString());

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

        $branchStock = [];
        $allocatedBranches = [];
        $stocks = $p->relationLoaded('productStocks') ? $p->productStocks : $p->productStocks()->with('location')->get();
        foreach ($stocks as $ps) {
            if ($ps->location && $ps->is_active !== false) {
                if ($ps->location->type !== 'WAREHOUSE' && (int) $ps->stock <= 0 && $ps->first_received_at === null) {
                    continue;
                }
                $branchStock[$ps->location->code] = (int) $ps->stock;
                $allocatedBranches[] = $ps->location->code;
            }
        }

        $locStock = $locationId ? $p->stockAtLocation($locationId) : null;
        $whStockFound = $branchStock['GDG-001'] ?? null;
        if ($whStockFound === null) {
            foreach ($stocks as $ps) {
                if ($ps->location && $ps->location->type === 'WAREHOUSE') {
                    $whStockFound = (int) $ps->stock;
                    break;
                }
            }
        }
        $warehouseStock = (int) ($whStockFound ?? $p->stock);
        $effectiveStock = $locationId ? (int) ($locStock?->stock ?? 0) : $warehouseStock;
        $effectiveMinStock = ($locStock && $locStock->min_stock !== null) ? (int) $locStock->min_stock : (int) $p->min_stock;
        $stockStatus = $effectiveStock <= 0
            ? 'HABIS'
            : ($effectiveStock <= $effectiveMinStock ? 'STOK_RENDAH' : 'TERSEDIA');

        return [
            'id' => $p->id,
            'ilp_id' => $p->ilp_id ?: (string) $p->id,
            'sku' => $p->sku,
            'barcode' => $p->barcode,
            'name' => $p->name,
            // 19 Kolom Standar Master Produk ILP
            'id_produk' => $p->id_produk ?: ($p->ilp_id ?: (string) $p->id),
            'kode_barcode' => $p->kode_barcode ?: $p->barcode,
            'kode_produk' => $p->kode_produk ?: $p->sku,
            'qty_produk' => $p->qty_produk !== null ? (int) $p->qty_produk : (int) $p->stock,
            'nama_produk' => $p->nama_produk ?: $p->name,
            'satuan_unit' => $p->satuan_unit ?: ($p->unit ?: 'Pcs'),
            'harga_beli' => (float) ($p->harga_beli !== null ? $p->harga_beli : $p->cost_price),
            'harga_jual_umum' => (float) ($p->harga_jual_umum !== null ? $p->harga_jual_umum : $p->price),
            'harga_jual_member' => (float) ($p->harga_jual_member !== null ? $p->harga_jual_member : ($p->member_price ?? $p->price)),
            'produk_disc' => (float) ($p->produk_disc !== null ? $p->produk_disc : ($p->discount_percent ?? 0)),
            'minimum_stock' => (int) ($p->minimum_stock !== null ? $p->minimum_stock : ($p->min_stock ?? 5)),
            'ppn_out' => (float) ($p->ppn_out !== null ? $p->ppn_out : ($p->tax_rate ?? 11)),
            'ppn_chk' => $p->ppn_chk ?: ($p->is_taxable ? 'Y' : 'N'),
            'harga_beli_terakhir' => (float) ($p->harga_beli_terakhir !== null ? $p->harga_beli_terakhir : ($p->last_cost_price ?? $p->cost_price)),
            'status_barang' => $p->status_barang ?: ($p->status ?? 'TERSEDIA'),
            'kode_suplier' => $p->kode_suplier ?: ($p->ko_sup ?: ($p->supplier?->code ?? '-')),
            'manufacturing_date' => $p->manufacturing_date ? (\Carbon\Carbon::parse($p->manufacturing_date)->format('Y-m-d')) : null,
            'exp_date' => $p->exp_date ? (\Carbon\Carbon::parse($p->exp_date)->format('Y-m-d')) : ($p->expired_date ? $p->expired_date->toDateString() : null),
            'brand' => $p->brand ?: ($p->supplier?->name ?: 'ILP'),
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
            // Location-scoped Stock (Stock Toko or Master Gudang stock)
            'stock' => $effectiveStock,
            'master_stock' => (int) $p->stock,
            'warehouse_stock' => $warehouseStock,
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
            'allocated_branches' => array_values(array_unique($allocatedBranches)),
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
            // Konsinyasi UMKM & Jenis Produk (Umum, UMKM, Kupon)
            'is_consignment' => (bool) $p->is_consignment,
            'is_coupon' => (bool) (!empty($p->category) && (str_contains(strtolower($p->category), 'kupon') || str_contains(strtolower($p->category), 'voucher'))),
            'product_type' => (!empty($p->category) && (str_contains(strtolower($p->category), 'kupon') || str_contains(strtolower($p->category), 'voucher'))) ? 'KUPON' : ($p->is_consignment ? 'UMKM' : 'UMUM'),
            'product_type_label' => (!empty($p->category) && (str_contains(strtolower($p->category), 'kupon') || str_contains(strtolower($p->category), 'voucher'))) ? 'Kupon / Voucher' : ($p->is_consignment ? 'Produk UMKM' : 'Produk Umum'),
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
