<?php

namespace App\Http\Controllers\Api\Manager;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\GoodsReceiptItem;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\PurchasePriceHistory;
use App\Models\StockBatch;
use App\Models\Supplier;
use App\Models\SupplierProduct;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class SupplierController extends Controller
{
    public const DEFAULT_BUSINESS_CATEGORIES = [
        'Distributor Makanan',
        'Distributor Minuman',
        'Distributor Sembako',
        'Distributor Perlengkapan Kebersihan',
        'Distributor Peralatan',
        'Supplier Umum',
    ];

    /**
     * Metadata for Supplier form (next auto-generated code & suggested business categories).
     */
    public function meta(): JsonResponse
    {
        $existingCategories = Supplier::regular()
            ->get(['category', 'business_categories'])
            ->flatMap(fn (Supplier $s) => $s->business_categories_list)
            ->merge(self::DEFAULT_BUSINESS_CATEGORIES)
            ->map(fn ($v) => trim((string) $v))
            ->filter(fn ($v) => $v !== '')
            ->unique(fn ($v) => mb_strtolower($v))
            ->values()
            ->all();

        return response()->json([
            'success' => true,
            'data' => [
                'next_code' => $this->generateSupplierCode(),
                'suggested_categories' => $existingCategories,
            ],
        ]);
    }

    /**
     * List regular suppliers with summary cards, search, status/category filter, sorting, and pagination.
     */
    public function index(Request $request): JsonResponse
    {
        $search = trim((string) $request->query('search', ''));
        $status = strtoupper(trim((string) $request->query('status', 'all')));
        $category = trim((string) $request->query('category', 'all'));
        $sort = trim((string) $request->query('sort', 'name_asc'));
        $perPage = max(5, min(100, (int) $request->query('per_page', 10)));

        $query = Supplier::regular()
            ->withCount('supplierProducts')
            ->with(['supplierProducts.product:id,category']);

        if ($search !== '') {
            $lower = mb_strtolower($search);
            $query->where(function ($sub) use ($lower) {
                $sub->whereRaw('LOWER(code) LIKE ?', ["%{$lower}%"])
                    ->orWhereRaw('LOWER(name) LIKE ?', ["%{$lower}%"])
                    ->orWhereRaw("LOWER(COALESCE(company_name, '')) LIKE ?", ["%{$lower}%"])
                    ->orWhereRaw("LOWER(COALESCE(pic_name, owner_name, '')) LIKE ?", ["%{$lower}%"])
                    ->orWhereRaw("LOWER(COALESCE(phone, '')) LIKE ?", ["%{$lower}%"])
                    ->orWhereRaw("LOWER(COALESCE(city, '')) LIKE ?", ["%{$lower}%"])
                    ->orWhereRaw("LOWER(COALESCE(address, '')) LIKE ?", ["%{$lower}%"])
                    ->orWhereRaw("LOWER(COALESCE(category, '')) LIKE ?", ["%{$lower}%"]);
            });
        }

        if (in_array($status, ['AKTIF', 'NONAKTIF'], true)) {
            $query->where('status', $status);
        }

        if ($category !== '' && strcasecmp($category, 'all') !== 0) {
            $lowerCat = mb_strtolower($category);
            $query->where(function ($sub) use ($lowerCat, $category) {
                $sub->whereRaw("LOWER(COALESCE(category, '')) LIKE ?", ["%{$lowerCat}%"])
                    ->orWhereJsonContains('business_categories', $category);
            });
        }

        match ($sort) {
            'name_desc' => $query->orderBy('name', 'desc'),
            'code_asc' => $query->orderBy('code', 'asc'),
            'code_desc' => $query->orderBy('code', 'desc'),
            'products_desc' => $query->orderBy('supplier_products_count', 'desc')->orderBy('name', 'asc'),
            'products_asc' => $query->orderBy('supplier_products_count', 'asc')->orderBy('name', 'asc'),
            'newest' => $query->orderByDesc('id'),
            default => $query->orderBy('name', 'asc'),
        };

        $paginated = $query->paginate($perPage);

        // Preload purchase stats for visible page suppliers
        $pageSupplierIds = collect($paginated->items())->pluck('id')->all();
        $purchaseStats = collect();
        if (!empty($pageSupplierIds)) {
            $purchaseStats = Purchase::whereIn('supplier_id', $pageSupplierIds)
                ->whereNotIn('status', [Purchase::STATUS_DIBATALKAN, 'CANCELLED'])
                ->selectRaw('supplier_id, COUNT(*) as purchases_count, COALESCE(SUM(total_amount), 0) as total_value, MAX(purchase_date) as last_purchase_date')
                ->groupBy('supplier_id')
                ->get()
                ->keyBy('supplier_id');
        }

        // Summary KPI cards across all regular suppliers
        $totalSuppliers = Supplier::regular()->count();
        $activeSuppliers = Supplier::regular()->where('status', 'AKTIF')->count();
        $inactiveSuppliers = Supplier::regular()->where('status', 'NONAKTIF')->count();
        $regularSupplierIdsSub = Supplier::regular()->select('id');
        $totalSuppliedProducts = (int) SupplierProduct::whereIn('supplier_id', $regularSupplierIdsSub)
            ->distinct('product_id')
            ->count('product_id');

        $availableCategories = Supplier::regular()
            ->get(['category', 'business_categories'])
            ->flatMap(fn (Supplier $s) => $s->business_categories_list)
            ->merge(self::DEFAULT_BUSINESS_CATEGORIES)
            ->map(fn ($v) => trim((string) $v))
            ->filter(fn ($v) => $v !== '')
            ->unique(fn ($v) => mb_strtolower($v))
            ->sort()
            ->values()
            ->all();

        $rows = collect($paginated->items())->map(function (Supplier $s) use ($purchaseStats) {
            $stat = $purchaseStats->get($s->id);
            $suppliedCategories = $s->supplierProducts
                ->map(fn (SupplierProduct $sp) => $sp->product?->category)
                ->filter(fn ($c) => !empty($c))
                ->unique()
                ->values()
                ->all();

            $jenisBarang = !empty($suppliedCategories)
                ? implode(', ', array_slice($suppliedCategories, 0, 3)) . (count($suppliedCategories) > 3 ? ' +' . (count($suppliedCategories) - 3) : '')
                : $s->category_summary;

            return [
                'id' => $s->id,
                'code' => $s->code,
                'name' => $s->name,
                'company_name' => $s->company_name,
                'pic_name' => $s->effective_pic,
                'phone' => $s->phone,
                'email' => $s->email,
                'address' => $s->address,
                'city' => $s->city,
                'category' => $s->category_summary,
                'business_categories' => $s->business_categories_list,
                'supplied_categories' => $suppliedCategories,
                'jenis_barang_summary' => $jenisBarang,
                'total_products' => (int) ($s->supplier_products_count ?? 0),
                'purchases_count' => (int) ($stat?->purchases_count ?? 0),
                'total_purchase_value' => (float) ($stat?->total_value ?? 0),
                'last_purchase_date' => $stat?->last_purchase_date,
                'status' => strtoupper((string) $s->status),
                'notes' => $s->notes,
                'created_at' => $s->created_at?->toIso8601String(),
            ];
        })->values();

        return response()->json([
            'success' => true,
            'summary' => [
                'total_suppliers' => $totalSuppliers,
                'active_suppliers' => $activeSuppliers,
                'inactive_suppliers' => $inactiveSuppliers,
                'total_supplied_products' => $totalSuppliedProducts,
            ],
            'available_categories' => $availableCategories,
            'data' => $rows,
            'meta' => [
                'current_page' => $paginated->currentPage(),
                'last_page' => $paginated->lastPage(),
                'per_page' => $paginated->perPage(),
                'total' => $paginated->total(),
            ],
        ]);
    }

    /**
     * Store a new regular Supplier (Manager Gudang / Supervisor only).
     */
    public function store(Request $request): JsonResponse
    {
        if ($denied = $this->ensureManagerWriteAccess()) {
            return $denied;
        }

        $validated = $this->validateSupplierPayload($request);
        $cleanName = trim((string) preg_replace('/\s+/', ' ', $validated['name']));
        if ($cleanName === '') {
            return response()->json([
                'success' => false,
                'message' => 'Nama supplier wajib diisi.',
                'errors' => ['name' => ['Nama supplier wajib diisi.']],
            ], 422);
        }

        $lockKey = 'supplier_create:' . hash('sha256', mb_strtolower($cleanName));

        $result = Cache::lock($lockKey, 10)->block(5, function () use ($validated, $cleanName) {
            return DB::transaction(function () use ($validated, $cleanName) {
                $existing = Supplier::whereRaw('LOWER(TRIM(name)) = ?', [mb_strtolower($cleanName)])
                    ->lockForUpdate()
                    ->first();

                if ($existing) {
                    return ['duplicate' => true, 'supplier' => $existing];
                }

                $code = $this->generateSupplierCode();
                $categories = $this->normalizeCategories($validated);
                $categorySummary = !empty($categories) ? implode(', ', $categories) : 'Supplier Umum';
                $pic = $validated['pic_name'] ?? ($validated['owner_name'] ?? null);

                $supplier = Supplier::create([
                    'code' => $code,
                    'name' => $cleanName,
                    'company_name' => $validated['company_name'] ?? null,
                    'owner_name' => $pic,
                    'pic_name' => $pic,
                    'phone' => $validated['phone'] ?? null,
                    'email' => $validated['email'] ?? null,
                    'address' => $validated['address'] ?? null,
                    'city' => $validated['city'] ?? null,
                    'category' => mb_substr($categorySummary, 0, 250),
                    'business_categories' => $categories,
                    'commission_percent' => 0,
                    'settlement_period' => 'BULANAN',
                    'status' => $validated['status'] ?? 'AKTIF',
                    'supplier_type' => Supplier::TYPE_REGULAR,
                    'notes' => $validated['notes'] ?? null,
                ]);

                return ['duplicate' => false, 'supplier' => $supplier];
            });
        });

        if ($result['duplicate']) {
            $existing = $result['supplier'];
            return response()->json([
                'success' => false,
                'message' => "Supplier dengan nama [{$existing->name}] sudah terdaftar ({$existing->code}).",
                'errors' => [
                    'name' => ["Supplier dengan nama [{$existing->name}] sudah terdaftar ({$existing->code})."],
                ],
                'data' => $existing,
            ], 422);
        }

        /** @var Supplier $supplier */
        $supplier = $result['supplier'];
        $this->recordAudit('CREATE_SUPPLIER', $supplier->code, "Menambahkan supplier baru [{$supplier->name}] ({$supplier->code}).");

        return response()->json([
            'success' => true,
            'message' => "Supplier [{$supplier->name}] berhasil ditambahkan.",
            'data' => $this->formatSupplierDetail($supplier),
        ], 201);
    }

    /**
     * Show detailed supplier profile & summary metrics.
     */
    public function show(int $id): JsonResponse
    {
        $supplier = Supplier::regular()->find($id);
        if (!$supplier) {
            return response()->json([
                'success' => false,
                'message' => 'Data supplier tidak ditemukan.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $this->formatSupplierDetail($supplier),
        ]);
    }

    /**
     * Update supplier details (Manager Gudang / Supervisor only).
     */
    public function update(Request $request, int $id): JsonResponse
    {
        if ($denied = $this->ensureManagerWriteAccess()) {
            return $denied;
        }

        $supplier = Supplier::regular()->find($id);
        if (!$supplier) {
            return response()->json([
                'success' => false,
                'message' => 'Data supplier tidak ditemukan.',
            ], 404);
        }

        $validated = $this->validateSupplierPayload($request);
        $cleanName = trim((string) preg_replace('/\s+/', ' ', $validated['name']));
        if ($cleanName === '') {
            return response()->json([
                'success' => false,
                'message' => 'Nama supplier wajib diisi.',
                'errors' => ['name' => ['Nama supplier wajib diisi.']],
            ], 422);
        }

        $duplicate = Supplier::where('id', '!=', $supplier->id)
            ->whereRaw('LOWER(TRIM(name)) = ?', [mb_strtolower($cleanName)])
            ->first();

        if ($duplicate) {
            return response()->json([
                'success' => false,
                'message' => "Nama supplier [{$cleanName}] sudah digunakan oleh {$duplicate->code}.",
                'errors' => ['name' => ["Nama supplier [{$cleanName}] sudah digunakan oleh {$duplicate->code}."]],
            ], 422);
        }

        $categories = $this->normalizeCategories($validated);
        $categorySummary = !empty($categories) ? implode(', ', $categories) : 'Supplier Umum';
        $pic = $validated['pic_name'] ?? ($validated['owner_name'] ?? $supplier->effective_pic);

        $supplier->update([
            'name' => $cleanName,
            'company_name' => $validated['company_name'] ?? null,
            'owner_name' => $pic,
            'pic_name' => $pic,
            'phone' => $validated['phone'] ?? null,
            'email' => $validated['email'] ?? null,
            'address' => $validated['address'] ?? null,
            'city' => $validated['city'] ?? null,
            'category' => mb_substr($categorySummary, 0, 250),
            'business_categories' => $categories,
            'status' => $validated['status'] ?? $supplier->status,
            'notes' => $validated['notes'] ?? null,
        ]);

        $this->recordAudit('UPDATE_SUPPLIER', $supplier->code, "Memperbarui data supplier [{$supplier->name}] ({$supplier->code}).");

        return response()->json([
            'success' => true,
            'message' => "Data supplier [{$supplier->name}] berhasil diperbarui.",
            'data' => $this->formatSupplierDetail($supplier->fresh()),
        ]);
    }

    /**
     * Activate / Deactivate a supplier while preserving all transactions and supplied product relations.
     */
    public function toggleStatus(Request $request, int $id): JsonResponse
    {
        if ($denied = $this->ensureManagerWriteAccess()) {
            return $denied;
        }

        $supplier = Supplier::regular()->find($id);
        if (!$supplier) {
            return response()->json(['success' => false, 'message' => 'Data supplier tidak ditemukan.'], 404);
        }

        $targetStatus = strtoupper(trim((string) $request->input('status', '')));
        if (!in_array($targetStatus, ['AKTIF', 'NONAKTIF'], true)) {
            $targetStatus = strtoupper((string) $supplier->status) === 'AKTIF' ? 'NONAKTIF' : 'AKTIF';
        }

        $supplier->status = $targetStatus;
        $supplier->save();

        $actionLabel = $targetStatus === 'AKTIF' ? 'diaktifkan' : 'dinonaktifkan';
        $this->recordAudit('TOGGLE_SUPPLIER_STATUS', $supplier->code, "Supplier [{$supplier->name}] {$actionLabel}.");

        return response()->json([
            'success' => true,
            'message' => "Supplier [{$supplier->name}] berhasil {$actionLabel}.",
            'data' => $this->formatSupplierDetail($supplier),
        ]);
    }

    /**
     * Delete a regular supplier safely.
     * All products previously linked, warehouse physical stocks, purchase orders,
     * and goods receipts remain completely intact (their supplier_id becomes null).
     */
    public function destroy(int $id): JsonResponse
    {
        if ($denied = $this->ensureManagerWriteAccess()) {
            return $denied;
        }

        $supplier = Supplier::regular()->find($id);
        if (!$supplier) {
            return response()->json(['success' => false, 'message' => 'Data supplier tidak ditemukan.'], 404);
        }

        $name = $supplier->name;
        $code = $supplier->code;

        DB::transaction(function () use ($supplier) {
            // 1. Ensure no products are deleted: set supplier_id to NULL on products
            Product::where('supplier_id', $supplier->id)->update(['supplier_id' => null]);

            // 2. Preserve purchases: record original supplier in notes and set supplier_id to NULL
            foreach (Purchase::where('supplier_id', $supplier->id)->get() as $p) {
                $noteAppend = "[Supplier Asal: {$supplier->name} ({$supplier->code})]";
                $p->update([
                    'supplier_id' => null,
                    'notes' => $p->notes ? ($p->notes . ' | ' . $noteAppend) : $noteAppend,
                ]);
            }

            // 3. Preserve batches and price histories: set supplier_id to NULL
            StockBatch::where('supplier_id', $supplier->id)->update(['supplier_id' => null]);
            PurchasePriceHistory::where('supplier_id', $supplier->id)->update(['supplier_id' => null]);

            // 4. Detach supplier-product mapping
            SupplierProduct::where('supplier_id', $supplier->id)->delete();

            // 5. Delete the supplier itself
            $supplier->delete();
        });

        $this->recordAudit('DELETE_SUPPLIER', $code, "Menghapus master supplier [{$name}] ({$code}). Seluruh data barang di gudang dan riwayat transaksi tetap tersimpan aman.");

        return response()->json([
            'success' => true,
            'message' => "Supplier [{$name}] ({$code}) berhasil dihapus. Data produk dan stok gudang tetap tersimpan aman.",
        ]);
    }

    /**
     * Tab B: Products automatically linked as supplied by this supplier (from confirmed receipts).
     */
    public function suppliedProducts(Request $request, int $id): JsonResponse
    {
        $supplier = Supplier::regular()->find($id);
        if (!$supplier) {
            return response()->json(['success' => false, 'message' => 'Data supplier tidak ditemukan.'], 404);
        }

        $search = trim((string) $request->query('search', ''));
        $category = trim((string) $request->query('category', 'all'));
        $supplyStatus = strtoupper(trim((string) $request->query('supply_status', 'all')));
        $perPage = max(5, min(100, (int) $request->query('per_page', 15)));

        $query = SupplierProduct::with(['product', 'lastPurchase'])
            ->where('supplier_id', $supplier->id);

        if ($search !== '') {
            $lower = mb_strtolower($search);
            $query->whereHas('product', function ($pq) use ($lower) {
                $pq->whereRaw('LOWER(name) LIKE ?', ["%{$lower}%"])
                    ->orWhereRaw('LOWER(sku) LIKE ?', ["%{$lower}%"])
                    ->orWhereRaw("LOWER(COALESCE(barcode, '')) LIKE ?", ["%{$lower}%"]);
            });
        }

        if ($category !== '' && strcasecmp($category, 'all') !== 0) {
            $query->whereHas('product', fn ($pq) => $pq->where('category', $category));
        }

        if (in_array($supplyStatus, [SupplierProduct::STATUS_AKTIF, SupplierProduct::STATUS_TIDAK_AKTIF], true)) {
            $query->where('supply_status', $supplyStatus);
        }

        $paginated = $query->orderByDesc('last_purchase_at')->orderByDesc('id')->paginate($perPage);

        $categories = Product::whereIn(
            'id',
            SupplierProduct::where('supplier_id', $supplier->id)->select('product_id')
        )
            ->whereNotNull('category')
            ->distinct()
            ->orderBy('category')
            ->pluck('category')
            ->values()
            ->all();

        $rows = collect($paginated->items())->map(function (SupplierProduct $sp) {
            $prod = $sp->product;
            return [
                'id' => $sp->id,
                'supplier_id' => $sp->supplier_id,
                'product_id' => $sp->product_id,
                'product_name' => $prod?->name ?? 'Produk',
                'sku' => $prod?->sku ?? '-',
                'barcode' => $prod?->barcode ?? '-',
                'category' => $prod?->category ?? '-',
                'unit' => $prod?->unit ?? 'Pcs',
                'last_purchase_price' => (float) $sp->last_purchase_price,
                'global_last_cost_price' => $prod ? (float) ($prod->last_cost_price ?? $prod->cost_price) : 0.0,
                'first_purchase_at' => $sp->first_purchase_at?->toIso8601String(),
                'last_purchase_at' => $sp->last_purchase_at?->toIso8601String(),
                'last_purchase_date' => $sp->last_purchase_at?->format('Y-m-d'),
                'last_purchase_at_label' => $sp->last_purchase_at?->translatedFormat('d M Y • H:i'),
                'last_purchase_number' => $sp->lastPurchase?->purchase_number ?? '-',
                'total_transactions' => (int) $sp->total_transactions,
                'supply_status' => strtoupper((string) $sp->supply_status),
            ];
        })->values();

        return response()->json([
            'success' => true,
            'categories' => $categories,
            'data' => $rows,
            'meta' => [
                'current_page' => $paginated->currentPage(),
                'last_page' => $paginated->lastPage(),
                'per_page' => $paginated->perPage(),
                'total' => $paginated->total(),
            ],
        ]);
    }

    /**
     * Toggle supply_status (AKTIF / TIDAK_AKTIF) on a supplier-product relation.
     */
    public function toggleSupplyStatus(Request $request, int $id, int $productId): JsonResponse
    {
        if ($denied = $this->ensureManagerWriteAccess()) {
            return $denied;
        }

        $relation = SupplierProduct::with(['supplier', 'product'])
            ->where('supplier_id', $id)
            ->where('product_id', $productId)
            ->first();

        if (!$relation) {
            return response()->json([
                'success' => false,
                'message' => 'Relasi produk supplier tidak ditemukan.',
            ], 404);
        }

        $target = strtoupper(trim((string) $request->input('supply_status', '')));
        if (!in_array($target, [SupplierProduct::STATUS_AKTIF, SupplierProduct::STATUS_TIDAK_AKTIF], true)) {
            $target = $relation->supply_status === SupplierProduct::STATUS_AKTIF
                ? SupplierProduct::STATUS_TIDAK_AKTIF
                : SupplierProduct::STATUS_AKTIF;
        }

        $relation->supply_status = $target;
        $relation->save();

        return response()->json([
            'success' => true,
            'message' => "Status pasokan [{$relation->product?->name}] diperbarui menjadi " . ($target === 'AKTIF' ? 'Aktif Dipasok' : 'Tidak Aktif Dipasok') . '.',
            'data' => [
                'product_id' => $relation->product_id,
                'supply_status' => $relation->supply_status,
            ],
        ]);
    }

    /**
     * Tab C: Purchase transaction history for this supplier.
     */
    public function purchaseHistory(Request $request, int $id): JsonResponse
    {
        $supplier = Supplier::regular()->find($id);
        if (!$supplier) {
            return response()->json(['success' => false, 'message' => 'Data supplier tidak ditemukan.'], 404);
        }

        $search = trim((string) $request->query('search', ''));
        $status = strtoupper(trim((string) $request->query('status', 'all')));
        $dateFrom = $request->query('date_from');
        $dateTo = $request->query('date_to');
        $perPage = max(5, min(100, (int) $request->query('per_page', 15)));

        $query = Purchase::with(['items', 'goodsReceipts'])
            ->where('supplier_id', $supplier->id);

        if ($search !== '') {
            $lower = mb_strtolower($search);
            $query->where(function ($sub) use ($lower) {
                $sub->whereRaw('LOWER(purchase_number) LIKE ?', ["%{$lower}%"])
                    ->orWhereRaw("LOWER(COALESCE(supplier_invoice_number, '')) LIKE ?", ["%{$lower}%"]);
            });
        }

        if ($status !== '' && $status !== 'ALL') {
            if ($status === Purchase::STATUS_MENUNGGU) {
                $query->whereIn('status', [Purchase::STATUS_MENUNGGU, 'ORDERED', 'PENDING']);
            } elseif ($status === Purchase::STATUS_SELESAI) {
                $query->whereIn('status', [Purchase::STATUS_SELESAI, 'RECEIVED', 'COMPLETED']);
            } elseif ($status === Purchase::STATUS_DIBATALKAN) {
                $query->whereIn('status', [Purchase::STATUS_DIBATALKAN, 'CANCELLED']);
            } else {
                $query->where('status', $status);
            }
        }

        if ($dateFrom) {
            $query->whereDate('purchase_date', '>=', $dateFrom);
        }
        if ($dateTo) {
            $query->whereDate('purchase_date', '<=', $dateTo);
        }

        $paginated = $query->orderByDesc('purchase_date')->orderByDesc('id')->paginate($perPage);

        $rows = collect($paginated->items())->map(function (Purchase $p) {
            $totalItems = $p->items->count();
            $totalQty = round((float) $p->items->sum('qty'), 2);
            $totalReceivedQty = round((float) $p->items->sum('received_qty'), 2);

            return [
                'id' => $p->id,
                'purchase_number' => $p->purchase_number,
                'purchase_date' => $p->purchase_date?->toDateString(),
                'purchase_date_label' => $p->purchase_date?->translatedFormat('d M Y'),
                'supplier_invoice_number' => $p->supplier_invoice_number,
                'total_items' => $totalItems,
                'total_qty' => $totalQty,
                'total_received_qty' => $totalReceivedQty,
                'total_amount' => (float) $p->total_amount,
                'status' => $p->normalized_status,
                'receipts_count' => $p->goodsReceipts->count(),
            ];
        })->values();

        return response()->json([
            'success' => true,
            'data' => $rows,
            'meta' => [
                'current_page' => $paginated->currentPage(),
                'last_page' => $paginated->lastPage(),
                'per_page' => $paginated->perPage(),
                'total' => $paginated->total(),
            ],
        ]);
    }

    /**
     * Tab D: Purchase price change history for this supplier.
     */
    public function priceHistory(Request $request, int $id): JsonResponse
    {
        $supplier = Supplier::regular()->find($id);
        if (!$supplier) {
            return response()->json(['success' => false, 'message' => 'Data supplier tidak ditemukan.'], 404);
        }

        $productId = $request->query('product_id', 'all');
        $dateFrom = $request->query('date_from');
        $dateTo = $request->query('date_to');
        $changeType = strtoupper(trim((string) $request->query('change_type', 'all')));
        $perPage = max(5, min(100, (int) $request->query('per_page', 15)));

        $query = PurchasePriceHistory::with(['product', 'purchase'])
            ->where('supplier_id', $supplier->id);

        if ($productId && $productId !== 'all') {
            $query->where('product_id', (int) $productId);
        }

        if ($dateFrom) {
            $query->whereDate('recorded_at', '>=', $dateFrom);
        }
        if ($dateTo) {
            $query->whereDate('recorded_at', '<=', $dateTo);
        }

        if (in_array($changeType, ['NAIK', 'TURUN', 'TETAP'], true)) {
            $query->where('change_type', $changeType);
        }

        $paginated = (clone $query)->orderByDesc('recorded_at')->orderByDesc('id')->paginate($perPage);

        $chartRows = (clone $query)->orderBy('recorded_at', 'asc')->orderBy('id', 'asc')->limit(30)->get();
        $chartSeries = $chartRows->map(fn (PurchasePriceHistory $h) => [
            'product_id' => $h->product_id,
            'label' => $h->recorded_at?->format('d/m'),
            'full_date' => $h->recorded_at?->translatedFormat('d M Y'),
            'product_name' => $h->product?->name ?? 'Produk',
            'supplier_name' => $supplier->name,
            'price' => (float) $h->new_price,
            'previous_price' => (float) $h->previous_price,
            'change_type' => $h->change_type,
        ])->values();

        $distinctProducts = Product::whereIn(
            'id',
            PurchasePriceHistory::where('supplier_id', $supplier->id)->select('product_id')
        )
            ->orderBy('name')
            ->get(['id', 'sku', 'name'])
            ->map(fn (Product $p) => [
                'id' => $p->id,
                'sku' => $p->sku,
                'name' => $p->name,
            ])
            ->values();

        $allForSummary = PurchasePriceHistory::where('supplier_id', $supplier->id);
        $summary = [
            'total_records' => (clone $allForSummary)->count(),
            'increase_count' => (clone $allForSummary)->where('change_type', 'NAIK')->count(),
            'decrease_count' => (clone $allForSummary)->where('change_type', 'TURUN')->count(),
            'stable_count' => (clone $allForSummary)->where('change_type', 'TETAP')->count(),
        ];

        $rows = collect($paginated->items())->map(fn (PurchasePriceHistory $h) => [
            'id' => $h->id,
            'recorded_at' => $h->recorded_at?->toIso8601String(),
            'recorded_date' => $h->recorded_at?->format('Y-m-d'),
            'recorded_date_label' => $h->recorded_at?->translatedFormat('d M Y'),
            'product_id' => $h->product_id,
            'product_name' => $h->product?->name ?? 'Produk',
            'sku' => $h->product?->sku ?? '-',
            'unit' => $h->unit ?: ($h->product?->unit ?? 'Pcs'),
            'previous_price' => (float) $h->previous_price,
            'new_price' => (float) $h->new_price,
            'price_diff' => (float) $h->price_diff,
            'change_percent' => (float) $h->change_percent,
            'change_type' => $h->change_type,
            'purchase_id' => $h->purchase_id,
            'purchase_number' => $h->purchase_number ?: ($h->purchase?->purchase_number ?? '-'),
            'notes' => $h->notes,
        ])->values();

        return response()->json([
            'success' => true,
            'summary' => $summary,
            'products' => $distinctProducts,
            'chart_series' => $chartSeries,
            'data' => $rows,
            'meta' => [
                'current_page' => $paginated->currentPage(),
                'last_page' => $paginated->lastPage(),
                'per_page' => $paginated->perPage(),
                'total' => $paginated->total(),
            ],
        ]);
    }

    protected function formatSupplierDetail(Supplier $s): array
    {
        $nonCancelledPurchases = Purchase::where('supplier_id', $s->id)
            ->whereNotIn('status', [Purchase::STATUS_DIBATALKAN, 'CANCELLED']);

        $totalPurchases = (clone $nonCancelledPurchases)->count();
        $totalPurchaseValue = (float) ((clone $nonCancelledPurchases)->sum('total_amount') ?? 0);

        $lastReceiptDate = DB::table('goods_receipt_items as gri')
            ->join('goods_receipts as gr', 'gr.id', '=', 'gri.goods_receipt_id')
            ->join('purchases as p', 'p.id', '=', 'gr.purchase_id')
            ->where('p.supplier_id', $s->id)
            ->where('gri.qty_received', '>', 0)
            ->whereNotIn('p.status', [Purchase::STATUS_DIBATALKAN, 'CANCELLED'])
            ->max('gr.received_at');

        $lastPurchaseDate = $lastReceiptDate ?: (clone $nonCancelledPurchases)->max('purchase_date');
        $lastPurchaseLabel = $lastPurchaseDate
            ? Carbon::parse($lastPurchaseDate)->translatedFormat('d M Y')
            : '-';

        $totalProducts = (int) SupplierProduct::where('supplier_id', $s->id)->count();

        return [
            'id' => $s->id,
            'code' => $s->code,
            'name' => $s->name,
            'company_name' => $s->company_name,
            'pic_name' => $s->effective_pic,
            'owner_name' => $s->effective_pic,
            'phone' => $s->phone,
            'email' => $s->email,
            'address' => $s->address,
            'city' => $s->city,
            'category' => $s->category_summary,
            'business_categories' => $s->business_categories_list,
            'status' => strtoupper((string) $s->status),
            'notes' => $s->notes,
            'created_at' => $s->created_at?->toIso8601String(),
            'summary' => [
                'total_products' => $totalProducts,
                'total_purchases' => $totalPurchases,
                'total_purchase_value' => round($totalPurchaseValue, 2),
                'last_purchase_date' => $lastPurchaseDate ? Carbon::parse($lastPurchaseDate)->toDateString() : null,
                'last_purchase_date_label' => $lastPurchaseLabel,
            ],
        ];
    }

    protected function validateSupplierPayload(Request $request): array
    {
        return $request->validate([
            'name' => 'required|string|max:150',
            'company_name' => 'nullable|string|max:150',
            'pic_name' => 'nullable|string|max:100',
            'owner_name' => 'nullable|string|max:100',
            'phone' => 'nullable|string|max:30',
            'email' => 'nullable|email|max:150',
            'address' => 'nullable|string|max:500',
            'city' => 'nullable|string|max:100',
            'category' => 'nullable|string|max:250',
            'business_categories' => 'nullable|array',
            'business_categories.*' => 'nullable|string|max:100',
            'status' => 'nullable|in:AKTIF,NONAKTIF',
            'notes' => 'nullable|string|max:1000',
        ]);
    }

    protected function normalizeCategories(array $validated): array
    {
        $categories = collect($validated['business_categories'] ?? [])
            ->map(fn ($v) => trim((string) $v))
            ->filter(fn ($v) => $v !== '')
            ->unique(fn ($v) => mb_strtolower($v))
            ->values()
            ->all();

        if (empty($categories) && !empty($validated['category'])) {
            $categories = collect(explode(',', (string) $validated['category']))
                ->map(fn ($v) => trim($v))
                ->filter(fn ($v) => $v !== '')
                ->unique(fn ($v) => mb_strtolower($v))
                ->values()
                ->all();
        }

        return $categories;
    }

    protected function generateSupplierCode(): string
    {
        $nextNum = ((int) Supplier::max('id')) + 1;
        $code = 'SUP-' . str_pad((string) $nextNum, 3, '0', STR_PAD_LEFT);
        while (Supplier::where('code', $code)->exists()) {
            $nextNum++;
            $code = 'SUP-' . str_pad((string) $nextNum, 3, '0', STR_PAD_LEFT);
        }
        return $code;
    }

    /**
     * Enforce Section 10 access control:
     * Manager Gudang / Supervisor can mutate; Owner is read-only monitoring.
     */
    protected function ensureManagerWriteAccess(): ?JsonResponse
    {
        $user = Auth::user();
        if (!$user) {
            return null; // Handled by EnsureApiAuthenticated in live env
        }

        if ($user->isOwnerOrSuperadmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Akses Owner hanya untuk pemantauan (read-only). Pengelolaan data supplier dilakukan oleh Manager Gudang.',
            ], 403);
        }

        return null;
    }

    protected function recordAudit(string $action, string $reference, string $description): void
    {
        try {
            $user = Auth::user();
            AuditLog::record([
                'user_id' => $user?->id,
                'user_name' => $user?->name ?? 'Manager Gudang',
                'user_role' => $user?->role ?? 'Manager',
                'location' => 'Gudang Pusat (DC)',
                'category' => 'SUPPLIER',
                'action_name' => $action,
                'description' => $description,
                'reference' => $reference,
                'status' => 'SUCCESS',
                'source' => 'WEB',
            ]);
        } catch (\Throwable $e) {
            // Non-fatal
        }
    }
}
