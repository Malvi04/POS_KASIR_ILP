<?php

namespace App\Http\Controllers\Api\Manager;

use App\Http\Controllers\Controller;
use App\Models\DebtMutation;
use App\Models\Member;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MemberDebtReportController extends Controller
{
    /**
     * Rekapitulasi Laporan Piutang & Kasbon Bulanan Anggota.
     */
    public function index(Request $request): JsonResponse
    {
        $month = $request->query('month', Carbon::now()->format('Y-m')); // e.g. '2026-10' or '2026-11'
        $branch = trim($request->query('branch', ''));
        $department = trim($request->query('department', ''));
        $status = trim($request->query('status', ''));
        $search = trim($request->query('search', ''));
        $perPage = max(10, min(100, (int) $request->query('per_page', 25)));

        // Date range for the requested month
        try {
            $startDate = Carbon::createFromFormat('Y-m', $month)->startOfMonth();
            $endDate = Carbon::createFromFormat('Y-m', $month)->endOfMonth();
        } catch (\Exception $e) {
            $startDate = Carbon::now()->startOfMonth();
            $endDate = Carbon::now()->endOfMonth();
            $month = Carbon::now()->format('Y-m');
        }

        // Subquery for monthly kasbon and settlements within the month
        $monthlyKasbonSubquery = DB::table('debt_mutations')
            ->select('member_id',
                DB::raw("SUM(CASE WHEN mutation_type = 'KASBON_POS' THEN amount ELSE 0 END) as monthly_kasbon"),
                DB::raw("SUM(CASE WHEN mutation_type IN ('SETTLEMENT', 'PAYROLL_DEDUCTION') THEN amount ELSE 0 END) as monthly_payments"),
                DB::raw("COUNT(CASE WHEN mutation_type = 'KASBON_POS' THEN 1 END) as kasbon_trx_count")
            )
            ->whereBetween('created_at', [$startDate, $endDate])
            ->groupBy('member_id');

        $query = Member::query()
            ->leftJoinSub($monthlyKasbonSubquery, 'm_stats', function ($join) {
                $join->on('members.id', '=', 'm_stats.member_id');
            })
            ->select(
                'members.*',
                DB::raw('COALESCE(m_stats.monthly_kasbon, 0) as monthly_kasbon_total'),
                DB::raw('COALESCE(m_stats.monthly_payments, 0) as monthly_payments_total'),
                DB::raw('COALESCE(m_stats.kasbon_trx_count, 0) as kasbon_trx_count')
            );

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('members.nik', 'like', "%{$search}%")
                  ->orWhere('members.member_code', 'like', "%{$search}%")
                  ->orWhereRaw('LOWER(members.name) LIKE ?', ['%' . strtolower($search) . '%'])
                  ->orWhereRaw('LOWER(members.department) LIKE ?', ['%' . strtolower($search) . '%']);
            });
        }

        if ($branch !== '' && $branch !== 'all' && $branch !== 'Semua') {
            $query->where('members.branch', $branch);
        }

        if ($department !== '' && $department !== 'all') {
            $query->where('members.department', $department);
        }

        if ($status !== '' && $status !== 'all') {
            $query->where('members.status', strtoupper($status));
        }

        // Overall summary KPI for the period
        $allMembers = (clone $query)->get();

        $summary = [
            'period_month' => $month,
            'period_label' => $startDate->translatedFormat('F Y'),
            'total_registered_members' => $allMembers->count(),
            'members_with_active_debt' => $allMembers->where('current_debt', '>', 0)->count(),
            'total_credit_limit' => (float) $allMembers->sum('credit_limit'),
            'total_active_debt' => (float) $allMembers->sum('current_debt'),
            'total_monthly_kasbon' => (float) $allMembers->sum('monthly_kasbon_total'),
            'total_monthly_settlements' => (float) $allMembers->sum('monthly_payments_total'),
            'total_remaining_credit' => (float) max(0, $allMembers->sum('credit_limit') - $allMembers->sum('current_debt')),
            'overall_utilization_percent' => $allMembers->sum('credit_limit') > 0
                ? round(($allMembers->sum('current_debt') / $allMembers->sum('credit_limit')) * 100, 2)
                : 0,
        ];

        // Paginated rows
        $paginated = $query->orderBy('members.current_debt', 'desc')
            ->orderBy('members.name', 'asc')
            ->paginate($perPage);

        $items = collect($paginated->items())->map(function ($m) {
            $remaining = max(0, (float) $m->credit_limit - (float) $m->current_debt);
            $utilization = $m->credit_limit > 0 ? round(($m->current_debt / $m->credit_limit) * 100, 1) : 0;

            return [
                'id' => $m->id,
                'member_code' => $m->member_code,
                'nik' => $m->nik,
                'name' => $m->name,
                'department' => $m->department,
                'branch' => $m->branch,
                'credit_limit' => (float) $m->credit_limit,
                'monthly_kasbon' => (float) $m->monthly_kasbon_total,
                'monthly_payments' => (float) $m->monthly_payments_total,
                'current_debt' => (float) $m->current_debt, // Total potong gaji
                'remaining_credit' => $remaining,
                'utilization_percent' => $utilization,
                'trx_count' => (int) $m->kasbon_trx_count,
                'status' => $m->status,
            ];
        });

        return response()->json([
            'success' => true,
            'summary' => $summary,
            'data' => $items,
            'meta' => [
                'current_page' => $paginated->currentPage(),
                'last_page' => $paginated->lastPage(),
                'per_page' => $paginated->perPage(),
                'total' => $paginated->total(),
            ],
        ]);
    }

    /**
     * Export Rekapitulasi Piutang Bulanan to CSV / Excel Ready format for Tim Finance/HRD PT Indo Lampung Perkasa.
     */
    public function exportCsv(Request $request): StreamedResponse
    {
        $month = $request->query('month', Carbon::now()->format('Y-m'));
        $branch = trim($request->query('branch', ''));
        $department = trim($request->query('department', ''));
        $status = trim($request->query('status', ''));
        $search = trim($request->query('search', ''));

        try {
            $startDate = Carbon::createFromFormat('Y-m', $month)->startOfMonth();
            $endDate = Carbon::createFromFormat('Y-m', $month)->endOfMonth();
        } catch (\Exception $e) {
            $startDate = Carbon::now()->startOfMonth();
            $endDate = Carbon::now()->endOfMonth();
            $month = Carbon::now()->format('Y-m');
        }

        $query = Member::query();

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('nik', 'like', "%{$search}%")
                  ->orWhere('member_code', 'like', "%{$search}%")
                  ->orWhereRaw('LOWER(name) LIKE ?', ['%' . strtolower($search) . '%']);
            });
        }

        if ($branch !== '' && $branch !== 'all' && $branch !== 'Semua') {
            $query->where('branch', $branch);
        }

        if ($department !== '' && $department !== 'all') {
            $query->where('department', $department);
        }

        if ($status !== '' && $status !== 'all') {
            $query->where('status', strtoupper($status));
        }

        $filename = 'Rekapitulasi_Piutang_Kasbon_PT_ILP_' . $month . ($branch ? '_' . Str::slug($branch) : '') . '.csv';

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            'Pragma' => 'no-cache',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Expires' => '0',
        ];

        return new StreamedResponse(function () use ($query, $month, $startDate, $branch, $department) {
            $output = fopen('php://output', 'w');

            // UTF-8 BOM for Microsoft Excel compatibility
            fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

            // PT Indo Lampung Perkasa Official Header
            fputcsv($output, ['PT INDO LAMPUNG PERKASA']);
            fputcsv($output, ['KOPERASI KARYAWAN INDO LAMPUNG PERKASA (KOPKAR ILP)']);
            fputcsv($output, ['LAPORAN REKAPITULASI PIUTANG KASBON BULANAN KARYAWAN']);
            fputcsv($output, ['UNTUK PEMOTONGAN GAJI (PAYROLL DEBT DEDUCTION)']);
            fputcsv($output, []);
            fputcsv($output, ['Periode Pemotongan Gaji', $startDate->translatedFormat('F Y') . ' (' . $month . ')']);
            fputcsv($output, ['Filter Cabang', $branch !== '' && $branch !== 'all' ? $branch : 'Semua 3 Cabang (Pusat & Kebun)']);
            fputcsv($output, ['Filter Departemen', $department !== '' && $department !== 'all' ? $department : 'Semua Departemen']);
            fputcsv($output, ['Tanggal Generate File', Carbon::now()->format('d/m/Y H:i:s') . ' WIB']);
            fputcsv($output, []);

            // Table Columns
            fputcsv($output, [
                'No',
                'Kode Anggota',
                'NIK Karyawan',
                'Nama Lengkap Karyawan',
                'Departemen / Divisi',
                'Cabang Penugasan',
                'Plafon Kredit Bulanan (Rp)',
                'Total Kasbon Berjalan / Potong Gaji (Rp)',
                'Sisa Plafon Kredit (Rp)',
                'Tingkat Utilisasi (%)',
                'Status Anggota',
                'Keterangan Rekomendasi HRD',
            ]);

            $no = 1;
            $grandTotalLimit = 0;
            $grandTotalDebt = 0;
            $grandTotalRemaining = 0;

            // Stream chunks for memory optimization (prevent memory leak / bottleneck)
            $query->orderBy('current_debt', 'desc')->orderBy('name', 'asc')->chunk(200, function ($members) use ($output, &$no, &$grandTotalLimit, &$grandTotalDebt, &$grandTotalRemaining) {
                foreach ($members as $m) {
                    $remaining = max(0, $m->credit_limit - $m->current_debt);
                    $utilization = $m->credit_limit > 0 ? round(($m->current_debt / $m->credit_limit) * 100, 1) : 0;
                    
                    $rekomendasi = 'Normal';
                    if ($m->status !== 'AKTIF') {
                        $rekomendasi = 'Akun Dibekukan - Konfirmasi HRD';
                    } elseif ($m->current_debt >= $m->credit_limit) {
                        $rekomendasi = 'Plafon 100% Penuh - Potong Gaji Penuh';
                    } elseif ($utilization >= 75) {
                        $rekomendasi = 'Perhatian: Di atas 75% plafon';
                    }

                    fputcsv($output, [
                        $no++,
                        $m->member_code,
                        "'" . $m->nik, // apostrophe prefix prevents scientific notation in Excel
                        $m->name,
                        $m->department,
                        $m->branch ?? 'Cabang 1 - Mill / Pabrik',
                        number_format($m->credit_limit, 2, '.', ''),
                        number_format($m->current_debt, 2, '.', ''),
                        number_format($remaining, 2, '.', ''),
                        $utilization . '%',
                        $m->status,
                        $rekomendasi,
                    ]);

                    $grandTotalLimit += $m->credit_limit;
                    $grandTotalDebt += $m->current_debt;
                    $grandTotalRemaining += $remaining;
                }
            });

            // Summary Totals Row
            fputcsv($output, []);
            fputcsv($output, [
                'TOTAL',
                '',
                '',
                '',
                '',
                '',
                number_format($grandTotalLimit, 2, '.', ''),
                number_format($grandTotalDebt, 2, '.', ''),
                number_format($grandTotalRemaining, 2, '.', ''),
                $grandTotalLimit > 0 ? round(($grandTotalDebt / $grandTotalLimit) * 100, 1) . '%' : '0%',
                '',
                'Total Kasbon yang Wajib Dipotong Gaji',
            ]);

            fputcsv($output, []);
            fputcsv($output, []);

            // Signoff Section for Finance & HRD PT Indo Lampung Perkasa
            fputcsv($output, ['', 'Disiapkan Oleh,', '', 'Diverifikasi Oleh,', '', 'Disetujui Oleh (Payroll),']);
            fputcsv($output, ['', 'Pengurus Koperasi Karyawan', '', 'Bagian Keuangan Koperasi', '', 'HRD / Finance PT Indo Lampung Perkasa']);
            fputcsv($output, []);
            fputcsv($output, []);
            fputcsv($output, []);
            fputcsv($output, ['', '( ................................... )', '', '( ................................... )', '', '( ................................... )']);
            fputcsv($output, ['', 'Tanggal: ............................', '', 'Tanggal: ............................', '', 'Tanggal: ............................']);

            fclose($output);
        }, 200, $headers);
    }

    /**
     * Process payroll cutoff: automatically settles debt that was deducted via payroll.
     */
    public function processPayrollCutoff(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'month' => 'required|string|regex:/^\d{4}-\d{2}$/',
            'branch' => 'nullable|string',
            'department' => 'nullable|string',
            'notes' => 'nullable|string',
            'processed_by' => 'nullable|string',
        ]);

        $query = Member::where('current_debt', '>', 0);

        if (!empty($validated['branch']) && $validated['branch'] !== 'all') {
            $query->where('branch', $validated['branch']);
        }

        if (!empty($validated['department']) && $validated['department'] !== 'all') {
            $query->where('department', $validated['department']);
        }

        $members = $query->get();
        $totalDeducted = 0;
        $processedCount = 0;
        $processedBy = $validated['processed_by'] ?? ($request->user()?->name ?? 'Tim HRD & Finance PT ILP');

        DB::transaction(function () use ($members, $validated, $processedBy, &$totalDeducted, &$processedCount) {
            foreach ($members as $member) {
                $debtToDeduct = (float) $member->current_debt;
                if ($debtToDeduct > 0) {
                    $member->recordSettlement(
                        amount: $debtToDeduct,
                        processedBy: $processedBy,
                        type: 'PAYROLL_DEDUCTION',
                        notes: "Pemotongan Gaji Rutin Payroll Periode {$validated['month']} (PT Indo Lampung Perkasa). {$validated['notes']}"
                    );
                    $totalDeducted += $debtToDeduct;
                    $processedCount++;
                }
            }
        });

        return response()->json([
            'success' => true,
            'message' => "Pemotongan gaji payroll periode {$validated['month']} berhasil diproses untuk {$processedCount} karyawan senilai Rp " . number_format($totalDeducted, 0, ',', '.') . ".",
            'processed_count' => $processedCount,
            'total_deducted' => $totalDeducted,
        ]);
    }
}
