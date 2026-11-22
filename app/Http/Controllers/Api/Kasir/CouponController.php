<?php

namespace App\Http\Controllers\Api\Kasir;

use App\Http\Controllers\Controller;
use App\Models\CouponRedemption;
use App\Models\CouponUnit;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CouponController extends Controller
{
    /**
     * List all coupons with filters (category, status, search).
     */
    public function index(Request $request): JsonResponse
    {
        $category = trim($request->query('category', 'all'));
        $status = trim($request->query('status', 'all'));
        $search = trim($request->query('search', ''));
        $branch = trim($request->query('branch', ''));
        $perPage = max(5, min(100, (int) $request->query('per_page', 50)));

        $authUser = \Illuminate\Support\Facades\Auth::user();
        $isKasir = $authUser && $authUser->isKasir();
        if ($isKasir && empty($branch)) {
            $branch = $authUser->branch ?: ($request->header('X-Branch') ?: 'Cabang Pusat');
        }

        $locationId = $request->integer('location_id') ?: ($request->header('X-Location-Id') ? (int) $request->header('X-Location-Id') : null);
        /** @var \App\Services\LocationStockService $stockService */
        $stockService = app(\App\Services\LocationStockService::class);
        $targetLoc = null;
        if ($locationId || ($branch !== '' && $branch !== 'all')) {
            $targetLoc = $stockService->resolveLocation($locationId, $branch ?: null, null, $authUser, 'STORE');
        }

        $query = CouponUnit::with(['redemptions.redeemedByUser', 'product', 'location'])->latest('id');

        if ($category !== '' && $category !== 'all') {
            $query->where('category', strtolower($category));
        }

        if ($targetLoc) {
            $query->where(function ($q) use ($targetLoc) {
                $q->where('location_id', $targetLoc->id)
                  ->orWhere('branch', $targetLoc->name);
            });
        } elseif ($branch !== '' && $branch !== 'all') {
            $query->where(function ($q) use ($branch) {
                $q->where('branch', 'like', "%{$branch}%")
                  ->orWhereHas('location', fn($lq) => $lq->where('name', 'like', "%{$branch}%"));
            });
        } elseif ($isKasir) {
            $query->where(function ($q) {
                $q->whereNotNull('location_id')
                  ->where('location_id', '!=', 1);
            })->where('branch', '!=', 'Gudang Pusat');
        }

        if ($status !== '' && $status !== 'all') {
            $query->where('status', strtoupper($status));
        }

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('serial_number', 'like', "%{$search}%")
                  ->orWhere('recipient_name', 'like', "%{$search}%")
                  ->orWhere('recipient_nik', 'like', "%{$search}%")
                  ->orWhere('vehicle_no', 'like', "%{$search}%")
                  ->orWhere('notes', 'like', "%{$search}%")
                  ->orWhereHas('product', fn($pq) => $pq->where('name', 'like', "%{$search}%")->orWhere('sku', 'like', "%{$search}%"));
            });
        }

        $allMatching = (clone $query)->get();
        $totalCount = $allMatching->count();
        $activeCount = $allMatching->whereIn('status', ['AVAILABLE', 'ISSUED', 'TERVALIDASI', 'SIAP_AMBIL'])->count();
        $redeemedCount = $allMatching->whereIn('status', ['REDEEMED', 'SELESAI'])->count();
        $totalNominal = (float) $allMatching->sum('nominal_value');

        $paginated = $query->paginate($perPage);

        $coupons = collect($paginated->items())->map(function ($c) {
            $categoryInfo = match(strtolower($c->category)) {
                'bbm' => [
                    'label' => 'BBM & Armada',
                    'badgeClass' => 'bg-blue-50 text-blue-700 border-blue-200',
                    'dotClass' => 'bg-blue-500',
                    'defaultDetail' => 'BBM Pertalite (Armada Toko)'
                ],
                'beras' => [
                    'label' => 'Beras Subsidi',
                    'badgeClass' => 'bg-emerald-50 text-emerald-700 border-emerald-200',
                    'dotClass' => 'bg-emerald-500',
                    'defaultDetail' => 'Beras Premium Subsidi 5kg'
                ],
                'galon' => [
                    'label' => 'Galon Air Mineral',
                    'badgeClass' => 'bg-cyan-50 text-cyan-700 border-cyan-200',
                    'dotClass' => 'bg-cyan-500',
                    'defaultDetail' => 'Tukar Galon Aqua 19L'
                ],
                'pulsa' => [
                    'label' => 'Kuota & Pulsa',
                    'badgeClass' => 'bg-orange-50 text-orange-700 border-orange-200',
                    'dotClass' => 'bg-orange-500',
                    'defaultDetail' => 'Paket Data Driver & Operasional'
                ],
                'produk' => [
                    'label' => 'Produk Khusus',
                    'badgeClass' => 'bg-amber-50 text-amber-700 border-amber-200',
                    'dotClass' => 'bg-amber-500',
                    'defaultDetail' => $c->product?->name ?? 'Produk Alokasi Cabang'
                ],
                default => [
                    'label' => ucfirst($c->category),
                    'badgeClass' => 'bg-gray-50 text-gray-700 border-gray-200',
                    'dotClass' => 'bg-gray-500',
                    'defaultDetail' => $c->product?->name ?? 'Voucher Koperasi'
                ]
            };

            $statusLabel = match($c->status) {
                'AVAILABLE', 'TERVALIDASI' => 'Tervalidasi (Siap Pakai)',
                'ISSUED', 'SIAP_AMBIL' => 'Siap Ambil di Kasir',
                'REDEEMED', 'SELESAI' => 'Selesai Ditukar',
                'EXPIRED' => 'Kedaluwarsa',
                default => $c->status
            };

            $detailText = $c->product?->name ?: ($c->notes ?: $categoryInfo['defaultDetail']);

            return [
                'id' => $c->serial_number,
                'serial_number' => $c->serial_number,
                'raw_id' => $c->id,
                'category' => $c->category,
                'categoryLabel' => $categoryInfo['label'],
                'detail' => $detailText,
                'itemDetail' => $detailText,
                'product_id' => $c->product_id,
                'product_name' => $c->product?->name ?? null,
                'product_sku' => $c->product?->sku ?? null,
                'product_price' => (float) ($c->product?->price ?? 0),
                'branch' => $c->branch ?? ($c->location?->name ?? 'Semua Cabang'),
                'quota' => (int) ($c->quota ?? 1),
                'qty' => $c->volume ?? '1 Unit',
                'volume' => $c->volume ?? '1 Unit',
                'recipient' => $c->recipient_name,
                'recipientName' => $c->recipient_name,
                'recipient_name' => $c->recipient_name,
                'nik' => $c->recipient_nik ?? '-',
                'recipientNik' => $c->recipient_nik ?? '-',
                'dept' => $c->recipient_dept ?? 'Operasional Toko',
                'recipientRole' => $c->recipient_dept ?? 'Operasional Toko',
                'vehicleNo' => $c->vehicle_no ?? '-',
                'vehicle_no' => $c->vehicle_no ?? '-',
                'nominal' => (float) $c->nominal_value,
                'nominal_value' => (float) $c->nominal_value,
                'status' => $c->status,
                'statusLabel' => $statusLabel,
                'badgeClass' => $categoryInfo['badgeClass'],
                'dotClass' => $categoryInfo['dotClass'],
                'notes' => $c->notes,
                'date' => $c->created_at ? $c->created_at->setTimezone('Asia/Jakarta')->format('d M Y • H:i') . ' WIB' : '-',
                'created_at' => $c->created_at ? $c->created_at->setTimezone('Asia/Jakarta')->format('d M Y H:i') . ' WIB' : '-',
            ];
        });

        return response()->json([
            'success' => true,
            'summary' => [
                'total_count' => $totalCount,
                'active_count' => $activeCount,
                'redeemed_count' => $redeemedCount,
                'total_nominal' => $totalNominal,
            ],
            'data' => $coupons,
            'pagination' => [
                'current_page' => $paginated->currentPage(),
                'last_page' => $paginated->lastPage(),
                'per_page' => $paginated->perPage(),
                'total' => $paginated->total(),
            ],
        ]);
    }

    /**
     * Get available products and branches for issuing coupons.
     */
    public function options(Request $request): JsonResponse
    {
        $products = \App\Models\Product::select('id', 'name', 'sku', 'barcode', 'price', 'unit', 'category', 'status')
            ->orderBy('name', 'asc')
            ->get();

        $locations = \App\Models\Location::where('is_active', true)
            ->select('id', 'code', 'name', 'type')
            ->orderBy('name', 'asc')
            ->get();

        $branches = ['Cabang Pusat', 'Cabang A', 'Cabang B'];
        foreach ($locations as $loc) {
            if (!in_array($loc->name, $branches)) {
                $branches[] = $loc->name;
            }
        }

        return response()->json([
            'success' => true,
            'data' => [
                'products' => $products,
                'locations' => $locations,
                'branches' => array_values(array_unique($branches)),
                'categories' => [
                    ['id' => 'bbm', 'label' => 'BBM & Armada Toko'],
                    ['id' => 'beras', 'label' => 'Beras Subsidi'],
                    ['id' => 'galon', 'label' => 'Galon Air Mineral'],
                    ['id' => 'pulsa', 'label' => 'Pulsa Operasional'],
                ],
            ],
        ]);
    }

    /**
     * Store new coupon.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'product_id' => 'nullable|integer|exists:products,id',
            'branch' => 'nullable|string|max:100',
            'quota' => 'nullable|integer|min:1',
            'category' => 'required|string|max:50',
            'serial_number' => 'nullable|string|max:100|unique:coupon_units,serial_number',
            'recipient_name' => 'nullable|string|max:150',
            'recipient_nik' => 'nullable|string|max:50',
            'recipient_dept' => 'nullable|string|max:100',
            'vehicle_no' => 'nullable|string|max:50',
            'volume' => 'nullable|string|max:50',
            'nominal_value' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string',
            'status' => 'nullable|string|max:50',
        ]);

        $product = !empty($validated['product_id']) ? \App\Models\Product::find($validated['product_id']) : null;

        $catKey = strtolower($validated['category']);
        $prefix = match($catKey) {
            'bbm' => '#KPN-BBM-',
            'beras' => '#KPN-BRS-',
            'galon' => '#KPN-GLN-',
            'pulsa' => '#KPN-PLS-',
            'produk' => '#KPN-PRD-',
            default => '#KPN-VCH-'
        };

        $serial = !empty($validated['serial_number'])
            ? $validated['serial_number']
            : $prefix . mt_rand(1000, 9999);

        $nominal = isset($validated['nominal_value']) && (float) $validated['nominal_value'] > 0
            ? (float) $validated['nominal_value']
            : ($product ? (float) $product->price : 50000.0);

        $categoryName = match($catKey) {
            'bbm' => 'Kupon BBM',
            'beras' => 'Kupon Beras',
            'galon' => 'Kupon Galon',
            'pulsa' => 'Kupon Pulsa',
            default => 'Kupon / Voucher',
        };

        if (!$product) {
            $productName = !empty($validated['notes'])
                ? (Str::startsWith($validated['notes'], 'Kupon') ? $validated['notes'] : 'Kupon ' . $validated['notes'])
                : ('Kupon ' . strtoupper($catKey) . ' ' . ($validated['volume'] ?? '1 Unit'));

            $cleanBarcode = preg_replace('/[^A-Za-z0-9\-]/', '', $serial);
            if (empty($cleanBarcode)) {
                $cleanBarcode = 'KPN-' . strtoupper($catKey) . '-' . mt_rand(10000, 99999);
            }

            $candidateSku = $cleanBarcode;
            $existingProd = \App\Models\Product::where('barcode', $candidateSku)->orWhere('sku', $candidateSku)->first();
            if ($existingProd) {
                $product = $existingProd;
            } else {
                $product = \App\Models\Product::create([
                    'sku' => $candidateSku,
                    'barcode' => $candidateSku,
                    'name' => $productName,
                    'category' => $categoryName,
                    'unit' => 'Lembar',
                    'cost_price' => $nominal > 0 ? $nominal : 50000.0,
                    'price' => $nominal > 0 ? $nominal : 50000.0,
                    'member_price' => $nominal > 0 ? $nominal : 50000.0,
                    'stock' => 0,
                    'min_stock' => 5,
                    'is_taxable' => false,
                    'tax_rate' => 0,
                    'status' => 'AKTIF',
                    'is_consignment' => false,
                ]);
            }
        }

        // Add/ensure warehouse stock in Gudang Pusat (GDG-001) for "Kirim Barang"
        $quota = max(1, (int) ($validated['quota'] ?? 10));
        /** @var \App\Services\LocationStockService $stockService */
        $stockService = app(\App\Services\LocationStockService::class);
        $stockService->ensureDefaultLocations();
        $warehouseLoc = $stockService->getWarehouseLocation();

        if ($warehouseLoc && $product) {
            $stockService->addStock(
                $product->id,
                $warehouseLoc->id,
                $quota,
                [
                    'reference_type' => 'COUPON_INIT',
                    'reference_id' => $product->id,
                    'reference_number' => $serial,
                    'notes' => "Stok awal kupon {$serial} di gudang pusat untuk pengiriman ke cabang",
                ]
            );
        }

        $notes = $validated['notes'] ?? ($product ? "Kupon penukaran produk: {$product->name}" : null);
        $initLocId = $warehouseLoc?->id ?? 1;
        $initBranchName = $warehouseLoc?->name ?? 'Gudang Pusat';

        $coupon = CouponUnit::create([
            'product_id' => $product ? $product->id : null,
            'location_id' => $initLocId,
            'branch' => $initBranchName,
            'quota' => $quota,
            'allocated_by' => \Illuminate\Support\Facades\Auth::id(),
            'category' => $catKey,
            'serial_number' => $serial,
            'recipient_name' => $validated['recipient_name'] ?? '-',
            'recipient_nik' => $validated['recipient_nik'] ?? null,
            'recipient_dept' => $validated['recipient_dept'] ?? 'Operasional Toko',
            'vehicle_no' => $validated['vehicle_no'] ?? null,
            'volume' => $validated['volume'] ?? ($product ? '1 ' . ($product->unit ?: 'Lembar') : '1 Unit'),
            'nominal_value' => $nominal,
            'notes' => $notes,
            'status' => $validated['status'] ?? 'TERVALIDASI',
        ]);

        return response()->json([
            'success' => true,
            'message' => "Kupon [{$coupon->serial_number}] berhasil diterbitkan dan stok masuk ke Gudang Pusat.",
            'data' => $coupon->load(['product', 'location']),
        ], 201);
    }

    /**
     * Update coupon details.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $coupon = CouponUnit::findOrFail($id);

        $validated = $request->validate([
            'product_id' => 'nullable|integer|exists:products,id',
            'branch' => 'nullable|string|max:100',
            'quota' => 'nullable|integer|min:1',
            'category' => 'sometimes|string|max:50',
            'recipient_name' => 'sometimes|string|max:150',
            'recipient_nik' => 'nullable|string|max:50',
            'recipient_dept' => 'nullable|string|max:100',
            'vehicle_no' => 'nullable|string|max:50',
            'volume' => 'nullable|string|max:50',
            'nominal_value' => 'sometimes|numeric|min:0',
            'notes' => 'nullable|string',
            'status' => 'sometimes|string|max:50',
        ]);

        if (array_key_exists('product_id', $validated) && $validated['product_id'] && (!isset($validated['nominal_value']) || (float) $validated['nominal_value'] <= 0)) {
            $product = \App\Models\Product::find($validated['product_id']);
            if ($product && (float) $coupon->nominal_value <= 0) {
                $validated['nominal_value'] = (float) $product->price;
            }
        }

        $coupon->update($validated);

        return response()->json([
            'success' => true,
            'message' => "Kupon [{$coupon->serial_number}] berhasil diperbarui.",
            'data' => $coupon->fresh(['product', 'location']),
        ]);
    }

    /**
     * Delete coupon.
     */
    public function destroy(int $id): JsonResponse
    {
        $coupon = CouponUnit::findOrFail($id);
        $serial = $coupon->serial_number;
        $coupon->delete();

        return response()->json([
            'success' => true,
            'message' => "Kupon [{$serial}] berhasil dihapus dari sistem.",
        ]);
    }

    /**
     * Redeem coupon (Kasir validasi penggunaan kupon).
     */
    public function redeem(Request $request, int $id): JsonResponse
    {
        return DB::transaction(function () use ($id, $request) {
            $coupon = CouponUnit::lockForUpdate()->find($id);
            if (!$coupon) {
                return response()->json([
                    'success' => false,
                    'message' => 'Kupon tidak ditemukan.',
                ], 404);
            }

            if ($coupon->status === 'REDEEMED' || $coupon->status === 'SELESAI') {
                return response()->json([
                    'success' => false,
                    'message' => "Kupon [{$coupon->serial_number}] sudah pernah ditukarkan sebelumnya.",
                ], 422);
            }

            $coupon->update(['status' => 'REDEEMED']);

            $redemption = CouponRedemption::create([
                'coupon_unit_id' => $coupon->id,
                'transaction_id' => $request->input('transaction_id'),
                'redeemed_at' => now(),
                'redeemed_by' => $request->input('user_id') ?: \Illuminate\Support\Facades\Auth::id(),
                'status' => 'COMPLETED',
            ]);

            return response()->json([
                'success' => true,
                'message' => "Kupon [{$coupon->serial_number}] berhasil ditukarkan/digunakan.",
                'data' => [
                    'coupon' => $coupon,
                    'redemption' => $redemption,
                ],
            ]);
        });
    }
}
