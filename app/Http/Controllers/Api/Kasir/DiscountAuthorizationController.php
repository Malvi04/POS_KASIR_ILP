<?php

namespace App\Http\Controllers\Api\Kasir;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DiscountAuthorizationController extends Controller
{
    /**
     * Authorize a manual cashier discount via Supervisor / Manager PIN.
     */
    public function authorizeDiscount(Request $request): JsonResponse
    {
        $credential = trim($request->input('pin_or_password') ?? $request->input('supervisor_pin') ?? '');
        if ($credential === '') {
            return response()->json([
                'authorized' => false,
                'message' => 'PIN Supervisor atau Password diperlukan.',
            ], 422);
        }

        $rawType = strtoupper(trim($request->input('discount_type', 'PERCENT')));
        $discountType = in_array($rawType, ['PERCENTAGE', 'PERCENT']) ? 'PERCENT' : 'NOMINAL';
        $discountValue = (float) $request->input('discount_value', 0);
        $reason = $request->input('reason', 'Diskon Manual');

        if ($discountValue <= 0) {
            return response()->json([
                'authorized' => false,
                'message' => 'Nilai diskon harus lebih besar dari 0.',
            ], 422);
        }

        // Check if ANY active supervisor, manager, admin, or owner matches the PIN or password
        $supervisor = User::where(function ($q) {
            $q->where('is_active', true)->orWhereNull('is_active');
        })->get()->first(function ($u) use ($credential) {
            return $u->isSupervisorOrManager() && ($u->verifyPin($credential) || $u->verifyPassword($credential));
        });

        if (!$supervisor) {
            // Check if cashier attempted to authorize
            $cashier = User::where('is_active', true)->get()->first(function ($u) use ($credential) {
                return $u->isKasir() && ($u->verifyPin($credential) || $u->verifyPassword($credential));
            });

            if ($cashier) {
                return response()->json([
                    'authorized' => false,
                    'message' => 'Otorisasi ditolak. Akun Kasir tidak memiliki wewenang untuk menyetujui diskon manual.',
                ], 403);
            }

            return response()->json([
                'authorized' => false,
                'message' => 'Otorisasi Diskon Ditolak! Membutuhkan PIN Supervisor atau Manager yang valid.',
            ], 403);
        }

        // Percentage ceiling check (e.g. Max 50% discount)
        if ($discountType === 'PERCENT' && $discountValue > 50) {
            // Only Owner / Manager can authorize > 50%
            if (!$supervisor->isOwnerOrSuperadmin() && strtolower($supervisor->role) !== 'manager') {
                return response()->json([
                    'authorized' => false,
                    'message' => 'Diskon manual di atas 50% memerlukan otorisasi Manager Toko atau Owner.',
                ], 403);
            }
        }

        $authKey = 'DISC-' . strtoupper(bin2hex(random_bytes(4)));

        return response()->json([
            'authorized' => true,
            'message' => "Diskon manual disetujui oleh {$supervisor->role} ({$supervisor->name}).",
            'auth_code' => $authKey,
            'supervisor' => [
                'id' => $supervisor->id,
                'name' => $supervisor->name,
                'nik' => $supervisor->nik,
                'role' => $supervisor->role,
            ],
            'discount' => [
                'type' => $discountType,
                'value' => (float) $discountValue,
                'reason' => $reason,
            ],
        ]);
    }
}
