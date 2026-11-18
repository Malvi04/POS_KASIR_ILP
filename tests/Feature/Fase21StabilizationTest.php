<?php

namespace Tests\Feature;

use App\Models\CashMovement;
use App\Models\CashierShift;
use App\Models\GoodsReceipt;
use App\Models\GoodsReceiptItem;
use App\Models\Location;
use App\Models\Product;
use App\Models\ProductStock;
use App\Models\StockBatch;
use App\Models\Transaction;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class Fase21StabilizationTest extends TestCase
{
    protected Location $warehouse;
    protected Location $storePusat;
    protected Location $storeCabang;
    protected User $manager;
    protected User $kasirPusat;
    protected User $kasirCabang;

    protected function setUp(): void
    {
        parent::setUp();

        $this->warehouse = Location::create([
            'code' => 'GDG-PST',
            'name' => 'Gudang Pusat',
            'type' => 'WAREHOUSE',
            'status' => 'AKTIF',
            'is_active' => true,
        ]);

        $this->storePusat = Location::create([
            'code' => 'TKO-PST',
            'name' => 'Toko Pusat',
            'type' => 'STORE',
            'status' => 'AKTIF',
            'is_active' => true,
        ]);

        $this->storeCabang = Location::create([
            'code' => 'TKO-CBG',
            'name' => 'Toko Cabang 1',
            'type' => 'STORE',
            'status' => 'AKTIF',
            'is_active' => true,
        ]);

        $this->manager = User::create([
            'name' => 'Manager Toko',
            'username' => 'mgr_fase21',
            'nik' => '910001',
            'email' => 'mgr21@pos.local',
            'role' => 'Manager',
            'pin' => '123456',
            'branch' => 'Toko Pusat',
            'password' => Hash::make('password'),
            'is_active' => true,
        ]);

        $this->kasirPusat = User::create([
            'name' => 'Kasir Toko Pusat',
            'username' => 'ksr_pusat21',
            'nik' => '910002',
            'email' => 'ksr_p@pos.local',
            'role' => 'Kasir Utama',
            'pin' => '123456',
            'branch' => 'Toko Pusat',
            'password' => Hash::make('password'),
            'is_active' => true,
        ]);

        $this->kasirCabang = User::create([
            'name' => 'Kasir Toko Cabang',
            'username' => 'ksr_cabang21',
            'nik' => '910003',
            'email' => 'ksr_c@pos.local',
            'role' => 'Kasir Utama',
            'pin' => '123456',
            'branch' => 'Toko Cabang 1',
            'password' => Hash::make('password'),
            'is_active' => true,
        ]);
    }

    /**
     * Skenario 9 & 10: Lifecycle Stock Toko nyata
     * Gudang Pusat -> Distribusi (MENUNGGU_VERIFIKASI) -> Konfirmasi Toko -> Stock Toko -> POS
     * Produk yang belum diterima toko TIDAK BOLEH muncul pada POS.
     */
    public function test_real_product_lifecycle_from_warehouse_to_store_confirmation_to_pos(): void
    {
        // 1. Create product in Master DB with stock in Gudang Pusat
        $prod = Product::create([
            'sku' => 'PRD-LIFECYCLE-01',
            'barcode' => '899778899001',
            'name' => 'Susu Kental Manis 500g',
            'category' => 'Sembako',
            'unit' => 'Kaleng',
            'cost_price' => 10000,
            'price' => 14000,
            'member_price' => 13500,
            'stock' => 100,
            'min_stock' => 5,
            'is_active' => true,
        ]);

        ProductStock::create([
            'product_id' => $prod->id,
            'location_id' => $this->warehouse->id,
            'stock' => 100,
            'min_stock' => 5,
            'is_active' => true,
        ]);

        StockBatch::create([
            'product_id' => $prod->id,
            'location_id' => $this->warehouse->id,
            'batch_number' => 'BCH-WH-001',
            'cost_price' => 10000,
            'initial_qty' => 100,
            'remaining_qty' => 100,
            'received_at' => now(),
            'expired_date' => now()->addMonths(6)->toDateString(),
            'status' => 'TERSEDIA',
        ]);

        // 2. Initial POS Check: Store Cabang MUST NOT see this product yet (Terminal POS-06 is assigned to Cabang 1)
        $this->actingAs($this->kasirCabang);
        $searchBefore = $this->getJson("/api/pos/products/search?location_id={$this->storeCabang->id}&branch=Toko+Cabang+1&terminal_code=POS-06&q=Susu");
        $searchBefore->assertStatus(200);
        $this->assertCount(0, $searchBefore->json('data'), 'Product not yet distributed to Store Cabang must NOT appear in POS');

        // 3. Manager dispatches transfer to Store Cabang with status MENUNGGU_VERIFIKASI (in transit)
        $this->actingAs($this->manager);
        $dispatchRes = $this->postJson('/api/goods-receipts', [
            'from_location_id' => $this->warehouse->id,
            'to_location_id' => $this->storeCabang->id,
            'destination_store' => 'Toko Cabang 1',
            'driver_name' => 'Sopir Budi',
            'plate_number' => 'B 1234 CD',
            'status' => 'MENUNGGU_VERIFIKASI',
            'items' => [
                [
                    'product_id' => $prod->id,
                    'sku' => $prod->sku,
                    'name' => $prod->name,
                    'qty_sent' => 20,
                ],
            ],
        ]);
        $dispatchRes->assertStatus(201);
        $receiptId = $dispatchRes->json('data.id');

        // 4. While in-transit (MENUNGGU_VERIFIKASI), Store Cabang STILL cannot see product in POS
        $this->actingAs($this->kasirCabang);
        $searchPending = $this->getJson("/api/pos/products/search?location_id={$this->storeCabang->id}&branch=Toko+Cabang+1&terminal_code=POS-06&q=Susu");
        $searchPending->assertStatus(200);
        $this->assertCount(0, $searchPending->json('data'), 'Unconfirmed in-transit goods must NOT appear in POS');

        // 5. Store confirms receipt of goods
        $receipt = GoodsReceipt::with('items')->findOrFail($receiptId);
        $grItem = $receipt->items->first();

        $verifyRes = $this->postJson("/api/goods-receipts/{$receiptId}/verify", [
            'location_id' => $this->storeCabang->id,
            'branch' => 'Toko Cabang 1',
            'receiver_name' => 'Kasir Toko Cabang',
            'items' => [
                [
                    'id' => $grItem->id,
                    'qty_received' => 20,
                    'condition' => 'BAIK',
                ],
            ],
        ]);
        $verifyRes->assertStatus(200);
        $this->assertEquals('DITERIMA_LENGKAP', $verifyRes->json('data.status'));

        // 6. After confirmation, ProductStock is created and POS can now search and sell
        $searchAfter = $this->getJson("/api/pos/products/search?location_id={$this->storeCabang->id}&branch=Toko+Cabang+1&terminal_code=POS-06&q=Susu");
        $searchAfter->assertStatus(200);
        $this->assertCount(1, $searchAfter->json('data'));
        $item = $searchAfter->json('data.0');
        $this->assertEquals($prod->sku, $item['sku']);
        $this->assertEquals(20, $item['stock'], 'Store Stock must reflect confirmed received quantity');

        // 7. Store Pusat STILL does not see this product in their Store Stock (strict location isolation)
        $this->actingAs($this->kasirPusat);
        $searchPusat = $this->getJson("/api/pos/products/search?location_id={$this->storePusat->id}&branch=Toko+Pusat&terminal_code=POS-01&q=Susu");
        $searchPusat->assertStatus(200);
        $this->assertCount(0, $searchPusat->json('data'), 'Stock allocated to Store Cabang must NOT leak into Store Pusat');
    }

    /**
     * Skenario 11: Urutan Antrean Offline yang Benar
     * OPEN SHIFT -> TRANSAKSI / CASH MOVEMENT -> CLOSE SHIFT
     */
    public function test_offline_sync_strict_execution_order_open_transactions_movements_close(): void
    {
        $this->actingAs($this->kasirPusat);

        // Pre-create product stock at Store Pusat
        $prod = Product::create([
            'sku' => 'PRD-ORD-01',
            'barcode' => '899123000001',
            'name' => 'Biskuit Cokelat',
            'category' => 'Makanan',
            'unit' => 'Pack',
            'cost_price' => 4000,
            'price' => 7000,
            'member_price' => 6500,
            'stock' => 50,
            'min_stock' => 5,
            'is_active' => true,
        ]);

        ProductStock::create([
            'product_id' => $prod->id,
            'location_id' => $this->storePusat->id,
            'stock' => 50,
            'min_stock' => 5,
            'is_active' => true,
        ]);

        StockBatch::create([
            'product_id' => $prod->id,
            'location_id' => $this->storePusat->id,
            'batch_number' => 'BCH-ORD-001',
            'cost_price' => 4000,
            'initial_qty' => 50,
            'remaining_qty' => 50,
            'received_at' => now(),
            'expired_date' => now()->addYear()->toDateString(),
            'status' => 'TERSEDIA',
        ]);

        $shiftOffOpen = 'shf_open_' . Str::uuid();
        $shiftOffClose = 'shf_close_' . Str::uuid();
        $trxOff = 'trx_off_' . Str::uuid();
        $movOff = 'mov_off_' . Str::uuid();
        $shiftCode = 'CS-OFF-' . date('Ymd') . '-01';
        $invoiceNum = 'TRX-OFF-' . date('Ymd') . '-POS01-7788';

        // Send multi-operation batch in a single request
        $payload = [
            'offline_shifts' => [
                [
                    'offline_id' => $shiftOffOpen,
                    'action' => 'OPEN',
                    'shift_code' => $shiftCode,
                    'terminal_code' => 'POS-01',
                    'location_id' => $this->storePusat->id,
                    'branch' => 'Toko Pusat',
                    'cashier_nik' => $this->kasirPusat->nik,
                    'cashier_name' => $this->kasirPusat->name,
                    'opening_cash' => 200000,
                    'opened_at' => now()->subHours(2)->toIso8601String(),
                ],
                [
                    'offline_id' => $shiftOffClose,
                    'action' => 'CLOSE',
                    'shift_code' => $shiftCode,
                    'terminal_code' => 'POS-01',
                    'location_id' => $this->storePusat->id,
                    'branch' => 'Toko Pusat',
                    'cashier_nik' => $this->kasirPusat->nik,
                    'cashier_name' => $this->kasirPusat->name,
                    'actual_cash' => 216500,
                    'closing_notes' => 'Shift selesai normal',
                    'closed_at' => now()->toIso8601String(),
                ],
            ],
            'transactions' => [
                [
                    'offline_id' => $trxOff,
                    'invoice_number' => $invoiceNum,
                    'terminal_code' => 'POS-01',
                    'location_id' => $this->storePusat->id,
                    'branch' => 'Toko Pusat',
                    'cashier_nik' => $this->kasirPusat->nik,
                    'cashier_name' => $this->kasirPusat->name,
                    'payment_method' => 'TUNAI',
                    'cash_received' => 50000,
                    'change_returned' => 29000,
                    'subtotal' => 21000,
                    'discount_total' => 0,
                    'tax_total' => 0,
                    'grand_total' => 21000,
                    'created_at' => now()->subHour()->toIso8601String(),
                    'items' => [
                        [
                            'id' => $prod->id,
                            'sku' => $prod->sku,
                            'barcode' => $prod->barcode,
                            'name' => $prod->name,
                            'qty' => 3,
                            'unit' => $prod->unit,
                            'price' => 7000,
                        ],
                    ],
                ],
            ],
            'cash_movements' => [
                [
                    'offline_id' => $movOff,
                    'shift_code' => $shiftCode,
                    'terminal_code' => 'POS-01',
                    'location_id' => $this->storePusat->id,
                    'branch' => 'Toko Pusat',
                    'cashier_nik' => $this->kasirPusat->nik,
                    'type' => 'OUT',
                    'amount' => 4500,
                    'reason' => 'Beli Baterai Jam Dinding POS',
                    'created_at' => now()->subMinutes(30)->toIso8601String(),
                ],
            ],
        ];

        $res = $this->postJson('/api/pos/sync-offline', $payload);
        $res->assertStatus(200);
        $this->assertTrue($res->json('success'));
        $this->assertEquals(1, $res->json('synced_count'));
        $this->assertEquals(0, $res->json('failed_count'));

        // Verify shift was created, transaction attached to shift, movement recorded, and shift closed
        $shift = CashierShift::where('offline_id', $shiftOffOpen)->first();
        $this->assertNotNull($shift);
        $this->assertEquals('CLOSED', $shift->status, 'Shift should be closed after full lifecycle sync');
        $this->assertEquals(200000, (float) $shift->opening_cash);
        $this->assertEquals(21000, (float) $shift->cash_sales_total);
        $this->assertEquals(4500, (float) $shift->cash_out);
        $this->assertEquals(216500, (float) $shift->expected_cash); // 200,000 + 21,000 - 4,500 = 216,500
        $this->assertEquals(216500, (float) $shift->actual_cash);
        $this->assertEquals(0, (float) $shift->cash_difference);

        // Verify transaction is linked to shift
        $trx = Transaction::where('offline_id', $trxOff)->first();
        $this->assertNotNull($trx);
        $this->assertEquals($shift->id, $trx->shift_id);
        $this->assertEquals($this->storePusat->id, $trx->location_id);

        // Verify stock deducted
        $stock = ProductStock::where('product_id', $prod->id)->where('location_id', $this->storePusat->id)->first();
        $this->assertEquals(47, $stock->stock, '50 - 3 = 47 remaining stock');
    }

    /**
     * Skenario 7: Idempotensi Paket Terulang / Re-Sync
     * Mencegah duplikasi data ketika server terlambat merespons atau paket dikirim ulang.
     */
    public function test_packet_loss_re_sync_idempotency_zero_duplicate_rows(): void
    {
        $this->actingAs($this->kasirPusat);

        $prod = Product::create([
            'sku' => 'PRD-IDEMP-01',
            'barcode' => '8999990001',
            'name' => 'Air Mineral 600ml',
            'category' => 'Minuman',
            'unit' => 'Botol',
            'cost_price' => 2000,
            'price' => 3500,
            'member_price' => 3000,
            'stock' => 100,
            'min_stock' => 5,
            'is_active' => true,
        ]);

        ProductStock::create([
            'product_id' => $prod->id,
            'location_id' => $this->storePusat->id,
            'stock' => 100,
            'min_stock' => 5,
            'is_active' => true,
        ]);

        StockBatch::create([
            'product_id' => $prod->id,
            'location_id' => $this->storePusat->id,
            'batch_number' => 'BCH-IDEMP-001',
            'cost_price' => 2000,
            'initial_qty' => 100,
            'remaining_qty' => 100,
            'received_at' => now(),
            'status' => 'TERSEDIA',
        ]);

        // Create shift
        $shift = CashierShift::create([
            'shift_code' => 'CS-IDEMP-01',
            'terminal_code' => 'POS-01',
            'location_id' => $this->storePusat->id,
            'cashier_id' => $this->kasirPusat->id,
            'cashier_nik' => $this->kasirPusat->nik,
            'cashier_name' => $this->kasirPusat->name,
            'opening_cash' => 100000,
            'status' => 'OPEN',
            'opened_at' => now(),
        ]);

        $offlineId = 'trx_idemp_' . Str::uuid();
        $invoiceNumber = 'TRX-OFF-IDEMP-9901';

        $txPayload = [
            'transactions' => [
                [
                    'offline_id' => $offlineId,
                    'invoice_number' => $invoiceNumber,
                    'terminal_code' => 'POS-01',
                    'location_id' => $this->storePusat->id,
                    'branch' => 'Toko Pusat',
                    'cashier_nik' => $this->kasirPusat->nik,
                    'cashier_name' => $this->kasirPusat->name,
                    'payment_method' => 'TUNAI',
                    'cash_received' => 10000,
                    'change_returned' => 3000,
                    'subtotal' => 7000,
                    'grand_total' => 7000,
                    'created_at' => now()->toIso8601String(),
                    'items' => [
                        [
                            'id' => $prod->id,
                            'sku' => $prod->sku,
                            'barcode' => $prod->barcode,
                            'name' => $prod->name,
                            'qty' => 2,
                            'unit' => $prod->unit,
                            'price' => 3500,
                        ],
                    ],
                ],
            ],
            'offline_shifts' => [],
            'cash_movements' => [],
        ];

        // 1. First sync attempt
        $res1 = $this->postJson('/api/pos/sync-offline', $txPayload);
        $res1->assertStatus(200);
        $this->assertEquals(1, $res1->json('synced_count'));
        $this->assertEquals('SYNCED', $res1->json('results.transactions.0.status'));

        // 2. Second sync attempt (simulating client not getting response due to network drop)
        $res2 = $this->postJson('/api/pos/sync-offline', $txPayload);
        $res2->assertStatus(200);
        $this->assertEquals(0, $res2->json('synced_count'));
        $this->assertEquals(1, $res2->json('duplicate_count'));
        $this->assertEquals('ALREADY_SYNCED', $res2->json('results.transactions.0.status'));

        // 3. Third sync attempt
        $res3 = $this->postJson('/api/pos/sync-offline', $txPayload);
        $res3->assertStatus(200);
        $this->assertEquals('ALREADY_SYNCED', $res3->json('results.transactions.0.status'));

        // Strict DB Assertions: ZERO DUPLICATE ROWS
        $this->assertEquals(1, Transaction::where('offline_id', $offlineId)->count(), 'Transaction count must remain strictly 1');
        $this->assertEquals(1, Transaction::where('invoice_number', $invoiceNumber)->count(), 'Invoice count must remain strictly 1');

        $trxRecord = Transaction::where('offline_id', $offlineId)->first();
        $this->assertEquals(1, $trxRecord->items()->count(), 'Items count must remain strictly 1');
        $this->assertEquals(2, $trxRecord->items()->first()->qty);

        // Stock deduction should only occur ONCE (100 - 2 = 98)
        $stock = ProductStock::where('product_id', $prod->id)->where('location_id', $this->storePusat->id)->first();
        $this->assertEquals(98, $stock->stock, 'Stock must only be deducted once across repeated syncs');
    }

    /**
     * Skenario 6: Sinkronisasi Parsial Saat Salah Satu Transaksi Bermasalah
     * Kegagalan satu transaksi tidak boleh menggagalkan transaksi valid lainnya.
     */
    public function test_partial_sync_failure_preserves_other_valid_items(): void
    {
        $this->actingAs($this->kasirPusat);

        $prod = Product::create([
            'sku' => 'PRD-PARTIAL-01',
            'barcode' => '8998887771',
            'name' => 'Kopi Sachet',
            'category' => 'Minuman',
            'unit' => 'Renceng',
            'cost_price' => 12000,
            'price' => 15000,
            'member_price' => 14000,
            'stock' => 50,
            'min_stock' => 5,
            'is_active' => true,
        ]);

        ProductStock::create([
            'product_id' => $prod->id,
            'location_id' => $this->storePusat->id,
            'stock' => 50,
            'min_stock' => 5,
            'is_active' => true,
        ]);

        StockBatch::create([
            'product_id' => $prod->id,
            'location_id' => $this->storePusat->id,
            'batch_number' => 'BCH-PARTIAL-001',
            'cost_price' => 12000,
            'initial_qty' => 50,
            'remaining_qty' => 50,
            'received_at' => now(),
            'status' => 'TERSEDIA',
        ]);

        $shift = CashierShift::create([
            'shift_code' => 'CS-PARTIAL-01',
            'terminal_code' => 'POS-01',
            'location_id' => $this->storePusat->id,
            'cashier_id' => $this->kasirPusat->id,
            'cashier_nik' => $this->kasirPusat->nik,
            'cashier_name' => $this->kasirPusat->name,
            'opening_cash' => 50000,
            'status' => 'OPEN',
            'opened_at' => now(),
        ]);

        $validOffId = 'trx_val_' . Str::uuid();
        $invalidMovOffId = 'mov_inval_' . Str::uuid();

        $batchPayload = [
            'transactions' => [
                // Item 1: VALID Transaction
                [
                    'offline_id' => $validOffId,
                    'invoice_number' => 'TRX-OFF-VAL-01',
                    'terminal_code' => 'POS-01',
                    'location_id' => $this->storePusat->id,
                    'branch' => 'Toko Pusat',
                    'cashier_nik' => $this->kasirPusat->nik,
                    'cashier_name' => $this->kasirPusat->name,
                    'payment_method' => 'TUNAI',
                    'cash_received' => 20000,
                    'change_returned' => 5000,
                    'subtotal' => 15000,
                    'grand_total' => 15000,
                    'created_at' => now()->toIso8601String(),
                    'items' => [
                        [
                            'id' => $prod->id,
                            'sku' => $prod->sku,
                            'barcode' => $prod->barcode,
                            'name' => $prod->name,
                            'qty' => 1,
                            'unit' => $prod->unit,
                            'price' => 15000,
                        ],
                    ],
                ],
            ],
            'offline_shifts' => [],
            'cash_movements' => [
                // Item 2: INVALID Cash Movement (terminal has no matching open shift)
                [
                    'offline_id' => $invalidMovOffId,
                    'shift_code' => 'CS-NONEXISTENT-999',
                    'terminal_code' => 'POS-99',
                    'location_id' => $this->storePusat->id,
                    'branch' => 'Toko Pusat',
                    'cashier_nik' => $this->kasirPusat->nik,
                    'type' => 'OUT',
                    'amount' => 10000,
                    'reason' => 'Invalid Shift Movement',
                ],
            ],
        ];

        $res = $this->postJson('/api/pos/sync-offline', $batchPayload);
        $res->assertStatus(200);
        $this->assertTrue($res->json('success'));
        $this->assertTrue($res->json('partial'), 'Response should flag partial success');
        $this->assertEquals(1, $res->json('synced_count'), '1 valid item synced');
        $this->assertEquals(1, $res->json('failed_count'), '1 invalid item failed');

        // Valid transaction committed to DB
        $this->assertEquals(1, Transaction::where('offline_id', $validOffId)->count());

        // Invalid cash movement NOT committed to DB
        $this->assertEquals(0, CashMovement::where('offline_id', $invalidMovOffId)->count());

        // Stock deduction only happened for the valid transaction (50 - 1 = 49)
        $stock = ProductStock::where('product_id', $prod->id)->where('location_id', $this->storePusat->id)->first();
        $this->assertEquals(49, $stock->stock);
    }
}
