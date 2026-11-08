<?php

namespace Tests\Feature;

use App\Models\AppSetting;
use App\Models\CashierShift;
use App\Models\Member;
use App\Models\Product;
use App\Models\StockCorrection;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UserAccessSecurityModulDTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Setup test users for each role
        User::updateOrCreate(
            ['username' => 'kasir_test_01'],
            [
                'name' => 'Budi Kasir Test',
                'email' => 'budi.kasir.test@minimarket.ilp',
                'nik' => 'KASIR-TEST-01',
                'role' => 'Kasir',
                'pin' => '111222',
                'branch' => 'Pusat',
                'password' => Hash::make('kasir123'),
                'is_active' => true,
            ]
        );

        User::updateOrCreate(
            ['username' => 'spv_test_01'],
            [
                'name' => 'Hendra SPV Test',
                'email' => 'spv.test@minimarket.ilp',
                'nik' => 'SPV-TEST-01',
                'role' => 'Supervisor',
                'pin' => '333444',
                'branch' => 'Pusat',
                'password' => Hash::make('spv123'),
                'is_active' => true,
            ]
        );

        User::updateOrCreate(
            ['username' => 'owner_test_01'],
            [
                'name' => 'Pak Hartono Owner Test',
                'email' => 'owner.test@minimarket.ilp',
                'nik' => 'OWNER-TEST-01',
                'role' => 'Owner',
                'pin' => '999888',
                'branch' => 'Pusat',
                'password' => Hash::make('owner123'),
                'is_active' => true,
            ]
        );
    }

    /**
     * Test 1: Kasir CANNOT authorize void without Supervisor/Manager.
     */
    public function test_cashier_cannot_authorize_void_with_cashier_pin(): void
    {
        $response = $this->postJson('/api/pos/void/authorize', [
            'pin_or_password' => '111222', // Cashier PIN
            'action_type' => 'VOID_TRANSACTION',
        ]);

        $response->assertStatus(403);
        $response->assertJson([
            'authorized' => false,
        ]);
        $this->assertStringContainsString('Kasir tidak memiliki wewenang', $response->json('message'));
    }

    /**
     * Test 2: Supervisor CAN authorize void with supervisor PIN.
     */
    public function test_supervisor_can_authorize_void_with_supervisor_pin(): void
    {
        $response = $this->postJson('/api/pos/void/authorize', [
            'pin_or_password' => '333444', // SPV PIN
            'action_type' => 'VOID_TRANSACTION',
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'authorized' => true,
            'supervisor' => [
                'name' => 'Hendra SPV Test',
                'role' => 'Supervisor',
            ],
        ]);
    }

    /**
     * Test 3: Supervisor can authorize manual discount with valid PIN.
     */
    public function test_supervisor_can_authorize_manual_discount(): void
    {
        // 1. Invalid Cashier PIN attempt
        $failResponse = $this->postJson('/api/pos/discount/authorize', [
            'pin_or_password' => '111222',
            'discount_type' => 'PERCENT',
            'discount_value' => 15,
            'reason' => 'Promo Member Loyal',
        ]);
        $failResponse->assertStatus(403);

        // 2. Valid SPV PIN
        $successResponse = $this->postJson('/api/pos/discount/authorize', [
            'pin_or_password' => '333444',
            'discount_type' => 'PERCENT',
            'discount_value' => 15,
            'reason' => 'Promo Diskon Loyalis',
        ]);

        $successResponse->assertStatus(200);
        $this->assertTrue($successResponse->json('authorized'));
        $this->assertNotEmpty($successResponse->json('auth_code'));
        $this->assertEquals(15, $successResponse->json('discount.value'));
    }

    /**
     * Test 4: Supervisor can execute daily branch stock correction.
     */
    public function test_supervisor_can_execute_daily_stock_correction(): void
    {
        $product = Product::firstOrCreate(
            ['sku' => 'TEST-STOCK-CORR'],
            [
                'name' => 'Gula Pasir 1kg Test',
                'barcode' => '8991234567899',
                'category' => 'Sembako',
                'unit' => 'Pcs',
                'cost_price' => 12000,
                'price' => 15000,
                'stock' => 50,
                'min_stock' => 10,
                'status' => 'TERSEDIA',
            ]
        );

        $product->update(['stock' => 50]);
        $product->refresh();

        $initialStock = (int) $product->stock;

        // Record stock correction for 3 pcs broken bags
        $response = $this->postJson('/api/inventory/stock-corrections', [
            'product_id' => $product->id,
            'type' => 'SUBTRACT',
            'qty' => 3,
            'reason' => 'Kemasan Rusak & Bocor',
            'branch' => 'Cabang 1 - Mill / Pabrik',
            'pin' => '333444', // SPV PIN
            'notes' => 'Tumpahan gula saat bongkar muat logistik.',
        ]);

        $response->assertStatus(201);
        $this->assertTrue($response->json('success'));
        $this->assertEquals($initialStock - 3, $response->json('data.product.stock_after'));

        $this->assertDatabaseHas('stock_corrections', [
            'product_id' => $product->id,
            'qty' => 3,
            'reason' => 'Kemasan Rusak & Bocor',
            'supervisor_name' => 'Hendra SPV Test',
        ]);
    }

    /**
     * Test 5: Owner / Superadmin can manage users (CRUD).
     */
    public function test_owner_can_manage_users_crud(): void
    {
        // 1. Get users list
        $listResponse = $this->getJson('/api/users');
        $listResponse->assertStatus(200);
        $this->assertTrue($listResponse->json('success'));
        $this->assertGreaterThanOrEqual(3, count($listResponse->json('data')));

        // 2. Create new user
        $createResponse = $this->postJson('/api/users', [
            'name' => 'Rina Staff Baru',
            'username' => 'rina_kasir_new',
            'email' => 'rina.new@minimarket.ilp',
            'nik' => '202611019999',
            'role' => 'Kasir',
            'pin' => '654321',
            'branch' => 'Cabang 2 - Kebun Barat',
            'password' => 'secret123',
            'is_active' => true,
        ]);

        $createResponse->assertStatus(201);
        $createdId = $createResponse->json('data.id');

        // 3. Update user
        $updateResponse = $this->putJson("/api/users/{$createdId}", [
            'name' => 'Rina Staff Promosi SPV',
            'username' => 'rina_kasir_new',
            'email' => 'rina.new@minimarket.ilp',
            'nik' => '202611019999',
            'role' => 'Supervisor',
            'branch' => 'Cabang 2 - Kebun Barat',
            'is_active' => true,
        ]);

        $updateResponse->assertStatus(200);
        $this->assertEquals('Supervisor', $updateResponse->json('data.role'));

        // 4. Reset PIN
        $pinResponse = $this->putJson("/api/users/{$createdId}/pin", [
            'pin' => '888999',
        ]);
        $pinResponse->assertStatus(200);
        $this->assertEquals('888999', $pinResponse->json('pin'));

        // 5. Toggle Status
        $toggleResponse = $this->postJson("/api/users/{$createdId}/toggle-status");
        $toggleResponse->assertStatus(200);
        $this->assertFalse($toggleResponse->json('is_active'));

        // 6. Delete User
        $deleteResponse = $this->deleteJson("/api/users/{$createdId}");
        $deleteResponse->assertStatus(200);
        $this->assertDatabaseMissing('users', ['id' => $createdId]);
    }

    /**
     * Test 6: Owner can update Tax (PPN) settings.
     */
    public function test_owner_can_configure_tax_settings(): void
    {
        $response = $this->putJson('/api/settings/tax', [
            'tax_rate_percent' => 12,
            'tax_enabled' => true,
            'tax_calculation_method' => 'EXCLUDE',
        ]);

        $response->assertStatus(200);
        $this->assertTrue($response->json('success'));
        $this->assertEquals(12, $response->json('tax.rate'));

        // Verify retrieval
        $getSettings = $this->getJson('/api/settings');
        $getSettings->assertStatus(200);
        $this->assertEquals(12, $getSettings->json('data.tax.tax_rate_percent'));
        $this->assertTrue($getSettings->json('data.tax.tax_enabled'));
    }

    /**
     * Test 7: Owner can query Consolidated 3-Branch Business Performance & P&L
     * without being tied to active cashier shift.
     */
    public function test_owner_can_view_consolidated_3_branch_performance(): void
    {
        $response = $this->getJson('/api/reports/consolidated-branches');

        $response->assertStatus(200);
        $this->assertTrue($response->json('success'));

        // Check Financial P&L keys
        $this->assertArrayHasKey('financial_summary', $response->json());
        $this->assertArrayHasKey('gross_revenue', $response->json('financial_summary'));
        $this->assertArrayHasKey('gross_profit', $response->json('financial_summary'));
        $this->assertArrayHasKey('net_profit', $response->json('financial_summary'));

        // Check Member Debt keys
        $this->assertArrayHasKey('member_debt_summary', $response->json());
        $this->assertArrayHasKey('total_outstanding_debt', $response->json('member_debt_summary'));

        // Check Inventory Valuation keys
        $this->assertArrayHasKey('inventory_valuation', $response->json());
        $this->assertArrayHasKey('total_asset_cost_value', $response->json('inventory_valuation'));

        // Check 3 Branches Breakdown
        $branches = $response->json('branch_breakdown');
        $this->assertCount(3, $branches);
        $this->assertEquals('Pusat', $branches[0]['branch_name']);
        $this->assertEquals('Cabang 1 - Mill / Pabrik', $branches[1]['branch_name']);
        $this->assertEquals('Cabang 2 - Kebun Barat', $branches[2]['branch_name']);
    }
}
