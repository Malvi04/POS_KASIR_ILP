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
        $query = GoodsReceipt::with(['items.product', 'receiver', 'fromLocation', 'toLocation']);

        if ($request->query('type') !== 'purchase') {
            $query->whereNull('purchase_id');
        }

        if ($request->filled('status') && $request->status !== 'all') {
            $query->where('status', strtoupper($request->status));
        }

        if ($request->filled('location_id') || $request->filled('branch')) {
            $loc = $this->stockService->resolveLocation(
                $request->integer('location_id') ?: null,
                $request->query('branch'),
                null,
                null,
                'STORE'
            );
            if ($loc) {
                $locName = strtolower($loc->name);
                $query->where(function ($sub) use ($loc, $locName) {
                    $sub->where('to_location_id', $loc->id)
                        ->orWhereRaw('LOWER(destination_store) = ?', [$locName]);
                    if (str_contains($locName, 'pusat')) {
                        $sub->orWhereRaw('LOWER(destination_store) = ?', ['pusat'])
                            ->orWhereRaw('LOWER(destination_store) = ?', ['toko pusat']);
                    }
                });
            }
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
            $status = strtoupper($validated['status'] ?? 'MENUNGGU_VERIFIKASI');
            $notes = $validated['generalNotes'] ?? $validated['notes'] ?? '';

            $receipt = GoodsReceipt::create([
                'receipt_number' => $docNo,
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
                    $product = Product::lockForUpdate()->find($productId);
                }
                if (!$product && $sku) {
                    $product = Product::where('sku', $sku)->orWhere('barcode', $sku)->lockForUpdate()->first();
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

            $receipt->load(['items.product', 'receiver', 'fromLocation', 'toLocation']);

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
     * Update shipment status (dispatch draft or cancel before verification).
     */
    public function updateStatus(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'status' => 'required|string|in:DRAFT,MENUNGGU_VERIFIKASI,DIBATALKAN',
        ]);

        return DB::transaction(function () use ($id, $validated) {
            $receipt = GoodsReceipt::with(['items.product', 'receiver', 'fromLocation', 'toLocation'])
                ->lockForUpdate()
                ->findOrFail($id);

            if (in_array($receipt->status, ['DITERIMA_LENGKAP', 'DITERIMA_SELISIH'], true) || $receipt->verified_at !== null) {
                return response()->json([
                    'success' => false,
                    'message' => 'Status dokumen yang sudah diverifikasi tidak dapat diubah.',
                ], 422);
            }

            $oldStatus = $receipt->status;
            $newStatus = strtoupper($validated['status']);
            $fromLoc = $receipt->fromLocation ?? $this->stockService->getWarehouseLocation();

            if ($oldStatus === 'DRAFT' && $newStatus === 'MENUNGGU_VERIFIKASI' && $fromLoc) {
                foreach ($receipt->items as $item) {
                    $qty = (int) round((float) $item->qty_sent);
                    if ($qty <= 0) {
                        continue;
                    }
                    $hasWarehousePs = ProductStock::where('product_id', $item->product_id)
                        ->where('location_id', $fromLoc->id)
                        ->exists();
                    if ($hasWarehousePs) {
                        $deductRes = $this->stockService->deductStock($item->product_id, $fromLoc->id, $qty, [
                            'movement_type' => 'TRANSFER_OUT',
                            'reference_type' => GoodsReceipt::class,
                            'reference_id' => $receipt->id,
                            'reference_number' => $receipt->receipt_number,
                            'user_id' => Auth::id(),
                            'notes' => "Pengiriman draft {$receipt->receipt_number}",
                        ]);
                        if (!$deductRes['success']) {
                            DB::rollBack();
                            return response()->json([
                                'success' => false,
                                'message' => 'Stok Gudang Pusat tidak mencukupi untuk mengirim draft ini.',
                            ], 422);
                        }
                        $item->batch_allocations = $deductRes['allocations'] ?? null;
                        if (!empty($deductRes['allocations'][0]['expired_date'])) {
                            $item->expired_date = $deductRes['allocations'][0]['expired_date'];
                        }
                        $item->save();
                    }
                }
            } elseif ($oldStatus === 'MENUNGGU_VERIFIKASI' && $newStatus === 'DIBATALKAN' && $fromLoc) {
                foreach ($receipt->items as $item) {
                    $qty = (int) round((float) $item->qty_sent);
                    if ($qty <= 0) {
                        continue;
                    }
                    $this->stockService->addStock($item->product_id, $fromLoc->id, $qty, [
                        'movement_type' => 'RETURN_IN',
                        'unit_cost' => (float) $item->actual_unit_cost,
                        'expired_date' => $item->expired_date?->toDateString(),
                        'reference_type' => GoodsReceipt::class,
                        'reference_id' => $receipt->id,
                        'reference_number' => $receipt->receipt_number,
                        'user_id' => Auth::id(),
                        'notes' => "Pembatalan pengiriman {$receipt->receipt_number}",
                    ]);
                }
            }

            $receipt->status = $newStatus;
            $receipt->save();

            return response()->json([
                'success' => true,
                'message' => 'Status surat jalan ' . $receipt->receipt_number . ' berhasil diperbarui menjadi ' . $receipt->status,
                'data' => $this->formatReceipt($receipt->fresh(['items.product', 'receiver', 'fromLocation', 'toLocation'])),
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

        return DB::transaction(function () use ($id, $validated) {
            $receipt = GoodsReceipt::with(['items.product', 'toLocation'])
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

            $toLoc = $receipt->toLocation ?? $this->stockService->resolveLocation(
                $validated['location_id'] ?? null,
                $validated['branch'] ?? ($receipt->destination_store !== '-' ? $receipt->destination_store : null),
                null,
                Auth::user(),
                'STORE'
            );

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
                    if ($toLoc) {
                        $this->creditStoreBatchesFromReceiptItem($item, $toLoc->id, $qtyRec, $receipt);
                    } else {
                        $product = Product::lockForUpdate()->find($item->product_id);
                        if ($product) {
                            $product->increment('stock', $qtyRec);
                        }
                    }
                }
            }

            $user = Auth::user()
                ?? (!empty($validated['receiver_name']) ? User::where('name', $validated['receiver_name'])->first() : null);

            $now = now();
            $receipt->to_location_id = $toLoc?->id ?? $receipt->to_location_id;
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
                'data' => $this->formatReceipt($receipt->fresh(['items.product', 'receiver', 'fromLocation', 'toLocation'])),
            ]);
        });
    }

    /**
     * Credit received quantity into Store Stock & Store StockBatch preserving batch expiry/cost metadata.
     */
    private function creditStoreBatchesFromReceiptItem(GoodsReceiptItem $item, int $storeLocationId, int $qtyReceived, GoodsReceipt $receipt): void
    {
        $allocations = is_array($item->batch_allocations) ? $item->batch_allocations : [];
        if (empty($allocations)) {
            $this->stockService->addStock($item->product_id, $storeLocationId, $qtyReceived, [
                'movement_type' => 'TRANSFER_IN',
                'goods_receipt_id' => $receipt->id,
                'goods_receipt_item_id' => $item->id,
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
            'status' => $r->status,
            'createdAt' => $r->created_at ? $r->created_at->toIso8601String() : now()->toIso8601String(),
            'sentAt' => $r->status !== 'DRAFT' ? ($r->created_at ? $r->created_at->format('d M Y • H:i') . ' WIB' : now()->format('d M Y • H:i') . ' WIB') : null,
            'receivedAt' => $r->verified_at ? $r->verified_at->format('d M Y • H:i') . ' WIB' : ($r->status !== 'MENUNGGU_VERIFIKASI' && $r->status !== 'DRAFT' && $r->received_at ? $r->received_at->format('d M Y • H:i') . ' WIB' : null),
            'receiverName' => $r->receiver?->name ?: null,
            'generalNotes' => $r->notes ?: '',
            'items' => $r->items->map(function ($it) {
                return [
                    'id' => $it->id,
                    'product_id' => $it->product_id,
                    'sku' => $it->product?->sku ?: 'SKU-' . $it->product_id,
                    'name' => $it->product?->name ?: 'Produk',
                    'category' => $it->product?->category ?: 'Umum',
                    'unit' => $it->product?->unit ?: 'Pcs',
                    'qtySent' => (float) $it->qty_sent,
                    'qtyReceived' => (float) $it->qty_received,
                    'condition' => $it->condition ?: 'BAIK',
                    'expired_date' => $it->expired_date ? $it->expired_date->toDateString() : null,
                    'note' => $it->notes ?: '',
                ];
            })->values()->all(),
        ];
    }
}
