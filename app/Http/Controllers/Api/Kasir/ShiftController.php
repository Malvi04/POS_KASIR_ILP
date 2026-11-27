<?php

namespace App\Http\Controllers\Api\Kasir;

use App\Events\AttendanceRecordedEvent;
use App\Events\CashierShiftEvent;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Api\Shared\NotificationController;
use App\Models\Attendance;
use App\Models\CashMovement;
use App\Models\CashierShift;
use App\Models\User;
use App\Services\LocationStockService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
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
                'location_id' => $shift->location_id,
                'branch' => $shift->branch,
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

        // Cari terminal yang shift terakhirnya ditutup dengan SERAH TERIMA dan belum dibuka shift baru
        $openTerminalCodes = $shifts->pluck('terminal_code')->toArray();
        $latestShiftIds = CashierShift::selectRaw('MAX(id) as max_id')
            ->groupBy('terminal_code')
            ->pluck('max_id');

        $latestShifts = CashierShift::whereIn('id', $latestShiftIds)->get();

        $handovers = [];
        foreach ($latestShifts as $ls) {
            $isHandover = str_contains($ls->closing_notes ?? '', '[SERAH TERIMA]');
            if ($ls->status === 'CLOSED' && $isHandover && !in_array($ls->terminal_code, $openTerminalCodes)) {
                $handovers[] = [
                    'terminal_code' => $ls->terminal_code,
                    'terminalId' => $ls->terminal_code,
                    'shift_code' => $ls->shift_code,
                    'from_cashier_name' => $ls->cashier_name,
                    'from_cashier_nik' => $ls->cashier_nik,
                    'actual_cash' => (float) ($ls->actual_cash ?? 0),
                    'expected_cash' => (float) ($ls->expected_cash ?? 0),
                    'variance' => (float) ($ls->variance ?? 0),
                    'variance_status' => $ls->variance_status ?? ($ls->variance == 0 ? 'SESUAI' : ($ls->variance > 0 ? 'LEBIH' : 'KURANG')),
                    'closing_notes' => $ls->closing_notes,
                    'ended_at' => $ls->closed_at ? $ls->closed_at->format('H:i') : '-',
                    'closed_at' => $ls->closed_at ? $ls->closed_at->toIso8601String() : null,
                ];
            }
        }

        return response()->json([
            'success' => true,
            'count' => $shifts->count(),
            'data' => $shifts->map(function ($s) {
                return [
                    'id' => $s->id,
                    'shift_code' => $s->shift_code,
                    'terminal_code' => $s->terminal_code,
                    'terminalId' => $s->terminal_code,
                    'location_id' => $s->location_id,
                    'branch' => $s->branch,
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
            'handovers' => $handovers,
        ]);
    }

    /**
     * Open a new shift (wajib input modal awal).
     */
    public function open(Request $request): JsonResponse
    {
        if (
            strtolower((string) config('pos_sync.node_role', 'central')) === 'local'
            && !$request->filled('terminal_code')
            && trim((string) config('pos_sync.terminal_code', '')) !== ''
        ) {
            $request->merge([
                'terminal_code' => strtoupper(trim((string) config('pos_sync.terminal_code'))),
            ]);
        }

        $validated = $request->validate([
            'terminal_code' => 'required|string|max:50',
            'cashier_nik' => 'required|string|max:50',
            'cashier_name' => 'required|string|max:100',
            'opening_cash' => 'required|numeric|min:0',
            'location_id' => 'nullable|integer|exists:locations,id',
            'branch' => 'nullable|string|max:100',
            'pin' => 'nullable|string',
        ]);

        $authUser = Auth::user();
        $stockService = app(LocationStockService::class);
        $terminalExpected = $stockService->resolveExpectedLocationForTerminal($validated['terminal_code']);
        $localIdentity = app(\App\Services\PosInvoiceGeneratorService::class)->resolveLocalIdentity(
            $stockService,
            $validated['location_id'] ?? null,
            $validated['branch'] ?? null,
            $validated['terminal_code'],
            $authUser
        );
        $resolvedLoc = $localIdentity['location'];

        // Validate terminal vs explicit requested location mismatch
        if ((!empty($validated['location_id']) || !empty($validated['branch'])) && $terminalExpected && $resolvedLoc && $terminalExpected->id !== $resolvedLoc->id) {
            return response()->json([
                'success' => false,
                'message' => "Terminal {$validated['terminal_code']} terdaftar di {$terminalExpected->name}, tidak sesuai dengan lokasi {$resolvedLoc->name}.",
            ], 422);
        }

        if ($authUser && $authUser->isKasir()) {
            $locToCheck = $resolvedLoc ?: $terminalExpected;
            if ($locToCheck && !$stockService->isUserAllowedInLocation($authUser, $locToCheck)) {
                return response()->json([
                    'success' => false,
                    'message' => "Akun Anda tidak diizinkan membuka shift pada lokasi {$locToCheck->name}.",
                ], 403);
            }
            if (empty($validated['pin']) && $validated['cashier_nik'] !== $authUser->nik && $validated['cashier_nik'] !== $authUser->username) {
                return response()->json([
                    'success' => false,
                    'message' => 'Kasir tidak diizinkan membuka shift atas nama kasir lain tanpa verifikasi PIN.',
                ], 403);
            }
        }

        if (!empty($validated['pin'])) {
            $user = User::where('nik', $validated['cashier_nik'])
                ->orWhere('username', $validated['cashier_nik'])
                ->first();
            if ($user && !empty($user->pin) && !$user->verifyPin($validated['pin'])) {
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

        $user = User::where('nik', $validated['cashier_nik'])
            ->orWhere('username', $validated['cashier_nik'])
            ->first() ?? $authUser;

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
            'location_id' => $resolvedLoc?->id,
            'branch' => $resolvedLoc?->name ?? ($validated['branch'] ?? $user?->branch ?? 'Toko Pusat'),
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

        // Pencatatan Absensi Karyawan Otomatis ke PostgreSQL saat sesi kasir dimulai
        try {
            $branch = $request->input('branch') ?: ($resolvedLoc?->name ?: ($user?->branch ?: 'Cabang Pusat'));
            $staffName = $user?->name ?? $validated['cashier_name'];

            \App\Services\AttendanceService::recordCashierCheckIn(
                user: $user,
                staffName: $staffName,
                terminalCode: $validated['terminal_code'],
                branch: $branch,
                notes: "Sesi {$shift->shift_code} aktif di {$validated['terminal_code']}"
            );

            \App\Models\AuditLog::record([
                'user_id' => $user?->id,
                'user_name' => $staffName,
                'user_role' => $user?->role ?? 'Kasir',
                'location' => $shift->branch ?: $branch,
                'category' => 'Sesi Kasir',
                'action_name' => 'Buka Sesi Shift Kasir',
                'description' => "Kasir {$staffName} membuka sesi {$shift->shift_code} di {$shift->terminal_code} dengan modal awal Rp " . number_format($shift->opening_cash, 0, ',', '.'),
                'reference' => $shift->shift_code,
                'status' => 'BERHASIL',
                'source' => $request->ip() . ' / ' . $shift->terminal_code,
            ]);
        } catch (\Throwable $e) {}

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
            NotificationController::notify(
                role: 'OWNER',
                title: "Shift Kasir Aktif: {$shift->terminal_code}",
                message: "Kasir {$shift->cashier_name} ({$shift->branch}) membuka sesi {$shift->shift_code} dengan modal awal Rp " . number_format($shift->opening_cash, 0, ',', '.') . '.',
                category: 'CASHIER',
                link: '/owner',
                icon: 'point_of_sale',
                color: 'blue',
                referenceId: $shift->shift_code
            );
            event(new \App\Events\CashierShiftEvent($shift, 'opened'));
        } catch (\Throwable $e) {}

        \App\Http\Controllers\Api\Shared\AuthController::dispatchBackgroundOperationalSync();

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
            'shift_code' => 'nullable|string',
            'terminal_code' => 'nullable|string',
            'actual_cash' => 'required|numeric|min:0', // Uang fisik di laci
            'closing_notes' => 'nullable|string|max:500',
            'handover_type' => 'nullable|string|max:50',
        ]);

        $shift = null;
        $cleanCode = null;
        if (!empty($validated['shift_code'])) {
            $shift = CashierShift::where('shift_code', $validated['shift_code'])->where('status', 'OPEN')->first();
        }
        if (!$shift && !empty($validated['shift_id'])) {
            $shift = CashierShift::where('id', $validated['shift_id'])->where('status', 'OPEN')->first();
        }
        if (!$shift && !empty($validated['terminal_code'])) {
            preg_match('/(POS-\d+|TERM-\d+)/i', $validated['terminal_code'], $m);
            $cleanCode = !empty($m[1]) ? strtoupper($m[1]) : trim($validated['terminal_code']);
            $shift = CashierShift::where('status', 'OPEN')
                ->where(function ($q) use ($cleanCode, $validated) {
                    $q->where('terminal_code', $cleanCode)
                      ->orWhere('terminal_code', $validated['terminal_code'])
                      ->orWhere('terminal_code', 'like', $cleanCode . '%');
                })
                ->latest('id')
                ->first();
        }
        if (!$shift && Auth::check()) {
            $shift = CashierShift::where('status', 'OPEN')->where('user_id', Auth::id())->latest('id')->first();
        }
        if (!$shift) {
            $shift = CashierShift::where('status', 'OPEN')->latest('id')->first();
        }

        if (!$shift && !empty($validated['shift_code'])) {
            $alreadyClosed = CashierShift::where('shift_code', $validated['shift_code'])->latest('id')->first();
            if ($alreadyClosed) {
                return response()->json([
                    'success' => true,
                    'already_closed' => true,
                    'message' => "Sesi {$alreadyClosed->shift_code} sudah ditutup sebelumnya.",
                    'data' => [
                        'shift_code' => $alreadyClosed->shift_code,
                        'terminal_code' => $alreadyClosed->terminal_code,
                        'cashier_name' => $alreadyClosed->cashier_name,
                        'opened_at' => $alreadyClosed->opened_at?->toIso8601String(),
                        'closed_at' => $alreadyClosed->closed_at?->toIso8601String(),
                        'actual_cash' => (float) $alreadyClosed->actual_cash,
                        'closing_notes' => $alreadyClosed->closing_notes,
                    ],
                ]);
            }
        }

        if (!$shift) {
            $lastClosed = null;
            if (!empty($cleanCode)) {
                $lastClosed = CashierShift::where('terminal_code', $cleanCode)->latest('id')->first();
            }
            if (!$lastClosed && Auth::check()) {
                $lastClosed = CashierShift::where('user_id', Auth::id())->latest('id')->first();
            }
            if (!$lastClosed) {
                $lastClosed = CashierShift::latest('id')->first();
            }

            return response()->json([
                'success' => true,
                'already_closed' => true,
                'message' => $lastClosed
                    ? "Sesi {$lastClosed->shift_code} ({$lastClosed->terminal_code}) sudah berstatus tutup sebelumnya."
                    : 'Tidak ada sesi kasir aktif yang perlu ditutup. Sesi berstatus siap.',
                'data' => $lastClosed ? [
                    'shift_code' => $lastClosed->shift_code,
                    'terminal_code' => $lastClosed->terminal_code,
                    'cashier_name' => $lastClosed->cashier_name,
                    'opened_at' => $lastClosed->opened_at?->toIso8601String(),
                    'closed_at' => $lastClosed->closed_at?->toIso8601String(),
                    'actual_cash' => (float) $lastClosed->actual_cash,
                    'closing_notes' => $lastClosed->closing_notes,
                ] : null,
            ]);
        }

        $authUser = Auth::user();
        if ($authUser && $authUser->isKasir()) {
            $ownsShift = false;
            if ($shift->user_id && (int) $shift->user_id === (int) $authUser->id) {
                $ownsShift = true;
            } elseif ($shift->cashier_nik) {
                $shiftNik = strtolower(trim($shift->cashier_nik));
                $authNik = strtolower(trim((string) $authUser->nik));
                $authUsername = strtolower(trim((string) $authUser->username));
                $authName = strtolower(trim((string) $authUser->name));
                if ($shiftNik === $authNik || $shiftNik === $authUsername || $shiftNik === $authName) {
                    $ownsShift = true;
                }
            } elseif (empty($shift->user_id) && empty($shift->cashier_nik)) {
                $ownsShift = true;
            }
            if (!$ownsShift) {
                return response()->json([
                    'success' => false,
                    'message' => "Anda tidak diizinkan menutup sesi shift milik kasir lain ({$shift->cashier_name}).",
                ], 403);
            }
        }

        $closingNotes = $validated['closing_notes'] ?? null;
        if (($request->input('handover_type') === 'handover' || str_contains($closingNotes ?? '', '[SERAH TERIMA]')) && !str_contains($closingNotes ?? '', '[SERAH TERIMA]')) {
            $closingNotes = '[SERAH TERIMA] ' . ($closingNotes ?: 'Serah terima ke kasir berikutnya');
        }

        $shift->closeShift((float) $validated['actual_cash'], $closingNotes);
        $shift->update(['synced_at' => null]);

        // Update Jam Keluar Absensi Karyawan di PostgreSQL
        try {
            $closingReason = ($request->input('handover_type') === 'handover' || str_contains($closingNotes ?? '', '[SERAH TERIMA]'))
                ? "Serah terima shift {$shift->shift_code}"
                : "Shift {$shift->shift_code} ditutup";

            \App\Services\AttendanceService::recordCashierCheckOut(
                shift: $shift,
                staffName: $shift->cashier_name,
                terminalCode: $shift->terminal_code,
                noteReason: $closingReason
            );

            \App\Models\AuditLog::record([
                'user_id' => $shift->user_id ?? $authUser?->id,
                'user_name' => $shift->cashier_name,
                'user_role' => $authUser?->role ?? 'Kasir',
                'location' => $shift->branch ?: 'Cabang Pusat',
                'category' => 'Sesi Kasir',
                'action_name' => 'Tutup Sesi Shift Kasir',
                'description' => "Sesi {$shift->shift_code} ({$shift->terminal_code}) ditutup. Kas fisik: Rp " . number_format((float) $shift->actual_cash, 0, ',', '.') . " ({$shift->variance_status}).",
                'reference' => $shift->shift_code,
                'status' => 'BERHASIL',
                'source' => $request->ip() . ' / ' . $shift->terminal_code,
            ]);
        } catch (\Throwable $e) {}

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

        \App\Http\Controllers\Api\Shared\AuthController::dispatchBackgroundOperationalSync();

        return response()->json([
            'success' => true,
            'message' => "Sesi {$shift->shift_code} berhasil ditutup. Audit kas: {$shift->variance_status} (Selisih: Rp " . number_format($shift->variance, 0, ',', '.') . ")",
            'data' => [
                'shift_code' => $shift->shift_code,
                'terminal_code' => $shift->terminal_code,
                'status' => $shift->status,
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
            'offline_id' => 'nullable|string|max:120',
            'shift_id' => 'nullable|integer|exists:cashier_shifts,id',
            'terminal_code' => 'nullable|string|max:50',
            'type' => 'required|in:IN,OUT,KAS_MASUK,PENGELUARAN_TUNAI,RETUR_TUNAI,CASH_DROP',
            'amount' => 'required|numeric|min:1',
            'reason' => 'nullable|string|max:255',
        ]);

        if (!empty($validated['offline_id'])) {
            $dup = CashMovement::where('offline_id', $validated['offline_id'])->first();
            if ($dup) {
                $existingShift = $dup->shift;
                return response()->json([
                    'success' => true,
                    'duplicate' => true,
                    'message' => 'Mutasi kas sudah tercatat sebelumnya (idempotent).',
                    'data' => [
                        'shift_id' => $existingShift?->id,
                        'shift_code' => $existingShift?->shift_code,
                        'terminal_code' => $dup->terminal_code,
                        'expected_cash' => (float) ($existingShift?->expected_cash ?? 0),
                        'cash_in' => (float) ($existingShift?->cash_in ?? 0),
                        'cash_out' => (float) ($existingShift?->cash_out ?? 0),
                    ],
                ]);
            }
        }

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

        $authUser = Auth::user();
        if ($authUser && $authUser->isKasir()) {
            $ownsShift = ($shift->user_id && $shift->user_id === $authUser->id)
                || ($shift->cashier_nik && ($shift->cashier_nik === $authUser->nik || $shift->cashier_nik === $authUser->username));
            if (!$ownsShift) {
                return response()->json([
                    'success' => false,
                    'message' => "Anda tidak diizinkan mencatat mutasi kas pada shift milik kasir lain ({$shift->cashier_name}).",
                ], 403);
            }
        }

        $isIncoming = in_array($validated['type'], ['IN', 'KAS_MASUK']);
        DB::transaction(function () use ($shift, $validated, $isIncoming, $authUser) {
            $lockedShift = CashierShift::lockForUpdate()->find($shift->id);
            CashMovement::create([
                'offline_id' => $validated['offline_id'] ?? null,
                'shift_id' => $lockedShift->id,
                'location_id' => $lockedShift->location_id,
                'terminal_code' => $lockedShift->terminal_code,
                'type' => strtoupper($validated['type']),
                'amount' => (float) $validated['amount'],
                'reason' => $validated['reason'] ?? null,
                'user_id' => $authUser?->id ?? $lockedShift->user_id,
                'cashier_nik' => $authUser?->nik ?? $lockedShift->cashier_nik,
                'occurred_at' => now(),
            ]);

            if ($isIncoming) {
                $lockedShift->cash_in += $validated['amount'];
            } else {
                $lockedShift->cash_out += $validated['amount'];
            }

            $lockedShift->expected_cash = $lockedShift->calculateExpectedCash();
            $lockedShift->synced_at = null;
            $lockedShift->save();
        });

        $shift->refresh();
        \App\Http\Controllers\Api\Shared\AuthController::dispatchBackgroundOperationalSync();

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

    /**
     * Get shift history for the authenticated cashier or terminal.
     */
    public function history(Request $request): JsonResponse
    {
        $terminal = $request->query('terminal_code');
        $cashierNik = $request->query('cashier_nik');
        $limit = max(5, min(100, (int) $request->query('limit', 50)));

        $query = CashierShift::with('location')->orderBy('id', 'desc');
        if ($terminal) {
            $query->where('terminal_code', $terminal);
        }
        if ($cashierNik) {
            $query->where('cashier_nik', $cashierNik);
        }

        $shifts = $query->take($limit)->get();

        return response()->json([
            'success' => true,
            'count' => $shifts->count(),
            'data' => $shifts->map(function ($s) {
                $isHandover = str_contains($s->closing_notes ?? '', '[SERAH TERIMA]');
                $variance = (float) ($s->variance ?? 0);
                $varianceStatus = $s->variance_status ?? ($variance == 0 ? 'SESUAI' : ($variance > 0 ? 'LEBIH' : 'KURANG'));

                return [
                    'id' => $s->id,
                    'shift_code' => $s->shift_code,
                    'sessionId' => $s->shift_code,
                    'terminal_code' => $s->terminal_code,
                    'terminalId' => $s->terminal_code,
                    'terminal_id' => $s->terminal_code,
                    'terminalLabel' => $s->terminal_code,
                    'location_id' => $s->location_id,
                    'branch' => $s->location?->name ?? $s->branch ?? 'Toko Pusat',
                    'cashier_name' => $s->cashier_name,
                    'cashierName' => $s->cashier_name,
                    'cashier_nik' => $s->cashier_nik,
                    'cashierId' => $s->cashier_nik,
                    'opening_cash' => (float) $s->opening_cash,
                    'openingCash' => (float) $s->opening_cash,
                    'cash_sales_total' => (float) $s->cash_sales_total,
                    'qris_sales_total' => (float) $s->qris_sales_total,
                    'credit_sales_total' => (float) $s->credit_sales_total,
                    'total_sales' => (float) $s->total_sales,
                    'transaction_count' => (int) $s->transaction_count,
                    'cash_in' => (float) $s->cash_in,
                    'cash_out' => (float) $s->cash_out,
                    'expected_cash' => (float) ($s->expected_cash ?? $s->calculateExpectedCash()),
                    'expectedClosingCash' => (float) ($s->expected_cash ?? $s->calculateExpectedCash()),
                    'actual_cash' => (float) ($s->actual_cash ?? 0),
                    'actualClosingCash' => (float) ($s->actual_cash ?? 0),
                    'variance' => $variance,
                    'cashVariance' => $variance,
                    'variance_status' => $varianceStatus,
                    'varianceStatus' => $varianceStatus,
                    'closing_notes' => $s->closing_notes,
                    'closeNote' => $s->closing_notes,
                    'is_handover' => $isHandover,
                    'isHandover' => $isHandover,
                    'handover_type' => $isHandover ? 'handover' : 'close_only',
                    'handoverType' => $isHandover ? 'handover' : 'none',
                    'status' => $s->status,
                    'startedAt' => $s->opened_at ? $s->opened_at->format('H:i') : '-',
                    'endedAt' => $s->closed_at ? $s->closed_at->format('H:i') : '-',
                    'opened_at' => $s->opened_at ? $s->opened_at->toIso8601String() : null,
                    'closed_at' => $s->closed_at ? $s->closed_at->toIso8601String() : null,
                    'created_at' => $s->created_at ? $s->created_at->toIso8601String() : null,
                ];
            }),
        ]);
    }
}
