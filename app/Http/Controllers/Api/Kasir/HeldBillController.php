<?php

namespace App\Http\Controllers\Api\Kasir;

use App\Http\Controllers\Controller;
use App\Models\CashierShift;
use App\Models\HeldBill;
use App\Models\Member;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class HeldBillController extends Controller
{
    /**
     * List all currently held bills for the active shift/terminal.
     */
    public function index(Request $request): JsonResponse
    {
        $shiftId = $request->query('shift_id');
        $terminal = $request->query('terminal_code');

        $query = HeldBill::where('status', 'HELD');

        if ($shiftId) {
            $query->where('shift_id', $shiftId);
        } elseif ($terminal) {
            $query->where('terminal_code', $terminal);
        }

        $heldBills = $query->latest('held_at')->get();

        return response()->json([
            'success' => true,
            'count' => $heldBills->count(),
            'data' => $heldBills->map(fn($b) => [
                'id' => $b->id,
                'hold_code' => $b->hold_code,
                'terminal_code' => $b->terminal_code,
                'customer_name' => $b->customer_name,
                'member_id' => $b->member_id,
                'member_nik' => $b->member_nik,
                'total_items' => (int) $b->total_items,
                'subtotal' => (float) $b->subtotal,
                'tax_total' => (float) $b->tax_total,
                'grand_total' => (float) $b->grand_total,
                'note' => $b->note,
                'held_at' => $b->held_at->toIso8601String(),
                'hold_time_label' => $b->held_at->format('H:i') . ' WIB',
                'items' => $b->items_payload,
            ]),
        ]);
    }

    /**
     * Store active cart as a Held Bill.
     */
    public function hold(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'shift_id' => 'nullable|integer|exists:cashier_shifts,id',
            'terminal_code' => 'required|string|max:20',
            'customer_name' => 'nullable|string|max:100',
            'member_nik' => 'nullable|string|max:50',
            'items' => 'required|array|min:1',
            'items.*.name' => 'required|string',
            'items.*.price' => 'required|numeric',
            'items.*.qty' => 'required|integer|min:1',
            'note' => 'nullable|string|max:255',
        ]);

        // Find active shift if not provided
        $shiftId = $validated['shift_id'] ?? null;
        if (!$shiftId) {
            $activeShift = CashierShift::where('terminal_code', $validated['terminal_code'])
                ->where('status', 'OPEN')
                ->latest('id')
                ->first();
            $shiftId = $activeShift?->id;
        }

        if (!$shiftId) {
            return response()->json([
                'success' => false,
                'message' => 'Tidak ada sesi shift aktif untuk terminal ini. Buka shift terlebih dahulu.',
            ], 422);
        }

        $member = null;
        if (!empty($validated['member_nik'])) {
            $member = Member::where('nik', $validated['member_nik'])->first();
        }

        $custName = $validated['customer_name'] ?? ($member ? "{$member->name} (Anggota)" : 'Pelanggan Walk-in');

        // Calculate totals
        $subtotal = 0;
        $totalItems = 0;
        foreach ($validated['items'] as $it) {
            $qty = (int) $it['qty'];
            $price = (float) ($it['price'] ?? 0);
            $subtotal += ($price * $qty);
            $totalItems += $qty;
        }
        $tax = round($subtotal * 0.11, 2);
        $grandTotal = $subtotal + $tax;

        $holdCode = 'HOLD-' . date('Ymd') . '-' . Str::upper(Str::random(5));

        $heldBill = HeldBill::create([
            'hold_code' => $holdCode,
            'shift_id' => $shiftId,
            'terminal_code' => $validated['terminal_code'],
            'customer_name' => $custName,
            'member_id' => $member?->id,
            'member_nik' => $member?->nik,
            'items_payload' => $validated['items'],
            'subtotal' => $subtotal,
            'tax_total' => $tax,
            'grand_total' => $grandTotal,
            'total_items' => $totalItems,
            'note' => $validated['note'] ?? null,
            'status' => 'HELD',
            'held_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => "Transaksi keranjang {$holdCode} berhasil ditahan.",
            'data' => [
                'id' => $heldBill->id,
                'hold_code' => $heldBill->hold_code,
                'customer_name' => $heldBill->customer_name,
                'total_items' => $heldBill->total_items,
                'grand_total' => (float) $heldBill->grand_total,
                'held_at' => $heldBill->held_at->toIso8601String(),
                'items' => $heldBill->items_payload,
            ],
        ], 201);
    }

    /**
     * Recall a held bill back to the active cashier cart.
     */
    public function recall(int $id): JsonResponse
    {
        $heldBill = HeldBill::findOrFail($id);

        if ($heldBill->status !== 'HELD') {
            return response()->json([
                'success' => false,
                'message' => "Transaksi [{$heldBill->hold_code}] sudah pernah dipanggil sebelumnya atau dibatalkan.",
            ], 422);
        }

        $heldBill->update([
            'status' => 'RECALLED',
            'recalled_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => "Transaksi {$heldBill->hold_code} berhasil dipanggil kembali ke keranjang kasir.",
            'data' => [
                'id' => $heldBill->id,
                'hold_code' => $heldBill->hold_code,
                'customer_name' => $heldBill->customer_name,
                'member_id' => $heldBill->member_id,
                'member_nik' => $heldBill->member_nik,
                'items' => $heldBill->items_payload,
                'grand_total' => (float) $heldBill->grand_total,
            ],
        ]);
    }

    /**
     * Cancel a held bill.
     */
    public function cancel(int $id): JsonResponse
    {
        $heldBill = HeldBill::findOrFail($id);
        $heldBill->update([
            'status' => 'CANCELLED',
        ]);

        return response()->json([
            'success' => true,
            'message' => "Antrean hold {$heldBill->hold_code} berhasil dibatalkan.",
        ]);
    }
}
