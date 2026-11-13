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
                'coop_license' => $all['coop_license']->value ?? 'AHU-0012984.AH.01.26.TAHUN 2024',
                'open_time' => $all['open_time']->value ?? '07:00',
                'close_time' => $all['close_time']->value ?? '22:00',
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
                'pin_for_void' => ($all['pin_for_void']->value ?? '1') === '1' || ($all['pin_for_void']->value ?? '') === 'true',
                'pin_for_refund' => ($all['pin_for_refund']->value ?? '1') === '1' || ($all['pin_for_refund']->value ?? '') === 'true',
                'pin_for_drawer' => ($all['pin_for_drawer']->value ?? '1') === '1' || ($all['pin_for_drawer']->value ?? '') === 'true',
                'pin_for_credit_override' => ($all['pin_for_credit_override']->value ?? '1') === '1' || ($all['pin_for_credit_override']->value ?? '') === 'true',
                'master_pin' => $all['master_pin']->value ?? '882910',
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
            'store_name' => 'nullable|string|max:150',
            'storeName' => 'nullable|string|max:150',
            'store_phone' => 'nullable|string|max:50',
            'phone' => 'nullable|string|max:50',
            'store_address' => 'nullable|string|max:255',
            'address' => 'nullable|string|max:255',
            'store_tax_id' => 'nullable|string|max:50',
            'taxId' => 'nullable|string|max:50',
            'coop_license' => 'nullable|string|max:100',
            'coopLicense' => 'nullable|string|max:100',
            'open_time' => 'nullable|string|max:20',
            'openTime' => 'nullable|string|max:20',
            'close_time' => 'nullable|string|max:20',
            'closeTime' => 'nullable|string|max:20',
        ]);

        $name = $validated['store_name'] ?? $validated['storeName'] ?? null;
        if ($name) AppSetting::set('store_name', $name, 'store');

        $phone = $validated['store_phone'] ?? $validated['phone'] ?? null;
        if ($phone) AppSetting::set('store_phone', $phone, 'store');

        $addr = $validated['store_address'] ?? $validated['address'] ?? null;
        if ($addr) AppSetting::set('store_address', $addr, 'store');

        $taxId = $validated['store_tax_id'] ?? $validated['taxId'] ?? null;
        if ($taxId) AppSetting::set('store_tax_id', $taxId, 'store');

        $license = $validated['coop_license'] ?? $validated['coopLicense'] ?? null;
        if ($license) AppSetting::set('coop_license', $license, 'store');

        $open = $validated['open_time'] ?? $validated['openTime'] ?? null;
        if ($open) AppSetting::set('open_time', $open, 'store');

        $close = $validated['close_time'] ?? $validated['closeTime'] ?? null;
        if ($close) AppSetting::set('close_time', $close, 'store');

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
        if ($request->has('void_require_supervisor_pin')) {
            AppSetting::set('void_require_supervisor_pin', $request->boolean('void_require_supervisor_pin') ? '1' : '0', 'security');
        }
        if ($request->has('manual_discount_require_pin')) {
            AppSetting::set('manual_discount_require_pin', $request->boolean('manual_discount_require_pin') ? '1' : '0', 'security');
        }
        if ($request->has('pinForVoid') || $request->has('pin_for_void')) {
            AppSetting::set('pin_for_void', $request->boolean('pinForVoid', $request->boolean('pin_for_void')) ? '1' : '0', 'security');
        }
        if ($request->has('pinForRefund') || $request->has('pin_for_refund')) {
            AppSetting::set('pin_for_refund', $request->boolean('pinForRefund', $request->boolean('pin_for_refund')) ? '1' : '0', 'security');
        }
        if ($request->has('pinForDrawer') || $request->has('pin_for_drawer')) {
            AppSetting::set('pin_for_drawer', $request->boolean('pinForDrawer', $request->boolean('pin_for_drawer')) ? '1' : '0', 'security');
        }
        if ($request->has('pinForCreditOverride') || $request->has('pin_for_credit_override')) {
            AppSetting::set('pin_for_credit_override', $request->boolean('pinForCreditOverride', $request->boolean('pin_for_credit_override')) ? '1' : '0', 'security');
        }
        if ($request->filled('masterPin') || $request->filled('master_pin')) {
            AppSetting::set('master_pin', $request->input('masterPin', $request->input('master_pin')), 'security');
        }

        return response()->json([
            'success' => true,
            'message' => 'Pengaturan keamanan dan otorisasi PIN berhasil disimpan.',
        ]);
    }
}
