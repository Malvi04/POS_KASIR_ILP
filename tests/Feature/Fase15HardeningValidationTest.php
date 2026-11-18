<?php

use App\Models\CashMovement;
use App\Models\CashierShift;
use App\Models\GoodsReceipt;
use App\Models\Location;
use App\Models\Product;
use App\Models\ProductStock;
use App\Models\StockBatch;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\Transaction;
use App\Models\User;
use App\Services\LocationStockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->gudang = Location::create([
        'code' => 'GDG-001',
        'name' => 'Gudang Pusat',
        'type' => 'WAREHOUSE',
        'status' => 'AKTIF',
        'is_active' => true,
    ]);
    $this->tokoPusat = Location::create([
        'code' => 'CAB-001',
        'name' => 'Toko Pusat',
        'type' => 'STORE',
        'status' => 'AKTIF',
        'is_active' => true,
    ]);
    $this->cabangA = Location::create([
        'code' => 'CAB-002',
        'name' => 'Cabang A',
        'type' => 'STORE',
        'status' => 'AKTIF',
        'is_active' => true,
    ]);
    $this->cabangB = Location::create([
        'code' => 'CAB-003',
        'name' => 'Cabang B',
        'type' => 'STORE',
        'status' => 'AKTIF',
        'is_active' => true,
    ]);

    $this->manager = User::create([
        'nik' => 'MGR-HARD-01',
        'name' => 'Manager Hardening',
        'username' => 'mgr_hard',
        'email' => 'mgr.hard@test.local',
        'password' => Hash::make('password123'),
        'pin' => '123456',
        'role' => 'Manager',
        'branch' => 'Gudang Pusat',
        'is_active' => true,
    ]);

    $this->kasirA1 = User::create([
        'nik' => 'KSR-A1-01',
        'name' => 'Kasir Cabang A 1',
        'username' => 'ksr_a1',
        'email' => 'ksr.a1@test.local',
        'password' => Hash::make('password123'),
        'pin' => '111111',
        'role' => 'Kasir',
        'branch' => 'Cabang A',
        'is_active' => true,
    ]);

    $this->kasirA2 = User::create([
        'nik' => 'KSR-A2-02',
        'name' => 'Kasir Cabang A 2',
        'username' => 'ksr_a2',
        'email' => 'ksr.a2@test.local',
        'password' => Hash::make('password123'),
        'pin' => '222222',
        'role' => 'Kasir',
        'branch' => 'Cabang A',
        'is_active' => true,
    ]);

    $this->kasirB = User::create([
        'nik' => 'KSR-B1-01',
        'name' => 'Kasir Cabang B 1',
        'username' => 'ksr_b1',
        'email' => 'ksr.b1@test.local',
        'password' => Hash::make('password123'),
        'pin' => '333333',
        'role' => 'Kasir',
        'branch' => 'Cabang B',
        'is_active' => true,
    ]);

    $this->supplier = Supplier::create([
        'code' => 'SUP-HARD-01',
        'name' => 'Supplier Hardening',
        'status' => 'AKTIF',
    ]);
});

test('fifo non-expired batches deduct oldest batch first per store location and double receive is blocked', function () {
    $product = Product::create([
        'sku' => 'FIFO-001',
        'barcode' => '899000111001',
        'name' => 'Gula Pasir Non-Expired',
        'category' => 'Sembako',
        'unit' => 'Pcs',
        'price' => 15000,
        'cost_price' => 12000,
        'stock' => 0,
        'min_stock' => 5,
        'status' => 'HABIS',
    ]);

    /** @var LocationStockService $svc */
    $svc = app(LocationStockService::class);
    $svc->addStock($product->id, $this->gudang->id, 100, ['movement_type' => 'PURCHASE_IN']);

    $this->actingAs($this->manager);
    $distRes = $this->postJson('/api/goods-receipts', [
        'type' => 'TRANSFER',
        'source_warehouse' => 'Gudang Pusat',
        'destination_store' => 'Toko Pusat',
        'from_location_id' => $this->gudang->id,
        'to_location_id' => $this->tokoPusat->id,
        'items' => [
            ['product_id' => $product->id, 'sku' => $product->sku, 'qty_sent' => 20],
        ],
    ])->assertStatus(201);

    $grId = $distRes->json('data.id');

    // First verify succeeds
    $this->postJson("/api/goods-receipts/{$grId}/verify")->assertStatus(200);
    // Double receiving MUST be rejected
    $this->postJson("/api/goods-receipts/{$grId}/verify")->assertStatus(422);

    // Manually create 2 non-expired batches in Cabang A to strictly verify FIFO received_at order
    $batchOlder = StockBatch::create([
        'product_id' => $product->id,
        'location_id' => $this->cabangA->id,
        'batch_number' => 'FIFO-OLD',
        'initial_qty' => 5,
        'remaining_qty' => 5,
        'unit_cost' => 12000,
        'received_at' => now()->subDays(10),
        'expired_date' => null,
    ]);
    $batchNewer = StockBatch::create([
        'product_id' => $product->id,
        'location_id' => $this->cabangA->id,
        'batch_number' => 'FIFO-NEW',
        'initial_qty' => 10,
        'remaining_qty' => 10,
        'unit_cost' => 12000,
        'received_at' => now()->subDays(1),
        'expired_date' => null,
    ]);
    ProductStock::create([
        'product_id' => $product->id,
        'location_id' => $this->cabangA->id,
        'stock' => 15,
        'is_active' => true,
    ]);

    $svc->deductStock($product->id, $this->cabangA->id, 7, ['movement_type' => 'SALE_OUT']);
    $batchOlder->refresh();
    $batchNewer->refresh();

    expect((int) $batchOlder->remaining_qty)->toBe(0);
    expect((int) $batchNewer->remaining_qty)->toBe(8);
    expect((int) ProductStock::where('product_id', $product->id)->where('location_id', $this->tokoPusat->id)->value('stock'))->toBe(20);
});

test('concurrent two-terminal sales on same branch respect stock locking and void restores exact batch and location stock', function () {
    $product = Product::create([
        'sku' => 'CONC-001',
        'barcode' => '899000111002',
        'name' => 'Minyak Goreng 2L',
        'category' => 'Sembako',
        'unit' => 'Pcs',
        'price' => 35000,
        'cost_price' => 28000,
        'stock' => 0,
        'min_stock' => 2,
        'status' => 'HABIS',
    ]);

    /** @var LocationStockService $svc */
    $svc = app(LocationStockService::class);
    $svc->addStock($product->id, $this->cabangA->id, 5, ['movement_type' => 'TRANSFER_IN', 'batch_number' => 'BATCH-CONC-1']);

    $this->actingAs($this->kasirA1);
    $s1 = $this->postJson('/api/shift/open', [
        'terminal_code' => 'POS-06',
        'cashier_nik' => $this->kasirA1->nik,
        'cashier_name' => $this->kasirA1->name,
        'opening_cash' => 100000,
        'location_id' => $this->cabangA->id,
    ])->assertStatus(201)->json('data');

    $this->actingAs($this->kasirA2);
    $s2 = $this->postJson('/api/shift/open', [
        'terminal_code' => 'POS-07',
        'cashier_nik' => $this->kasirA2->nik,
        'cashier_name' => $this->kasirA2->name,
        'opening_cash' => 100000,
        'location_id' => $this->cabangA->id,
    ])->assertStatus(201)->json('data');

    // Terminal 1 buys 3 units
    $this->actingAs($this->kasirA1);
    $tx1Res = $this->postJson('/api/pos/checkout', [
        'shift_id' => $s1['id'],
        'terminal_code' => 'POS-06',
        'location_id' => $this->cabangA->id,
        'payment_method' => 'TUNAI',
        'cash_received' => 120000,
        'items' => [['sku' => 'CONC-001', 'barcode' => '899000111002', 'name' => $product->name, 'qty' => 3, 'price' => 35000]],
    ])->assertStatus(201);

    // Terminal 2 attempts to buy 3 units (only 2 remaining!) -> MUST be rejected cleanly
    $this->actingAs($this->kasirA2);
    $this->postJson('/api/pos/checkout', [
        'shift_id' => $s2['id'],
        'terminal_code' => 'POS-07',
        'location_id' => $this->cabangA->id,
        'payment_method' => 'TUNAI',
        'cash_received' => 120000,
        'items' => [['sku' => 'CONC-001', 'barcode' => '899000111002', 'name' => $product->name, 'qty' => 3, 'price' => 35000]],
    ])->assertStatus(422)->assertJsonPath('error_code', 'STOCK_INSUFFICIENT');

    // Stock remains 2
    expect((int) ProductStock::where('product_id', $product->id)->where('location_id', $this->cabangA->id)->value('stock'))->toBe(2);

    // Manager voids Transaction 1 -> restores 3 units back to Cabang A batch & stock
    $this->actingAs($this->manager);
    $this->postJson('/api/pos/void/transaction', [
        'invoice_number' => $tx1Res->json('data.invoice_number'),
        'pin_or_password' => '123456',
        'reason' => 'Salah input item kasir',
    ])->assertStatus(200);

    expect((int) ProductStock::where('product_id', $product->id)->where('location_id', $this->cabangA->id)->value('stock'))->toBe(5);
    expect((int) StockBatch::where('batch_number', 'BATCH-CONC-1')->value('remaining_qty'))->toBe(5);
    expect(StockMovement::where('product_id', $product->id)->where('location_id', $this->cabangA->id)->where('movement_type', 'VOID_IN')->exists())->toBeTrue();
});

test('offline shift open cash movements transactions close handover and retry idempotency work end to end', function () {
    $product = Product::create([
        'sku' => 'OFF-SHF-01',
        'barcode' => '899000222001',
        'name' => 'Beras Premium 5kg',
        'category' => 'Sembako',
        'unit' => 'Pcs',
        'price' => 70000,
        'cost_price' => 60000,
        'stock' => 0,
        'min_stock' => 2,
        'status' => 'HABIS',
    ]);

    app(LocationStockService::class)->addStock($product->id, $this->cabangA->id, 1, ['movement_type' => 'TRANSFER_IN']);

    $this->actingAs($this->kasirA1);

    $payload = [
        'offline_shifts' => [
            [
                'offline_id' => 'shf-open-uuid-001',
                'action' => 'OPEN',
                'shift_code' => 'CS-OFF-A-001',
                'terminal_code' => 'POS-06',
                'location_id' => $this->cabangA->id,
                'branch' => 'Cabang A',
                'cashier_nik' => $this->kasirA1->nik,
                'cashier_name' => $this->kasirA1->name,
                'opening_cash' => 200000,
                'opened_at' => now()->subHours(2)->toIso8601String(),
            ],
            [
                'offline_id' => 'shf-close-uuid-001',
                'action' => 'CLOSE',
                'shift_code' => 'CS-OFF-A-001',
                'terminal_code' => 'POS-06',
                'location_id' => $this->cabangA->id,
                'branch' => 'Cabang A',
                'cashier_nik' => $this->kasirA1->nik,
                'cashier_name' => $this->kasirA1->name,
                'actual_cash' => 425000,
                'closing_notes' => 'Serah terima shift offline aman',
                'closed_at' => now()->toIso8601String(),
            ],
        ],
        'transactions' => [
            [
                'offline_id' => 'trx-uuid-001',
                'invoice_number' => 'TRX-OFF-A-001',
                'terminal_code' => 'POS-06',
                'location_id' => $this->cabangA->id,
                'branch' => 'Cabang A',
                'cashier_nik' => $this->kasirA1->nik,
                'cashier_name' => $this->kasirA1->name,
                'payment_method' => 'TUNAI',
                'cash_received' => 250000,
                'change_returned' => 40000,
                'grand_total' => 210000,
                'items' => [
                    ['sku' => 'OFF-SHF-01', 'barcode' => '899000222001', 'name' => $product->name, 'qty' => 3, 'price' => 70000, 'total' => 210000],
                ],
            ],
        ],
        'cash_movements' => [
            [
                'offline_id' => 'mov-uuid-in-001',
                'shift_code' => 'CS-OFF-A-001',
                'terminal_code' => 'POS-06',
                'location_id' => $this->cabangA->id,
                'branch' => 'Cabang A',
                'cashier_nik' => $this->kasirA1->nik,
                'type' => 'KAS_MASUK',
                'amount' => 50000,
                'reason' => 'Tambah receh kembalian',
            ],
            [
                'offline_id' => 'mov-uuid-out-001',
                'shift_code' => 'CS-OFF-A-001',
                'terminal_code' => 'POS-06',
                'location_id' => $this->cabangA->id,
                'branch' => 'Cabang A',
                'cashier_nik' => $this->kasirA1->nik,
                'type' => 'PENGELUARAN_TUNAI',
                'amount' => 35000,
                'reason' => 'Beli plastik kresek',
            ],
        ],
    ];

    // 1. Initial sync
    $res1 = $this->postJson('/api/pos/sync-offline', $payload)->assertStatus(200);
    expect($res1->json('synced_count'))->toBe(1);
    expect($res1->json('failed_count'))->toBe(0);
    expect($res1->json('results.transactions.0.stock_conflict'))->toBeTrue();

    // Verify offline stock conflict recorded in StockMovement notes
    $conflictMov = StockMovement::where('reference_number', 'TRX-OFF-A-001')->first();
    expect($conflictMov)->not->toBeNull();
    expect($conflictMov->notes)->toContain('KONFLIK_STOK_OFFLINE');

    // Verify shift math: 200k + 210k + 50k - 35k = 425k expected, 425k actual => 0 variance, CLOSED
    $shift = CashierShift::where('shift_code', 'CS-OFF-A-001')->first();
    expect($shift)->not->toBeNull();
    expect($shift->status)->toBe('CLOSED');
    expect((float) $shift->cash_in)->toBe(50000.0);
    expect((float) $shift->cash_out)->toBe(35000.0);
    expect((float) $shift->expected_cash)->toBe(425000.0);
    expect((float) $shift->variance)->toBe(0.0);

    // 2. Retry same batch (simulating lost HTTP response after server committed): ZERO duplicates
    $res2 = $this->postJson('/api/pos/sync-offline', $payload)->assertStatus(200);
    expect($res2->json('synced_count'))->toBe(0);
    expect($res2->json('duplicate_count'))->toBe(1);
    expect($res2->json('results.cash_movements.0.status'))->toBe('ALREADY_SYNCED');
    expect($res2->json('results.cash_movements.1.status'))->toBe('ALREADY_SYNCED');

    $shift->refresh();
    expect((float) $shift->cash_in)->toBe(50000.0);
    expect((float) $shift->cash_out)->toBe(35000.0);
    expect(Transaction::where('offline_id', 'trx-uuid-001')->count())->toBe(1);
    expect(CashMovement::where('offline_id', 'mov-uuid-in-001')->count())->toBe(1);
});

test('online checkout with offline_id prevents duplicate when retry hits sync-offline after lost response', function () {
    $product = Product::create([
        'sku' => 'LOST-RESP-01',
        'barcode' => '899000333001',
        'name' => 'Kopi Kapal Api',
        'category' => 'Minuman',
        'unit' => 'Pcs',
        'price' => 15000,
        'cost_price' => 10000,
        'stock' => 0,
        'min_stock' => 2,
        'status' => 'HABIS',
    ]);
    app(LocationStockService::class)->addStock($product->id, $this->cabangA->id, 10, ['movement_type' => 'TRANSFER_IN']);

    $this->actingAs($this->kasirA1);
    $shift = $this->postJson('/api/shift/open', [
        'terminal_code' => 'POS-06',
        'cashier_nik' => $this->kasirA1->nik,
        'cashier_name' => $this->kasirA1->name,
        'opening_cash' => 50000,
        'location_id' => $this->cabangA->id,
    ])->assertStatus(201)->json('data');

    $sharedOfflineId = 'off-lost-resp-uuid-99';
    $checkoutRes = $this->postJson('/api/pos/checkout', [
        'offline_id' => $sharedOfflineId,
        'shift_id' => $shift['id'],
        'terminal_code' => 'POS-06',
        'location_id' => $this->cabangA->id,
        'payment_method' => 'TUNAI',
        'cash_received' => 50000,
        'items' => [['sku' => 'LOST-RESP-01', 'barcode' => '899000333001', 'name' => $product->name, 'qty' => 2, 'price' => 15000]],
    ])->assertStatus(201);

    // Client simulates lost response & retries via syncOffline with same offline_id
    $retryRes = $this->postJson('/api/pos/sync-offline', [
        'transactions' => [[
            'offline_id' => $sharedOfflineId,
            'invoice_number' => 'TRX-OFF-CLIENT-FALLBACK',
            'terminal_code' => 'POS-06',
            'location_id' => $this->cabangA->id,
            'cashier_nik' => $this->kasirA1->nik,
            'payment_method' => 'TUNAI',
            'grand_total' => 30000,
            'items' => [['sku' => 'LOST-RESP-01', 'barcode' => '899000333001', 'name' => $product->name, 'qty' => 2, 'price' => 15000]],
        ]],
    ])->assertStatus(200);

    expect($retryRes->json('results.transactions.0.status'))->toBe('ALREADY_SYNCED');
    expect($retryRes->json('results.transactions.0.server_id'))->toBe($checkoutRes->json('data.id'));
    expect((int) ProductStock::where('product_id', $product->id)->where('location_id', $this->cabangA->id)->value('stock'))->toBe(8);
});

test('partial sync keeps failed movement independent without losing valid transaction', function () {
    app(LocationStockService::class)->addStock(
        Product::create([
            'sku' => 'PART-001', 'barcode' => '899000444001', 'name' => 'Teh Botol',
            'category' => 'Minuman', 'unit' => 'Pcs', 'price' => 5000, 'cost_price' => 3000,
            'stock' => 0, 'min_stock' => 2, 'status' => 'HABIS',
        ])->id,
        $this->cabangA->id,
        10,
        ['movement_type' => 'TRANSFER_IN']
    );

    $this->actingAs($this->kasirA1);
    $res = $this->postJson('/api/pos/sync-offline', [
        'transactions' => [[
            'offline_id' => 'part-trx-1',
            'invoice_number' => 'TRX-PART-1',
            'terminal_code' => 'POS-06',
            'location_id' => $this->cabangA->id,
            'cashier_nik' => $this->kasirA1->nik,
            'payment_method' => 'TUNAI',
            'grand_total' => 10000,
            'items' => [['sku' => 'PART-001', 'barcode' => '899000444001', 'name' => 'Teh Botol', 'qty' => 2, 'price' => 5000]],
        ]],
        'cash_movements' => [
            [
                'offline_id' => 'part-mov-missing-shift',
                'terminal_code' => 'POS-999-UNKNOWN',
                'type' => 'OUT',
                'amount' => 5000,
                'reason' => 'No shift on terminal',
            ],
        ],
    ])->assertStatus(200);

    expect($res->json('partial'))->toBeTrue();
    expect($res->json('synced_count'))->toBe(1);
    expect($res->json('failed_count'))->toBe(1);
    expect($res->json('results.transactions.0.status'))->toBe('SYNCED');
    expect($res->json('results.cash_movements.0.status'))->toBe('FAILED');
});

test('api security blocks unauthenticated spoofed identity cross location stock master and shift hijacking', function () {
    // 1. Unauthenticated requests with spoofed cashier_nik or X-Staff-Id are rejected (401)
    $this->withHeaders(['X-Security-Test' => '1', 'X-Staff-Id' => (string) $this->manager->id])
        ->postJson('/api/pos/checkout', [
            'terminal_code' => 'POS-06',
            'cashier_nik' => $this->kasirA1->nik,
            'payment_method' => 'TUNAI',
            'items' => [['name' => 'Item', 'qty' => 1, 'price' => 1000]],
        ])->assertStatus(401);

    $this->withHeaders(['X-Security-Test' => '1'])
        ->getJson('/api/pos/products/search?cashier_nik=' . $this->kasirA1->nik)
        ->assertStatus(401);

    $this->withHeaders(['X-Security-Test' => '1'])
        ->postJson('/api/shift/open', ['terminal_code' => 'POS-06', 'cashier_nik' => $this->kasirA1->nik, 'cashier_name' => 'Fake', 'opening_cash' => 1000])
        ->assertStatus(401);

    // 2. Authenticated Kasir A cannot access Stock Master or create Master products or access Cabang B
    $this->actingAs($this->kasirA1);
    $this->getJson('/api/products')->assertStatus(403);
    $this->getJson('/api/products?only_registered=1&location_id=' . $this->cabangB->id)->assertStatus(403);
    $this->getJson('/api/products?only_registered=1&location_code=GDG-001')->assertStatus(403);
    $this->postJson('/api/products', ['sku' => 'X', 'barcode' => 'X', 'name' => 'X', 'category' => 'X', 'unit' => 'Pcs', 'price' => 1000, 'cost_price' => 500, 'stock' => 1])->assertStatus(403);
    $this->getJson('/api/pos/products/search?location_id=' . $this->cabangB->id)->assertStatus(403);

    // 3. Terminal mismatch to location rejected with 422
    $this->postJson('/api/shift/open', [
        'terminal_code' => 'POS-06', // Cabang A terminal
        'cashier_nik' => $this->kasirA1->nik,
        'cashier_name' => $this->kasirA1->name,
        'opening_cash' => 100000,
        'location_id' => $this->tokoPusat->id,
    ])->assertStatus(422);

    // 4. Kasir A1 opens shift on POS-06; Kasir A2 CANNOT checkout, mutate cash, or close Kasir A1's shift!
    $shiftA1 = $this->postJson('/api/shift/open', [
        'terminal_code' => 'POS-06',
        'cashier_nik' => $this->kasirA1->nik,
        'cashier_name' => $this->kasirA1->name,
        'opening_cash' => 100000,
        'location_id' => $this->cabangA->id,
    ])->assertStatus(201)->json('data');

    $this->actingAs($this->kasirA2);
    $this->postJson('/api/pos/checkout', [
        'shift_id' => $shiftA1['id'],
        'terminal_code' => 'POS-06',
        'payment_method' => 'TUNAI',
        'cash_received' => 5000,
        'items' => [['name' => 'Test', 'qty' => 1, 'price' => 1000]],
    ])->assertStatus(403);

    $this->postJson('/api/shift/cash-movement', [
        'shift_id' => $shiftA1['id'],
        'type' => 'KAS_MASUK',
        'amount' => 10000,
    ])->assertStatus(403);

    $this->postJson('/api/shift/close', [
        'shift_id' => $shiftA1['id'],
        'actual_cash' => 100000,
    ])->assertStatus(403);

    // 5. Offline sync with manipulated cashier_nik or unauthorized branch blocked with 403
    $this->postJson('/api/pos/sync-offline', [
        'transactions' => [[
            'offline_id' => 'tamper-01',
            'invoice_number' => 'TRX-TAMPER-01',
            'terminal_code' => 'POS-06',
            'location_id' => $this->cabangA->id,
            'cashier_nik' => $this->kasirA1->nik, // Spoofed NIK!
            'payment_method' => 'TUNAI',
            'grand_total' => 10000,
            'items' => [['name' => 'X', 'qty' => 1, 'price' => 10000]],
        ]],
    ])->assertStatus(403);

    $this->postJson('/api/pos/sync-offline', [
        'transactions' => [[
            'offline_id' => 'tamper-02',
            'invoice_number' => 'TRX-TAMPER-02',
            'terminal_code' => 'POS-11', // Cabang B terminal!
            'location_id' => $this->cabangB->id,
            'cashier_nik' => $this->kasirA2->nik,
            'payment_method' => 'TUNAI',
            'grand_total' => 10000,
            'items' => [['name' => 'X', 'qty' => 1, 'price' => 10000]],
        ]],
    ])->assertStatus(403);
});
