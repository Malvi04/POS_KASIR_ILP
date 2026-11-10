<?php

namespace Tests\Feature;

use App\Models\CashierShift;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthRealFlowTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        User::updateOrCreate(['username' => 'test_kasir_flow'], [
            'name' => 'Kasir Flow Test',
            'email' => 'kasir_flow@test.com',
            'nik' => '20249999001',
            'role' => 'Kasir Operasional',
            'branch' => 'Pusat',
            'password' => Hash::make('kasirpass123'),
            'pin' => '123456',
            'is_active' => true,
        ]);

        User::updateOrCreate(['username' => 'test_manager_flow'], [
            'name' => 'Manager Flow Test',
            'email' => 'manager_flow@test.com',
            'nik' => '20249999002',
            'role' => 'Manager',
            'branch' => 'Pusat',
            'password' => Hash::make('managerpass123'),
            'pin' => '654321',
            'is_active' => true,
        ]);

        User::updateOrCreate(['username' => 'test_owner_flow'], [
            'name' => 'Owner Flow Test',
            'email' => 'owner_flow@test.com',
            'nik' => '20249999003',
            'role' => 'Owner',
            'branch' => 'Pusat',
            'password' => Hash::make('ownerpass123'),
            'pin' => '999888',
            'is_active' => true,
        ]);
    }

    public function test_cashier_login_success_with_valid_credentials(): void
    {
        $response = $this->postJson('/api/auth/login', [
            'role' => 'kasir',
            'identifier' => 'test_kasir_flow',
            'password' => 'kasirpass123',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('user.username', 'test_kasir_flow');
    }

    public function test_cashier_login_fails_with_invalid_password(): void
    {
        $response = $this->postJson('/api/auth/login', [
            'role' => 'kasir',
            'identifier' => 'test_kasir_flow',
            'password' => 'wrong_password',
        ]);

        $response->assertStatus(401)
            ->assertJsonPath('success', false);
    }

    public function test_manager_login_success_and_role_guard(): void
    {
        $response = $this->postJson('/api/auth/login', [
            'role' => 'manager',
            'identifier' => 'test_manager_flow',
            'password' => 'managerpass123',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('user.username', 'test_manager_flow');

        // Kasir trying to login as manager should be forbidden
        $forbiddenResponse = $this->postJson('/api/auth/login', [
            'role' => 'manager',
            'identifier' => 'test_kasir_flow',
            'password' => 'kasirpass123',
        ]);
        $forbiddenResponse->assertStatus(403);
    }

    public function test_owner_login_success_with_pin(): void
    {
        $response = $this->postJson('/api/auth/login', [
            'role' => 'owner',
            'pin' => '999888',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    public function test_owner_login_fails_with_invalid_pin(): void
    {
        $response = $this->postJson('/api/auth/login', [
            'role' => 'owner',
            'pin' => '000000',
        ]);

        $response->assertStatus(401)
            ->assertJsonPath('success', false);
    }
}
