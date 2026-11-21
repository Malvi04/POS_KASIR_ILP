<?php

namespace App\Http\Controllers\Api\Manager;

use App\Http\Controllers\Controller;
use App\Models\DebtMutation;
use App\Models\Location;
use App\Models\Member;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class MemberManagementController extends Controller
{
    /**
     * List members with multi-cabang transparent access, search, filter & summary stats.
     */
    public function index(Request $request): JsonResponse
    {
        $search = trim($request->query('search', ''));
        $branch = trim($request->query('branch', ''));
        $department = trim($request->query('department', ''));
        $status = trim($request->query('status', ''));
        $perPage = max(5, min(200, (int) $request->query('per_page', 15)));
        $sortBy = in_array($request->query('sort_by'), ['id', 'member_code', 'name', 'nik', 'department', 'credit_limit', 'current_debt', 'created_at'])
            ? $request->query('sort_by')
            : 'id';
        $sortOrder = strtolower($request->query('sort_order', 'asc')) === 'desc' ? 'desc' : 'asc';

        // Base Query
        $query = Member::query();

        if ($search !== '') {
            $lowerSearch = strtolower($search);
            $query->where(function ($q) use ($search, $lowerSearch) {
                $q->where('nik', 'like', "%{$search}%")
                  ->orWhere('member_code', 'like', "%{$search}%")
                  ->orWhere('customer_id', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%")
                  ->orWhere('no_rekening', 'like', "%{$search}%")
                  ->orWhereRaw('LOWER(name) LIKE ?', ["%{$lowerSearch}%"])
                  ->orWhereRaw('LOWER(department) LIKE ?', ["%{$lowerSearch}%"])
                  ->orWhereRaw('LOWER(division) LIKE ?', ["%{$lowerSearch}%"]);
            });
        }

        if ($branch !== '' && $branch !== 'all' && $branch !== 'Semua') {
            $query->where('branch', $branch);
        }

        if ($department !== '' && $department !== 'all') {
            $query->where(function ($q) use ($department) {
                $q->where('department', $department)
                  ->orWhere('division', $department);
            });
        }

        if ($status !== '' && $status !== 'all') {
            $query->where('status', strtoupper($status));
        }

        // Cached Summary Aggregations & Metadata for fast pagination on 10,000+ rows
        $cachedMeta = Cache::remember('members_kpi_summary', 60, function () {
            $agg = Member::selectRaw(
                "COUNT(*) as total_members,"
                . " COALESCE(SUM(CASE WHEN status = 'AKTIF' THEN 1 ELSE 0 END), 0) as active_members,"
                . " COALESCE(SUM(CASE WHEN status = 'DIBLOKIR' THEN 1 ELSE 0 END), 0) as blocked_members,"
                . " COALESCE(SUM(credit_limit), 0) as total_credit_limit,"
                . " COALESCE(SUM(current_debt), 0) as total_current_debt"
            )->first();
            $totalLimit = (float) ($agg->total_credit_limit ?? 0);
            $totalDebt = (float) ($agg->total_current_debt ?? 0);

            $summary = [
                'total_members' => (int) ($agg->total_members ?? 0),
                'active_members' => (int) ($agg->active_members ?? 0),
                'blocked_members' => (int) ($agg->blocked_members ?? 0),
                'total_credit_limit' => $totalLimit,
                'total_current_debt' => $totalDebt,
                'total_remaining_credit' => (float) max(0, $totalLimit - $totalDebt),
                'branch_distribution' => Member::select('branch', DB::raw('count(*) as count'), DB::raw('sum(current_debt) as total_debt'))
                    ->groupBy('branch')
                    ->get(),
            ];

            $branches = Location::where('type', '!=', 'WAREHOUSE')
                ->orderBy('code')
                ->pluck('name')
                ->merge(Member::whereNotNull('branch')->where('branch', '!=', '')->distinct()->pluck('branch'))
                ->filter()
                ->unique()
                ->values();

            $departments = Member::whereNotNull('department')
                ->where('department', '!=', '')
                ->distinct()
                ->pluck('department')
                ->filter()
                ->values();

            return [
                'summary' => $summary,
                'branches' => $branches,
                'departments' => $departments,
            ];
        });

        // Paginated results
        $paginated = $query->orderBy($sortBy, $sortOrder)->paginate($perPage);

        return response()->json([
            'success' => true,
            'summary' => $cachedMeta['summary'],
            'branches' => $cachedMeta['branches'],
            'departments' => $cachedMeta['departments'],
            'data' => $paginated->items(),
            'meta' => [
                'current_page' => $paginated->currentPage(),
                'last_page' => $paginated->lastPage(),
                'per_page' => $paginated->perPage(),
                'total' => $paginated->total(),
            ],
        ]);
    }

    /**
     * Show single member detail with debt utilization and recent transactions.
     */
    public function show(string $idOrNik): JsonResponse
    {
        $member = Member::where('id', is_numeric($idOrNik) ? (int) $idOrNik : -1)
            ->orWhere('nik', $idOrNik)
            ->orWhere('member_code', $idOrNik)
            ->first();

        if (!$member) {
            return response()->json([
                'success' => false,
                'message' => "Anggota dengan identitas [{$idOrNik}] tidak ditemukan.",
            ], 404);
        }

        // Recent 10 mutations
        $mutations = $member->debtMutations()->limit(10)->get();

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $member->id,
                'customer_id' => $member->customer_id ?? (string) $member->id,
                'member_code' => $member->member_code,
                'kode_out' => $member->kode_out ?? '',
                'nik' => $member->nik,
                'name' => $member->name,
                'department' => $member->department,
                'division' => $member->division ?? $member->department,
                'address' => $member->address ?? '',
                'branch' => $member->branch ?? '-',
                'phone' => $member->phone ?? '-',
                'fax' => $member->fax ?? '',
                'contact' => $member->contact ?? '',
                'tempo_bayar' => $member->tempo_bayar ?? '',
                'idtk' => $member->idtk ?? '',
                'no_rekening' => $member->no_rekening ?? '',
                'join_date' => $member->join_date ? $member->join_date->format('d M Y') : '-',
                'credit_limit' => (float) $member->credit_limit,
                'current_debt' => (float) $member->current_debt,
                'remaining_credit' => (float) $member->remaining_credit,
                'debt_utilization_percent' => (float) $member->debt_utilization_percent,
                'status' => $member->status,
                'is_active' => $member->is_active,
                'notes' => $member->notes ?? '',
                'recent_mutations' => $mutations,
            ],
        ]);
    }

    /**
     * Store new member.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'customer_id' => 'nullable|string|max:60',
            'member_code' => 'nullable|string|max:60|unique:members,member_code',
            'kode_out' => 'nullable|string|max:60',
            'nik' => 'required|string|max:60|unique:members,nik',
            'name' => 'required|string|max:150',
            'department' => 'required|string|max:100',
            'division' => 'nullable|string|max:150',
            'address' => 'nullable|string',
            'branch' => 'nullable|string|max:100',
            'phone' => 'nullable|string|max:30',
            'fax' => 'nullable|string|max:100',
            'contact' => 'nullable|string|max:150',
            'tempo_bayar' => 'nullable|string|max:60',
            'idtk' => 'nullable|string|max:100',
            'no_rekening' => 'nullable|string|max:100',
            'join_date' => 'nullable|date',
            'credit_limit' => 'required|numeric|min:0',
            'status' => 'required|in:AKTIF,DIBLOKIR',
            'notes' => 'nullable|string',
        ]);

        return DB::transaction(function () use ($validated, $request) {
            $memberCode = !empty($validated['member_code'])
                ? trim($validated['member_code'])
                : ('MBR-' . Str::padLeft((string) (Member::max('id') + 1), 4, '0'));

            $member = Member::create([
                'customer_id' => $validated['customer_id'] ?? null,
                'member_code' => $memberCode,
                'kode_out' => $validated['kode_out'] ?? null,
                'nik' => $validated['nik'],
                'name' => $validated['name'],
                'department' => $validated['department'],
                'division' => $validated['division'] ?? $validated['department'],
                'address' => $validated['address'] ?? null,
                'branch' => $validated['branch'] ?? 'Cabang 1 - Mill / Pabrik',
                'phone' => $validated['phone'] ?? null,
                'fax' => $validated['fax'] ?? null,
                'contact' => $validated['contact'] ?? null,
                'tempo_bayar' => $validated['tempo_bayar'] ?? null,
                'idtk' => $validated['idtk'] ?? null,
                'no_rekening' => $validated['no_rekening'] ?? null,
                'join_date' => $validated['join_date'] ?? now()->toDateString(),
                'credit_limit' => (float) $validated['credit_limit'],
                'current_debt' => 0,
                'status' => $validated['status'],
                'notes' => $validated['notes'] ?? 'Pendaftaran anggota baru',
            ]);

            // Initial limit record
            $member->adjustCreditLimit(
                newLimit: (float) $validated['credit_limit'],
                processedBy: $request->user()?->name ?? 'Admin Koperasi',
                notes: 'Pemberian plafon kredit awal saat pendaftaran anggota baru.'
            );

            Cache::forget('members_kpi_summary');

            return response()->json([
                'success' => true,
                'message' => "Anggota baru [{$member->name}] berhasil didaftarkan.",
                'data' => $member,
            ], 201);
        });
    }

    /**
     * Update member profile.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $member = Member::find($id);
        if (!$member) {
            return response()->json([
                'success' => false,
                'message' => 'Anggota tidak ditemukan.',
            ], 404);
        }

        $validated = $request->validate([
            'customer_id' => 'nullable|string|max:60',
            'member_code' => 'nullable|string|max:60|unique:members,member_code,' . $member->id,
            'kode_out' => 'nullable|string|max:60',
            'nik' => 'nullable|string|max:60',
            'name' => 'required|string|max:150',
            'department' => 'required|string|max:100',
            'division' => 'nullable|string|max:150',
            'address' => 'nullable|string',
            'branch' => 'nullable|string|max:100',
            'phone' => 'nullable|string|max:30',
            'fax' => 'nullable|string|max:100',
            'contact' => 'nullable|string|max:150',
            'tempo_bayar' => 'nullable|string|max:60',
            'idtk' => 'nullable|string|max:100',
            'no_rekening' => 'nullable|string|max:100',
            'credit_limit' => 'nullable|numeric|min:0',
            'status' => 'required|in:AKTIF,DIBLOKIR',
            'notes' => 'nullable|string',
        ]);

        if (empty($validated['member_code'])) {
            unset($validated['member_code']);
        }
        if (empty($validated['nik'])) {
            unset($validated['nik']);
        }
        if (!isset($validated['credit_limit'])) {
            unset($validated['credit_limit']);
        }

        $member->update($validated);
        Cache::forget('members_kpi_summary');

        return response()->json([
            'success' => true,
            'message' => "Data anggota [{$member->name}] berhasil diperbarui.",
            'data' => $member,
        ]);
    }

    /**
     * Safe delete member (only if zero debt).
     */
    public function destroy(int $id): JsonResponse
    {
        $member = Member::find($id);
        if (!$member) {
            return response()->json([
                'success' => false,
                'message' => 'Anggota tidak ditemukan.',
            ], 404);
        }

        if ($member->current_debt > 0) {
            return response()->json([
                'success' => false,
                'message' => "Anggota [{$member->name}] tidak dapat dihapus karena masih memiliki saldo piutang kasbon sebesar Rp " . number_format($member->current_debt, 0, ',', '.') . ". Harap lunasi piutang terlebih dahulu.",
            ], 422);
        }

        $name = $member->name;
        $member->delete();
        Cache::forget('members_kpi_summary');

        return response()->json([
            'success' => true,
            'message' => "Anggota [{$name}] berhasil dihapus dari sistem.",
        ]);
    }

    /**
     * Update Plafon Kredit Kasbon Bulanan per Anggota with Audit Trail.
     */
    public function updateCreditLimit(Request $request, int $id): JsonResponse
    {
        $member = Member::find($id);
        if (!$member) {
            return response()->json([
                'success' => false,
                'message' => 'Anggota tidak ditemukan.',
            ], 404);
        }

        $validated = $request->validate([
            'credit_limit' => 'required|numeric|min:0',
            'notes' => 'nullable|string|max:255',
            'processed_by' => 'nullable|string|max:100',
        ]);

        $newLimit = (float) $validated['credit_limit'];
        $oldLimit = (float) $member->credit_limit;

        if ($newLimit < $member->current_debt) {
            return response()->json([
                'success' => false,
                'message' => "Plafon baru (Rp " . number_format($newLimit, 0, ',', '.') . ") tidak boleh lebih rendah dari saldo kasbon berjalan anggota saat ini (Rp " . number_format($member->current_debt, 0, ',', '.') . ").",
            ], 422);
        }

        $mutation = $member->adjustCreditLimit(
            newLimit: $newLimit,
            processedBy: $validated['processed_by'] ?? ($request->user()?->name ?? 'Manager Koperasi'),
            notes: $validated['notes'] ?? "Perubahan plafon kredit kasbon dari Rp " . number_format($oldLimit, 0, ',', '.') . " ke Rp " . number_format($newLimit, 0, ',', '.')
        );
        Cache::forget('members_kpi_summary');

        return response()->json([
            'success' => true,
            'message' => "Plafon kredit anggota [{$member->name}] berhasil diperbarui menjadi Rp " . number_format($newLimit, 0, ',', '.') . ".",
            'data' => [
                'member' => $member,
                'mutation' => $mutation,
            ],
        ]);
    }

    /**
     * Record Kasbon Settlement / Pelunasan / Potong Gaji.
     */
    public function recordSettlement(Request $request, int $id): JsonResponse
    {
        $member = Member::find($id);
        if (!$member) {
            return response()->json([
                'success' => false,
                'message' => 'Anggota tidak ditemukan.',
            ], 404);
        }

        $validated = $request->validate([
            'amount' => 'required|numeric|min:1',
            'type' => 'required|in:PAYMENT_SETTLEMENT,PAYROLL_DEDUCTION',
            'notes' => 'nullable|string|max:255',
            'processed_by' => 'nullable|string|max:100',
        ]);

        $amount = (float) $validated['amount'];
        if ($amount > $member->current_debt) {
            return response()->json([
                'success' => false,
                'message' => "Nominal pelunasan (Rp " . number_format($amount, 0, ',', '.') . ") melebihi total saldo piutang berjalan (Rp " . number_format($member->current_debt, 0, ',', '.') . ").",
            ], 422);
        }

        $typeLabel = $validated['type'] === 'PAYROLL_DEDUCTION' ? 'Potong Gaji Payroll' : 'Pelunasan Tunai/Transfer';
        $mutation = $member->recordSettlement(
            amount: $amount,
            processedBy: $validated['processed_by'] ?? ($request->user()?->name ?? 'Finance HRD PT ILP'),
            type: $validated['type'],
            notes: $validated['notes'] ?? "{$typeLabel} kasbon anggota."
        );
        Cache::forget('members_kpi_summary');

        return response()->json([
            'success' => true,
            'message' => "Berhasil memproses {$typeLabel} sebesar Rp " . number_format($amount, 0, ',', '.') . " untuk [{$member->name}]. Sisa piutang: Rp " . number_format($member->current_debt, 0, ',', '.') . ".",
            'data' => [
                'member' => $member,
                'mutation' => $mutation,
            ],
        ]);
    }

    /**
     * Get Member Debt History / Kasbon Mutations.
     */
    public function debtHistory(Request $request, int $id): JsonResponse
    {
        $member = Member::find($id);
        if (!$member) {
            return response()->json([
                'success' => false,
                'message' => 'Anggota tidak ditemukan.',
            ], 404);
        }

        $perPage = max(5, min(50, (int) $request->query('per_page', 15)));
        $mutations = $member->debtMutations()->paginate($perPage);

        return response()->json([
            'success' => true,
            'member' => [
                'id' => $member->id,
                'nik' => $member->nik,
                'name' => $member->name,
                'department' => $member->department,
                'branch' => $member->branch,
                'credit_limit' => (float) $member->credit_limit,
                'current_debt' => (float) $member->current_debt,
                'remaining_credit' => (float) $member->remaining_credit,
                'status' => $member->status,
            ],
            'data' => $mutations->items(),
            'meta' => [
                'current_page' => $mutations->currentPage(),
                'last_page' => $mutations->lastPage(),
                'total' => $mutations->total(),
            ],
        ]);
    }

    /**
     * Bulk update credit limits by department or branch.
     */
    public function bulkUpdateLimit(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'target_type' => 'required|in:ALL,BRANCH,DEPARTMENT',
            'target_value' => 'nullable|string',
            'credit_limit' => 'required|numeric|min:0',
            'notes' => 'nullable|string',
        ]);

        $query = Member::query();
        if ($validated['target_type'] === 'BRANCH' && !empty($validated['target_value'])) {
            $query->where('branch', $validated['target_value']);
        } elseif ($validated['target_type'] === 'DEPARTMENT' && !empty($validated['target_value'])) {
            $query->where('department', $validated['target_value']);
        }

        $members = $query->get();
        $updatedCount = 0;
        $newLimit = (float) $validated['credit_limit'];

        DB::transaction(function () use ($members, $newLimit, $validated, $request, &$updatedCount) {
            foreach ($members as $member) {
                // Only update if newLimit >= current_debt
                if ($newLimit >= $member->current_debt) {
                    $member->adjustCreditLimit(
                        newLimit: $newLimit,
                        processedBy: $request->user()?->name ?? 'Manager Koperasi',
                        notes: $validated['notes'] ?? "Mass update plafon kredit ({$validated['target_type']}: {$validated['target_value']})"
                    );
                    $updatedCount++;
                }
            }
        });
        Cache::forget('members_kpi_summary');

        return response()->json([
            'success' => true,
            'message' => "Berhasil memperbarui plafon kredit untuk {$updatedCount} anggota.",
            'updated_count' => $updatedCount,
            'total_targeted' => $members->count(),
        ]);
    }
}
