<?php

namespace App\Http\Controllers\Api\Kasir;

use App\Http\Controllers\Controller;
use App\Models\GoodsReceipt;
use App\Models\GoodsReceiptItem;
use App\Models\Product;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class GoodsReceiptController extends Controller
{
    /**
     * List goods receipts from warehouse / supplier.
     */
    public function index(Request $request): JsonResponse
    {
        $query = GoodsReceipt::with(['items.product', 'receiver']);

        if ($request->filled('status') && $request->status !== 'all') {
            $query->where('status', strtoupper($request->status));
        }

        if ($request->filled('q')) {
            $term = $request->q;
            $query->where(function ($q) use ($term) {
                $q->where('receipt_number', 'ilike', "%{$term}%")
                  ->orWhere('driver_name', 'ilike', "%{$term}%")
                  ->orWhere('plate_number', 'ilike', "%{$term}%")
                  ->orWhere('origin_source', 'ilike', "%{$term}%");
            });
        }

        $receipts = $query->orderBy('id', 'desc')->get();

        // Seed default goods receipts if table is empty
        if ($receipts->isEmpty() && GoodsReceipt::count() === 0) {
            $this->seedDefaults();
            $receipts = GoodsReceipt::with(['items.product', 'receiver'])->orderBy('id', 'desc')->get();
        }

        $mapped = $receipts->map(function ($r) {
            return [
                'id' => $r->id,
                'docNo' => $r->receipt_number,
                'date' => $r->received_at ? $r->received_at->translatedFormat('d F Y • H:i') . ' WIB' : now()->translatedFormat('d F Y • H:i') . ' WIB',
                'warehouseOrigin' => $r->origin_source ?: 'DC Pusat Logistik',
                'destinationStore' => 'Minimarket ILP - Pusat',
                'driverName' => $r->driver_name ?: 'Supir Pengantar',
                'plateNumber' => $r->plate_number ?: 'B 9102 UQ',
                'status' => $r->status,
                'receivedAt' => $r->received_at ? $r->received_at->format('d M Y H:i') . ' WIB' : null,
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
                }),
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $mapped,
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

                        if ($qtyRec != (float) $item->qty_sent) {
                            $hasDiscrepancy = true;
                        }

                        // Increment product stock
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

            $user = User::first();
            $receipt->status = $hasDiscrepancy ? 'DITERIMA_SELISIH' : 'DITERIMA_LENGKAP';
            $receipt->received_at = now();
            $receipt->received_by = $user?->id;
            $receipt->notes = $validated['notes'] ?? $receipt->notes;
            $receipt->save();

            return response()->json([
                'success' => true,
                'message' => 'Surat jalan ' . $receipt->receipt_number . ' berhasil diverifikasi dan stok telah ditambahkan.',
                'data' => $receipt->fresh(['items.product', 'receiver']),
            ]);
        });
    }

    /**
     * Seed initial demo records into PostgreSQL.
     */
    protected function seedDefaults(): void
    {
        $products = Product::take(6)->get();
        if ($products->isEmpty()) return;

        $user = User::first();

        $r1 = GoodsReceipt::create([
            'receipt_number' => '#SJ-DC-20261024-001',
            'received_at' => now()->subHours(3),
            'driver_name' => 'Rahmat Hidayat',
            'plate_number' => 'B 9102 UQ',
            'origin_source' => 'DC Cikarang (Pusat Logistik)',
            'status' => 'MENUNGGU_VERIFIKASI',
            'notes' => 'Pengiriman rutin stok rak minimarket',
        ]);

        foreach ($products->take(4) as $p) {
            GoodsReceiptItem::create([
                'goods_receipt_id' => $r1->id,
                'product_id' => $p->id,
                'qty_sent' => 48,
                'qty_received' => 0,
                'actual_unit_cost' => $p->cost_price ?: 10000,
                'condition' => 'BAIK',
            ]);
        }

        $r2 = GoodsReceipt::create([
            'receipt_number' => '#SJ-DC-20261023-014',
            'received_at' => now()->subDay(),
            'received_by' => $user?->id,
            'driver_name' => 'Andi Pratama',
            'plate_number' => 'B 9481 UQ',
            'origin_source' => 'DC Cibitung (Hub Sembako)',
            'status' => 'DITERIMA_LENGKAP',
            'notes' => 'Penerimaan fisik lengkap, segel boks aman.',
        ]);

        foreach ($products->skip(2)->take(3) as $p) {
            GoodsReceiptItem::create([
                'goods_receipt_id' => $r2->id,
                'product_id' => $p->id,
                'qty_sent' => 30,
                'qty_received' => 30,
                'actual_unit_cost' => $p->cost_price ?: 12000,
                'condition' => 'BAIK',
                'notes' => 'Sesuai',
            ]);
        }
    }
}
