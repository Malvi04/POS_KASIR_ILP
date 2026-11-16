<?php

namespace Tests\Feature;

use App\Events\AttendanceRecordedEvent;
use App\Models\Attendance;
use App\Models\CashierShift;
use App\Models\User;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CashierAttendanceRealtimeTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Ensure test cashiers exist
        User::updateOrCreate(
            ['username' => 'budi_kasir01'],
            [
                'name' => 'Budi Kurniawan',
                'email' => 'budi_kasir01@ilp-pos.co.id',
                'nik' => '202409821002',
                'role' => 'kasir',
                'password' => Hash::make('budi123'),
                'pin' => '123456',
                'branch' => 'Pusat',
                'is_active' => true,
            ]
        );

        User::updateOrCreate(
            ['username' => 'citra_kasir'],
            [
                'name' => 'Citra Dewi',
                'email' => 'citra_kasir@ilp-pos.co.id',
                'nik' => '202409821003',
                'role' => 'kasir',
                'password' => Hash::make('citra123'),
                'pin' => '654321',
                'branch' => 'Cabang A',
                'is_active' => true,
            ]
        );

        CashierShift::whereIn('terminal_code', ['POS-01', 'POS-02'])->update(['status' => 'CLOSED']);
        Attendance::whereIn('terminal', ['POS-01', 'POS-02'])->delete();
    }

    public function test_cashier_login_via_nik_and_password_success(): void
    {
        $response = $this->postJson('/api/auth/login', [
            'role' => 'kasir',
            'identifier' => '202409821002',
            'password' => 'budi123',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'user' => [
                    'name' => 'Budi Kurniawan',
                    'username' => 'budi_kasir01',
                    'nik' => '202409821002',
                    'role' => 'kasir',
                ]
            ]);
    }

    public function test_cashier_login_with_invalid_credentials_fails(): void
    {
        $response = $this->postJson('/api/auth/login', [
            'role' => 'kasir',
            'identifier' => '202409821002',
            'password' => 'wrongpassword',
        ]);

        $response->assertStatus(401)
            ->assertJson([
                'success' => false,
            ]);
    }

    public function test_shift_open_automatically_creates_attendance_and_broadcasts_event(): void
    {
        Event::fake([AttendanceRecordedEvent::class]);

        $response = $this->postJson('/api/shift/open', [
            'terminal_code' => 'POS-01',
            'cashier_nik' => '202409821002',
            'cashier_name' => 'Budi Kurniawan',
            'opening_cash' => 200000,
            'branch' => 'Pusat',
            'pin' => '123456',
        ]);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
            ]);

        // Verify attendance record created in database
        $this->assertDatabaseHas('attendances', [
            'staff_name' => 'Budi Kurniawan',
            'terminal' => 'POS-01',
            'branch' => 'Pusat',
        ]);

        $attendance = Attendance::where('staff_name', 'Budi Kurniawan')->first();
        $this->assertNotNull($attendance);
        $this->assertNotNull($attendance->check_in);
        $this->assertTrue(empty($attendance->check_out) || $attendance->check_out === '-');

        // Verify event broadcasted
        Event::assertDispatched(AttendanceRecordedEvent::class, function ($event) {
            return $event->type === 'MASUK'
                && $event->attendance['staffName'] === 'Budi Kurniawan'
                && $event->attendance['terminal'] === 'POS-01';
        });
    }

    public function test_shift_close_updates_attendance_checkout_and_broadcasts(): void
    {
        Event::fake([AttendanceRecordedEvent::class]);

        // Open shift first
        $openResponse = $this->postJson('/api/shift/open', [
            'terminal_code' => 'POS-02',
            'cashier_nik' => '202409821003',
            'cashier_name' => 'Citra Dewi',
            'opening_cash' => 150000,
            'branch' => 'Cabang A',
            'pin' => '654321',
        ]);
        $openResponse->assertStatus(201);
        $shiftCode = $openResponse->json('data.shift_code');

        // Close shift
        $closeResponse = $this->postJson('/api/shift/close', [
            'shift_code' => $shiftCode,
            'actual_cash' => 150000,
            'closing_notes' => 'Shift selesai normal',
        ]);

        $closeResponse->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);

        // Verify attendance checkout timestamp set
        $attendance = Attendance::where('staff_name', 'Citra Dewi')->first();
        $this->assertNotNull($attendance);
        $this->assertNotNull($attendance->check_out);
        $this->assertNotEquals('-', $attendance->check_out);

        // Verify checkout broadcast
        Event::assertDispatched(AttendanceRecordedEvent::class, function ($event) {
            return $event->type === 'PULANG'
                && $event->attendance['staffName'] === 'Citra Dewi';
        });
    }

    public function test_manager_attendance_api_returns_terminal_field(): void
    {
        $user = User::where('username', 'budi_kasir01')->first();

        // Create an attendance record
        Attendance::create([
            'staff_name' => 'Andi Pratama',
            'user_id' => $user->id,
            'terminal' => 'POS-06',
            'branch' => 'Cabang A',
            'date' => now()->toDateString(),
            'check_in' => '08:00',
            'check_out' => null,
            'status' => 'HADIR',
        ]);

        $response = $this->getJson('/api/attendance');

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertNotEmpty($data);

        $record = collect($data)->firstWhere('staffName', 'Andi Pratama');
        $this->assertNotNull($record);
        $this->assertEquals('POS-06', $record['terminal']);
        $this->assertEquals('Cabang A', $record['branch']);
    }
}
