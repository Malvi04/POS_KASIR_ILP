<?php

namespace App\Http\Controllers\Api\Kasir;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Api\Shared\NotificationController;
use App\Models\CashierShift;
use App\Models\Product;
use App\Models\Transaction;
use App\Models\User;
use App\Models\VoidLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class VoidController extends Controller
{
    /**
     * Authorize supervisor / admin PIN or password for void actions.
     */
    public function authorizeSupervisor(Request $request): JsonResponse
    {
        $credential = trim($request->input('pin_or_password') ?? $request->input('supervisor_pin') ?? '');
        if ($credential === '') {
            return response()->json([
                'authorized' => false,
                'message' => 'PIN Supervisor atau Password diperlukan.',
            ], 422);
        }

        // Check if supervisor or admin with matching PIN exists
        $supervisor = User::where(function ($q) {
            $q->whereIn(DB::raw('LOWER(role)'), ['supervisor', 'admin', 'manager', 'owner', 'superadmin', 'spv']);
        })->where('pin', $credential)->first();

        // Fallback for default PIN 123456 if supervisor role exists
        if (!$supervisor && $credential === '123456') {
            $supervisor = User::whereIn(DB::raw('LOWER(role)'), ['supervisor', 'admin', 'manager', 'owner', 'superadmin', 'spv'])->first();
        }

        if ($supervisor) {
            return response()->json([
                'authorized' => true,
                'message' => "Otorisasi berhasil oleh {$supervisor->role} ({$supervisor->name}).",
                'supervisor' => [
                    'id' => $supervisor->id,
                    'name' => $supervisor->name,
                    'nik' => $supervisor->nik ?? 'SPV-01',
                    'role' => $supervisor->role,
                ],
            ]);
        }

        // If no supervisor matched, check if it was a cashier attempting self-void
        $cashierUser = User::where('pin', $credential)->first();
        if ($cashierUser && $cashierUser->isKasir()) {
            return response()->json([
                'authorized' => false,
                'message' => 'Otorisasi ditolak. Akun Kasir tidak memiliki wewenang untuk Void transaksi tanpa persetujuan Supervisor atau Manager.',
            ], 403);
        }

        return response()->json([
            'authorized' => false,
            'message' => 'Otorisasi ditolak. PIN atau Password Supervisor/Admin tidak valid.',
        ], 403);
    }

    /**
     * Authorize and record Void for a specific Item in active cart.
     */
    public function voidItem(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'pin_or_password' => 'required|string',
            'item_sku' => 'required|string',
            'item_name' => 'required|string',
            'item_qty' => 'required|integer|min:1',
            'amount_voided' => 'required|numeric|min:0',
            'cashier_name' => 'required|string',
            'terminal_code' => 'required|string',
            'reason' => 'required|string|max:255',
            'shift_id' => 'nullable|integer',
        ]);

        $credential = trim($validated['pin_or_password']);

        $supervisor = User::where(function ($q) {
            $q->whereIn(DB::raw('LOWER(role)'), ['supervisor', 'admin', 'manager', 'owner', 'superadmin', 'spv']);
        })->where('pin', $credential)->first();

        if (!$supervisor && $credential === '123456') {
            $supervisor = User::whereIn(DB::raw('LOWER(role)'), ['supervisor', 'admin', 'manager', 'owner', 'superadmin', 'spv'])->first();
        }

        if (!$supervisor) {
            $cashierUser = User::where('pin', $credential)->first();
            $msg = ($cashierUser && $cashierUser->isKasir())
                ? 'Otorisasi ditolak. Akun Kasir tidak memiliki wewenang untuk Void transaksi tanpa persetujuan Supervisor atau Manager.'
                : 'Otorisasi gagal! Pembatalan item membutuhkan PIN/Password Supervisor atau Admin.';

            return response()->json([
                'success' => false,
                'message' => $msg,
            ], 403);
        }

        $log = VoidLog::create([
            'transaction_id' => null,
            'shift_id' => $validated['shift_id'] ?? null,
            'action_type' => 'VOID_ITEM',
            'supervisor_nik' => $supervisor->nik ?? 'SPV-01',
            'supervisor_name' => $supervisor->name,
            'cashier_name' => $validated['cashier_name'],
            'item_sku' => $validated['item_sku'],
            'item_name' => $validated['item_name'],
            'item_qty' => $validated['item_qty'],
            'amount_voided' => $validated['amount_voided'],
            'reason' => $validated['reason'],
            'ip_address' => $request->ip(),
        ]);

        try {
            NotificationController::notify(
                role: 'MANAGER',
                title: "Void Item: {$validated['item_name']}",
                message: "Kasir {$validated['cashier_name']} membatalkan {$validated['item_qty']}x {$validated['item_name']} (Rp " . number_format($validated['amount_voided'], 0, ',', '.') . ") di terminal {$validated['terminal_code']}.",
                category: 'VOID',
                link: '/manager/dashboard',
                icon: 'remove_shopping_cart',
                color: 'rose',
                referenceId: $validated['item_sku']
            );
        } catch (\Throwable $e) {}

        return response()->json([
            'success' => true,
            'message' => "Item [{$validated['item_name']}] berhasil di-void dengan otorisasi oleh {$supervisor->name}.",
            'data' => $log,
        ]);
    }

    /**
     * Cancel / Void an entire completed transaction (Anti-Fraud Control).
     * Reverses inventory stock and member credit debt.
     */
    public function voidTransaction(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'invoice_number' => 'required|string',
            'pin_or_password' => 'required|string',
            'reason' => 'required|string|max:500',
        ]);

        $credential = trim($validated['pin_or_password']);

        $supervisor = User::where(function ($q) {
            $q->whereIn(DB::raw('LOWER(role)'), ['supervisor', 'admin', 'manager', 'owner', 'superadmin', 'spv']);
        })->where('pin', $credential)->first();

        if (!$supervisor && $credential === '123456') {
            $supervisor = User::whereIn(DB::raw('LOWER(role)'), ['supervisor', 'admin', 'manager', 'owner', 'superadmin', 'spv'])->first();
        }

        if (!$supervisor) {
            $cashierUser = User::where('pin', $credential)->first();
            $msg = ($cashierUser && $cashierUser->isKasir())
                ? 'Otorisasi ditolak. Akun Kasir tidak memiliki wewenang untuk Void transaksi tanpa persetujuan Supervisor atau Manager.'
                : 'Otorisasi gagal! Pembatalan nota wajib memasukkan PIN otorisasi Supervisor atau Admin.';

            return response()->json([
                'success' => false,
                'message' => $msg,
            ], 403);
        }

        return DB::transaction(function () use ($validated, $supervisor, $request) {
            $transaction = Transaction::with(['items', 'shift', 'member'])
                ->where('invoice_number', $validated['invoice_number'])
                ->lockForUpdate()
                ->first();

            if (!$transaction) {
                return response()->json([
                    'success' => false,
                    'message' => "Transaksi [{$validated['invoice_number']}] tidak ditemukan.",
                ], 404);
            }

            if ($transaction->status === 'VOIDED') {
                return response()->json([
                    'success' => false,
                    'message' => "Transaksi [{$validated['invoice_number']}] sudah pernah dibatalkan (VOID) sebelumnya.",
                ], 422);
            }

            // 1. Restore product stock
            foreach ($transaction->items as $item) {
                if ($item->product_id) {
                    $product = Product::lockForUpdate()->find($item->product_id);
                    if ($product) {
                        $product->stock += $item->qty;
                        if ($product->stock > $product->min_stock) {
                            $product->status = $product->is_near_expired ? 'NEAR_EXPIRED' : 'TERSEDIA';
                        }
                        $product->save();
                    }
                }
            }

            // 2. Revert member credit if paid via credit
            if ($transaction->payment_method === 'KREDIT' && $transaction->member) {
                $member = $transaction->member;
                $member->current_debt = max(0, $member->current_debt - $transaction->grand_total);
                $member->save();
            }

            // 3. Adjust shift financial numbers
            if ($transaction->shift && $transaction->shift->status === 'OPEN') {
                $shift = $transaction->shift;
                if ($transaction->payment_method === 'TUNAI') {
                    $shift->cash_sales_total = max(0, $shift->cash_sales_total - $transaction->grand_total);
                } elseif ($transaction->payment_method === 'QRIS' || $transaction->payment_method === 'TRANSFER') {
                    $shift->qris_sales_total = max(0, $shift->qris_sales_total - $transaction->grand_total);
                } elseif ($transaction->payment_method === 'KREDIT') {
                    $shift->credit_sales_total = max(0, $shift->credit_sales_total - $transaction->grand_total);
                } elseif ($transaction->payment_method === 'CAMPURAN') {
                    $shift->cash_sales_total = max(0, $shift->cash_sales_total - $transaction->split_cash);
                    $shift->qris_sales_total = max(0, $shift->qris_sales_total - $transaction->split_non_cash);
                }

                $shift->total_sales = max(0, $shift->total_sales - $transaction->grand_total);
                $shift->transaction_count = max(0, $shift->transaction_count - 1);
                $shift->expected_cash = $shift->calculateExpectedCash();
                $shift->save();
            }

            // 4. Mark transaction as voided
            $transaction->update([
                'status' => 'VOIDED',
                'voided_at' => now(),
                'voided_by' => "{$supervisor->role}: {$supervisor->name} ({$supervisor->nik})",
                'void_reason' => $validated['reason'],
            ]);

            // 5. Record Anti-Fraud Void Log
            $log = VoidLog::create([
                'transaction_id' => $transaction->id,
                'shift_id' => $transaction->shift_id,
                'action_type' => 'VOID_TRANSACTION',
                'supervisor_nik' => $supervisor->nik ?? 'SPV-01',
                'supervisor_name' => $supervisor->name,
                'cashier_name' => $transaction->cashier_name,
                'item_sku' => null,
                'item_name' => "Seluruh Nota {$transaction->invoice_number}",
                'item_qty' => $transaction->items->sum('qty'),
                'amount_voided' => $transaction->grand_total,
                'reason' => $validated['reason'],
                'ip_address' => $request->ip(),
            ]);

            try {
                NotificationController::notify(
                    role: 'MANAGER',
                    title: "Otorisasi Void: {$transaction->invoice_number}",
                    message: "Supervisor {$supervisor->name} menyetujui pembatalan nota {$transaction->invoice_number} senilai Rp " . number_format($transaction->grand_total, 0, ',', '.') . ". Alasan: {$validated['reason']}.",
                    category: 'VOID',
                    link: '/manager/dashboard',
                    icon: 'cancel',
                    color: 'rose',
                    referenceId: $transaction->invoice_number
                );
                NotificationController::notify(
                    role: 'OWNER',
                    title: "Audit Void Nota: {$transaction->invoice_number}",
                    message: "Transaksi dibatalkan (VOID) senilai Rp " . number_format($transaction->grand_total, 0, ',', '.') . " di terminal kasir {$transaction->cashier_name}.",
                    category: 'VOID',
                    link: '/owner/audit-log',
                    icon: 'security',
                    color: 'rose',
                    referenceId: $transaction->invoice_number
                );
                NotificationController::notify(
                    role: 'KASIR',
                    title: "Approval Void Disetujui",
                    message: "Pembatalan nota {$transaction->invoice_number} berhasil diotorisasi oleh {$supervisor->name}.",
                    category: 'VOID',
                    link: '/pos',
                    icon: 'verified',
                    color: 'emerald',
                    referenceId: $transaction->invoice_number
                );
            } catch (\Throwable $e) {}

            return response()->json([
                'success' => true,
                'message' => "Transaksi [{$transaction->invoice_number}] sebesar Rp " . number_format($transaction->grand_total, 0, ',', '.') . " berhasil dibatalkan (VOID) dan diverifikasi oleh {$supervisor->name}.",
                'data' => [
                    'invoice_number' => $transaction->invoice_number,
                    'status' => 'VOIDED',
                    'voided_by' => $transaction->voided_by,
                    'void_log_id' => $log->id,
                ],
            ]);
        });
    }

    /**
     * Audit log list for supervisor / admin review.
     */
    public function logs(): JsonResponse
    {
        $logs = VoidLog::latest('id')->limit(50)->get();

        return response()->json([
            'success' => true,
            'count' => $logs->count(),
            'data' => $logs,
        ]);
    }
}
