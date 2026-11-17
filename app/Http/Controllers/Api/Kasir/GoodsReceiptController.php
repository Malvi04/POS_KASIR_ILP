<?php

namespace App\Http\Controllers\Api\Kasir;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Api\Shared\NotificationController;
use App\Models\GoodsReceipt;
use App\Models\GoodsReceiptItem;
use App\Models\Location;
use App\Models\Product;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class GoodsReceiptController extends Controller
{
    /**
     * List goods receipts from warehouse / supplier.
     */
    public function index(Request $request): JsonResponse
    {
        $query = GoodsReceipt::with(['items.product', 'receiver']);

        // Isolasi: jangan mencampur penerimaan pembelian supplier ke modul Kirim Barang / Barang Masuk Toko
        if ($request->query('type') !== 'purchase') {
            $query->whereNull('purchase_id');
        }

        if ($request->filled('status') && $request->status !== 'all') {
            $query->where('status', strtoupper($request->status));
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

        $mapped = $receipts->map(fn ($r) => $this->formatReceipt($r));

        return response()->json([
            'success' => true,
            'data' => $mapped,
        ]);
    }

    /**
     * Store a new shipment / goods receipt created by Manager (Kirim Barang).
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'docNo' => 'nullable|string|max:100',
            'receipt_number' => 'nullable|string|max:100',
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
            'items.*.note' => 'nullable|string|max:255',
        ]);

        return DB::transaction(function () use ($validated) {
            $docNo = $validated['docNo'] ?? $validated['receipt_number'] ?? null;
            if (empty($docNo)) {
                $year = date('Y');
                $count = GoodsReceipt::count() + 1;
                $docNo = 'SJ-TRF-' . $year . '-' . str_pad($count, 4, '0', STR_PAD_LEFT);
            }

            // Ensure unique docNo
            if (GoodsReceipt::where('receipt_number', $docNo)->exists()) {
                $docNo = $docNo . '-' . time();
            }

            $warehouseName = Location::where('type', 'WAREHOUSE')->value('name') ?? '-';
            $origin = $validated['warehouseOrigin'] ?? $validated['origin_source'] ?? $warehouseName;
            $dest = $validated['destinationStore'] ?? $validated['destination_store'] ?? '-';
            $driver = $validated['driverName'] ?? $validated['driver_name'] ?? '-';
            $plate = $validated['plateNumber'] ?? $validated['plate_number'] ?? '-';
            $status = strtoupper($validated['status'] ?? 'MENUNGGU_VERIFIKASI');
            $notes = $validated['generalNotes'] ?? $validated['notes'] ?? '';

            $receipt = GoodsReceipt::create([
                'receipt_number' => $docNo,
                'origin_source' => $origin,
                'destination_store' => $dest,
                'driver_name' => $driver,
                'plate_number' => $plate,
                'status' => $status,
                'received_at' => now(),
                'notes' => $notes,
            ]);

            foreach ($validated['items'] as $it) {
                $qty = (float) ($it['qtySent'] ?? $it['qty_sent'] ?? 0);
                if ($qty <= 0) continue;

                $sku = $it['sku'] ?? null;
                $productId = $it['product_id'] ?? null;

                $product = null;
                if ($productId) {
                    $product = Product::find($productId);
                }
                if (!$product && $sku) {
                    $product = Product::where('sku', $sku)->orWhere('barcode', $sku)->first();
                }
                if (!$product) {
                    $product = Product::firstOrCreate(
                        ['sku' => $sku ?: ('SKU-' . strtoupper(Str::random(6)))],
                        [
                            'barcode' => $sku ?: ('BRC-' . rand(100000, 999999)),
                            'name' => $it['name'] ?? 'Produk Transfer',
                            'category' => $it['category'] ?? 'Umum',
                            'unit' => $it['unit'] ?? 'Pcs',
                            'cost_price' => (float) ($it['cost_price'] ?? 0),
                            'price' => (float) ($it['price'] ?? 0),
                            'stock' => 0,
                            'min_stock' => 5,
                            'status' => 'HABIS',
                        ]
                    );
                }

                GoodsReceiptItem::create([
                    'goods_receipt_id' => $receipt->id,
                    'product_id' => $product->id,
                    'qty_sent' => $qty,
                    'qty_received' => 0,
                    'actual_unit_cost' => (float) ($product->cost_price ?? 0),
                    'condition' => $it['condition'] ?? 'BAIK',
                    'notes' => $it['note'] ?? ($it['notes'] ?? ''),
                ]);
            }

            $receipt->load(['items.product', 'receiver']);

            // Realtime notification to Kasir: new incoming transfer to store
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
                // Silently prevent notification errors from failing transaction
            }

            return response()->json([
                'success' => true,
                'message' => 'Pengiriman barang ' . $receipt->receipt_number . ' berhasil disimpan dan dikirim ke toko.',
                'data' => $this->formatReceipt($receipt),
            ], 201);
        });
    }

    /**
     * Update shipment status (e.g. dispatch draft or cancel draft).
     */
    public function updateStatus(Request $request, int $id): JsonResponse
    {
        $receipt = GoodsReceipt::with(['items.product', 'receiver'])->findOrFail($id);
        $validated = $request->validate([
            'status' => 'required|string|in:DRAFT,MENUNGGU_VERIFIKASI,DIBATALKAN',
        ]);

        $receipt->status = strtoupper($validated['status']);
        $receipt->save();

        return response()->json([
            'success' => true,
            'message' => 'Status surat jalan ' . $receipt->receipt_number . ' berhasil diperbarui menjadi ' . $receipt->status,
            'data' => $this->formatReceipt($receipt->fresh(['items.product', 'receiver'])),
        ]);
    }

    /**
     * Verify goods receipt and update product stock in DB.
     */
    public function verify(Request $request, int $id): JsonResponse
    {
        $receipt = GoodsReceipt::with('items')->findOrFail($id);

        $validated = $request->validate([
            'receiver_name' => 'nullable|string|max:100',
            'notes' => 'nullable|string|max:500',
            'items' => 'nullable|array',
            'items.*.id' => 'required_with:items|integer',
            'items.*.qty_received' => 'required_with:items|numeric|min:0',
            'items.*.condition' => 'nullable|string',
            'items.*.note' => 'nullable|string',
        ]);

        return DB::transaction(function () use ($receipt, $validated) {
            $hasDiscrepancy = false;

            if (!empty($validated['items'])) {
                foreach ($validated['items'] as $itData) {
                    $item = GoodsReceiptItem::where('goods_receipt_id', $receipt->id)->find($itData['id']);
                    if ($item) {
                        $qtyRec = (float) $itData['qty_received'];
                        $item->qty_received = $qtyRec;
                        if (isset($itData['condition'])) $item->condition = $itData['condition'];
                        if (isset($itData['note'])) $item->notes = $itData['note'];
                        $item->save();

                        if ($qtyRec != (float) $item->qty_sent || ($itData['condition'] ?? 'BAIK') !== 'BAIK') {
                            $hasDiscrepancy = true;
                        }

                        // Increment product stock in cashier store
                        $product = Product::find($item->product_id);
                        if ($product) {
                            $product->increment('stock', $qtyRec);
                        }
                    }
                }
            } else {
                // If items not sent, accept all sent quantities into stock
                foreach ($receipt->items as $item) {
                    $item->qty_received = $item->qty_sent;
                    $item->save();

                    $product = Product::find($item->product_id);
                    if ($product) {
                        $product->increment('stock', (float) $item->qty_sent);
                    }
                }
            }

            $user = Auth::user()
                ?? (!empty($validated['receiver_name']) ? User::where('name', $validated['receiver_name'])->first() : null);
            $receipt->status = $hasDiscrepancy ? 'DITERIMA_SELISIH' : 'DITERIMA_LENGKAP';
            $receipt->received_at = now();
            $receipt->received_by = $user?->id;
            $receipt->notes = $validated['notes'] ?? $receipt->notes;
            $receipt->save();

            // Realtime role-based notification for verification result
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
                    NotificationController::notify(
                        role: 'OWNER',
                        title: "Deviasi Stok Masuk: {$receipt->receipt_number}",
                        message: "Tercatat selisih fisik pada barang masuk di {$receipt->destination_store}. Cek berita acara serah terima.",
                        category: 'TRANSFER',
                        link: '/owner/audit-log',
                        icon: 'report_problem',
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
                // Ignore notification failure
            }

            return response()->json([
                'success' => true,
                'message' => 'Surat jalan ' . $receipt->receipt_number . ' berhasil diverifikasi dan stok telah ditambahkan.',
                'data' => $this->formatReceipt($receipt->fresh(['items.product', 'receiver'])),
            ]);
        });
    }

    /**
     * Format GoodsReceipt model into clean API array structure.
     */
    protected function formatReceipt(GoodsReceipt $r): array
    {
        $dateStr = $r->received_at
            ? $r->received_at->translatedFormat('d F Y • H:i') . ' WIB'
            : ($r->created_at ? $r->created_at->translatedFormat('d F Y • H:i') . ' WIB' : now()->translatedFormat('d F Y • H:i') . ' WIB');

        return [
            'id' => $r->id,
            'docNo' => $r->receipt_number,
            'date' => $dateStr,
            'time' => $r->created_at ? $r->created_at->format('H:i') . ' WIB' : now()->format('H:i') . ' WIB',
            'warehouseOrigin' => $r->origin_source ?: '-',
            'destinationStore' => $r->destination_store ?: '-',
            'driverName' => $r->driver_name ?: '-',
            'plateNumber' => $r->plate_number ?: '-',
            'status' => $r->status,
            'createdAt' => $r->created_at ? $r->created_at->toIso8601String() : now()->toIso8601String(),
            'sentAt' => $r->status !== 'DRAFT' ? ($r->created_at ? $r->created_at->format('d M Y • H:i') . ' WIB' : now()->format('d M Y • H:i') . ' WIB') : null,
            'receivedAt' => $r->received_at ? $r->received_at->format('d M Y • H:i') . ' WIB' : null,
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
                    'note' => $it->notes ?: '',
                ];
            })->values()->all(),
        ];
    }
}
