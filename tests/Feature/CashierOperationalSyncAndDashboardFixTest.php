<?php

use App\Models\Attendance;
use App\Models\AuditLog;
use App\Models\CashierShift;
use App\Models\GoodsReceipt;
use App\Models\Location;
use App\Models\PosTerminal;
use App\Models\Product;
use App\Models\ProductStock;
use App\Models\User;
use App\Services\PosPullSyncService;
use App\Services\PosPushSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

test('cashier portal login records attendance, audit log, and surfaces on manager and owner dashboards', function () {
    $tokoPusat = Location::create([
        'code' => 'CAB-001',
        'name' => 'Toko Pusat',
        'type' => 'STORE',
        'status' => 'AKTIF',
        'is_active' => true,
    ]);

    $cashier = User::create([
        'name' => 'Kasir Satu',
        'username' => 'kasir_satu',
        'nik' => '20260001',
        'email' => 'kasir1@ilp.test',
        'password' => 'Password123!',
        'pin' => '111222',
        'role' => 'Kasir Utama',
        'branch' => 'Pusat',
        'location_id' => $tokoPusat->id,
        'is_active' => true,
    ]);

    $manager = User::create([
        'name' => 'Manager Satu',
        'username' => 'manager_satu',
        'nik' => '20260002',
        'email' => 'manager1@ilp.test',
        'password' => 'Password123!',
        'role' => 'Manager',
        'branch' => 'Pusat',
        'is_active' => true,
    ]);

    $owner = User::create([
        'name' => 'Owner Satu',
        'username' => 'owner_satu',
        'nik' => '20260003',
        'email' => 'owner1@ilp.test',
        'password' => 'Password123!',
        'role' => 'Owner',
        'branch' => 'Pusat',
        'is_active' => true,
    ]);

    // Cashier logs in via portal (even before opening a cash drawer shift)
    $loginRes = $this->postJson('/api/auth/login', [
        'role' => 'kasir',
        'identifier' => 'kasir_satu',
        'password' => 'Password123!',
    ]);

    $loginRes->assertOk()->assertJsonPath('success', true);

    // 1. Attendance & AuditLog immediately recorded
    $attendance = Attendance::where('user_id', $cashier->id)->first();
    expect($attendance)->not->toBeNull()
        ->and($attendance->status)->toBe('HADIR')
        ->and($attendance->uuid)->not->toBeEmpty()
        ->and($attendance->synced_at)->toBeNull();

    $audit = AuditLog::where('user_id', $cashier->id)->latest('id')->first();
    expect($audit)->not->toBeNull()
        ->and($audit->uuid)->not->toBeEmpty()
        ->and($audit->synced_at)->toBeNull();

    // 2. Manager Dashboard immediately sees active cashier
    $mgrRes = $this->actingAs($manager)->getJson('/api/manager/dashboard-summary');
    $mgrRes->assertOk()->assertJsonPath('success', true);
    $mgrNames = collect($mgrRes->json('data.cashier_list'))->pluck('name')->all();
    expect($mgrNames)->toContain('Kasir Satu');

    // 3. Owner Dashboard immediately sees active cashier
    $ownRes = $this->actingAs($owner)->getJson('/api/owner/dashboard-summary');
    $ownRes->assertOk()->assertJsonPath('success', true);
    expect($ownRes->json('data.active_cashiers_count'))->toBeGreaterThanOrEqual(1);
    $ownNames = collect($ownRes->json('data.active_cashiers'))->pluck('name')->all();
    expect($ownNames)->toContain('Kasir Satu');
});

test('operational push sync sends standalone shifts, attendances, and audit logs to central endpoint', function () {
    $tokoPusat = Location::create([
        'code' => 'CAB-001',
        'name' => 'Toko Pusat',
        'type' => 'STORE',
        'status' => 'AKTIF',
        'is_active' => true,
    ]);

    $plainToken = 'central_pos01_sync_token_test';
    PosTerminal::create([
        'branch_id' => $tokoPusat->id,
        'terminal_code' => 'POS-01',
        'name' => 'Terminal POS-01 Toko Pusat',
        'api_token_hash' => PosTerminal::hashToken($plainToken),
        'is_active' => true,
    ]);

    $cashier = User::create([
        'name' => 'Kasir Sync',
        'username' => 'kasir_sync',
        'nik' => '20260099',
        'email' => 'kasirsync@ilp.test',
        'password' => 'Password123!',
        'role' => 'Kasir Utama',
        'branch' => 'Pusat',
        'location_id' => $tokoPusat->id,
        'is_active' => true,
    ]);

    $shiftUuid = (string) Str::uuid7();
    $attUuid = (string) Str::uuid7();
    $auditUuid = (string) Str::uuid7();

    $centralRes = $this->withHeader('Authorization', 'Bearer ' . $plainToken)
        ->postJson('/api/v1/sync/operational', [
            'shifts' => [[
                'uuid' => $shiftUuid,
                'shift_code' => 'CS-20261126-POS01-777',
                'terminal_code' => 'POS-01',
                'employee_uuid' => $cashier->uuid,
                'cashier_nik' => $cashier->nik,
                'cashier_name' => $cashier->name,
                'status' => 'OPEN',
                'opening_cash' => 500000,
                'opened_at' => now()->toIso8601String(),
            ]],
            'attendances' => [[
                'uuid' => $attUuid,
                'employee_uuid' => $cashier->uuid,
                'staff_name' => $cashier->name,
                'date' => now()->toDateString(),
                'terminal' => 'POS-01',
                'check_in' => '08:00',
                'check_out' => '-',
                'status' => 'HADIR',
            ]],
            'audit_logs' => [[
                'uuid' => $auditUuid,
                'employee_uuid' => $cashier->uuid,
                'user_name' => $cashier->name,
                'user_role' => 'Kasir Utama',
                'category' => 'KEAMANAN',
                'action_name' => 'Login Portal Kasir',
                'description' => 'Kasir Sync login ke terminal POS-01',
                'created_at' => now()->toIso8601String(),
            ]],
            'goods_receipts' => [],
        ]);

    $centralRes->assertOk()->assertJsonPath('success', true);
    expect($centralRes->json('results.shifts.0.uuid'))->toBe($shiftUuid)
        ->and($centralRes->json('results.shifts.0.success'))->toBeTrue();

    expect(CashierShift::where('uuid', $shiftUuid)->where('location_id', $tokoPusat->id)->exists())->toBeTrue()
        ->and(Attendance::where('uuid', $attUuid)->exists())->toBeTrue()
        ->and(AuditLog::where('uuid', $auditUuid)->exists())->toBeTrue();

    // Also test PosPushSyncService client marking synced_at when Central acknowledges
    $localShift = CashierShift::where('uuid', $shiftUuid)->first();
    $localShift->forceFill(['synced_at' => null])->save();
    $localAtt = Attendance::where('uuid', $attUuid)->first();
    $localAtt->forceFill(['synced_at' => null])->save();
    $localAudit = AuditLog::where('uuid', $auditUuid)->first();
    $localAudit->forceFill(['synced_at' => null])->save();

    config([
        'pos_sync.node_role' => 'local',
        'pos_sync.central_api_url' => 'https://central.pos-ilp.test',
        'pos_sync.central_api_token' => $plainToken,
    ]);

    Http::fake([
        'https://central.pos-ilp.test/api/v1/sync/operational' => Http::response([
            'success' => true,
            'results' => [
                'shifts' => [['uuid' => $shiftUuid, 'success' => true, 'status' => 'synced']],
                'attendances' => [['uuid' => $attUuid, 'success' => true, 'status' => 'synced']],
                'audit_logs' => [['uuid' => $auditUuid, 'success' => true, 'status' => 'synced']],
                'goods_receipts' => [],
            ],
        ], 200),
    ]);

    $pushResult = app(PosPushSyncService::class)->sync();
    expect($pushResult['failed_count'])->toBe(0)
        ->and($localShift->fresh()->synced_at)->not->toBeNull()
        ->and($localAtt->fresh()->synced_at)->not->toBeNull()
        ->and($localAudit->fresh()->synced_at)->not->toBeNull();
});

test('pos product search prioritizes in-stock items and goods receipts pull sync works', function () {
    $warehouse = Location::create([
        'code' => 'GDG-001',
        'name' => 'Gudang Pusat',
        'type' => 'WAREHOUSE',
        'status' => 'AKTIF',
        'is_active' => true,
    ]);

    $tokoPusat = Location::create([
        'code' => 'CAB-001',
        'name' => 'Toko Pusat',
        'type' => 'STORE',
        'status' => 'AKTIF',
        'is_active' => true,
    ]);

    $prodZero = Product::create([
        'sku' => 'SKU-AAA-ZERO',
        'barcode' => '8990000000011',
        'name' => 'AAA Produk Stok Kosong',
        'category' => 'Sembako',
        'unit' => 'Pcs',
        'cost_price' => 5000,
        'price' => 7000,
        'member_price' => 6800,
        'stock' => 0,
        'min_stock' => 5,
    ]);

    $prodReady = Product::create([
        'sku' => 'SKU-ZZZ-READY',
        'barcode' => '8990000000028',
        'name' => 'ZZZ Produk Stok Tersedia',
        'category' => 'Sembako',
        'unit' => 'Pcs',
        'cost_price' => 8000,
        'price' => 10000,
        'member_price' => 9500,
        'stock' => 25,
        'min_stock' => 5,
    ]);

    ProductStock::create([
        'product_id' => $prodZero->id,
        'location_id' => $tokoPusat->id,
        'stock' => 0,
        'min_stock' => 5,
        'is_active' => true,
    ]);

    ProductStock::create([
        'product_id' => $prodReady->id,
        'location_id' => $tokoPusat->id,
        'stock' => 25,
        'min_stock' => 5,
        'is_active' => true,
    ]);

    // ZZZ Produk Stok Tersedia (stock=25) must appear BEFORE AAA Produk Stok Kosong (stock=0)
    $searchRes = $this->getJson('/api/pos/products/search?branch=Toko+Pusat&terminal_code=POS-01&limit=100');
    $searchRes->assertOk()->assertJsonPath('success', true);
    expect($searchRes->json('data.0.sku'))->toBe('SKU-ZZZ-READY')
        ->and($searchRes->json('data.1.sku'))->toBe('SKU-AAA-ZERO');

    // Test pulling GoodsReceipt from Central Server via PosPullSyncService
    $grUuid = (string) Str::uuid7();
    $griUuid = (string) Str::uuid7();

    config([
        'pos_sync.node_role' => 'local',
        'pos_sync.central_api_url' => 'https://central.pos-ilp.test',
        'pos_sync.central_api_token' => 'test_pull_token',
    ]);

    Http::fake([
        'https://central.pos-ilp.test/api/v1/sync/master/goods-receipts*' => Http::response([
            'server_time' => now()->utc()->toIso8601String(),
            'data' => [[
                'uuid' => $grUuid,
                'receipt_number' => 'SJ-20261126-0001',
                'origin_source' => 'Gudang Pusat',
                'from_location_uuid' => $warehouse->uuid,
                'to_location_uuid' => $tokoPusat->uuid,
                'destination_store' => 'Toko Pusat',
                'status' => 'BELUM_SAMPAI',
                'notes' => 'Distribusi perdana ke Toko Pusat',
                'items' => [[
                    'uuid' => $griUuid,
                    'product_uuid' => $prodZero->uuid,
                    'qty_sent' => 30,
                    'qty_received' => 0,
                    'actual_unit_cost' => 5000,
                    'condition' => 'BAIK',
                ]],
            ]],
            'current_page' => 1,
            'last_page' => 1,
            'total' => 1,
        ], 200),
    ]);

    $pullSummary = app(PosPullSyncService::class)->pull('goods_receipts', true);
    expect($pullSummary['status'])->toBe('success');

    $pulledGr = GoodsReceipt::where('uuid', $grUuid)->with('items')->first();
    expect($pulledGr)->not->toBeNull()
        ->and($pulledGr->receipt_number)->toBe('SJ-20261126-0001')
        ->and($pulledGr->to_location_id)->toBe($tokoPusat->id)
        ->and($pulledGr->items)->toHaveCount(1)
        ->and($pulledGr->items->first()->product_id)->toBe($prodZero->id);
});
