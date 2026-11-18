<?php

namespace Tests\Feature;

use App\Models\CashMovement;
use App\Models\CashierShift;
use App\Models\GoodsReceipt;
use App\Models\GoodsReceiptItem;
use App\Models\Location;
use App\Models\Member;
use App\Models\Product;
use App\Models\ProductStock;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\StockBatch;
use App\Models\StoreReturnHeader;
use App\Models\StoreReturnItem;
use App\Models\Supplier;
use App\Models\Transaction;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class Fase3IntegrationTest extends TestCase
{
    protected Location $warehouse;
    protected Location $storePusat;
    protected Location $storeCabangA;
    protected Location $storeCabangB;
    protected User $manager;
    protected User $owner;
    protected User $kasirPusat;
    protected User $kasirCabangA;
    protected Supplier $supplier;

    protected function setUp(): void
    {
        parent::setUp();

        $this->warehouse = Location::firstOrCreate(
            ['code' => 'GDG-001'],
            ['name' => 'Gudang Pusat', 'type' => 'WAREHOUSE', 'status' => 'AKTIF', 'is_active' => true]
        );

        $this->storePusat = Location::firstOrCreate(
            ['code' => 'CAB-001'],
            ['name' => 'Toko Pusat', 'type' => 'STORE', 'status' => 'AKTIF', 'is_active' => true]
        );

        $this->storeCabangA = Location::firstOrCreate(
            ['code' => 'CAB-002'],
            ['name' => 'Cabang A', 'type' => 'STORE', 'status' => 'AKTIF', 'is_active' => true]
        );

        $this->storeCabangB = Location::firstOrCreate(
            ['code' => 'CAB-003'],
            ['name' => 'Cabang B', 'type' => 'STORE', 'status' => 'AKTIF', 'is_active' => true]
        );

        $this->manager = User::firstOrCreate(
            ['username' => 'mgr_fase3'],
            [
                'name' => 'Manager Operasional',
                'nik' => '880001',
                'email' => 'mgr3@pos.local',
                'role' => 'Manager',
                'pin' => '654321',
                'branch' => 'Toko Pusat',
                'password' => Hash::make('password'),
                'is_active' => true,
            ]
        );

        $this->owner = User::firstOrCreate(
            ['username' => 'owner_fase3'],
            [
                'name' => 'Owner ILP',
                'nik' => '880000',
                'email' => 'owner3@pos.local',
                'role' => 'Owner',
                'pin' => '999888',
                'branch' => 'Toko Pusat',
                'password' => Hash::make('password'),
                'is_active' => true,
            ]
        );

        $this->kasirPusat = User::firstOrCreate(
            ['username' => 'ksr_pusat3'],
            [
                'name' => 'Kasir Toko Pusat',
                'nik' => '880002',
                'email' => 'ksr_p3@pos.local',
                'role' => 'Kasir Utama',
                'pin' => '111222',
                'branch' => 'Toko Pusat',
                'password' => Hash::make('password'),
                'is_active' => true,
            ]
        );

        $this->kasirCabangA = User::firstOrCreate(
            ['username' => 'ksr_cabanga3'],
            [
                'name' => 'Kasir Cabang A',
                'nik' => '880003',
                'email' => 'ksr_ca3@pos.local',
                'role' => 'Kasir Utama',
                'pin' => '111222',
                'branch' => 'Cabang A',
                'password' => Hash::make('password'),
                'is_active' => true,
            ]
        );

        $this->supplier = Supplier::firstOrCreate(
            ['code' => 'SUP-FASE3'],
            [
                'name' => 'PT Pangan Mandiri Nusantara',
                'company_name' => 'PT Pangan Mandiri',
                'phone' => '08123456789',
                'address' => 'Kawasan Industri Lampung',
                'category' => 'DISTRIBUTOR',
                'status' => 'AKTIF',
                'is_consignment' => false,
            ]
        );
    }

    /**
     * Flow 1: Full Lifecycle Trace:
     * Supplier Reception -> Gudang Stock -> Distribution (In-Transit) -> Store Confirmation -> Store Stock -> POS Checkout -> Reports -> Owner Dashboard.
     */
    public function test_flow_1_full_lifecycle_from_supplier_to_pos_to_reports(): void
    {
        $uniqueSuffix = Str::lower(Str::random(6));

        // 1. Create Product in Stock Master (0 stock across stores)
        $product = Product::create([
            'sku' => 'SKU-F3-LIFECYCLE-' . $uniqueSuffix,
            'barcode' => '8999' . rand(100000, 999999),
            'name' => 'Susu Formula Premium ' . $uniqueSuffix,
            'category' => 'Susu & Nutrisi',
            'unit' => 'Klg',
            'cost_price' => 80000,
            'price' => 105000,
            'member_price' => 100000,
            'stock' => 0,
            'min_stock' => 5,
            'is_active' => true,
            'is_taxable' => false,
            'is_consignment' => false,
        ]);

        // 2. Receive Purchase Order from Supplier at Gudang Pusat
        $purchase = Purchase::create([
            'purchase_number' => 'PO-F3-' . date('Ymd') . '-' . Str::upper(Str::random(4)),
            'supplier_id' => $this->supplier->id,
            'supplier_invoice_number' => 'INV-SUP-9988',
            'purchase_date' => now()->toDateString(),
            'total_amount' => 8000000, // 100 kaleng @ Rp 80.000
            'status' => Purchase::STATUS_MENUNGGU,
            'location_id' => $this->warehouse->id,
            'created_by' => $this->manager->id,
        ]);

        $purchaseItem = PurchaseItem::create([
            'purchase_id' => $purchase->id,
            'product_id' => $product->id,
            'unit' => 'Klg',
            'conversion_factor' => 1,
            'qty' => 100,
            'received_qty' => 0,
            'purchase_price' => 80000,
            'discount_amount' => 0,
            'subtotal' => 8000000,
            'ownership_type' => 'OUTRIGHT',
        ]);

        // Receive goods at Gudang Pusat via Purchase API
        $receivePayload = [
            'received_at' => now()->toDateTimeString(),
            'notes' => 'Penerimaan kontainer pertama dari supplier',
            'items' => [
                [
                    'purchase_item_id' => $purchaseItem->id,
                    'qty_received_now' => 100,
                    'actual_unit_price' => 80000,
                    'condition' => 'BAIK',
                    'expired_date' => now()->addMonths(18)->toDateString(),
                    'batch_number' => 'BTH-SUP-' . $uniqueSuffix,
                ],
            ],
        ];

        $receiveResp = $this->actingAs($this->manager)
            ->postJson("/api/purchases/{$purchase->id}/receive", $receivePayload);
        $receiveResp->assertStatus(200);

        // Verify Gudang Pusat stock is 100; Store stocks are strictly 0
        $warehouseStock = ProductStock::where('product_id', $product->id)->where('location_id', $this->warehouse->id)->value('stock');
        $storeCabangAStock = ProductStock::where('product_id', $product->id)->where('location_id', $this->storeCabangA->id)->value('stock');
        $this->assertEquals(100, $warehouseStock);
        $this->assertNull($storeCabangAStock);

        // Verify Kasir at Cabang A CANNOT sell the product yet (returns 0 stock or not in store catalog)
        $searchResp = $this->actingAs($this->kasirCabangA)
            ->getJson("/api/pos/products/search?q={$product->barcode}&terminal_code=POS-06&location_id={$this->storeCabangA->id}");
        $searchResp->assertStatus(200);
        $this->assertCount(0, $searchResp->json('data'));

        // 3. Manager Dispatches 30 units to Cabang A (In-Transit)
        $dispatchPayload = [
            'warehouseOrigin' => 'Gudang Pusat',
            'destinationStore' => 'Cabang A',
            'from_location_id' => $this->warehouse->id,
            'to_location_id' => $this->storeCabangA->id,
            'driverName' => 'Pak Slamet',
            'plateNumber' => 'BE 1234 ILP',
            'status' => 'MENUNGGU_VERIFIKASI',
            'items' => [
                [
                    'product_id' => $product->id,
                    'qtySent' => 30,
                    'condition' => 'BAIK',
                ],
            ],
        ];

        $dispatchResp = $this->actingAs($this->manager)
            ->postJson('/api/goods-receipts', $dispatchPayload);
        $dispatchResp->assertStatus(201);
        $receiptId = $dispatchResp->json('data.id');

        // Verify Gudang stock reduced by 30 (now 70); Store stock remains 0 (In-Transit goods cannot be sold)
        $warehouseStockAfter = ProductStock::where('product_id', $product->id)->where('location_id', $this->warehouse->id)->value('stock');
        $this->assertEquals(70, $warehouseStockAfter);
        $this->assertNull(ProductStock::where('product_id', $product->id)->where('location_id', $this->storeCabangA->id)->first());

        // 4. Cabang A Store Confirmation (Verifies 30 units received in good condition)
        $receiptItem = GoodsReceiptItem::where('goods_receipt_id', $receiptId)->firstOrFail();
        $verifyPayload = [
            'location_id' => $this->storeCabangA->id,
            'receiver_name' => $this->kasirCabangA->name,
            'items' => [
                [
                    'id' => $receiptItem->id,
                    'qty_received' => 30,
                    'condition' => 'BAIK',
                ],
            ],
        ];

        $verifyResp = $this->actingAs($this->kasirCabangA)
            ->postJson("/api/goods-receipts/{$receiptId}/verify", $verifyPayload);
        $verifyResp->assertStatus(200);

        // Verify Stock Toko at Cabang A is now exactly 30 units with FEFO batch
        $storeAStockAfterVerify = ProductStock::where('product_id', $product->id)->where('location_id', $this->storeCabangA->id)->value('stock');
        $this->assertEquals(30, $storeAStockAfterVerify);
        $batchStoreA = StockBatch::where('product_id', $product->id)->where('location_id', $this->storeCabangA->id)->first();
        $this->assertNotNull($batchStoreA);
        $this->assertEquals(30, (int) $batchStoreA->remaining_qty);

        // 5. POS Checkout at Cabang A on Terminal POS-06
        // Open shift at POS-06
        $shiftPayload = [
            'terminal_code' => 'POS-06',
            'cashier_nik' => $this->kasirCabangA->nik,
            'cashier_name' => $this->kasirCabangA->name,
            'opening_cash' => 200000,
            'location_id' => $this->storeCabangA->id,
            'branch' => 'Cabang A',
            'pin' => '111222',
        ];
        $shiftResp = $this->actingAs($this->kasirCabangA)
            ->postJson('/api/shift/open', $shiftPayload);
        $shiftResp->assertStatus(201);
        $shiftId = $shiftResp->json('data.id');

        // Checkout 5 units of product (Tunai Rp 600.000 for total Rp 525.000)
        $checkoutPayload = [
            'offline_id' => 'off_f3_flow1_' . $uniqueSuffix,
            'shift_id' => $shiftId,
            'terminal_code' => 'POS-06',
            'location_id' => $this->storeCabangA->id,
            'branch' => 'Cabang A',
            'cashier_nik' => $this->kasirCabangA->nik,
            'cashier_name' => $this->kasirCabangA->name,
            'payment_method' => 'TUNAI',
            'cash_received' => 600000,
            'items' => [
                [
                    'product_id' => $product->id,
                    'sku' => $product->sku,
                    'barcode' => $product->barcode,
                    'name' => $product->name,
                    'qty' => 5,
                    'price' => 105000,
                ],
            ],
        ];

        $checkoutResp = $this->actingAs($this->kasirCabangA)
            ->postJson('/api/pos/checkout', $checkoutPayload);
        $checkoutResp->assertStatus(201);
        $this->assertEquals(525000, $checkoutResp->json('data.grand_total'));
        $this->assertEquals(75000, $checkoutResp->json('data.change_returned'));

        // Verify Store Stock Cabang A is reduced from 30 to 25
        $storeAStockAfterSale = ProductStock::where('product_id', $product->id)->where('location_id', $this->storeCabangA->id)->value('stock');
        $this->assertEquals(25, $storeAStockAfterSale);

        // Verify Transaction has correct location_id and terminal_code
        $tx = Transaction::where('offline_id', 'off_f3_flow1_' . $uniqueSuffix)->firstOrFail();
        $this->assertEquals($this->storeCabangA->id, $tx->location_id);
        $this->assertEquals('POS-06', $tx->terminal_code);

        // 6. Reports & Consolidation: Check that Cabang A sales reflect strictly in Cabang A
        $reportResp = $this->actingAs($this->manager)
            ->getJson("/api/reports/consolidated-branches?start_date=" . now()->toDateString() . "&end_date=" . now()->toDateString());
        $reportResp->assertStatus(200);

        $branchBreakdown = collect($reportResp->json('branch_breakdown'));
        $cabangARow = $branchBreakdown->firstWhere('location_id', $this->storeCabangA->id);
        $this->assertNotNull($cabangARow);
        $this->assertGreaterThanOrEqual(525000, (float) $cabangARow['total_revenue']);

        // 7. Owner Dashboard: Real-time monitoring reflects the sale
        $ownerResp = $this->actingAs($this->owner)
            ->getJson("/api/owner/dashboard-summary?date=" . now()->toDateString());
        $ownerResp->assertStatus(200);
        $this->assertGreaterThanOrEqual(525000, (float) $ownerResp->json('data.total_sales_today'));
    }

    /**
     * Flow 2: Store Return & Segregation from Sellable Stock Master:
     * Store returns damaged goods -> Stock deducted from store -> Approved by supervisor -> Quarantined without entering sellable Gudang stock.
     * Rejection restores store stock.
     */
    public function test_flow_2_store_return_lifecycle_and_segregation(): void
    {
        $uniqueSuffix = Str::lower(Str::random(6));

        $product = Product::create([
            'sku' => 'SKU-F3-RET-' . $uniqueSuffix,
            'barcode' => '8999' . rand(100000, 999999),
            'name' => 'Biskuit Kaleng Renyah ' . $uniqueSuffix,
            'category' => 'Biskuit',
            'unit' => 'Klg',
            'cost_price' => 30000,
            'price' => 45000,
            'stock' => 0,
            'min_stock' => 5,
            'is_active' => true,
        ]);

        // Seed Store Stock at Cabang A with 20 units
        app(\App\Services\LocationStockService::class)->addStock($product->id, $this->storeCabangA->id, 20, [
            'unit_cost' => 30000,
            'received_at' => now(),
            'expired_date' => now()->addMonths(6)->toDateString(),
            'notes' => 'Stok awal untuk pengujian retur',
        ]);

        $initialStoreStock = ProductStock::where('product_id', $product->id)->where('location_id', $this->storeCabangA->id)->value('stock');
        $this->assertEquals(20, $initialStoreStock);

        // 1. Cabang A submits return document for 3 damaged units to DC
        $returnPayload = [
            'direction' => 'toko_ke_gudang',
            'condition_type' => 'rusak',
            'product_id' => $product->id,
            'qty' => 3,
            'reason' => 'Kemasan penyok saat display',
            'from_location_id' => $this->storeCabangA->id,
            'use_fleet' => true,
        ];

        $returnResp = $this->actingAs($this->kasirCabangA)
            ->postJson('/api/returns', $returnPayload);
        $returnResp->assertStatus(201);
        $returnId = $returnResp->json('data.id');

        // Store stock reduced from 20 to 17
        $stockAfterSubmit = ProductStock::where('product_id', $product->id)->where('location_id', $this->storeCabangA->id)->value('stock');
        $this->assertEquals(17, $stockAfterSubmit);

        // Warehouse sellable stock must remain 0 (damaged return cannot enter sellable warehouse stock)
        $warehouseStock = ProductStock::where('product_id', $product->id)->where('location_id', $this->warehouse->id)->value('stock');
        $this->assertNull($warehouseStock);

        // 2. Supervisor approves return (status: disetujui)
        $approveResp = $this->actingAs($this->manager)
            ->putJson("/api/returns/{$returnId}/status", ['status' => 'disetujui']);
        $approveResp->assertStatus(200);

        // Damaged goods stay quarantined in return items, NEVER in sellable ProductStock of Gudang Pusat
        $warehouseStockAfterApprove = ProductStock::where('product_id', $product->id)->where('location_id', $this->warehouse->id)->value('stock');
        $this->assertNull($warehouseStockAfterApprove);

        // 3. Test Return Rejection: Cabang A submits another return for 2 units
        $returnPayload2 = [
            'direction' => 'toko_ke_gudang',
            'condition_type' => 'rusak',
            'product_id' => $product->id,
            'qty' => 2,
            'reason' => 'Salah hitung',
            'from_location_id' => $this->storeCabangA->id,
            'use_fleet' => true,
        ];
        $returnResp2 = $this->actingAs($this->kasirCabangA)
            ->postJson('/api/returns', $returnPayload2);
        $returnResp2->assertStatus(201);
        $returnId2 = $returnResp2->json('data.id');

        $this->assertEquals(15, ProductStock::where('product_id', $product->id)->where('location_id', $this->storeCabangA->id)->value('stock'));

        // Supervisor rejects return (status: ditolak) -> Stock safely restored to 17
        $rejectResp = $this->actingAs($this->manager)
            ->putJson("/api/returns/{$returnId2}/status", ['status' => 'ditolak']);
        $rejectResp->assertStatus(200);

        $restoredStock = ProductStock::where('product_id', $product->id)->where('location_id', $this->storeCabangA->id)->value('stock');
        $this->assertEquals(17, $restoredStock);
    }

    /**
     * Flow 3: Complete Cashier Shift & Cash Variance Reconciliation:
     * Shift Open -> Cash In -> Cash Out -> Cash Sale -> QRIS Sale -> Member Kasbon Sale -> Shift Close -> Variance Status Check.
     */
    public function test_flow_3_complete_cashier_shift_and_cash_reconciliation(): void
    {
        $uniqueSuffix = Str::lower(Str::random(6));

        // 1. Open Shift at POS-01 (Toko Pusat) with Rp 150.000 modal awal
        $openResp = $this->actingAs($this->kasirPusat)->postJson('/api/shift/open', [
            'terminal_code' => 'POS-01',
            'cashier_nik' => $this->kasirPusat->nik,
            'cashier_name' => $this->kasirPusat->name,
            'opening_cash' => 150000,
            'location_id' => $this->storePusat->id,
            'branch' => 'Toko Pusat',
            'pin' => '111222',
        ]);
        $openResp->assertStatus(201);
        $shiftId = $openResp->json('data.id');

        // 2. Cash In: Modal tambahan Rp 50.000 -> Expected: 200.000
        $inResp = $this->actingAs($this->kasirPusat)->postJson('/api/shift/cash-movement', [
            'shift_id' => $shiftId,
            'terminal_code' => 'POS-01',
            'type' => 'IN',
            'amount' => 50000,
            'reason' => 'Tambah uang kembalian pecahan kecil',
        ]);
        $inResp->assertStatus(200);
        $this->assertEquals(200000, $inResp->json('data.expected_cash'));

        // 3. Cash Out: Beli lakban & kantong kresek Rp 15.000 -> Expected: 185.000
        $outResp = $this->actingAs($this->kasirPusat)->postJson('/api/shift/cash-movement', [
            'shift_id' => $shiftId,
            'terminal_code' => 'POS-01',
            'type' => 'OUT',
            'amount' => 15000,
            'reason' => 'Beli isi ulang lakban packing',
        ]);
        $outResp->assertStatus(200);
        $this->assertEquals(185000, $outResp->json('data.expected_cash'));

        // Create product for sales
        $prod = Product::create([
            'sku' => 'SKU-F3-SHF-' . $uniqueSuffix,
            'barcode' => '8999' . rand(100000, 999999),
            'name' => 'Minyak Goreng 1L ' . $uniqueSuffix,
            'category' => 'Sembako',
            'unit' => 'Pch',
            'cost_price' => 14000,
            'price' => 18000,
            'member_price' => 17000,
            'stock' => 0,
            'min_stock' => 5,
            'is_active' => true,
            'is_taxable' => false,
        ]);
        app(\App\Services\LocationStockService::class)->addStock($prod->id, $this->storePusat->id, 50, [
            'unit_cost' => 14000,
            'received_at' => now(),
        ]);

        // 4. Cash Sale: 2 pouch @ Rp 18.000 = Rp 36.000 (Tunai Rp 50.000, kembalian Rp 14.000) -> Expected: 185.000 + 36.000 = 221.000
        $saleTunaiResp = $this->actingAs($this->kasirPusat)->postJson('/api/pos/checkout', [
            'shift_id' => $shiftId,
            'terminal_code' => 'POS-01',
            'location_id' => $this->storePusat->id,
            'cashier_nik' => $this->kasirPusat->nik,
            'payment_method' => 'TUNAI',
            'cash_received' => 50000,
            'items' => [
                ['product_id' => $prod->id, 'sku' => $prod->sku, 'barcode' => $prod->barcode, 'name' => $prod->name, 'qty' => 2, 'price' => 18000],
            ],
        ]);
        $saleTunaiResp->assertStatus(201);
        $this->assertEquals(36000, $saleTunaiResp->json('data.grand_total'));

        // 5. QRIS Sale: 1 pouch @ Rp 18.000 = Rp 18.000 -> Expected Cash remains 221.000
        $saleQrisResp = $this->actingAs($this->kasirPusat)->postJson('/api/pos/checkout', [
            'shift_id' => $shiftId,
            'terminal_code' => 'POS-01',
            'location_id' => $this->storePusat->id,
            'cashier_nik' => $this->kasirPusat->nik,
            'payment_method' => 'QRIS',
            'payment_reference' => 'QRIS-TEST-9988',
            'items' => [
                ['product_id' => $prod->id, 'sku' => $prod->sku, 'barcode' => $prod->barcode, 'name' => $prod->name, 'qty' => 1, 'price' => 18000],
            ],
        ]);
        $saleQrisResp->assertStatus(201);

        // 6. Member Kasbon Sale: 2 pouch @ Rp 17.000 (Member price) = Rp 34.000 -> Expected Cash remains 221.000
        $member = Member::create([
            'nik' => 'MBR-' . $uniqueSuffix,
            'member_code' => 'KOP-' . $uniqueSuffix,
            'name' => 'Budi Santoso',
            'department' => 'Pabrik Divisi A',
            'branch' => 'Toko Pusat',
            'credit_limit' => 1000000,
            'current_debt' => 0,
            'status' => 'AKTIF',
        ]);

        $saleKreditResp = $this->actingAs($this->kasirPusat)->postJson('/api/pos/checkout', [
            'shift_id' => $shiftId,
            'terminal_code' => 'POS-01',
            'location_id' => $this->storePusat->id,
            'cashier_nik' => $this->kasirPusat->nik,
            'payment_method' => 'KREDIT',
            'member_nik' => $member->nik,
            'items' => [
                ['product_id' => $prod->id, 'sku' => $prod->sku, 'barcode' => $prod->barcode, 'name' => $prod->name, 'qty' => 2, 'price' => 17000, 'is_member' => true],
            ],
        ]);
        $saleKreditResp->assertStatus(201);
        $this->assertEquals(34000, $saleKreditResp->json('data.grand_total'));

        // Member debt is updated to Rp 34.000
        $member->refresh();
        $this->assertEquals(34000, (float) $member->current_debt);

        // 7. Close Shift with exact physical cash Rp 221.000 -> Status: SESUAI, Variance: 0
        $closeResp = $this->actingAs($this->kasirPusat)->postJson('/api/shift/close', [
            'shift_id' => $shiftId,
            'actual_cash' => 221000,
            'closing_notes' => 'Kas fisik laci dihitung bersama supervisor',
        ]);
        $closeResp->assertStatus(200);
        $this->assertEquals(221000, $closeResp->json('data.expected_cash'));
        $this->assertEquals(221000, $closeResp->json('data.actual_cash'));
        $this->assertEquals(0, $closeResp->json('data.variance'));
        $this->assertEquals('SESUAI', $closeResp->json('data.variance_status'));
        $this->assertEquals(36000, $closeResp->json('data.cash_sales_total'));
        $this->assertEquals(18000, $closeResp->json('data.qris_sales_total'));
        $this->assertEquals(34000, $closeResp->json('data.credit_sales_total'));
        $this->assertEquals(88000, $closeResp->json('data.total_sales'));

        // 8. Verify Accounting Cash Reconciliation Report
        $reconResp = $this->actingAs($this->manager)
            ->getJson("/api/reports/cash-reconciliation?start_date=" . now()->toDateString() . "&end_date=" . now()->toDateString());
        $reconResp->assertStatus(200);
        $this->assertEquals(100, (float) $reconResp->json('summary.accuracy_rate_percent'));
        $this->assertEquals(0, (float) $reconResp->json('summary.net_variance'));
    }

    /**
     * Flow 4: Void & Refund Security with Supervisor Authorization and Stock Reversal.
     */
    public function test_flow_4_void_and_refund_with_supervisor_authorization(): void
    {
        $uniqueSuffix = Str::lower(Str::random(6));

        $prod = Product::create([
            'sku' => 'SKU-F3-VOID-' . $uniqueSuffix,
            'barcode' => '8999' . rand(100000, 999999),
            'name' => 'Kopi Bubuk 250g ' . $uniqueSuffix,
            'category' => 'Kopi',
            'unit' => 'Bks',
            'cost_price' => 15000,
            'price' => 22000,
            'stock' => 0,
            'min_stock' => 5,
            'is_active' => true,
        ]);
        app(\App\Services\LocationStockService::class)->addStock($prod->id, $this->storePusat->id, 20, [
            'unit_cost' => 15000,
            'received_at' => now(),
            'expired_date' => now()->addYear()->toDateString(),
        ]);

        // Open shift
        $shift = CashierShift::create([
            'shift_code' => 'CS-VD-' . $uniqueSuffix,
            'terminal_code' => 'POS-01',
            'location_id' => $this->storePusat->id,
            'branch' => 'Toko Pusat',
            'user_id' => $this->kasirPusat->id,
            'cashier_nik' => $this->kasirPusat->nik,
            'cashier_name' => $this->kasirPusat->name,
            'opening_cash' => 100000,
            'status' => 'OPEN',
            'opened_at' => now(),
        ]);

        // Perform checkout 4 units (stock drops from 20 to 16)
        $checkoutResp = $this->actingAs($this->kasirPusat)->postJson('/api/pos/checkout', [
            'shift_id' => $shift->id,
            'terminal_code' => 'POS-01',
            'location_id' => $this->storePusat->id,
            'cashier_nik' => $this->kasirPusat->nik,
            'payment_method' => 'TUNAI',
            'cash_received' => 100000,
            'items' => [
                ['product_id' => $prod->id, 'sku' => $prod->sku, 'barcode' => $prod->barcode, 'name' => $prod->name, 'qty' => 4, 'price' => 22000],
            ],
        ]);
        $checkoutResp->assertStatus(201);
        $invoice = $checkoutResp->json('data.invoice_number');

        $this->assertEquals(16, ProductStock::where('product_id', $prod->id)->where('location_id', $this->storePusat->id)->value('stock'));

        // 1. Kasir attempts self-void without supervisor PIN -> 403 Forbidden
        $selfVoidResp = $this->actingAs($this->kasirPusat)->postJson('/api/pos/void/transaction', [
            'invoice_number' => $invoice,
            'pin_or_password' => '111222', // Cashier PIN
            'reason' => 'Salah input',
        ]);
        $selfVoidResp->assertStatus(403);
        $this->assertStringContainsString('Akun Kasir tidak memiliki wewenang', $selfVoidResp->json('message'));

        // 2. Supervisor authorizes void with valid Supervisor PIN (654321) -> 200 OK
        $spvVoidResp = $this->actingAs($this->kasirPusat)->postJson('/api/pos/void/transaction', [
            'invoice_number' => $invoice,
            'pin_or_password' => '654321', // Supervisor PIN
            'reason' => 'Pelanggan membatalkan pesanan di kasir',
        ]);
        $spvVoidResp->assertStatus(200);

        // 3. Verify ProductStock is precisely restored from 16 back to 20!
        $this->assertEquals(20, ProductStock::where('product_id', $prod->id)->where('location_id', $this->storePusat->id)->value('stock'));

        // 4. Verify transaction status is VOIDED
        $tx = Transaction::where('invoice_number', $invoice)->firstOrFail();
        $this->assertEquals('VOIDED', $tx->status);

        // 5. Attempting to void an already VOIDED transaction is rejected (422)
        $reVoidResp = $this->actingAs($this->kasirPusat)->postJson('/api/pos/void/transaction', [
            'invoice_number' => $invoice,
            'pin_or_password' => '654321',
            'reason' => 'Double void attempt',
        ]);
        $reVoidResp->assertStatus(422);
    }

    /**
     * Flow 5: Security Guards: Multi-Location, Terminal Constraints, and Role Separation.
     */
    public function test_flow_5_multi_location_and_terminal_security_guards(): void
    {
        // 1. Kasir Cabang A attempting to specify terminal POS-01 (Toko Pusat) is rejected (Terminal Location Mismatch)
        $mismatchResp = $this->actingAs($this->kasirCabangA)->postJson('/api/shift/open', [
            'terminal_code' => 'POS-01', // belongs to Toko Pusat
            'cashier_nik' => $this->kasirCabangA->nik,
            'cashier_name' => $this->kasirCabangA->name,
            'opening_cash' => 100000,
            'location_id' => $this->storeCabangA->id, // mismatch with POS-01!
            'pin' => '111222',
        ]);
        $mismatchResp->assertStatus(422);
        $this->assertStringContainsString('tidak sesuai dengan lokasi', $mismatchResp->json('message'));

        // 2. Offline Sync Anti-Spoofing: Cashier attempting to forge another cashier's NIK is rejected (403 Identity Mismatch)
        $spoofSyncResp = $this->actingAs($this->kasirPusat)->postJson('/api/pos/sync-offline', [
            'transactions' => [
                [
                    'offline_id' => 'off_spoof_001',
                    'invoice_number' => 'TRX-OFF-SPOOF',
                    'terminal_code' => 'POS-01',
                    'location_id' => $this->storePusat->id,
                    'cashier_nik' => '999999_DIFFERENT_NIK', // Forged NIK!
                    'payment_method' => 'TUNAI',
                    'grand_total' => 50000,
                    'items' => [['sku' => 'ITM-01', 'name' => 'Barang', 'qty' => 1, 'price' => 50000]],
                ],
            ],
        ]);
        $spoofSyncResp->assertStatus(403);
        $this->assertEquals('IDENTITY_MISMATCH', $spoofSyncResp->json('error_code'));
    }

    /**
     * Flow 6: Offline Batch Sync Idempotency and Zero Duplicate Guarantee.
     */
    public function test_flow_6_offline_batch_sync_idempotency_and_zero_duplicate(): void
    {
        $uniqueSuffix = Str::lower(Str::random(6));

        $prod = Product::create([
            'sku' => 'SKU-F3-SYNC-' . $uniqueSuffix,
            'barcode' => '8999' . rand(100000, 999999),
            'name' => 'Gula Pasir 1kg ' . $uniqueSuffix,
            'category' => 'Sembako',
            'unit' => 'Pcs',
            'cost_price' => 12000,
            'price' => 16000,
            'stock' => 0,
            'is_active' => true,
        ]);
        app(\App\Services\LocationStockService::class)->addStock($prod->id, $this->storePusat->id, 50, [
            'unit_cost' => 12000,
            'received_at' => now(),
        ]);

        $offShiftId = 'shf_off_' . $uniqueSuffix;
        $offTxId = 'trx_off_' . $uniqueSuffix;
        $offMovId = 'mov_off_' . $uniqueSuffix;
        $shiftCode = 'CS-OFF-' . $uniqueSuffix;

        $batchPayload = [
            'offline_shifts' => [
                [
                    'offline_id' => $offShiftId . '_open',
                    'action' => 'OPEN',
                    'shift_code' => $shiftCode,
                    'terminal_code' => 'POS-01',
                    'location_id' => $this->storePusat->id,
                    'cashier_nik' => $this->kasirPusat->nik,
                    'cashier_name' => $this->kasirPusat->name,
                    'opening_cash' => 100000,
                    'opened_at' => now()->subHours(2)->toIso8601String(),
                ],
                [
                    'offline_id' => $offShiftId . '_close',
                    'action' => 'CLOSE',
                    'shift_code' => $shiftCode,
                    'terminal_code' => 'POS-01',
                    'location_id' => $this->storePusat->id,
                    'cashier_nik' => $this->kasirPusat->nik,
                    'cashier_name' => $this->kasirPusat->name,
                    'actual_cash' => 148000,
                    'closed_at' => now()->toIso8601String(),
                ],
            ],
            'transactions' => [
                [
                    'offline_id' => $offTxId,
                    'invoice_number' => 'TRX-OFF-' . $uniqueSuffix,
                    'terminal_code' => 'POS-01',
                    'location_id' => $this->storePusat->id,
                    'branch' => 'Toko Pusat',
                    'cashier_nik' => $this->kasirPusat->nik,
                    'cashier_name' => $this->kasirPusat->name,
                    'payment_method' => 'TUNAI',
                    'cash_received' => 50000,
                    'change_returned' => 2000,
                    'subtotal' => 48000,
                    'grand_total' => 48000,
                    'created_at' => now()->subHour()->toIso8601String(),
                    'items' => [
                        ['product_id' => $prod->id, 'sku' => $prod->sku, 'barcode' => $prod->barcode, 'name' => $prod->name, 'qty' => 3, 'price' => 16000],
                    ],
                ],
            ],
            'cash_movements' => [],
        ];

        // First synchronization run
        $syncResp1 = $this->actingAs($this->kasirPusat)->postJson('/api/pos/sync-offline', $batchPayload);
        $syncResp1->assertStatus(200);
        $this->assertEquals(1, $syncResp1->json('synced_count'));
        $this->assertEquals(0, $syncResp1->json('duplicate_count'));

        // Verify stock reduced by 3 (from 50 to 47)
        $this->assertEquals(47, ProductStock::where('product_id', $prod->id)->where('location_id', $this->storePusat->id)->value('stock'));

        // Second synchronization run with identical payload (Simulating packet loss re-try)
        $syncResp2 = $this->actingAs($this->kasirPusat)->postJson('/api/pos/sync-offline', $batchPayload);
        $syncResp2->assertStatus(200);
        $this->assertEquals(0, $syncResp2->json('synced_count'));
        $this->assertEquals(1, $syncResp2->json('duplicate_count'));

        // Verify stock is STILL exactly 47 (NOT reduced again!)
        $this->assertEquals(47, ProductStock::where('product_id', $prod->id)->where('location_id', $this->storePusat->id)->value('stock'));

        // Verify exactly 1 Transaction record exists with this offline_id
        $this->assertEquals(1, Transaction::where('offline_id', $offTxId)->count());
    }
}
