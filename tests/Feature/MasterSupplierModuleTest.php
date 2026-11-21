<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Purchase;
use App\Models\PurchasePriceHistory;
use App\Models\Supplier;
use App\Models\SupplierProduct;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class MasterSupplierModuleTest extends TestCase
{
    use DatabaseTransactions;

    protected User $manager;
    protected User $owner;
    protected User $kasir;
    protected Product $indomie;
    protected Product $tehBotol;
    protected Product $consignmentItem;

    protected function setUp(): void
    {
        parent::setUp();

        $this->manager = User::firstOrCreate(
            ['username' => 'test_mgr_supplier'],
            [
                'nik' => '7700112201',
                'name' => 'Manager Gudang Test',
                'email' => 'mgr.supplier@test.local',
                'role' => 'Manager',
                'pin' => '123456',
                'branch' => 'Pusat',
                'password' => Hash::make('password'),
                'is_active' => true,
            ]
        );

        $this->owner = User::firstOrCreate(
            ['username' => 'test_owner_supplier'],
            [
                'nik' => '7700112202',
                'name' => 'Owner Monitor Test',
                'email' => 'owner.supplier@test.local',
                'role' => 'Owner',
                'pin' => '654321',
                'branch' => 'Pusat',
                'password' => Hash::make('password'),
                'is_active' => true,
            ]
        );

        $this->kasir = User::firstOrCreate(
            ['username' => 'test_kasir_supplier'],
            [
                'nik' => '7700112203',
                'name' => 'Kasir Test',
                'email' => 'kasir.supplier@test.local',
                'role' => 'Kasir',
                'pin' => '112233',
                'branch' => 'Pusat',
                'password' => Hash::make('password'),
                'is_active' => true,
            ]
        );

        $this->actingAs($this->manager);

        $this->indomie = Product::create([
            'sku' => 'SUP-IND-' . rand(1000, 9999),
            'barcode' => '899700' . rand(100000, 999999),
            'name' => 'Indomie Goreng Master Test',
            'category' => 'Makanan',
            'unit' => 'Pcs',
            'cost_price' => 2500,
            'last_cost_price' => 2500,
            'price' => 3500,
            'member_price' => 3300,
            'stock' => 20,
            'min_stock' => 5,
            'is_consignment' => false,
            'status' => 'TERSEDIA',
        ]);

        $this->tehBotol = Product::create([
            'sku' => 'SUP-TEH-' . rand(1000, 9999),
            'barcode' => '899701' . rand(100000, 999999),
            'name' => 'Teh Botol Sosro Test',
            'category' => 'Minuman',
            'unit' => 'Botol',
            'cost_price' => 3200,
            'last_cost_price' => 3200,
            'price' => 4500,
            'member_price' => 4200,
            'stock' => 15,
            'min_stock' => 5,
            'is_consignment' => false,
            'status' => 'TERSEDIA',
        ]);

        $umkm = Supplier::create([
            'code' => 'UMKM-TEST-' . rand(100, 999),
            'name' => 'UMKM Snack Titipan Test',
            'status' => 'AKTIF',
            'supplier_type' => Supplier::TYPE_CONSIGNMENT,
        ]);

        $this->consignmentItem = Product::create([
            'sku' => 'SUP-KNS-' . rand(1000, 9999),
            'barcode' => '899702' . rand(100000, 999999),
            'name' => 'Keripik Pisang UMKM Test',
            'category' => 'Snack UMKM',
            'unit' => 'Bks',
            'cost_price' => 7000,
            'price' => 10000,
            'stock' => 10,
            'min_stock' => 2,
            'is_consignment' => true,
            'supplier_id' => $umkm->id,
            'status' => 'TERSEDIA',
        ]);
    }

    public function test_manager_can_create_edit_and_toggle_supplier_status_and_sidebar_structure(): void
    {
        // 1. Web pages & sidebar structure check
        $listPage = $this->get('/suppliers');
        $listPage->assertStatus(200);
        $listPage->assertSee('Master Supplier');
        $listPage->assertSee('Daftar Master Supplier');

        $sidebarHtml = view('components.manager-sidebar', ['active' => 'suppliers'])->render();
        $this->assertEquals(1, substr_count($sidebarHtml, 'Master Supplier'));
        $this->assertStringNotContainsString('Produk Dipasok', $sidebarHtml);
        $this->assertStringNotContainsString('Riwayat Pembelian', $sidebarHtml);

        // 2. Create supplier with multi-category & auto-generated code
        $createRes = $this->postJson('/api/suppliers', [
            'name' => 'PT Distribusi Nusantara Jaya',
            'company_name' => 'PT Distribusi Nusantara Jaya Tbk',
            'pic_name' => 'Bapak Budi Santoso',
            'phone' => '0812-8899-7766',
            'email' => 'order@nusantarajaya.co.id',
            'address' => 'Jl. Soekarno Hatta No. 120',
            'city' => 'Bandar Lampung',
            'business_categories' => ['Distributor Makanan', 'Distributor Minuman'],
            'notes' => 'Pengiriman tiap Selasa dan Jumat',
            'status' => 'AKTIF',
        ], ['X-Idempotency-Key' => 'sup-create-001']);

        $createRes->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.name', 'PT Distribusi Nusantara Jaya')
            ->assertJsonPath('data.status', 'AKTIF')
            ->assertJsonPath('data.summary.total_products', 0);

        $supplierId = $createRes->json('data.id');
        $supplierCode = $createRes->json('data.code');
        $this->assertStringStartsWith('SUP-', $supplierCode);
        $this->assertCount(2, $createRes->json('data.business_categories'));

        // Idempotency replay does not duplicate
        $replayRes = $this->postJson('/api/suppliers', [
            'name' => 'PT Distribusi Nusantara Jaya',
            'company_name' => 'PT Distribusi Nusantara Jaya Tbk',
            'pic_name' => 'Bapak Budi Santoso',
            'phone' => '0812-8899-7766',
            'email' => 'order@nusantarajaya.co.id',
            'address' => 'Jl. Soekarno Hatta No. 120',
            'city' => 'Bandar Lampung',
            'business_categories' => ['Distributor Makanan', 'Distributor Minuman'],
            'notes' => 'Pengiriman tiap Selasa dan Jumat',
            'status' => 'AKTIF',
        ], ['X-Idempotency-Key' => 'sup-create-001']);
        $replayRes->assertStatus(201)->assertJsonPath('data.id', $supplierId);

        // 3. Detail page renders cleanly
        $detailPage = $this->get("/suppliers/{$supplierId}");
        $detailPage->assertStatus(200);
        $detailPage->assertSee('Informasi Supplier');
        $detailPage->assertSee('Produk Dipasok');
        $detailPage->assertSee('Riwayat Pembelian');
        $detailPage->assertSee('Riwayat Harga');

        // 4. Edit supplier info & business categories
        $updateRes = $this->putJson("/api/suppliers/{$supplierId}", [
            'name' => 'PT Distribusi Nusantara Jaya',
            'company_name' => 'PT Nusantara Logistik Tbk',
            'pic_name' => 'Ibu Sari Rahayu',
            'phone' => '0813-1122-3344',
            'email' => 'procurement@nusantarajaya.co.id',
            'address' => 'Jl. Bypass Soekarno Hatta KM 5',
            'city' => 'Metro',
            'business_categories' => ['Distributor Sembako', 'Supplier Umum'],
            'status' => 'AKTIF',
            'notes' => 'Update termin 14 hari',
        ]);
        $updateRes->assertStatus(200)
            ->assertJsonPath('data.pic_name', 'Ibu Sari Rahayu')
            ->assertJsonPath('data.city', 'Metro')
            ->assertJsonPath('data.business_categories.0', 'Distributor Sembako');

        // 5. Deactivate supplier -> excluded from purchase meta dropdown & rejected on new purchase
        $toggleRes = $this->patchJson("/api/suppliers/{$supplierId}/status", ['status' => 'NONAKTIF']);
        $toggleRes->assertStatus(200)->assertJsonPath('data.status', 'NONAKTIF');

        $metaRes = $this->getJson('/api/purchases/meta');
        $metaRes->assertStatus(200);
        $activeIds = collect($metaRes->json('data.suppliers'))->pluck('id')->all();
        $this->assertNotContains($supplierId, $activeIds);

        $blockedPurchase = $this->postJson('/api/purchases', [
            'purchase_date' => '2026-11-17',
            'supplier_id' => $supplierId,
            'status' => 'MENUNGGU_PENERIMAAN',
            'items' => [
                ['product_id' => $this->indomie->id, 'unit' => 'Pcs', 'qty' => 5, 'purchase_price' => 2700],
            ],
        ]);
        $blockedPurchase->assertStatus(422);

        // 6. Reactivate supplier -> appears in purchase meta dropdown again
        $reactivateRes = $this->patchJson("/api/suppliers/{$supplierId}/status", ['status' => 'AKTIF']);
        $reactivateRes->assertStatus(200)->assertJsonPath('data.status', 'AKTIF');

        $metaAfter = $this->getJson('/api/purchases/meta');
        $activeIdsAfter = collect($metaAfter->json('data.suppliers'))->pluck('id')->all();
        $this->assertContains($supplierId, $activeIdsAfter);
    }

    public function test_auto_supplier_products_per_supplier_vs_global_price_and_deactivation_preservation(): void
    {
        $supA = Supplier::create(['code' => 'SUP-A-' . rand(100, 999), 'name' => 'Supplier A Utama', 'status' => 'AKTIF', 'supplier_type' => 'REGULAR']);
        $supB = Supplier::create(['code' => 'SUP-B-' . rand(100, 999), 'name' => 'Supplier B Murah', 'status' => 'AKTIF', 'supplier_type' => 'REGULAR']);
        $supC = Supplier::create(['code' => 'SUP-C-' . rand(100, 999), 'name' => 'Supplier C Terbaru', 'status' => 'AKTIF', 'supplier_type' => 'REGULAR']);

        // 1. Unreceived PO from Supplier A must NOT link supplier_products or change price
        $poARes = $this->postJson('/api/purchases', [
            'purchase_date' => '2026-11-17',
            'supplier_id' => $supA->id,
            'status' => 'MENUNGGU_PENERIMAAN',
            'items' => [
                ['product_id' => $this->indomie->id, 'unit' => 'Pcs', 'qty' => 10, 'purchase_price' => 2700],
                ['product_id' => $this->tehBotol->id, 'unit' => 'Botol', 'qty' => 5, 'purchase_price' => 3300],
            ],
        ]);
        $poARes->assertStatus(201);
        $poAId = $poARes->json('data.id');
        $poAIndomieItemId = $poARes->json('data.items.0.id');
        $poATehItemId = $poARes->json('data.items.1.id');

        $this->assertEquals(0, SupplierProduct::where('supplier_id', $supA->id)->count());
        $this->indomie->refresh();
        $this->assertEquals(2500.0, (float) $this->indomie->last_cost_price);

        // 2. Partial receipt from Supplier A (only 4 Indomie received, 0 Teh Botol yet)
        $this->postJson("/api/purchases/{$poAId}/receive", [
            'received_at' => '2026-11-17 09:00:00',
            'items' => [
                ['purchase_item_id' => $poAIndomieItemId, 'qty_received_now' => 4, 'actual_unit_price' => 2700],
                ['purchase_item_id' => $poATehItemId, 'qty_received_now' => 0],
            ],
        ])->assertStatus(200);

        // Only Indomie is linked in supplier_products for Supplier A so far
        $this->assertEquals(1, SupplierProduct::where('supplier_id', $supA->id)->count());

        // Complete remaining receipt from Supplier A (6 Indomie at 2700, 5 Teh Botol at 3300)
        $this->postJson("/api/purchases/{$poAId}/receive", [
            'received_at' => '2026-11-17 10:00:00',
            'items' => [
                ['purchase_item_id' => $poAIndomieItemId, 'qty_received_now' => 6, 'actual_unit_price' => 2700],
                ['purchase_item_id' => $poATehItemId, 'qty_received_now' => 5, 'actual_unit_price' => 3300],
            ],
        ])->assertStatus(200);

        // Still NO duplicate relation for (Supplier A, Indomie) -> total 2 distinct products supplied by A
        $this->assertEquals(2, SupplierProduct::where('supplier_id', $supA->id)->count());
        $relAIndomie = SupplierProduct::where('supplier_id', $supA->id)->where('product_id', $this->indomie->id)->first();
        $this->assertNotNull($relAIndomie);
        $this->assertEquals(2700.0, (float) $relAIndomie->last_purchase_price);
        $this->assertEquals(1, $relAIndomie->total_transactions);

        // Repeat purchase from Supplier A at Rp 2.750 -> total_transactions increments to 2, no duplicate row!
        $poA2Res = $this->postJson('/api/purchases', [
            'purchase_date' => '2026-11-17',
            'supplier_id' => $supA->id,
            'status' => 'MENUNGGU_PENERIMAAN',
            'items' => [
                ['product_id' => $this->indomie->id, 'unit' => 'Pcs', 'qty' => 5, 'purchase_price' => 2750],
            ],
        ])->assertStatus(201);
        $this->postJson("/api/purchases/{$poA2Res->json('data.id')}/receive", [
            'received_at' => '2026-11-17 11:00:00',
            'items' => [
                ['purchase_item_id' => $poA2Res->json('data.items.0.id'), 'qty_received_now' => 5, 'actual_unit_price' => 2750],
            ],
        ])->assertStatus(200);

        $relAIndomie->refresh();
        $this->assertEquals(1, SupplierProduct::where('supplier_id', $supA->id)->where('product_id', $this->indomie->id)->count());
        $this->assertEquals(2750.0, (float) $relAIndomie->last_purchase_price);
        $this->assertEquals(2, $relAIndomie->total_transactions);

        // 3. Supplier B supplies Indomie at Rp 2.600
        $poBRes = $this->postJson('/api/purchases', [
            'purchase_date' => '2026-11-17',
            'supplier_id' => $supB->id,
            'status' => 'MENUNGGU_PENERIMAAN',
            'items' => [
                ['product_id' => $this->indomie->id, 'unit' => 'Pcs', 'qty' => 10, 'purchase_price' => 2600],
            ],
        ])->assertStatus(201);
        $this->postJson("/api/purchases/{$poBRes->json('data.id')}/receive", [
            'received_at' => '2026-11-17 12:00:00',
            'items' => [
                ['purchase_item_id' => $poBRes->json('data.items.0.id'), 'qty_received_now' => 10, 'actual_unit_price' => 2600],
            ],
        ])->assertStatus(200);

        // 4. Supplier C supplies Indomie at Rp 2.800 (latest valid receipt!)
        $poCRes = $this->postJson('/api/purchases', [
            'purchase_date' => '2026-11-17',
            'supplier_id' => $supC->id,
            'status' => 'MENUNGGU_PENERIMAAN',
            'items' => [
                ['product_id' => $this->indomie->id, 'unit' => 'Pcs', 'qty' => 8, 'purchase_price' => 2800],
            ],
        ])->assertStatus(201);
        $this->postJson("/api/purchases/{$poCRes->json('data.id')}/receive", [
            'received_at' => '2026-11-17 14:00:00',
            'items' => [
                ['purchase_item_id' => $poCRes->json('data.items.0.id'), 'qty_received_now' => 8, 'actual_unit_price' => 2800],
            ],
        ])->assertStatus(200);

        // Verify per-supplier prices remain distinct!
        $supAProds = $this->getJson("/api/suppliers/{$supA->id}/products")->assertStatus(200);
        $supAIndomie = collect($supAProds->json('data'))->firstWhere('product_id', $this->indomie->id);
        $this->assertEquals(2750.0, (float) $supAIndomie['last_purchase_price']);

        $supBProds = $this->getJson("/api/suppliers/{$supB->id}/products")->assertStatus(200);
        $supBIndomie = collect($supBProds->json('data'))->firstWhere('product_id', $this->indomie->id);
        $this->assertEquals(2600.0, (float) $supBIndomie['last_purchase_price']);

        $supCProds = $this->getJson("/api/suppliers/{$supC->id}/products")->assertStatus(200);
        $supCIndomie = collect($supCProds->json('data'))->firstWhere('product_id', $this->indomie->id);
        $this->assertEquals(2800.0, (float) $supCIndomie['last_purchase_price']);

        // Verify Global Master Product last_cost_price is latest valid receipt (2800), NOT cheapest (2600) or average,
        // and selling prices MUST NOT change!
        $this->indomie->refresh();
        $this->assertEquals(2800.0, (float) $this->indomie->last_cost_price);
        $this->assertEquals(3500.0, (float) $this->indomie->price);
        $this->assertEquals(3300.0, (float) $this->indomie->member_price);

        // Verify no duplicate product created in Master Produk
        $this->assertEquals(1, Product::where('sku', $this->indomie->sku)->count());

        // 5. Deactivate & Delete Supplier A -> Supplier deleted safely; products and warehouse stock remain intact!
        $this->patchJson("/api/suppliers/{$supA->id}/status", ['status' => 'NONAKTIF'])->assertStatus(200);

        // Deleting supplier succeeds (200), products and warehouse stock stay completely safe!
        $this->deleteJson("/api/suppliers/{$supA->id}")->assertStatus(200);
        $this->assertNull(Supplier::find($supA->id));

        // Products and prices remain completely intact in catalog
        $this->assertNotNull(Product::find($this->indomie->id));
        $this->assertEquals(2800.0, (float) $this->indomie->fresh()->last_cost_price);
        $this->assertEquals(3500.0, (float) $this->indomie->fresh()->price);
    }

    public function test_role_access_control_for_manager_owner_and_kasir(): void
    {
        $sup = Supplier::create([
            'code' => 'SUP-RBAC-' . rand(100, 999),
            'name' => 'Supplier RBAC Test',
            'status' => 'AKTIF',
            'supplier_type' => 'REGULAR',
        ]);

        // 1. Owner: can view list & detail (web & API), but CANNOT create/edit/toggle/delete
        $this->actingAs($this->owner);
        $this->get('/owner/suppliers')->assertStatus(200);
        $this->get("/owner/suppliers/{$sup->id}")->assertStatus(200);
        $this->getJson('/api/suppliers')->assertStatus(200);
        $this->getJson("/api/suppliers/{$sup->id}")->assertStatus(200);
        $this->getJson("/api/suppliers/{$sup->id}/products")->assertStatus(200);
        $this->getJson("/api/suppliers/{$sup->id}/purchases")->assertStatus(200);
        $this->getJson("/api/suppliers/{$sup->id}/price-history")->assertStatus(200);

        $this->postJson('/api/suppliers', ['name' => 'Owner Should Not Create'])->assertStatus(403);
        $this->putJson("/api/suppliers/{$sup->id}", ['name' => 'Owner Edit'])->assertStatus(403);
        $this->patchJson("/api/suppliers/{$sup->id}/status", ['status' => 'NONAKTIF'])->assertStatus(403);
        $this->deleteJson("/api/suppliers/{$sup->id}")->assertStatus(403);

        // 2. Kasir: NO access at all
        $this->actingAs($this->kasir);
        $this->get('/suppliers')->assertStatus(302);
        $this->getJson('/api/suppliers')->assertStatus(403);
        $this->postJson('/api/suppliers', ['name' => 'Kasir Blocked'])->assertStatus(403);
        $this->getJson("/api/suppliers/{$sup->id}")->assertStatus(403);
    }
}
