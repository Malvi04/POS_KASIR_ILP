<?php

namespace App\Http\Controllers\Api\Owner;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuditLogController extends Controller
{
    /**
     * Display a listing of system audit logs with full filtering.
     */
    public function index(Request $request): JsonResponse
    {
        $query = AuditLog::query();

        // 1. Search Query
        if ($request->filled('search')) {
            $s = '%' . trim($request->search) . '%';
            $query->where(function ($q) use ($s) {
                $q->where('user_name', 'LIKE', $s)
                  ->orWhere('action_name', 'LIKE', $s)
                  ->orWhere('description', 'LIKE', $s)
                  ->orWhere('reference', 'LIKE', $s)
                  ->orWhere('log_id', 'LIKE', $s);
            });
        }

        // 2. Category Filter
        if ($request->filled('category') && $request->category !== 'all') {
            $query->where('category', $request->category);
        }

        // 3. Role Filter
        if ($request->filled('role') && $request->role !== 'all') {
            $query->where('user_role', $request->role);
        }

        // 4. Location Filter
        if ($request->filled('location') && $request->location !== 'all') {
            $query->where('location', $request->location);
        }

        // 5. Status Filter
        if ($request->filled('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        // 6. Date Filter
        if ($request->filled('date')) {
            if ($request->date === 'today') {
                $query->whereDate('created_at', Carbon::today());
            } elseif ($request->date === '7d') {
                $query->where('created_at', '>=', Carbon::now()->subDays(7));
            } elseif ($request->date === '30d') {
                $query->where('created_at', '>=', Carbon::now()->subDays(30));
            } elseif ($request->date === 'custom') {
                if ($request->filled('start_date')) {
                    $query->whereDate('created_at', '>=', $request->start_date);
                }
                if ($request->filled('end_date')) {
                    $query->whereDate('created_at', '<=', $request->end_date);
                }
            }
        }

        // Order
        $sortOrder = strtolower($request->get('sort', 'desc')) === 'asc' ? 'asc' : 'desc';
        $logs = $query->orderBy('created_at', $sortOrder)->get();

        // If table is completely empty, seed initial realistic logs
        if ($logs->isEmpty() && AuditLog::count() === 0) {
            $this->seedInitialLogs();
            $logs = AuditLog::orderBy('created_at', $sortOrder)->get();
        }

        // Calculate summary counters
        $allLogs = AuditLog::all();
        $todayStr = Carbon::today()->toDateString();
        $todayCount = $allLogs->filter(fn($l) => $l->created_at && $l->created_at->toDateString() === $todayStr)->count();
        $dataChangesCount = $allLogs->whereIn('category', ['Stok & Persediaan', 'Barang Masuk / Distribusi / Mutasi', 'Anggota & Plafon', 'Pengaturan Sistem', 'User & Hak Akses'])->count();
        $trxCount = $allLogs->whereIn('category', ['Transaksi Penjualan', 'Void / Pembatalan', 'Refund / Retur'])->count();
        $secCount = $allLogs->whereIn('category', ['Login & Logout', 'User & Hak Akses', 'Sesi Kasir'])->count();

        $formatted = $logs->map(function ($l) {
            return [
                'id' => $l->log_id ?? ('LOG-' . $l->id),
                'timestamp' => $l->created_at ? $l->created_at->format('Y-m-d H:i:s') : now()->format('Y-m-d H:i:s'),
                'userId' => $l->user_id ? 'USR-' . $l->user_id : 'SYS-01',
                'userName' => $l->user_name,
                'userRole' => $l->user_role,
                'location' => $l->location,
                'category' => $l->category,
                'actionName' => $l->action_name,
                'description' => $l->description,
                'reference' => $l->reference ?? '-',
                'status' => $l->status ?? 'BERHASIL',
                'source' => $l->source ?? '127.0.0.1 / Web Portal',
                'changes' => $l->changes ?? [],
            ];
        });

        return response()->json([
            'success' => true,
            'summary' => [
                'today_count' => $todayCount,
                'data_changes_count' => $dataChangesCount,
                'transaction_count' => $trxCount,
                'security_count' => $secCount,
                'total_count' => $allLogs->count(),
            ],
            'data' => $formatted,
        ]);
    }

    /**
     * Record a new audit log.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'user_name' => 'required|string|max:150',
            'user_role' => 'nullable|string|max:50',
            'location' => 'nullable|string|max:100',
            'category' => 'required|string|max:100',
            'action_name' => 'required|string|max:150',
            'description' => 'required|string',
            'reference' => 'nullable|string|max:100',
            'status' => 'nullable|string|max:50',
            'source' => 'nullable|string|max:100',
            'changes' => 'nullable|array',
        ]);

        $log = AuditLog::record($validated);

        return response()->json([
            'success' => true,
            'message' => 'Catatan audit log berhasil direkam.',
            'data' => $log,
        ], 201);
    }

    /**
     * Seed initial realistic logs if empty.
     */
    private function seedInitialLogs(): void
    {
        $now = Carbon::now();
        $sampleLogs = [
            [
                'log_id' => 'LOG-' . $now->format('Ymd') . '-0001',
                'user_name' => 'Ir. Hartono Wibowo (Owner)',
                'user_role' => 'Owner',
                'location' => 'Konsolidasi 3 Cabang',
                'category' => 'User & Hak Akses',
                'action_name' => 'Inisialisasi Console Eksekutif',
                'description' => 'Pemeriksaan kepatuhan RBAC multi-cabang dan sinkronisasi hak akses seluruh petugas.',
                'reference' => 'RBAC-INIT',
                'created_at' => $now->copy()->subMinutes(15),
            ],
            [
                'log_id' => 'LOG-' . $now->format('Ymd') . '-0002',
                'user_name' => 'Andi Pratama (Manager)',
                'user_role' => 'Manager',
                'location' => 'Cabang 1 - Mill / Pabrik',
                'category' => 'Sesi Kasir',
                'action_name' => 'Approval Buka Shift Kasir',
                'description' => 'Verifikasi modal awal kasir laci kasir terminal 01 sebesar Rp 500.000.',
                'reference' => 'SFT-001',
                'created_at' => $now->copy()->subHours(2),
            ],
            [
                'log_id' => 'LOG-' . $now->format('Ymd') . '-0003',
                'user_name' => 'Budi Kurniawan (Kasir)',
                'user_role' => 'Kasir',
                'location' => 'Toko Pusat',
                'category' => 'Transaksi Penjualan',
                'action_name' => 'Checkout Nota Belanja',
                'description' => 'Penyelesaian transaksi penjualan tunai dengan diskon kupon BBM anggota.',
                'reference' => 'TRX-202611-001',
                'created_at' => $now->copy()->subHours(1),
            ],
        ];

        foreach ($sampleLogs as $log) {
            AuditLog::create($log);
        }
    }
}
