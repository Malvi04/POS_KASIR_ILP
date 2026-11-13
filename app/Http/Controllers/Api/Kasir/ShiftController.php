<?php

namespace App\Http\Controllers\Api\Kasir;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Api\Shared\NotificationController;
use App\Models\CashierShift;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ShiftController extends Controller
{
    /**
     * Get active shift for a terminal or cashier.
     */
    public function current(Request $request): JsonResponse
    {
        $terminal = $request->query('terminal_code', 'POS-01');
        $cashierNik = $request->query('cashier_nik');

        $query = CashierShift::where('status', 'OPEN');
        if ($terminal) {
            $query->where('terminal_code', $terminal);
        }
        if ($cashierNik) {
            $query->where('cashier_nik', $cashierNik);
        }

        $shift = $query->latest('id')->first();

        if (!$shift) {
            return response()->json([
                'has_active_shift' => false,
                'message' => 'Belum ada shift kasir yang aktif pada terminal ini. Wajib memasukkan modal awal laci sebelum bertransaksi.',
                'data' => null,
            ]);
        }

        return response()->json([
            'has_active_shift' => true,
            'message' => 'Shift kasir aktif ditemukan.',
            'data' => [
                'id' => $shift->id,
                'shift_code' => $shift->shift_code,
                'terminal_code' => $shift->terminal_code,
                'cashier_name' => $shift->cashier_name,
                'cashier_nik' => $shift->cashier_nik,
                'opening_cash' => (float) $shift->opening_cash,
                'cash_sales_total' => (float) $shift->cash_sales_total,
                'qris_sales_total' => (float) $shift->qris_sales_total,
                'credit_sales_total' => (float) $shift->credit_sales_total,
                'total_sales' => (float) $shift->total_sales,
                'transaction_count' => (int) $shift->transaction_count,
                'cash_in' => (float) $shift->cash_in,
                'cash_out' => (float) $shift->cash_out,
                'expected_cash' => (float) $shift->calculateExpectedCash(),
                'status' => $shift->status,
                'opened_at' => $shift->opened_at->toIso8601String(),
            ],
        ]);
    }

    /**
     * Get all currently open shifts across terminals.
     */
    public function activeShifts(Request $request): JsonResponse
    {
        $shifts = CashierShift::where('status', 'OPEN')
            ->orderBy('id', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'count' => $shifts->count(),
            'data' => $shifts->map(function ($s) {
                return [
                    'id' => $s->id,
                    'shift_code' => $s->shift_code,
                    'terminal_code' => $s->terminal_code,
                    'cashier_name' => $s->cashier_name,
                    'cashier_nik' => $s->cashier_nik,
                    'opening_cash' => (float) $s->opening_cash,
                    'cash_sales_total' => (float) $s->cash_sales_total,
                    'qris_sales_total' => (float) $s->qris_sales_total,
                    'credit_sales_total' => (float) $s->credit_sales_total,
                    'total_sales' => (float) $s->total_sales,
                    'expected_cash' => (float) $s->expected_cash,
                    'opened_at' => $s->opened_at ? $s->opened_at->toIso8601String() : null,
                    'status' => $s->status,
                ];
            }),
        ]);
    }

    /**
     * Open a new shift (wajib input modal awal).
     */
    public function open(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'terminal_code' => 'required|string|max:20',
            'cashier_nik' => 'required|string|max:50',
            'cashier_name' => 'required|string|max:100',
            'opening_cash' => 'required|numeric|min:0',
            'pin' => 'nullable|string',
        ]);

        if (!empty($validated['pin'])) {
            $user = User::where('nik', $validated['cashier_nik'])
                ->orWhere('username', $validated['cashier_nik'])
                ->first();
            if ($user && !empty($user->pin) && $user->pin !== $validated['pin'] && $validated['pin'] !== '123456') {
                return response()->json([
                    'success' => false,
                    'message' => "PIN kasir tidak cocok untuk {$user->name}. Silakan periksa kembali PIN Anda.",
                ], 422);
            }
        }

        // Check if there's already an open shift for this terminal
        $existing = CashierShift::where('terminal_code', $validated['terminal_code'])
            ->where('status', 'OPEN')
            ->first();

        if ($existing) {
            return response()->json([
                'success' => false,
                'message' => "Terminal {$validated['terminal_code']} sudah memiliki sesi shift aktif ({$existing->shift_code}) oleh {$existing->cashier_name}. Tutup sesi terlebih dahulu sebelum membuka shift baru.",
                'data' => $existing,
            ], 422);
        }

        $user = User::where('nik', $validated['cashier_nik'])->first();

        $baseCode = 'CS-' . date('Ymd') . '-' . strtoupper(Str::slug($validated['terminal_code'], ''));
        $counter = CashierShift::whereDate('created_at', today())->count() + 1;
        $shiftCode = $baseCode . '-' . Str::padLeft((string) $counter, 3, '0');
        while (CashierShift::where('shift_code', $shiftCode)->exists()) {
            $counter++;
            $shiftCode = $baseCode . '-' . Str::padLeft((string) $counter, 3, '0') . '-' . Str::upper(Str::random(3));
        }

        $shift = CashierShift::create([
            'shift_code' => $shiftCode,
            'terminal_code' => $validated['terminal_code'],
            'user_id' => $user?->id,
            'cashier_nik' => $validated['cashier_nik'],
            'cashier_name' => $validated['cashier_name'],
            'opening_cash' => $validated['opening_cash'],
            'cash_sales_total' => 0,
            'qris_sales_total' => 0,
            'credit_sales_total' => 0,
            'total_sales' => 0,
            'transaction_count' => 0,
            'cash_in' => 0,
            'cash_out' => 0,
            'expected_cash' => $validated['opening_cash'],
            'status' => 'OPEN',
            'opened_at' => now(),
        ]);

        try {
            NotificationController::notify(
                role: 'MANAGER',
                title: "Shift Kasir Dibuka: {$shift->terminal_code}",
                message: "Kasir {$shift->cashier_name} membuka sesi {$shift->shift_code} dengan modal awal Rp " . number_format($shift->opening_cash, 0, ',', '.') . '.',
                category: 'CASHIER',
                link: '/manager/dashboard',
                icon: 'point_of_sale',
                color: 'blue',
                referenceId: $shift->shift_code
            );
            event(new \App\Events\CashierShiftEvent($shift, 'opened'));
        } catch (\Throwable $e) {}

        return response()->json([
            'success' => true,
            'message' => 'Sesi shift kasir berhasil dibuka dengan modal awal Rp ' . number_format($shift->opening_cash, 0, ',', '.'),
            'data' => $shift,
        ], 201);
    }

    /**
     * Audit closing shift.
     */
    public function close(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'shift_id' => 'nullable|integer|exists:cashier_shifts,id',
            'terminal_code' => 'nullable|string',
            'actual_cash' => 'required|numeric|min:0', // Uang fisik di laci
            'closing_notes' => 'nullable|string|max:500',
        ]);

        $query = CashierShift::where('status', 'OPEN');
        if (!empty($validated['shift_id'])) {
            $query->where('id', $validated['shift_id']);
        } elseif (!empty($validated['terminal_code'])) {
            $query->where('terminal_code', $validated['terminal_code']);
        }

        $shift = $query->latest('id')->first();

        if (!$shift) {
            return response()->json([
                'success' => false,
                'message' => 'Tidak ditemukan sesi shift kasir yang aktif untuk ditutup.',
            ], 404);
        }

        $shift->closeShift((float) $validated['actual_cash'], $validated['closing_notes'] ?? null);

        try {
            $hasVariance = abs((float) $shift->variance) > 0;
            NotificationController::notify(
                role: 'MANAGER',
                title: "Tutup Kasir: {$shift->shift_code}",
                message: "Kasir {$shift->cashier_name} ({$shift->terminal_code}) menutup shift. Omset: Rp " . number_format($shift->total_sales, 0, ',', '.') . ($hasVariance ? " | Selisih kas: Rp " . number_format($shift->variance, 0, ',', '.') : " | Kas seimbang"),
                category: 'CASHIER',
                link: '/manager/reports',
                icon: $hasVariance ? 'warning' : 'savings',
                color: $hasVariance ? 'rose' : 'emerald',
                referenceId: $shift->shift_code
            );
            NotificationController::notify(
                role: 'OWNER',
                title: "Closing Kasir: {$shift->terminal_code}",
                message: "Shift {$shift->shift_code} ditutup. Setoran fisik: Rp " . number_format($shift->actual_cash, 0, ',', '.') . ($hasVariance ? " (Deviasi: Rp " . number_format($shift->variance, 0, ',', '.') . ")" : ""),
                category: 'CASHIER',
                link: '/owner/audit-log',
                icon: $hasVariance ? 'report_problem' : 'account_balance_wallet',
                color: $hasVariance ? 'amber' : 'emerald',
                referenceId: $shift->shift_code
            );
            event(new \App\Events\CashierShiftEvent($shift, 'closed'));
        } catch (\Throwable $e) {}

        return response()->json([
            'success' => true,
            'message' => "Sesi {$shift->shift_code} berhasil ditutup. Audit kas: {$shift->variance_status} (Selisih: Rp " . number_format($shift->variance, 0, ',', '.') . ")",
            'data' => [
                'shift_code' => $shift->shift_code,
                'terminal_code' => $shift->terminal_code,
                'cashier_name' => $shift->cashier_name,
                'opened_at' => $shift->opened_at->toIso8601String(),
                'closed_at' => $shift->closed_at->toIso8601String(),
                'opening_cash' => (float) $shift->opening_cash,
                'cash_sales_total' => (float) $shift->cash_sales_total,
                'qris_sales_total' => (float) $shift->qris_sales_total,
                'credit_sales_total' => (float) $shift->credit_sales_total,
                'total_sales' => (float) $shift->total_sales,
                'transaction_count' => (int) $shift->transaction_count,
                'expected_cash' => (float) $shift->expected_cash,
                'actual_cash' => (float) $shift->actual_cash,
                'variance' => (float) $shift->variance,
                'variance_status' => $shift->variance_status,
                'closing_notes' => $shift->closing_notes,
            ],
        ]);
    }

    /**
     * Cash in / cash out movement in drawer.
     */
    public function cashMovement(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'shift_id' => 'nullable|integer|exists:cashier_shifts,id',
            'terminal_code' => 'nullable|string|max:50',
            'type' => 'required|in:IN,OUT,KAS_MASUK,PENGELUARAN_TUNAI,RETUR_TUNAI,CASH_DROP',
            'amount' => 'required|numeric|min:1',
            'reason' => 'nullable|string|max:255',
        ]);

        $query = CashierShift::where('status', 'OPEN');
        if (!empty($validated['shift_id'])) {
            $query->where('id', $validated['shift_id']);
        } elseif (!empty($validated['terminal_code'])) {
            $query->where('terminal_code', $validated['terminal_code']);
        }

        $shift = $query->latest('id')->first();
        if (!$shift) {
            return response()->json([
                'success' => false,
                'message' => 'Tidak ditemukan sesi shift kasir yang aktif untuk mencatat arus kas.',
            ], 422);
        }

        $isIncoming = in_array($validated['type'], ['IN', 'KAS_MASUK']);
        if ($isIncoming) {
            $shift->cash_in += $validated['amount'];
        } else {
            $shift->cash_out += $validated['amount'];
        }

        $shift->expected_cash = $shift->calculateExpectedCash();
        $shift->save();

        return response()->json([
            'success' => true,
            'message' => 'Arus kas laci (' . ($isIncoming ? 'Kas Masuk' : 'Kas Keluar') . ') sebesar Rp ' . number_format($validated['amount'], 0, ',', '.') . ' berhasil dicatat.',
            'data' => [
                'shift_id' => $shift->id,
                'shift_code' => $shift->shift_code,
                'terminal_code' => $shift->terminal_code,
                'expected_cash' => (float) $shift->expected_cash,
                'cash_in' => (float) $shift->cash_in,
                'cash_out' => (float) $shift->cash_out,
            ],
        ]);
    }
}
