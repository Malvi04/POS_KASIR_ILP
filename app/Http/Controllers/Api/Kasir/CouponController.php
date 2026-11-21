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

        $query = CouponUnit::with(['redemptions.redeemedByUser', 'product', 'location'])->latest('id');

        if ($category !== '' && $category !== 'all') {
            $query->where('category', strtolower($category));
        }

        if ($branch !== '' && $branch !== 'all') {
            $query->where(function ($q) use ($branch) {
                $q->where('branch', 'like', "%{$branch}%")
                  ->orWhereHas('location', fn($lq) => $lq->where('name', 'like', "%{$branch}%"));
            });
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
        $redeemedCount = $allMatching->where('status', 'REDEEMED')->count();
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
                    'label' => 'Pulsa Operasional',
                    'badgeClass' => 'bg-purple-50 text-purple-700 border-purple-200',
                    'dotClass' => 'bg-purple-500',
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
                'REDEEMED' => 'Sudah Digunakan',
                'EXPIRED' => 'Kedaluwarsa',
                default => $c->status
            };

            return [
                'id' => $c->serial_number,
                'raw_id' => $c->id,
                'category' => $c->category,
                'categoryLabel' => $categoryInfo['label'],
                'detail' => $c->product?->name ?: ($c->notes ?: $categoryInfo['defaultDetail']),
                'product_name' => $c->product?->name ?? null,
                'product_sku' => $c->product?->sku ?? null,
                'branch' => $c->branch ?? ($c->location?->name ?? 'Semua Cabang'),
                'quota' => (int) ($c->quota ?? 1),
                'recipient' => $c->recipient_name,
                'nik' => $c->recipient_nik ?? '-',
                'dept' => $c->recipient_dept ?? 'Operasional Toko',
                'vehicleNo' => $c->vehicle_no ?? '-',
                'volume' => $c->volume ?? '1 Unit',
                'nominal' => (float) $c->nominal_value,
                'status' => $c->status,
                'statusLabel' => $statusLabel,
                'badgeClass' => $categoryInfo['badgeClass'],
                'dotClass' => $categoryInfo['dotClass'],
                'notes' => $c->notes,
                'created_at' => $c->created_at ? $c->created_at->format('d M Y H:i') : '-',
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
            'recipient_name' => 'required|string|max:150',
            'recipient_nik' => 'nullable|string|max:50',
            'recipient_dept' => 'nullable|string|max:100',
            'vehicle_no' => 'nullable|string|max:50',
            'volume' => 'nullable|string|max:50',
            'nominal_value' => 'required|numeric|min:0',
            'notes' => 'nullable|string',
            'status' => 'nullable|string|max:50',
        ]);

        $prefix = match(strtolower($validated['category'])) {
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

        $coupon = CouponUnit::create([
            'product_id' => $validated['product_id'] ?? null,
            'branch' => $validated['branch'] ?? null,
            'quota' => $validated['quota'] ?? 1,
            'allocated_by' => Auth::id(),
            'category' => strtolower($validated['category']),
            'serial_number' => $serial,
            'recipient_name' => $validated['recipient_name'],
            'recipient_nik' => $validated['recipient_nik'] ?? null,
            'recipient_dept' => $validated['recipient_dept'] ?? 'Operasional Toko',
            'vehicle_no' => $validated['vehicle_no'] ?? null,
            'volume' => $validated['volume'] ?? '1 Unit',
            'nominal_value' => $validated['nominal_value'],
            'notes' => $validated['notes'] ?? null,
            'status' => $validated['status'] ?? 'TERVALIDASI',
        ]);

        return response()->json([
            'success' => true,
            'message' => "Kupon [{$coupon->serial_number}] berhasil diterbitkan.",
            'data' => $coupon,
        ], 201);
    }

    /**
     * Update coupon details.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $coupon = CouponUnit::findOrFail($id);

        $validated = $request->validate([
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

        $coupon->update($validated);

        return response()->json([
            'success' => true,
            'message' => "Kupon [{$coupon->serial_number}] berhasil diperbarui.",
            'data' => $coupon,
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

            if ($coupon->status === 'REDEEMED') {
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
                'redeemed_by' => $request->input('user_id'),
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
