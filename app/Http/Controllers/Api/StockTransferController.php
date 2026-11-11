<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Location;
use App\Models\Product;
use App\Models\StockTransfer;
use App\Models\StockTransferItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StockTransferController extends Controller
{
    /** Status DB -> kode status yang dipakai frontend. */
    private const STATUS_TO_FE = [
        StockTransfer::DRAFT => 'DRAFT',
        StockTransfer::IN_TRANSIT => 'MENUNGGU_VERIFIKASI',
        StockTransfer::RECEIVED => 'DITERIMA_LENGKAP',
        StockTransfer::RECEIVED_DISCREPANCY => 'DITERIMA_SELISIH',
        StockTransfer::CANCELLED => 'DIBATALKAN',
    ];

    /**
     * Master data untuk form: toko tujuan + produk (beserta stok).
     */
    public function options(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => [
                'origin' => $this->origin()?->only(['id', 'code', 'name']),
                'destinations' => Location::where('type', Location::TYPE_STORE)
                    ->where('is_active', true)->orderBy('id')->get(['id', 'code', 'name']),
                'products' => Product::orderBy('name')->get(['sku', 'name', 'category', 'unit', 'stock'])
                    ->map(fn ($p) => [
                        'sku' => $p->sku,
                        'name' => $p->name,
                        'category' => $p->category,
                        'unit' => $p->unit,
                        'stock' => (int) $p->stock,
                    ]),
            ],
        ]);
    }

    /**
     * Daftar surat jalan.
     * Query: status (kode FE), destination_id, q, incoming=1 (sisi toko: tanpa draft & batal).
     */
    public function index(Request $request): JsonResponse
    {
        $query = StockTransfer::with(['items.product', 'fromLocation', 'toLocation'])->orderByDesc('id');

        if ($request->filled('status') && $request->status !== 'all') {
            $db = array_search(strtoupper($request->status), self::STATUS_TO_FE, true);
            $query->where('status', $db ?: strtoupper($request->status));
        }
        if ($request->filled('destination_id')) {
            $query->where('to_location_id', (int) $request->destination_id);
        }
        if ($request->boolean('incoming')) {
            $query->whereNotIn('status', [StockTransfer::DRAFT, StockTransfer::CANCELLED]);
        }
        if ($request->filled('q')) {
            $term = '%' . $request->q . '%';
            $query->where(function ($q) use ($term) {
                $q->where('transfer_number', 'ilike', $term)
                    ->orWhere('request_ref', 'ilike', $term)
                    ->orWhere('driver_name', 'ilike', $term)
                    ->orWhere('plate_number', 'ilike', $term)
                    ->orWhereHas('toLocation', fn ($l) => $l->where('name', 'ilike', $term))
                    ->orWhereHas('items.product', fn ($p) => $p->where('name', 'ilike', $term)->orWhere('sku', 'ilike', $term));
            });
        }

        return response()->json([
            'success' => true,
            'data' => $query->get()->map(fn ($t) => $this->format($t)),
        ]);
    }

    public function show(int $id): JsonResponse
    {
        $t = StockTransfer::with(['items.product', 'fromLocation', 'toLocation'])->findOrFail($id);

        return response()->json(['success' => true, 'data' => $this->format($t)]);
    }

    /**
     * Buat pengiriman. mode=draft -> simpan saja; mode=kirim (default) -> kirim & potong stok.
     */
    public function store(Request $request): JsonResponse
    {
        $v = $request->validate([
            'mode' => 'nullable|in:draft,kirim',
            'destinationId' => 'required|integer|exists:locations,id',
            'requestRef' => 'nullable|string|max:100',
            'driverName' => 'required|string|max:100',
            'plateNumber' => 'required|string|max:50',
            'date' => 'nullable|date',
            'generalNotes' => 'nullable|string|max:500',
            'items' => 'required|array|min:1',
            'items.*.sku' => 'required|string|distinct|exists:products,sku',
            'items.*.qtySent' => 'required|numeric|gt:0',
        ]);

        $origin = $this->origin();
        if (!$origin) {
            throw ValidationException::withMessages(['origin' => 'Lokasi gudang asal (DC) belum terdaftar.']);
        }
        if (Location::findOrFail($v['destinationId'])->type !== Location::TYPE_STORE) {
            throw ValidationException::withMessages(['destinationId' => 'Tujuan harus berupa toko/cabang.']);
        }

        $dispatch = ($v['mode'] ?? 'kirim') === 'kirim';

        $transfer = DB::transaction(function () use ($v, $dispatch, $request, $origin) {
            $transfer = StockTransfer::create([
                'transfer_number' => 'TMP-' . uniqid(),
                'from_location_id' => $origin->id,
                'to_location_id' => $v['destinationId'],
                'request_ref' => $v['requestRef'] ?? null,
                'driver_name' => $v['driverName'],
                'plate_number' => strtoupper($v['plateNumber']),
                'transfer_date' => $v['date'] ?? now()->toDateString(),
                'notes' => $v['generalNotes'] ?? null,
                'status' => StockTransfer::DRAFT,
                'created_by' => optional($request->user())->id,
            ]);
            $transfer->update(['transfer_number' => sprintf('SJ-TRF-%s-%04d', now()->year, $transfer->id)]);

            $products = Product::whereIn('sku', collect($v['items'])->pluck('sku'))->get()->keyBy('sku');
            foreach ($v['items'] as $it) {
                $transfer->items()->create([
                    'product_id' => $products[$it['sku']]->id,
                    'qty_sent' => $it['qtySent'],
                ]);
            }

            if ($dispatch) {
                $this->dispatchTransfer($transfer->load('items'));
            }

            return $transfer;
        });

        return response()->json([
            'success' => true,
            'message' => $dispatch
                ? "Dokumen pengiriman {$transfer->transfer_number} berhasil dibuat dan dikirim."
                : "Draft pengiriman {$transfer->transfer_number} berhasil disimpan.",
            'data' => $this->format($transfer->fresh(['items.product', 'fromLocation', 'toLocation'])),
        ], 201);
    }

    /** Kirim draft: stok dipotong, status -> IN_TRANSIT. */
    public function dispatch(int $id): JsonResponse
    {
        $transfer = DB::transaction(function () use ($id) {
            $transfer = StockTransfer::with('items')->lockForUpdate()->findOrFail($id);
            abort_unless($transfer->status === StockTransfer::DRAFT, 409, 'Hanya dokumen draft yang dapat dikirim.');
            $this->dispatchTransfer($transfer);

            return $transfer;
        });

        return response()->json([
            'success' => true,
            'message' => "Dokumen {$transfer->transfer_number} berhasil dikirim.",
            'data' => $this->format($transfer->fresh(['items.product', 'fromLocation', 'toLocation'])),
        ]);
    }

    public function cancel(int $id): JsonResponse
    {
        $transfer = DB::transaction(function () use ($id) {
            $transfer = StockTransfer::lockForUpdate()->findOrFail($id);
            abort_unless($transfer->status === StockTransfer::DRAFT, 409, 'Hanya dokumen draft yang dapat dibatalkan.');
            $transfer->update(['status' => StockTransfer::CANCELLED]);

            return $transfer;
        });

        return response()->json([
            'success' => true,
            'message' => "Draft {$transfer->transfer_number} dibatalkan.",
            'data' => $this->format($transfer->fresh(['items.product', 'fromLocation', 'toLocation'])),
        ]);
    }

    /**
     * Sisi toko: verifikasi penerimaan. Stok bertambah sebesar qty diterima.
     * Hanya dari status IN_TRANSIT, sehingga verifikasi ganda ditolak (409).
     */
    public function receive(Request $request, int $id): JsonResponse
    {
        $v = $request->validate([
            'receiver_name' => 'nullable|string|max:100',
            'notes' => 'nullable|string|max:500',
            'items' => 'nullable|array',
            'items.*.id' => 'required_with:items|integer',
            'items.*.qty_received' => 'required_with:items|numeric|min:0',
            'items.*.condition' => 'nullable|string|max:50',
            'items.*.note' => 'nullable|string|max:255',
        ]);

        $transfer = DB::transaction(function () use ($id, $v, $request) {
            $transfer = StockTransfer::with('items')->lockForUpdate()->findOrFail($id);
            abort_unless($transfer->status === StockTransfer::IN_TRANSIT, 409, 'Dokumen ini tidak sedang menunggu verifikasi.');

            $input = collect($v['items'] ?? [])->keyBy('id');
            $diff = false;

            foreach ($transfer->items as $item) {
                $row = $input->get($item->id);
                $qty = $row ? (float) $row['qty_received'] : (float) $item->qty_sent;
                if ($qty > $item->qty_sent) {
                    throw ValidationException::withMessages(['items' => 'Qty diterima tidak boleh melebihi qty dikirim.']);
                }
                $item->update([
                    'qty_received' => $qty,
                    'condition' => $row['condition'] ?? $item->condition,
                    'notes' => $row['note'] ?? $item->notes,
                ]);
                $diff = $diff || $qty != (float) $item->qty_sent;
                Product::whereKey($item->product_id)->increment('stock', (int) round($qty));
            }

            $transfer->update([
                'status' => $diff ? StockTransfer::RECEIVED_DISCREPANCY : StockTransfer::RECEIVED,
                'received_at' => now(),
                'receiver_name' => $v['receiver_name'] ?? optional($request->user())->name,
                'notes' => $v['notes'] ?? $transfer->notes,
            ]);

            return $transfer;
        });

        return response()->json([
            'success' => true,
            'message' => "Surat jalan {$transfer->transfer_number} berhasil diverifikasi dan stok ditambahkan.",
            'data' => $this->format($transfer->fresh(['items.product', 'fromLocation', 'toLocation'])),
        ]);
    }

    private function origin(): ?Location
    {
        return Location::where('type', Location::TYPE_WAREHOUSE)->where('is_active', true)->orderBy('id')->first();
    }

    private function dispatchTransfer(StockTransfer $transfer): void
    {
        $products = Product::whereIn('id', $transfer->items->pluck('product_id'))->lockForUpdate()->get()->keyBy('id');

        foreach ($transfer->items as $item) {
            $p = $products[$item->product_id];
            if ($item->qty_sent > $p->stock) {
                throw ValidationException::withMessages([
                    'items' => "Stok {$p->name} tidak mencukupi (butuh {$item->qty_sent}, tersedia {$p->stock}).",
                ]);
            }
        }
        foreach ($transfer->items as $item) {
            Product::whereKey($item->product_id)->decrement('stock', (int) round($item->qty_sent));
        }

        $transfer->update(['status' => StockTransfer::IN_TRANSIT, 'shipped_at' => now()]);
    }

    private function format(StockTransfer $t): array
    {
        return [
            'id' => $t->id,
            'docNo' => $t->transfer_number,
            'date' => ($t->transfer_date ?? $t->created_at)?->format('d-m-Y'),
            'time' => $t->shipped_at ? $t->shipped_at->format('H:i') . ' WIB' : null,
            'warehouseOrigin' => $t->fromLocation?->name,
            'destinationId' => $t->to_location_id,
            'destinationStore' => $t->toLocation?->name,
            'requestRef' => $t->request_ref ?: '-',
            'driverName' => $t->driver_name,
            'plateNumber' => $t->plate_number,
            'status' => self::STATUS_TO_FE[$t->status] ?? $t->status,
            'generalNotes' => $t->notes ?? '',
            'sentAt' => $t->shipped_at?->toIso8601String(),
            'receivedAt' => $t->received_at?->toIso8601String(),
            'receiverName' => $t->receiver_name,
            'items' => $t->items->map(fn (StockTransferItem $i) => [
                'id' => $i->id,
                'sku' => $i->product?->sku,
                'name' => $i->product?->name,
                'category' => $i->product?->category,
                'unit' => $i->product?->unit,
                'qtySent' => $i->qty_sent,
                'qtyReceived' => $i->qty_received,
                'condition' => $i->condition,
                'note' => $i->notes ?? '',
            ])->values(),
        ];
    }
}
