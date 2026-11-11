<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\LeaveRequest;
use App\Models\Product;
use App\Models\Promotion;
use App\Models\StoreReturnHeader;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ManagerSupervisorRolesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Ensure Supervisor user exists
        User::updateOrCreate(
            ['nik' => '202409821010'],
            [
                'name' => 'Hendra Wijaya',
                'username' => 'spv_hendra',
                'email' => 'spv@minimarket.ilp',
                'role' => 'Supervisor',
                'pin' => '123456',
                'branch' => 'Pusat',
                'password' => Hash::make('spv123'),
            ]
        );
    }

    /**
     * 1. Test Manager Dashboard Summary API.
     */
    public function test_manager_dashboard_summary_api(): void
    {
        $response = $this->getJson('/api/manager/dashboard-summary');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'data' => [
                'sales_today',
                'trx_count_today',
                'low_stock_count',
                'low_stock_items',
                'cashier_list',
                'weekly_sales',
            ],
        ]);
        $this->assertTrue($response->json('success'));
    }

    /**
     * 2. Test Promotions & Expiry Discount Rules Lifecycle API.
     */
    public function test_promotions_and_rules_lifecycle_api(): void
    {
        // 1. Get List
        $listRes = $this->getJson('/api/promotions');
        $listRes->assertStatus(200);
        $listRes->assertJsonPath('success', true);

        // 2. Create Promotion
        $createRes = $this->postJson('/api/promotions', [
            'name' => 'Diskon Weekend Kemerdekaan',
            'condition' => 'Min belanja Rp 100.000',
            'type' => 'PERCENTAGE',
            'value' => 17,
            'period_start' => now()->toDateString(),
            'period_end' => now()->addDays(3)->toDateString(),
            'status' => 'AKTIF',
        ]);
        $createRes->assertStatus(201);
        $promoId = $createRes->json('data.id');
        $this->assertNotNull($promoId);

        // 3. Update Promotion
        $updateRes = $this->putJson("/api/promotions/{$promoId}", [
            'name' => 'Diskon Weekend Super Merdeka',
            'type' => 'PERCENTAGE',
            'value' => 20,
            'status' => 'AKTIF',
        ]);
        $updateRes->assertStatus(200);
        $this->assertEquals('Diskon Weekend Super Merdeka', $updateRes->json('data.name'));

        // 4. Update Expiry Settings
        $settingRes = $this->putJson('/api/promotions/settings', [
            'h7Percent' => 45,
            'h3Percent' => 75,
            'autoPrint' => true,
        ]);
        $settingRes->assertStatus(200);
        $this->assertEquals(45, $settingRes->json('data.h7Percent'));

        // 5. Delete Promotion
        $delRes = $this->deleteJson("/api/promotions/{$promoId}");
        $delRes->assertStatus(200);
        $this->assertNull(Promotion::find($promoId));
    }

    /**
     * 3. Test Store Returns (Retur Toko) & Supervisor Approval.
     */
    public function test_store_returns_and_supervisor_approval(): void
    {
        $product = Product::first();
        $this->assertNotNull($product, 'Ensure products seeded');

        // 1. List Returns
        $listRes = $this->getJson('/api/returns');
        $listRes->assertStatus(200);
        $listRes->assertJsonPath('success', true);

        // 2. Create Return Document
        $createRes = $this->postJson('/api/returns', [
            'direction' => 'toko_ke_gudang',
            'condition_type' => 'rusak',
            'product_id' => $product->id,
            'qty' => 5,
            'unit' => 'Pcs',
            'reason' => 'Bocor saat pembongkaran rak gondola',
            'driver_name' => 'Bambang Sudiro',
            'vehicle_plate' => 'B 9211 UQ',
            'use_fleet' => true,
        ]);
        $createRes->assertStatus(201);
        $returnId = $createRes->json('data.id');
        $this->assertNotNull($returnId);

        // 3. Supervisor Approve Return
        $approveRes = $this->putJson("/api/returns/{$returnId}/status", [
            'status' => 'disetujui',
        ]);
        $approveRes->assertStatus(200);
        $this->assertEquals('disetujui', $approveRes->json('data.status'));

        // 4. Check DB
        $returnDoc = StoreReturnHeader::find($returnId);
        $this->assertEquals('disetujui', $returnDoc->status);
    }

    /**
     * 4. Test Attendance, Shift Schedule & Leave Requests Workflow.
     */
    public function test_attendance_and_leave_requests_workflow(): void
    {
        $uniqueStaff = 'Dewi Sartika ' . rand(1000, 9999);

        // 1. Check shifts
        $shiftRes = $this->getJson('/api/attendance/shifts');
        $shiftRes->assertStatus(200);
        $shiftRes->assertJsonPath('success', true);

        // 2. Record Check-In
        $checkInRes = $this->postJson('/api/attendance', [
            'staffName' => $uniqueStaff,
            'terminal' => 'Kasir 02',
            'branch' => 'Cabang Pusat',
            'type' => 'MASUK',
            'notes' => 'Shift pagi tepat waktu',
        ]);
        $checkInRes->assertStatus(201);
        $attendanceId = $checkInRes->json('data.id');

        // 3. Record Check-Out
        $checkOutRes = $this->postJson('/api/attendance', [
            'staffName' => $uniqueStaff,
            'terminal' => 'Kasir 02',
            'branch' => 'Cabang Pusat',
            'type' => 'PULANG',
            'notes' => 'Selesai tugas',
        ]);
        $checkOutRes->assertStatus(200);
        $this->assertNotEquals('-', $checkOutRes->json('data.checkOut'));

        // 4. Submit Leave Request
        $leaveRes = $this->postJson('/api/attendance/leaves', [
            'staffName' => $uniqueStaff,
            'type' => 'IZIN',
            'date' => now()->addDays(2)->format('d M Y'),
            'reason' => 'Urusan keluarga mendesak',
        ]);
        $leaveRes->assertStatus(201);
        $leaveId = $leaveRes->json('data.id');

        // 5. Supervisor Approve Leave
        $approveLeaveRes = $this->putJson("/api/attendance/leaves/{$leaveId}/status", [
            'status' => 'DISETUJUI',
            'approved_by' => 'Manager HRD ILP',
        ]);
        $approveLeaveRes->assertStatus(200);
        $this->assertEquals('DISETUJUI', $approveLeaveRes->json('data.status'));
    }

    /**
     * 5. Test Supervisor Void Authorization & Manual Discount Controls.
     */
    public function test_supervisor_control_features(): void
    {
        // 1. Void Authorization with Valid Supervisor PIN
        $voidAuthRes = $this->postJson('/api/pos/void/authorize', [
            'supervisor_pin' => '123456',
        ]);
        $voidAuthRes->assertStatus(200);
        $voidAuthRes->assertJsonPath('authorized', true);

        // 2. Discount Authorization with Valid Supervisor PIN
        $discAuthRes = $this->postJson('/api/pos/discount/authorize', [
            'supervisor_pin' => '123456',
            'discount_type' => 'PERCENTAGE',
            'discount_value' => 15,
            'reason' => 'Promo VIP Member',
        ]);
        $discAuthRes->assertStatus(200);
        $discAuthRes->assertJsonPath('authorized', true);
    }
}
