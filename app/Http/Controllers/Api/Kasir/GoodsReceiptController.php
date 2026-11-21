<?php

namespace App\Http\Controllers\Api\Kasir;

use App\Events\StockUpdatedEvent;
use App\Http\Controllers\Api\Shared\NotificationController;
use App\Http\Controllers\Controller;
use App\Models\GoodsReceipt;
use App\Models\GoodsReceiptItem;
use App\Models\Location;
use App\Models\Product;
use App\Models\ProductStock;
use App\Models\User;
use App\Services\LocationStockService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class GoodsReceiptController extends Controller
{
    public function __construct(
        protected LocationStockService $stockService
    ) {}

    /**
     * List goods receipts from warehouse / supplier.
     */
    public function index(Request $request): JsonResponse
    {
        $query = GoodsReceipt::with(['items.product.supplier', 'receiver', 'sender', 'fromLocation', 'toLocation']);

        if ($request->query('type') !== 'purchase') {
            $query->whereNull('purchase_id');
        }

        if ($request->filled('status') && $request->status !== 'all') {
            $query->where('status', strtoupper($request->status));
        }

        $user = Auth::user();
        $reqBranch = $request->query('branch');
        $reqLocationId = $request->integer('location_id') ?: null;

        $targetLoc = null;
        if ($reqLocationId || $reqBranch) {
            $targetLoc = $this->stockService->resolveLocation(
                $reqLocationId,
                $reqBranch,
                null,
                null,
                'STORE'
            );
        } elseif ($user && $user->isKasir()) {
            $targetLoc = $this->stockService->resolveLocation(
                null,
                $user->branch,
                null,
                $user,
                'STORE'
            );
        }

        if ($targetLoc) {
            $locName = strtolower($targetLoc->name);
            $query->where(function ($sub) use ($targetLoc, $locName) {
                $sub->where('to_location_id', $targetLoc->id)
                    ->orWhereRaw('LOWER(destination_store) = ?', [$locName]);
                if (str_contains($locName, 'pusat')) {
                    $sub->orWhereRaw('LOWER(destination_store) = ?', ['pusat'])
                        ->orWhereRaw('LOWER(destination_store) = ?', ['toko pusat'])
                        ->orWhereRaw('LOWER(destination_store) = ?', ['minimarket ilp - pusat']);
                } elseif (str_contains($locName, 'cabang a') || str_contains($locName, 'sektor a')) {
                    $sub->orWhereRaw('LOWER(destination_store) = ?', ['cabang a'])
                        ->orWhereRaw('LOWER(destination_store) = ?', ['toko cabang a']);
                } elseif (str_contains($locName, 'cabang b') || str_contains($locName, 'sektor b')) {
                    $sub->orWhereRaw('LOWER(destination_store) = ?', ['cabang b'])
                        ->orWhereRaw('LOWER(destination_store) = ?', ['toko cabang b']);
                }
            });
        }

        if ($request->filled('q')) {
            $term = strtolower($request->q);
            $query->where(function ($q) use ($term) {
                $q->whereRaw('LOWER(receipt_number) LIKE ?', ["%{$term}%"])
                    ->orWhereRaw('LOWER(driver_name) LIKE ?', ["%{$term}%"])
                    ->orWhereRaw('LOWER(plate_number) LIKE ?', ["%{$term}%"])
                    ->orWhereRaw('LOWER(origin_source) LIKE ?', ["%{$term}%"])
                    ->orWhereRaw('LOWER(destination_store) LIKE ?', ["%{$term}%"]);
            });
        }

        $receipts = $query->orderBy('id', 'desc')->get();

        return response()->json([
            'success' => true,
            'data' => $receipts->map(fn ($r) => $this->formatReceipt($r)),
        ]);
    }

    /**
     * Store a new shipment / distribution document created by Manager (Gudang Pusat -> Store).
     * When dispatched (MENUNGGU_VERIFIKASI), deducts Gudang Pusat stock (FEFO/FIFO) without adding to Store Stock yet.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'docNo' => 'nullable|string|max:100',
            'receipt_number' => 'nullable|string|max:100',
            'from_location_id' => 'nullable|integer|exists:locations,id',
            'to_location_id' => 'nullable|integer|exists:locations,id',
            'warehouseOrigin' => 'nullable|string|max:150',
            'origin_source' => 'nullable|string|max:150',
            'destinationStore' => 'nullable|string|max:100',
            'destination_store' => 'nullable|string|max:100',
            'driverName' => 'nullable|string|max:100',
            'driver_name' => 'nullable|string|max:100',
            'plateNumber' => 'nullable|string|max:50',
            'plate_number' => 'nullable|string|max:50',
            'status' => 'nullable|string|max:50',
            'generalNotes' => 'nullable|string|max:500',
            'notes' => 'nullable|string|max:500',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'nullable|integer',
            'items.*.sku' => 'nullable|string|max:100',
            'items.*.name' => 'nullable|string|max:255',
            'items.*.category' => 'nullable|string|max:100',
            'items.*.unit' => 'nullable|string|max:50',
            'items.*.qtySent' => 'nullable|numeric|min:0',
            'items.*.qty_sent' => 'nullable|numeric|min:0',
            'items.*.condition' => 'nullable|string|max:50',
            'items.*.expired_date' => 'nullable|date',
            'items.*.batch_number' => 'nullable|string|max:100',
            'items.*.note' => 'nullable|string|max:255',
        ]);

        return DB::transaction(function () use ($validated) {
            $docNo = $validated['docNo'] ?? $validated['receipt_number'] ?? null;
            if (empty($docNo)) {
                $year = date('Y');
                $count = GoodsReceipt::count() + 1;
                $docNo = 'SJ-TRF-' . $year . '-' . str_pad((string) $count, 4, '0', STR_PAD_LEFT);
            }

            if (GoodsReceipt::where('receipt_number', $docNo)->exists()) {
                $docNo = $docNo . '-' . time();
            }

            $fromLoc = $this->stockService->resolveLocation(
                $validated['from_location_id'] ?? null,
                $validated['warehouseOrigin'] ?? $validated['origin_source'] ?? null,
                null,
                null,
                'WAREHOUSE'
            ) ?? $this->stockService->getWarehouseLocation();

            $destStr = $validated['destinationStore'] ?? $validated['destination_store'] ?? '-';
            $toLoc = $this->stockService->resolveLocation(
                $validated['to_location_id'] ?? null,
                $destStr !== '-' ? $destStr : null,
                null,
                null,
                'STORE'
            );

            $origin = $validated['warehouseOrigin'] ?? $validated['origin_source'] ?? ($fromLoc?->name ?? 'Gudang Pusat');
            $dest = $toLoc?->name ?? $destStr;
            $driver = $validated['driverName'] ?? $validated['driver_name'] ?? '-';
            $plate = $validated['plateNumber'] ?? $validated['plate_number'] ?? '-';
            $inputStatus = strtoupper(trim($validated['status'] ?? ''));
            if (empty($inputStatus) || $inputStatus === 'MENUNGGU_VERIFIKASI') {
                $status = 'BELUM_SAMPAI';
            } else {
                $status = $inputStatus;
            }
            $notes = $validated['generalNotes'] ?? $validated['notes'] ?? '';

            $receipt = GoodsReceipt::create([
                'receipt_number' => $docNo,
                'sent_by' => Auth::id(),
                'from_location_id' => $fromLoc?->id,
                'to_location_id' => $toLoc?->id,
                'origin_source' => $origin,
                'destination_store' => $dest,
                'driver_name' => $driver,
                'plate_number' => $plate,
                'status' => $status,
                'received_at' => now(),
                'notes' => $notes,
            ]);

            foreach ($validated['items'] as $it) {
                $qty = (int) round((float) ($it['qtySent'] ?? $it['qty_sent'] ?? 0));
                if ($qty <= 0) {
                    continue;
                }

                $sku = $it['sku'] ?? null;
                $productId = $it['product_id'] ?? null;

                $product = null;
                if ($productId) {
                    $product = Product::with('supplier')->lockForUpdate()->find($productId);
                }
                if (!$product && $sku) {
                    $product = Product::with('supplier')->where('sku', $sku)->orWhere('barcode', $sku)->lockForUpdate()->first();
                }
                if (!$product) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Produk pengiriman tidak terdaftar di Stock Master.',
                    ], 422);
                }

                $allocations = [];
                $itemExpired = $it['expired_date'] ?? ($product->expired_date ? $product->expired_date->toDateString() : null);

                // Deduct warehouse stock immediately on dispatch (in-transit) using FEFO/FIFO
                if ($status !== 'DRAFT' && $fromLoc) {
                    $hasWarehousePs = ProductStock::where('product_id', $product->id)
                        ->where('location_id', $fromLoc->id)
                        ->exists();

                    if ($hasWarehousePs) {
                        $deductRes = $this->stockService->deductStock($product->id, $fromLoc->id, $qty, [
                            'movement_type' => 'TRANSFER_OUT',
                            'reference_type' => GoodsReceipt::class,
                            'reference_id' => $receipt->id,
                            'reference_number' => $receipt->receipt_number,
                            'user_id' => Auth::id(),
                            'notes' => "Pengiriman distribusi {$receipt->receipt_number} ke {$dest}",
                        ]);

                        if (!$deductRes['success']) {
                            DB::rollBack();
                            return response()->json([
                                'success' => false,
                                'error_code' => $deductRes['error_code'] ?? 'WAREHOUSE_STOCK_INSUFFICIENT',
                                'message' => "Stok Gudang Pusat untuk [{$product->name}] tidak mencukupi: " . ($deductRes['message'] ?? ''),
                            ], 422);
                        }

                        $allocations = $deductRes['allocations'] ?? [];
                        if (!empty($allocations[0]['expired_date'])) {
                            $itemExpired = $allocations[0]['expired_date'];
                        }
                    } elseif ($product->stock >= $qty) {
                        $product->decrement('stock', $qty);
                    }
                }

                GoodsReceiptItem::create([
                    'goods_receipt_id' => $receipt->id,
                    'product_id' => $product->id,
                    'qty_sent' => $qty,
                    'qty_received' => 0,
                    'actual_unit_cost' => (float) ($allocations[0]['unit_cost'] ?? $product->cost_price ?? 0),
                    'condition' => $it['condition'] ?? 'BAIK',
                    'expired_date' => $itemExpired,
                    'batch_number' => $it['batch_number'] ?? ($allocations[0]['batch_number'] ?? null),
                    'batch_allocations' => !empty($allocations) ? $allocations : null,
                    'notes' => $it['note'] ?? ($it['notes'] ?? ''),
                ]);
            }

            $receipt->load(['items.product.supplier', 'receiver', 'fromLocation', 'toLocation']);

            if ($status !== 'DRAFT') {
                try {
                    $totalUnits = $receipt->items->sum('qty_sent');
                    NotificationController::notify(
                        role: 'KASIR',
                        title: "Surat Jalan {$receipt->receipt_number} Tiba",
                        message: "Gudang mengirimkan {$totalUnits} unit barang ke {$dest}. Siap diverifikasi kasir di terminal.",
                        category: 'TRANSFER',
                        link: '/kasir/produk/masuk',
                        icon: 'local_shipping',
                        color: 'amber',
                        referenceId: $receipt->receipt_number
                    );
                } catch (\Throwable $e) {
                }
            }

            return response()->json([
                'success' => true,
                'message' => 'Pengiriman barang ' . $receipt->receipt_number . ' berhasil disimpan.',
                'data' => $this->formatReceipt($receipt),
            ], 201);
        });
    }

    /**
     * Update shipment status (dispatch draft, mark arrived, approve, or cancel).
     */
    public function updateStatus(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'status' => 'required|string',
        ]);

        return DB::transaction(function () use ($id, $validated, $request) {
            $receipt = GoodsReceipt::with(['items.product.supplier', 'receiver', 'sender', 'fromLocation', 'toLocation'])
                ->lockForUpdate()
                ->findOrFail($id);

            $oldStatus = strtoupper($receipt->status);
            $rawInput = strtoupper(trim(str_replace(' ', '_', $validated['status'])));

            $targetStatus = match ($rawInput) {
                'SUDAH_SAMPAI' => 'SUDAH_SAMPAI',
                'APPROVED', 'DITERIMA_LENGKAP' => 'APPROVED',
                'TIDAK_SAMPAI', 'DIBATALKAN' => 'TIDAK_SAMPAI',
                'BELUM_SAMPAI', 'MENUNGGU_VERIFIKASI' => 'BELUM_SAMPAI',
                default => $rawInput,
            };

            if (in_array($oldStatus, ['APPROVED', 'DITERIMA_LENGKAP', 'DITERIMA_SELISIH'], true) && in_array($targetStatus, ['BELUM_SAMPAI', 'SUDAH_SAMPAI'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Dokumen yang sudah di-Approve tidak dapat dikembalikan ke status sebelum verifikasi.',
                ], 422);
            }

            $destLoc = $this->resolveDestinationLocation($receipt);
            $actingLoc = $this->resolveActorStoreLocation($request);

            // Validasi otorisasi gerai: kasir cabang lain dilarang memproses surat jalan milik gerai ini!
            if ($actingLoc && $destLoc && $actingLoc->id !== $destLoc->id) {
                return response()->json([
                    'success' => false,
                    'error_code' => 'FORBIDDEN_BRANCH',
                    'message' => "Pengiriman ini ditujukan khusus untuk gerai {$receipt->destination_store}. Kasir di {$actingLoc->name} tidak berwenang untuk menerima atau menyetujui dokumen ini.",
                ], 403);
            }

            // Jika di-Approve oleh kasir dan belum diverifikasi stoknya:
            if ($targetStatus === 'APPROVED' && $receipt->verified_at === null) {
                $toLoc = $destLoc;
                if (!$toLoc) {
                    return response()->json([
                        'success' => false,
                        'message' => "Lokasi gerai tujuan ({$receipt->destination_store}) tidak ditemukan dalam sistem.",
                    ], 422);
                }

                foreach ($receipt->items as $item) {
                    $qtyRec = (int) round((float) $item->qty_sent);
                    $item->qty_received = $qtyRec;
                    $item->condition = 'BAIK';
                    $item->save();

                    if ($qtyRec > 0) {
                        $this->creditStoreBatchesFromReceiptItem($item, $toLoc->id, $qtyRec, $receipt);
                    }
                }

                $receipt->to_location_id = $toLoc->id;
                $receipt->verified_at = now();
                $receipt->received_at = now();
                $receipt->received_by = Auth::id() ?? $receipt->received_by;
            } elseif ($targetStatus === 'TIDAK_SAMPAI' && in_array($oldStatus, ['BELUM_SAMPAI', 'MENUNGGU_VERIFIKASI', 'SUDAH_SAMPAI'], true)) {
                // Kembalikan alokasi stok ke gudang asal jika tidak sampai
                $fromLoc = $receipt->fromLocation ?? $this->stockService->getWarehouseLocation();
                if ($fromLoc) {
                    foreach ($receipt->items as $item) {
                        $qty = (int) round((float) $item->qty_sent);
                        if ($qty > 0) {
                            $this->stockService->addStock($item->product_id, $fromLoc->id, $qty, [
                                'movement_type' => 'RETURN_IN',
                                'unit_cost' => (float) $item->actual_unit_cost,
                                'expired_date' => $item->expired_date?->toDateString(),
                                'reference_type' => GoodsReceipt::class,
                                'reference_id' => $receipt->id,
                                'reference_number' => $receipt->receipt_number,
                                'user_id' => Auth::id(),
                                'notes' => "Pengiriman tidak sampai / batal {$receipt->receipt_number}",
                            ]);
                        }
                    }
                }
            }

            $receipt->status = $targetStatus;
            $receipt->save();

            try {
                if ($targetStatus === 'APPROVED') {
                    event(new StockUpdatedEvent([
                        'location_id' => $receipt->to_location_id,
                        'type' => 'goods_receipt_verified',
                        'receipt_number' => $receipt->receipt_number,
                    ]));

                    NotificationController::notify(
                        role: 'MANAGER',
                        title: "Barang Masuk Approved: {$receipt->receipt_number}",
                        message: "Surat jalan {$receipt->receipt_number} telah di-Approve oleh kasir di {$receipt->destination_store}. Stok toko bertambah.",
                        category: 'TRANSFER',
                        link: '/manager/kirim-barang',
                        icon: 'verified',
                        color: 'emerald',
                        referenceId: $receipt->receipt_number
                    );
                }
            } catch (\Throwable $e) {}

            return response()->json([
                'success' => true,
                'message' => 'Status surat jalan ' . $receipt->receipt_number . ' berhasil diperbarui menjadi ' . $targetStatus,
                'data' => $this->formatReceipt($receipt->fresh(['items.product.supplier', 'receiver', 'sender', 'fromLocation', 'toLocation'])),
            ]);
        });
    }

    /**
     * Verify goods receipt at destination Store and credit Stock Toko + Store StockBatch.
     * Prevents duplicate verification and keeps locations isolated.
     */
    public function verify(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'receiver_name' => 'nullable|string|max:100',
            'location_id' => 'nullable|integer|exists:locations,id',
            'branch' => 'nullable|string|max:100',
            'notes' => 'nullable|string|max:500',
            'items' => 'nullable|array',
            'items.*.id' => 'required_with:items|integer',
            'items.*.qty_received' => 'required_with:items|numeric|min:0',
            'items.*.condition' => 'nullable|string',
            'items.*.note' => 'nullable|string',
        ]);

        return DB::transaction(function () use ($id, $validated, $request) {
            $receipt = GoodsReceipt::with(['items.product.supplier', 'toLocation'])
                ->lockForUpdate()
                ->findOrFail($id);

            // Prevent duplicate receiving
            if ($receipt->verified_at !== null || in_array($receipt->status, ['DITERIMA_LENGKAP', 'DITERIMA_SELISIH'], true)) {
                return response()->json([
                    'success' => false,
                    'error_code' => 'ALREADY_VERIFIED',
                    'message' => "Dokumen {$receipt->receipt_number} sudah pernah diverifikasi sebelumnya. Penerimaan ganda ditolak.",
                ], 422);
            }

            if ($receipt->status === 'DIBATALKAN') {
                return response()->json([
                    'success' => false,
                    'message' => "Dokumen {$receipt->receipt_number} sudah dibatalkan dan tidak dapat diterima.",
                ], 422);
            }

            $destLoc = $this->resolveDestinationLocation($receipt);
            $actingLoc = $this->resolveActorStoreLocation($request);

            // Validasi otorisasi gerai: kasir cabang lain dilarang memverifikasi dokumen gerai ini!
            if ($actingLoc && $destLoc && $actingLoc->id !== $destLoc->id) {
                return response()->json([
                    'success' => false,
                    'error_code' => 'FORBIDDEN_BRANCH',
                    'message' => "Pengiriman ini ditujukan khusus untuk gerai {$receipt->destination_store}. Kasir di {$actingLoc->name} tidak berwenang untuk memverifikasi dokumen ini.",
                ], 403);
            }

            $toLoc = $destLoc;
            if (!$toLoc) {
                return response()->json([
                    'success' => false,
                    'message' => "Lokasi gerai tujuan ({$receipt->destination_store}) tidak ditemukan dalam sistem.",
                ], 422);
            }

            $hasDiscrepancy = false;
            $itemsMap = [];
            if (!empty($validated['items'])) {
                foreach ($validated['items'] as $row) {
                    $itemsMap[(int) $row['id']] = $row;
                }
            }

            foreach ($receipt->items as $item) {
                $row = $itemsMap[$item->id] ?? null;
                $qtyRec = $row !== null ? (int) round((float) $row['qty_received']) : (int) round((float) $item->qty_sent);
                $condition = strtoupper((string) ($row['condition'] ?? $item->condition ?? 'BAIK'));

                $item->qty_received = $qtyRec;
                $item->condition = $condition;
                if ($row !== null && isset($row['note'])) {
                    $item->notes = $row['note'];
                }
                $item->save();

                if ($qtyRec != (int) round((float) $item->qty_sent) || $condition !== 'BAIK') {
                    $hasDiscrepancy = true;
                }

                if ($qtyRec > 0) {
                    $this->creditStoreBatchesFromReceiptItem($item, $toLoc->id, $qtyRec, $receipt);
                }
            }

            $user = Auth::user()
                ?? (!empty($validated['receiver_name']) ? User::where('name', $validated['receiver_name'])->first() : null);

            $now = now();
            $receipt->to_location_id = $toLoc->id;
            $receipt->status = $hasDiscrepancy ? 'DITERIMA_SELISIH' : 'DITERIMA_LENGKAP';
            $receipt->received_at = $now;
            $receipt->verified_at = $now;
            $receipt->received_by = $user?->id;
            $receipt->notes = $validated['notes'] ?? $receipt->notes;
            $receipt->save();

            try {
                event(new StockUpdatedEvent([
                    'location_id' => $receipt->to_location_id,
                    'type' => 'goods_receipt_verified',
                    'receipt_number' => $receipt->receipt_number,
                ]));
            } catch (\Throwable $e) {
            }

            try {
                if ($hasDiscrepancy) {
                    NotificationController::notify(
                        role: 'MANAGER',
                        title: "Selisih Penerimaan Barang: {$receipt->receipt_number}",
                        message: "Kasir melaporkan selisih / kerusakan fisik saat penerimaan barang di {$receipt->destination_store}.",
                        category: 'TRANSFER',
                        link: '/manager/kirim-barang',
                        icon: 'warning',
                        color: 'rose',
                        referenceId: $receipt->receipt_number
                    );
                } else {
                    NotificationController::notify(
                        role: 'MANAGER',
                        title: "Penerimaan Barang Selesai: {$receipt->receipt_number}",
                        message: "Surat jalan {$receipt->receipt_number} telah diverifikasi lengkap 100% oleh kasir di {$receipt->destination_store}.",
                        category: 'TRANSFER',
                        link: '/manager/kirim-barang',
                        icon: 'check_circle',
                        color: 'emerald',
                        referenceId: $receipt->receipt_number
                    );
                }
            } catch (\Throwable $e) {
            }

            return response()->json([
                'success' => true,
                'message' => 'Surat jalan ' . $receipt->receipt_number . ' berhasil diverifikasi dan stok toko telah ditambahkan.',
                'data' => $this->formatReceipt($receipt->fresh(['items.product.supplier', 'receiver', 'fromLocation', 'toLocation'])),
            ]);
        });
    }

    /**
     * Resolve the strict, immutable destination STORE location of a goods receipt.
     * Prevents cross-branch credit misdirection.
     */
    protected function resolveDestinationLocation(GoodsReceipt $receipt): ?Location
    {
        if ($receipt->to_location_id) {
            $loc = Location::find($receipt->to_location_id);
            if ($loc && $loc->type !== 'WAREHOUSE') {
                return $loc;
            }
        }

        $destStr = trim((string) $receipt->destination_store);
        if ($destStr !== '' && $destStr !== '-') {
            return $this->stockService->resolveLocation(null, $destStr, null, null, 'STORE');
        }

        return null;
    }

    /**
     * Determine which physical store location the acting user/cashier is operating from.
     */
    protected function resolveActorStoreLocation(Request $request): ?Location
    {
        $branch = $request->input('branch') ?: $request->header('X-Cashier-Branch');
        $locationId = $request->integer('location_id') ?: null;
        $terminalCode = $request->input('terminal_code');
        $user = Auth::user();

        if ($locationId || $branch || $terminalCode) {
            return $this->stockService->resolveLocation(
                $locationId,
                $branch,
                $terminalCode,
                null,
                'STORE'
            );
        }

        if ($user && $user->isKasir()) {
            return $this->stockService->resolveLocation(
                null,
                $user->branch,
                null,
                $user,
                'STORE'
            );
        }

        return null;
    }

    /**
     * Credit received quantity into Store Stock & Store StockBatch preserving batch expiry/cost metadata.
     */
    private function creditStoreBatchesFromReceiptItem(GoodsReceiptItem $item, int $storeLocationId, int $qtyReceived, GoodsReceipt $receipt): void
    {
        $product = $item->product ?: Product::with('supplier')->find($item->product_id);
        $isConsignment = (bool) ($product?->is_consignment);
        $supplierId = $product?->supplier_id;
        $ownershipType = $isConsignment ? 'CONSIGNMENT' : 'INTERNAL';

        $allocations = is_array($item->batch_allocations) ? $item->batch_allocations : [];
        if (empty($allocations)) {
            $this->stockService->addStock($item->product_id, $storeLocationId, $qtyReceived, [
                'movement_type' => 'TRANSFER_IN',
                'goods_receipt_id' => $receipt->id,
                'goods_receipt_item_id' => $item->id,
                'supplier_id' => $supplierId,
                'ownership_type' => $ownershipType,
                'unit_cost' => (float) ($item->actual_unit_cost ?? $item->product?->cost_price ?? 0),
                'expired_date' => $item->expired_date?->toDateString() ?? $item->product?->expired_date?->toDateString(),
                'reference_type' => GoodsReceipt::class,
                'reference_id' => $receipt->id,
                'reference_number' => $receipt->receipt_number,
                'user_id' => Auth::id(),
                'notes' => "Penerimaan distribusi {$receipt->receipt_number}",
            ]);
            return;
        }

        $remainingToCredit = $qtyReceived;
        foreach ($allocations as $alloc) {
            if ($remainingToCredit <= 0) {
                break;
            }
            $allocQty = (int) ($alloc['qty'] ?? 0);
            if ($allocQty <= 0) {
                continue;
            }
            $creditNow = min($remainingToCredit, $allocQty);
            $this->stockService->addStock($item->product_id, $storeLocationId, $creditNow, [
                'movement_type' => 'TRANSFER_IN',
                'goods_receipt_id' => $receipt->id,
                'goods_receipt_item_id' => $item->id,
                'supplier_id' => $supplierId,
                'ownership_type' => $ownershipType,
                'unit_cost' => (float) ($alloc['unit_cost'] ?? $item->actual_unit_cost ?? 0),
                'expired_date' => $alloc['expired_date'] ?? $item->expired_date?->toDateString(),
                'reference_type' => GoodsReceipt::class,
                'reference_id' => $receipt->id,
                'reference_number' => $receipt->receipt_number,
                'user_id' => Auth::id(),
                'notes' => "Penerimaan distribusi {$receipt->receipt_number}",
            ]);
            $remainingToCredit -= $creditNow;
        }

        if ($remainingToCredit > 0) {
            $this->stockService->addStock($item->product_id, $storeLocationId, $remainingToCredit, [
                'movement_type' => 'TRANSFER_IN',
                'goods_receipt_id' => $receipt->id,
                'goods_receipt_item_id' => $item->id,
                'supplier_id' => $supplierId,
                'ownership_type' => $ownershipType,
                'unit_cost' => (float) ($item->actual_unit_cost ?? 0),
                'expired_date' => $item->expired_date?->toDateString(),
                'reference_type' => GoodsReceipt::class,
                'reference_id' => $receipt->id,
                'reference_number' => $receipt->receipt_number,
                'user_id' => Auth::id(),
                'notes' => "Penerimaan distribusi {$receipt->receipt_number} (lebih kirim)",
            ]);
        }
    }

    protected function formatReceipt(GoodsReceipt $r): array
    {
        $dateStr = $r->received_at
            ? $r->received_at->translatedFormat('d F Y • H:i') . ' WIB'
            : ($r->created_at ? $r->created_at->translatedFormat('d F Y • H:i') . ' WIB' : now()->translatedFormat('d F Y • H:i') . ' WIB');

        $senderName = $r->sender?->name ?? 'Manager Gudang DC';
        $senderUsername = $r->sender?->username ?? ($r->sender?->nik ?? 'manager');

        $rawStatus = strtoupper($r->status ?? 'BELUM_SAMPAI');
        $statusLabel = match ($rawStatus) {
            'APPROVED', 'DITERIMA_LENGKAP' => 'Approved',
            'SUDAH_SAMPAI' => 'Sudah sampai',
            'TIDAK_SAMPAI', 'DIBATALKAN' => 'Tidak sampai',
            default => 'Belum sampai',
        };

        return [
            'id' => $r->id,
            'docNo' => $r->receipt_number,
            'from_location_id' => $r->from_location_id,
            'to_location_id' => $r->to_location_id,
            'date' => $dateStr,
            'time' => $r->created_at ? $r->created_at->format('H:i') . ' WIB' : now()->format('H:i') . ' WIB',
            'warehouseOrigin' => $r->fromLocation?->name ?? ($r->origin_source ?: '-'),
            'destinationStore' => $r->toLocation?->name ?? ($r->destination_store ?: '-'),
            'driverName' => $r->driver_name ?: '-',
            'plateNumber' => $r->plate_number ?: '-',
            'sent_by' => $r->sent_by,
            'senderName' => $senderName,
            'senderUsername' => $senderUsername,
            'status' => $rawStatus,
            'statusLabel' => $statusLabel,
            'createdAt' => $r->created_at ? $r->created_at->toIso8601String() : now()->toIso8601String(),
            'sentAt' => $r->status !== 'DRAFT' ? ($r->created_at ? $r->created_at->format('d M Y • H:i') . ' WIB' : now()->format('d M Y • H:i') . ' WIB') : null,
            'receivedAt' => $r->verified_at ? $r->verified_at->format('d M Y • H:i') . ' WIB' : ($r->status !== 'MENUNGGU_VERIFIKASI' && $r->status !== 'BELUM_SAMPAI' && $r->status !== 'DRAFT' && $r->received_at ? $r->received_at->format('d M Y • H:i') . ' WIB' : null),
            'receiverName' => $r->receiver?->name ?: null,
            'generalNotes' => $r->notes ?: '',
            'items' => $r->items->map(function ($it) {
                $category = $it->product?->category ?? '';
                $isCoupon = !empty($category) && (str_contains(strtolower($category), 'kupon') || str_contains(strtolower($category), 'voucher'));
                $isConsignment = (bool) ($it->product?->is_consignment);
                $partnerName = $it->product?->supplier?->name ?? ($isConsignment ? 'Mitra UMKM' : null);

                $productType = $isCoupon ? 'KUPON' : ($isConsignment ? 'UMKM' : 'UMUM');
                $productTypeLabel = $isCoupon ? 'Kupon / Voucher' : ($isConsignment ? 'Produk UMKM' : 'Produk Umum');

                return [
                    'id' => $it->id,
                    'product_id' => $it->product_id,
                    'sku' => $it->product?->sku ?: 'SKU-' . $it->product_id,
                    'barcode' => $it->product?->barcode ?: ($it->product?->sku ?: '-'),
                    'name' => $it->product?->name ?: 'Produk',
                    'brand' => $it->product?->brand ?: ($partnerName ?: 'ILP'),
                    'category' => $it->product?->category ?: ($isCoupon ? 'Kupon BBM' : ($isConsignment ? 'Produk UMKM' : 'Umum')),
                    'unit' => $it->product?->unit ?: 'Pcs',
                    'qtySent' => (float) $it->qty_sent,
                    'qtyReceived' => (float) $it->qty_received,
                    'price' => (float) ($it->product?->price ?? 0),
                    'memberPrice' => (float) ($it->product?->member_price ?? $it->product?->price ?? 0),
                    'condition' => $it->condition ?: 'BAIK',
                    'expired_date' => $it->expired_date ? $it->expired_date->toDateString() : null,
                    'note' => $it->notes ?: '',
                    'is_consignment' => $isConsignment,
                    'is_coupon' => $isCoupon,
                    'product_type' => $productType,
                    'product_type_label' => $productTypeLabel,
                    'supplier_name' => $partnerName,
                ];
            })->values()->all(),
        ];
    }
}
