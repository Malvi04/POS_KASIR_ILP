<?php

namespace App\Http\Controllers\Api\Manager;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Api\Shared\NotificationController;
use App\Models\Attendance;
use App\Models\LeaveRequest;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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

        // Seed initial attendance records if table is empty
        if ($list->isEmpty() && Attendance::count() === 0) {
            $initials = [
                ['terminal' => 'Kasir 1', 'staff_name' => 'Andi Setiawan', 'check_in' => '06:55', 'check_out' => '15:05', 'branch' => 'Cabang Pusat', 'status' => 'TEPAT_WAKTU', 'date' => $date],
                ['terminal' => 'Kasir 2', 'staff_name' => 'Bambang Pamungkas', 'check_in' => '07:15', 'check_out' => '-', 'branch' => 'Cabang A', 'status' => 'TERLAMBAT', 'date' => $date],
                ['terminal' => 'Kasir 3', 'staff_name' => 'Citra Dewi', 'check_in' => '06:50', 'check_out' => '-', 'branch' => 'Cabang Pusat', 'status' => 'TEPAT_WAKTU', 'date' => $date],
                ['terminal' => 'Kasir 4', 'staff_name' => 'Dedi Nurcahyo', 'check_in' => '06:45', 'check_out' => '-', 'branch' => 'Cabang Pusat', 'status' => 'TEPAT_WAKTU', 'date' => $date],
                ['terminal' => 'Kasir 5', 'staff_name' => 'Eka Ramadhan', 'check_in' => '07:02', 'check_out' => '-', 'branch' => 'Cabang A', 'status' => 'TEPAT_WAKTU', 'date' => $date],
            ];

            foreach ($initials as $item) {
                Attendance::create($item);
            }

            $list = Attendance::whereDate('date', $date)->orderBy('check_in', 'asc')->get();
        }

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

        if ($validated['type'] === 'MASUK') {
            if ($attendance) {
                return response()->json([
                    'success' => false,
                    'message' => 'Petugas sudah melakukan absen masuk hari ini.',
                ], 422);
            }

            $isLate = Carbon::now()->hour > 7 || (Carbon::now()->hour === 7 && Carbon::now()->minute > 5);
            $attendance = Attendance::create([
                'staff_name' => $validated['staffName'],
                'terminal' => $validated['terminal'] ?? 'Kasir 01',
                'branch' => $validated['branch'] ?? 'Cabang Pusat',
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
                    'staff_name' => $validated['staffName'],
                    'terminal' => $validated['terminal'] ?? 'Kasir 01',
                    'branch' => $validated['branch'] ?? 'Cabang Pusat',
                    'date' => $date,
                    'check_in' => '07:00',
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
     * List leave / izin / sakit requests.
     */
    public function leaves(): JsonResponse
    {
        $leaves = LeaveRequest::orderBy('id', 'desc')->get();

        if ($leaves->isEmpty() && LeaveRequest::count() === 0) {
            $defaults = [
                ['staff_name' => 'Rico Wijaya', 'type' => 'SAKIT', 'date_range' => Carbon::today()->format('d M Y'), 'reason' => 'Demam flu butuh istirahat dokter', 'status' => 'DISETUJUI', 'approved_by' => 'Manager HRD'],
                ['staff_name' => 'Santi Larasati', 'type' => 'CUTI', 'date_range' => Carbon::today()->addDays(1)->format('d M') . ' - ' . Carbon::today()->addDays(3)->format('d M Y'), 'reason' => 'Keperluan keluarga di luar kota', 'status' => 'MENUNGGU', 'approved_by' => null],
            ];

            foreach ($defaults as $d) {
                LeaveRequest::create($d);
            }
            $leaves = LeaveRequest::orderBy('id', 'desc')->get();
        }

        return response()->json([
            'success' => true,
            'data' => $leaves->map(function ($l) {
                return [
                    'id' => $l->id,
                    'staffName' => $l->staff_name,
                    'type' => $l->type,
                    'date' => $l->date_range,
                    'reason' => $l->reason,
                    'status' => $l->status,
                    'approved_by' => $l->approved_by,
                ];
            }),
        ]);
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
            'approved_by' => $validated['approved_by'] ?? 'Supervisor Bertugas',
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
     * Shift schedules.
     */
    public function shifts(): JsonResponse
    {
        $shifts = [
            ['id' => 1, 'name' => 'Shift Pagi (07:00 - 15:00)', 'staff' => ['Andi Setiawan', 'Budi Kurniawan', 'Citra Dewi'], 'branch' => 'Cabang Pusat'],
            ['id' => 2, 'name' => 'Shift Siang (14:30 - 22:30)', 'staff' => ['Bambang Pamungkas', 'Dedi Nurcahyo', 'Eka Ramadhan'], 'branch' => 'Cabang Pusat'],
            ['id' => 3, 'name' => 'Shift Pagi Cabang A (07:30 - 15:30)', 'staff' => ['Ahmad Fauzi', 'Siti Aminah'], 'branch' => 'Cabang A'],
        ];

        return response()->json([
            'success' => true,
            'data' => $shifts,
        ]);
    }
}
