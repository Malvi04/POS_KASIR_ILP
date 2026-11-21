<?php

namespace Tests\Feature;

use App\Models\CashierShift;
use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\StockOpname;
use App\Models\Supplier;
use App\Models\User;
use Carbon\Carbon;
use Tests\TestCase;
use Tests\Traits\SeedsTestFixtures;

class InventoryConsignmentModulCTest extends TestCase
{
    use SeedsTestFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedTestFixtures();
    }

    /**
     * Test Stock Threshold Alert & Two-Tier Expired Alarm System.
     */
    public function test_can_list_inventory_with_stock_alerts_and_expired_alarm_system(): void
    {
        $response = $this->getJson('/api/products');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'summary' => [
                    'total_products',
                    'low_stock_count',
                    'out_of_stock_count',
                    'critical_expired_count',
                    'warning_expired_count',
                    'consignment_count',
                    'total_stock_units',
                    'total_inventory_value',
                ],
                'categories',
                'data',
                'meta',
            ]);

        // Filter low stock
        $lowStockRes = $this->getJson('/api/products?stock_filter=STOK_RENDAH');
        $lowStockRes->assertStatus(200);
        foreach ($lowStockRes->json('data') as $p) {
            $this->assertEquals('STOK_RENDAH', $p['stock_status']);
        }

        // Filter critical expired (Merah <= H-14)
        $critRes = $this->getJson('/api/products?expired_filter=CRITICAL');
        $critRes->assertStatus(200);
        foreach ($critRes->json('data') as $p) {
            $this->assertEquals('CRITICAL', $p['expiration_alert_level']);
            $this->assertLessThanOrEqual(14, $p['days_until_expiration']);
        }
    }

    /**
     * Test Multi-Satuan Barang (Unit Conversion): Beli 1 Dus = 40 Pcs, sistem memutus stok eceran secara presisi.
     */
    public function test_multi_satuan_unit_conversion_deducts_accurately(): void
    {
        $product = Product::where('sku', 'PRD-006')->first(); // Indomie Goreng
        $product->update(['stock' => max(100, $product->stock)]);
        $unit = ProductUnit::where('product_id', $product->id)->where('unit_name', 'Dus')->first();

        $this->assertNotNull($unit);
        $this->assertEquals(40, $unit->conversion_factor);

        $initialStock = $product->stock;

        // Open shift for cashier checkout
        $cashier = User::where('role', 'like', '%Kasir%')->first();
        $shift = CashierShift::create([
            'shift_code' => 'SFT-UNIT-' . time(),
            'terminal_code' => 'POS-01',
            'cashier_nik' => $cashier->nik,
            'cashier_name' => $cashier->name,
            'opening_cash' => 100000,
            'status' => 'OPEN',
            'opened_at' => now(),
        ]);

        $checkoutPayload = [
            'shift_id' => $shift->id,
            'terminal_code' => 'POS-01',
            'cashier_nik' => $cashier->nik,
            'cashier_name' => $cashier->name,
            'payment_method' => 'TUNAI',
            'cash_received' => 200000,
            'items' => [
                [
                    'sku' => $product->sku,
                    'barcode' => $unit->barcode,
                    'name' => $product->name,
                    'qty' => 1, // 1 Dus
                    'unit' => 'Dus', // Higher unit
                    'price' => (float) $unit->price,
                ],
            ],
        ];

        $response = $this->postJson('/api/pos/checkout', $checkoutPayload);
        $response->assertSuccessful();

        $product->refresh();
        // 1 Dus = 40 Pcs, so exactly 40 Pcs must be deducted from base stock!
        $this->assertEquals($initialStock - 40, $product->stock);
    }

    /**
     * Test Konsinyasi UMKM Partner CRUD.
     */
    public function test_consignment_partners_crud(): void
    {
        $payload = [
            'name' => 'Kripik Tempe Renyah Barokah',
            'owner_name' => 'Pak Joko',
            'phone' => '0812-9988-7766',
            'address' => 'Unit 2 Menggala',
            'category' => 'Snack Tradisional',
            'commission_percent' => 15.00,
            'settlement_period' => 'MINGGUAN',
            'bank_name' => 'BRI',
            'bank_account' => '1234-567-890',
        ];

        $response = $this->postJson('/api/consignment/partners', $payload);
        $response->assertStatus(201)
            ->assertJson(['success' => true]);

        $partnerId = $response->json('data.id');
        $this->assertDatabaseHas('suppliers', [
            'id' => $partnerId,
            'name' => 'Kripik Tempe Renyah Barokah',
            'commission_percent' => 15.00,
        ]);
    }

    /**
     * Test Konsinyasi Bagi Hasil Report (Automatic Split Omzet UMKM vs Komisi Koperasi).
     */
    public function test_consignment_sales_split_report(): void
    {
        $month = now()->format('Y-m');
        $response = $this->getJson("/api/consignment/report?month={$month}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'period' => ['month', 'label'],
                'summary' => [
                    'total_gross_sales',
                    'total_coop_commission',
                    'total_umkm_net_share',
                    'total_sold_units',
                    'active_consignment_skus',
                ],
                'data',
                'suppliers',
            ]);
    }

    /**
     * Test Stock Opname lifecycle, variance calculation, and automatic inventory adjustment.
     */
    public function test_stock_opname_lifecycle_and_variance_report(): void
    {
        // Ensure products have sufficient stock for opname variance testing
        Product::where('stock', '<=', 10)->update(['stock' => 50]);

        // 1. Create Opname Session
        $createRes = $this->postJson('/api/inventory/stock-opname', [
            'title' => 'Stock Opname Bulanan Toko Pusat',
            'branch' => 'Pusat',
            'conducted_by' => 'Manager Hendra',
            'notes' => 'Pengecekan fisik rak depan dan gudang',
        ]);

        $createRes->assertStatus(201);
        $opnameId = $createRes->json('data.id');

        // 2. Fetch Detail
        $detailRes = $this->getJson("/api/inventory/stock-opname/{$opnameId}");
        $detailRes->assertStatus(200);
        $firstItem = $detailRes->json('data.items.0');
        $this->assertNotNull($firstItem);

        $product = Product::find($firstItem['product_id']);
        $systemStock = (int) $firstItem['system_stock'];

        // 3. Verify physical count with discrepancy (-2 shortage)
        $physicalCount = max(0, $systemStock - 2);
        $verifyRes = $this->postJson("/api/inventory/stock-opname/{$opnameId}/verify-item", [
            'item_id' => $firstItem['id'],
            'physical_stock' => $physicalCount,
            'notes' => 'Barang kemasan robek/hilang',
        ]);

        $verifyRes->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'variance' => -2,
                    'variance_reason' => 'Hilang / Selisih Kurang',
                    'is_audited' => true,
                ],
            ]);

        // 4. Complete opname and apply adjustment
        $completeRes = $this->postJson("/api/inventory/stock-opname/{$opnameId}/complete", [
            'notes' => 'Opname selesai diverifikasi oleh Supervisor',
        ]);

        $completeRes->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'status' => 'COMPLETED',
                ],
            ]);

        // Check product stock in master database has been adjusted to physical count!
        $product->refresh();
        $this->assertEquals($physicalCount, $product->stock);
    }

    /**
     * Test Stock Opname Variance Report Export to CSV.
     */
    public function test_stock_opname_export_csv(): void
    {
        $opname = StockOpname::create([
            'opname_code' => 'SOP-TEST-' . time(),
            'title' => 'Audit Test',
            'conducted_by' => 'Auditor',
            'status' => 'COMPLETED',
            'completed_at' => now(),
        ]);

        $response = $this->get("/api/inventory/stock-opname/{$opname->id}/export-csv");
        $response->assertStatus(200);
        $this->assertTrue(str_contains($response->headers->get('content-type'), 'text/csv'));
    }

    /**
     * Test Stock Opname Variance Report Export to Excel (.xlsx).
     */
    public function test_stock_opname_export_excel(): void
    {
        $opname = StockOpname::create([
            'opname_code' => 'SOP-XLSX-' . time(),
            'title' => 'Audit Excel Test',
            'conducted_by' => 'Auditor',
            'status' => 'COMPLETED',
            'completed_at' => now(),
        ]);

        $response = $this->get("/api/inventory/stock-opname/{$opname->id}/export-excel");
        $response->assertStatus(200);
        $this->assertTrue(str_contains($response->headers->get('content-type'), 'spreadsheetml.sheet'));
        $this->assertTrue(str_contains($response->headers->get('content-disposition'), '.xlsx'));
    }

    /**
     * Test full end-to-end UMKM workflow:
     * Manager adds UMKM Partner -> Adds UMKM Product (buy & sell price) -> Sends stock to Branch ->
     * Cashier loads UMKM products -> Cashier completes POS transaction with UMKM product.
     */
    public function test_umkm_product_creation_branch_transfer_cashier_load_and_pos_checkout(): void
    {
        // 1. Manager creates Mitra UMKM
        $partnerRes = $this->postJson('/api/consignment/partners', [
            'name' => 'UMKM Keripik Singkong Nusantara',
            'owner_name' => 'Ibu Sari',
            'phone' => '0813-7788-9900',
            'category' => 'Makanan Ringan',
            'commission_percent' => 15.0,
            'settlement_period' => 'MINGGUAN',
            'bank_name' => 'BRI',
            'bank_account' => '9988-7766-55',
        ]);
        $partnerRes->assertStatus(201);
        $partnerId = $partnerRes->json('data.id');

        // 2. Manager adds UMKM Product (bought at 12,750, sold at 15,000, initial stock 40 in Gudang Pusat)
        $prodRes = $this->postJson('/api/consignment/products', [
            'supplier_id' => $partnerId,
            'name' => 'Keripik Singkong Balado 200g',
            'sku' => 'UMKM-KSB-200',
            'barcode' => '8997766554433',
            'category' => 'Makanan Ringan',
            'unit' => 'Bungkus',
            'cost_price' => 12750,
            'price' => 15000,
            'member_price' => 14500,
            'commission_percent' => 15.0,
            'stock' => 40,
            'min_stock' => 5,
            'target_location' => 'GUDANG_PUSAT',
        ]);
        $prodRes->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.warehouse_stock', 40)
            ->assertJsonPath('data.branch_stock_total', 0)
            ->assertJsonPath('data.allocated_branches', []);
        $productId = $prodRes->json('data.id');

        // 2b. Verify product that has NOT been sent to any branch does NOT exist in Toko Pusat or Cabang A
        $beforeSendPusat = $this->getJson('/api/pos/products/umkm?branch=Toko+Pusat&terminal_code=POS-01');
        $beforeSendPusat->assertStatus(200);
        $this->assertNull(collect($beforeSendPusat->json('data'))->firstWhere('id', $productId));

        $beforeLoadPusat = $this->postJson('/api/pos/products/umkm/load', [
            'branch' => 'Toko Pusat',
            'terminal_code' => 'POS-01',
        ]);
        $beforeLoadPusat->assertStatus(200);
        $this->assertNull(collect($beforeLoadPusat->json('data'))->firstWhere('id', $productId));

        // 3. Manager sends 15 Bungkus to Toko Pusat (CAB-001)
        $sendRes = $this->postJson('/api/consignment/send-stock', [
            'destination_store' => 'Toko Pusat',
            'source_mode' => 'WAREHOUSE',
            'delivery_mode' => 'DIRECT_ACTIVE',
            'driver_name' => 'Kurir UMKM',
            'plate_number' => 'BE 9988 XX',
            'items' => [
                [
                    'product_id' => $productId,
                    'qty_sent' => 15,
                ],
            ],
        ]);
        $sendRes->assertStatus(201)
            ->assertJsonPath('success', true);

        // Verify warehouse stock is now 25 and branch stock is 15 (only in CAB-001 Toko Pusat, NOT in Cabang A or Cabang B)
        $listRes = $this->getJson("/api/consignment/products?supplier_id={$partnerId}");
        $listRes->assertStatus(200);
        $prodData = collect($listRes->json('data'))->firstWhere('id', $productId);
        $this->assertEquals(25, $prodData['warehouse_stock']);
        $this->assertEquals(15, $prodData['branch_stock_total']);
        $this->assertCount(1, $prodData['allocated_branches']);

        // Verify Cabang A (where the product was NOT sent) still does NOT have this product
        $cabangAUmkm = $this->getJson('/api/pos/products/umkm?branch=Cabang+A&terminal_code=POS-06');
        $cabangAUmkm->assertStatus(200);
        $this->assertNull(collect($cabangAUmkm->json('data'))->firstWhere('id', $productId));

        // 4. Cashier loads UMKM products at Toko Pusat (POS-01)
        $loadRes = $this->postJson('/api/pos/products/umkm/load', [
            'branch' => 'Toko Pusat',
            'terminal_code' => 'POS-01',
        ]);
        $loadRes->assertStatus(200)
            ->assertJsonPath('success', true);
        $loadedItem = collect($loadRes->json('data'))->firstWhere('id', $productId);
        $this->assertNotNull($loadedItem);
        $this->assertEquals(15, $loadedItem['branch_stock_qty']);
        $this->assertTrue($loadedItem['can_transact']);

        // 5. Cashier executes POS Checkout with 3 Bungkus of the UMKM product
        $cashier = User::where('role', 'like', '%Kasir%')->first();
        $shift = CashierShift::create([
            'shift_code' => 'SFT-UMKM-' . time(),
            'terminal_code' => 'POS-01',
            'branch' => 'Toko Pusat',
            'cashier_nik' => $cashier->nik,
            'cashier_name' => $cashier->name,
            'opening_cash' => 200000,
            'status' => 'OPEN',
            'opened_at' => now(),
        ]);

        $checkoutRes = $this->postJson('/api/pos/checkout', [
            'shift_id' => $shift->id,
            'terminal_code' => 'POS-01',
            'branch' => 'Toko Pusat',
            'cashier_nik' => $cashier->nik,
            'cashier_name' => $cashier->name,
            'payment_method' => 'TUNAI',
            'cash_received' => 100000,
            'items' => [
                [
                    'sku' => 'UMKM-KSB-200',
                    'barcode' => '8997766554433',
                    'name' => 'Keripik Singkong Balado 200g',
                    'qty' => 3,
                    'unit' => 'Bungkus',
                    'price' => 15000,
                    'is_member' => false,
                ],
            ],
        ]);
        $checkoutRes->assertStatus(201)
            ->assertJsonPath('success', true);

        // Verify branch stock decreased from 15 to 12
        $afterCheckRes = $this->getJson('/api/pos/products/umkm?branch=Toko+Pusat&terminal_code=POS-01');
        $afterCheckRes->assertStatus(200);
        $afterItem = collect($afterCheckRes->json('data'))->firstWhere('id', $productId);
        $this->assertEquals(12, $afterItem['branch_stock_qty']);
    }
}
