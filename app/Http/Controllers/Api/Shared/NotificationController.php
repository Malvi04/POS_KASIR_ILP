<?php

namespace App\Http\Controllers\Api\Shared;

use App\Http\Controllers\Controller;
use App\Models\SystemNotification;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    /**
     * Get notifications filtered by role with auto-seed if empty.
     */
    public function index(Request $request): JsonResponse
    {
        $role = strtoupper(trim($request->query('role', '')));
        
        // Auto-seed initial contextual notifications if table is empty
        if (SystemNotification::count() === 0) {
            $this->seedInitialNotifications();
        }

        $query = SystemNotification::query();
        if ($role && $role !== 'ALL') {
            $query->forRole($role);
        }

        $notifications = $query->orderBy('is_read', 'asc')
            ->orderBy('created_at', 'desc')
            ->limit(30)
            ->get();

        $unreadCount = $notifications->where('is_read', false)->count();

        // Format relative time in Indonesian
        $formatted = $notifications->map(function ($n) {
            return [
                'id' => $n->id,
                'role' => $n->role,
                'title' => $n->title,
                'message' => $n->message,
                'category' => $n->category,
                'icon' => $n->icon ?: 'notifications',
                'color' => $n->color ?: 'blue',
                'link' => $n->link,
                'reference_id' => $n->reference_id,
                'is_read' => (bool)$n->is_read,
                'time_ago' => $n->created_at ? $n->created_at->diffForHumans() : 'Baru saja',
                'created_at' => $n->created_at ? $n->created_at->format('d/m/Y H:i') : null,
            ];
        });

        return response()->json([
            'success' => true,
            'role' => $role ?: 'ALL',
            'unread_count' => $unreadCount,
            'total_count' => $formatted->count(),
            'data' => $formatted,
        ]);
    }

    /**
     * Create a new notification.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'role' => 'nullable|string|max:30',
            'title' => 'required|string|max:255',
            'message' => 'required|string',
            'category' => 'nullable|string|max:50',
            'icon' => 'nullable|string|max:50',
            'color' => 'nullable|string|max:30',
            'link' => 'nullable|string|max:255',
            'reference_id' => 'nullable|string|max:100',
        ]);

        $notification = SystemNotification::create([
            'role' => strtoupper($validated['role'] ?? 'ALL'),
            'title' => $validated['title'],
            'message' => $validated['message'],
            'category' => strtoupper($validated['category'] ?? 'SYSTEM'),
            'icon' => $validated['icon'] ?? 'notifications',
            'color' => $validated['color'] ?? 'blue',
            'link' => $validated['link'] ?? null,
            'reference_id' => $validated['reference_id'] ?? null,
            'is_read' => false,
        ]);

        try {
            event(new \App\Events\NotificationBroadcastEvent($notification));
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Broadcast notification failed: ' . $e->getMessage());
        }

        return response()->json([
            'success' => true,
            'message' => 'Notifikasi berhasil diterbitkan.',
            'data' => $notification,
        ], 201);
    }

    /**
     * Mark a single notification or all notifications as read.
     */
    public function markAsRead(Request $request, ?int $id = null): JsonResponse
    {
        if ($id) {
            $notification = SystemNotification::findOrFail($id);
            $notification->update([
                'is_read' => true,
                'read_at' => now(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Notifikasi ditandai sebagai telah dibaca.',
                'data' => $notification,
            ]);
        }

        $role = strtoupper(trim($request->input('role', '')));
        $query = SystemNotification::where('is_read', false);

        if ($role && $role !== 'ALL') {
            $query->forRole($role);
        }

        $affected = $query->update([
            'is_read' => true,
            'read_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => "{$affected} notifikasi ditandai sebagai telah dibaca.",
            'affected' => $affected,
        ]);
    }

    /**
     * Delete a single notification.
     */
    public function destroy(int $id): JsonResponse
    {
        $notification = SystemNotification::findOrFail($id);
        $notification->delete();

        return response()->json([
            'success' => true,
            'message' => 'Notifikasi berhasil dihapus.',
        ]);
    }

    /**
     * Clear all notifications for role.
     */
    public function clearAll(Request $request): JsonResponse
    {
        $role = strtoupper(trim($request->input('role', '')));
        $query = SystemNotification::query();

        if ($role && $role !== 'ALL') {
            $query->forRole($role);
        }

        $deleted = $query->delete();

        return response()->json([
            'success' => true,
            'message' => "{$deleted} notifikasi berhasil dibersihkan.",
        ]);
    }

    /**
     * Helper to trigger activity notification from anywhere in Laravel code.
     */
    public static function notify(
        string $role,
        string $title,
        string $message,
        string $category = 'SYSTEM',
        ?string $link = null,
        string $icon = 'notifications',
        string $color = 'blue',
        ?string $referenceId = null
    ): SystemNotification {
        $notification = SystemNotification::create([
            'role' => strtoupper($role),
            'title' => $title,
            'message' => $message,
            'category' => strtoupper($category),
            'icon' => $icon,
            'color' => $color,
            'link' => $link,
            'reference_id' => $referenceId,
            'is_read' => false,
        ]);

        try {
            event(new \App\Events\NotificationBroadcastEvent($notification));
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Broadcast notification failed: ' . $e->getMessage());
        }

        return $notification;
    }

    /**
     * Seed realistic initial role-based notifications.
     */
    protected function seedInitialNotifications(): void
    {
        $now = Carbon::now();

        $notifications = [
            // ----------------------------------------------------
            // KASIR NOTIFICATIONS
            // ----------------------------------------------------
            [
                'role' => 'KASIR',
                'title' => 'Surat Jalan SJ-TRF-20261111-002 Tiba',
                'message' => 'Gudang Pusat mengirimkan 55 unit barang (Ultra Milk 5 Tetra & Aqua Mineral 50 Botol). Menunggu verifikasi fisik kasir.',
                'category' => 'TRANSFER',
                'icon' => 'local_shipping',
                'color' => 'amber',
                'link' => '/kasir/produk/masuk',
                'reference_id' => 'SJ-TRF-20261111-002',
                'is_read' => false,
                'created_at' => $now->copy()->subMinutes(8),
            ],
            [
                'role' => 'KASIR',
                'title' => 'Approval Void Transaksi Disetujui',
                'message' => 'Supervisor Budi menyetujui void struk #TRX-20261111-0012 senilai Rp 45.000 karena salah pilih satuan.',
                'category' => 'VOID',
                'icon' => 'verified',
                'color' => 'emerald',
                'link' => '/kasir/transaksi/riwayat',
                'reference_id' => 'TRX-20261111-0012',
                'is_read' => false,
                'created_at' => $now->copy()->subMinutes(25),
            ],
            [
                'role' => 'KASIR',
                'title' => 'Plafon Kasbon Anggota Disetujui',
                'message' => 'Plafon kredit kasbon anggota Bpk. Sugeng Raharjo (NIK: 202409821005) disetujui HRD sebesar Rp 3.000.000.',
                'category' => 'MEMBER',
                'icon' => 'credit_card',
                'color' => 'blue',
                'link' => '/kasir/members',
                'reference_id' => 'MBR-005',
                'is_read' => false,
                'created_at' => $now->copy()->subHours(1),
            ],
            [
                'role' => 'KASIR',
                'title' => 'Peringatan Stok Menipis di Toko',
                'message' => 'Stok Indomie Goreng Spesial 85g tersisa 8 pcs di rak display. Harap restock dari gudang penyimpanan toko.',
                'category' => 'STOCK',
                'icon' => 'warning',
                'color' => 'rose',
                'link' => '/kasir/inventory',
                'reference_id' => 'PRD-006',
                'is_read' => false,
                'created_at' => $now->copy()->subHours(2),
            ],
            [
                'role' => 'KASIR',
                'title' => 'Kupon BBM Musim Giling Aktif',
                'message' => 'Kupon BBM Seri G-2026 telah diaktifkan koperasi dan dapat ditukarkan anggota di kasir.',
                'category' => 'COUPON',
                'icon' => 'local_gas_station',
                'color' => 'purple',
                'link' => '/kasir/kupon',
                'reference_id' => 'KPN-BBM-2026',
                'is_read' => true,
                'created_at' => $now->copy()->subHours(4),
            ],

            // ----------------------------------------------------
            // MANAGER NOTIFICATIONS
            // ----------------------------------------------------
            [
                'role' => 'MANAGER',
                'title' => 'Deviasi Barang Masuk: Cabang B',
                'message' => 'Verifikasi fisik barang transfer mencatat selisih -90 unit pada Beer Malvi (Surat BAST Selisih telah diterbitkan).',
                'category' => 'TRANSFER',
                'icon' => 'assignment_late',
                'color' => 'rose',
                'link' => '/manager/retur-masuk',
                'reference_id' => 'BAST-20261111-01',
                'is_read' => false,
                'created_at' => $now->copy()->subMinutes(12),
            ],
            [
                'role' => 'MANAGER',
                'title' => 'Permintaan Otorisasi Void Kasir',
                'message' => 'Kasir 01 meminta otorisasi pembatalan transaksi #TRX-20261111-0015 senilai Rp 125.000 karena kesalahan barcode.',
                'category' => 'VOID',
                'icon' => 'lock_open',
                'color' => 'amber',
                'link' => '/manager/retur',
                'reference_id' => 'VOID-REQ-01',
                'is_read' => false,
                'created_at' => $now->copy()->subMinutes(30),
            ],
            [
                'role' => 'MANAGER',
                'title' => 'Pengajuan Izin Sakit Karyawan',
                'message' => 'Staf kasir Budi Kurniawan mengajukan izin sakit untuk shift siang hari ini. Butuh konfirmasi manager.',
                'category' => 'ATTENDANCE',
                'icon' => 'event_busy',
                'color' => 'blue',
                'link' => '/manager/absensi',
                'reference_id' => 'IZN-20261111-01',
                'is_read' => false,
                'created_at' => $now->copy()->subHours(1),
            ],
            [
                'role' => 'MANAGER',
                'title' => 'Laporan Variance Stock Opname Selesai',
                'message' => 'Sesi Stock Opname November 2026 telah ditutup. Laporan variance spreadsheet siap diunduh.',
                'category' => 'STOCK_OPNAME',
                'icon' => 'fact_check',
                'color' => 'emerald',
                'link' => '/manager/stock-opname',
                'reference_id' => 'SOP-20261105-H8PB',
                'is_read' => false,
                'created_at' => $now->copy()->subHours(3),
            ],
            [
                'role' => 'MANAGER',
                'title' => 'Pengajuan Kenaikan Plafon Kasbon',
                'message' => 'Karyawan Hendro Wibowo (NIK: 202409821008) mengajukan kenaikan plafon belanja Rp 5.000.000 ke HRD.',
                'category' => 'MEMBER',
                'icon' => 'payments',
                'color' => 'purple',
                'link' => '/manager/members',
                'reference_id' => 'PLAFON-REQ-08',
                'is_read' => true,
                'created_at' => $now->copy()->subHours(5),
            ],

            // ----------------------------------------------------
            // OWNER NOTIFICATIONS
            // ----------------------------------------------------
            [
                'role' => 'OWNER',
                'title' => 'Omset Penjualan Konsolidasi 3 Cabang',
                'message' => 'Total omset penjualan retail hari ini mencapai Rp 48.750.000 (Target harian 95% telah tercapai).',
                'category' => 'SALES',
                'icon' => 'trending_up',
                'color' => 'emerald',
                'link' => '/owner/reports',
                'reference_id' => 'SALES-SUMMARY-TODAY',
                'is_read' => false,
                'created_at' => $now->copy()->subMinutes(15),
            ],
            [
                'role' => 'OWNER',
                'title' => 'Audit Variance Opname Kritis: -Rp 9.000.000',
                'message' => 'Audit fisik stok persediaan menemukan deviasi bernilai tinggi pada lini produk non-sembako.',
                'category' => 'STOCK',
                'icon' => 'report_problem',
                'color' => 'rose',
                'link' => '/owner/products',
                'reference_id' => 'AUDIT-VAR-001',
                'is_read' => false,
                'created_at' => $now->copy()->subHours(1),
            ],
            [
                'role' => 'OWNER',
                'title' => 'Deviasi Kas Fisik Tutup Shift Kasir',
                'message' => 'Serah terima kasir POS-02 mencatat deviasi kas fisik -Rp 15.000 terhadap sistem penerimaan.',
                'category' => 'CASHIER',
                'icon' => 'price_change',
                'color' => 'amber',
                'link' => '/owner/audit-log',
                'reference_id' => 'SHIFT-DEV-02',
                'is_read' => false,
                'created_at' => $now->copy()->subHours(2),
            ],
            [
                'role' => 'OWNER',
                'title' => 'Akun Staf Kasir Baru Didaftarkan',
                'message' => 'Hak akses RBAC untuk staf kasir baru [Dedi Santoso] telah diaktifkan untuk operasional Cabang B.',
                'category' => 'SECURITY',
                'icon' => 'badge',
                'color' => 'blue',
                'link' => '/owner/kelola-akun',
                'reference_id' => 'USR-04',
                'is_read' => false,
                'created_at' => $now->copy()->subHours(3),
            ],
            [
                'role' => 'OWNER',
                'title' => 'Stok Konsinyasi UMKM Menipis: Keripik Bu Eni',
                'message' => 'Stok produk konsinyasi Keripik Singkong Balado Bu Eni tersisa 5 pcs. Notifikasi WhatsApp restock siap dikirim.',
                'category' => 'UMKM',
                'icon' => 'storefront',
                'color' => 'purple',
                'link' => '/owner/umkm-partners',
                'reference_id' => 'UMKM-001',
                'is_read' => true,
                'created_at' => $now->copy()->subHours(6),
            ],
        ];

        foreach ($notifications as $data) {
            SystemNotification::create($data);
        }
    }
}
