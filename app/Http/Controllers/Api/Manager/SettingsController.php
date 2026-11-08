<?php

namespace App\Http\Controllers\Api\Manager;

use App\Http\Controllers\Controller;
use App\Models\AppSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SettingsController extends Controller
{
    /**
     * Get all system settings.
     */
    public function index(): JsonResponse
    {
        $all = AppSetting::all()->keyBy('key');

        $settings = [
            'store' => [
                'store_name' => $all['store_name']->value ?? 'ILP Mart - Koperasi Pekerja Pusat',
                'store_code' => $all['store_code']->value ?? 'CAB-JKT-001',
                'store_phone' => $all['store_phone']->value ?? '(021) 5589-2210',
                'store_address' => $all['store_address']->value ?? 'Jl. Industri Raya No. 45, Kawasan Terpadu ILP',
                'store_tax_id' => $all['store_tax_id']->value ?? '01.882.991.4-012.000',
                'branches' => json_decode($all['branches_list']->value ?? '[]', true) ?: [
                    'Pusat',
                    'Cabang 1 - Mill / Pabrik',
                    'Cabang 2 - Kebun Barat',
                    'Cabang 3 - Kebun Timur',
                ],
            ],
            'tax' => [
                'tax_rate_percent' => (float) ($all['tax_rate_percent']->value ?? 11),
                'tax_enabled' => ($all['tax_enabled']->value ?? '1') === '1' || ($all['tax_enabled']->value ?? '') === 'true',
                'tax_calculation_method' => $all['tax_calculation_method']->value ?? 'EXCLUDE',
            ],
            'security' => [
                'void_require_supervisor_pin' => ($all['void_require_supervisor_pin']->value ?? '1') === '1' || ($all['void_require_supervisor_pin']->value ?? '') === 'true',
                'manual_discount_require_pin' => ($all['manual_discount_require_pin']->value ?? '1') === '1' || ($all['manual_discount_require_pin']->value ?? '') === 'true',
            ],
            'credit' => [
                'credit_limit_default' => (float) ($all['credit_limit_default']->value ?? 2500000),
            ],
        ];

        return response()->json([
            'success' => true,
            'data' => $settings,
        ]);
    }

    /**
     * Update store and operational profile.
     */
    public function updateStore(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'store_name' => 'required|string|max:150',
            'store_phone' => 'nullable|string|max:50',
            'store_address' => 'nullable|string|max:255',
            'store_tax_id' => 'nullable|string|max:50',
        ]);

        foreach ($validated as $key => $val) {
            AppSetting::set($key, (string) $val, 'store');
        }

        return response()->json([
            'success' => true,
            'message' => 'Profil toko & cabang berhasil diperbarui.',
        ]);
    }

    /**
     * Update Tax (PPN) settings.
     */
    public function updateTax(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'tax_rate_percent' => 'required|numeric|min:0|max:100',
            'tax_enabled' => 'required|boolean',
            'tax_calculation_method' => 'required|string|in:EXCLUDE,INCLUDE',
        ]);

        AppSetting::set('tax_rate_percent', (string) $validated['tax_rate_percent'], 'tax');
        AppSetting::set('tax_enabled', $validated['tax_enabled'] ? '1' : '0', 'tax');
        AppSetting::set('tax_calculation_method', $validated['tax_calculation_method'], 'tax');

        return response()->json([
            'success' => true,
            'message' => 'Pengaturan Pajak (PPN ' . $validated['tax_rate_percent'] . '%) berhasil disimpan.',
            'tax' => [
                'rate' => (float) $validated['tax_rate_percent'],
                'enabled' => (bool) $validated['tax_enabled'],
                'method' => $validated['tax_calculation_method'],
            ],
            'data' => [
                'tax_rate_percent' => (float) $validated['tax_rate_percent'],
                'tax_enabled' => (bool) $validated['tax_enabled'],
                'tax_calculation_method' => $validated['tax_calculation_method'],
            ],
        ]);
    }

    /**
     * Update Security & Authorization rules (Supervisor PIN enforcement).
     */
    public function updateSecurity(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'void_require_supervisor_pin' => 'required|boolean',
            'manual_discount_require_pin' => 'required|boolean',
        ]);

        AppSetting::set('void_require_supervisor_pin', $validated['void_require_supervisor_pin'] ? '1' : '0', 'security');
        AppSetting::set('manual_discount_require_pin', $validated['manual_discount_require_pin'] ? '1' : '0', 'security');

        return response()->json([
            'success' => true,
            'message' => 'Pengaturan keamanan dan otorisasi PIN berhasil disimpan.',
        ]);
    }
}
