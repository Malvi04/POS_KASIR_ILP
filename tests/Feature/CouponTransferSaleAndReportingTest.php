<?php

namespace Tests\Feature;

use App\Models\CashierShift;
use App\Models\GoodsReceipt;
use App\Models\Location;
use App\Models\Product;
use App\Models\ProductStock;
use App\Models\User;
use App\Services\LocationStockService;
use Tests\TestCase;

class CouponTransferSaleAndReportingTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
    }

    public function test_coupon_in_kirim_barang_goods_in_pos_checkout_and_reports(): void
    {
        $stockService = app(LocationStockService::class);

        // Ensure users exist
        $manager = User::firstOrCreate(
            ['username' => 'manager1'],
            [
                'name' => 'Manager Toko',
                'email' => 'manager1@example.com',
                'role' => 'manager',
                'is_active' => true,
                'password' => bcrypt('manager123'),
                'branch' => 'Pusat',
            ]
        );
        $manager->update(['role' => 'manager', 'is_active' => true]);

        $kasir = User::firstOrCreate(
            ['username' => 'kasir1'],
            [
                'name' => 'Kasir Utama',
                'email' => 'kasir1@example.com',
                'role' => 'kasir',
                'is_active' => true,
                'password' => bcrypt('kasir123'),
                'branch' => 'Toko Pusat,Cabang A,Cabang B',
            ]
        );
        $kasir->update(['role' => 'kasir', 'is_active' => true, 'branch' => 'Toko Pusat,Cabang A,Cabang B']);

        // Locations
        $whLoc = Location::where('code', 'GDG-001')->first() ?? Location::firstOrCreate(
            ['code' => 'GDG-001'],
            ['name' => 'Gudang Pusat', 'type' => 'WAREHOUSE']
        );
        $storeLoc = Location::where('type', '!=', 'WAREHOUSE')->where(function ($q) {
            $q->where('code', 'PST-001')->orWhere('name', 'like', '%Pusat%');
        })->first() ?? Location::firstOrCreate(
            ['code' => 'PST-001'],
            ['name' => 'Toko Pusat', 'type' => 'STORE']
        );

        // 1. Create Kupon Product in Master Catalog & Gudang Pusat
        $kuponProduct = Product::create([
            'sku' => 'KPN-BBM-SOLAR-01',
            'barcode' => '8999901001',
            'name' => 'Kupon BBM Solar 10 Liter',
            'category' => 'Kupon BBM',
            'unit' => 'Kupon',
            'cost_price' => 60000,
            'price' => 68000,
            'member_price' => 65000,
            'stock' => 50,
            'min_stock' => 5,
            'is_consignment' => false,
            'status' => 'AKTIF',
        ]);

        // Add warehouse stock
        $stockService->addStock($kuponProduct->id, $whLoc->id, 50, [
            'movement_type' => 'PURCHASE_IN',
            'notes' => 'Inisialisasi Stok Kupon Gudang Pusat',
        ]);

        // 2. Query /api/products?location_code=GDG-001 as Manager
        $prodRes = $this->actingAs($manager, 'web')->withHeaders(['X-POS-Role' => 'manager'])->getJson('/api/products?location_code=GDG-001&per_page=50');
        $prodRes->assertStatus(200);
        $matchingKupon = collect($prodRes->json('data'))->firstWhere('sku', 'KPN-BBM-SOLAR-01');
        $this->assertNotNull($matchingKupon);
        $this->assertTrue($matchingKupon['is_coupon']);
        $this->assertEquals('KUPON', $matchingKupon['product_type']);
        $this->assertEquals('Kupon / Voucher', $matchingKupon['product_type_label']);

        // 3. Manager sends Kupon to Toko Pusat via Kirim Barang (/api/goods-receipts)
        $docNo = 'SJ-KPN-' . time();
        $transferRes = $this->actingAs($manager, 'web')->withHeaders(['X-POS-Role' => 'manager'])->postJson('/api/goods-receipts', [
            'docNo' => $docNo,
            'from_location_id' => $whLoc->id,
            'to_location_id' => $storeLoc->id,
            'warehouseOrigin' => $whLoc->name,
            'destinationStore' => $storeLoc->name,
            'driverName' => 'Pak Joko',
            'plateNumber' => 'B 1234 CD',
            'status' => 'MENUNGGU_VERIFIKASI',
            'notes' => 'Distribusi kupon BBM ke gerai Toko Pusat',
            'items' => [
                [
                    'product_id' => $kuponProduct->id,
                    'sku' => $kuponProduct->sku,
                    'name' => $kuponProduct->name,
                    'category' => $kuponProduct->category,
                    'qtySent' => 10,
                    'condition' => 'BAIK',
                ],
            ],
        ]);

        $transferRes->assertStatus(201)
            ->assertJsonPath('success', true);

        $receiptId = $transferRes->json('data.id');
        $receiptItem = $transferRes->json('data.items.0');
        $this->assertTrue($receiptItem['is_coupon']);
        $this->assertEquals('KUPON', $receiptItem['product_type']);
        $this->assertEquals('Kupon / Voucher', $receiptItem['product_type_label']);

        // Check Gudang stock decreased by 10
        $whPs = ProductStock::where('product_id', $kuponProduct->id)->where('location_id', $whLoc->id)->first();
        $this->assertEquals(40, (int) $whPs->stock);

        // 4. Cashier verifies & approves shipment (/api/goods-receipts/{id}/verify)
        $verifyRes = $this->actingAs($kasir, 'web')->withHeaders(['X-POS-Role' => 'kasir'])->postJson("/api/goods-receipts/{$receiptId}/verify", [
            'receiver_name' => $kasir->name,
            'location_id' => $storeLoc->id,
            'notes' => 'Kupon diterima lengkap dan tervalidasi oleh kasir',
            'items' => [
                [
                    'id' => $receiptItem['id'],
                    'qty_received' => 10,
                    'condition' => 'BAIK',
                ],
            ],
        ]);

        $verifyRes->assertStatus(200)
            ->assertJsonPath('success', true);

        // Store stock at branch should now be 10
        $storePs = ProductStock::where('product_id', $kuponProduct->id)->where('location_id', $storeLoc->id)->first();
        $this->assertNotNull($storePs);
        $this->assertEquals(10, (int) $storePs->stock);

        // 5. Cashier opens shift and sells 2 Kupon in POS (/api/transactions/checkout)
        $shift = CashierShift::create([
            'user_id' => $kasir->id,
            'cashier_nik' => $kasir->nik ?? 'KASIR-01',
            'cashier_name' => $kasir->name,
            'shift_code' => 'SFT-' . time(),
            'terminal_code' => 'POS-01',
            'location_id' => $storeLoc->id,
            'branch' => $storeLoc->name,
            'opening_cash' => 100000,
            'opened_at' => now(),
            'start_time' => now(),
            'status' => 'OPEN',
        ]);

        $checkoutRes = $this->actingAs($kasir, 'web')->withHeaders(['X-POS-Role' => 'kasir'])->postJson('/api/pos/checkout', [
            'terminal_code' => 'POS-01',
            'location_id' => $storeLoc->id,
            'branch' => $storeLoc->name,
            'shift_id' => $shift->id,
            'cashier_name' => $kasir->name,
            'cashier_nik' => $kasir->nik ?? 'KASIR-01',
            'payment_method' => 'TUNAI',
            'cash_received' => 200000,
            'items' => [
                [
                    'sku' => $kuponProduct->sku,
                    'barcode' => $kuponProduct->barcode,
                    'name' => $kuponProduct->name,
                    'price' => 68000,
                    'qty' => 2,
                    'unit' => 'Kupon',
                ],
            ],
        ]);

        $checkoutRes->assertStatus(201)
            ->assertJsonPath('success', true);

        // Store stock should now be 8
        $storePs->refresh();
        $this->assertEquals(8, (int) $storePs->stock);

        // 6. Reports: Verify Sales & Profit Loss capture the Kupon transaction
        $salesRes = $this->actingAs($manager, 'web')->withHeaders(['X-POS-Role' => 'manager'])->getJson('/api/reports/sales?period=daily&date=' . date('Y-m-d'));
        $salesRes->assertStatus(200)
            ->assertJsonPath('success', true);
        $this->assertGreaterThanOrEqual(136000, (float) $salesRes->json('summary.grand_total'));

        $plRes = $this->actingAs($manager, 'web')->withHeaders(['X-POS-Role' => 'manager'])->getJson('/api/reports/profit-loss?period=daily&date=' . date('Y-m-d'));
        $plRes->assertStatus(200)
            ->assertJsonPath('success', true);
        $this->assertArrayHasKey('coupon_sales', $plRes->json('statement'));
        $this->assertGreaterThanOrEqual(136000, (float) $plRes->json('statement.coupon_sales'));
        $this->assertGreaterThanOrEqual(120000, (float) $plRes->json('statement.coupon_cogs'));
        $this->assertGreaterThanOrEqual(16000, (float) $plRes->json('statement.coupon_gross_profit'));
    }

    public function test_manager_added_coupon_automatically_appears_in_kirim_barang_and_can_be_shipped(): void
    {
        $manager = User::firstOrCreate(
            ['username' => 'manager1'],
            [
                'name' => 'Manager Toko',
                'email' => 'manager1@example.com',
                'role' => 'manager',
                'is_active' => true,
                'password' => bcrypt('manager123'),
                'branch' => 'Pusat',
            ]
        );
        $manager->update(['role' => 'manager', 'is_active' => true]);

        // 1. Manager adds a new Kupon in /manager/kupon
        $couponSerial = '#KPN-BBM-' . mt_rand(1000, 9999);
        $createRes = $this->actingAs($manager, 'web')->postJson('/api/coupons', [
            'category' => 'bbm',
            'serial_number' => $couponSerial,
            'recipient_name' => 'Sopir Logistik Armada',
            'recipient_nik' => '3275001199',
            'recipient_dept' => 'Divisi Distribusi',
            'volume' => '30 Liter',
            'quota' => 25,
            'nominal_value' => 300000,
            'notes' => 'Kupon Pertalite Armada Logistik Pusat',
        ]);

        $createRes->assertStatus(201)
            ->assertJsonPath('success', true);

        // Verify product was auto-created and linked
        $productId = $createRes->json('data.product_id');
        $this->assertNotNull($productId);

        $product = Product::find($productId);
        $this->assertNotNull($product);
        $this->assertStringContainsString('Kupon', $product->category);

        // 2. Query Gudang Pusat products for "Kirim Barang"
        $whProductsRes = $this->actingAs($manager, 'web')->getJson('/api/products?location_code=GDG-001&per_page=500');
        $whProductsRes->assertStatus(200)
            ->assertJsonPath('success', true);

        $matchingItems = collect($whProductsRes->json('data'))->where('id', $productId);
        $this->assertNotEmpty($matchingItems, "Kupon product must appear in Gudang Pusat products for Kirim Barang");

        $itemData = $matchingItems->first();
        $this->assertTrue($itemData['is_coupon']);
        $this->assertEquals('KUPON', $itemData['product_type']);
        $this->assertGreaterThanOrEqual(25, $itemData['stock']);

        // 3. Manager ships this coupon product to branch Cabang A
        $destLoc = Location::where('type', '!=', 'WAREHOUSE')->where('name', 'like', '%Cabang A%')->first()
            ?? Location::where('type', '!=', 'WAREHOUSE')->first();

        $shipRes = $this->actingAs($manager, 'web')->postJson('/api/goods-receipts', [
            'warehouseOrigin' => 'Gudang Pusat',
            'destinationStore' => $destLoc->name,
            'driverName' => 'Pak Slamet',
            'plateNumber' => 'B 9918 ZZ',
            'generalNotes' => 'Pengiriman kupon BBM ke cabang',
            'items' => [
                [
                    'sku' => $product->sku,
                    'name' => $product->name,
                    'category' => $product->category,
                    'unit' => $product->unit,
                    'qtySent' => 10,
                ],
            ],
        ]);

        $shipRes->assertStatus(201)
            ->assertJsonPath('success', true);

        $receiptId = $shipRes->json('data.id');
        $receiptData = $shipRes->json('data');
        $this->assertEquals('BELUM_SAMPAI', $receiptData['status']);

        $receiptItem = $receiptData['items'][0];
        $this->assertEquals(10, (int) $receiptItem['qtySent']);
        $this->assertEquals('KUPON', $receiptItem['product_type']);
        $this->assertTrue((bool) $receiptItem['is_coupon']);

        // Gudang Pusat stock should now be reduced by 10
        $whLoc = Location::where('code', 'GDG-001')->first();
        $whStock = ProductStock::where('product_id', $productId)->where('location_id', $whLoc->id)->first();
        $this->assertEquals(15, (int) $whStock->stock);
    }
}
