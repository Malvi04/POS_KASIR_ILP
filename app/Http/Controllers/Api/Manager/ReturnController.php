<?php

namespace App\Http\Controllers\Api\Manager;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Api\Shared\NotificationController;
use App\Models\Location;
use App\Models\Product;
use App\Models\StoreReturnHeader;
use App\Models\StoreReturnItem;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ReturnController extends Controller
{
    /**
     * List return documents with filters.
     */
    public function index(Request $request): JsonResponse
    {
        $query = StoreReturnHeader::with(['items.product', 'fromLocation', 'toLocation', 'creator']);

        if ($request->filled('direction') && $request->direction !== 'all') {
            $query->where('direction', $request->direction);
        }

        if ($request->filled('condition') && $request->condition !== 'all') {
            $query->where('condition_type', $request->condition);
        }

        if ($request->filled('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        if ($request->filled('q')) {
            $term = $request->q;
            $query->where(function ($q) use ($term) {
                $q->where('return_number', 'ilike', "%{$term}%")
                  ->orWhere('driver_name', 'ilike', "%{$term}%")
                  ->orWhere('vehicle_plate', 'ilike', "%{$term}%")
                  ->orWhere('notes', 'ilike', "%{$term}%");
            });
        }

        $returns = $query->orderBy('id', 'desc')->get();

        // Transform for frontend
        $transformed = $returns->map(function ($r) {
            $conditionLabels = [
                'rusak' => 'RUSAK / BOCOR',
                'ed' => 'KEDALUWARSA / H-14',
                'cacat' => 'KEMASAN CACAT',
                'overstock' => 'OVERSTOCK / RETUR DC',
            ];

            $directionLabels = [
                'toko_ke_gudang' => 'TOKO → GUDANG',
                'gudang_ke_toko' => 'GUDANG → TOKO',
                'toko_ke_supplier' => 'TOKO → SUPPLIER',
            ];

            $statusLabels = [
                'cek_fisik' => 'CEK FISIK GUDANG',
                'disetujui' => 'DISETUJUI / SELESAI',
                'ditolak' => 'DITOLAK',
                'draft' => 'DRAFT DIAJUKAN',
            ];

            return [
                'id' => $r->id,
                'docNo' => $r->return_number,
                'date' => $r->returned_at ? $r->returned_at->format('d M Y • H:i') . ' WIB' : now()->format('d M Y • H:i') . ' WIB',
                'ref' => 'Ref: ' . ($r->notes ? substr($r->notes, 0, 25) : 'RETUR-' . $r->id),
                'direction' => $r->direction,
                'directionLabel' => $directionLabels[$r->direction] ?? strtoupper(str_replace('_', ' ', $r->direction)),
                'conditionType' => $r->condition_type,
                'conditionLabel' => $conditionLabels[$r->condition_type] ?? strtoupper($r->condition_type),
                'conditionDesc' => $r->notes ?: 'Retur barang operasional',
                'products' => $r->items->map(function ($it) {
                    return [
                        'name' => $it->product?->name ?: 'Item Retur',
                        'qty' => (float)$it->qty . ' ' . ($it->product?->unit ?: 'Pcs'),
                        'color' => 'text-[#dc2626]',
                    ];
                }),
                'extraItems' => $r->items->count() > 2 ? ('+ ' . ($r->items->count() - 2) . ' item lainnya') : '',
                'subtotal' => 'Rp ' . number_format((float)$r->subtotal, 0, ',', '.'),
                'truckFee' => (float)$r->truck_fee > 0 ? ('-Rp ' . number_format((float)$r->truck_fee, 0, ',', '.')) : 'GRATIS / ARMADA INTERNAL',
                'truckFeeFree' => (float)$r->truck_fee <= 0,
                'netAmount' => 'Rp ' . number_format((float)$r->net_amount, 0, ',', '.'),
                'plate' => $r->vehicle_plate ?: '-',
                'driver' => $r->driver_name ?: '-',
                'statusKey' => $r->status,
                'statusLabel' => $statusLabels[$r->status] ?? strtoupper($r->status),
                'statusSub' => $r->status === 'cek_fisik' ? 'Menunggu Approval Supervisor' : 'Diproses oleh Supervisor',
                'needsApproval' => in_array($r->status, ['cek_fisik', 'draft']),
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $transformed,
        ]);
    }

    /**
     * Create new store return document.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'direction' => 'nullable|string',
            'condition_type' => 'nullable|string',
            'product_id' => 'nullable|exists:products,id',
            'sku' => 'nullable|string',
            'qty' => 'required|numeric|min:1',
            'unit' => 'nullable|string',
            'reason' => 'nullable|string',
            'driver_name' => 'nullable|string',
            'vehicle_plate' => 'nullable|string',
            'use_fleet' => 'nullable|boolean',
        ]);

        $product = null;
        if (!empty($validated['product_id'])) {
            $product = Product::find($validated['product_id']);
        } elseif (!empty($validated['sku'])) {
            $product = Product::where('sku', $validated['sku'])
                ->orWhere('barcode', $validated['sku'])
                ->orWhere('name', 'ilike', "%{$validated['sku']}%")
                ->first();
        }

        if (!$product) {
            return response()->json([
                'success' => false,
                'message' => 'Produk yang akan diretur tidak ditemukan di master inventaris.',
            ], 422);
        }

        $user = Auth::user();
        $stockService = app(\App\Services\LocationStockService::class);
        $cabang = $stockService->resolveLocation(
            $request->integer('from_location_id') ?: null,
            $request->input('branch'),
            null,
            $user,
            'STORE'
        ) ?: Location::where('type', 'STORE')->first() ?: Location::where('type', 'BRANCH')->first() ?: Location::first();
        $dc = $stockService->getWarehouseLocation() ?: Location::where('type', 'DC')->first() ?: Location::first();

        $price = (float) $product->cost_price;
        $qty = (float)$validated['qty'];
        $subtotal = $price * $qty;
        $truckFee = empty($validated['use_fleet']) ? 250000 : 0;
        $netAmount = max(0, $subtotal - $truckFee);

        $docNo = '#RTR-TK-' . date('Y') . '-' . str_pad((string)(StoreReturnHeader::count() + 1), 4, '0', STR_PAD_LEFT);

        DB::beginTransaction();
        try {
            $header = StoreReturnHeader::create([
                'return_number' => $docNo,
                'from_location_id' => $cabang?->id,
                'to_location_id' => $dc?->id,
                'created_by' => $user?->id,
                'returned_at' => now(),
                'direction' => $validated['direction'] ?? 'toko_ke_gudang',
                'condition_type' => $validated['condition_type'] ?? 'rusak',
                'notes' => $validated['reason'] ?? 'Pengajuan retur barang toko',
                'driver_name' => $validated['driver_name'] ?? '-',
                'vehicle_plate' => $validated['vehicle_plate'] ?? '-',
                'subtotal' => $subtotal,
                'truck_fee' => $truckFee,
                'net_amount' => $netAmount,
                'status' => 'cek_fisik',
            ]);

            if ($product) {
                StoreReturnItem::create([
                    'store_return_id' => $header->id,
                    'product_id' => $product->id,
                    'qty' => $qty,
                    'disposition' => strtoupper($validated['condition_type'] ?? 'RUSAK'),
                    'claim_price' => $price,
                ]);

                // Reduce store stock at origin location since item is being returned to DC
                $intQty = (int) round($qty);
                if ($cabang && \App\Models\ProductStock::where('product_id', $product->id)->where('location_id', $cabang->id)->exists()) {
                    $stockService->deductStock($product->id, $cabang->id, $intQty, [
                        'movement_type' => 'RETURN_OUT',
                        'allow_negative' => true,
                        'reference_type' => StoreReturnHeader::class,
                        'reference_id' => $header->id,
                        'reference_number' => $header->return_number,
                        'user_id' => $user?->id,
                        'notes' => "Retur toko ke gudang ({$header->return_number})",
                    ]);
                } elseif ($product->stock >= $qty) {
                    $product->decrement('stock', $qty);
                }
            }

            DB::commit();

            try {
                NotificationController::notify(
                    role: 'MANAGER',
                    title: "Pengajuan Retur: {$header->return_number}",
                    message: "Toko mengajukan retur barang {$product->name} ({$qty} {$product->unit}) senilai Rp " . number_format($subtotal, 0, ',', '.') . ". Alasan: {$validated['reason']}.",
                    category: 'RETURN',
                    link: '/manager/retur',
                    icon: 'assignment_return',
                    color: 'purple',
                    referenceId: $header->return_number
                );
                NotificationController::notify(
                    role: 'OWNER',
                    title: "Pengajuan Retur Toko: {$header->return_number}",
                    message: "Tercatat pengajuan retur barang ke gudang senilai Rp " . number_format($subtotal, 0, ',', '.') . '.',
                    category: 'RETURN',
                    link: '/owner/audit-log',
                    icon: 'assignment_return',
                    color: 'purple',
                    referenceId: $header->return_number
                );
            } catch (\Throwable $e) {}

            return response()->json([
                'success' => true,
                'message' => 'Dokumen retur berhasil dibuat dan diserahkan ke DC/Supervisor',
                'data' => $header->load('items.product'),
            ], 201);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Gagal membuat dokumen retur: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Supervisor approve/reject return status.
     */
    public function updateStatus(Request $request, int $id): JsonResponse
    {
        $header = StoreReturnHeader::with('items')->findOrFail($id);

        $validated = $request->validate([
            'status' => 'required|in:cek_fisik,disetujui,ditolak,selesai',
        ]);

        $oldStatus = $header->status;
        $newStatus = $validated['status'];

        // If return is rejected by supervisor/warehouse, restore deducted store stock
        if ($oldStatus !== 'ditolak' && $newStatus === 'ditolak') {
            $stockService = app(\App\Services\LocationStockService::class);
            if ($header->from_location_id) {
                foreach ($header->items as $item) {
                    $intQty = (int) round((float) $item->qty);
                    if ($intQty > 0 && $item->product_id) {
                        $stockService->addStock($item->product_id, $header->from_location_id, $intQty, [
                            'movement_type' => 'RETURN_REJECTED_IN',
                            'reference_type' => StoreReturnHeader::class,
                            'reference_id' => $header->id,
                            'reference_number' => $header->return_number,
                            'notes' => "Pengembalian stok toko akibat retur ditolak ({$header->return_number})",
                        ]);
                    }
                }
            }
        }

        $header->update(['status' => $newStatus]);

        try {
            $readableStatus = strtoupper(str_replace('_', ' ', $validated['status']));
            NotificationController::notify(
                role: 'KASIR',
                title: "Status Retur {$header->return_number}: {$readableStatus}",
                message: "Pengajuan retur barang ke gudang telah diubah statusnya menjadi {$readableStatus}.",
                category: 'RETURN',
                link: '/kasir/retur',
                icon: $validated['status'] === 'disetujui' || $validated['status'] === 'selesai' ? 'check_circle' : ($validated['status'] === 'ditolak' ? 'cancel' : 'pending'),
                color: $validated['status'] === 'disetujui' || $validated['status'] === 'selesai' ? 'emerald' : ($validated['status'] === 'ditolak' ? 'rose' : 'amber'),
                referenceId: $header->return_number
            );
        } catch (\Throwable $e) {}

        return response()->json([
            'success' => true,
            'message' => 'Status dokumen retur berhasil diperbarui menjadi ' . strtoupper($validated['status']),
            'data' => $header,
        ]);
    }

    /**
     * Delete draft return.
     */
    public function destroy(int $id): JsonResponse
    {
        $header = StoreReturnHeader::with('items')->findOrFail($id);

        // Restore store stock if draft/unapproved return is deleted
        if (in_array($header->status, ['cek_fisik', 'draft'], true)) {
            $stockService = app(\App\Services\LocationStockService::class);
            if ($header->from_location_id) {
                foreach ($header->items as $item) {
                    $intQty = (int) round((float) $item->qty);
                    if ($intQty > 0 && $item->product_id) {
                        $stockService->addStock($item->product_id, $header->from_location_id, $intQty, [
                            'movement_type' => 'RETURN_CANCELLED_IN',
                            'reference_type' => StoreReturnHeader::class,
                            'reference_id' => $header->id,
                            'reference_number' => $header->return_number,
                            'notes' => "Pengembalian stok toko akibat pembatalan dokumen retur ({$header->return_number})",
                        ]);
                    }
                }
            }
        }

        $header->delete();

        return response()->json([
            'success' => true,
            'message' => 'Dokumen retur berhasil dihapus',
        ]);
    }
}
