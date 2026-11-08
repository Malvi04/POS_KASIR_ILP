<?php

namespace Tests\Feature;

use App\Models\ChartOfAccount;
use App\Models\CouponUnit;
use App\Models\GoodsReceipt;
use App\Models\Location;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Purchase;
use App\Models\SalesReturn;
use App\Models\StockRequest;
use App\Models\StockTransfer;
use App\Models\StoreReturnHeader;
use App\Models\Supplier;
use App\Models\Unit;
use App\Models\User;
use Tests\TestCase;

class ErdAlignmentDatabaseTest extends TestCase
{
    /**
     * Test ERD Locations, Categories, Units and Chart of Accounts Seeding.
     */
    public function test_erd_master_data_seeded(): void
    {
        // 1. Locations
        $locations = Location::all();
        $this->assertGreaterThanOrEqual(4, $locations->count());
        $this->assertTrue($locations->contains('code', 'LOC-01'));
        $this->assertTrue($locations->contains('code', 'LOC-DC'));

        // 2. Product Categories
        $categories = ProductCategory::all();
        $this->assertGreaterThanOrEqual(5, $categories->count());
        $this->assertTrue($categories->contains('name', 'Kebutuhan Pokok'));

        // 3. Units
        $units = Unit::all();
        $this->assertGreaterThanOrEqual(6, $units->count());
        $this->assertTrue($units->contains('symbol', 'Dus'));

        // 4. Chart of Accounts
        $coas = ChartOfAccount::all();
        $this->assertGreaterThanOrEqual(8, $coas->count());
        $this->assertTrue($coas->contains('account_code', '1-1001'));
        $this->assertTrue($coas->contains('account_code', '4-4002'));
    }

    /**
     * Test ERD Purchases and Goods Receipts Lifecycle.
     */
    public function test_erd_purchase_and_goods_receipt(): void
    {
        $supplier = Supplier::first();
        $location = Location::where('code', 'LOC-DC')->first();
        $user = User::first();
        $product = Product::first();

        // 1. Create Purchase Order
        $purchase = Purchase::create([
            'purchase_number' => 'PO-' . time(),
            'supplier_id' => $supplier?->id,
            'location_id' => $location?->id,
            'created_by' => $user?->id,
            'purchase_date' => now()->toDateString(),
            'status' => 'ORDERED',
            'total_amount' => 500000,
        ]);

        $purchase->items()->create([
            'product_id' => $product->id,
            'qty' => 50,
            'purchase_price' => 10000,
            'ownership_type' => 'OUTRIGHT',
        ]);

        $this->assertNotNull($purchase->id);
        $this->assertEquals(1, $purchase->items()->count());

        // 2. Create Goods Receipt
        $receipt = GoodsReceipt::create([
            'receipt_number' => 'SJ-' . time(),
            'purchase_id' => $purchase->id,
            'received_by' => $user?->id,
            'received_at' => now(),
            'driver_name' => 'Pak Joko',
            'plate_number' => 'BE 1234 LP',
            'origin_source' => 'Gudang DC',
            'status' => 'DITERIMA_LENGKAP',
        ]);

        $receipt->items()->create([
            'product_id' => $product->id,
            'qty_sent' => 50,
            'qty_received' => 50,
            'actual_unit_cost' => 10000,
            'condition' => 'BAIK',
        ]);

        $this->assertNotNull($receipt->id);
        $this->assertEquals(50, $receipt->items()->first()->qty_received);
    }

    /**
     * Test ERD Stock Request and Stock Transfer Lifecycle.
     */
    public function test_erd_stock_transfer_between_locations(): void
    {
        $fromLoc = Location::where('code', 'LOC-DC')->first();
        $toLoc = Location::where('code', 'LOC-01')->first();
        $user = User::first();
        $product = Product::first();

        // 1. Stock Request
        $request = StockRequest::create([
            'request_number' => 'REQ-' . time(),
            'from_location_id' => $fromLoc?->id,
            'to_location_id' => $toLoc?->id,
            'requested_by' => $user?->id,
            'requested_at' => now(),
            'status' => 'APPROVED',
        ]);

        $request->items()->create([
            'product_id' => $product->id,
            'qty_requested' => 100,
            'qty_approved' => 100,
        ]);

        // 2. Stock Transfer
        $transfer = StockTransfer::create([
            'transfer_number' => 'TRF-' . time(),
            'from_location_id' => $fromLoc?->id,
            'to_location_id' => $toLoc?->id,
            'request_id' => $request->id,
            'created_by' => $user?->id,
            'shipped_at' => now(),
            'status' => 'IN_TRANSIT',
        ]);

        $transfer->items()->create([
            'product_id' => $product->id,
            'qty_sent' => 100,
            'qty_received' => 0,
        ]);

        $this->assertNotNull($transfer->id);
        $this->assertEquals('IN_TRANSIT', $transfer->status);
    }

    /**
     * Test ERD Coupon Units and Redemptions.
     */
    public function test_erd_coupon_units(): void
    {
        $product = Product::first();
        $serial = 'KPN-BBM-' . time();

        $coupon = CouponUnit::create([
            'product_id' => $product?->id,
            'serial_number' => $serial,
            'status' => 'AVAILABLE',
            'nominal_value' => 50000,
        ]);

        $this->assertNotNull($coupon->id);
        $this->assertEquals('AVAILABLE', $coupon->status);
        $this->assertEquals(50000, $coupon->nominal_value);
    }
}
