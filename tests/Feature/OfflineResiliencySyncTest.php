<?php

namespace Tests\Feature;

use App\Models\CashierShift;
use App\Models\Member;
use App\Models\Product;
use App\Models\Transaction;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class OfflineResiliencySyncTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        User::updateOrCreate(
            ['nik' => '202409821002'],
            [
                'name' => 'Budi Kurniawan',
                'username' => 'budi_kasir01',
                'email' => 'budi_offline@minimarket.ilp',
                'role' => 'Kasir Utama',
                'pin' => '123456',
                'branch' => 'Pusat',
                'password' => Hash::make('budi123'),
            ]
        );
    }

    /**
     * Test sync-offline endpoint processes multiple offline transactions and movements.
     */
    public function test_batch_offline_transactions_sync(): void
    {
        // 1. Prepare Product
        $sku = 'PRD-OFFLINE-' . Str::random(4);
        $barcode = '899' . mt_rand(100000000, 999999999);
        $product = Product::create([
            'sku' => $sku,
            'barcode' => $barcode,
            'name' => 'Produk Uji Offline Resiliency',
            'category' => 'Makanan',
            'unit' => 'Pcs',
            'cost_price' => 5000,
            'price' => 10000,
            'member_price' => 9000,
            'stock' => 50,
            'min_stock' => 5,
            'status' => 'TERSEDIA',
        ]);

        // 2. Prepare Member
        $memberNik = 'NIK-MEMBER-' . mt_rand(1000, 9999);
        $member = Member::create([
            'member_code' => 'MBR-' . mt_rand(1000, 9999),
            'nik' => $memberNik,
            'name' => 'Anggota Uji Offline',
            'department' => 'Agronomi',
            'credit_limit' => 500000,
            'current_debt' => 0,
            'status' => 'AKTIF',
        ]);

        // 3. Prepare an open shift
        $termCode = 'POS-OFFLINE-TEST';
        CashierShift::where('terminal_code', $termCode)->delete();
        $shift = CashierShift::create([
            'shift_code' => 'CS-OFFLINE-TEST-001',
            'terminal_code' => $termCode,
            'cashier_nik' => '202409821002',
            'cashier_name' => 'Budi Kurniawan',
            'opening_cash' => 100000,
            'cash_sales_total' => 0,
            'qris_sales_total' => 0,
            'credit_sales_total' => 0,
            'total_sales' => 0,
            'transaction_count' => 0,
            'cash_in' => 0,
            'cash_out' => 0,
            'expected_cash' => 100000,
            'status' => 'OPEN',
            'opened_at' => now(),
        ]);

        $offlineInv1 = 'TRX-OFF-' . date('Ymd') . '-TEST-001';
        $offlineInv2 = 'TRX-OFF-' . date('Ymd') . '-TEST-002';

        // 4. Send Batch Offline Sync Request
        $payload = [
            'transactions' => [
                [
                    'offline_id' => 'off_uuid_001',
                    'invoice_number' => $offlineInv1,
                    'terminal_code' => $termCode,
                    'cashier_nik' => '202409821002',
                    'cashier_name' => 'Budi Kurniawan',
                    'payment_method' => 'TUNAI',
                    'cash_received' => 50000,
                    'change_returned' => 30000,
                    'payment_reference' => null,
                    'split_cash' => 0,
                    'split_non_cash' => 0,
                    'member_nik' => null,
                    'created_at' => now()->subMinutes(15)->toIso8601String(),
                    'subtotal' => 20000,
                    'discount_total' => 0,
                    'tax_total' => 0,
                    'grand_total' => 20000,
                    'items' => [
                        [
                            'sku' => $product->sku,
                            'barcode' => $product->barcode,
                            'name' => $product->name,
                            'qty' => 2,
                            'price' => 10000,
                            'total' => 20000,
                            'unit' => 'Pcs',
                        ]
                    ]
                ],
                [
                    'offline_id' => 'off_uuid_002',
                    'invoice_number' => $offlineInv2,
                    'terminal_code' => $termCode,
                    'cashier_nik' => '202409821002',
                    'cashier_name' => 'Budi Kurniawan',
                    'payment_method' => 'KREDIT',
                    'cash_received' => 0,
                    'change_returned' => 0,
                    'payment_reference' => null,
                    'split_cash' => 0,
                    'split_non_cash' => 0,
                    'member_nik' => $member->nik,
                    'created_at' => now()->subMinutes(10)->toIso8601String(),
                    'subtotal' => 10000,
                    'discount_total' => 0,
                    'tax_total' => 0,
                    'grand_total' => 10000,
                    'items' => [
                        [
                            'sku' => $product->sku,
                            'barcode' => $product->barcode,
                            'name' => $product->name,
                            'qty' => 1,
                            'price' => 10000,
                            'total' => 10000,
                            'unit' => 'Pcs',
                        ]
                    ]
                ],
            ],
            'cash_movements' => [
                [
                    'offline_id' => 'mov_uuid_001',
                    'terminal_code' => $termCode,
                    'type' => 'OUT',
                    'amount' => 15000,
                    'reason' => 'Beli plastik kasir',
                    'created_at' => now()->subMinutes(12)->toIso8601String(),
                ]
            ]
        ];

        $response = $this->postJson('/api/pos/sync-offline', $payload);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'synced_count' => 2,
                'duplicate_count' => 0,
            ]);

        // 5. Assert Database Transactions created
        $this->assertDatabaseHas('transactions', [
            'invoice_number' => $offlineInv1,
            'payment_method' => 'TUNAI',
            'grand_total' => 20000,
        ]);

        $this->assertDatabaseHas('transactions', [
            'invoice_number' => $offlineInv2,
            'payment_method' => 'KREDIT',
            'member_nik' => $member->nik,
            'grand_total' => 10000,
        ]);

        // 6. Assert Stock Deducted (50 - 2 - 1 = 47)
        $product->refresh();
        $this->assertEquals(47, $product->stock);

        // 7. Assert Member Kasbon recorded (10.000)
        $member->refresh();
        $this->assertEquals(10000, (float) $member->current_debt);

        // 8. Assert Shift Sales Updated
        $shift->refresh();
        $this->assertEquals(20000, (float) $shift->cash_sales_total);
        $this->assertEquals(10000, (float) $shift->credit_sales_total);
        $this->assertEquals(30000, (float) $shift->total_sales);
        $this->assertEquals(2, $shift->transaction_count);
        $this->assertEquals(15000, (float) $shift->cash_out);

        // 9. Deduplication Test: Sending the same payload again should NOT re-deduct or duplicate
        $dupResponse = $this->postJson('/api/pos/sync-offline', $payload);
        $dupResponse->assertStatus(200)
            ->assertJson([
                'success' => true,
                'synced_count' => 0,
                'duplicate_count' => 2,
            ]);

        // Stock should still be 47 (no double deduction)
        $product->refresh();
        $this->assertEquals(47, $product->stock);
    }

    /**
     * Test PWA manifest and service worker files are accessible.
     */
    public function test_pwa_files_accessible(): void
    {
        $manifestResponse = $this->get('/manifest.json');
        $manifestResponse->assertStatus(200);

        $swResponse = $this->get('/sw.js');
        $swResponse->assertStatus(200);
    }

    /**
     * Test core cashier precached routes return 200 for Service Worker caching.
     */
    public function test_core_cashier_routes_accessible_for_sw_caching(): void
    {
        $this->get('/pos')->assertStatus(200);
        $this->get('/kasir/pos')->assertStatus(200);
        $this->get('/select-shift')->assertStatus(200);
        $this->get('/cetak-struk')->assertStatus(200);
        $this->get('/kasir/transactions')->assertStatus(200);
    }

    /**
     * Test offline sync automatically creates fallback shift if no open shift exists on server.
     */
    public function test_offline_sync_auto_allocates_shift_if_none_open(): void
    {
        $termCode = 'POS-AUTO-SHIFT-' . mt_rand(10, 99);
        CashierShift::where('terminal_code', $termCode)->delete();

        $payload = [
            'transactions' => [
                [
                    'offline_id' => 'off_auto_001',
                    'invoice_number' => 'TRX-OFF-AUTO-' . mt_rand(1000, 9999),
                    'terminal_code' => $termCode,
                    'cashier_nik' => '202409821002',
                    'cashier_name' => 'Budi Kurniawan',
                    'payment_method' => 'TUNAI',
                    'cash_received' => 25000,
                    'change_returned' => 0,
                    'grand_total' => 25000,
                    'created_at' => now()->toIso8601String(),
                    'items' => [
                        [
                            'sku' => 'GEN-01',
                            'barcode' => '899999',
                            'name' => 'Barang Darurat Offline',
                            'qty' => 1,
                            'price' => 25000,
                            'total' => 25000,
                        ]
                    ]
                ]
            ]
        ];

        $res = $this->postJson('/api/pos/sync-offline', $payload);
        $res->assertStatus(200)
            ->assertJson([
                'success' => true,
                'synced_count' => 1,
            ]);

        // Shift should have been auto-created
        $this->assertDatabaseHas('cashier_shifts', [
            'terminal_code' => $termCode,
            'cashier_nik' => '202409821002',
            'status' => 'OPEN',
        ]);
    }
}
