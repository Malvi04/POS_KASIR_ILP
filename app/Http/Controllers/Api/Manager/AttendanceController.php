<?php

namespace App\Http\Controllers\Api\Manager;

use App\Events\AttendanceRecordedEvent;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Api\Shared\NotificationController;
use App\Models\Attendance;
use App\Models\CashierShift;
use App\Models\LeaveRequest;
use App\Models\User;
use App\Services\ExcelExportService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AttendanceController extends Controller
{
    /**
     * List attendance records with optional filters.
     */
    public function index(Request $request): JsonResponse
    {
        $date = $request->input('date', Carbon::today()->toDateString());
        $query = Attendance::whereDate('date', $date);

        if ($request->filled('branch') && $request->branch !== 'all') {
            $query->where('branch', $request->branch);
        }

        if ($request->filled('terminal') && $request->terminal !== 'all') {
            $query->where('terminal', $request->terminal);
        }

        if ($request->filled('q')) {
            $term = $request->q;
            $query->where(function ($q) use ($term) {
                $q->where('staff_name', 'ilike', "%{$term}%")
                  ->orWhere('terminal', 'ilike', "%{$term}%")
                  ->orWhere('branch', 'ilike', "%{$term}%");
            });
        }

        $list = $query->orderBy('check_in', 'asc')->get();

        return response()->json([
            'success' => true,
            'data' => $list->map(function ($a) {
                return [
                    'id' => $a->id,
                    'terminal' => $a->terminal,
                    'staffName' => $a->staff_name,
                    'checkIn' => $a->check_in ?: '-',
                    'checkOut' => $a->check_out ?: '-',
                    'branch' => $a->branch,
                    'status' => $a->status,
                    'notes' => $a->notes,
                ];
            }),
        ]);
    }

    /**
     * Record manual check-in / check-out by Supervisor.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'staffName' => 'required|string|max:255',
            'terminal' => 'nullable|string|max:50',
            'branch' => 'nullable|string|max:100',
            'type' => 'required|in:MASUK,PULANG',
            'notes' => 'nullable|string|max:500',
            'date' => 'nullable|date',
        ]);

        $date = $validated['date'] ?? Carbon::today()->toDateString();
        $timeNow = Carbon::now()->format('H:i');

        $attendance = Attendance::where('staff_name', $validated['staffName'])
            ->whereDate('date', $date)
            ->first();

        $matchedUser = User::where('name', $validated['staffName'])->first();
        $defaultBranch = $matchedUser?->branch ?: '-';

        if ($validated['type'] === 'MASUK') {
            if ($attendance) {
                return response()->json([
                    'success' => false,
                    'message' => 'Petugas sudah melakukan absen masuk hari ini.',
                ], 422);
            }

            $isLate = Carbon::now()->hour > 7 || (Carbon::now()->hour === 7 && Carbon::now()->minute > 5);
            $attendance = Attendance::create([
                'user_id' => $matchedUser?->id,
                'staff_name' => $validated['staffName'],
                'terminal' => $validated['terminal'] ?? '-',
                'branch' => $validated['branch'] ?? $defaultBranch,
                'date' => $date,
                'check_in' => $timeNow,
                'check_out' => '-',
                'status' => $isLate ? 'TERLAMBAT' : 'TEPAT_WAKTU',
                'notes' => $validated['notes'] ?? null,
            ]);
        } else {
            // Check out
            if (!$attendance) {
                $attendance = Attendance::create([
                    'user_id' => $matchedUser?->id,
                    'staff_name' => $validated['staffName'],
                    'terminal' => $validated['terminal'] ?? '-',
                    'branch' => $validated['branch'] ?? $defaultBranch,
                    'date' => $date,
                    'check_in' => '-',
                    'check_out' => $timeNow,
                    'status' => 'TEPAT_WAKTU',
                    'notes' => $validated['notes'] ?? null,
                ]);
            } else {
                $attendance->update([
                    'check_out' => $timeNow,
                    'notes' => $validated['notes'] ?: $attendance->notes,
                ]);
            }
        }

        $statusCode = $validated['type'] === 'MASUK' ? 201 : 200;

        try {
            event(new AttendanceRecordedEvent($attendance, $validated['type']));
        } catch (\Throwable $e) {}

        return response()->json([
            'success' => true,
            'message' => 'Absensi ' . strtolower($validated['type']) . ' berhasil dicatat',
            'data' => [
                'id' => $attendance->id,
                'terminal' => $attendance->terminal,
                'staffName' => $attendance->staff_name,
                'checkIn' => $attendance->check_in,
                'checkOut' => $attendance->check_out,
                'branch' => $attendance->branch,
                'status' => $attendance->status,
            ],
        ], $statusCode);
    }

    /**
     * Build query for leave requests with time periods and search filters.
     */
    protected function buildLeavesQuery(Request $request)
    {
        $query = LeaveRequest::with(['user.branch'])->orderBy('id', 'desc');

        $period = $request->input('period', 'all');

        if ($period === 'hari' || $period === 'day') {
            $date = $request->input('date', Carbon::today()->toDateString());
            $query->whereDate('created_at', $date);
        } elseif ($period === 'minggu' || $period === 'week') {
            $refDate = $request->filled('date') ? Carbon::parse($request->date) : Carbon::today();
            $startOfWeek = $refDate->copy()->startOfWeek();
            $endOfWeek = $refDate->copy()->endOfWeek();
            $query->whereBetween('created_at', [$startOfWeek, $endOfWeek]);
        } elseif ($period === 'bulan' || $period === 'month') {
            if ($request->filled('month')) {
                if (preg_match('/^\d{4}-\d{2}$/', $request->month)) {
                    [$year, $month] = explode('-', $request->month);
                } else {
                    $month = (int) $request->month;
                    $year = $request->input('year', Carbon::now()->year);
                }
            } else {
                $month = Carbon::now()->month;
                $year = $request->input('year', Carbon::now()->year);
            }
            $query->whereYear('created_at', $year)->whereMonth('created_at', $month);
        } elseif ($period === 'tahun' || $period === 'year') {
            $year = $request->input('year', Carbon::now()->year);
            $query->whereYear('created_at', $year);
        }

        if ($request->filled('type') && $request->type !== 'all') {
            $query->where('type', strtoupper($request->type));
        }

        if ($request->filled('status') && $request->status !== 'all') {
            $query->where('status', strtoupper($request->status));
        }

        if ($request->filled('q')) {
            $term = $request->q;
            $query->where(function ($q) use ($term) {
                $q->where('staff_name', 'ilike', "%{$term}%")
                  ->orWhere('reason', 'ilike', "%{$term}%")
                  ->orWhere('date_range', 'ilike', "%{$term}%")
                  ->orWhere('approved_by', 'ilike', "%{$term}%");
            });
        }

        return $query;
    }

    /**
     * List leave / izin / sakit requests with period filters and summary.
     */
    public function leaves(Request $request): JsonResponse
    {
        $leaves = $this->buildLeavesQuery($request)->get();

        $summary = [
            'total' => $leaves->count(),
            'disetujui' => $leaves->where('status', 'DISETUJUI')->count(),
            'menunggu' => $leaves->where('status', 'MENUNGGU')->count(),
            'ditolak' => $leaves->where('status', 'DITOLAK')->count(),
            'izin' => $leaves->where('type', 'IZIN')->count(),
            'cuti' => $leaves->where('type', 'CUTI')->count(),
            'sakit' => $leaves->where('type', 'SAKIT')->count(),
        ];

        return response()->json([
            'success' => true,
            'summary' => $summary,
            'data' => $leaves->map(function ($l) {
                return [
                    'id' => $l->id,
                    'staffName' => $l->staff_name,
                    'branch' => $l->user?->branch?->name ?? ($l->user?->branch_assignment ?? '-'),
                    'type' => $l->type,
                    'date' => $l->date_range,
                    'reason' => $l->reason,
                    'status' => $l->status,
                    'approved_by' => $l->approved_by,
                    'created_at' => $l->created_at?->format('d/m/Y H:i'),
                ];
            }),
        ]);
    }

    /**
     * Export leave requests to Excel (.xlsx) based on active period filter.
     */
    public function exportLeavesExcel(Request $request, ExcelExportService $excelService): StreamedResponse
    {
        $leaves = $this->buildLeavesQuery($request)->get();

        $period = $request->input('period', 'all');
        $periodLabel = 'Semua Periode';
        $filenameSuffix = 'Semua';

        if ($period === 'hari' || $period === 'day') {
            $date = $request->input('date', Carbon::today()->toDateString());
            $periodLabel = 'Hari: ' . Carbon::parse($date)->isoFormat('D MMMM YYYY');
            $filenameSuffix = 'Hari_' . Carbon::parse($date)->format('Y-m-d');
        } elseif ($period === 'minggu' || $period === 'week') {
            $refDate = $request->filled('date') ? Carbon::parse($request->date) : Carbon::today();
            $startOfWeek = $refDate->copy()->startOfWeek();
            $endOfWeek = $refDate->copy()->endOfWeek();
            $periodLabel = 'Minggu: ' . $startOfWeek->isoFormat('D MMM YYYY') . ' - ' . $endOfWeek->isoFormat('D MMM YYYY');
            $filenameSuffix = 'Minggu_' . $startOfWeek->format('Ymd') . '-' . $endOfWeek->format('Ymd');
        } elseif ($period === 'bulan' || $period === 'month') {
            if ($request->filled('month') && preg_match('/^\d{4}-\d{2}$/', $request->month)) {
                [$year, $month] = explode('-', $request->month);
            } else {
                $month = $request->input('month', Carbon::now()->month);
                $year = $request->input('year', Carbon::now()->year);
            }
            $periodCarbon = Carbon::createFromDate((int)$year, (int)$month, 1);
            $periodLabel = 'Bulan: ' . $periodCarbon->isoFormat('MMMM YYYY');
            $filenameSuffix = 'Bulan_' . $periodCarbon->format('Y-m');
        } elseif ($period === 'tahun' || $period === 'year') {
            $year = $request->input('year', Carbon::now()->year);
            $periodLabel = 'Tahun: ' . $year;
            $filenameSuffix = 'Tahun_' . $year;
        }

        $meta = [
            'period_label' => $periodLabel,
            'generated_by' => Auth::user()?->name ?? 'Manager Operasional',
            'generated_at' => Carbon::now()->isoFormat('D MMMM YYYY, HH:mm'),
            'total_records' => $leaves->count(),
            'disetujui' => $leaves->where('status', 'DISETUJUI')->count(),
            'menunggu' => $leaves->where('status', 'MENUNGGU')->count(),
            'ditolak' => $leaves->where('status', 'DITOLAK')->count(),
        ];

        $spreadsheet = $excelService->createLeaveRequestsSpreadsheet($leaves, $meta);
        $filename = 'Rekap_Pengajuan_Izin_Cuti_' . $filenameSuffix . '_' . date('His') . '.xlsx';

        return $excelService->downloadSpreadsheet($spreadsheet, $filename);
    }

    /**
     * Submit leave request.
     */
    public function storeLeave(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'staffName' => 'required|string|max:255',
            'type' => 'required|in:SAKIT,CUTI,IZIN',
            'date' => 'required|string|max:100',
            'reason' => 'required|string|max:500',
        ]);

        $leave = LeaveRequest::create([
            'staff_name' => $validated['staffName'],
            'type' => $validated['type'],
            'date_range' => $validated['date'],
            'reason' => $validated['reason'],
            'status' => 'MENUNGGU',
        ]);

        try {
            NotificationController::notify(
                role: 'MANAGER',
                title: "Pengajuan {$validated['type']}: {$validated['staffName']}",
                message: "Staf {$validated['staffName']} mengajukan {$validated['type']} ({$validated['date']}). Alasan: {$validated['reason']}.",
                category: 'HR',
                link: '/manager/absensi',
                icon: 'event_busy',
                color: 'amber',
                referenceId: (string)$leave->id
            );
        } catch (\Throwable $e) {}

        return response()->json([
            'success' => true,
            'message' => 'Pengajuan izin berhasil dikirim ke Supervisor/Manager',
            'data' => [
                'id' => $leave->id,
                'staffName' => $leave->staff_name,
                'type' => $leave->type,
                'date' => $leave->date_range,
                'reason' => $leave->reason,
                'status' => $leave->status,
            ],
        ], 201);
    }

    /**
     * Approve or reject leave request by Supervisor.
     */
    public function updateLeaveStatus(Request $request, int $id): JsonResponse
    {
        $leave = LeaveRequest::findOrFail($id);

        $validated = $request->validate([
            'status' => 'required|in:DISETUJUI,DITOLAK,MENUNGGU',
            'approved_by' => 'nullable|string|max:100',
        ]);

        $leave->update([
            'status' => $validated['status'],
            'approved_by' => $validated['approved_by'] ?? (Auth::user()?->name ?? 'Supervisor'),
        ]);

        try {
            $isApproved = $validated['status'] === 'DISETUJUI';
            NotificationController::notify(
                role: 'KASIR',
                title: "Pengajuan {$leave->type} {$validated['status']}",
                message: "Permohonan {$leave->type} ({$leave->date_range}) untuk staf {$leave->staff_name} telah {$validated['status']}.",
                category: 'HR',
                link: '/kasir/session',
                icon: $isApproved ? 'check_circle' : 'cancel',
                color: $isApproved ? 'emerald' : 'rose',
                referenceId: (string)$leave->id
            );
        } catch (\Throwable $e) {}

        return response()->json([
            'success' => true,
            'message' => 'Status pengajuan berhasil diubah menjadi ' . $validated['status'],
            'data' => $leave,
        ]);
    }

    /**
     * Shift schedules derived from real CashierShift & Attendance records in PostgreSQL.
     */
    public function shifts(): JsonResponse
    {
        $activeOrTodayShifts = CashierShift::query()
            ->where('status', 'OPEN')
            ->orWhereDate('opened_at', Carbon::today())
            ->orderByDesc('opened_at')
            ->get();

        if ($activeOrTodayShifts->isEmpty()) {
            return response()->json([
                'success' => true,
                'data' => [],
            ]);
        }

        $userIds = $activeOrTodayShifts->pluck('user_id')->filter()->unique()->all();
        $niks = $activeOrTodayShifts->pluck('cashier_nik')->filter()->unique()->all();
        $usersById = empty($userIds) ? collect() : User::whereIn('id', $userIds)->get()->keyBy('id');
        $usersByNik = empty($niks) ? collect() : User::whereIn('nik', $niks)->get()->keyBy('nik');

        $grouped = [];
        foreach ($activeOrTodayShifts as $s) {
            $user = ($s->user_id ? $usersById->get($s->user_id) : null)
                ?? ($s->cashier_nik ? $usersByNik->get($s->cashier_nik) : null);
            $branch = $user?->branch ?: ($s->terminal_code ?: '-');
            $hour = $s->opened_at ? (int) $s->opened_at->format('H') : 8;
            $period = $hour < 14 ? 'Shift Pagi' : 'Shift Siang';
            $key = $period . '|' . $branch;

            if (!isset($grouped[$key])) {
                $grouped[$key] = [
                    'id' => count($grouped) + 1,
                    'name' => $period === 'Shift Pagi' ? 'Shift Pagi (07:00 - 15:00)' : 'Shift Siang (14:30 - 22:30)',
                    'branch' => $branch,
                    'staff' => [],
                ];
            }

            $staffLabel = $s->cashier_name ?: ($user?->name ?? $s->cashier_nik);
            if ($staffLabel && !in_array($staffLabel, $grouped[$key]['staff'], true)) {
                $grouped[$key]['staff'][] = $staffLabel;
            }
        }

        return response()->json([
            'success' => true,
            'data' => array_values($grouped),
        ]);
    }
}
