<?php

namespace Tests\Feature;

use App\Models\CashierShift;
use App\Models\Member;
use App\Models\Product;
use App\Models\Transaction;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PosKasirApiTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Ensure users exist
        User::updateOrCreate(
            ['nik' => '202409821002'],
            [
                'name' => 'Budi Kurniawan',
                'username' => 'budi_kasir01',
                'email' => 'budi@minimarket.ilp',
                'role' => 'Kasir Utama',
                'pin' => '123456',
                'branch' => 'Pusat',
                'password' => Hash::make('budi123'),
            ]
        );

        User::updateOrCreate(
            ['nik' => '202409821010'],
            [
                'name' => 'Hendra Wijaya',
                'username' => 'spv_hendra',
                'email' => 'spv@minimarket.ilp',
                'role' => 'Supervisor',
                'pin' => '123456',
                'branch' => 'Pusat',
                'password' => Hash::make('spv123'),
            ]
        );

        // Ensure test product has enough stock for checkout & barcode tests
        Product::where('barcode', '8991234567890')->update(['stock' => 100]);

        // Ensure near-expired test product has dynamic near-expired date (H-6)
        Product::where('barcode', '8993456789012')->update([
            'stock' => 50,
            'expired_date' => Carbon::today()->addDays(6)->toDateString(),
            'clearance_discount_percent' => 20.0,
        ]);
    }

    /**
     * Test 1: Shift & Modal Kasir (Wajib input modal awal laci).
     */
    public function test_shift_opening_and_closing_audit(): void
    {
        // 1. Close any existing open shift on test terminal
        CashierShift::where('terminal_code', 'POS-TEST')->update(['status' => 'CLOSED']);

        // 2. Open shift with opening cash Rp 200.000
        $response = $this->postJson('/api/shift/open', [
            'terminal_code' => 'POS-TEST',
            'cashier_nik' => '202409821002',
            'cashier_name' => 'Budi Kurniawan',
            'opening_cash' => 200000,
        ]);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
            ]);

        $shiftId = $response->json('data.id');

        // 3. Current shift check
        $currentRes = $this->getJson('/api/shift/current?terminal_code=POS-TEST');
        $currentRes->assertStatus(200)
            ->assertJson([
                'has_active_shift' => true,
                'data' => [
                    'opening_cash' => 200000,
                    'status' => 'OPEN',
                ],
            ]);

        // 4. Cash movement (Cash In Rp 50.000)
        $movementRes = $this->postJson('/api/shift/cash-movement', [
            'shift_id' => $shiftId,
            'type' => 'IN',
            'amount' => 50000,
            'reason' => 'Tambah uang kembalian receh',
        ]);
        $movementRes->assertStatus(200);

        // 5. Close shift with audit (Actual cash Rp 250.000 -> Sesuai)
        $closeRes = $this->postJson('/api/shift/close', [
            'shift_id' => $shiftId,
            'actual_cash' => 250000,
            'closing_notes' => 'Kas fisik sesuai',
        ]);

        $closeRes->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'variance_status' => 'SESUAI',
                    'variance' => 0,
                ],
            ]);
    }

    /**
     * Test 2: Fast POS search & Barcode scan with Real-time Stock and Tax status.
     */
    public function test_fast_pos_search_and_barcode_lookup(): void
    {
        $barcode = '8991234567890'; // Aqua 600ml
        $res = $this->getJson("/api/pos/products/barcode/{$barcode}");

        $res->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'barcode' => $barcode,
                    'is_taxable' => true,
                    'is_in_stock' => true,
                ],
            ]);

        // Search by query
        $searchRes = $this->getJson('/api/pos/products/search?q=Aqua');
        $searchRes->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    '*' => ['id', 'sku', 'barcode', 'name', 'stock', 'is_taxable', 'tax_label', 'price']
                ],
            ]);
    }

    /**
     * Test 3: Auto-Discount for Near-Expired Products (Default H-14).
     */
    public function test_auto_discount_near_expired_products(): void
    {
        // Ultra Milk is expiring in 6 days (H-6), default clearance discount is 20%
        $barcode = '8993456789012';
        $res = $this->getJson("/api/pos/products/barcode/{$barcode}");

        $res->assertStatus(200);
        $data = $res->json('data');

        $this->assertTrue($data['is_near_expired'], 'Produk harus terdeteksi near expired (H-14)');
        $this->assertEquals(20.0, $data['clearance_discount_percent'], 'Persentase diskon clearance harus 20%');
        $this->assertGreaterThan(0, $data['clearance_discount_amount'], 'Nominal diskon harus terhitung otomatis');
        $this->assertLessThan($data['original_price'], $data['price'], 'Harga jual harus otomatis terpotong diskon');
    }

    /**
     * Test 4: Multi-Type Payment (Cash with automatic change, QRIS with reference, Member Credit limit validation).
     */
    public function test_multi_type_payment_and_credit_limit(): void
    {
        // 1. Prepare open shift
        $shift = CashierShift::create([
            'shift_code' => 'CS-TEST-' . uniqid() . '-' . rand(1000, 9999),
            'terminal_code' => 'POS-02',
            'cashier_nik' => '202409821002',
            'cashier_name' => 'Budi Kurniawan',
            'opening_cash' => 100000,
            'status' => 'OPEN',
            'opened_at' => now(),
        ]);

        $aqua = Product::where('barcode', '8991234567890')->first();

        // A. Cash Payment
        $cashRes = $this->postJson('/api/pos/checkout', [
            'shift_id' => $shift->id,
            'terminal_code' => 'POS-02',
            'cashier_name' => 'Budi Kurniawan',
            'payment_method' => 'TUNAI',
            'cash_received' => 50000,
            'items' => [
                [
                    'sku' => $aqua->sku,
                    'barcode' => $aqua->barcode,
                    'name' => $aqua->name,
                    'price' => $aqua->price,
                    'qty' => 2,
                ]
            ],
        ]);

        $cashRes->assertStatus(201)
            ->assertJson([
                'success' => true,
                'data' => [
                    'payment_method' => 'TUNAI',
                ],
            ]);

        $this->assertGreaterThan(0, $cashRes->json('data.change_returned'));

        // B. QRIS Payment with Reference
        $qrisRes = $this->postJson('/api/pos/checkout', [
            'shift_id' => $shift->id,
            'terminal_code' => 'POS-02',
            'cashier_name' => 'Budi Kurniawan',
            'payment_method' => 'QRIS',
            'payment_reference' => 'QRIS-REF-998811',
            'items' => [
                [
                    'sku' => $aqua->sku,
                    'barcode' => $aqua->barcode,
                    'name' => $aqua->name,
                    'price' => $aqua->price,
                    'qty' => 1,
                ]
            ],
        ]);

        $qrisRes->assertStatus(201)
            ->assertJson([
                'success' => true,
                'data' => [
                    'payment_method' => 'QRIS',
                    'payment_reference' => 'QRIS-REF-998811',
                ],
            ]);

        // C. Member Credit (Kasbon Potong Gaji) - Validasi NIK & Plafon
        $member = Member::where('nik', '1029384756')->first(); // Santoso, sisa plafon Rp 1.120.000
        $creditRes = $this->postJson('/api/pos/checkout', [
            'shift_id' => $shift->id,
            'terminal_code' => 'POS-02',
            'cashier_name' => 'Budi Kurniawan',
            'payment_method' => 'KREDIT',
            'member_nik' => $member->nik,
            'items' => [
                [
                    'sku' => $aqua->sku,
                    'barcode' => $aqua->barcode,
                    'name' => $aqua->name,
                    'price' => $aqua->member_price ?? $aqua->price,
                    'qty' => 10,
                ]
            ],
        ]);

        $creditRes->assertStatus(201)
            ->assertJson([
                'success' => true,
                'data' => [
                    'payment_method' => 'KREDIT',
                ],
            ]);

        // D. Member Credit Rejection when Exceeding Limit
        $blockedMember = Member::where('nik', '327501002230099')->first(); // Andri Setiawan (DIBLOKIR / 0 sisa)
        $rejectRes = $this->postJson('/api/pos/checkout', [
            'shift_id' => $shift->id,
            'terminal_code' => 'POS-02',
            'cashier_name' => 'Budi Kurniawan',
            'payment_method' => 'KREDIT',
            'member_nik' => $blockedMember->nik,
            'items' => [
                [
                    'sku' => $aqua->sku,
                    'barcode' => $aqua->barcode,
                    'name' => $aqua->name,
                    'price' => $aqua->price,
                    'qty' => 1,
                ]
            ],
        ]);

        $rejectRes->assertStatus(422)
            ->assertJson([
                'success' => false,
                'error_code' => 'CREDIT_LIMIT_EXCEEDED',
            ]);
    }

    /**
     * Test 5: Hold Bill and Recall.
     */
    public function test_hold_bill_and_recall(): void
    {
        $shift = CashierShift::where('status', 'OPEN')->latest('id')->first();
        if (!$shift) {
            $shift = CashierShift::create([
                'shift_code' => 'CS-HOLD-' . rand(100, 999),
                'terminal_code' => 'POS-01',
                'cashier_nik' => '202409821002',
                'cashier_name' => 'Budi Kurniawan',
                'opening_cash' => 150000,
                'status' => 'OPEN',
                'opened_at' => now(),
            ]);
        }

        // Hold bill
        $holdRes = $this->postJson('/api/pos/hold', [
            'shift_id' => $shift->id,
            'terminal_code' => $shift->terminal_code,
            'customer_name' => 'Pelanggan Tertahan',
            'items' => [
                [
                    'sku' => 'PRD-001',
                    'name' => 'Aqua Mineral 600ml',
                    'price' => 5500,
                    'qty' => 3,
                ]
            ],
        ]);

        $holdRes->assertStatus(201);
        $holdId = $holdRes->json('data.id');

        // List held bills
        $listRes = $this->getJson("/api/pos/held-bills?shift_id={$shift->id}");
        $listRes->assertStatus(200);
        $this->assertGreaterThan(0, $listRes->json('count'));

        // Recall held bill
        $recallRes = $this->postJson("/api/pos/recall/{$holdId}");
        $recallRes->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);
        $this->assertNotEmpty($recallRes->json('data.items'));
    }

    /**
     * Test 6: Anti-Fraud Void Authorization (PIN Supervisor & Transaction Cancellation).
     */
    public function test_anti_fraud_void_authorization(): void
    {
        // 1. Wrong PIN fails
        $wrongPin = $this->postJson('/api/pos/void/authorize', [
            'pin_or_password' => '000000',
        ]);
        $wrongPin->assertStatus(403);

        // 2. Correct Supervisor PIN (123456) succeeds
        $correctPin = $this->postJson('/api/pos/void/authorize', [
            'pin_or_password' => '123456',
        ]);
        $correctPin->assertStatus(200)
            ->assertJson([
                'authorized' => true,
            ]);

        // 3. Void item log
        $voidItemRes = $this->postJson('/api/pos/void/item', [
            'pin_or_password' => '123456',
            'item_sku' => 'PRD-001',
            'item_name' => 'Aqua Mineral 600ml',
            'item_qty' => 1,
            'amount_voided' => 5500,
            'cashier_name' => 'Budi Kurniawan',
            'terminal_code' => 'POS-01',
            'reason' => 'Pelanggan salah ambil barang',
        ]);
        $voidItemRes->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);

        // 4. Void entire transaction with stock restoration
        $product = Product::where('sku', 'PRD-001')->first();
        $stockBefore = $product->stock;

        // Perform a checkout to get a real invoice
        $shift = CashierShift::where('status', 'OPEN')->latest('id')->first();
        $checkoutRes = $this->postJson('/api/pos/checkout', [
            'shift_id' => $shift->id,
            'terminal_code' => $shift->terminal_code,
            'payment_method' => 'TUNAI',
            'cash_received' => 20000,
            'items' => [
                [
                    'sku' => $product->sku,
                    'barcode' => $product->barcode,
                    'name' => $product->name,
                    'price' => $product->price,
                    'qty' => 2,
                ]
            ],
        ]);
        $invoice = $checkoutRes->json('data.invoice_number');

        // Check stock reduced
        $this->assertEquals($stockBefore - 2, $product->fresh()->stock);

        // Void the invoice
        $voidTrxRes = $this->postJson('/api/pos/void/transaction', [
            'invoice_number' => $invoice,
            'pin_or_password' => '123456',
            'reason' => 'Pelanggan membatalkan seluruh transaksi',
        ]);

        $voidTrxRes->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'status' => 'VOIDED',
                ],
            ]);

        // Assert stock was restored
        $this->assertEquals($stockBefore, $product->fresh()->stock, 'Stok produk harus kembali setelah void');
    }
}
