<?php

namespace Tests\Feature;

use App\Models\CashierShift;
use App\Models\GoodsReceipt;
use App\Models\Location;
use App\Models\Product;
use App\Models\ProductStock;
use App\Models\StockBatch;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class StockMasterVsStoreStockFase15Test extends TestCase
{
    protected Location $gudang;
    protected Location $tokoPusat;
    protected Location $cabangA;
    protected Location $cabangB;
    protected User $manager;
    protected User $kasirPusat;
    protected User $kasirCabangA;
    protected User $kasirCabangB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->gudang = Location::create(['code' => 'GDG-001', 'name' => 'Gudang Pusat', 'type' => 'WAREHOUSE', 'status' => 'AKTIF', 'is_active' => true]);
        $this->tokoPusat = Location::create(['code' => 'CAB-001', 'name' => 'Toko Pusat', 'type' => 'STORE', 'status' => 'AKTIF', 'is_active' => true]);
        $this->cabangA = Location::create(['code' => 'CAB-002', 'name' => 'Cabang A', 'type' => 'STORE', 'status' => 'AKTIF', 'is_active' => true]);
        $this->cabangB = Location::create(['code' => 'CAB-003', 'name' => 'Cabang B', 'type' => 'STORE', 'status' => 'AKTIF', 'is_active' => true]);

        $this->manager = User::create([
            'name' => 'Manager ILP',
            'username' => 'mgr_ilp_15',
            'nik' => '900001',
            'email' => 'mgr15@ilp.local',
            'role' => 'Manager',
            'pin' => '123456',
            'branch' => 'Pusat',
            'password' => Hash::make('password'),
            'is_active' => true,
        ]);

        $this->kasirPusat = User::create([
            'name' => 'Kasir Pusat',
            'username' => 'kasir_pusat_15',
            'nik' => '900002',
            'email' => 'kasir.pusat@ilp.local',
            'role' => 'Kasir Utama',
            'pin' => '123456',
            'branch' => 'Toko Pusat',
            'password' => Hash::make('password'),
            'is_active' => true,
        ]);

        $this->kasirCabangA = User::create([
            'name' => 'Kasir Cabang A',
            'username' => 'kasir_caba_15',
            'nik' => '900003',
            'email' => 'kasir.a@ilp.local',
            'role' => 'Kasir Utama',
            'pin' => '123456',
            'branch' => 'Cabang A',
            'password' => Hash::make('password'),
            'is_active' => true,
        ]);

        $this->kasirCabangB = User::create([
            'name' => 'Kasir Cabang B',
            'username' => 'kasir_cabb_15',
            'nik' => '900004',
            'email' => 'kasir.b@ilp.local',
            'role' => 'Kasir Utama',
            'pin' => '123456',
            'branch' => 'Cabang B',
            'password' => Hash::make('password'),
            'is_active' => true,
        ]);
    }

    private function createMasterProduct(string $sku, string $barcode, string $name, int $warehouseStock = 0, ?string $expiredDate = null): Product
    {
        $this->actingAs($this->manager);
        $res = $this->postJson('/api/products', [
            'sku' => $sku,
            'barcode' => $barcode,
            'name' => $name,
            'category' => 'Kebutuhan Pokok',
            'unit' => 'Pcs',
            'cost_price' => 5000,
            'price' => 8000,
            'member_price' => 7500,
            'stock' => $warehouseStock,
            'min_stock' => 5,
            'expired_date' => $expiredDate,
        ]);
        $res->assertStatus(201);
        return Product::findOrFail($res->json('data.id'));
    }

    public function test_manager_sees_full_stock_master_while_pos_sees_only_store_stock(): void
    {
        // Create 120 master products in Stock Master (initial stock goes to Gudang Pusat, NOT Store Stock)
        $now = now();
        $bulk = [];
        for ($i = 1; $i <= 120; $i++) {
            $bulk[] = [
                'sku' => sprintf('MST-%04d', $i),
                'barcode' => sprintf('89910000%05d', $i),
                'name' => "Produk Master {$i}",
                'category' => 'Makanan',
                'unit' => 'Pcs',
                'cost_price' => 4000,
                'price' => 6000,
                'member_price' => 5500,
                'stock' => 50,
                'min_stock' => 5,
                'status' => 'TERSEDIA',
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }
        Product::insert($bulk);

        // Manager accesses Stock Master (/api/products) -> sees all 120 master products
        $this->actingAs($this->manager);
        $mgrRes = $this->getJson('/api/products?per_page=100');
        $mgrRes->assertStatus(200);
        $this->assertEquals(120, $mgrRes->json('meta.total'));

        // Distribute 105 products ONLY to Toko Pusat (so we also test initial 100 cap on POS!)
        $pusatProducts = Product::orderBy('id')->take(105)->get();
        $psRows = [];
        foreach ($pusatProducts as $p) {
            $psRows[] = [
                'product_id' => $p->id,
                'location_id' => $this->tokoPusat->id,
                'stock' => 10,
                'is_active' => true,
                'first_received_at' => $now,
                'last_received_at' => $now,
                'version' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }
        ProductStock::insert($psRows);

        // Distribute only 2 products to Cabang A
        $cabAProducts = Product::orderBy('id')->skip(105)->take(2)->get();
        foreach ($cabAProducts as $p) {
            ProductStock::create([
                'product_id' => $p->id,
                'location_id' => $this->cabangA->id,
                'stock' => 15,
                'is_active' => true,
                'first_received_at' => $now,
                'last_received_at' => $now,
                'version' => 1,
            ]);
        }

        // Scenario 2 & 11: Kasir Toko Pusat sees ONLY Toko Pusat products, initial view capped at 100
        $this->actingAs($this->kasirPusat);
        $pusatSearch = $this->getJson('/api/pos/products/search?branch=Toko%20Pusat&limit=500');
        $pusatSearch->assertStatus(200)
            ->assertJsonPath('catalog_mode', 'STOCK_TOKO')
            ->assertJsonPath('location.code', 'CAB-001');
        $this->assertCount(100, $pusatSearch->json('data'), 'Tampilan awal POS harus maksimal 100 produk.');
        $this->assertEquals(105, $pusatSearch->json('meta.total'));

        // Scenario 3: Kasir Cabang A sees ONLY 2 products registered in Cabang A
        $this->actingAs($this->kasirCabangA);
        $cabASearch = $this->getJson('/api/pos/products/search?branch=Cabang%20A');
        $cabASearch->assertStatus(200)
            ->assertJsonPath('catalog_mode', 'STOCK_TOKO')
            ->assertJsonPath('location.code', 'CAB-002');
        $this->assertCount(2, $cabASearch->json('data'));

        // Scenario 4 & 5: Kasir Cabang B sees 0 products (none distributed to Cabang B yet)
        $this->actingAs($this->kasirCabangB);
        $cabBSearch = $this->getJson('/api/pos/products/search?branch=Cabang%20B');
        $cabBSearch->assertStatus(200)
            ->assertJsonPath('catalog_mode', 'STOCK_TOKO')
            ->assertJsonPath('location.code', 'CAB-003');
        $this->assertCount(0, $cabBSearch->json('data'));

        // Scenario 9 & 10: Barcode, SKU, Name search on Cabang B must NOT find products from Master/Pusat/Cabang A
        $pusatProd = $pusatProducts->first();
        $this->getJson('/api/pos/products/barcode/' . $pusatProd->barcode . '?branch=Cabang%20B')
            ->assertStatus(404);
        $this->getJson('/api/pos/products/search?q=' . urlencode($pusatProd->sku) . '&branch=Cabang%20B')
            ->assertStatus(200)
            ->assertJsonCount(0, 'data');

        // Incremental sync endpoint returns all 105 items for Toko Pusat across 2 pages (100 + 5), never Cabang A/Master
        $this->actingAs($this->kasirPusat);
        $syncPage1 = $this->getJson('/api/pos/products/sync?branch=Toko%20Pusat&per_page=100&after_id=0');
        $syncPage1->assertStatus(200)->assertJsonPath('has_more', true);
        $this->assertCount(100, $syncPage1->json('data'));
        $nextAfter = $syncPage1->json('next_after_id');

        $syncPage2 = $this->getJson("/api/pos/products/sync?branch=Toko%20Pusat&per_page=100&after_id={$nextAfter}");
        $syncPage2->assertStatus(200)->assertJsonPath('has_more', false);
        $this->assertCount(5, $syncPage2->json('data'));
    }

    public function test_distribution_in_transit_vs_verified_receipt_and_double_verify_prevention(): void
    {
        // 1. Product created in Stock Master with 50 units at Gudang Pusat
        $product = $this->createMasterProduct('DIST-001', '899555000111', 'Beras Premium 5kg', 50);

        // Before distribution: Cabang A POS cannot see or scan this product
        $this->actingAs($this->kasirCabangA);
        $this->getJson('/api/pos/products/search?branch=Cabang%20A&q=Beras')
            ->assertStatus(200)
            ->assertJsonCount(0, 'data');
        $this->getJson('/api/pos/products/barcode/899555000111?branch=Cabang%20A')
            ->assertStatus(404);

        // 2. Manager dispatches 20 units from Gudang Pusat to Cabang A (status: MENUNGGU_VERIFIKASI = in transit)
        $this->actingAs($this->manager);
        $dispatchRes = $this->postJson('/api/goods-receipts', [
            'destination_store' => 'Cabang A',
            'driver_name' => 'Sopir Budi',
            'plate_number' => 'BE 9999 XX',
            'status' => 'MENUNGGU_VERIFIKASI',
            'items' => [
                [
                    'product_id' => $product->id,
                    'sku' => $product->sku,
                    'name' => $product->name,
                    'qty_sent' => 20,
                ],
            ],
        ]);
        $dispatchRes->assertStatus(201);
        $receiptId = $dispatchRes->json('data.id');

        // Gudang Pusat stock deducted (50 - 20 = 30)
        $this->assertEquals(30, ProductStock::where('product_id', $product->id)->where('location_id', $this->gudang->id)->value('stock'));

        // Scenario 6: While in-transit, Cabang A POS STILL does NOT see the product!
        $this->actingAs($this->kasirCabangA);
        $this->getJson('/api/pos/products/search?branch=Cabang%20A&q=Beras')
            ->assertStatus(200)
            ->assertJsonCount(0, 'data');

        // 3. Cabang A verifies receipt (only 18 units received physically out of 20 sent)
        $receipt = GoodsReceipt::with('items')->findOrFail($receiptId);
        $grItem = $receipt->items->first();

        $verifyRes = $this->postJson("/api/goods-receipts/{$receiptId}/verify", [
            'branch' => 'Cabang A',
            'receiver_name' => 'Kasir Cabang A',
            'items' => [
                [
                    'id' => $grItem->id,
                    'qty_received' => 18,
                    'condition' => 'BAIK',
                ],
            ],
        ]);
        $verifyRes->assertStatus(200)
            ->assertJsonPath('data.status', 'DITERIMA_SELISIH');

        // Scenario 7: Product now appears in Cabang A Stock Toko with exact qty_received (18), NOT in Cabang B or Toko Pusat!
        $this->assertEquals(18, ProductStock::where('product_id', $product->id)->where('location_id', $this->cabangA->id)->value('stock'));
        $this->assertNull(ProductStock::where('product_id', $product->id)->where('location_id', $this->cabangB->id)->first());

        $this->getJson('/api/pos/products/search?branch=Cabang%20A&q=Beras')
            ->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.stock', 18);

        $this->getJson('/api/pos/products/barcode/899555000111?branch=Cabang%20A')
            ->assertStatus(200)
            ->assertJsonPath('data.stock', 18);

        $this->actingAs($this->kasirCabangB);
        $this->getJson('/api/pos/products/search?branch=Cabang%20B&q=Beras')
            ->assertStatus(200)
            ->assertJsonCount(0, 'data');

        // Double receiving prevention: verifying the same document again must fail with 422
        $this->actingAs($this->kasirCabangA);
        $this->postJson("/api/goods-receipts/{$receiptId}/verify", [
            'branch' => 'Cabang A',
        ])->assertStatus(422)->assertJsonPath('error_code', 'ALREADY_VERIFIED');
        $this->assertEquals(18, ProductStock::where('product_id', $product->id)->where('location_id', $this->cabangA->id)->value('stock'));
    }

    public function test_branch_checkout_isolation_zero_stock_habis_visibility_and_fefo_fifo(): void
    {
        $service = app(\App\Services\LocationStockService::class);

        // Product 1 (Expired -> FEFO): 2 batches in Cabang A (earlier expiry = 5 qty, later expiry = 10 qty) + 1 batch in Cabang B (25 qty)
        $milk = $this->createMasterProduct('FEFO-001', '899777000111', 'Susu Segar FEFO', 0);
        $batchLater = $service->addStock($milk->id, $this->cabangA->id, 10, [
            'batch_number' => 'BTH-LATER',
            'expired_date' => Carbon::today()->addDays(60)->toDateString(),
            'received_at' => now()->subDays(5),
        ]);
        $batchEarlier = $service->addStock($milk->id, $this->cabangA->id, 5, [
            'batch_number' => 'BTH-EARLIER',
            'expired_date' => Carbon::today()->addDays(20)->toDateString(),
            'received_at' => now()->subDays(1),
        ]);
        $service->addStock($milk->id, $this->cabangB->id, 25, [
            'batch_number' => 'BTH-CABB',
            'expired_date' => Carbon::today()->addDays(40)->toDateString(),
        ]);

        // Product 2 (Non-Expired -> FIFO): 2 batches in Cabang A (received earlier = 4 qty, received later = 8 qty)
        $soap = $this->createMasterProduct('FIFO-001', '899777000222', 'Sabun Mandi FIFO', 0);
        $fifoFirst = $service->addStock($soap->id, $this->cabangA->id, 4, [
            'batch_number' => 'BTH-FIFO-1',
            'expired_date' => null,
            'received_at' => now()->subDays(10),
        ]);
        $fifoSecond = $service->addStock($soap->id, $this->cabangA->id, 8, [
            'batch_number' => 'BTH-FIFO-2',
            'expired_date' => null,
            'received_at' => now()->subDays(2),
        ]);

        // Open shift at Cabang A (POS-06)
        $shiftA = CashierShift::create([
            'shift_code' => 'CS-CABA-01',
            'terminal_code' => 'POS-06',
            'location_id' => $this->cabangA->id,
            'branch' => 'Cabang A',
            'user_id' => $this->kasirCabangA->id,
            'cashier_nik' => $this->kasirCabangA->nik,
            'cashier_name' => $this->kasirCabangA->name,
            'opening_cash' => 100000,
            'status' => 'OPEN',
            'opened_at' => now(),
        ]);

        // Checkout 7 units of Susu Segar FEFO (must take 5 from BTH-EARLIER first, then 2 from BTH-LATER)
        // and 5 units of Sabun Mandi FIFO (must take 4 from BTH-FIFO-1 first, then 1 from BTH-FIFO-2)
        $this->actingAs($this->kasirCabangA);
        $checkoutRes = $this->postJson('/api/pos/checkout', [
            'shift_id' => $shiftA->id,
            'terminal_code' => 'POS-06',
            'location_id' => $this->cabangA->id,
            'branch' => 'Cabang A',
            'cashier_nik' => $this->kasirCabangA->nik,
            'cashier_name' => $this->kasirCabangA->name,
            'payment_method' => 'TUNAI',
            'cash_received' => 200000,
            'items' => [
                ['sku' => $milk->sku, 'barcode' => $milk->barcode, 'name' => $milk->name, 'qty' => 7, 'price' => 8000],
                ['sku' => $soap->sku, 'barcode' => $soap->barcode, 'name' => $soap->name, 'qty' => 5, 'price' => 8000],
            ],
        ]);
        $checkoutRes->assertStatus(201);

        // Verify FEFO deduction on Cabang A batches
        $this->assertEquals(0.0, (float) StockBatch::where('batch_number', 'BTH-EARLIER')->value('remaining_qty'));
        $this->assertEquals(8.0, (float) StockBatch::where('batch_number', 'BTH-LATER')->value('remaining_qty'));
        $this->assertEquals(8, ProductStock::where('product_id', $milk->id)->where('location_id', $this->cabangA->id)->value('stock'));

        // Verify FIFO deduction on Cabang A batches
        $this->assertEquals(0.0, (float) StockBatch::where('batch_number', 'BTH-FIFO-1')->value('remaining_qty'));
        $this->assertEquals(7.0, (float) StockBatch::where('batch_number', 'BTH-FIFO-2')->value('remaining_qty'));
        $this->assertEquals(7, ProductStock::where('product_id', $soap->id)->where('location_id', $this->cabangA->id)->value('stock'));

        // Scenario 14: Cabang B stock MUST be completely untouched (still 25)
        $this->assertEquals(25, ProductStock::where('product_id', $milk->id)->where('location_id', $this->cabangB->id)->value('stock'));

        // Sell remaining 8 units of milk in Cabang A so stock becomes 0
        $this->postJson('/api/pos/checkout', [
            'shift_id' => $shiftA->id,
            'terminal_code' => 'POS-06',
            'location_id' => $this->cabangA->id,
            'branch' => 'Cabang A',
            'cashier_nik' => $this->kasirCabangA->nik,
            'cashier_name' => $this->kasirCabangA->name,
            'payment_method' => 'TUNAI',
            'cash_received' => 100000,
            'items' => [
                ['sku' => $milk->sku, 'barcode' => $milk->barcode, 'name' => $milk->name, 'qty' => 8, 'price' => 8000],
            ],
        ])->assertStatus(201);

        // Scenario 8: Product with stock=0 in Store Stock is still discoverable with status HABIS
        $zeroSearch = $this->getJson('/api/pos/products/search?branch=Cabang%20A&q=Susu%20Segar');
        $zeroSearch->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.stock', 0)
            ->assertJsonPath('data.0.stock_status', 'HABIS')
            ->assertJsonPath('data.0.is_in_stock', false);

        // Cannot sell when stock is 0
        $this->postJson('/api/pos/checkout', [
            'shift_id' => $shiftA->id,
            'terminal_code' => 'POS-06',
            'location_id' => $this->cabangA->id,
            'branch' => 'Cabang A',
            'payment_method' => 'TUNAI',
            'cash_received' => 20000,
            'items' => [
                ['sku' => $milk->sku, 'barcode' => $milk->barcode, 'name' => $milk->name, 'qty' => 1, 'price' => 8000],
            ],
        ])->assertStatus(422)->assertJsonPath('error_code', 'STOCK_INSUFFICIENT');

        // Scenario 15: Price & stock changes sync properly via /api/pos/products/sync and /api/products shows real branch_stock
        $milk->update(['price' => 9500]);
        $syncRes = $this->getJson('/api/pos/products/sync?branch=Cabang%20A&per_page=100');
        $syncRes->assertStatus(200);
        $milkSyncItem = collect($syncRes->json('data'))->firstWhere('sku', 'FEFO-001');
        $this->assertNotNull($milkSyncItem);
        $this->assertEquals(9500.0, $milkSyncItem['price']);
        $this->assertEquals(0, $milkSyncItem['stock']);

        $this->actingAs($this->manager);
        $mgrList = $this->getJson('/api/products?search=FEFO-001');
        $mgrList->assertStatus(200)
            ->assertJsonPath('data.0.branch_stock.CAB-002', 0)
            ->assertJsonPath('data.0.branch_stock.CAB-003', 25);
    }

    public function test_offline_sync_and_cross_branch_cashier_guard_and_warehouse_rejection(): void
    {
        $service = app(\App\Services\LocationStockService::class);
        $item = $this->createMasterProduct('SYNC-15', '899888000111', 'Minyak Goreng 1L', 0);
        $service->addStock($item->id, $this->cabangA->id, 12, ['batch_number' => 'BTH-SYNC-A']);
        $service->addStock($item->id, $this->cabangB->id, 30, ['batch_number' => 'BTH-SYNC-B']);

        // 1. Cashier cannot query Gudang Pusat as POS catalog (422)
        $this->actingAs($this->kasirCabangA);
        $this->getJson('/api/pos/products/search?location_id=' . $this->gudang->id)
            ->assertStatus(422);

        // 2. Cashier assigned to Cabang A cannot query Cabang B catalog (403)
        $this->getJson('/api/pos/products/search?branch=Cabang%20B')
            ->assertStatus(403);

        // 3. Offline sync for Cabang A deducts ONLY Cabang A (12 - 3 = 9), Cabang B stays 30
        $shiftA = CashierShift::create([
            'shift_code' => 'CS-SYNC-A',
            'terminal_code' => 'POS-07',
            'location_id' => $this->cabangA->id,
            'branch' => 'Cabang A',
            'user_id' => $this->kasirCabangA->id,
            'cashier_nik' => $this->kasirCabangA->nik,
            'cashier_name' => $this->kasirCabangA->name,
            'opening_cash' => 100000,
            'status' => 'OPEN',
            'opened_at' => now(),
        ]);

        $syncRes = $this->postJson('/api/pos/sync-offline', [
            'transactions' => [
                [
                    'offline_id' => 'off_branch_a_001',
                    'invoice_number' => 'TRX-OFF-CABA-001',
                    'terminal_code' => 'POS-07',
                    'location_id' => $this->cabangA->id,
                    'branch' => 'Cabang A',
                    'cashier_nik' => $this->kasirCabangA->nik,
                    'cashier_name' => $this->kasirCabangA->name,
                    'payment_method' => 'TUNAI',
                    'cash_received' => 30000,
                    'change_returned' => 6000,
                    'grand_total' => 24000,
                    'items' => [
                        [
                            'sku' => $item->sku,
                            'barcode' => $item->barcode,
                            'name' => $item->name,
                            'qty' => 3,
                            'price' => 8000,
                            'total' => 24000,
                        ],
                    ],
                ],
            ],
        ]);

        $syncRes->assertStatus(200)->assertJsonPath('synced_count', 1);
        $this->assertEquals(9, ProductStock::where('product_id', $item->id)->where('location_id', $this->cabangA->id)->value('stock'));
        $this->assertEquals(30, ProductStock::where('product_id', $item->id)->where('location_id', $this->cabangB->id)->value('stock'));
    }
}
