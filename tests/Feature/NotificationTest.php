<?php

namespace Tests\Feature;

use App\Models\SystemNotification;
use Tests\TestCase;

class NotificationTest extends TestCase
{
    public function test_can_fetch_notifications_for_kasir(): void
    {
        $response = $this->getJson('/api/notifications?role=KASIR');
        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'role',
            'unread_count',
            'data' => [
                '*' => [
                    'id',
                    'role',
                    'title',
                    'message',
                    'category',
                    'icon',
                    'color',
                    'link',
                    'is_read',
                    'time_ago',
                ]
            ]
        ]);

        $this->assertEquals('KASIR', $response->json('role'));
        $this->assertGreaterThan(0, count($response->json('data')));
    }

    public function test_can_fetch_notifications_for_manager(): void
    {
        $response = $this->getJson('/api/notifications?role=MANAGER');
        $response->assertStatus(200);
        $this->assertEquals('MANAGER', $response->json('role'));
        $this->assertGreaterThan(0, count($response->json('data')));
    }

    public function test_can_fetch_notifications_for_owner(): void
    {
        $response = $this->getJson('/api/notifications?role=OWNER');
        $response->assertStatus(200);
        $this->assertEquals('OWNER', $response->json('role'));
        $this->assertGreaterThan(0, count($response->json('data')));
    }

    public function test_can_mark_notification_as_read(): void
    {
        $notification = SystemNotification::first();
        $this->assertNotNull($notification);

        $response = $this->postJson("/api/notifications/mark-read/{$notification->id}");
        $response->assertStatus(200);

        $this->assertTrue($notification->fresh()->is_read);
    }

    public function test_can_create_new_notification(): void
    {
        $payload = [
            'role' => 'KASIR',
            'title' => 'Test Realtime Notification',
            'message' => 'Pesan uji coba aktivitas realtime sistem.',
            'category' => 'TRANSFER',
            'icon' => 'local_shipping',
            'color' => 'amber',
            'link' => '/kasir/produk/masuk',
        ];

        $response = $this->postJson('/api/notifications', $payload);
        $response->assertStatus(201);
        $this->assertDatabaseHas('system_notifications', [
            'title' => 'Test Realtime Notification',
            'role' => 'KASIR',
        ]);
    }

    public function test_shift_open_and_close_triggers_notifications(): void
    {
        $openPayload = [
            'terminal_code' => 'POS-TEST-NOTIF',
            'cashier_nik' => 'NIK-NOTIF-01',
            'cashier_name' => 'Kasir Test Notif',
            'opening_cash' => 150000,
        ];

        $resOpen = $this->postJson('/api/shift/open', $openPayload);
        $resOpen->assertStatus(201);

        $this->assertDatabaseHas('system_notifications', [
            'role' => 'MANAGER',
            'category' => 'CASHIER',
        ]);

        $closePayload = [
            'terminal_code' => 'POS-TEST-NOTIF',
            'actual_cash' => 150000,
            'closing_notes' => 'Test closing shift',
        ];

        $resClose = $this->postJson('/api/shift/close', $closePayload);
        $resClose->assertStatus(200);

        $this->assertDatabaseHas('system_notifications', [
            'role' => 'OWNER',
            'category' => 'CASHIER',
        ]);
    }

    public function test_leave_request_triggers_manager_and_kasir_notifications(): void
    {
        $payload = [
            'staffName' => 'Budi Staf Notif',
            'type' => 'IZIN',
            'date' => '12 Nov 2026',
            'reason' => 'Keperluan keluarga',
        ];

        $res = $this->postJson('/api/attendance/leaves', $payload);
        $res->assertStatus(201);

        $leaveId = $res->json('data.id');
        $this->assertDatabaseHas('system_notifications', [
            'role' => 'MANAGER',
            'category' => 'HR',
            'reference_id' => (string)$leaveId,
        ]);

        $updateRes = $this->putJson("/api/attendance/leaves/{$leaveId}/status", [
            'status' => 'DISETUJUI',
            'approved_by' => 'Manager Toko',
        ]);
        $updateRes->assertStatus(200);

        $this->assertDatabaseHas('system_notifications', [
            'role' => 'KASIR',
            'category' => 'HR',
            'reference_id' => (string)$leaveId,
        ]);
    }
}
