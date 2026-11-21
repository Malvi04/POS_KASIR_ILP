<?php

namespace App\Http\Controllers\Api\Manager;

use App\Http\Controllers\Controller;
use App\Models\AppSetting;
use App\Models\Product;
use App\Models\Promotion;
use App\Services\PromotionEngine;
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

        return response()->json([
            'success' => true,
            'data' => $promotions,
        ]);
    }

    /**
     * Validation rules shared by store & update.
     */
    protected function rules(bool $isUpdate = false): array
    {
        $req = $isUpdate ? 'sometimes|required' : 'required';
        return [
            'name' => "{$req}|string|max:255",
            'condition' => 'nullable|string|max:255',
            'type' => "{$req}|in:" . implode(',', Promotion::TYPES),
            'value' => 'nullable|numeric|min:0',
            'period_start' => 'nullable|date',
            'period_end' => 'nullable|date|after_or_equal:period_start',
            'status' => ($isUpdate ? 'sometimes|required' : 'nullable') . '|string|in:AKTIF,MENDATANG,NONAKTIF,BERAKHIR',
            'scope' => 'nullable|in:ALL,CATEGORY,PRODUCTS',
            'product_ids' => 'nullable|array',
            'product_ids.*' => 'integer|exists:products,id',
            'categories' => 'nullable|array',
            'categories.*' => 'string|max:100',
            'buy_qty' => 'nullable|integer|min:1',
            'get_qty' => 'nullable|integer|min:1',
            'min_qty' => 'nullable|integer|min:1',
            'min_purchase' => 'nullable|numeric|min:0',
            'max_qty_per_trx' => 'nullable|integer|min:1',
            'reward_product_id' => 'nullable|integer|exists:products,id',
            'excluded_categories' => 'nullable|array',
            'excluded_categories.*' => 'string|max:100',
            'days_of_week' => 'nullable|array',
            'days_of_week.*' => 'integer|between:1,7',
            'time_start' => 'nullable|date_format:H:i',
            'time_end' => 'nullable|date_format:H:i',
            'member_only' => 'nullable|boolean',
        ];
    }

    /**
     * Business validation per promo type.
     */
    protected function validateTypeRules(array $data): ?string
    {
        $type = $data['type'] ?? null;
        $value = (float) ($data['value'] ?? 0);
        $scope = $data['scope'] ?? 'ALL';

        if (in_array($type, PromotionEngine::ITEM_TYPES, true)) {
            if ($scope === 'PRODUCTS' && empty($data['product_ids'])) {
                return 'Pilih minimal 1 produk target promo.';
            }
            if ($scope === 'CATEGORY' && empty($data['categories'])) {
                return 'Pilih minimal 1 kategori target promo.';
            }
        }

        return match ($type) {
            'PERCENTAGE' => ($value <= 0 || $value > 100) ? 'Persentase diskon harus antara 1 - 100%.' : null,
            'FIXED' => $value <= 0 ? 'Nominal potongan harus lebih dari Rp 0.' : null,
            'SPECIAL_PRICE' => $value <= 0 ? 'Harga promo (harga coret) wajib diisi.' : null,
            'TIERED' => ($value <= 0 || (int) ($data['min_qty'] ?? 0) < 2) ? 'Isi minimal qty (≥ 2) dan harga satuan grosir.' : null,
            'BOGO' => (empty($data['buy_qty']) || empty($data['get_qty'])) ? 'Isi jumlah Beli (X) dan Gratis (Y).' : null,
            'BUNDLE' => (count($data['product_ids'] ?? []) < 2 || $value <= 0) ? 'Paket bundling wajib minimal 2 produk dan harga paket.' : null,
            'PWP' => (empty($data['reward_product_id']) || (float) ($data['min_purchase'] ?? 0) <= 0) ? 'Tebus murah wajib mengisi minimal belanja dan produk tebusan.' : null,
            default => null,
        };
    }

    protected function normalizePayload(array $data): array
    {
        $type = $data['type'] ?? null;
        if (in_array($type, ['BUNDLE', 'PWP'], true)) {
            $data['scope'] = $type === 'BUNDLE' ? 'PRODUCTS' : 'ALL';
        }
        if (($data['scope'] ?? 'ALL') !== 'PRODUCTS' && $type !== 'BUNDLE') {
            $data['product_ids'] = [];
        }
        if (($data['scope'] ?? 'ALL') !== 'CATEGORY') {
            $data['categories'] = [];
        }
        foreach (['time_start', 'time_end'] as $k) {
            if (array_key_exists($k, $data) && $data[$k] === '') {
                $data[$k] = null;
            }
        }
        return $data;
    }

    protected function statusColor(string $status): string
    {
        return match ($status) {
            'AKTIF' => 'green',
            'MENDATANG' => 'blue',
            default => 'gray',
        };
    }

    /**
     * Create a new promotion.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $this->normalizePayload($request->validate($this->rules()));

        if ($err = $this->validateTypeRules($validated)) {
            return response()->json(['success' => false, 'message' => $err], 422);
        }

        $nextNum = ((int) Promotion::max('id')) + 1;
        $code = 'PRM-' . str_pad((string) $nextNum, 3, '0', STR_PAD_LEFT);
        while (Promotion::where('code', $code)->exists()) {
            $nextNum++;
            $code = 'PRM-' . str_pad((string) $nextNum, 3, '0', STR_PAD_LEFT);
        }

        $status = $validated['status'] ?? 'AKTIF';

        $promo = Promotion::create(array_merge($validated, [
            'code' => $code,
            'condition' => $validated['condition'] ?? 'Tanpa syarat',
            'value' => $validated['value'] ?? 0,
            'badge_class' => Promotion::badgeClassFor($validated['type']),
            'period_start' => $validated['period_start'] ?? now()->toDateString(),
            'period_end' => $validated['period_end'] ?? now()->addDays(7)->toDateString(),
            'status' => $status,
            'status_color' => $this->statusColor($status),
            'scope' => $validated['scope'] ?? 'ALL',
            'member_only' => (bool) ($validated['member_only'] ?? false),
        ]));

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

        $validated = $request->validate($this->rules(true));

        // Full form update (bukan sekadar toggle status) -> validasi aturan tipe
        if (isset($validated['type'])) {
            $validated = $this->normalizePayload($validated);
            if ($err = $this->validateTypeRules($validated)) {
                return response()->json(['success' => false, 'message' => $err], 422);
            }
            $validated['badge_class'] = Promotion::badgeClassFor($validated['type']);
        }

        if (isset($validated['status'])) {
            $validated['status_color'] = $this->statusColor($validated['status']);
        }

        $promo->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Promosi berhasil diperbarui',
            'data' => $promo->fresh(),
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
     * Lightweight product & category options for the promo form.
     */
    public function productOptions(): JsonResponse
    {
        $products = Product::query()
            ->orderBy('name')
            ->get(['id', 'sku', 'name', 'category', 'price', 'member_price']);

        return response()->json([
            'success' => true,
            'data' => [
                'products' => $products->map(fn ($p) => [
                    'id' => $p->id,
                    'sku' => $p->sku,
                    'name' => $p->name,
                    'category' => $p->category,
                    'price' => (float) $p->price,
                ])->values(),
                'categories' => $products->pluck('category')->filter()->unique()->sort()->values(),
            ],
        ]);
    }

    /**
     * Get promo settings & expiry rules.
     */
    public function getSettings(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => PromotionEngine::settings(),
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
            'promoEngineEnabled' => 'nullable|boolean',
            'allowCashierToggle' => 'nullable|boolean',
        ]);

        $merged = array_merge(PromotionEngine::settings(), array_filter($data, fn ($v) => $v !== null));

        AppSetting::updateOrCreate(
            ['key' => 'promo_settings'],
            ['value' => json_encode($merged)]
        );

        return response()->json([
            'success' => true,
            'message' => 'Pengaturan promo berhasil disimpan',
            'data' => $merged,
        ]);
    }

    /**
     * Promo aktif untuk layar kasir POS (semua role yang login).
     */
    public function activeForPos(PromotionEngine $engine): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $engine->activePayloadForPos(),
        ]);
    }
}
