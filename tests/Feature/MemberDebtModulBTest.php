<?php

namespace Tests\Feature;

use App\Models\CashierShift;
use App\Models\DebtMutation;
use App\Models\Member;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MemberDebtModulBTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Seed database if empty
        if (Member::count() === 0) {
            $this->seed();
        }
    }

    /**
     * Test Database Anggota Multi-Cabang listing & transparent access across 3 branches.
     */
    public function test_can_list_members_transparently_across_three_branches(): void
    {
        $response = $this->getJson('/api/members');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'summary' => [
                    'total_members',
                    'active_members',
                    'blocked_members',
                    'total_credit_limit',
                    'total_current_debt',
                    'total_remaining_credit',
                    'branch_distribution',
                ],
                'branches',
                'departments',
                'data',
                'meta',
            ]);

        $branches = $response->json('branches');
        $this->assertCount(3, $branches);

        // Filter by Cabang 2
        $responseCabang2 = $this->getJson('/api/members?branch=' . urlencode('Cabang 2 - Kebun Barat'));
        $responseCabang2->assertStatus(200);
        foreach ($responseCabang2->json('data') as $item) {
            $this->assertEquals('Cabang 2 - Kebun Barat', $item['branch']);
        }
    }

    /**
     * Test fast search members by NIK and name.
     */
    public function test_can_search_members_fast(): void
    {
        $response = $this->getJson('/api/members?search=Santoso');
        $response->assertStatus(200);
        $this->assertGreaterThanOrEqual(1, count($response->json('data')));
        $this->assertEquals('Santoso', $response->json('data.0.name'));
    }

    /**
     * Test store new member with branch assignment.
     */
    public function test_can_create_new_member_with_branch(): void
    {
        $uniqueNik = 'NIK-' . time() . rand(100, 999);
        $payload = [
            'nik' => $uniqueNik,
            'name' => 'Karyawan Baru PT ILP',
            'department' => 'Agronomi & Riset',
            'branch' => 'Cabang 3 - Kebun Timur',
            'phone' => '081233445566',
            'credit_limit' => 4500000,
            'status' => 'AKTIF',
            'notes' => 'Karyawan baru divisi pembibitan tebu.',
        ];

        $response = $this->postJson('/api/members', $payload);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
            ]);

        $this->assertDatabaseHas('members', [
            'nik' => $uniqueNik,
            'branch' => 'Cabang 3 - Kebun Timur',
            'credit_limit' => 4500000,
        ]);
    }

    /**
     * Test update credit limit (Plafon Kredit Kasbon) & audit mutation log.
     */
    public function test_can_update_member_credit_limit_with_audit_trail(): void
    {
        $member = Member::where('status', 'AKTIF')->first();
        $newLimit = 6500000;

        $response = $this->putJson("/api/members/{$member->id}/credit-limit", [
            'credit_limit' => $newLimit,
            'notes' => 'Kenaikan plafon kasbon tahunan oleh Manager',
            'processed_by' => 'Manager Koperasi',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);

        $member->refresh();
        $this->assertEquals($newLimit, $member->credit_limit);

        // Check mutation recorded
        $this->assertDatabaseHas('debt_mutations', [
            'member_id' => $member->id,
            'mutation_type' => 'LIMIT_ADJUSTMENT',
            'new_limit' => $newLimit,
        ]);
    }

    /**
     * Test automatic ceiling deduction when POS kasbon transaction occurs.
     */
    public function test_automatic_credit_deduction_on_pos_kasbon(): void
    {
        // 1. Prepare open shift
        $cashier = User::where('role', 'like', '%Kasir%')->first();
        $shift = CashierShift::create([
            'shift_code' => 'SFT-TEST-' . time(),
            'terminal_code' => 'POS-01',
            'cashier_nik' => $cashier->nik,
            'cashier_name' => $cashier->name,
            'opening_cash' => 200000,
            'status' => 'OPEN',
            'opened_at' => now(),
        ]);

        // 2. Prepare member with sufficient credit limit
        $member = Member::where('status', 'AKTIF')
            ->whereRaw('credit_limit - current_debt >= 100000')
            ->first();

        $initialDebt = (float) $member->current_debt;

        // 3. Prepare product
        $product = Product::where('stock', '>', 5)->first();

        $checkoutPayload = [
            'shift_id' => $shift->id,
            'terminal_code' => 'POS-01',
            'cashier_nik' => $cashier->nik,
            'cashier_name' => $cashier->name,
            'payment_method' => 'KREDIT',
            'member_nik' => $member->nik,
            'items' => [
                [
                    'sku' => $product->sku,
                    'barcode' => $product->barcode,
                    'name' => $product->name,
                    'qty' => 1,
                    'price' => (float) $product->price,
                    'member_price' => (float) ($product->member_price ?? $product->price),
                    'unit' => $product->unit,
                    'is_member' => true,
                ],
            ],
        ];

        $response = $this->postJson('/api/pos/checkout', $checkoutPayload);

        $response->assertSuccessful()
            ->assertJson([
                'success' => true,
            ]);

        $grandTotal = (float) $response->json('data.grand_total');

        // Verify member debt increased by grand total
        $member->refresh();
        $this->assertEquals($initialDebt + $grandTotal, (float) $member->current_debt);

        // Verify audit mutation was logged
        $this->assertDatabaseHas('debt_mutations', [
            'member_id' => $member->id,
            'mutation_type' => 'KASBON_POS',
            'amount' => $grandTotal,
        ]);
    }

    /**
     * Test record kasbon settlement (pelunasan / bayar piutang).
     */
    public function test_can_record_settlement_and_reduce_debt(): void
    {
        $member = Member::first();
        $member->current_debt = 500000;
        $member->save();

        $initialDebt = (float) $member->current_debt;
        $settlementAmount = 100000;

        $response = $this->postJson("/api/members/{$member->id}/settlement", [
            'amount' => $settlementAmount,
            'type' => 'PAYMENT_SETTLEMENT',
            'notes' => 'Pembayaran tunai sebagian di kasir',
            'processed_by' => 'Kasir Operasional',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);

        $member->refresh();
        $this->assertEquals($initialDebt - $settlementAmount, (float) $member->current_debt);

        $this->assertDatabaseHas('debt_mutations', [
            'member_id' => $member->id,
            'mutation_type' => 'PAYMENT_SETTLEMENT',
            'amount' => $settlementAmount,
        ]);
    }

    /**
     * Test Laporan Rekapitulasi Piutang Anggota API.
     */
    public function test_can_retrieve_member_debts_monthly_report(): void
    {
        $month = now()->format('Y-m');
        $response = $this->getJson("/api/reports/member-debts?month={$month}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'summary' => [
                    'period_month',
                    'total_registered_members',
                    'members_with_active_debt',
                    'total_credit_limit',
                    'total_active_debt',
                    'total_remaining_credit',
                    'overall_utilization_percent',
                ],
                'data',
                'meta',
            ]);
    }

    /**
     * Test Export CSV / Excel ready format for Tim Finance / HRD PT Indo Lampung Perkasa.
     */
    public function test_can_export_rekapitulasi_piutang_csv_for_hrd_finance(): void
    {
        $month = now()->format('Y-m');
        $response = $this->get("/api/reports/member-debts/export-csv?month={$month}");

        $response->assertStatus(200);
        $this->assertTrue(str_contains($response->headers->get('content-type'), 'text/csv'));
        $this->assertTrue(str_contains($response->headers->get('content-disposition'), 'Rekapitulasi_Piutang_Kasbon_PT_ILP'));

        $content = $response->streamedContent();
        $this->assertStringContainsString('PT INDO LAMPUNG PERKASA', $content);
        $this->assertStringContainsString('LAPORAN REKAPITULASI PIUTANG KASBON BULANAN KARYAWAN', $content);
        $this->assertStringContainsString('HRD / Finance PT Indo Lampung Perkasa', $content);
    }

    /**
     * Test Export Excel (.xlsx) format for Tim Finance / HRD PT Indo Lampung Perkasa.
     */
    public function test_can_export_rekapitulasi_piutang_excel_for_hrd_finance(): void
    {
        $month = now()->format('Y-m');
        $response = $this->get("/api/reports/member-debts/export-excel?month={$month}");

        $response->assertStatus(200);
        $this->assertTrue(str_contains($response->headers->get('content-type'), 'spreadsheetml.sheet'));
        $this->assertTrue(str_contains($response->headers->get('content-disposition'), '.xlsx'));
    }
}
