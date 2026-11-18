<?php

namespace App\Http\Controllers\Api\Manager;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Api\Shared\NotificationController;
use App\Models\AuditLog;
use App\Models\GoodsReceipt;
use App\Models\GoodsReceiptItem;
use App\Models\Location;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\PurchasePriceHistory;
use App\Models\StockBatch;
use App\Models\Supplier;
use App\Services\SupplierProductSyncService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PurchaseController extends Controller
{
    /**
     * Provide metadata for Purchase form (suppliers, non-consignment products, next PO number).
     */
    public function meta(): JsonResponse
    {
        $suppliers = Supplier::regular()
            ->where('status', 'AKTIF')
            ->orderBy('name')
            ->get(['id', 'code', 'name', 'company_name', 'phone', 'address', 'city', 'category', 'business_categories', 'status']);

        $allSuppliers = Supplier::regular()
            ->orderBy('name')
            ->get(['id', 'code', 'name', 'status']);

        $products = Product::with('units')
            ->where('is_consignment', false)
            ->orderBy('name')
            ->get()
            ->map(function (Product $p) {
                $unitOptions = [
                    [
                        'unit_name' => $p->unit ?: 'Pcs',
                        'conversion_factor' => 1,
                        'is_base' => true,
                    ],
                ];
                foreach ($p->units as $u) {
                    if (strcasecmp($u->unit_name, $p->unit) !== 0) {
                        $unitOptions[] = [
                            'unit_name' => $u->unit_name,
                            'conversion_factor' => max(1, (int) $u->conversion_factor),
                            'is_base' => false,
                        ];
                    }
                }

                return [
                    'id' => $p->id,
                    'sku' => $p->sku,
                    'barcode' => $p->barcode,
                    'name' => $p->name,
                    'category' => $p->category,
                    'unit' => $p->unit ?: 'Pcs',
                    'cost_price' => (float) $p->cost_price,
                    'last_cost_price' => $p->last_cost_price !== null ? (float) $p->last_cost_price : (float) $p->cost_price,
                    'price' => (float) $p->price,
                    'stock' => (int) $p->stock,
                    'unit_options' => $unitOptions,
                ];
            });

        $warehouse = Location::where('type', 'WAREHOUSE')->where('is_active', true)->first();

        return response()->json([
            'success' => true,
            'data' => [
                'next_purchase_number' => $this->generatePurchaseNumber(),
                'warehouse_name' => $warehouse?->name ?? 'Gudang Pusat (DC)',
                'warehouse_id' => $warehouse?->id,
                'suppliers' => $suppliers,
                'all_suppliers' => $allSuppliers,
                'products' => $products,
            ],
        ]);
    }

    /**
     * Quick-create a regular supplier from the Purchase module.
     */
    public function storeSupplier(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:150',
            'company_name' => 'nullable|string|max:150',
            'owner_name' => 'nullable|string|max:100',
            'pic_name' => 'nullable|string|max:100',
            'phone' => 'nullable|string|max:30',
            'email' => 'nullable|email|max:150',
            'address' => 'nullable|string|max:500',
            'city' => 'nullable|string|max:100',
            'category' => 'nullable|string|max:100',
            'business_categories' => 'nullable|array',
            'business_categories.*' => 'string|max:100',
            'notes' => 'nullable|string|max:1000',
        ]);

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

                $nextNum = ((int) Supplier::max('id')) + 1;
                $code = 'SUP-' . str_pad((string) $nextNum, 3, '0', STR_PAD_LEFT);
                while (Supplier::where('code', $code)->exists()) {
                    $nextNum++;
                    $code = 'SUP-' . str_pad((string) $nextNum, 3, '0', STR_PAD_LEFT);
                }

                $pic = $validated['pic_name'] ?? ($validated['owner_name'] ?? null);
                $categories = collect($validated['business_categories'] ?? [])
                    ->map(fn ($c) => trim((string) $c))
                    ->filter(fn ($c) => $c !== '')
                    ->unique(fn ($c) => mb_strtolower($c))
                    ->values()
                    ->all();
                if (empty($categories) && !empty($validated['category'])) {
                    $categories = [trim((string) $validated['category'])];
                }
                $categorySummary = !empty($categories) ? implode(', ', $categories) : 'Supplier Umum';

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
                    'status' => 'AKTIF',
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

        $supplier = $result['supplier'];

        return response()->json([
            'success' => true,
            'message' => "Supplier [{$supplier->name}] berhasil ditambahkan.",
            'data' => $supplier,
        ], 201);
    }

    /**
     * List purchases with filters, summary KPIs, and pagination.
     */
    public function index(Request $request): JsonResponse
    {
        $search = trim((string) $request->query('search', ''));
        $supplierId = $request->query('supplier_id');
        $status = strtoupper(trim((string) $request->query('status', 'all')));
        $dateFrom = $request->query('date_from');
        $dateTo = $request->query('date_to');
        $pendingReceipt = filter_var($request->query('pending_receipt', false), FILTER_VALIDATE_BOOLEAN);
        $perPage = max(5, min(100, (int) $request->query('per_page', 10)));

        $query = Purchase::with(['supplier', 'creator', 'items.product', 'goodsReceipts.receiver']);

        if ($search !== '') {
            $lower = strtolower($search);
            $query->where(function ($sub) use ($lower) {
                $sub->whereRaw('LOWER(purchase_number) LIKE ?', ["%{$lower}%"])
                    ->orWhereRaw('LOWER(COALESCE(supplier_invoice_number, \'\')) LIKE ?', ["%{$lower}%"])
                    ->orWhereHas('supplier', fn ($sq) => $sq->whereRaw('LOWER(name) LIKE ?', ["%{$lower}%"]));
            });
        }

        if ($supplierId && $supplierId !== 'all') {
            $query->where('supplier_id', (int) $supplierId);
        }

        if ($pendingReceipt) {
            $query->whereIn('status', [
                Purchase::STATUS_MENUNGGU,
                Purchase::STATUS_SEBAGIAN,
                'ORDERED',
                'PENDING',
            ]);
        } elseif ($status !== '' && $status !== 'ALL') {
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

        // Compute summary KPIs across all purchases
        $allForSummary = Purchase::select(['id', 'status', 'total_amount'])->get();
        $totalPurchases = $allForSummary->count();
        $waitingCount = 0;
        $partialCount = 0;
        $totalValue = 0.0;

        foreach ($allForSummary as $p) {
            $norm = $p->normalized_status;
            if ($norm === Purchase::STATUS_MENUNGGU) {
                $waitingCount++;
            } elseif ($norm === Purchase::STATUS_SEBAGIAN) {
                $partialCount++;
            }
            if ($norm !== Purchase::STATUS_DIBATALKAN) {
                $totalValue += (float) $p->total_amount;
            }
        }

        $items = collect($paginated->items())->map(fn (Purchase $p) => $this->formatPurchase($p))->values();

        return response()->json([
            'success' => true,
            'summary' => [
                'total_purchases' => $totalPurchases,
                'waiting_receipt' => $waitingCount,
                'partial_receipt' => $partialCount,
                'total_value' => round($totalValue, 2),
            ],
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
     * Show detailed single purchase including receipt transactions and stock batches.
     */
    public function show(int $id): JsonResponse
    {
        $purchase = Purchase::with([
            'supplier',
            'creator',
            'items.product',
            'goodsReceipts.items.product',
            'goodsReceipts.receiver',
            'stockBatches.product',
        ])->find($id);

        if (!$purchase) {
            return response()->json([
                'success' => false,
                'message' => 'Transaksi pembelian tidak ditemukan.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $this->formatPurchase($purchase, true),
        ]);
    }

    /**
     * Create a new Purchase transaction (Draft or Menunggu Penerimaan).
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'purchase_number' => 'nullable|string|max:100',
            'purchase_date' => 'required|date',
            'supplier_id' => 'required|exists:suppliers,id',
            'supplier_invoice_number' => 'nullable|string|max:100',
            'notes' => 'nullable|string|max:1000',
            'status' => 'nullable|in:DRAFT,MENUNGGU_PENERIMAAN',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.unit' => 'nullable|string|max:50',
            'items.*.conversion_factor' => 'nullable|integer|min:1',
            'items.*.qty' => 'required|numeric|min:0.01',
            'items.*.purchase_price' => 'required|numeric|min:0',
            'items.*.discount_amount' => 'nullable|numeric|min:0',
        ]);

        // Enforce active & regular supplier rules (Rule 5 & Rule 13)
        $supplier = Supplier::find((int) $validated['supplier_id']);
        if (!$supplier || $supplier->supplier_type === Supplier::TYPE_CONSIGNMENT || str_starts_with((string) $supplier->code, 'UMKM-')) {
            return response()->json([
                'success' => false,
                'message' => 'Mitra konsinyasi UMKM tidak dapat dipilih untuk transaksi pembelian reguler.',
            ], 422);
        }
        if (strtoupper((string) $supplier->status) !== 'AKTIF') {
            return response()->json([
                'success' => false,
                'message' => "Supplier [{$supplier->name}] berstatus Nonaktif dan tidak dapat digunakan untuk transaksi pembelian baru.",
            ], 422);
        }

        // Ensure normal purchase does not include consignment products
        $productIds = collect($validated['items'])->pluck('product_id')->unique()->all();
        $products = Product::with('units')->whereIn('id', $productIds)->get()->keyBy('id');

        foreach ($validated['items'] as $it) {
            $prod = $products->get((int) $it['product_id']);
            if (!$prod || $prod->is_consignment) {
                $name = $prod?->name ?? ('ID ' . $it['product_id']);
                return response()->json([
                    'success' => false,
                    'message' => "Produk [{$name}] adalah barang konsinyasi UMKM dan tidak boleh dicampur ke transaksi pembelian reguler.",
                ], 422);
            }
        }

        return DB::transaction(function () use ($validated, $products) {
            $poNumber = trim((string) ($validated['purchase_number'] ?? ''));
            if ($poNumber === '' || Purchase::where('purchase_number', $poNumber)->exists()) {
                $poNumber = $this->generatePurchaseNumber();
            }

            $warehouse = Location::where('type', 'WAREHOUSE')->where('is_active', true)->first();
            $status = $validated['status'] ?? Purchase::STATUS_MENUNGGU;

            $purchase = Purchase::create([
                'purchase_number' => $poNumber,
                'supplier_id' => (int) $validated['supplier_id'],
                'supplier_invoice_number' => $validated['supplier_invoice_number'] ?? null,
                'location_id' => $warehouse?->id,
                'warehouse_name' => $warehouse?->name ?? 'Gudang Pusat (DC)',
                'created_by' => Auth::id(),
                'purchase_date' => $validated['purchase_date'],
                'status' => $status,
                'total_amount' => 0,
                'notes' => $validated['notes'] ?? null,
            ]);

            $grandTotal = 0.0;
            foreach ($validated['items'] as $it) {
                $prod = $products->get((int) $it['product_id']);
                $unitName = trim((string) ($it['unit'] ?? $prod->unit ?? 'Pcs'));
                $factor = (int) ($it['conversion_factor'] ?? 1);
                if ($factor <= 1 && strcasecmp($unitName, $prod->unit ?? 'Pcs') !== 0) {
                    $matchedUnit = $prod->units->first(fn ($u) => strcasecmp($u->unit_name, $unitName) === 0);
                    $factor = $matchedUnit ? max(1, (int) $matchedUnit->conversion_factor) : 1;
                }
                $factor = max(1, $factor);

                $qty = round((float) $it['qty'], 2);
                $price = round((float) $it['purchase_price'], 2);
                $discount = round((float) ($it['discount_amount'] ?? 0), 2);
                $subtotal = max(0.0, round(($qty * $price) - $discount, 2));
                $grandTotal += $subtotal;

                PurchaseItem::create([
                    'purchase_id' => $purchase->id,
                    'product_id' => $prod->id,
                    'unit' => $unitName,
                    'conversion_factor' => $factor,
                    'qty' => $qty,
                    'received_qty' => 0,
                    'purchase_price' => $price,
                    'discount_amount' => $discount,
                    'subtotal' => $subtotal,
                    'ownership_type' => 'OUTRIGHT',
                ]);
            }

            $purchase->total_amount = round($grandTotal, 2);
            $purchase->save();

            $this->recordAudit(
                'CREATE_PURCHASE',
                $purchase->purchase_number,
                "Membuat transaksi pembelian {$purchase->purchase_number} ({$status}) senilai Rp " . number_format($grandTotal, 0, ',', '.')
            );

            $purchase->load(['supplier', 'creator', 'items.product', 'goodsReceipts.receiver']);

            return response()->json([
                'success' => true,
                'message' => $status === Purchase::STATUS_DRAFT
                    ? "Draft pembelian {$purchase->purchase_number} berhasil disimpan."
                    : "Pembelian {$purchase->purchase_number} berhasil disimpan dan siap diterima di gudang.",
                'data' => $this->formatPurchase($purchase),
            ], 201);
        });
    }

    /**
     * Update an existing purchase (Draft or unreceived purchase) or cancel it.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $purchase = Purchase::with('items')->find($id);
        if (!$purchase) {
            return response()->json(['success' => false, 'message' => 'Transaksi pembelian tidak ditemukan.'], 404);
        }

        $normalized = $purchase->normalized_status;
        $hasReceivedAny = $purchase->items->sum('received_qty') > 0;

        if ($hasReceivedAny || in_array($normalized, [Purchase::STATUS_SEBAGIAN, Purchase::STATUS_SELESAI], true)) {
            return response()->json([
                'success' => false,
                'message' => 'Pembelian yang sudah memiliki penerimaan barang tidak dapat diubah atau dibatalkan.',
            ], 422);
        }

        // Quick status-only update (e.g., cancel or submit draft)
        if ($request->has('status') && !$request->has('items')) {
            $validatedStatus = $request->validate([
                'status' => 'required|in:DRAFT,MENUNGGU_PENERIMAAN,DIBATALKAN',
            ]);
            $purchase->status = $validatedStatus['status'];
            $purchase->save();

            $this->recordAudit(
                'UPDATE_PURCHASE_STATUS',
                $purchase->purchase_number,
                "Mengubah status pembelian {$purchase->purchase_number} menjadi {$purchase->status}."
            );

            return response()->json([
                'success' => true,
                'message' => "Status pembelian {$purchase->purchase_number} diperbarui.",
                'data' => $this->formatPurchase($purchase->fresh(['supplier', 'creator', 'items.product', 'goodsReceipts.receiver'])),
            ]);
        }

        $validated = $request->validate([
            'purchase_date' => 'required|date',
            'supplier_id' => 'required|exists:suppliers,id',
            'supplier_invoice_number' => 'nullable|string|max:100',
            'notes' => 'nullable|string|max:1000',
            'status' => 'nullable|in:DRAFT,MENUNGGU_PENERIMAAN,DIBATALKAN',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.unit' => 'nullable|string|max:50',
            'items.*.conversion_factor' => 'nullable|integer|min:1',
            'items.*.qty' => 'required|numeric|min:0.01',
            'items.*.purchase_price' => 'required|numeric|min:0',
            'items.*.discount_amount' => 'nullable|numeric|min:0',
        ]);

        $supplier = Supplier::find((int) $validated['supplier_id']);
        if (!$supplier || $supplier->supplier_type === Supplier::TYPE_CONSIGNMENT || str_starts_with((string) $supplier->code, 'UMKM-')) {
            return response()->json([
                'success' => false,
                'message' => 'Mitra konsinyasi UMKM tidak dapat dipilih untuk transaksi pembelian reguler.',
            ], 422);
        }
        if (($validated['status'] ?? $purchase->status) !== Purchase::STATUS_DIBATALKAN && strtoupper((string) $supplier->status) !== 'AKTIF') {
            return response()->json([
                'success' => false,
                'message' => "Supplier [{$supplier->name}] berstatus Nonaktif dan tidak dapat digunakan untuk transaksi pembelian baru.",
            ], 422);
        }

        $productIds = collect($validated['items'])->pluck('product_id')->unique()->all();
        $products = Product::with('units')->whereIn('id', $productIds)->get()->keyBy('id');

        foreach ($validated['items'] as $it) {
            $prod = $products->get((int) $it['product_id']);
            if (!$prod || $prod->is_consignment) {
                $name = $prod?->name ?? ('ID ' . $it['product_id']);
                return response()->json([
                    'success' => false,
                    'message' => "Produk [{$name}] adalah barang konsinyasi UMKM dan tidak boleh dicampur ke pembelian reguler.",
                ], 422);
            }
        }

        return DB::transaction(function () use ($purchase, $validated, $products) {
            $purchase->supplier_id = (int) $validated['supplier_id'];
            $purchase->supplier_invoice_number = $validated['supplier_invoice_number'] ?? null;
            $purchase->purchase_date = $validated['purchase_date'];
            $purchase->notes = $validated['notes'] ?? null;
            if (!empty($validated['status'])) {
                $purchase->status = $validated['status'];
            }

            $purchase->items()->delete();

            $grandTotal = 0.0;
            foreach ($validated['items'] as $it) {
                $prod = $products->get((int) $it['product_id']);
                $unitName = trim((string) ($it['unit'] ?? $prod->unit ?? 'Pcs'));
                $factor = (int) ($it['conversion_factor'] ?? 1);
                if ($factor <= 1 && strcasecmp($unitName, $prod->unit ?? 'Pcs') !== 0) {
                    $matchedUnit = $prod->units->first(fn ($u) => strcasecmp($u->unit_name, $unitName) === 0);
                    $factor = $matchedUnit ? max(1, (int) $matchedUnit->conversion_factor) : 1;
                }
                $factor = max(1, $factor);

                $qty = round((float) $it['qty'], 2);
                $price = round((float) $it['purchase_price'], 2);
                $discount = round((float) ($it['discount_amount'] ?? 0), 2);
                $subtotal = max(0.0, round(($qty * $price) - $discount, 2));
                $grandTotal += $subtotal;

                PurchaseItem::create([
                    'purchase_id' => $purchase->id,
                    'product_id' => $prod->id,
                    'unit' => $unitName,
                    'conversion_factor' => $factor,
                    'qty' => $qty,
                    'received_qty' => 0,
                    'purchase_price' => $price,
                    'discount_amount' => $discount,
                    'subtotal' => $subtotal,
                    'ownership_type' => 'OUTRIGHT',
                ]);
            }

            $purchase->total_amount = round($grandTotal, 2);
            $purchase->save();

            $this->recordAudit(
                'UPDATE_PURCHASE',
                $purchase->purchase_number,
                "Memperbarui transaksi pembelian {$purchase->purchase_number}."
            );

            return response()->json([
                'success' => true,
                'message' => "Transaksi pembelian {$purchase->purchase_number} berhasil diperbarui.",
                'data' => $this->formatPurchase($purchase->fresh(['supplier', 'creator', 'items.product', 'goodsReceipts.receiver'])),
            ]);
        });
    }

    /**
     * Confirm full or partial goods receipt for a Purchase at the Warehouse.
     * - Creates a dedicated GoodsReceipt transaction record.
     * - Creates StockBatch rows for FIFO/FEFO tracking.
     * - Increments Product warehouse stock for confirmed received qty only.
     * - Updates Product.last_cost_price & cost_price without touching selling price.
     * - Records immutable PurchasePriceHistory.
     */
    public function receive(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'received_at' => 'nullable|date',
            'notes' => 'nullable|string|max:1000',
            'items' => 'required|array|min:1',
            'items.*.purchase_item_id' => 'required|integer',
            'items.*.qty_received_now' => 'required|numeric|min:0',
            'items.*.actual_unit_price' => 'nullable|numeric|min:0',
            'items.*.condition' => 'nullable|string|in:BAIK,RUSAK,KURANG,KEMASAN_RUSAK',
            'items.*.expired_date' => 'nullable|date',
            'items.*.batch_number' => 'nullable|string|max:100',
            'items.*.note' => 'nullable|string|max:500',
        ]);

        return DB::transaction(function () use ($id, $validated) {
            /** @var Purchase|null $purchase */
            $purchase = Purchase::with(['supplier', 'items.product'])
                ->where('id', $id)
                ->lockForUpdate()
                ->first();

            if (!$purchase) {
                return response()->json(['success' => false, 'message' => 'Transaksi pembelian tidak ditemukan.'], 404);
            }

            $normalized = $purchase->normalized_status;
            if (!in_array($normalized, [Purchase::STATUS_MENUNGGU, Purchase::STATUS_SEBAGIAN], true)) {
                return response()->json([
                    'success' => false,
                    'message' => "Pembelian dengan status [{$normalized}] tidak dapat menerima barang.",
                ], 422);
            }

            $itemsById = $purchase->items->keyBy('id');
            $totalReceivingNow = 0.0;
            $hasDiscrepancy = false;

            // Validate every submitted item before mutating anything
            foreach ($validated['items'] as $row) {
                $pItem = $itemsById->get((int) $row['purchase_item_id']);
                if (!$pItem) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Item pembelian tidak valid untuk transaksi ini.',
                    ], 422);
                }

                $qtyNow = round((float) $row['qty_received_now'], 2);
                $remaining = $pItem->remaining_qty;

                if ($qtyNow > $remaining + 0.0001) {
                    $prodName = $pItem->product?->name ?? 'Produk';
                    return response()->json([
                        'success' => false,
                        'message' => "Jumlah diterima untuk [{$prodName}] ({$qtyNow}) melebihi sisa jumlah pembelian ({$remaining}).",
                    ], 422);
                }

                if ($qtyNow > 0) {
                    $totalReceivingNow += $qtyNow;
                }
                if ($qtyNow < $remaining || (($row['condition'] ?? 'BAIK') !== 'BAIK') || !empty($row['note'])) {
                    $hasDiscrepancy = true;
                }
            }

            if ($totalReceivingNow <= 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Masukkan minimal 1 produk dengan jumlah diterima lebih dari 0.',
                ], 422);
            }

            $receivedAt = !empty($validated['received_at'])
                ? Carbon::parse($validated['received_at'])
                : now();

            $receiptNo = $this->generateReceiptNumber();
            $warehouse = Location::where('type', 'WAREHOUSE')->where('is_active', true)->first();
            $warehouseName = $purchase->warehouse_name ?: ($warehouse?->name ?? 'Gudang Pusat (DC)');

            $receipt = GoodsReceipt::create([
                'receipt_number' => $receiptNo,
                'purchase_id' => $purchase->id,
                'received_by' => Auth::id(),
                'received_at' => $receivedAt,
                'driver_name' => $purchase->supplier?->owner_name ?: ($purchase->supplier?->name ?? 'Supplier'),
                'plate_number' => $purchase->supplier_invoice_number ?: '-',
                'origin_source' => $purchase->supplier?->name ?? 'Supplier',
                'destination_store' => $warehouseName,
                'status' => $hasDiscrepancy ? 'DITERIMA_SELISIH' : 'DITERIMA_LENGKAP',
                'notes' => $validated['notes'] ?? null,
            ]);

            foreach ($validated['items'] as $row) {
                /** @var PurchaseItem $pItem */
                $pItem = $itemsById->get((int) $row['purchase_item_id']);
                $qtyNow = round((float) $row['qty_received_now'], 2);
                $note = trim((string) ($row['note'] ?? ''));
                $condition = strtoupper((string) ($row['condition'] ?? 'BAIK'));

                // Record item on receipt only if received > 0 or manager logged a discrepancy note
                if ($qtyNow <= 0 && $note === '') {
                    continue;
                }

                $remainingBefore = $pItem->remaining_qty;
                $factor = max(1, (int) ($pItem->conversion_factor ?: 1));

                // Effective purchase price per unit (respecting explicit receipt override or purchase price net of unit discount)
                $defaultUnitPrice = (float) $pItem->purchase_price;
                if ((float) $pItem->qty > 0 && (float) $pItem->discount_amount > 0) {
                    $defaultUnitPrice = max(0, round(((float) $pItem->subtotal) / (float) $pItem->qty, 2));
                }
                $actualUnitPrice = isset($row['actual_unit_price']) && $row['actual_unit_price'] !== null
                    ? round((float) $row['actual_unit_price'], 2)
                    : $defaultUnitPrice;

                $newBaseUnitCost = round($actualUnitPrice / $factor, 2);

                $grItem = GoodsReceiptItem::create([
                    'goods_receipt_id' => $receipt->id,
                    'purchase_item_id' => $pItem->id,
                    'product_id' => $pItem->product_id,
                    'qty_sent' => $remainingBefore,
                    'qty_received' => $qtyNow,
                    'actual_unit_cost' => $newBaseUnitCost,
                    'condition' => $condition,
                    'ownership_type' => 'OUTRIGHT',
                    'notes' => $note !== '' ? $note : null,
                ]);

                if ($qtyNow > 0) {
                    // 1. Update received quantity on purchase item
                    $pItem->received_qty = round((float) $pItem->received_qty + $qtyNow, 2);
                    $pItem->save();

                    // 2. Lock and update Master Product (Stock + Last Cost Price ONLY; NEVER selling price)
                    /** @var Product $product */
                    $product = Product::where('id', $pItem->product_id)->lockForUpdate()->firstOrFail();

                    $baseQtyAdded = (int) round($qtyNow * $factor);
                    $previousPrice = $product->last_cost_price !== null
                        ? (float) $product->last_cost_price
                        : (float) $product->cost_price;

                    $batchExpDate = !empty($row['expired_date']) ? Carbon::parse($row['expired_date'])->toDateString() : null;
                    $batchNumber = trim((string) ($row['batch_number'] ?? ''));
                    if ($batchNumber === '' || StockBatch::where('batch_number', $batchNumber)->exists()) {
                        $batchNumber = $this->generateBatchNumber($product->sku);
                    }

                    // 3. Create StockBatch for FIFO & FEFO
                    $warehouseLocId = $purchase->location_id ?: $warehouse?->id;
                    $createdBatch = StockBatch::create([
                        'batch_number' => $batchNumber,
                        'product_id' => $product->id,
                        'location_id' => $warehouseLocId,
                        'supplier_id' => $purchase->supplier_id,
                        'purchase_id' => $purchase->id,
                        'goods_receipt_id' => $receipt->id,
                        'goods_receipt_item_id' => $grItem->id,
                        'ownership_type' => 'OUTRIGHT',
                        'initial_qty' => $baseQtyAdded,
                        'remaining_qty' => $baseQtyAdded,
                        'unit_cost' => $newBaseUnitCost,
                        'received_at' => $receivedAt,
                        'expired_date' => $batchExpDate,
                        'notes' => $note !== '' ? $note : "Penerimaan {$receiptNo} ({$purchase->purchase_number})",
                    ]);

                    // 3b. Sync Warehouse ProductStock (Gudang Pusat) without touching Store Stock
                    if ($warehouseLocId) {
                        app(\App\Services\LocationStockService::class)->addStock($product->id, $warehouseLocId, $baseQtyAdded, [
                            'skip_batch' => true,
                            'movement_type' => 'PURCHASE_IN',
                            'reference_type' => Purchase::class,
                            'reference_id' => $purchase->id,
                            'reference_number' => $purchase->purchase_number,
                            'user_id' => Auth::id(),
                            'notes' => "Penerimaan pembelian {$receiptNo} (Batch {$createdBatch->batch_number})",
                        ]);
                        $product->refresh();
                    }

                    // 4. Increment warehouse stock & update global last_cost_price from latest valid receipt (keep price & member_price intact!)
                    $syncService = app(SupplierProductSyncService::class);
                    $latestGlobalCost = $syncService->resolveGlobalLatestCostPrice($product->id) ?? $newBaseUnitCost;

                    if (!$warehouseLocId) {
                        $product->stock = (int) $product->stock + $baseQtyAdded;
                    }
                    $product->last_cost_price = $latestGlobalCost;
                    $product->cost_price = $latestGlobalCost;

                    // FEFO: if new batch has expired_date and is earlier than current product expired_date (or product had none)
                    if ($batchExpDate) {
                        if (!$product->expired_date || Carbon::parse($batchExpDate)->lt(Carbon::parse($product->expired_date))) {
                            $product->expired_date = $batchExpDate;
                        }
                    }

                    $product->status = $product->stock <= 0
                        ? 'HABIS'
                        : ($product->stock <= (int) $product->min_stock ? 'STOK_RENDAH' : 'TERSEDIA');
                    $product->save();

                    // 4b. Auto-build / update supplier_products relationship & supplier-specific last purchase price
                    $syncService->syncFromReceipt($purchase, $receipt, $product, $grItem, $receivedAt);

                    // 5. Append immutable PurchasePriceHistory record
                    $priceDiff = round($newBaseUnitCost - $previousPrice, 2);
                    $changePct = $previousPrice > 0
                        ? round(($priceDiff / $previousPrice) * 100, 2)
                        : ($newBaseUnitCost > 0 ? 100.0 : 0.0);
                    $changeType = $priceDiff > 0 ? 'NAIK' : ($priceDiff < 0 ? 'TURUN' : 'TETAP');

                    PurchasePriceHistory::create([
                        'product_id' => $product->id,
                        'supplier_id' => $purchase->supplier_id,
                        'purchase_id' => $purchase->id,
                        'purchase_item_id' => $pItem->id,
                        'goods_receipt_id' => $receipt->id,
                        'recorded_by' => Auth::id(),
                        'purchase_number' => $purchase->purchase_number,
                        'unit' => $pItem->unit ?: ($product->unit ?: 'Pcs'),
                        'conversion_factor' => $factor,
                        'raw_unit_price' => $actualUnitPrice,
                        'previous_price' => $previousPrice,
                        'new_price' => $newBaseUnitCost,
                        'price_diff' => $priceDiff,
                        'change_percent' => $changePct,
                        'change_type' => $changeType,
                        'recorded_at' => $receivedAt,
                        'notes' => $note !== '' ? $note : null,
                    ]);
                }
            }

            // Recompute Purchase status from all items
            $purchase->load('items');
            $allCompleted = $purchase->items->every(fn (PurchaseItem $i) => (float) $i->received_qty >= (float) $i->qty - 0.0001);
            $anyReceived = $purchase->items->some(fn (PurchaseItem $i) => (float) $i->received_qty > 0);

            $purchase->status = $allCompleted
                ? Purchase::STATUS_SELESAI
                : ($anyReceived ? Purchase::STATUS_SEBAGIAN : Purchase::STATUS_MENUNGGU);
            $purchase->save();

            // Clear cached inventory KPI summary
            Cache::forget('products_kpi_summary_' . Carbon::today()->toDateString());

            $this->recordAudit(
                'RECEIVE_PURCHASE_GOODS',
                $receiptNo,
                "Konfirmasi penerimaan barang {$receiptNo} untuk PO {$purchase->purchase_number} (Status PO: {$purchase->status})."
            );

            try {
                NotificationController::notify(
                    role: 'MANAGER',
                    title: "Penerimaan Pembelian {$receiptNo}",
                    message: "Barang dari {$purchase->supplier?->name} ({$purchase->purchase_number}) diterima di gudang. Status: {$purchase->status}.",
                    category: 'PURCHASE',
                    link: '/pembelian-barang',
                    icon: 'inventory',
                    color: $allCompleted ? 'emerald' : 'amber',
                    referenceId: $purchase->purchase_number
                );
            } catch (\Throwable $e) {
                // Non-fatal notification failure
            }

            $purchase->load(['supplier', 'creator', 'items.product', 'goodsReceipts.receiver']);

            return response()->json([
                'success' => true,
                'message' => "Penerimaan {$receiptNo} berhasil dikonfirmasi. Stok gudang & harga beli terakhir Master Produk telah diperbarui.",
                'data' => [
                    'purchase' => $this->formatPurchase($purchase, true),
                    'receipt' => [
                        'id' => $receipt->id,
                        'receipt_number' => $receipt->receipt_number,
                        'received_at' => $receipt->received_at?->toIso8601String(),
                        'status' => $receipt->status,
                    ],
                ],
            ]);
        });
    }

    /**
     * List Purchase Price History with KPIs, filters, and sorting.
     */
    public function priceHistory(Request $request): JsonResponse
    {
        $search = trim((string) $request->query('search', ''));
        $supplierId = $request->query('supplier_id');
        $dateFrom = $request->query('date_from');
        $dateTo = $request->query('date_to');
        $changeType = strtoupper(trim((string) $request->query('change_type', 'all')));
        $sort = trim((string) $request->query('sort', 'latest'));
        $perPage = max(5, min(100, (int) $request->query('per_page', 15)));

        $query = PurchasePriceHistory::with(['product', 'supplier', 'purchase']);

        if ($search !== '') {
            $lower = strtolower($search);
            $query->where(function ($sub) use ($lower) {
                $sub->whereRaw('LOWER(COALESCE(purchase_number, \'\')) LIKE ?', ["%{$lower}%"])
                    ->orWhereHas('product', function ($pq) use ($lower) {
                        $pq->whereRaw('LOWER(name) LIKE ?', ["%{$lower}%"])
                           ->orWhereRaw('LOWER(sku) LIKE ?', ["%{$lower}%"]);
                    });
            });
        }

        if ($supplierId && $supplierId !== 'all') {
            $query->where('supplier_id', (int) $supplierId);
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

        if ($sort === 'price_asc') {
            $query->orderBy('new_price', 'asc')->orderByDesc('recorded_at');
        } elseif ($sort === 'price_desc') {
            $query->orderBy('new_price', 'desc')->orderByDesc('recorded_at');
        } else {
            $query->orderByDesc('recorded_at')->orderByDesc('id');
        }

        $paginated = $query->paginate($perPage);

        // Summary metrics across all recorded price histories
        $productsWithHistory = (int) PurchasePriceHistory::distinct('product_id')->count('product_id');
        $totalPriceChanges = (int) PurchasePriceHistory::where('price_diff', '!=', 0)->count();
        $productsWithIncrease = (int) PurchasePriceHistory::where('change_type', 'NAIK')->distinct('product_id')->count('product_id');
        $productsWithDecrease = (int) PurchasePriceHistory::where('change_type', 'TURUN')->distinct('product_id')->count('product_id');

        $rows = collect($paginated->items())->map(fn (PurchasePriceHistory $h) => [
            'id' => $h->id,
            'recorded_at' => $h->recorded_at?->toIso8601String(),
            'recorded_date' => $h->recorded_at?->format('Y-m-d'),
            'recorded_date_label' => $h->recorded_at?->translatedFormat('d M Y'),
            'product_id' => $h->product_id,
            'product_name' => $h->product?->name ?? 'Produk',
            'sku' => $h->product?->sku ?? '-',
            'unit' => $h->unit ?: ($h->product?->unit ?? 'Pcs'),
            'supplier_id' => $h->supplier_id,
            'supplier_name' => $h->supplier?->name ?? 'Supplier',
            'previous_price' => (float) $h->previous_price,
            'new_price' => (float) $h->new_price,
            'raw_unit_price' => (float) $h->raw_unit_price,
            'price_diff' => (float) $h->price_diff,
            'change_percent' => (float) $h->change_percent,
            'change_type' => $h->change_type,
            'purchase_id' => $h->purchase_id,
            'purchase_number' => $h->purchase_number ?: ($h->purchase?->purchase_number ?? '-'),
            'notes' => $h->notes,
        ])->values();

        // Build chart dataset (latest up to 12 events chronologically for quick overview chart)
        $chartRows = (clone $query)->reorder('recorded_at', 'asc')->orderBy('id', 'asc')->limit(30)->get();
        $chartSeries = $chartRows->map(fn (PurchasePriceHistory $h) => [
            'product_id' => $h->product_id,
            'label' => $h->recorded_at?->format('d/m'),
            'full_date' => $h->recorded_at?->translatedFormat('d M Y'),
            'product_name' => $h->product?->name ?? 'Produk',
            'supplier_name' => $h->supplier?->name ?? 'Supplier',
            'price' => (float) $h->new_price,
            'previous_price' => (float) $h->previous_price,
            'change_type' => $h->change_type,
        ])->values();

        return response()->json([
            'success' => true,
            'summary' => [
                'products_with_history' => $productsWithHistory,
                'total_price_changes' => $totalPriceChanges,
                'products_with_increase' => $productsWithIncrease,
                'products_with_decrease' => $productsWithDecrease,
            ],
            'data' => $rows,
            'chart_series' => $chartSeries,
            'meta' => [
                'current_page' => $paginated->currentPage(),
                'last_page' => $paginated->lastPage(),
                'per_page' => $paginated->perPage(),
                'total' => $paginated->total(),
            ],
        ]);
    }

    /**
     * Chronological price history & statistics for a specific Product.
     */
    public function productPriceHistory(int $productId): JsonResponse
    {
        $product = Product::find($productId);
        if (!$product) {
            return response()->json(['success' => false, 'message' => 'Produk tidak ditemukan.'], 404);
        }

        $records = PurchasePriceHistory::with(['supplier', 'purchase'])
            ->where('product_id', $productId)
            ->orderBy('recorded_at', 'asc')
            ->orderBy('id', 'asc')
            ->get();

        $prices = $records->pluck('new_price')->map(fn ($v) => (float) $v);
        $firstRecord = $records->first();
        $lastRecord = $records->last();

        $history = $records->map(fn (PurchasePriceHistory $h) => [
            'id' => $h->id,
            'recorded_at' => $h->recorded_at?->toIso8601String(),
            'date_label' => $h->recorded_at?->translatedFormat('d F Y'),
            'short_date' => $h->recorded_at?->format('d/m/y'),
            'supplier_id' => $h->supplier_id,
            'supplier_name' => $h->supplier?->name ?? 'Supplier',
            'purchase_number' => $h->purchase_number ?: ($h->purchase?->purchase_number ?? '-'),
            'previous_price' => (float) $h->previous_price,
            'new_price' => (float) $h->new_price,
            'price_diff' => (float) $h->price_diff,
            'change_percent' => (float) $h->change_percent,
            'change_type' => $h->change_type,
            'unit' => $h->unit ?: ($product->unit ?: 'Pcs'),
            'notes' => $h->notes,
        ])->values();

        return response()->json([
            'success' => true,
            'data' => [
                'product' => [
                    'id' => $product->id,
                    'name' => $product->name,
                    'sku' => $product->sku,
                    'unit' => $product->unit ?: 'Pcs',
                    'selling_price' => (float) $product->price,
                    'last_cost_price' => $product->last_cost_price !== null ? (float) $product->last_cost_price : (float) $product->cost_price,
                ],
                'stats' => [
                    'first_price' => $firstRecord ? (float) $firstRecord->new_price : (float) $product->cost_price,
                    'last_price' => $lastRecord ? (float) $lastRecord->new_price : (float) ($product->last_cost_price ?? $product->cost_price),
                    'highest_price' => $prices->isNotEmpty() ? (float) $prices->max() : (float) $product->cost_price,
                    'lowest_price' => $prices->isNotEmpty() ? (float) $prices->min() : (float) $product->cost_price,
                    'last_supplier' => $lastRecord?->supplier?->name ?? '-',
                    'total_records' => $records->count(),
                ],
                'history' => $history,
            ],
        ]);
    }

    /**
     * Format Purchase model for API responses.
     */
    protected function formatPurchase(Purchase $purchase, bool $includeFullReceipts = false): array
    {
        $items = $purchase->items->map(function (PurchaseItem $it) {
            $qty = (float) $it->qty;
            $received = (float) $it->received_qty;
            $remaining = max(0.0, round($qty - $received, 2));
            $price = (float) $it->purchase_price;
            $discount = (float) $it->discount_amount;
            $subtotal = (float) $it->subtotal > 0 ? (float) $it->subtotal : max(0.0, round(($qty * $price) - $discount, 2));

            return [
                'id' => $it->id,
                'product_id' => $it->product_id,
                'sku' => $it->product?->sku ?? '-',
                'barcode' => $it->product?->barcode ?? '-',
                'product_name' => $it->product?->name ?? 'Produk',
                'base_unit' => $it->product?->unit ?? 'Pcs',
                'unit' => $it->unit ?: ($it->product?->unit ?? 'Pcs'),
                'conversion_factor' => max(1, (int) ($it->conversion_factor ?: 1)),
                'qty' => $qty,
                'received_qty' => $received,
                'remaining_qty' => $remaining,
                'purchase_price' => $price,
                'discount_amount' => $discount,
                'subtotal' => $subtotal,
                'ownership_type' => $it->ownership_type ?: 'OUTRIGHT',
            ];
        })->values();

        $totalQty = round($items->sum('qty'), 2);
        $totalReceivedQty = round($items->sum('received_qty'), 2);
        $totalRemainingQty = round($items->sum('remaining_qty'), 2);

        $payload = [
            'id' => $purchase->id,
            'purchase_number' => $purchase->purchase_number,
            'purchase_date' => $purchase->purchase_date?->toDateString(),
            'purchase_date_label' => $purchase->purchase_date?->translatedFormat('d M Y'),
            'supplier_id' => $purchase->supplier_id,
            'supplier_name' => $purchase->supplier?->name ?? 'Supplier Umum',
            'supplier_code' => $purchase->supplier?->code ?? '-',
            'supplier_invoice_number' => $purchase->supplier_invoice_number,
            'warehouse_name' => $purchase->warehouse_name ?: 'Gudang Pusat (DC)',
            'created_by_name' => $purchase->creator?->name ?? 'Manager Gudang',
            'status' => $purchase->normalized_status,
            'raw_status' => $purchase->status,
            'total_items' => $items->count(),
            'total_qty' => $totalQty,
            'total_received_qty' => $totalReceivedQty,
            'total_remaining_qty' => $totalRemainingQty,
            'total_amount' => (float) $purchase->total_amount,
            'notes' => $purchase->notes,
            'can_edit' => $totalReceivedQty <= 0 && in_array($purchase->normalized_status, [Purchase::STATUS_DRAFT, Purchase::STATUS_MENUNGGU], true),
            'can_receive' => $totalRemainingQty > 0 && in_array($purchase->normalized_status, [Purchase::STATUS_MENUNGGU, Purchase::STATUS_SEBAGIAN], true),
            'items' => $items->all(),
            'receipts_count' => $purchase->goodsReceipts->count(),
        ];

        if ($includeFullReceipts) {
            $payload['receipts'] = $purchase->goodsReceipts->map(function (GoodsReceipt $r) {
                return [
                    'id' => $r->id,
                    'receipt_number' => $r->receipt_number,
                    'received_at' => $r->received_at?->toIso8601String(),
                    'received_at_label' => $r->received_at?->translatedFormat('d M Y • H:i') . ' WIB',
                    'receiver_name' => $r->receiver?->name ?? 'Manager Gudang',
                    'status' => $r->status,
                    'notes' => $r->notes,
                    'items' => $r->items->map(fn (GoodsReceiptItem $gi) => [
                        'id' => $gi->id,
                        'product_id' => $gi->product_id,
                        'sku' => $gi->product?->sku ?? '-',
                        'product_name' => $gi->product?->name ?? 'Produk',
                        'qty_received' => (float) $gi->qty_received,
                        'actual_unit_cost' => (float) $gi->actual_unit_cost,
                        'condition' => $gi->condition,
                        'notes' => $gi->notes,
                    ])->values()->all(),
                ];
            })->values()->all();

            $payload['batches'] = $purchase->stockBatches->map(fn (StockBatch $b) => [
                'id' => $b->id,
                'batch_number' => $b->batch_number,
                'product_id' => $b->product_id,
                'sku' => $b->product?->sku ?? '-',
                'product_name' => $b->product?->name ?? 'Produk',
                'initial_qty' => (float) $b->initial_qty,
                'remaining_qty' => (float) $b->remaining_qty,
                'unit_cost' => (float) $b->unit_cost,
                'received_at' => $b->received_at?->translatedFormat('d M Y H:i'),
                'expired_date' => $b->expired_date?->toDateString(),
            ])->values()->all();
        }

        return $payload;
    }

    protected function generatePurchaseNumber(): string
    {
        $prefix = 'PO-' . date('Ym') . '-';
        $count = Purchase::where('purchase_number', 'like', "{$prefix}%")->count() + 1;
        $candidate = $prefix . str_pad((string) $count, 4, '0', STR_PAD_LEFT);
        while (Purchase::where('purchase_number', $candidate)->exists()) {
            $count++;
            $candidate = $prefix . str_pad((string) $count, 4, '0', STR_PAD_LEFT);
        }
        return $candidate;
    }

    protected function generateReceiptNumber(): string
    {
        $prefix = 'GRN-PO-' . date('Ymd') . '-';
        $count = GoodsReceipt::where('receipt_number', 'like', "{$prefix}%")->count() + 1;
        $candidate = $prefix . str_pad((string) $count, 4, '0', STR_PAD_LEFT);
        while (GoodsReceipt::where('receipt_number', $candidate)->exists()) {
            $count++;
            $candidate = $prefix . str_pad((string) $count, 4, '0', STR_PAD_LEFT);
        }
        return $candidate;
    }

    protected function generateBatchNumber(string $sku): string
    {
        $cleanSku = preg_replace('/[^A-Z0-9]/', '', strtoupper($sku)) ?: 'PRD';
        $prefix = 'BTH-' . date('Ymd') . '-' . substr($cleanSku, 0, 6) . '-';
        $candidate = $prefix . strtoupper(Str::random(4));
        while (StockBatch::where('batch_number', $candidate)->exists()) {
            $candidate = $prefix . strtoupper(Str::random(4));
        }
        return $candidate;
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
                'category' => 'PURCHASE',
                'action_name' => $action,
                'description' => $description,
                'reference' => $reference,
                'status' => 'SUCCESS',
                'source' => 'WEB',
            ]);
        } catch (\Throwable $e) {
            // Non-fatal audit failure
        }
    }
}
