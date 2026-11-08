<?php

namespace Tests\Feature;

use App\Models\CashierShift;
use App\Models\HeldBill;
use App\Models\Member;
use App\Models\Product;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Str;
use Tests\TestCase;

class CashierFeaturesComprehensiveTest extends TestCase
{
    /**
     * Test Cashier Transactions List API with Search, Payment Method & Status Filter.
     */
    public function test_cashier_transactions_api_list_and_filters(): void
    {
        $response = $this->getJson('/api/pos/transactions?per_page=20');
        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'summary' => [
                'total_omzet',
                'total_cash',
                'total_non_cash',
                'total_transactions',
                'total_voided',
            ],
            'data',
            'pagination',
        ]);
    }

    /**
     * Test Cashier Product Inventory CRUD.
     */
    public function test_cashier_product_inventory_crud(): void
    {
        $uniqueBarcode = '899' . rand(1000000000, 9999999999);
        $uniqueSku = 'PRD-TEST-' . rand(100, 999);

        // 1. Create Product
        $createRes = $this->postJson('/api/products', [
            'sku' => $uniqueSku,
            'barcode' => $uniqueBarcode,
            'name' => 'Biskuit Roma Kelapa 300g',
            'category' => 'Makanan Ringan',
            'unit' => 'Pack',
            'cost_price' => 8000,
            'price' => 10000,
            'member_price' => 9500,
            'stock' => 50,
            'min_stock' => 10,
            'rack_location' => 'Lorong C - Rak 02',
            'is_taxable' => true,
        ]);

        $createRes->assertStatus(201);
        $createRes->assertJsonPath('success', true);
        $productId = $createRes->json('data.id');
        $this->assertNotNull($productId);

        // 2. Read / Verify in Search
        $searchRes = $this->getJson("/api/pos/products/search?q={$uniqueBarcode}");
        $searchRes->assertStatus(200);
        $this->assertTrue(collect($searchRes->json('data'))->contains('barcode', $uniqueBarcode));

        // 3. Update Product
        $updateRes = $this->putJson("/api/products/{$productId}", [
            'barcode' => $uniqueBarcode,
            'name' => 'Biskuit Roma Kelapa 300g Super',
            'category' => 'Makanan Ringan',
            'unit' => 'Pack',
            'cost_price' => 8500,
            'price' => 11000,
            'member_price' => 10500,
            'stock' => 60,
            'min_stock' => 12,
            'rack_location' => 'Lorong C - Rak 02 Promosi',
        ]);

        $updateRes->assertStatus(200);
        $this->assertEquals('Biskuit Roma Kelapa 300g Super', $updateRes->json('data.name'));
        $this->assertEquals(60, $updateRes->json('data.stock'));

        // 4. Delete Product
        $deleteRes = $this->deleteJson("/api/products/{$productId}");
        $deleteRes->assertStatus(200);
        $this->assertNull(Product::find($productId));
    }

    /**
     * Test Cashier Member Management CRUD & Credit Checking.
     */
    public function test_cashier_member_management_crud(): void
    {
        $uniqueNik = '2026' . rand(10000000, 99999999);

        // 1. Create Member
        $createRes = $this->postJson('/api/members', [
            'nik' => $uniqueNik,
            'name' => 'Kusuma Wardana',
            'department' => 'Operasional Kebun',
            'branch' => 'Cabang 2 - Kebun Barat',
            'phone' => '081234567899',
            'credit_limit' => 3000000,
            'status' => 'AKTIF',
            'notes' => 'Pendaftaran anggota kasir',
        ]);

        $createRes->assertStatus(201);
        $memberId = $createRes->json('data.id');
        $this->assertNotNull($memberId);

        // 2. Validate NIK for Cashier Checkout
        $validateRes = $this->getJson("/api/pos/members/validate/{$uniqueNik}");
        $validateRes->assertStatus(200);
        $validateRes->assertJsonPath('is_eligible', true);
        $validateRes->assertJsonPath('data.remaining_credit', 3000000);

        // 3. Update Member
        $updateRes = $this->putJson("/api/members/{$memberId}", [
            'name' => 'Kusuma Wardana S.P.',
            'department' => 'Agronomi & QC',
            'branch' => 'Cabang 2 - Kebun Barat',
            'phone' => '081234567800',
            'status' => 'AKTIF',
            'notes' => 'Update jabatan',
        ]);
        $updateRes->assertStatus(200);
        $this->assertEquals('Kusuma Wardana S.P.', $updateRes->json('data.name'));

        // 4. Delete Member
        $deleteRes = $this->deleteJson("/api/members/{$memberId}");
        $deleteRes->assertStatus(200);
        $this->assertNull(Member::find($memberId));
    }

    /**
     * Test Cashier Held Bill Lifecycle (Hold -> List -> Recall / Cancel).
     */
    public function test_cashier_held_bill_lifecycle(): void
    {
        \App\Models\CashierShift::firstOrCreate(
            ['terminal_code' => 'POS-01', 'status' => 'OPEN'],
            [
                'shift_code' => 'CS-' . date('Ymd') . '-POS01-001',
                'cashier_nik' => '202409821002',
                'cashier_name' => 'Budi Kurniawan',
                'opening_cash' => 500000,
                'status' => 'OPEN',
                'opened_at' => now(),
            ]
        );

        // 1. Hold a transaction
        $holdRes = $this->postJson('/api/pos/hold', [
            'terminal_code' => 'POS-01',
            'customer_name' => 'Pelanggan Antrean 5',
            'note' => 'Tertinggal dompet di motor',
            'items' => [
                [
                    'sku' => 'PRD-001',
                    'barcode' => '8991234567890',
                    'name' => 'Aqua Mineral 600ml',
                    'qty' => 3,
                    'price' => 5500,
                    'unit' => 'Botol',
                ]
            ],
            'subtotal' => 16500,
            'tax_total' => 0,
            'grand_total' => 16500,
        ]);

        $holdRes->assertStatus(201);
        $heldId = $holdRes->json('data.id');
        $this->assertNotNull($heldId);

        // 2. List Held Bills
        $listRes = $this->getJson('/api/pos/held-bills?terminal_code=POS-01');
        $listRes->assertStatus(200);
        $this->assertTrue(collect($listRes->json('data'))->contains('id', $heldId));

        // 3. Recall Held Bill
        $recallRes = $this->postJson("/api/pos/recall/{$heldId}");
        $recallRes->assertStatus(200);
        $recallRes->assertJsonPath('success', true);

        // Verify status in DB
        $held = HeldBill::find($heldId);
        $this->assertEquals('RECALLED', $held->status);
    }

    /**
     * Test Cashier Petty Cash Movements in Active Shift.
     */
    public function test_cashier_shift_cash_movements(): void
    {
        $cashier = User::where('role', 'like', '%Kasir%')->first();
        $uniqueTerminal = 'POS-CASH-' . rand(10, 99);

        // 1. Open Shift
        $openRes = $this->postJson('/api/shift/open', [
            'terminal_code' => $uniqueTerminal,
            'cashier_nik' => $cashier->nik,
            'cashier_name' => $cashier->name,
            'opening_cash' => 250000,
        ]);
        $openRes->assertStatus(201);
        $shiftId = $openRes->json('data.id');

        // 2. Cash In (Tambahan Kas)
        $cashInRes = $this->postJson('/api/shift/cash-movement', [
            'shift_id' => $shiftId,
            'type' => 'IN',
            'amount' => 50000,
            'reason' => 'Tambahan modal uang kecil',
        ]);
        $cashInRes->assertStatus(200);
        $this->assertEquals(300000, $cashInRes->json('data.expected_cash'));

        // 3. Cash Out (Beli ATK)
        $cashOutRes = $this->postJson('/api/shift/cash-movement', [
            'shift_id' => $shiftId,
            'type' => 'OUT',
            'amount' => 20000,
            'reason' => 'Beli kertas struk kasir cadangan',
        ]);
        $cashOutRes->assertStatus(200);
        $this->assertEquals(280000, $cashOutRes->json('data.expected_cash'));

        // 4. Close Shift with Cash Count
        $closeRes = $this->postJson('/api/shift/close', [
            'terminal_code' => $uniqueTerminal,
            'actual_cash' => 280000,
            'closing_notes' => 'Tutup shift kasir pas.',
        ]);
        $closeRes->assertStatus(200);
        $this->assertEquals(0, $closeRes->json('data.variance'));
        $this->assertEquals('SESUAI', $closeRes->json('data.variance_status'));
    }
}
