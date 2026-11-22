<?php

namespace Tests\Feature;

use App\Models\CashierShift;
use App\Models\Location;
use App\Models\Member;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MemberOverLimitDebtMonitoringTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
    }

    public function test_member_exceeding_credit_limit_can_still_transact_with_over_limit_status(): void
    {
        // 1. Setup cashier user, shift, store location, and product
        $cashier = User::factory()->create([
            'role' => 'KASIR',
            'is_active' => true,
        ]);

        $location = Location::create([
            'code' => 'CAB-001',
            'name' => 'Cabang Pusat',
            'type' => 'STORE',
            'is_active' => true,
        ]);

        $shift = CashierShift::create([
            'shift_code' => 'SHIFT-' . time(),
            'terminal_code' => 'POS-01',
            'cashier_nik' => 'KASIR-001',
            'cashier_name' => $cashier->name,
            'opening_cash' => 200000,
            'status' => 'OPEN',
            'opened_at' => now(),
            'branch' => 'Cabang Pusat',
            'user_id' => $cashier->id,
            'location_id' => $location->id,
        ]);

        $category = ProductCategory::create([
            'name' => 'Sembako',
            'code' => 'SBK',
        ]);

        $product = Product::create([
            'category_id' => $category->id,
            'sku' => 'BRS-001',
            'barcode' => '899123456001',
            'name' => 'Beras Premium 5kg',
            'cost_price' => 60000,
            'price' => 75000,
            'stock' => 50,
            'unit' => 'Sak',
            'status' => 'ACTIVE',
        ]);

        // 2. Setup Member with credit limit Rp 100.000, but purchase total will be Rp 150.000 (exceeds limit!)
        $member = Member::create([
            'member_code' => '01-0001',
            'nik' => '199701002',
            'name' => 'Ir. H. Mudjiono',
            'department' => 'Central Personnel',
            'branch' => 'Cabang Pusat',
            'credit_limit' => 100000,
            'current_debt' => 0,
            'status' => 'AKTIF',
            'is_active' => true,
        ]);

        // Validate API reports over limit eligibility
        $validateRes = $this->actingAs($cashier)->getJson("/api/pos/members/validate/{$member->nik}?amount=150000");
        $validateRes->assertStatus(200);
        $validateRes->assertJsonPath('success', true);
        $validateRes->assertJsonPath('is_eligible', true);
        $validateRes->assertJsonPath('is_over_limit', true);
        $validateRes->assertJsonPath('debt_status', 'MELEBIHI_LIMIT');

        // 3. Checkout with KREDIT: Total is 2 x Rp 75.000 = Rp 150.000 (exceeds limit Rp 100.000)
        $checkoutRes = $this->actingAs($cashier)->postJson('/api/pos/checkout', [
            'shift_id' => $shift->id,
            'terminal_code' => 'POS-01',
            'cashier_name' => $cashier->name,
            'payment_method' => 'KREDIT',
            'member_nik' => $member->nik,
            'items' => [
                [
                    'product_id' => $product->id,
                    'sku' => $product->sku,
                    'barcode' => $product->barcode,
                    'name' => $product->name,
                    'unit' => $product->unit,
                    'price' => $product->price,
                    'qty' => 2,
                ],
            ],
        ]);

        $checkoutRes->assertStatus(201);
        $checkoutRes->assertJsonPath('success', true);
        $invoiceNumber = $checkoutRes->json('data.invoice_number');
        $grandTotal = (float) $checkoutRes->json('data.grand_total');

        // Verify transaction is flagged as over limit
        $this->assertDatabaseHas('transactions', [
            'invoice_number' => $invoiceNumber,
            'payment_method' => 'KREDIT',
            'is_over_limit' => true,
            'debt_status' => 'MELEBIHI_LIMIT',
            'grand_total' => $grandTotal,
        ]);

        // Verify member debt is updated and marked as over limit
        $member->refresh();
        $this->assertEquals($grandTotal, (float) $member->current_debt);
        $this->assertTrue($member->is_over_limit);
        $this->assertEquals($grandTotal - 100000, (float) $member->excess_debt_amount);
        $this->assertEquals('MELEBIHI_LIMIT', $member->debt_status);

        // Verify debt mutation recorded over limit
        $this->assertDatabaseHas('debt_mutations', [
            'invoice_number' => $invoiceNumber,
            'is_over_limit' => true,
            'debt_status' => 'MELEBIHI_LIMIT',
            'new_debt' => $grandTotal,
        ]);

        // 4. Test Manager Piutang API: Monitoring & Details
        $manager = User::factory()->create([
            'role' => 'MANAGER',
            'is_active' => true,
        ]);

        // Manager debts overview
        $debtsRes = $this->actingAs($manager)->getJson('/api/manager/debts');
        $debtsRes->assertStatus(200);
        $debtsRes->assertJsonPath('success', true);
        $debtsRes->assertJsonPath('summary.members_over_limit_count', 1);
        $this->assertEquals($grandTotal, $debtsRes->json('summary.total_active_debt'));

        // Manager filter by over limit
        $filterRes = $this->actingAs($manager)->getJson('/api/manager/debts?filter_status=over_limit');
        $filterRes->assertStatus(200);
        $filterRes->assertJsonCount(1, 'data');
        $this->assertEquals($member->id, $filterRes->json('data.0.id'));
        $this->assertTrue($filterRes->json('data.0.is_over_limit'));
        $this->assertEquals('MELEBIHI_LIMIT', $filterRes->json('data.0.debt_status'));

        // Manager view member's piutang transactions & items
        $trxRes = $this->actingAs($manager)->getJson("/api/manager/debts/members/{$member->id}/transactions");
        $trxRes->assertStatus(200);
        $trxRes->assertJsonPath('success', true);
        $trxRes->assertJsonCount(1, 'data');
        $this->assertEquals($invoiceNumber, $trxRes->json('data.0.invoice_number'));
        $this->assertTrue($trxRes->json('data.0.is_over_limit'));
        $this->assertEquals('MELEBIHI_LIMIT', $trxRes->json('data.0.debt_status'));
        $this->assertCount(1, $trxRes->json('data.0.items'));
        $this->assertEquals('Beras Premium 5kg', $trxRes->json('data.0.items.0.name'));
        $this->assertEquals(2, $trxRes->json('data.0.items.0.qty'));

        // Manager view thermal receipt
        $receiptRes = $this->actingAs($manager)->getJson("/api/manager/debts/transactions/{$invoiceNumber}/receipt");
        $receiptRes->assertStatus(200);
        $receiptRes->assertJsonPath('success', true);
        $receiptRes->assertJsonPath('data.is_over_limit', true);
        $receiptRes->assertJsonPath('data.debt_status', 'MELEBIHI_LIMIT');
        $this->assertEquals($grandTotal, $receiptRes->json('data.total'));

        // Manager Web Route
        $webRes = $this->actingAs($manager)->get('/manager/piutang');
        $webRes->assertStatus(200);
        $webRes->assertSee('Daftar &amp; Monitoring Piutang', false);
    }
}
