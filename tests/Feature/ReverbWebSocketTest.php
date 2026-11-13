<?php

namespace Tests\Feature;

use App\Events\CashierShiftEvent;
use App\Events\InventoryUpdatedEvent;
use App\Events\NotificationBroadcastEvent;
use App\Events\StockUpdatedEvent;
use App\Events\TransactionCreatedEvent;
use App\Models\CashierShift;
use App\Models\Location;
use App\Models\Product;
use App\Models\SystemNotification;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class ReverbWebSocketTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
    }

    public function test_notification_broadcast_event_structure_and_channels(): void
    {
        $notification = SystemNotification::create([
            'role' => 'KASIR',
            'title' => 'Diskon Promo Berhasil Diterapkan',
            'message' => 'Diskon 10% untuk transaksi promo hari ini.',
            'category' => 'PROMOTION',
            'icon' => 'percent',
            'color' => 'blue',
            'is_read' => false,
        ]);

        $event = new NotificationBroadcastEvent($notification);

        $channels = $event->broadcastOn();
        $this->assertCount(2, $channels);
        $this->assertEquals('notifications', $channels[0]->name);
        $this->assertEquals('notifications.kasir', $channels[1]->name);
        $this->assertEquals('notification.created', $event->broadcastAs());

        $payload = $event->broadcastWith();
        $this->assertArrayHasKey('notification', $payload);
        $this->assertEquals('Diskon Promo Berhasil Diterapkan', $payload['notification']['title']);
        $this->assertEquals('KASIR', $payload['notification']['role']);
    }

    public function test_transaction_created_event_structure_and_channels(): void
    {
        $shift = CashierShift::where('status', 'OPEN')->first() ?? CashierShift::create([
            'shift_code' => 'CS-TEST-WS-' . uniqid(),
            'terminal_code' => 'POS-01',
            'cashier_nik' => 'KASIR01',
            'cashier_name' => 'Budi Kasir',
            'opening_cash' => 100000,
            'status' => 'OPEN',
            'opened_at' => now(),
        ]);

        $inv = 'INV-TEST-WS-' . uniqid();
        $location = Location::first();
        $transaction = Transaction::create([
            'shift_id' => $shift->id,
            'invoice_number' => $inv,
            'terminal_code' => 'POS-01',
            'cashier_nik' => 'KASIR01',
            'cashier_name' => 'Budi Kasir',
            'payment_method' => 'TUNAI',
            'subtotal' => 50000,
            'discount_total' => 0,
            'tax_total' => 5500,
            'grand_total' => 55500,
            'cash_received' => 60000,
            'change_returned' => 4500,
            'location_id' => $location?->id ?? 1,
            'status' => 'COMPLETED',
        ]);

        $event = new TransactionCreatedEvent($transaction);

        $channels = $event->broadcastOn();
        $this->assertNotEmpty($channels);
        $this->assertEquals('pos-transactions', $channels[0]->name);
        $this->assertEquals('transaction.created', $event->broadcastAs());

        $payload = $event->broadcastWith();
        $this->assertArrayHasKey('transaction', $payload);
        $this->assertEquals($inv, $payload['transaction']['invoice_number']);
        $this->assertEquals(55500, $payload['transaction']['total_amount']);
    }

    public function test_stock_updated_event_structure_and_channels(): void
    {
        $event = new StockUpdatedEvent([
            'location_id' => 1,
            'type' => 'checkout',
            'sku' => 'BRG-001',
            'new_stock' => 25,
        ]);

        $channels = $event->broadcastOn();
        $this->assertEquals('inventory', $channels[0]->name);
        $this->assertEquals('branch.1', $channels[1]->name);
        $this->assertEquals('stock.updated', $event->broadcastAs());

        $payload = $event->broadcastWith();
        $this->assertEquals('checkout', $payload['stock']['type']);
    }

    public function test_cashier_shift_event_structure_and_channels(): void
    {
        $code = 'CS-TEST-WS-' . uniqid();
        $shift = CashierShift::create([
            'shift_code' => $code,
            'terminal_code' => 'POS-01',
            'cashier_nik' => 'KASIR01',
            'cashier_name' => 'Budi Kasir',
            'opening_cash' => 100000,
            'status' => 'OPEN',
            'opened_at' => now(),
        ]);

        $event = new CashierShiftEvent($shift, 'opened');

        $channels = $event->broadcastOn();
        $this->assertEquals('cashier-shifts', $channels[0]->name);
        $this->assertEquals('shift.opened', $event->broadcastAs());

        $payload = $event->broadcastWith();
        $this->assertEquals('opened', $payload['action']);
        $this->assertEquals($code, $payload['shift']['shift_code']);
    }

    public function test_api_notification_store_triggers_reverb_broadcast_event(): void
    {
        Event::fake([NotificationBroadcastEvent::class]);

        $response = $this->postJson('/api/notifications', [
            'role' => 'MANAGER',
            'title' => 'Permintaan Void Transaksi',
            'message' => 'Kasir meminta persetujuan void nota INV-009.',
            'category' => 'VOID',
            'icon' => 'delete',
            'color' => 'rose',
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('success', true);

        Event::assertDispatched(NotificationBroadcastEvent::class, function ($event) {
            return $event->notification['title'] === 'Permintaan Void Transaksi'
                && $event->notification['role'] === 'MANAGER';
        });
    }

    public function test_api_checkout_triggers_transaction_and_stock_broadcast_events(): void
    {
        Event::fake([TransactionCreatedEvent::class, StockUpdatedEvent::class, NotificationBroadcastEvent::class]);

        $shift = CashierShift::where('status', 'OPEN')->first();
        if (!$shift) {
            $shift = CashierShift::create([
                'shift_code' => 'CS-WS-TEST-02',
                'terminal_code' => 'POS-01',
                'cashier_nik' => 'KASIR01',
                'cashier_name' => 'Budi Kasir',
                'opening_cash' => 200000,
                'status' => 'OPEN',
                'opened_at' => now(),
            ]);
        }

        $product = Product::where('stock', '>', 5)->first();

        $response = $this->postJson('/api/pos/checkout', [
            'shift_id' => $shift->id,
            'terminal_code' => $shift->terminal_code,
            'cashier_nik' => $shift->cashier_nik,
            'cashier_name' => $shift->cashier_name,
            'payment_method' => 'TUNAI',
            'cash_received' => 100000,
            'items' => [
                [
                    'sku' => $product->sku,
                    'name' => $product->name,
                    'qty' => 1,
                    'price' => (float)$product->selling_price,
                    'unit' => $product->unit,
                ]
            ],
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('success', true);

        Event::assertDispatched(TransactionCreatedEvent::class);
        Event::assertDispatched(StockUpdatedEvent::class);
    }
}
