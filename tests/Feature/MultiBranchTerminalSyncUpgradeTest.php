<?php

use App\Models\CashierShift;
use App\Models\Location;
use App\Models\PosTerminal;
use App\Models\Product;
use App\Models\ProductStock;
use App\Models\Transaction;
use App\Models\User;
use App\Services\PosInvoiceGeneratorService;
use App\Services\PosPullSyncService;
use App\Services\PosPushSyncService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

/**
 * Helper to provision the 4 production ILP facilities + 4 POS Cashier Terminals.
 */
function seedProductionMultiBranchTopology(): array
{
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

    $cabang1 = Location::create([
        'code' => 'CAB-002',
        'name' => 'Cabang 1',
        'type' => 'STORE',
        'status' => 'AKTIF',
        'is_active' => true,
    ]);

    $cabang2 = Location::create([
        'code' => 'CAB-003',
        'name' => 'Cabang 2',
        'type' => 'STORE',
        'status' => 'AKTIF',
        'is_active' => true,
    ]);

    $tokens = [
        'pst_ksr01' => 'token_pst_ksr01_plain_secret',
        'pst_ksr02' => 'token_pst_ksr02_plain_secret',
        'cbg1_ksr01' => 'token_cbg1_ksr01_plain_secret',
        'cbg2_ksr01' => 'token_cbg2_ksr01_plain_secret',
    ];

    $terminals = [
        'pst_ksr01' => PosTerminal::create([
            'branch_id' => $tokoPusat->id,
            'terminal_code' => 'KSR01',
            'name' => 'Kasir 1 Toko Pusat',
            'api_token_hash' => PosTerminal::hashToken($tokens['pst_ksr01']),
            'is_active' => true,
        ]),
        'pst_ksr02' => PosTerminal::create([
            'branch_id' => $tokoPusat->id,
            'terminal_code' => 'KSR02',
            'name' => 'Kasir 2 Toko Pusat',
            'api_token_hash' => PosTerminal::hashToken($tokens['pst_ksr02']),
            'is_active' => true,
        ]),
        'cbg1_ksr01' => PosTerminal::create([
            'branch_id' => $cabang1->id,
            'terminal_code' => 'KSR01',
            'name' => 'Kasir 1 Cabang 1',
            'api_token_hash' => PosTerminal::hashToken($tokens['cbg1_ksr01']),
            'is_active' => true,
        ]),
        'cbg2_ksr01' => PosTerminal::create([
            'branch_id' => $cabang2->id,
            'terminal_code' => 'KSR01',
            'name' => 'Kasir 1 Cabang 2',
            'api_token_hash' => PosTerminal::hashToken($tokens['cbg2_ksr01']),
            'is_active' => true,
        ]),
    ];

    return compact('warehouse', 'tokoPusat', 'cabang1', 'cabang2', 'tokens', 'terminals');
}

test('1. push sync from PST-KSR01 and PST-KSR02 atomically deducts shared Toko Pusat stock without altering Cabang 1, Cabang 2, or Gudang stock, enforcing token authority', function () {
    $topo = seedProductionMultiBranchTopology();

    $cashier1 = User::create([
        'name' => 'Kasir PST 1',
        'username' => 'kasir_pst_1',
        'email' => 'kasir_pst_1@ilp.test',
        'nik' => 'NIK-PST-01',
        'role' => 'Kasir',
        'pin' => '123456',
        'branch' => 'Toko Pusat',
        'location_id' => $topo['tokoPusat']->id,
        'password' => bcrypt('password'),
        'is_active' => true,
    ]);

    $cashier2 = User::create([
        'name' => 'Kasir PST 2',
        'username' => 'kasir_pst_2',
        'email' => 'kasir_pst_2@ilp.test',
        'nik' => 'NIK-PST-02',
        'role' => 'Kasir',
        'pin' => '123456',
        'branch' => 'Toko Pusat',
        'location_id' => $topo['tokoPusat']->id,
        'password' => bcrypt('password'),
        'is_active' => true,
    ]);

    $product = Product::create([
        'sku' => 'SKU-MULTI-001',
        'barcode' => '8990011223344',
        'name' => 'Minyak Goreng Sawit 2L',
        'price' => 36000,
        'cost_price' => 31000,
        'stock' => 205, // 100 + 50 + 30 + 25
        'qty_produk' => 205,
        'unit' => 'Pouch',
    ]);

    // Seed isolated stocks across all 4 locations
    ProductStock::create(['product_id' => $product->id, 'location_id' => $topo['warehouse']->id, 'stock' => 100, 'is_active' => true]);
    ProductStock::create(['product_id' => $product->id, 'location_id' => $topo['tokoPusat']->id, 'stock' => 50, 'is_active' => true]);
    ProductStock::create(['product_id' => $product->id, 'location_id' => $topo['cabang1']->id, 'stock' => 30, 'is_active' => true]);
    ProductStock::create(['product_id' => $product->id, 'location_id' => $topo['cabang2']->id, 'stock' => 25, 'is_active' => true]);

    $trxUuid1 = (string) Str::uuid7();
    $trxUuid2 = (string) Str::uuid7();

    // Push 1: PST-KSR01 sells 3 units (even spoofing Cabang 2 in JSON payload — server MUST enforce Toko Pusat from token!)
    $res1 = $this->withToken($topo['tokens']['pst_ksr01'])
        ->postJson('/api/v1/sync/transactions', [
            'transactions' => [
                [
                    'transaction_uuid' => $trxUuid1,
                    'invoice_number' => 'INV-PST-KSR01-20261126-0001',
                    'employee_uuid' => $cashier1->uuid,
                    'location_uuid' => $topo['cabang2']->uuid, // Spoof attempt!
                    'branch' => 'Cabang 2',                     // Spoof attempt!
                    'terminal_code' => 'FAKE99',                // Spoof attempt!
                    'payment_method' => 'TUNAI',
                    'grand_total' => 108000,
                    'items' => [
                        [
                            'product_uuid' => $product->uuid,
                            'qty' => 3,
                            'unit_price' => 36000,
                            'total_price' => 108000,
                        ],
                    ],
                ],
            ],
        ]);

    $res1->assertStatus(200)
        ->assertJsonPath('summary.synced', 1)
        ->assertJsonPath('results.0.branch_id', $topo['tokoPusat']->id)
        ->assertJsonPath('results.0.terminal_code', 'KSR01');

    // Push 2: PST-KSR02 sells 4 units
    $res2 = $this->withToken($topo['tokens']['pst_ksr02'])
        ->postJson('/api/v1/sync/transactions', [
            'transactions' => [
                [
                    'transaction_uuid' => $trxUuid2,
                    'invoice_number' => 'INV-PST-KSR02-20261126-0001',
                    'employee_uuid' => $cashier2->uuid,
                    'payment_method' => 'QRIS',
                    'grand_total' => 144000,
                    'items' => [
                        [
                            'product_uuid' => $product->uuid,
                            'qty' => 4,
                            'unit_price' => 36000,
                            'total_price' => 144000,
                        ],
                    ],
                ],
            ],
        ]);

    $res2->assertStatus(200)
        ->assertJsonPath('summary.synced', 1)
        ->assertJsonPath('results.0.branch_id', $topo['tokoPusat']->id)
        ->assertJsonPath('results.0.terminal_code', 'KSR02');

    // Verify authoritative branch & terminal stored on central transactions
    $centralTrx1 = Transaction::where('uuid', $trxUuid1)->first();
    $centralTrx2 = Transaction::where('uuid', $trxUuid2)->first();

    expect($centralTrx1->location_id)->toBe($topo['tokoPusat']->id)
        ->and($centralTrx1->branch_id)->toBe($topo['tokoPusat']->id)
        ->and($centralTrx1->terminal_code)->toBe('KSR01')
        ->and($centralTrx2->location_id)->toBe($topo['tokoPusat']->id)
        ->and($centralTrx2->terminal_code)->toBe('KSR02');

    // Verify Toko Pusat stock = 50 - 3 - 4 = 43, while Cabang 1 (30), Cabang 2 (25), and Gudang (100) are UNTOUCHED!
    $pstStock = ProductStock::where('product_id', $product->id)->where('location_id', $topo['tokoPusat']->id)->value('stock');
    $cbg1Stock = ProductStock::where('product_id', $product->id)->where('location_id', $topo['cabang1']->id)->value('stock');
    $cbg2Stock = ProductStock::where('product_id', $product->id)->where('location_id', $topo['cabang2']->id)->value('stock');
    $whStock = ProductStock::where('product_id', $product->id)->where('location_id', $topo['warehouse']->id)->value('stock');

    expect($pstStock)->toBe(43)
        ->and($cbg1Stock)->toBe(30)
        ->and($cbg2Stock)->toBe(25)
        ->and($whStock)->toBe(100)
        ->and($product->fresh()->stock)->toBe(198); // 100 + 43 + 30 + 25

    // Verify terminal heartbeat timestamps
    expect($topo['terminals']['pst_ksr01']->fresh()->last_seen_at)->not->toBeNull()
        ->and($topo['terminals']['pst_ksr01']->fresh()->last_push_at)->not->toBeNull()
        ->and($topo['terminals']['pst_ksr02']->fresh()->last_push_at)->not->toBeNull();
});

test('2. pull master data by CBG1-KSR01 strictly receives only Cabang 1 stock, location, and authorized employees', function () {
    $topo = seedProductionMultiBranchTopology();

    $product = Product::create([
        'sku' => 'SKU-PULL-CBG1',
        'barcode' => '8999988776655',
        'name' => 'Gula Kristal Putih 1Kg',
        'price' => 17500,
        'cost_price' => 14500,
        'stock' => 205,
        'unit' => 'Bks',
    ]);

    ProductStock::create(['product_id' => $product->id, 'location_id' => $topo['warehouse']->id, 'stock' => 100, 'is_active' => true]);
    ProductStock::create(['product_id' => $product->id, 'location_id' => $topo['tokoPusat']->id, 'stock' => 50, 'is_active' => true]);
    ProductStock::create(['product_id' => $product->id, 'location_id' => $topo['cabang1']->id, 'stock' => 30, 'is_active' => true]);
    ProductStock::create(['product_id' => $product->id, 'location_id' => $topo['cabang2']->id, 'stock' => 25, 'is_active' => true]);

    $empCbg1 = User::create([
        'name' => 'Kasir Cabang 1',
        'username' => 'kasir_cbg1',
        'email' => 'cbg1@ilp.test',
        'nik' => 'EMP-CBG1',
        'role' => 'Kasir',
        'pin' => '111111',
        'branch' => 'Cabang 1',
        'location_id' => $topo['cabang1']->id,
        'password' => bcrypt('password'),
        'is_active' => true,
    ]);

    $empCbg2 = User::create([
        'name' => 'Kasir Cabang 2',
        'username' => 'kasir_cbg2',
        'email' => 'cbg2@ilp.test',
        'nik' => 'EMP-CBG2',
        'role' => 'Kasir',
        'pin' => '222222',
        'branch' => 'Cabang 2',
        'location_id' => $topo['cabang2']->id,
        'password' => bcrypt('password'),
        'is_active' => true,
    ]);

    $manager = User::create([
        'name' => 'Manager Pusat',
        'username' => 'mgr_pusat',
        'email' => 'mgr@ilp.test',
        'nik' => 'EMP-MGR',
        'role' => 'Manager',
        'pin' => '999999',
        'branch' => 'Pusat',
        'password' => bcrypt('password'),
        'is_active' => true,
    ]);

    // 1. Pull Products as CBG1-KSR01
    $prodRes = $this->withToken($topo['tokens']['cbg1_ksr01'])
        ->getJson('/api/v1/sync/master/products?per_page=100');

    $prodRes->assertStatus(200)
        ->assertJsonPath('branch.id', $topo['cabang1']->id)
        ->assertJsonPath('terminal_code', 'KSR01')
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.uuid', $product->uuid)
        ->assertJsonPath('data.0.stock', 30)
        ->assertJsonPath('data.0.qty_produk', 30)
        ->assertJsonCount(1, 'data.0.location_stocks')
        ->assertJsonPath('data.0.location_stocks.0.location_uuid', $topo['cabang1']->uuid)
        ->assertJsonPath('data.0.location_stocks.0.stock', 30);

    // 2. Pull Locations as CBG1-KSR01 -> strictly only Cabang 1
    $locRes = $this->withToken($topo['tokens']['cbg1_ksr01'])
        ->getJson('/api/v1/sync/master/locations');

    $locRes->assertStatus(200)
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.uuid', $topo['cabang1']->uuid);

    // 3. Pull Employees as CBG1-KSR01 -> includes Cabang 1 cashier + Manager, excludes Cabang 2 cashier
    $empRes = $this->withToken($topo['tokens']['cbg1_ksr01'])
        ->getJson('/api/v1/sync/master/employees');

    $empRes->assertStatus(200);
    $returnedUuids = array_column($empRes->json('data'), 'uuid');

    expect($returnedUuids)->toContain($empCbg1->uuid)
        ->and($returnedUuids)->toContain($manager->uuid)
        ->and($returnedUuids)->not->toContain($empCbg2->uuid)
        ->and($topo['terminals']['cbg1_ksr01']->fresh()->last_pull_at)->not->toBeNull();
});

test('3. invoice generator produces collision-free distinct prefixes across PST-KSR01, PST-KSR02, CBG1-KSR01, and CBG2-KSR01 and local checkout tolerates negative stock', function () {
    $topo = seedProductionMultiBranchTopology();
    $invoiceService = app(PosInvoiceGeneratorService::class);
    $fixedDate = Carbon::parse('2026-11-26');

    $nodes = [
        ['branch_code' => 'PST', 'terminal_code' => 'KSR01', 'loc' => $topo['tokoPusat']],
        ['branch_code' => 'PST', 'terminal_code' => 'KSR02', 'loc' => $topo['tokoPusat']],
        ['branch_code' => 'CBG1', 'terminal_code' => 'KSR01', 'loc' => $topo['cabang1']],
        ['branch_code' => 'CBG2', 'terminal_code' => 'KSR01', 'loc' => $topo['cabang2']],
    ];

    $generated = [];
    foreach ($nodes as $node) {
        config([
            'pos_sync.node_role' => 'local',
            'pos_sync.branch_uuid' => $node['loc']->uuid,
            'pos_sync.branch_code' => $node['branch_code'],
            'pos_sync.terminal_code' => $node['terminal_code'],
        ]);

        $inv = $invoiceService->generate($node['loc'], $node['terminal_code'], $fixedDate);
        expect($inv)->toBe("INV-{$node['branch_code']}-{$node['terminal_code']}-20261126-0001");
        $generated[] = $inv;
    }

    expect(count(array_unique($generated)))->toBe(4);

    // End-to-end local checkout on PST-KSR02 when local stock is 0 (sync lag with PST-KSR01)
    config([
        'pos_sync.node_role' => 'local',
        'pos_sync.branch_uuid' => $topo['tokoPusat']->uuid,
        'pos_sync.branch_code' => 'PST',
        'pos_sync.terminal_code' => 'KSR02',
        'pos_sync.allow_negative_local_stock' => true,
    ]);

    $cashier = User::create([
        'name' => 'Kasir Lokal PST 2',
        'username' => 'kasir_lokal_pst2',
        'email' => 'lokal_pst2@ilp.test',
        'nik' => 'LOKAL-PST-02',
        'role' => 'Kasir',
        'pin' => '123456',
        'branch' => 'Toko Pusat',
        'location_id' => $topo['tokoPusat']->id,
        'password' => bcrypt('password'),
        'is_active' => true,
    ]);

    $product = Product::create([
        'sku' => 'SKU-LAG-01',
        'barcode' => '8991234000011',
        'name' => 'Air Mineral 600ml',
        'price' => 4000,
        'cost_price' => 2500,
        'stock' => 0,
        'unit' => 'Botol',
    ]);

    ProductStock::create([
        'product_id' => $product->id,
        'location_id' => $topo['tokoPusat']->id,
        'stock' => 0, // Temporarily 0 locally due to sync lag
        'is_active' => true,
    ]);

    $shift = CashierShift::create([
        'shift_code' => 'CS-PST-KSR02-01',
        'terminal_code' => 'KSR02',
        'location_id' => $topo['tokoPusat']->id,
        'branch' => 'Toko Pusat',
        'user_id' => $cashier->id,
        'cashier_nik' => $cashier->nik,
        'cashier_name' => $cashier->name,
        'opening_cash' => 100000,
        'status' => 'OPEN',
        'opened_at' => now(),
    ]);

    $todayStr = date('Ymd');
    $checkoutRes = $this->actingAs($cashier)->postJson('/api/pos/checkout', [
        'shift_id' => $shift->id,
        'payment_method' => 'TUNAI',
        'cash_received' => 10000,
        'items' => [
            [
                'sku' => $product->sku,
                'barcode' => $product->barcode,
                'name' => $product->name,
                'qty' => 2,
                'price' => 4000,
            ],
        ],
    ]);

    $checkoutRes->assertStatus(201)
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.invoice_number', "INV-PST-KSR02-{$todayStr}-0001")
        ->assertJsonPath('data.terminal_code', 'KSR02');

    $savedLocalTrx = Transaction::where('invoice_number', "INV-PST-KSR02-{$todayStr}-0001")->first();
    $localBranchStock = ProductStock::where('product_id', $product->id)->where('location_id', $topo['tokoPusat']->id)->value('stock');

    expect($savedLocalTrx)->not->toBeNull()
        ->and($savedLocalTrx->location_id)->toBe($topo['tokoPusat']->id)
        ->and($savedLocalTrx->terminal_code)->toBe('KSR02')
        ->and($localBranchStock)->toBe(-2);
});

test('4. invalid or deactivated PosTerminal token is rejected with 401, pos:create-terminal works, and Cache::lock prevents concurrent execution', function () {
    $topo = seedProductionMultiBranchTopology();

    // A. Invalid token -> 401
    $this->withToken('completely_wrong_token_12345')
        ->getJson('/api/v1/sync/master/categories')
        ->assertStatus(401);

    // B. Missing token -> 401
    $this->getJson('/api/v1/sync/master/products')
        ->assertStatus(401);

    // C. Deactivated terminal (`is_active = false`) -> 401
    $topo['terminals']['cbg2_ksr01']->update(['is_active' => false]);

    $this->withToken($topo['tokens']['cbg2_ksr01'])
        ->postJson('/api/v1/sync/transactions', ['transactions' => []])
        ->assertStatus(401);

    $this->withToken($topo['tokens']['cbg2_ksr01'])
        ->getJson('/api/v1/sync/master/products')
        ->assertStatus(401);

    // D. CLI command `pos:create-terminal` registers new terminal & rejects WAREHOUSE
    $this->artisan('pos:create-terminal', [
        '--branch' => 'GDG-001',
        '--code' => 'KSR99',
    ])->assertExitCode(1);

    $this->artisan('pos:create-terminal', [
        '--branch' => 'PST',
        '--code' => 'KSR03',
        '--name' => 'Kasir Cadangan Toko Pusat',
    ])->assertExitCode(0);

    $createdTerm = PosTerminal::where('branch_id', $topo['tokoPusat']->id)
        ->where('terminal_code', 'KSR03')
        ->first();
    expect($createdTerm)->not->toBeNull()
        ->and(Str::isUuid($createdTerm->uuid))->toBeTrue()
        ->and(strlen($createdTerm->api_token_hash))->toBe(64);

    // E. Service Cache::lock prevents overlapping Push & Pull runs
    config([
        'pos_sync.node_role' => 'local',
        'pos_sync.central_api_url' => 'https://central.pos-ilp.test',
        'pos_sync.central_api_token' => $topo['tokens']['pst_ksr01'],
    ]);

    $pushLock = Cache::lock(PosPushSyncService::LOCK_KEY, 60);
    expect($pushLock->get())->toBeTrue();
    $pushLockedResult = app(PosPushSyncService::class)->sync();
    expect($pushLockedResult['status'])->toBe('locked');
    $pushLock->release();

    $pullLock = Cache::lock(PosPullSyncService::LOCK_KEY, 60);
    expect($pullLock->get())->toBeTrue();
    $pullLockedResult = app(PosPullSyncService::class)->pull();
    expect($pullLockedResult['status'])->toBe('locked');
    $pullLock->release();
});
