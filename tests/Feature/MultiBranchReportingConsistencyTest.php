<?php

use App\Models\CashierShift;
use App\Models\Location;
use App\Models\Product;
use App\Models\ProductStock;
use App\Models\Transaction;
use App\Models\TransactionItem;
use App\Models\User;
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

    $this->owner = User::create([
        'nik' => 'OWN-REP-01',
        'name' => 'Owner Report Test',
        'username' => 'own_rep',
        'email' => 'own.rep@test.local',
        'password' => Hash::make('password123'),
        'pin' => '999999',
        'role' => 'Owner',
        'branch' => 'Pusat',
        'is_active' => true,
    ]);

    $this->manager = User::create([
        'nik' => 'MGR-REP-01',
        'name' => 'Manager Report Test',
        'username' => 'mgr_rep',
        'email' => 'mgr.rep@test.local',
        'password' => Hash::make('password123'),
        'pin' => '888888',
        'role' => 'Manager',
        'branch' => 'Pusat',
        'is_active' => true,
    ]);

    $this->productA = Product::create([
        'sku' => 'REP-SKU-A',
        'barcode' => '8991110001',
        'name' => 'Produk Reguler A',
        'category' => 'Sembako',
        'unit' => 'Pcs',
        'price' => 25000,
        'cost_price' => 20000,
        'stock' => 100,
        'min_stock' => 10,
        'status' => 'TERSEDIA',
    ]);

    $this->productB = Product::create([
        'sku' => 'REP-SKU-B',
        'barcode' => '8991110002',
        'name' => 'Produk Belum Didistribusi',
        'category' => 'Sembako',
        'unit' => 'Pcs',
        'price' => 15000,
        'cost_price' => 10000,
        'stock' => 5, // Global low stock, NOT distributed to any store!
        'min_stock' => 10,
        'status' => 'STOK_RENDAH',
    ]);
});

function seedTxWithTwoItems(Location $loc, string $invoice, string $terminal, float $total, Product $prodA, Product $prodB): Transaction
{
    $shift = CashierShift::firstOrCreate(
        ['shift_code' => 'SHF-' . $loc->code . '-' . $terminal],
        [
            'terminal_code' => $terminal,
            'location_id' => $loc->id,
            'branch' => $loc->name,
            'cashier_nik' => 'KSR-' . $loc->code,
            'cashier_name' => 'Kasir ' . $loc->code,
            'opening_cash' => 100000,
            'status' => 'OPEN',
            'opened_at' => now(),
        ]
    );

    $tx = Transaction::create([
        'invoice_number' => $invoice,
        'shift_id' => $shift->id,
        'terminal_code' => $terminal,
        'location_id' => $loc->id,
        'branch' => $loc->name,
        'cashier_name' => 'Kasir ' . $loc->code,
        'payment_method' => 'TUNAI',
        'subtotal' => $total,
        'discount_total' => 0,
        'tax_total' => 0,
        'grand_total' => $total,
        'cash_received' => $total,
        'change_returned' => 0,
        'status' => 'SUCCESS',
    ]);

    // Multi-item transaction to guard against join duplication!
    TransactionItem::create([
        'transaction_id' => $tx->id,
        'product_id' => $prodA->id,
        'sku' => $prodA->sku,
        'barcode' => $prodA->barcode,
        'product_name' => $prodA->name,
        'unit' => 'Pcs',
        'unit_price' => $total / 2,
        'qty' => 1,
        'total_price' => $total / 2,
    ]);
    TransactionItem::create([
        'transaction_id' => $tx->id,
        'product_id' => $prodB->id,
        'sku' => $prodB->sku,
        'barcode' => $prodB->barcode,
        'product_name' => $prodB->name,
        'unit' => 'Pcs',
        'unit_price' => $total / 2,
        'qty' => 1,
        'total_price' => $total / 2,
    ]);

    return $tx;
}

test('multi-branch reports isolate stores, exclude warehouse, obey location_id over terminal_code, and avoid duplicates', function () {
    // Toko Pusat: 100.000 (on POS-01) + 50.000 (on POS-06 whose authoritative location_id is Toko Pusat!)
    seedTxWithTwoItems($this->tokoPusat, 'INV-PUSAT-1', 'POS-01', 100000, $this->productA, $this->productB);
    seedTxWithTwoItems($this->tokoPusat, 'INV-PUSAT-POS06', 'POS-06', 50000, $this->productA, $this->productB);

    // Cabang A: 200.000 on POS-06
    seedTxWithTwoItems($this->cabangA, 'INV-CABA-1', 'POS-06', 200000, $this->productA, $this->productB);

    // Cabang B: 300.000 on POS-11
    seedTxWithTwoItems($this->cabangB, 'INV-CABB-1', 'POS-11', 300000, $this->productA, $this->productB);

    // Gudang Pusat (WAREHOUSE): 999.000 -> MUST NOT leak into store sales reports!
    seedTxWithTwoItems($this->gudang, 'INV-GDG-1', 'GDG-01', 999000, $this->productA, $this->productB);

    // Historical UNMAPPED transaction (no location_id, unknown branch, unknown terminal LEGACY-99) -> must NOT be guessed into any store
    $legacyShift = CashierShift::create([
        'shift_code' => 'SHF-LEGACY-UNMAPPED',
        'terminal_code' => 'LEGACY-99',
        'location_id' => null,
        'branch' => null,
        'cashier_nik' => 'LEGACY-01',
        'cashier_name' => 'Kasir Lama',
        'opening_cash' => 0,
        'status' => 'CLOSED',
        'opened_at' => now()->subDay(),
        'closed_at' => now()->subDay(),
    ]);

    $unmappedTx = Transaction::create([
        'invoice_number' => 'INV-HIST-UNMAPPED',
        'shift_id' => $legacyShift->id,
        'terminal_code' => 'LEGACY-99',
        'location_id' => null,
        'branch' => null,
        'cashier_name' => 'Kasir Lama',
        'payment_method' => 'TUNAI',
        'subtotal' => 75000,
        'discount_total' => 0,
        'tax_total' => 0,
        'grand_total' => 75000,
        'cash_received' => 75000,
        'change_returned' => 0,
        'status' => 'SUCCESS',
    ]);

    // Historical transaction WITH valid branch reference (no location_id yet) -> deterministic fallback to Cabang B (25.000) without mutating DB
    $histShiftB = CashierShift::create([
        'shift_code' => 'SHF-HIST-CABB',
        'terminal_code' => 'POS-12',
        'location_id' => null,
        'branch' => 'Cabang B',
        'cashier_nik' => 'LEGACY-B1',
        'cashier_name' => 'Kasir Lama B',
        'opening_cash' => 0,
        'status' => 'CLOSED',
        'opened_at' => now()->subDay(),
        'closed_at' => now()->subDay(),
    ]);

    $histMapped = Transaction::create([
        'invoice_number' => 'INV-HIST-CABB',
        'shift_id' => $histShiftB->id,
        'terminal_code' => 'POS-12',
        'location_id' => null,
        'branch' => 'Cabang B',
        'cashier_name' => 'Kasir Lama B',
        'payment_method' => 'TUNAI',
        'subtotal' => 25000,
        'discount_total' => 0,
        'tax_total' => 0,
        'grand_total' => 25000,
        'cash_received' => 25000,
        'change_returned' => 0,
        'status' => 'SUCCESS',
    ]);

    $this->actingAs($this->owner);

    $today = now()->toDateString();

    // A. Consolidated Report
    $cons = $this->getJson("/api/reports/consolidated-branches?start_date={$today}&end_date={$today}")
        ->assertStatus(200)
        ->json('data');

    // Expected store totals: Toko Pusat = 150k (2 trx), Cabang A = 200k (1 trx), Cabang B = 325k (2 trx) -> Total = 675k (5 trx)
    expect((float) $cons['financial_summary']['gross_revenue'])->toBe(675000.0);
    expect((int) $cons['financial_summary']['transaction_count'])->toBe(5);
    expect((int) $cons['excluded_warehouse_transactions']['count'])->toBe(1);
    expect((float) $cons['excluded_warehouse_transactions']['total_amount'])->toBe(999000.0);
    expect((int) $cons['unmapped_transactions']['count'])->toBe(1);
    expect($cons['unmapped_transactions']['invoice_numbers'])->toContain('INV-HIST-UNMAPPED');

    $byCode = collect($cons['branch_breakdown'])->keyBy('branch_code');
    expect((float) $byCode['CAB-001']['total_revenue'])->toBe(150000.0);
    expect((int) $byCode['CAB-001']['transaction_count'])->toBe(2);
    expect((float) $byCode['CAB-002']['total_revenue'])->toBe(200000.0);
    expect((int) $byCode['CAB-002']['transaction_count'])->toBe(1);
    expect((float) $byCode['CAB-003']['total_revenue'])->toBe(325000.0);
    expect((int) $byCode['CAB-003']['transaction_count'])->toBe(2);

    // Sum of all store breakdown MUST equal consolidated gross_revenue
    $sumBranchRev = collect($cons['branch_breakdown'])->sum('total_revenue');
    $sumBranchCnt = collect($cons['branch_breakdown'])->sum('transaction_count');
    expect((float) $sumBranchRev)->toBe((float) $cons['financial_summary']['gross_revenue']);
    expect((int) $sumBranchCnt)->toBe((int) $cons['financial_summary']['transaction_count']);

    // Historical unmapped DB row MUST remain untouched (null location_id)
    expect($unmappedTx->fresh()->location_id)->toBeNull();
    expect($histMapped->fresh()->location_id)->toBeNull();

    // B. Accounting Sales Report (all vs branch filter)
    $salesAll = $this->getJson("/api/reports/sales?period=daily&date={$today}")
        ->assertStatus(200)
        ->json();
    expect((float) $salesAll['summary']['grand_total'])->toBe(675000.0);
    expect((int) $salesAll['summary']['transaction_count'])->toBe(5);
    expect((float) collect($salesAll['branch_breakdown'])->sum('total_sales'))->toBe((float) $salesAll['summary']['grand_total']);

    $salesCabA = $this->getJson("/api/reports/sales?period=daily&date={$today}&branch=Cabang%20A")
        ->assertStatus(200)
        ->json();
    expect((float) $salesCabA['summary']['grand_total'])->toBe(200000.0);
    expect((int) $salesCabA['summary']['transaction_count'])->toBe(1);

    $salesPusatByCode = $this->getJson("/api/reports/sales?period=daily&date={$today}&branch=CAB-001")
        ->assertStatus(200)
        ->json();
    expect((float) $salesPusatByCode['summary']['grand_total'])->toBe(150000.0);
    expect((int) $salesPusatByCode['summary']['transaction_count'])->toBe(2);

    // Filtering by Warehouse returns 0 store revenue
    $salesWarehouse = $this->getJson("/api/reports/sales?period=daily&date={$today}&branch=GDG-001")
        ->assertStatus(200)
        ->json();
    expect((float) $salesWarehouse['summary']['grand_total'])->toBe(0.0);

    // C. Accounting Profit/Loss with location filter
    $plCabB = $this->getJson("/api/reports/profit-loss?start_date={$today}&end_date={$today}&location_id={$this->cabangB->id}")
        ->assertStatus(200)
        ->json();
    expect((float) $plCabB['statement']['gross_sales'])->toBe(325000.0);

    // D. Owner Dashboard Summary
    $ownerDash = $this->getJson('/api/owner/dashboard-summary')
        ->assertStatus(200)
        ->json('data');
    expect((float) $ownerDash['total_sales_today'])->toBe(675000.0);
    expect((int) $ownerDash['total_trx_today'])->toBe(5);
    $ownerByCode = collect($ownerDash['branches'])->keyBy('code');
    expect((float) $ownerByCode['CAB-001']['rawSales'])->toBe(150000.0);
    expect((float) $ownerByCode['CAB-002']['rawSales'])->toBe(200000.0);
    expect((float) $ownerByCode['CAB-003']['rawSales'])->toBe(325000.0);
    expect((float) collect($ownerDash['branches'])->sum('rawSales'))->toBe((float) $ownerDash['total_sales_today']);
    expect((int) $ownerDash['excluded_warehouse_transactions']['count'])->toBe(1);
    expect((int) $ownerDash['unmapped_transactions']['count'])->toBe(1);
});

test('manager dashboard reports per-location product_stocks and never counts undistributed stock master as store low stock', function () {
    // Distribute productA ONLY to Warehouse (50 units) and Cabang A (3 units -> low stock!), NOT to Toko Pusat or Cabang B!
    // productB has global stock=5 (low in master), NOT distributed anywhere -> MUST NOT show as store low stock!
    ProductStock::create([
        'product_id' => $this->productA->id,
        'location_id' => $this->gudang->id,
        'stock' => 50,
        'min_stock' => 10,
        'is_active' => true,
    ]);
    ProductStock::create([
        'product_id' => $this->productA->id,
        'location_id' => $this->cabangA->id,
        'stock' => 3,
        'min_stock' => 10,
        'is_active' => true,
    ]);

    $this->actingAs($this->manager);

    $mgrAll = $this->getJson('/api/manager/dashboard-summary')
        ->assertStatus(200)
        ->json('data');

    expect($mgrAll['warehouse_stock_summary']['location_code'])->toBe('GDG-001');
    expect((int) $mgrAll['warehouse_stock_summary']['total_units'])->toBe(50);

    $storesByCode = collect($mgrAll['store_stocks_summary'])->keyBy('location_code');
    expect((int) $storesByCode['CAB-001']['total_skus'])->toBe(0);
    expect((int) $storesByCode['CAB-002']['total_skus'])->toBe(1);
    expect((int) $storesByCode['CAB-002']['total_units'])->toBe(3);
    expect((int) $storesByCode['CAB-002']['low_stock_count'])->toBe(1);
    expect((int) $storesByCode['CAB-003']['total_skus'])->toBe(0);

    // Only Cabang A has low stock (productB undistributed is excluded from location low stock!)
    expect((int) $mgrAll['low_stock_count'])->toBe(1);
    expect($mgrAll['low_stock_items'][0]['location_code'])->toBe('CAB-002');
    expect((int) $mgrAll['low_stock_items'][0]['stok'])->toBe(3);

    // Global Stock Master summary explicitly preserved & labeled separately
    expect((int) $mgrAll['global_stock_master']['total_skus'])->toBe(2);

    // Filtering Manager Dashboard specifically to Toko Pusat shows 0 low stock (since nothing is distributed there)
    $mgrPusat = $this->getJson("/api/manager/dashboard-summary?location_id={$this->tokoPusat->id}")
        ->assertStatus(200)
        ->json('data');
    expect((int) $mgrPusat['low_stock_count'])->toBe(0);
    expect($mgrPusat['low_stock_items'])->toBeEmpty();
});
