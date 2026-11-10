<?php

namespace App\Http\Controllers\Api\Manager;

use App\Http\Controllers\Controller;
use App\Models\AppSetting;
use App\Models\Promotion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PromotionController extends Controller
{
    /**
     * List promotions with optional search & status filter.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Promotion::query();

        if ($request->filled('status') && $request->status !== 'all') {
            $query->where('status', strtoupper($request->status));
        }

        if ($request->filled('q')) {
            $term = $request->q;
            $query->where(function ($q) use ($term) {
                $q->where('name', 'ilike', "%{$term}%")
                  ->orWhere('code', 'ilike', "%{$term}%")
                  ->orWhere('condition', 'ilike', "%{$term}%");
            });
        }

        $promotions = $query->orderBy('id', 'desc')->get();

        // Seed default promotions if table is empty
        if ($promotions->isEmpty() && Promotion::count() === 0) {
            $defaults = [
                [
                    'code' => 'PRM-001',
                    'name' => 'Promo Gajian Mantap',
                    'condition' => 'Min. belanja Rp 250k',
                    'type' => 'PERCENTAGE',
                    'value' => 10,
                    'badge_class' => 'bg-[#dbeafe] text-[#1e40af]',
                    'period_start' => now()->subDays(5)->toDateString(),
                    'period_end' => now()->addDays(5)->toDateString(),
                    'status' => 'AKTIF',
                    'status_color' => 'green',
                ],
                [
                    'code' => 'PRM-002',
                    'name' => 'BOGO: Susu Cair Ultra',
                    'condition' => 'Beli 2 Gratis 1',
                    'type' => 'BOGO',
                    'value' => 100,
                    'badge_class' => 'bg-[#ffedd5] text-[#9a3412]',
                    'period_start' => now()->toDateString(),
                    'period_end' => now()->addDays(14)->toDateString(),
                    'status' => 'MENDATANG',
                    'status_color' => 'blue',
                ],
                [
                    'code' => 'PRM-003',
                    'name' => 'Flash Sale Minyak Goreng',
                    'condition' => 'Potongan Langsung Rp 5.000',
                    'type' => 'FIXED',
                    'value' => 5000,
                    'badge_class' => 'bg-[#e0e7ff] text-[#3730a3]',
                    'period_start' => now()->subDays(10)->toDateString(),
                    'period_end' => now()->subDay()->toDateString(),
                    'status' => 'NONAKTIF',
                    'status_color' => 'red',
                ],
            ];

            foreach ($defaults as $d) {
                Promotion::create($d);
            }
            $promotions = Promotion::orderBy('id', 'desc')->get();
        }

        return response()->json([
            'success' => true,
            'data' => $promotions,
        ]);
    }

    /**
     * Create a new promotion.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'condition' => 'nullable|string|max:255',
            'type' => 'required|in:PERCENTAGE,FIXED,BOGO',
            'value' => 'nullable|numeric|min:0',
            'period_start' => 'nullable|date',
            'period_end' => 'nullable|date',
            'status' => 'nullable|string|in:AKTIF,MENDATANG,NONAKTIF',
        ]);

        $code = 'PRM-' . str_pad((string)(Promotion::count() + 1), 3, '0', STR_PAD_LEFT);

        $badgeClass = match ($validated['type']) {
            'PERCENTAGE' => 'bg-[#dbeafe] text-[#1e40af]',
            'FIXED' => 'bg-[#e0e7ff] text-[#3730a3]',
            'BOGO' => 'bg-[#ffedd5] text-[#9a3412]',
            default => 'bg-gray-100 text-gray-700',
        };

        $status = $validated['status'] ?? 'AKTIF';
        $statusColor = match ($status) {
            'AKTIF' => 'green',
            'MENDATANG' => 'blue',
            default => 'red',
        };

        $promo = Promotion::create([
            'code' => $code,
            'name' => $validated['name'],
            'condition' => $validated['condition'] ?? 'Tanpa syarat',
            'type' => $validated['type'],
            'value' => $validated['value'] ?? 0,
            'badge_class' => $badgeClass,
            'period_start' => $validated['period_start'] ?? now()->toDateString(),
            'period_end' => $validated['period_end'] ?? now()->addDays(7)->toDateString(),
            'status' => $status,
            'status_color' => $statusColor,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Promosi berhasil dibuat',
            'data' => $promo,
        ], 201);
    }

    /**
     * Update an existing promotion.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $promo = Promotion::findOrFail($id);

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'condition' => 'nullable|string|max:255',
            'type' => 'sometimes|required|in:PERCENTAGE,FIXED,BOGO',
            'value' => 'nullable|numeric|min:0',
            'period_start' => 'nullable|date',
            'period_end' => 'nullable|date',
            'status' => 'sometimes|required|string|in:AKTIF,MENDATANG,NONAKTIF',
        ]);

        if (isset($validated['type'])) {
            $validated['badge_class'] = match ($validated['type']) {
                'PERCENTAGE' => 'bg-[#dbeafe] text-[#1e40af]',
                'FIXED' => 'bg-[#e0e7ff] text-[#3730a3]',
                'BOGO' => 'bg-[#ffedd5] text-[#9a3412]',
                default => 'bg-gray-100 text-gray-700',
            };
        }

        if (isset($validated['status'])) {
            $validated['status_color'] = match ($validated['status']) {
                'AKTIF' => 'green',
                'MENDATANG' => 'blue',
                default => 'red',
            };
        }

        $promo->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Promosi berhasil diperbarui',
            'data' => $promo,
        ]);
    }

    /**
     * Delete a promotion.
     */
    public function destroy(int $id): JsonResponse
    {
        $promo = Promotion::findOrFail($id);
        $promo->delete();

        return response()->json([
            'success' => true,
            'message' => 'Promosi berhasil dihapus',
        ]);
    }

    /**
     * Get promo settings & expiry rules.
     */
    public function getSettings(): JsonResponse
    {
        $setting = AppSetting::where('key', 'promo_settings')->first();
        $settingsData = $setting ? json_decode($setting->value, true) : [
            'h7Percent' => 40,
            'h3Percent' => 70,
            'autoPrint' => true,
        ];

        return response()->json([
            'success' => true,
            'data' => $settingsData,
        ]);
    }

    /**
     * Save promo settings & expiry rules.
     */
    public function updateSettings(Request $request): JsonResponse
    {
        $data = $request->validate([
            'h7Percent' => 'required|numeric|min:0|max:100',
            'h3Percent' => 'required|numeric|min:0|max:100',
            'autoPrint' => 'required|boolean',
        ]);

        AppSetting::updateOrCreate(
            ['key' => 'promo_settings'],
            ['value' => json_encode($data)]
        );

        return response()->json([
            'success' => true,
            'message' => 'Pengaturan promo kadaluarsa berhasil disimpan',
            'data' => $data,
        ]);
    }
}
