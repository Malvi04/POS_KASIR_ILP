<?php

namespace Tests\Feature;

use App\Models\Member;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class OwnerFeaturesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Ensure Owner exists
        User::updateOrCreate(
            ['username' => 'owner_ilp'],
            [
                'name' => 'Ir. Hartono Wibowo',
                'email' => 'owner@minimarket.ilp',
                'role' => 'Owner',
                'pin' => '999888',
                'branch' => 'Pusat',
                'password' => Hash::make('owner123'),
                'is_active' => true,
            ]
        );
    }

    /**
     * 1. Test Owner PIN authorization.
     */
    public function test_owner_pin_authorization(): void
    {
        $response = $this->postJson('/api/pos/void/authorize', [
            'pin_or_password' => '999888',
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('authorized', true);
        $this->assertEquals('Owner', $response->json('supervisor.role'));
    }

    /**
     * 2. Test Owner Consolidated 3-Branch Business Performance & P&L API.
     */
    public function test_owner_consolidated_3_branch_report_api(): void
    {
        $response = $this->getJson('/api/reports/consolidated-branches?start_date=' . now()->startOfMonth()->toDateString() . '&end_date=' . now()->toDateString());

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'period' => ['start_date', 'end_date'],
            'data' => [
                'financial_summary' => [
                    'gross_revenue',
                    'total_cogs',
                    'gross_profit',
                    'total_discounts',
                    'total_tax',
                    'net_profit',
                    'profit_margin_percent',
                    'transaction_count',
                    'average_basket',
                ],
                'member_debt_overview' => [
                    'total_members',
                    'members_with_debt',
                    'total_credit_limit',
                    'total_outstanding_debt',
                    'total_remaining_credit',
                    'overall_debt_utilization_percent',
                ],
                'stock_valuation' => [
                    'total_product_count',
                    'total_inventory_stock_qty',
                    'total_stock_asset_cost',
                    'total_stock_retail_value',
                    'low_stock_count',
                    'critical_expired_count',
                ],
                'branch_comparison',
                'recent_stock_corrections',
            ],
        ]);
        $this->assertTrue($response->json('success'));
        $this->assertCount(3, $response->json('data.branch_comparison'));
    }

    /**
     * 3. Test Owner Tax (PPN) Configuration API.
     */
    public function test_owner_tax_configuration_api(): void
    {
        // 1. Set PPN to 12%
        $taxRes = $this->putJson('/api/settings/tax', [
            'tax_rate_percent' => 12,
            'tax_enabled' => true,
            'tax_calculation_method' => 'EXCLUDE',
        ]);

        $taxRes->assertStatus(200);
        $taxRes->assertJsonPath('success', true);
        $this->assertEquals(12, $taxRes->json('data.tax_rate_percent'));

        // 2. Verify via settings index
        $getRes = $this->getJson('/api/settings');
        $getRes->assertStatus(200);
        $this->assertEquals(12, $getRes->json('data.tax.tax_rate_percent'));

        // 3. Reset back to 11%
        $this->putJson('/api/settings/tax', [
            'tax_rate_percent' => 11,
            'tax_enabled' => true,
            'tax_calculation_method' => 'EXCLUDE',
        ])->assertStatus(200);
    }

    /**
     * 4. Test Owner User Management (Full Control CRUD).
     */
    public function test_owner_user_management_crud(): void
    {
        $uniqueNik = '9999' . rand(10000000, 99999999);
        $uniqueUser = 'spv_test_' . rand(100, 999);

        // 1. Create Supervisor Account
        $createRes = $this->postJson('/api/users', [
            'nik' => $uniqueNik,
            'name' => 'Supervisor Cabang 2',
            'username' => $uniqueUser,
            'email' => $uniqueUser . '@ilp.com',
            'role' => 'Supervisor',
            'branch' => 'Cabang 2 - Kebun Barat',
            'pin' => '654321',
            'password' => 'spvsecret123',
            'password_confirmation' => 'spvsecret123',
        ]);

        $createRes->assertStatus(201);
        $userId = $createRes->json('data.id');
        $this->assertNotNull($userId);

        // 2. Reset PIN
        $pinRes = $this->putJson("/api/users/{$userId}/pin", [
            'pin' => '112233',
        ]);
        $pinRes->assertStatus(200);

        // 3. Toggle Status
        $toggleRes = $this->postJson("/api/users/{$userId}/toggle-status");
        $toggleRes->assertStatus(200);
        $this->assertFalse($toggleRes->json('data.is_active'));

        // 4. Delete Account
        $delRes = $this->deleteJson("/api/users/{$userId}");
        $delRes->assertStatus(200);
        $this->assertNull(User::find($userId));
    }

    /**
     * 5. Test Owner Bulk Limit and HRD Payroll Cutoff API.
     */
    public function test_owner_member_bulk_limit_and_payroll_cutoff(): void
    {
        // 1. Bulk limit update
        $bulkRes = $this->postJson('/api/members/bulk-limit', [
            'target_type' => 'DEPARTMENT',
            'target_value' => 'Operasional Kebun',
            'credit_limit' => 3500000,
            'notes' => 'Penyesuaian plafon tahunan oleh Owner',
        ]);
        $bulkRes->assertStatus(200);
        $bulkRes->assertJsonPath('success', true);

        // 2. Payroll cutoff simulation
        $cutoffRes = $this->postJson('/api/reports/member-debts/payroll-cutoff', [
            'month' => now()->format('Y-m'),
            'notes' => 'Potong gaji reguler bulanan payroll HRD',
        ]);
        $cutoffRes->assertStatus(200);
        $cutoffRes->assertJsonPath('success', true);
    }
}
