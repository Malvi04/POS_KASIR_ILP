<?php

namespace Tests\Feature;

use App\Models\Location;
use App\Models\Product;
use App\Models\ProductStock;
use App\Models\StoreReturnHeader;
use App\Models\StoreReturnItem;
use App\Models\User;
use App\Services\LocationStockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ReturnRejectionFlowTest extends TestCase
{
    protected User $manager;
    protected User $cashier;
    protected Location $storeBranch;
    protected Location $warehouse;
    protected Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->storeBranch = Location::firstOrCreate(
            ['code' => 'TKO-TEST-01'],
            ['name' => 'Toko Cabang Test', 'type' => 'STORE', 'is_active' => true]
        );

        $this->warehouse = Location::firstOrCreate(
            ['code' => 'GUD-DC-01'],
            ['name' => 'Gudang Pusat DC', 'type' => 'WAREHOUSE', 'is_active' => true]
        );

        $this->manager = User::firstOrCreate(
            ['username' => 'test_manager_retur'],
            [
                'name' => 'Manager Retur Test',
                'email' => 'manager_retur_test@example.com',
                'password' => bcrypt('password123'),
                'role' => 'MANAGER',
                'is_active' => true,
            ]
        );

        $this->cashier = User::firstOrCreate(
            ['username' => 'test_cashier_retur'],
            [
                'name' => 'Kasir Retur Test',
                'email' => 'cashier_retur_test@example.com',
                'password' => bcrypt('password123'),
                'role' => 'KASIR',
                'branch' => 'Toko Cabang Test',
                'assigned_branches' => ['Toko Cabang Test'],
                'is_active' => true,
            ]
        );

        $suffix = Str::lower(Str::random(5));
        $this->product = Product::create([
            'sku' => 'SKU-RET-' . $suffix,
            'barcode' => '8999' . rand(100000, 999999),
            'name' => 'Produk Retur Test ' . $suffix,
            'category' => 'Snack',
            'unit' => 'Pcs',
            'cost_price' => 10000,
            'price' => 15000,
            'stock' => 0,
            'min_stock' => 5,
            'is_active' => true,
        ]);

        app(LocationStockService::class)->addStock($this->product->id, $this->storeBranch->id, 25, [
            'unit_cost' => 10000,
            'received_at' => now(),
            'notes' => 'Stok awal untuk test retur',
        ]);
    }

    /**
     * Test: When manager rejects return submission from "Persetujuan Retur",
     * cashier view receives status "ditolak", stock is restored, and reason is saved.
     */
    public function test_manager_rejects_return_from_approval_page_and_status_becomes_ditolak(): void
    {
        // 1. Kasir mengajukan retur 5 pcs
        $createResp = $this->actingAs($this->cashier)
            ->postJson('/api/returns', [
                'direction' => 'toko_ke_gudang',
                'condition_type' => 'rusak',
                'branch' => 'Toko Cabang Test',
                'from_location_id' => $this->storeBranch->id,
                'reason' => 'Barang rembes/cacat',
                'items' => [
                    [
                        'product_id' => $this->product->id,
                        'qty' => 5,
                        'condition' => 'RUSAK',
                    ]
                ],
            ]);

        $createResp->assertStatus(201);
        $returnId = $createResp->json('data.id');
        $docNo = $createResp->json('data.docNo');

        // Verify stock deducted by 5 (25 -> 20)
        $stockAfterSubmit = ProductStock::where('product_id', $this->product->id)
            ->where('location_id', $this->storeBranch->id)
            ->value('stock');
        $this->assertEquals(20, $stockAfterSubmit);

        // Kasir checks initial return list -> status is 'cek_fisik'
        $cashierListResp = $this->actingAs($this->cashier)
            ->getJson('/api/returns?direction=toko_ke_gudang');
        $cashierListResp->assertStatus(200);

        $initialDoc = collect($cashierListResp->json('data'))->firstWhere('id', $returnId);
        $this->assertNotNull($initialDoc);
        $this->assertEquals('cek_fisik', $initialDoc['statusKey']);

        // 2. Manager menolak pengajuan retur dari page "Persetujuan Retur"
        $rejectResp = $this->actingAs($this->manager)
            ->putJson("/api/returns/{$returnId}/status", [
                'status' => 'ditolak',
                'reason' => 'Kemasan rusak karena kelalaian toko, retur tidak disetujui',
            ]);

        $rejectResp->assertStatus(200);

        // 3. Database verification
        $header = StoreReturnHeader::findOrFail($returnId);
        $this->assertEquals('ditolak', $header->status);
        $this->assertStringContainsString('Kemasan rusak karena kelalaian toko', $header->notes);

        // 4. Stock restored to 25
        $stockAfterReject = ProductStock::where('product_id', $this->product->id)
            ->where('location_id', $this->storeBranch->id)
            ->value('stock');
        $this->assertEquals(25, $stockAfterReject);

        // 5. Kasir checks returns list -> status must be 'ditolak'
        $updatedCashierListResp = $this->actingAs($this->cashier)
            ->getJson('/api/returns?direction=toko_ke_gudang');
        $updatedCashierListResp->assertStatus(200);

        $rejectedDoc = collect($updatedCashierListResp->json('data'))->firstWhere('id', $returnId);
        $this->assertNotNull($rejectedDoc);
        $this->assertEquals('ditolak', $rejectedDoc['statusKey']);
        $this->assertEquals('DITOLAK', $rejectedDoc['statusLabel']);
        $this->assertEquals('Ditolak oleh Supervisor', $rejectedDoc['statusSub']);
    }

    /**
     * Test: When manager rejects return submission from "Barang Masuk Retur" page,
     * status in cashier view changes to "ditolak".
     */
    public function test_manager_rejects_return_from_barang_masuk_retur_page(): void
    {
        // 1. Kasir mengajukan retur 3 pcs
        $createResp = $this->actingAs($this->cashier)
            ->postJson('/api/returns', [
                'direction' => 'toko_ke_gudang',
                'condition_type' => 'rusak',
                'branch' => 'Toko Cabang Test',
                'from_location_id' => $this->storeBranch->id,
                'reason' => 'Produk cacat segel',
                'items' => [
                    [
                        'product_id' => $this->product->id,
                        'qty' => 3,
                        'condition' => 'RUSAK',
                    ]
                ],
            ]);

        $createResp->assertStatus(201);
        $returnId = $createResp->json('data.id');

        // 2. Manager menolak dari page "Barang Masuk Retur" saat verifikasi fisik
        $rejectResp = $this->actingAs($this->manager)
            ->putJson("/api/returns/{$returnId}/status", [
                'status' => 'ditolak',
                'reason' => 'Ditolak QC DC: Cacat kelalaian toko',
                'notes' => 'Pemeriksaan fisik gudang pusat tidak memenuhi kriteria retur',
            ]);

        $rejectResp->assertStatus(200);

        // 3. Status di database berubah menjadi 'ditolak'
        $this->assertEquals('ditolak', StoreReturnHeader::findOrFail($returnId)->status);

        // 4. Kasir yang mengajukan melihat status 'ditolak'
        $cashierListResp = $this->actingAs($this->cashier)
            ->getJson('/api/returns?direction=toko_ke_gudang');
        $cashierListResp->assertStatus(200);

        $doc = collect($cashierListResp->json('data'))->firstWhere('id', $returnId);
        $this->assertEquals('ditolak', $doc['statusKey']);
        $this->assertEquals('DITOLAK', $doc['statusLabel']);
    }
}
