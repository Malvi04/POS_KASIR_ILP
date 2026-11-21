<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CashierPasswordAndPinSettingsTest extends TestCase
{
    protected User $cashier;

    protected function setUp(): void
    {
        parent::setUp();

        $this->cashier = User::firstOrCreate(
            ['username' => 'kasir_setting_test'],
            [
                'name' => 'Kasir Setting Test',
                'email' => 'kasir_setting_test@example.com',
                'password' => bcrypt('password123'),
                'role' => 'KASIR',
                'branch' => 'Cabang Pusat',
                'pin' => '123456',
                'is_active' => true,
            ]
        );

        // Ensure clean state
        $this->cashier->update([
            'password' => bcrypt('password123'),
            'pin' => '123456',
            'is_active' => true,
        ]);
    }

    public function test_auth_me_returns_has_pin_status(): void
    {
        $response = $this->actingAs($this->cashier)
            ->getJson('/api/auth/me');

        $response->assertStatus(200)
            ->assertJson([
                'authenticated' => true,
                'user' => [
                    'username' => 'kasir_setting_test',
                    'has_pin' => true,
                ],
            ]);
    }

    public function test_cashier_successfully_changes_password(): void
    {
        $response = $this->actingAs($this->cashier)
            ->putJson('/api/auth/password', [
                'current_password' => 'password123',
                'password' => 'newPassword888',
                'password_confirmation' => 'newPassword888',
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);

        $this->cashier->refresh();
        $this->assertTrue(Hash::check('newPassword888', $this->cashier->password));

        // Test login with new password succeeds
        $loginResp = $this->postJson('/api/auth/login', [
            'identifier' => 'kasir_setting_test',
            'password' => 'newPassword888',
            'role' => 'kasir',
        ]);
        $loginResp->assertStatus(200)->assertJson(['success' => true]);
    }

    public function test_cashier_fails_to_change_password_with_wrong_current_password(): void
    {
        $response = $this->actingAs($this->cashier)
            ->putJson('/api/auth/password', [
                'current_password' => 'wrongOldPassword',
                'password' => 'newPassword999',
                'password_confirmation' => 'newPassword999',
            ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
            ]);

        $this->cashier->refresh();
        $this->assertFalse(Hash::check('newPassword999', $this->cashier->password));
    }

    public function test_cashier_successfully_changes_pin(): void
    {
        $response = $this->actingAs($this->cashier)
            ->putJson('/api/auth/pin', [
                'current_pin' => '123456',
                'pin' => '654321',
                'pin_confirmation' => '654321',
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'has_pin' => true,
            ]);

        $this->cashier->refresh();
        $this->assertEquals('654321', $this->cashier->pin);

        // Test cashier login with new PIN succeeds
        $loginResp = $this->postJson('/api/auth/login', [
            'identifier' => 'kasir_setting_test',
            'password' => '654321',
            'role' => 'kasir',
        ]);
        $loginResp->assertStatus(200)->assertJson(['success' => true]);
    }

    public function test_cashier_can_change_pin_using_current_password_as_verification(): void
    {
        $response = $this->actingAs($this->cashier)
            ->putJson('/api/auth/pin', [
                'current_password' => 'password123',
                'pin' => '789123',
                'pin_confirmation' => '789123',
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'has_pin' => true,
            ]);

        $this->cashier->refresh();
        $this->assertEquals('789123', $this->cashier->pin);
    }

    public function test_cashier_fails_to_change_pin_with_wrong_current_pin(): void
    {
        $response = $this->actingAs($this->cashier)
            ->putJson('/api/auth/pin', [
                'current_pin' => '999999',
                'pin' => '888888',
                'pin_confirmation' => '888888',
            ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
            ]);

        $this->cashier->refresh();
        $this->assertEquals('123456', $this->cashier->pin);
    }

    public function test_cashier_validates_pin_format_and_confirmation(): void
    {
        // PIN must be 6 digits (5 digits should fail)
        $respShort = $this->actingAs($this->cashier)
            ->putJson('/api/auth/pin', [
                'current_pin' => '123456',
                'pin' => '12345',
                'pin_confirmation' => '12345',
            ]);
        $respShort->assertStatus(422);

        // PIN must be numeric (letters should fail)
        $respAlpha = $this->actingAs($this->cashier)
            ->putJson('/api/auth/pin', [
                'current_pin' => '123456',
                'pin' => 'ABC123',
                'pin_confirmation' => 'ABC123',
            ]);
        $respAlpha->assertStatus(422);

        // Confirmation mismatch should fail
        $respMismatch = $this->actingAs($this->cashier)
            ->putJson('/api/auth/pin', [
                'current_pin' => '123456',
                'pin' => '777888',
                'pin_confirmation' => '777999',
            ]);
        $respMismatch->assertStatus(422);
    }
}
