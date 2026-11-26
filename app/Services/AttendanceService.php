<?php

namespace App\Services;

use App\Events\AttendanceRecordedEvent;
use App\Models\Attendance;
use App\Models\CashierShift;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class AttendanceService
{
    /**
     * Record or update check-in (masuk) for a cashier upon portal login or shift opening.
     */
    public static function recordCashierCheckIn(
        ?User $user = null,
        ?string $staffName = null,
        ?string $terminalCode = null,
        ?string $branch = null,
        ?string $notes = null
    ): ?Attendance {
        try {
            $now = Carbon::now();
            $today = $now->toDateString();

            $resolvedStaffName = $staffName ?? $user?->name;
            if (!$user && !$resolvedStaffName) {
                return null;
            }

            $resolvedBranch = $branch ?: ($user?->branch ? trim(explode(',', $user->branch)[0]) : 'Cabang Pusat');

            $baseQuery = Attendance::query()
                ->whereDate('date', $today)
                ->where(function ($q) use ($user, $resolvedStaffName) {
                    if ($user) {
                        $q->where('user_id', $user->id)
                          ->orWhere('staff_name', $user->name);
                    }
                    if ($resolvedStaffName) {
                        $q->orWhere('staff_name', $resolvedStaffName);
                    }
                });

            $attendance = null;
            if ($terminalCode && $terminalCode !== '-') {
                $attendance = (clone $baseQuery)->where('terminal', $terminalCode)->latest('id')->first();
            }
            if (!$attendance) {
                $attendance = (clone $baseQuery)
                    ->where(function ($cq) {
                        $cq->where('check_out', '-')->orWhereNull('check_out')->orWhere('check_out', '');
                    })
                    ->latest('id')
                    ->first()
                    ?? (clone $baseQuery)->latest('id')->first();
            }

            if (!$attendance) {
                $attendance = Attendance::create([
                    'staff_name' => $resolvedStaffName,
                    'user_id' => $user?->id,
                    'terminal' => $terminalCode ?: '-',
                    'branch' => $resolvedBranch,
                    'date' => $today,
                    'check_in' => $now->format('H:i'),
                    'check_out' => '-',
                    'status' => 'HADIR',
                    'notes' => $notes ?: 'Login portal kasir',
                    'synced_at' => null,
                ]);
            } else {
                if (empty($attendance->check_in) || $attendance->check_in === '-') {
                    $attendance->check_in = $now->format('H:i');
                }
                if (!empty($attendance->check_out) && $attendance->check_out !== '-') {
                    // Cashier logged back in for an active session on the same day
                    $attendance->check_out = '-';
                }
                if ($user && !$attendance->user_id) {
                    $attendance->user_id = $user->id;
                }
                if ($terminalCode && ($terminalCode !== '-' || empty($attendance->terminal))) {
                    $attendance->terminal = $terminalCode;
                }
                if ($branch) {
                    $attendance->branch = $branch;
                }
                if ($notes) {
                    $attendance->notes = $notes;
                }
                $attendance->status = 'HADIR';
                $attendance->synced_at = null;
                $attendance->save();
            }

            event(new AttendanceRecordedEvent($attendance, 'MASUK'));

            return $attendance;
        } catch (\Throwable $e) {
            Log::error('AttendanceService::recordCashierCheckIn error: ' . $e->getMessage(), [
                'user_id' => $user?->id,
                'staff_name' => $staffName,
            ]);
            return null;
        }
    }

    /**
     * Record check-out (pulang) for a cashier upon shift closing or logout.
     */
    public static function recordCashierCheckOut(
        ?CashierShift $shift = null,
        ?User $user = null,
        ?string $staffName = null,
        ?string $terminalCode = null,
        ?string $noteReason = null
    ): ?Attendance {
        try {
            $now = Carbon::now();
            $today = $now->toDateString();
            $shiftDate = $shift?->opened_at ? $shift->opened_at->toDateString() : $shift?->created_at?->toDateString();

            $resolvedUser = $user ?? ($shift?->user_id ? User::find($shift->user_id) : null);
            $resolvedStaffName = $staffName ?? $shift?->cashier_name ?? $resolvedUser?->name;
            $resolvedTerminal = $terminalCode ?? $shift?->terminal_code;

            if (!$resolvedUser && !$resolvedStaffName) {
                return null;
            }

            // Query attendance matching user/staff
            $query = Attendance::query()
                ->where(function ($q) use ($resolvedUser, $resolvedStaffName) {
                    if ($resolvedUser) {
                        $q->where('user_id', $resolvedUser->id)
                          ->orWhere('staff_name', $resolvedUser->name);
                    }
                    if ($resolvedStaffName) {
                        $q->orWhere('staff_name', $resolvedStaffName);
                    }
                })
                ->where(function ($dq) use ($today, $shiftDate) {
                    $dq->whereDate('date', $today);
                    if ($shiftDate && $shiftDate !== $today) {
                        $dq->orWhereDate('date', $shiftDate);
                    }
                });

            // Priority 1: Match terminal + check_out not set or '-'
            $attendance = (clone $query)
                ->when($resolvedTerminal, fn ($tq) => $tq->where('terminal', $resolvedTerminal))
                ->where(function ($cq) {
                    $cq->where('check_out', '-')->orWhereNull('check_out')->orWhere('check_out', '');
                })
                ->latest('id')
                ->first();

            // Priority 2: Any matching terminal for that user today/shiftDate
            if (!$attendance) {
                $attendance = (clone $query)
                    ->when($resolvedTerminal, fn ($tq) => $tq->where('terminal', $resolvedTerminal))
                    ->latest('id')
                    ->first();
            }

            // Priority 3: Fallback without terminal restriction if still '-'
            if (!$attendance) {
                $attendance = (clone $query)
                    ->where(function ($cq) {
                        $cq->where('check_out', '-')->orWhereNull('check_out')->orWhere('check_out', '');
                    })
                    ->latest('id')
                    ->first();
            }

            if (!$attendance) {
                // If no record exists at all, create check-out record
                $attendance = Attendance::create([
                    'staff_name' => $resolvedStaffName,
                    'user_id' => $resolvedUser?->id,
                    'terminal' => $resolvedTerminal ?: 'POS-01',
                    'branch' => $shift?->branch ?: ($resolvedUser?->branch ?: 'Cabang Pusat'),
                    'date' => $today,
                    'check_in' => $shift?->opened_at ? $shift->opened_at->format('H:i') : $now->format('H:i'),
                    'check_out' => $now->format('H:i'),
                    'status' => 'HADIR',
                    'notes' => $noteReason ?: 'Check-out serah terima / tutup kasir',
                ]);
            } else {
                $attendance->check_out = $now->format('H:i');
                if ($noteReason) {
                    $attendance->notes = ($attendance->notes ? $attendance->notes . ' | ' : '') . $noteReason;
                }
                if ($resolvedTerminal && empty($attendance->terminal)) {
                    $attendance->terminal = $resolvedTerminal;
                }
                $attendance->synced_at = null;
                $attendance->save();
            }

            // Dispatch real-time WebSocket event
            event(new AttendanceRecordedEvent($attendance, 'PULANG'));

            return $attendance;
        } catch (\Throwable $e) {
            Log::error('AttendanceService::recordCashierCheckOut error: ' . $e->getMessage(), [
                'exception' => $e,
                'shift_id' => $shift?->id,
                'user_id' => $user?->id,
            ]);
            return null;
        }
    }
}
