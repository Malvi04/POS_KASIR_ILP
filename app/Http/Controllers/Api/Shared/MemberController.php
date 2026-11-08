<?php

namespace App\Http\Controllers\Api\Shared;

use App\Http\Controllers\Controller;
use App\Models\Member;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MemberController extends Controller
{
    /**
     * Search members by NIK, Member Code, or Name.
     */
    public function search(Request $request): JsonResponse
    {
        $q = trim($request->query('q', ''));
        $query = Member::query();

        if ($q !== '') {
            $query->where('nik', 'like', "%{$q}%")
                ->orWhere('member_code', 'like', "%{$q}%")
                ->orWhereRaw('LOWER(name) LIKE ?', ['%' . strtolower($q) . '%']);
        }

        $members = $query->orderBy('name', 'asc')->limit(20)->get();

        return response()->json([
            'success' => true,
            'count' => $members->count(),
            'data' => $members->map(fn($m) => [
                'id' => $m->id,
                'member_code' => $m->member_code,
                'nik' => $m->nik,
                'name' => $m->name,
                'department' => $m->department,
                'branch' => $m->branch ?? 'Cabang 1 - Mill / Pabrik',
                'credit_limit' => (float) $m->credit_limit,
                'current_debt' => (float) $m->current_debt,
                'remaining_credit' => (float) $m->remaining_credit,
                'status' => $m->status,
                'is_active' => $m->is_active,
            ]),
        ]);
    }

    /**
     * Instant lookup and credit validation by NIK.
     * Rejects or allows according to credit ceiling and status.
     */
    public function validateNik(Request $request, string $nik): JsonResponse
    {
        $nik = trim($nik);
        $amount = (float) $request->query('amount', 0);

        $member = Member::where('nik', $nik)->first();

        if (!$member) {
            return response()->json([
                'success' => false,
                'is_found' => false,
                'message' => "Anggota dengan NIK [{$nik}] tidak terdaftar di database koperasi.",
                'data' => null,
            ], 404);
        }

        $eligibility = $member->checkCreditEligibility($amount);

        return response()->json([
            'success' => true,
            'is_found' => true,
            'is_eligible' => $eligibility['eligible'],
            'message' => $eligibility['reason'],
            'data' => [
                'id' => $member->id,
                'member_code' => $member->member_code,
                'nik' => $member->nik,
                'name' => $member->name,
                'department' => $member->department,
                'branch' => $member->branch ?? 'Cabang 1 - Mill / Pabrik',
                'credit_limit' => (float) $member->credit_limit,
                'current_debt' => (float) $member->current_debt,
                'remaining_credit' => (float) $member->remaining_credit,
                'status' => $member->status,
                'is_active' => $member->is_active,
                'projected_remaining' => $eligibility['projected_remaining'] ?? ($member->remaining_credit - $amount),
                'shortage' => $eligibility['shortage'] ?? 0,
            ],
        ]);
    }
}
