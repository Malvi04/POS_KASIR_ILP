<?php

namespace Tests\Feature;

use App\Models\CashierShift;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CashierShiftCloseEdgeCasesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Buat Akun Kasir Utama
        $this->kasir = User::create([
            'name' => 'Kasir 1',
            'email' => 'kasir1@example.com',
            'username' => 'cashier_dody',
            'nik' => 'KSR-001',
            'role' => 'Kasir',
            'branch' => 'Pusat',
            'pin' => '123456',
            'password' => bcrypt('password123'),
            'is_active' => true,
        ]);

        // Buat Akun Kasir Lain
        $this->otherKasir = User::create([
            'name' => 'Kasir 2',
            'email' => 'kasir2@example.com',
            'username' => 'cashier_budi',
            'nik' => 'KSR-002',
            'role' => 'Kasir',
            'branch' => 'Pusat',
            'pin' => '654321',
            'password' => bcrypt('password123'),
            'is_active' => true,
        ]);
    }

    public function test_close_shift_with_zero_cash_and_notes_succeeds(): void
    {
        $shift = CashierShift::create([
            'shift_code' => 'CS-20261127-POS01-001',
            'terminal_code' => 'POS-01',
            'user_id' => $this->kasir->id,
            'cashier_name' => $this->kasir->name,
            'cashier_nik' => $this->kasir->nik,
            'branch' => 'Pusat',
            'opening_cash' => 0,
            'expected_cash' => 0,
            'status' => 'OPEN',
            'opened_at' => now(),
        ]);

        $response = $this->actingAs($this->kasir)->postJson('/api/shift/close', [
            'shift_code' => $shift->shift_code,
            'terminal_code' => 'POS-01',
            'actual_cash' => 0,
            'closing_notes' => 'semangat',
            'handover_type' => 'close_only',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'shift_code' => $shift->shift_code,
                    'status' => 'CLOSED',
                    'actual_cash' => 0,
                ],
            ]);

        $this->assertDatabaseHas('cashier_shifts', [
            'id' => $shift->id,
            'status' => 'CLOSED',
            'actual_cash' => 0,
        ]);
    }

    public function test_closing_when_no_active_shift_exists_is_idempotent_and_returns_success(): void
    {
        // Pastikan tidak ada shift berstatus OPEN
        CashierShift::where('status', 'OPEN')->update(['status' => 'CLOSED']);

        $response = $this->actingAs($this->kasir)->postJson('/api/shift/close', [
            'shift_code' => 'CS-NONEXISTENT',
            'terminal_code' => 'POS-01',
            'actual_cash' => 0,
            'closing_notes' => 'tutup bersih',
            'handover_type' => 'close_only',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'shift_code' => 'CS-NONEXISTENT',
                    'status' => 'CLOSED',
                ],
            ]);
    }

    public function test_case_insensitive_cashier_username_and_nik_ownership(): void
    {
        $shift = CashierShift::create([
            'shift_code' => 'CS-20261127-POS01-CASE',
            'terminal_code' => 'POS-01',
            'user_id' => null, // Skenario offline di mana user_id null namun cashier_nik uppercase
            'cashier_name' => 'Kasir 1',
            'cashier_nik' => 'KSR-001',
            'branch' => 'Pusat',
            'opening_cash' => 50000,
            'expected_cash' => 50000,
            'status' => 'OPEN',
            'opened_at' => now(),
        ]);

        // Login kasir dengan nik lowercase 'ksr-001'
        $response = $this->actingAs($this->kasir)->postJson('/api/shift/close', [
            'shift_code' => $shift->shift_code,
            'terminal_code' => 'POS-01',
            'actual_cash' => 50000,
            'closing_notes' => 'Tutup aman',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'status' => 'CLOSED',
                ],
            ]);
    }

    public function test_other_cashier_cannot_close_different_cashier_shift(): void
    {
        $shift = CashierShift::create([
            'shift_code' => 'CS-20261127-POS01-OTHER',
            'terminal_code' => 'POS-01',
            'user_id' => $this->kasir->id,
            'cashier_name' => $this->kasir->name,
            'cashier_nik' => $this->kasir->nik,
            'branch' => 'Pusat',
            'opening_cash' => 50000,
            'expected_cash' => 50000,
            'status' => 'OPEN',
            'opened_at' => now(),
        ]);

        // Login kasir 2 dan coba tutup shift milik kasir 1
        $response = $this->actingAs($this->otherKasir)->postJson('/api/shift/close', [
            'shift_code' => $shift->shift_code,
            'terminal_code' => 'POS-01',
            'actual_cash' => 50000,
        ]);

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
            ]);
    }
}
