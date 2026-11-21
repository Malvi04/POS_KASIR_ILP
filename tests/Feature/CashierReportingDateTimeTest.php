<?php

namespace Tests\Feature;

use App\Models\CashierShift;
use App\Models\HeldBill;
use App\Models\Transaction;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CashierReportingDateTimeTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::firstOrCreate(
            ['username' => 'kasir_test_wib'],
            [
                'nik' => '998877661122',
                'name' => 'Kasir WIB Test',
                'email' => 'kasirwib@test.com',
                'role' => 'Kasir',
                'pin' => '123456',
                'branch' => 'Pusat',
                'is_active' => true,
                'password' => Hash::make('password'),
            ]
        );
        $this->actingAs($this->user);
    }

    public function test_held_bills_returns_jakarta_date_and_time(): void
    {
        // 1. Create active shift
        $shift = CashierShift::create([
            'shift_code' => 'SFT-TEST-' . rand(100, 999),
            'terminal_code' => 'POS-01',
            'cashier_nik' => $this->user->nik,
            'cashier_name' => $this->user->name,
            'status' => 'OPEN',
            'opening_cash' => 500000,
            'opened_at' => Carbon::now('Asia/Jakarta'),
        ]);

        // 2. Create a held bill
        $held = HeldBill::create([
            'shift_id' => $shift->id,
            'terminal_code' => 'POS-01',
            'hold_code' => '#HOLD-' . rand(1000, 9999),
            'customer_name' => 'Budi Santoso',
            'total_items' => 2,
            'subtotal' => 25000,
            'tax_total' => 2750,
            'grand_total' => 27750,
            'status' => 'HELD',
            'held_at' => Carbon::now('Asia/Jakarta'),
            'items_payload' => [
                ['name' => 'Item A', 'price' => 10000, 'qty' => 1],
                ['name' => 'Item B', 'price' => 15000, 'qty' => 1],
            ],
            'note' => 'Pelanggan ambil barang tambahan',
        ]);

        $response = $this->getJson('/api/pos/held-bills?terminal_code=POS-01');
        $response->assertStatus(200);
        $response->assertJsonPath('success', true);

        $data = collect($response->json('data'))->firstWhere('hold_code', $held->hold_code);
        $this->assertNotNull($data);
        $this->assertStringContainsString('WIB', $data['hold_time_label']);
        $this->assertNotEmpty($data['hold_date_label']);
        $this->assertStringContainsString('WIB', $data['hold_datetime_label']);
    }

    public function test_transaction_reports_returns_jakarta_date_and_time_and_period_filters(): void
    {
        $nowWib = Carbon::now('Asia/Jakarta');

        $shift = CashierShift::create([
            'shift_code' => 'SFT-TEST-' . rand(100, 999),
            'terminal_code' => 'POS-01',
            'cashier_nik' => $this->user->nik,
            'cashier_name' => $this->user->name,
            'status' => 'OPEN',
            'opening_cash' => 500000,
            'opened_at' => $nowWib,
        ]);

        $trx = Transaction::create([
            'shift_id' => $shift->id,
            'invoice_number' => 'INV-TEST-WIB-' . rand(1000, 9999),
            'terminal_code' => 'POS-01',
            'cashier_name' => $this->user->name,
            'payment_method' => 'TUNAI',
            'subtotal' => 50000,
            'tax' => 5500,
            'grand_total' => 55500,
            'status' => 'SUCCESS',
            'created_at' => $nowWib,
            'updated_at' => $nowWib,
        ]);

        // Query today
        $resToday = $this->getJson('/api/pos/transactions?date=today');
        $resToday->assertStatus(200);
        $item = collect($resToday->json('data'))->firstWhere('id', $trx->invoice_number);
        $this->assertNotNull($item);
        $this->assertStringContainsString('WIB', $item['time']);
        $this->assertEquals($nowWib->format('Y-m-d'), $item['date_iso']);

        // Query by custom date
        $resCustom = $this->getJson('/api/pos/transactions?date=' . $nowWib->format('Y-m-d'));
        $resCustom->assertStatus(200);
        $itemCustom = collect($resCustom->json('data'))->firstWhere('id', $trx->invoice_number);
        $this->assertNotNull($itemCustom);
    }
}
