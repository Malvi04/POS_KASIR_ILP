<?php

namespace Tests\Feature;

use App\Models\GoodsReceipt;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\PurchasePriceHistory;
use App\Models\StockBatch;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PurchaseModuleTest extends TestCase
{
    use DatabaseTransactions;

    protected User $manager;
    protected Supplier $supplierA;
    protected Supplier $supplierB;
    protected Product $regularProduct1;
    protected Product $regularProduct2;
    protected Product $consignmentProduct;

    protected function setUp(): void
    {
        parent::setUp();

        $this->manager = User::firstOrCreate(
            ['username' => 'test_manager_purchase'],
            [
                'nik' => '9988776655',
                'name' => 'Test Manager Gudang',
                'email' => 'mgr.purchase@test.local',
                'role' => 'Manager',
                'pin' => '123456',
                'branch' => 'Pusat',
                'password' => Hash::make('password'),
                'is_active' => true,
            ]
        );
        $this->actingAs($this->manager);

        $this->supplierA = Supplier::create([
            'code' => 'SUP-TEST-A-' . rand(100, 999),
            'name' => 'PT Supplier Uji A',
            'status' => 'AKTIF',
        ]);

        $this->supplierB = Supplier::create([
            'code' => 'SUP-TEST-B-' . rand(100, 999),
            'name' => 'PT Supplier Uji B',
            'status' => 'AKTIF',
        ]);

        $this->regularProduct1 = Product::create([
            'sku' => 'TST-REG-' . rand(1000, 9999),
            'barcode' => '899000' . rand(100000, 999999),
            'name' => 'Indomie Goreng Uji',
            'category' => 'Makanan',
            'unit' => 'Pcs',
            'cost_price' => 2500,
            'last_cost_price' => 2500,
            'price' => 3000,
            'member_price' => 2900,
            'stock' => 10,
            'min_stock' => 5,
            'is_consignment' => false,
            'status' => 'TERSEDIA',
        ]);

        $this->regularProduct2 = Product::create([
            'sku' => 'TST-REG2-' . rand(1000, 9999),
            'barcode' => '899001' . rand(100000, 999999),
            'name' => 'Aqua Botol 600ml Uji',
            'category' => 'Minuman',
            'unit' => 'Botol',
            'cost_price' => 3000,
            'last_cost_price' => 3000,
            'price' => 4500,
            'member_price' => 4200,
            'stock' => 20,
            'min_stock' => 5,
            'is_consignment' => false,
            'status' => 'TERSEDIA',
        ]);

        $this->consignmentProduct = Product::create([
            'sku' => 'TST-KNS-' . rand(1000, 9999),
            'barcode' => '899002' . rand(100000, 999999),
            'name' => 'Keripik Konsinyasi Uji',
            'category' => 'Snack UMKM',
            'unit' => 'Pcs',
            'cost_price' => 8000,
            'price' => 10000,
            'stock' => 5,
            'min_stock' => 2,
            'is_consignment' => true,
            'supplier_id' => $this->supplierA->id,
            'status' => 'TERSEDIA',
        ]);
    }

    public function test_pembelian_barang_page_and_sidebar_structure(): void
    {
        $response = $this->get('/pembelian-barang');
        $response->assertStatus(200);
        $response->assertSee('Pembelian Barang');
        $response->assertSee('Kelola transaksi pembelian produk, penerimaan barang, dan riwayat harga beli supplier.');
        $response->assertSee('Daftar Pembelian');
        $response->assertSee('Buat Pembelian');
        $response->assertSee('Penerimaan Barang');
        $response->assertSee('Riwayat Harga Beli');

        $managerPrefixResponse = $this->get('/manager/pembelian-barang');
        $managerPrefixResponse->assertStatus(200);

        // Verify sidebar has ONLY ONE menu item for Pembelian Barang and no separate submenu links
        $sidebarHtml = view('components.manager-sidebar', ['active' => 'pembelian_barang'])->render();
        $this->assertEquals(1, substr_count($sidebarHtml, 'Pembelian Barang'));
        $this->assertStringNotContainsString('Daftar Pembelian', $sidebarHtml);
        $this->assertStringNotContainsString('Buat Pembelian', $sidebarHtml);
        $this->assertStringNotContainsString('Penerimaan Barang', $sidebarHtml);
        $this->assertStringNotContainsString('Riwayat Harga Beli', $sidebarHtml);
    }

    public function test_create_purchase_draft_and_reject_consignment_mixing(): void
    {
        // Reject consignment product
        $consignRes = $this->postJson('/api/purchases', [
            'purchase_date' => '2026-11-17',
            'supplier_id' => $this->supplierA->id,
            'status' => 'MENUNGGU_PENERIMAAN',
            'items' => [
                [
                    'product_id' => $this->consignmentProduct->id,
                    'unit' => 'Pcs',
                    'qty' => 5,
                    'purchase_price' => 8000,
                    'discount_amount' => 0,
                ],
            ],
        ]);
        $consignRes->assertStatus(422);

        // Create Draft purchase with 2 regular products and custom prices + discount
        $res = $this->postJson('/api/purchases', [
            'purchase_date' => '2026-11-17',
            'supplier_id' => $this->supplierA->id,
            'supplier_invoice_number' => 'INV-A-001',
            'notes' => 'Pembelian stok awal',
            'status' => 'DRAFT',
            'items' => [
                [
                    'product_id' => $this->regularProduct1->id,
                    'unit' => 'Pcs',
                    'qty' => 10,
                    'purchase_price' => 2700,
                    'discount_amount' => 1000,
                ],
                [
                    'product_id' => $this->regularProduct2->id,
                    'unit' => 'Botol',
                    'qty' => 5,
                    'purchase_price' => 3100,
                    'discount_amount' => 0,
                ],
            ],
        ]);

        $res->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'DRAFT')
            ->assertJsonPath('data.total_items', 2);

        $purchaseId = $res->json('data.id');
        // (10 * 2700 - 1000) + (5 * 3100) = 26000 + 15500 = 41500
        $this->assertEquals(41500.0, (float) $res->json('data.total_amount'));

        // Stock and last_cost_price MUST NOT change while purchase is unreceived
        $this->regularProduct1->refresh();
        $this->assertEquals(10, $this->regularProduct1->stock);
        $this->assertEquals(2500.0, (float) $this->regularProduct1->last_cost_price);
        $this->assertEquals(3000.0, (float) $this->regularProduct1->price);

        // Edit Draft and promote to MENUNGGU_PENERIMAAN
        $updateRes = $this->putJson("/api/purchases/{$purchaseId}", [
            'purchase_date' => '2026-11-17',
            'supplier_id' => $this->supplierA->id,
            'supplier_invoice_number' => 'INV-A-001-REV',
            'status' => 'MENUNGGU_PENERIMAAN',
            'items' => [
                [
                    'product_id' => $this->regularProduct1->id,
                    'unit' => 'Pcs',
                    'qty' => 10,
                    'purchase_price' => 2700,
                    'discount_amount' => 0,
                ],
            ],
        ]);
        $updateRes->assertStatus(200)
            ->assertJsonPath('data.status', 'MENUNGGU_PENERIMAAN');

        // Still unreceived -> stock and last_cost_price unchanged
        $this->regularProduct1->refresh();
        $this->assertEquals(10, $this->regularProduct1->stock);
        $this->assertEquals(2500.0, (float) $this->regularProduct1->last_cost_price);
    }

    public function test_partial_and_full_goods_receipt_updates_stock_batches_and_price_history(): void
    {
        // 1. Create PO from Supplier A at Rp 2.700 (qty 10)
        $createRes = $this->postJson('/api/purchases', [
            'purchase_date' => '2026-11-17',
            'supplier_id' => $this->supplierA->id,
            'supplier_invoice_number' => 'INV-SUPA-100',
            'status' => 'MENUNGGU_PENERIMAAN',
            'items' => [
                [
                    'product_id' => $this->regularProduct1->id,
                    'unit' => 'Pcs',
                    'qty' => 10,
                    'purchase_price' => 2700,
                    'discount_amount' => 0,
                ],
            ],
        ]);
        $createRes->assertStatus(201);
        $purchaseId = $createRes->json('data.id');
        $purchaseItemId = $createRes->json('data.items.0.id');

        // 2. Reject over-receipt (qty_received_now > remaining)
        $overRes = $this->postJson("/api/purchases/{$purchaseId}/receive", [
            'items' => [
                [
                    'purchase_item_id' => $purchaseItemId,
                    'qty_received_now' => 12,
                ],
            ],
        ]);
        $overRes->assertStatus(422);

        // 3. Partial receipt: 4 Pcs received now
        $partialRes = $this->postJson("/api/purchases/{$purchaseId}/receive", [
            'received_at' => '2026-11-17 10:00:00',
            'notes' => 'Pengiriman tahap 1',
            'items' => [
                [
                    'purchase_item_id' => $purchaseItemId,
                    'qty_received_now' => 4,
                    'actual_unit_price' => 2700,
                    'condition' => 'BAIK',
                    'expired_date' => '2027-06-01',
                    'note' => '6 Pcs menyusul besok',
                ],
            ],
        ]);
        $partialRes->assertStatus(200)
            ->assertJsonPath('data.purchase.status', 'DITERIMA_SEBAGIAN');

        $this->regularProduct1->refresh();
        $this->assertEquals(14, $this->regularProduct1->stock); // 10 + 4
        $this->assertEquals(2700.0, (float) $this->regularProduct1->last_cost_price);
        $this->assertEquals(3000.0, (float) $this->regularProduct1->price); // Selling price MUST NOT change!
        $this->assertEquals(2900.0, (float) $this->regularProduct1->member_price);

        // Verify Batch & Receipt created
        $this->assertEquals(1, StockBatch::where('purchase_id', $purchaseId)->count());
        $this->assertEquals(1, GoodsReceipt::where('purchase_id', $purchaseId)->count());

        // Verify Kasir/Kirim Barang /api/goods-receipts does NOT return this purchase receipt (no duplicate Barang Masuk!)
        $grIndex = $this->getJson('/api/goods-receipts');
        $grIndex->assertStatus(200);
        $grDocNos = collect($grIndex->json('data'))->pluck('docNo')->all();
        $purchaseReceiptNo = $partialRes->json('data.receipt.receipt_number');
        $this->assertNotContains($purchaseReceiptNo, $grDocNos);

        // 4. Second receipt completing remaining 6 Pcs
        $completeRes = $this->postJson("/api/purchases/{$purchaseId}/receive", [
            'received_at' => '2026-11-17 14:00:00',
            'notes' => 'Pengiriman tahap 2 (lunas)',
            'items' => [
                [
                    'purchase_item_id' => $purchaseItemId,
                    'qty_received_now' => 6,
                    'actual_unit_price' => 2700,
                    'condition' => 'BAIK',
                    'expired_date' => '2027-07-01',
                ],
            ],
        ]);
        $completeRes->assertStatus(200)
            ->assertJsonPath('data.purchase.status', 'SELESAI');

        $this->regularProduct1->refresh();
        $this->assertEquals(20, $this->regularProduct1->stock); // 14 + 6
        $this->assertEquals(2, GoodsReceipt::where('purchase_id', $purchaseId)->count());
        $this->assertEquals(2, StockBatch::where('purchase_id', $purchaseId)->count());

        // 5. Create another PO from Supplier B at lower price Rp 2.450 and receive it
        $poBRes = $this->postJson('/api/purchases', [
            'purchase_date' => '2026-11-17',
            'supplier_id' => $this->supplierB->id,
            'status' => 'MENUNGGU_PENERIMAAN',
            'items' => [
                [
                    'product_id' => $this->regularProduct1->id,
                    'unit' => 'Pcs',
                    'qty' => 5,
                    'purchase_price' => 2450,
                    'discount_amount' => 0,
                ],
            ],
        ]);
        $poBId = $poBRes->json('data.id');
        $poBItemId = $poBRes->json('data.items.0.id');

        $this->postJson("/api/purchases/{$poBId}/receive", [
            'received_at' => '2026-11-17 16:00:00',
            'items' => [
                [
                    'purchase_item_id' => $poBItemId,
                    'qty_received_now' => 5,
                    'actual_unit_price' => 2450,
                ],
            ],
        ])->assertStatus(200);

        $this->regularProduct1->refresh();
        $this->assertEquals(25, $this->regularProduct1->stock);
        $this->assertEquals(2450.0, (float) $this->regularProduct1->last_cost_price);
        $this->assertEquals(3000.0, (float) $this->regularProduct1->price);

        // Verify Master Product API exposes updated last_cost_price
        $prodListRes = $this->getJson('/api/products?search=' . urlencode($this->regularProduct1->sku));
        $prodListRes->assertStatus(200);
        $foundProd = collect($prodListRes->json('data'))->firstWhere('id', $this->regularProduct1->id);
        $this->assertNotNull($foundProd);
        $this->assertEquals(2450.0, (float) $foundProd['last_cost_price']);
        $this->assertEquals(3000.0, (float) $foundProd['original_price']);

        // Verify Price History API & Product Price Detail API
        $histRes = $this->getJson('/api/purchases/price-history?search=' . urlencode($this->regularProduct1->sku));
        $histRes->assertStatus(200)
            ->assertJsonPath('success', true);
        $this->assertGreaterThanOrEqual(3, count($histRes->json('data')));

        $prodHistRes = $this->getJson("/api/purchases/price-history/product/{$this->regularProduct1->id}");
        $prodHistRes->assertStatus(200)
            ->assertJsonPath('data.stats.first_price', 2700)
            ->assertJsonPath('data.stats.last_price', 2450)
            ->assertJsonPath('data.stats.highest_price', 2700)
            ->assertJsonPath('data.stats.lowest_price', 2450)
            ->assertJsonPath('data.stats.last_supplier', $this->supplierB->name);
    }
}
