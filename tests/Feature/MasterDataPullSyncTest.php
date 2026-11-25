<?php

use App\Models\CashierShift;
use App\Models\Location;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Supplier;
use App\Models\SyncState;
use App\Models\Transaction;
use App\Models\TransactionItem;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpClientRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function () {
    config([
        'pos_sync.node_role' => 'local',
        'pos_sync.central_api_url' => 'https://central.pos-ilp.test',
        'pos_sync.central_api_token' => 'secret-central-sync-token-2026',
        'pos_sync.pull_per_page' => 200,
        'pos_sync.pull_sync_stock' => true,
    ]);
});

test('1. initial & delta sync: central API filters by updated_since and local pull uses server_time cursor in sync_states', function () {
    // Part A: Test Central Server REST API delta filtering (`?updated_since=`)
    $oldCat = ProductCategory::create(['name' => 'Kategori Lama']);
    DB::table('product_categories')->where('id', $oldCat->id)->update([
        'updated_at' => Carbon::parse('2026-11-20T00:00:00Z'),
    ]);

    $newCat = ProductCategory::create(['name' => 'Kategori Baru']);
    DB::table('product_categories')->where('id', $newCat->id)->update([
        'updated_at' => Carbon::parse('2026-11-26T00:00:00Z'),
    ]);

    $centralRes = $this->withToken('secret-central-sync-token-2026')
        ->getJson('/api/v1/sync/master/categories?updated_since=2026-11-25T00:00:00Z&per_page=200');

    $centralRes->assertStatus(200)
        ->assertJsonPath('success', true)
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.uuid', $newCat->uuid)
        ->assertJsonPath('data.0.name', 'Kategori Baru');

    expect($centralRes->json('server_time'))->not->toBeEmpty();

    // Part B: Test Local Pull Client (`pos:pull-master --entity=categories`) initial vs delta cursor
    $serverCursor1 = '2026-11-26T01:00:00+00:00';
    $serverCursor2 = '2026-11-26T01:05:00+00:00';
    $pulledCatUuid = (string) Str::uuid7();

    $callCount = 0;
    $capturedSecondUpdatedSince = null;

    Http::fake(function (HttpClientRequest $request) use (
        &$callCount,
        &$capturedSecondUpdatedSince,
        $serverCursor1,
        $serverCursor2,
        $pulledCatUuid
    ) {
        $callCount++;
        if ($callCount === 1) {
            return Http::response([
                'success' => true,
                'server_time' => $serverCursor1,
                'data' => [
                    [
                        'uuid' => $pulledCatUuid,
                        'name' => 'Minuman Dingin',
                        'created_at' => $serverCursor1,
                        'updated_at' => $serverCursor1,
                    ],
                ],
                'current_page' => 1,
                'last_page' => 1,
                'total' => 1,
            ], 200);
        }

        $capturedSecondUpdatedSince = $request->data()['updated_since'] ?? null;

        return Http::response([
            'success' => true,
            'server_time' => $serverCursor2,
            'data' => [],
            'current_page' => 1,
            'last_page' => 1,
            'total' => 0,
        ], 200);
    });

    // Run 1: Initial pull (no updated_since yet)
    $this->artisan('pos:pull-master', ['--entity' => 'categories'])
        ->assertExitCode(0);

    $stateAfterRun1 = SyncState::where('entity', 'categories')->first();
    expect($stateAfterRun1)->not->toBeNull()
        ->and($stateAfterRun1->last_status)->toBe('success')
        ->and($stateAfterRun1->last_synced_at->copy()->utc()->toIso8601String())->toBe(Carbon::parse($serverCursor1)->utc()->toIso8601String())
        ->and(ProductCategory::where('uuid', $pulledCatUuid)->exists())->toBeTrue();

    // Run 2: Delta pull (must send updated_since = server_time from Run 1)
    $this->artisan('pos:pull-master', ['--entity' => 'categories'])
        ->assertExitCode(0);

    expect($capturedSecondUpdatedSince)->not->toBeNull()
        ->and(Carbon::parse($capturedSecondUpdatedSince)->utc()->toIso8601String())
        ->toBe(Carbon::parse($serverCursor1)->utc()->toIso8601String());
});

test('2. idempotency & dual-key preservation: updating product price from central updates local price without duplicating or changing local integer id', function () {
    $productUuid = (string) Str::uuid7();

    $localProduct = Product::create([
        'uuid' => $productUuid,
        'sku' => 'SKU-DUAL-001',
        'barcode' => '899111222333',
        'name' => 'Beras Ramos 5Kg',
        'price' => 65000,
        'member_price' => 64000,
        'cost_price' => 58000,
        'stock' => 25,
        'unit' => 'Sak',
    ]);

    $originalLocalId = $localProduct->id;
    $initialProductCount = Product::count();

    Http::fake([
        'https://central.pos-ilp.test/api/v1/sync/master/products*' => Http::response([
            'success' => true,
            'server_time' => '2026-11-26T02:00:00Z',
            'data' => [
                [
                    'uuid' => $productUuid,
                    'sku' => 'SKU-DUAL-001',
                    'barcode' => '899111222333',
                    'name' => 'Beras Ramos 5Kg Premium',
                    'price' => 72000,
                    'member_price' => 70500,
                    'cost_price' => 62000,
                    'stock' => 25,
                    'unit' => 'Sak',
                    'status' => 'TERSEDIA',
                ],
            ],
            'current_page' => 1,
            'last_page' => 1,
            'total' => 1,
        ], 200),
    ]);

    $this->artisan('pos:pull-master', ['--entity' => 'products'])
        ->assertExitCode(0);

    $refreshed = Product::where('uuid', $productUuid)->first();

    expect(Product::count())->toBe($initialProductCount)
        ->and($refreshed->id)->toBe($originalLocalId)
        ->and((float) $refreshed->price)->toBe(72000.0)
        ->and((float) $refreshed->harga_jual_umum)->toBe(72000.0)
        ->and((float) $refreshed->member_price)->toBe(70500.0)
        ->and($refreshed->name)->toBe('Beras Ramos 5Kg Premium');
});

test('3. local foreign key resolution: product maps category_uuid and supplier_uuid to local category_id and supplier_id even when local IDs differ from central', function () {
    $categoryUuid = (string) Str::uuid7();
    $supplierUuid = (string) Str::uuid7();
    $productUuid = (string) Str::uuid7();

    // Force non-1 auto-increment local IDs (e.g. 55 and 77) to prove resolution uses UUID -> local ID
    DB::table('product_categories')->insert([
        'id' => 55,
        'uuid' => $categoryUuid,
        'name' => 'Kebutuhan Dapur',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('suppliers')->insert([
        'id' => 77,
        'uuid' => $supplierUuid,
        'code' => 'SUP-DAPUR-77',
        'name' => 'PT Pangan Nusantara',
        'category' => 'Sembako',
        'commission_percent' => 10,
        'settlement_period' => 'BULANAN',
        'status' => 'AKTIF',
        'supplier_type' => 'REGULAR',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    Http::fake([
        'https://central.pos-ilp.test/api/v1/sync/master/products*' => Http::response([
            'success' => true,
            'server_time' => '2026-11-26T02:10:00Z',
            'data' => [
                [
                    'uuid' => $productUuid,
                    'category_uuid' => $categoryUuid,
                    'supplier_uuid' => $supplierUuid,
                    'sku' => 'SKU-FK-001',
                    'barcode' => '899777888999',
                    'name' => 'Kecap Manis 500ml',
                    'price' => 19000,
                    'cost_price' => 15000,
                    'stock' => 40,
                    'unit' => 'Botol',
                ],
            ],
            'current_page' => 1,
            'last_page' => 1,
            'total' => 1,
        ], 200),
    ]);

    $this->artisan('pos:pull-master', ['--entity' => 'products'])
        ->assertExitCode(0);

    $pulledProduct = Product::where('uuid', $productUuid)->first();

    expect($pulledProduct)->not->toBeNull()
        ->and($pulledProduct->category_id)->toBe(55)
        ->and($pulledProduct->category)->toBe('Kebutuhan Dapur')
        ->and($pulledProduct->supplier_id)->toBe(77);
});

test('4. pending local stock protection: central stock 10 minus 3 unsynced local transaction qty results in local stock 7 after pull', function () {
    $productUuid = (string) Str::uuid7();

    $cashier = User::create([
        'name' => 'Kasir Stok Pending',
        'username' => 'kasir_pending',
        'email' => 'kasir_pending@ilp.test',
        'nik' => '20269901',
        'role' => 'Kasir',
        'pin' => '111222',
        'branch' => 'Pusat',
        'password' => bcrypt('password'),
        'is_active' => true,
    ]);

    $product = Product::create([
        'uuid' => $productUuid,
        'sku' => 'SKU-PENDING-STK',
        'barcode' => '899555666777',
        'name' => 'Sarden Kaleng 425g',
        'price' => 22000,
        'cost_price' => 18000,
        'stock' => 7,
        'unit' => 'Kaleng',
    ]);

    $shift = CashierShift::create([
        'shift_code' => 'CS-PENDING-01',
        'terminal_code' => 'POS-01',
        'branch' => 'Pusat',
        'user_id' => $cashier->id,
        'cashier_nik' => $cashier->nik,
        'cashier_name' => $cashier->name,
        'opening_cash' => 100000,
        'status' => 'OPEN',
        'opened_at' => now(),
    ]);

    // Unsynced local transaction (synced_at = null) selling 3 units
    $unsyncedTrx = Transaction::create([
        'invoice_number' => 'TRX-UNSYNCED-001',
        'shift_id' => $shift->id,
        'user_id' => $cashier->id,
        'cashier_name' => $cashier->name,
        'terminal_code' => 'POS-01',
        'branch' => 'Pusat',
        'payment_method' => 'TUNAI',
        'subtotal' => 66000,
        'discount_total' => 0,
        'tax_total' => 0,
        'grand_total' => 66000,
        'cash_received' => 70000,
        'change_returned' => 4000,
        'status' => 'SUCCESS',
        'synced_at' => null,
    ]);

    TransactionItem::create([
        'transaction_id' => $unsyncedTrx->id,
        'product_id' => $product->id,
        'sku' => $product->sku,
        'barcode' => $product->barcode,
        'product_name' => $product->name,
        'unit' => 'Kaleng',
        'unit_price' => 22000,
        'qty' => 3,
        'total_price' => 66000,
    ]);

    // Central Server still thinks stock is 10 (because TRX-UNSYNCED-001 hasn't pushed yet)
    Http::fake([
        'https://central.pos-ilp.test/api/v1/sync/master/products*' => Http::response([
            'success' => true,
            'server_time' => '2026-11-26T02:20:00Z',
            'data' => [
                [
                    'uuid' => $productUuid,
                    'sku' => 'SKU-PENDING-STK',
                    'barcode' => '899555666777',
                    'name' => 'Sarden Kaleng 425g',
                    'price' => 22000,
                    'cost_price' => 18000,
                    'stock' => 10,
                    'qty_produk' => 10,
                    'unit' => 'Kaleng',
                ],
            ],
            'current_page' => 1,
            'last_page' => 1,
            'total' => 1,
        ], 200),
    ]);

    $this->artisan('pos:pull-master', ['--entity' => 'products'])
        ->assertExitCode(0);

    // Local stock MUST be compensated: 10 (central) - 3 (pending local) = 7!
    expect($product->fresh()->stock)->toBe(7)
        ->and((int) $product->fresh()->qty_produk)->toBe(7);
});

test('5. employee login after pull: password hash from central is not double-hashed and passes Hash::check and offline login', function () {
    $employeeUuid = (string) Str::uuid7();
    $plainPassword = 'PasswordKasirOffline2026!';
    $centralBcryptHash = Hash::make($plainPassword);

    Http::fake([
        'https://central.pos-ilp.test/api/v1/sync/master/employees*' => Http::response([
            'success' => true,
            'server_time' => '2026-11-26T02:30:00Z',
            'data' => [
                [
                    'uuid' => $employeeUuid,
                    'name' => 'Siti Aminah',
                    'username' => 'siti_kasir',
                    'email' => 'siti@minimarket.ilp',
                    'nik' => '20268801',
                    'role' => 'Kasir Utama',
                    'pin' => '654321',
                    'branch' => 'Pusat',
                    'is_active' => true,
                    'password' => $centralBcryptHash,
                    'deleted_at' => null,
                ],
            ],
            'current_page' => 1,
            'last_page' => 1,
            'total' => 1,
        ], 200),
    ]);

    $this->artisan('pos:pull-master', ['--entity' => 'employees'])
        ->assertExitCode(0);

    $pulledEmployee = User::where('uuid', $employeeUuid)->first();

    expect($pulledEmployee)->not->toBeNull()
        ->and($pulledEmployee->getRawOriginal('password'))->toBe($centralBcryptHash)
        ->and(Hash::check($plainPassword, $pulledEmployee->password))->toBeTrue();

    // Verify real local login API works with plain password
    $loginRes = $this->postJson('/api/auth/login', [
        'identifier' => 'siti_kasir',
        'password' => $plainPassword,
        'role' => 'kasir',
    ]);

    $loginRes->assertStatus(200)
        ->assertJsonPath('success', true)
        ->assertJsonPath('user.username', 'siti_kasir');
});

test('6. soft delete & deactivation propagation: soft-deleted product and deactivated employee on central are synced to local cashier', function () {
    $productUuid = (string) Str::uuid7();
    $employeeUuid = (string) Str::uuid7();

    $localProduct = Product::create([
        'uuid' => $productUuid,
        'sku' => 'SKU-DEL-01',
        'barcode' => '899444333222',
        'name' => 'Produk Akan Dihapus',
        'price' => 10000,
        'cost_price' => 8000,
        'stock' => 15,
        'unit' => 'Pcs',
    ]);

    $localEmployee = User::create([
        'uuid' => $employeeUuid,
        'name' => 'Karyawan Akan Nonaktif',
        'username' => 'kasir_nonaktif',
        'email' => 'nonaktif@minimarket.ilp',
        'nik' => '20267701',
        'role' => 'Kasir',
        'pin' => '123456',
        'branch' => 'Pusat',
        'password' => bcrypt('password'),
        'is_active' => true,
    ]);

    $deletedTimestamp = '2026-11-26T03:00:00Z';

    Http::fake([
        'https://central.pos-ilp.test/api/v1/sync/master/products*' => Http::response([
            'success' => true,
            'server_time' => $deletedTimestamp,
            'data' => [
                [
                    'uuid' => $productUuid,
                    'sku' => 'SKU-DEL-01',
                    'barcode' => '899444333222',
                    'name' => 'Produk Akan Dihapus',
                    'price' => 10000,
                    'cost_price' => 8000,
                    'stock' => 0,
                    'unit' => 'Pcs',
                    'status' => 'NONAKTIF',
                    'deleted_at' => $deletedTimestamp,
                ],
            ],
            'current_page' => 1,
            'last_page' => 1,
            'total' => 1,
        ], 200),
        'https://central.pos-ilp.test/api/v1/sync/master/employees*' => Http::response([
            'success' => true,
            'server_time' => $deletedTimestamp,
            'data' => [
                [
                    'uuid' => $employeeUuid,
                    'name' => 'Karyawan Akan Nonaktif',
                    'username' => 'kasir_nonaktif',
                    'email' => 'nonaktif@minimarket.ilp',
                    'nik' => '20267701',
                    'role' => 'Kasir',
                    'pin' => '123456',
                    'branch' => 'Pusat',
                    'is_active' => false,
                    'password' => $localEmployee->getRawOriginal('password'),
                    'deleted_at' => $deletedTimestamp,
                ],
            ],
            'current_page' => 1,
            'last_page' => 1,
            'total' => 1,
        ], 200),
    ]);

    $this->artisan('pos:pull-master', ['--entity' => 'products'])->assertExitCode(0);
    $this->artisan('pos:pull-master', ['--entity' => 'employees'])->assertExitCode(0);

    // Product is now soft-deleted locally (hidden from normal queries, visible via withTrashed())
    expect(Product::find($localProduct->id))->toBeNull()
        ->and(Product::withTrashed()->find($localProduct->id)->trashed())->toBeTrue();

    // Employee is now deactivated and has deleted_at populated locally
    $updatedEmp = User::find($localEmployee->id);
    expect($updatedEmp->is_active)->toBeFalse()
        ->and($updatedEmp->deleted_at)->not->toBeNull();
});
