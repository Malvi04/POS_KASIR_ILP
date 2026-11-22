<?php

namespace App\Http\Controllers\Api\Manager;

use App\Http\Controllers\Controller;
use App\Models\DebtMutation;
use App\Models\Location;
use App\Models\Member;
use App\Models\Transaction;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MemberDebtManagementController extends Controller
{
    /**
     * Display real-time list of member debts, limit status, and global KPI statistics.
     */
    public function index(Request $request): JsonResponse
    {
        $search = trim($request->query('search', ''));
        $filterStatus = trim($request->query('filter_status', 'all')); // 'all' | 'over_limit' | 'within_limit' | 'zero_debt'
        $branch = trim($request->query('branch', ''));
        $department = trim($request->query('department', ''));
        $perPage = max(5, min(100, (int) $request->query('per_page', 15)));
        $sortBy = in_array($request->query('sort_by'), ['current_debt', 'credit_limit', 'name', 'nik', 'id', 'updated_at'])
            ? $request->query('sort_by')
            : 'current_debt';
        $sortOrder = strtolower($request->query('sort_order', 'desc')) === 'asc' ? 'asc' : 'desc';

        // Base Query
        $query = Member::query();

        if ($search !== '') {
            $lower = strtolower($search);
            $query->where(function ($q) use ($search, $lower) {
                $q->where('nik', 'like', "%{$search}%")
                  ->orWhere('member_code', 'like', "%{$search}%")
                  ->orWhere('customer_id', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%")
                  ->orWhereRaw('LOWER(name) LIKE ?', ["%{$lower}%"])
                  ->orWhereRaw('LOWER(department) LIKE ?', ["%{$lower}%"]);
            });
        }

        if ($branch !== '' && $branch !== 'all' && $branch !== 'Semua') {
            $query->where('branch', $branch);
        }

        if ($department !== '' && $department !== 'all') {
            $query->where('department', $department);
        }

        // Filter based on debt limit status
        if ($filterStatus === 'over_limit') {
            $query->whereRaw('current_debt > credit_limit');
        } elseif ($filterStatus === 'within_limit') {
            $query->where('current_debt', '>', 0)->whereRaw('current_debt <= credit_limit');
        } elseif ($filterStatus === 'zero_debt') {
            $query->where('current_debt', '<=', 0);
        }

        // Global KPI summary metrics calculated in real-time across entire members database
        $kpiAgg = Member::selectRaw("
            COUNT(*) as total_members,
            COALESCE(SUM(credit_limit), 0) as total_credit_limit,
            COALESCE(SUM(current_debt), 0) as total_active_debt,
            COALESCE(SUM(CASE WHEN current_debt > 0 THEN 1 ELSE 0 END), 0) as members_with_debt,
            COALESCE(SUM(CASE WHEN current_debt > credit_limit THEN 1 ELSE 0 END), 0) as members_over_limit,
            COALESCE(SUM(CASE WHEN current_debt > 0 AND current_debt <= credit_limit THEN 1 ELSE 0 END), 0) as members_within_limit
        ")->first();

        $totalPiutangTrx = Transaction::where('payment_method', 'KREDIT')->count();
        $totalOverLimitTrx = Transaction::where('payment_method', 'KREDIT')->where('is_over_limit', true)->count();

        $summary = [
            'total_registered_members' => (int) ($kpiAgg->total_members ?? 0),
            'total_credit_limit' => (float) ($kpiAgg->total_credit_limit ?? 0),
            'total_active_debt' => (float) ($kpiAgg->total_active_debt ?? 0),
            'total_remaining_credit' => (float) max(0, ($kpiAgg->total_credit_limit ?? 0) - ($kpiAgg->total_active_debt ?? 0)),
            'members_with_debt_count' => (int) ($kpiAgg->members_with_debt ?? 0),
            'members_over_limit_count' => (int) ($kpiAgg->members_over_limit ?? 0),
            'members_within_limit_count' => (int) ($kpiAgg->members_within_limit ?? 0),
            'total_piutang_transactions_count' => $totalPiutangTrx,
            'total_over_limit_transactions_count' => $totalOverLimitTrx,
        ];

        // Apply sorting and pagination
        $paginated = $query->orderBy($sortBy, $sortOrder)->paginate($perPage);

        // Pre-fetch count of piutang transactions per member in this page
        $memberIds = collect($paginated->items())->pluck('id')->filter()->all();
        $trxStats = [];
        if (!empty($memberIds)) {
            $trxStats = Transaction::whereIn('member_id', $memberIds)
                ->where('payment_method', 'KREDIT')
                ->select(
                    'member_id',
                    DB::raw('COUNT(*) as piutang_count'),
                    DB::raw('MAX(created_at) as last_trx_at'),
                    DB::raw('MAX(invoice_number) as last_invoice')
                )
                ->groupBy('member_id')
                ->get()
                ->keyBy('member_id');
        }

        $items = collect($paginated->items())->map(function ($m) use ($trxStats) {
            $stat = $trxStats[$m->id] ?? null;
            $currentDebt = (float) $m->current_debt;
            $limit = (float) $m->credit_limit;
            $isOverLimit = $currentDebt > $limit;
            $excessAmount = $isOverLimit ? ($currentDebt - $limit) : 0;
            $remaining = max(0, $limit - $currentDebt);
            $utilization = $limit > 0 ? round(($currentDebt / $limit) * 100, 1) : ($currentDebt > 0 ? 100 : 0);

            $debtStatus = 'LUNAS';
            if ($currentDebt > 0) {
                $debtStatus = $isOverLimit ? 'MELEBIHI_LIMIT' : 'DALAM_LIMIT';
            }

            return [
                'id' => $m->id,
                'member_code' => $m->member_code,
                'nik' => $m->nik,
                'name' => $m->name,
                'department' => $m->department ?? '-',
                'branch' => $m->branch ?? 'Cabang Pusat',
                'phone' => $m->phone ?? '-',
                'credit_limit' => $limit,
                'current_debt' => $currentDebt,
                'remaining_credit' => $remaining,
                'is_over_limit' => $isOverLimit,
                'excess_debt_amount' => $excessAmount,
                'utilization_percent' => $utilization,
                'debt_status' => $debtStatus,
                'status' => $m->status,
                'is_active' => $m->is_active,
                'piutang_trx_count' => (int) ($stat->piutang_count ?? 0),
                'last_piutang_at' => $stat && $stat->last_trx_at ? Carbon::parse($stat->last_trx_at)->translatedFormat('d M Y, H:i') : null,
                'last_invoice' => $stat->last_invoice ?? null,
            ];
        });

        // Branches & Departments for filtering
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
            ->orderBy('department')
            ->pluck('department')
            ->filter()
            ->values();

        return response()->json([
            'success' => true,
            'summary' => $summary,
            'data' => $items,
            'branches' => $branches,
            'departments' => $departments,
            'meta' => [
                'current_page' => $paginated->currentPage(),
                'last_page' => $paginated->lastPage(),
                'per_page' => $paginated->perPage(),
                'total' => $paginated->total(),
            ],
        ]);
    }

    /**
     * Get all transactions made by a specific member using payment method KREDIT (Piutang).
     * Includes purchased items, invoice number, status limit, and timestamps.
     */
    public function memberTransactions(Request $request, int $memberId): JsonResponse
    {
        $member = Member::find($memberId);
        if (!$member) {
            return response()->json([
                'success' => false,
                'message' => 'Anggota tidak ditemukan.',
            ], 404);
        }

        $transactions = Transaction::with(['items', 'shift'])
            ->where('member_id', $member->id)
            ->where('payment_method', 'KREDIT')
            ->orderBy('id', 'desc')
            ->get();

        $transformed = $transactions->map(function ($trx) {
            return [
                'id' => $trx->id,
                'invoice_number' => $trx->invoice_number,
                'created_at' => $trx->created_at->toIso8601String(),
                'formatted_date' => $trx->created_at->translatedFormat('d M Y, H:i'),
                'cashier_name' => $trx->cashier_name ?? 'Kasir Toko',
                'terminal_code' => $trx->terminal_code ?? '-',
                'branch' => $trx->branch ?? 'Cabang Pusat',
                'payment_method' => $trx->payment_method,
                'subtotal' => (float) $trx->subtotal,
                'discount_total' => (float) $trx->discount_total + (float) ($trx->promo_discount_total ?? 0),
                'tax_total' => (float) $trx->tax_total,
                'grand_total' => (float) $trx->grand_total,
                'is_over_limit' => (bool) $trx->is_over_limit,
                'debt_status' => $trx->debt_status ?? ($trx->is_over_limit ? 'MELEBIHI_LIMIT' : 'DALAM_LIMIT'),
                'status' => $trx->status,
                'items_count' => $trx->items->count(),
                'items' => $trx->items->map(function ($it) {
                    return [
                        'id' => $it->id,
                        'product_id' => $it->product_id,
                        'name' => $it->product_name,
                        'sku' => $it->sku,
                        'barcode' => $it->barcode ?? '-',
                        'qty' => (int) $it->qty,
                        'unit' => $it->unit ?? 'Pcs',
                        'unit_price' => (float) $it->unit_price,
                        'total_price' => (float) $it->total_price,
                        'promo_name' => $it->promo_name,
                        'promo_discount' => (float) ($it->promo_discount ?? 0),
                    ];
                }),
            ];
        });

        return response()->json([
            'success' => true,
            'member' => [
                'id' => $member->id,
                'name' => $member->name,
                'nik' => $member->nik,
                'member_code' => $member->member_code,
                'department' => $member->department ?? '-',
                'branch' => $member->branch ?? 'Cabang Pusat',
                'credit_limit' => (float) $member->credit_limit,
                'current_debt' => (float) $member->current_debt,
                'remaining_credit' => (float) $member->remaining_credit,
                'is_over_limit' => (bool) $member->is_over_limit,
                'excess_debt_amount' => (float) $member->excess_debt_amount,
                'utilization_percent' => (float) $member->debt_utilization_percent,
                'debt_status' => $member->debt_status,
                'status' => $member->status,
            ],
            'total_piutang_transactions' => $transactions->count(),
            'total_piutang_amount' => (float) $transactions->sum('grand_total'),
            'data' => $transformed,
        ]);
    }

    /**
     * Get thermal receipt payload for any transaction to view/print receipt in modal.
     */
    public function receipt(Request $request, string $idOrInvoice): JsonResponse
    {
        $transaction = Transaction::with(['items', 'shift', 'member'])
            ->where('invoice_number', $idOrInvoice)
            ->orWhere('id', is_numeric($idOrInvoice) ? (int) $idOrInvoice : 0)
            ->first();

        if (!$transaction) {
            return response()->json([
                'success' => false,
                'message' => 'Faktur transaksi piutang tidak ditemukan.',
            ], 404);
        }

        $dt = $transaction->created_at ?? now();

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $transaction->invoice_number,
                'rawId' => $transaction->id,
                'createdAt' => $transaction->created_at->toIso8601String(),
                'time' => $dt->format('H:i:s'),
                'date' => $dt->format('d-m-Y'),
                'terminal' => $transaction->terminal_code ?? '-',
                'cashier' => $transaction->cashier_name ?? '-',
                'branch' => $transaction->branch ?? 'Cabang Pusat',
                'method' => $transaction->payment_method,
                'methodLabel' => 'Kredit Anggota (Piutang)',
                'paymentReference' => $transaction->payment_reference ?? null,
                'is_over_limit' => (bool) $transaction->is_over_limit,
                'debt_status' => $transaction->debt_status ?? ($transaction->is_over_limit ? 'MELEBIHI_LIMIT' : 'DALAM_LIMIT'),
                'member' => $transaction->member ? [
                    'id' => $transaction->member->id,
                    'nik' => $transaction->member->nik,
                    'name' => $transaction->member->name,
                    'department' => $transaction->member->department,
                    'credit_limit' => (float) $transaction->member->credit_limit,
                    'current_debt' => (float) $transaction->member->current_debt,
                    'remaining_credit' => (float) $transaction->member->remaining_credit,
                ] : [
                    'nik' => $transaction->member_nik,
                    'name' => $transaction->member_name,
                ],
                'items' => $transaction->items->map(fn($it) => [
                    'name' => $it->product_name,
                    'qty' => (int) $it->qty,
                    'unit' => $it->unit ?? 'Pcs',
                    'price' => (float) $it->unit_price,
                    'total' => (float) $it->total_price,
                    'promo_name' => $it->promo_name,
                    'promo_discount' => (float) ($it->promo_discount ?? 0),
                ]),
                'subtotal' => (float) $transaction->subtotal,
                'discount' => (float) $transaction->discount_total + (float) ($transaction->promo_discount_total ?? 0),
                'tax' => (float) $transaction->tax_total,
                'total' => (float) $transaction->grand_total,
                'cashGiven' => 0,
                'change' => 0,
            ],
        ]);
    }
}
