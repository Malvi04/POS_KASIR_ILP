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
                'selesai' => 'DITERIMA SELESAI',
            ];

            return [
                'id' => $r->id,
                'docNo' => $r->return_number,
                'branch' => $r->branch ?: ($r->fromLocation?->name ?: ($r->creator?->branch ?: 'Cabang A')),
                'branchOrigin' => $r->branch ?: ($r->fromLocation?->name ?: ($r->creator?->branch ?: 'Cabang A')),
                'cashierUsername' => $r->cashier_username ?: ($r->creator?->username ?: 'kasir1'),
                'cashier_username' => $r->cashier_username ?: ($r->creator?->username ?: 'kasir1'),
                'cashierName' => $r->creator?->name ?: ($r->cashier_username ?: 'Kasir 1'),
                'terminalCode' => $r->terminal_code ?: 'POS-01',
                'terminalId' => $r->terminal_code ?: 'POS-01',
                'posKasir' => $r->terminal_code ?: 'POS-01',
                'pos_kasir' => $r->terminal_code ?: 'POS-01',
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
                        'sku' => $it->product?->sku ?: '-',
                        'qty' => (float)$it->qty . ' ' . ($it->product?->unit ?: 'Pcs'),
                        'submittedQty' => (float)$it->qty,
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
                'statusSub' => $r->status === 'cek_fisik' ? 'Menunggu Approval Supervisor' : ($r->status === 'ditolak' ? 'Ditolak oleh Supervisor' : ($r->status === 'disetujui' ? 'Disetujui Supervisor' : 'Diproses oleh Supervisor')),
                'needsApproval' => in_array($r->status, ['cek_fisik', 'draft']),
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $transformed,
        ]);
    }

    /**
     * Create new store return document (Supports multi-item submissions).
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'direction' => 'nullable|string',
            'condition_type' => 'nullable|string',
            'branch' => 'nullable|string|max:100',
            'cashier_username' => 'nullable|string|max:100',
            'terminal_code' => 'nullable|string|max:50',
            'pos_kasir' => 'nullable|string|max:50',
            'driver_name' => 'nullable|string',
            'vehicle_plate' => 'nullable|string',
            'use_fleet' => 'nullable|boolean',
            'reason' => 'nullable|string',
            'items' => 'nullable|array|min:1',
            'items.*.product_id' => 'nullable|integer',
            'items.*.sku' => 'nullable|string',
            'items.*.barcode' => 'nullable|string',
            'items.*.qty' => 'nullable|numeric|min:1',
            'items.*.unit' => 'nullable|string',
            'items.*.condition' => 'nullable|string',
            'items.*.note' => 'nullable|string',
            'items.*.notes' => 'nullable|string',
            // Single-item fallback:
            'product_id' => 'nullable|integer',
            'sku' => 'nullable|string',
            'qty' => 'nullable|numeric|min:1',
            'unit' => 'nullable|string',
        ]);

        $rawItems = $validated['items'] ?? [];
        if (empty($rawItems)) {
            if (!empty($validated['product_id']) || !empty($validated['sku'])) {
                $rawItems[] = [
                    'product_id' => $validated['product_id'] ?? null,
                    'sku' => $validated['sku'] ?? null,
                    'qty' => $validated['qty'] ?? 1,
                    'unit' => $validated['unit'] ?? 'Pcs',
                    'condition' => $validated['condition_type'] ?? 'RUSAK',
                    'note' => $validated['reason'] ?? 'Retur barang operasional',
                ];
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Daftar produk yang akan diretur tidak boleh kosong.',
                ], 422);
            }
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

        DB::beginTransaction();
        try {
            $docNo = '#RTR-TK-' . date('Y') . '-' . str_pad((string)(StoreReturnHeader::count() + 1), 4, '0', STR_PAD_LEFT);
            $truckFee = empty($validated['use_fleet']) ? 250000 : 0;

            $totalSubtotal = 0;
            $preparedItems = [];

            foreach ($rawItems as $raw) {
                $product = null;
                if (!empty($raw['product_id'])) {
                    $product = Product::find($raw['product_id']);
                }
                if (!$product && !empty($raw['sku'])) {
                    $product = Product::where('sku', $raw['sku'])
                        ->orWhere('barcode', $raw['sku'])
                        ->orWhere('name', 'ilike', "%{$raw['sku']}%")
                        ->first();
                }
                if (!$product && !empty($raw['barcode'])) {
                    $product = Product::where('barcode', $raw['barcode'])
                        ->orWhere('sku', $raw['barcode'])
                        ->first();
                }

                if (!$product) {
                    continue;
                }

                $qty = (float) ($raw['qty'] ?? 1);
                $price = (float) ($product->cost_price ?? $product->price ?? 0);
                $lineSubtotal = $price * $qty;
                $totalSubtotal += $lineSubtotal;

                $preparedItems[] = [
                    'product' => $product,
                    'qty' => $qty,
                    'price' => $price,
                    'condition' => $raw['condition'] ?? ($validated['condition_type'] ?? 'RUSAK'),
                    'note' => $raw['note'] ?? ($raw['notes'] ?? ($validated['reason'] ?? '')),
                ];
            }

            if (empty($preparedItems)) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'Produk yang dipilih tidak valid atau tidak terdaftar di sistem.',
                ], 422);
            }

            $netAmount = max(0, $totalSubtotal - $truckFee);

            $header = StoreReturnHeader::create([
                'return_number' => $docNo,
                'from_location_id' => $cabang?->id,
                'branch' => $validated['branch'] ?? ($request->input('branch') ?: ($cabang?->name ?? $user?->branch ?? 'Cabang A')),
                'to_location_id' => $dc?->id,
                'created_by' => $user?->id,
                'cashier_username' => $validated['cashier_username'] ?? ($request->input('cashier_username') ?: ($user?->username ?? 'kasir1')),
                'terminal_code' => $validated['terminal_code'] ?? ($validated['pos_kasir'] ?? ($request->input('terminal_code') ?: ($request->input('pos_kasir') ?: 'POS-01'))),
                'returned_at' => now(),
                'direction' => $validated['direction'] ?? 'toko_ke_gudang',
                'condition_type' => $validated['condition_type'] ?? 'rusak',
                'notes' => $validated['reason'] ?? 'Pengajuan retur multi-item toko',
                'driver_name' => $validated['driver_name'] ?? '-',
                'vehicle_plate' => $validated['vehicle_plate'] ?? '-',
                'subtotal' => $totalSubtotal,
                'truck_fee' => $truckFee,
                'net_amount' => $netAmount,
                'status' => 'cek_fisik',
            ]);

            foreach ($preparedItems as $pi) {
                $prod = $pi['product'];
                $itemQty = $pi['qty'];

                StoreReturnItem::create([
                    'store_return_id' => $header->id,
                    'product_id' => $prod->id,
                    'qty' => $itemQty,
                    'disposition' => strtoupper($pi['condition'] ?? 'RUSAK'),
                    'claim_price' => $pi['price'],
                ]);

                // Kurangi stok toko di cabang asal
                $intQty = (int) round($itemQty);
                if ($cabang && \App\Models\ProductStock::where('product_id', $prod->id)->where('location_id', $cabang->id)->exists()) {
                    $stockService->deductStock($prod->id, $cabang->id, $intQty, [
                        'movement_type' => 'RETURN_OUT',
                        'allow_negative' => true,
                        'reference_type' => StoreReturnHeader::class,
                        'reference_id' => $header->id,
                        'reference_number' => $header->return_number,
                        'user_id' => $user?->id,
                        'notes' => "Retur toko ke gudang ({$header->return_number})",
                    ]);
                } elseif ($prod->stock >= $itemQty) {
                    $prod->decrement('stock', $intQty);
                }
            }

            DB::commit();

            try {
                $itemNames = collect($preparedItems)->pluck('product.name')->take(2)->join(', ');
                if (count($preparedItems) > 2) {
                    $itemNames .= ' +' . (count($preparedItems) - 2) . ' produk lainnya';
                }

                NotificationController::notify(
                    role: 'MANAGER',
                    title: "Pengajuan Retur: {$header->return_number}",
                    message: "Toko mengajukan retur [{$itemNames}] senilai Rp " . number_format($totalSubtotal, 0, ',', '.') . ". Alasan: {$header->notes}.",
                    category: 'RETURN',
                    link: '/manager/retur',
                    icon: 'assignment_return',
                    color: 'purple',
                    referenceId: $header->return_number
                );
            } catch (\Throwable $e) {}

            return response()->json([
                'success' => true,
                'message' => 'Dokumen retur multi-item berhasil dibuat dan dikirim ke Supervisor / Gudang DC.',
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
            'reason' => 'nullable|string|max:500',
            'notes' => 'nullable|string|max:500',
        ]);

        $oldStatus = $header->status;
        $newStatus = $validated['status'];

        // Jika pengajuan retur toko ditolak oleh Supervisor/Gudang
        if ($oldStatus !== 'ditolak' && $newStatus === 'ditolak') {
            // 1. Kembalikan stok toko di cabang asal jika sebelumnya dipotong saat pengajuan retur toko_ke_gudang
            if ($header->direction === 'toko_ke_gudang' && $header->from_location_id) {
                $stockService = app(\App\Services\LocationStockService::class);
                foreach ($header->items as $it) {
                    $intQty = (int) round((float) $it->qty);
                    if ($intQty > 0 && $it->product_id) {
                        $stockService->addStock($it->product_id, $header->from_location_id, $intQty, [
                            'movement_type' => 'RETURN_RESTORE_IN',
                            'reference_type' => StoreReturnHeader::class,
                            'reference_id' => $header->id,
                            'reference_number' => $header->return_number,
                            'user_id' => Auth::id(),
                            'notes' => "Pengembalian stok toko akibat retur ditolak ({$header->return_number})",
                        ]);
                    }
                }
            }

            // 2. Buat dokumen pencatatan fisik barang retur ditolak jika belum ada
            try {
                $returnInDocNo = '#RTR-IN-' . date('Y') . '-' . str_pad((string)(StoreReturnHeader::count() + 1), 4, '0', STR_PAD_LEFT);
                $reasonText = $validated['reason'] ?? ($validated['notes'] ?? 'Ditolak oleh Supervisor/Gudang');
                $inboundHeader = StoreReturnHeader::create([
                    'return_number' => $returnInDocNo,
                    'from_location_id' => $header->to_location_id,
                    'to_location_id' => $header->from_location_id,
                    'created_by' => Auth::id(),
                    'returned_at' => now(),
                    'direction' => 'gudang_ke_toko',
                    'condition_type' => 'retur_ditolak',
                    'notes' => "Pengembalian barang retur ditolak gudang (Ref: {$header->return_number}) - Alasan: {$reasonText}",
                    'driver_name' => $header->driver_name ?: '-',
                    'vehicle_plate' => $header->vehicle_plate ?: '-',
                    'subtotal' => $header->subtotal,
                    'truck_fee' => 0,
                    'net_amount' => $header->subtotal,
                    'status' => 'cek_fisik',
                ]);

                foreach ($header->items as $it) {
                    StoreReturnItem::create([
                        'store_return_id' => $inboundHeader->id,
                        'product_id' => $it->product_id,
                        'qty' => $it->qty,
                        'disposition' => 'PENGEMBALIAN_TOKO',
                        'claim_price' => $it->claim_price,
                    ]);
                }
            } catch (\Throwable $e) {}
        }

        // Jika barang masuk retur di-approve oleh kasir (status = selesai pada direction gudang_ke_toko), kembalikan ke stok toko
        if ($header->direction === 'gudang_ke_toko' && ($newStatus === 'selesai' || $newStatus === 'disetujui')) {
            $stockService = app(\App\Services\LocationStockService::class);
            $targetLocId = $header->to_location_id ?: $header->from_location_id;
            if ($targetLocId) {
                foreach ($header->items as $item) {
                    $intQty = (int) round((float) $item->qty);
                    if ($intQty > 0 && $item->product_id) {
                        $stockService->addStock($item->product_id, $targetLocId, $intQty, [
                            'movement_type' => 'RETURN_RESTORE_IN',
                            'reference_type' => StoreReturnHeader::class,
                            'reference_id' => $header->id,
                            'reference_number' => $header->return_number,
                            'user_id' => Auth::id(),
                            'notes' => "Barang masuk retur disetujui kasir ({$header->return_number})",
                        ]);
                    }
                }
            }
        }

        $updateData = ['status' => $newStatus];
        if (!empty($validated['reason'])) {
            $reasonStr = trim($validated['reason']);
            $updateData['notes'] = $header->notes ? ($header->notes . " | Alasan SPV/Gudang: " . $reasonStr) : ("Alasan SPV/Gudang: " . $reasonStr);
        } elseif (!empty($validated['notes'])) {
            $notesStr = trim($validated['notes']);
            $updateData['notes'] = $header->notes ? ($header->notes . " | " . $notesStr) : $notesStr;
        }

        $header->update($updateData);

        try {
            $readableStatus = strtoupper(str_replace('_', ' ', $validated['status']));
            $reasonText = !empty($validated['reason']) ? " Alasan: {$validated['reason']}." : '';
            NotificationController::notify(
                role: 'KASIR',
                title: "Status Retur {$header->return_number}: {$readableStatus}",
                message: "Pengajuan retur barang ke gudang telah diubah statusnya menjadi {$readableStatus}.{$reasonText}",
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
