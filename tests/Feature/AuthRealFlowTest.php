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

    public function test_owner_login_success_with_password(): void
    {
        $response = $this->postJson('/api/auth/login', [
            'role' => 'owner',
            'identifier' => 'test_owner_flow',
            'password' => 'ownerpass123',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('user.username', 'test_owner_flow');
    }

    public function test_owner_login_fails_with_invalid_password(): void
    {
        $response = $this->postJson('/api/auth/login', [
            'role' => 'owner',
            'identifier' => 'test_owner_flow',
            'password' => 'wrongpass',
        ]);

        $response->assertStatus(401)
            ->assertJsonPath('success', false);
    }

    public function test_manager_login_session_allows_dashboard_access_without_bouncing_to_cashier(): void
    {
        $loginRes = $this->postJson('/api/auth/login', [
            'role' => 'manager',
            'identifier' => 'test_manager_flow',
            'password' => 'managerpass123',
        ]);

        $loginRes->assertStatus(200);
        $redirectUrl = $loginRes->json('redirect_url');
        $this->assertEquals(url('/dashboard'), $redirectUrl);

        // Next request with same session to /dashboard should succeed with 200, NOT redirect to cashier login
        $dashRes = $this->get('/dashboard');
        $dashRes->assertStatus(200);
    }

    public function test_cashier_login_session_allows_pos_and_blocks_dashboard(): void
    {
        $loginRes = $this->postJson('/api/auth/login', [
            'role' => 'kasir',
            'identifier' => 'test_kasir_flow',
            'password' => 'kasirpass123',
        ]);

        $loginRes->assertStatus(200);
        $redirectUrl = $loginRes->json('redirect_url');
        $this->assertEquals(url('/select-shift'), $redirectUrl);

        $shiftRes = $this->get('/select-shift');
        $shiftRes->assertStatus(200);

        $posRes = $this->get('/pos');
        $posRes->assertStatus(200);

        // Kasir attempting to access manager dashboard should be safely redirected to cashier POS
        $dashRes = $this->get('/dashboard');
        $dashRes->assertRedirect(route('cashier.pos'));
    }

    public function test_role_switching_from_cashier_to_manager(): void
    {
        // 1. Login as Kasir
        $this->postJson('/api/auth/login', [
            'role' => 'kasir',
            'identifier' => 'test_kasir_flow',
            'password' => 'kasirpass123',
        ])->assertStatus(200);

        $this->get('/pos')->assertStatus(200);

        // 2. Switch to Manager
        $mgrLogin = $this->postJson('/api/auth/login', [
            'role' => 'manager',
            'identifier' => 'test_manager_flow',
            'password' => 'managerpass123',
        ]);
        $mgrLogin->assertStatus(200);

        // 3. Manager dashboard now accessible
        $this->get('/dashboard')->assertStatus(200);
    }

    public function test_public_cashiers_api_roster(): void
    {
        $response = $this->getJson('/api/auth/cashiers');
        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['success', 'data']);

        $data = $response->json('data');
        $this->assertNotEmpty($data);
        // Ensure no passwords or pins leaked
        $this->assertArrayNotHasKey('password', $data[0]);
        $this->assertArrayNotHasKey('pin', $data[0]);
    }

    public function test_logout_clears_session_and_redirects(): void
    {
        $this->postJson('/api/auth/login', [
            'role' => 'manager',
            'identifier' => 'test_manager_flow',
            'password' => 'managerpass123',
        ])->assertStatus(200);

        $this->get('/dashboard')->assertStatus(200);

        // Logout
        $logoutRes = $this->get('/logout');
        $logoutRes->assertRedirect('/');

        // Now dashboard requires re-authentication, redirected to manager login
        $this->withHeaders(['X-Security-Test' => '1'])
            ->get('/dashboard')
            ->assertRedirect(route('manager.login'));
    }

    public function test_manager_settings_api_access_and_owner_route_redirect(): void
    {
        $this->postJson('/api/auth/login', [
            'role' => 'manager',
            'identifier' => 'test_manager_flow',
            'password' => 'managerpass123',
        ])->assertStatus(200);

        $this->get('/absensi')->assertStatus(200);
        $this->get('/manager/absensi')->assertStatus(200);

        $this->getJson('/api/settings')
            ->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->withHeaders(['X-Security-Test' => '1'])
            ->putJson('/api/settings/tax', [
                'tax_rate_percent' => 11,
                'tax_enabled' => true,
                'tax_calculation_method' => 'EXCLUDE',
            ])
            ->assertStatus(403);

        // Manager navigating to Owner-only web routes redirects cleanly to manager dashboard
        $this->get('/owner')->assertRedirect(route('dashboard'));
        $this->get('/cabang')->assertRedirect(route('dashboard'));
        $this->get('/audit-log')->assertRedirect(route('dashboard'));
    }

    public function test_owner_console_and_tax_settings_access(): void
    {
        $this->postJson('/api/auth/login', [
            'role' => 'owner',
            'identifier' => 'test_owner_flow',
            'password' => 'ownerpass123',
        ])->assertStatus(200);

        $this->get('/owner')->assertStatus(200);
        $this->get('/cabang')->assertStatus(200);
        $this->get('/owner/kelola-akun')->assertStatus(200);
        $this->getJson('/api/owner/dashboard-summary')->assertStatus(200);
        $this->getJson('/api/settings')->assertStatus(200);
    }

    public function test_service_worker_has_no_cache_headers(): void
    {
        $response = $this->get('/sw.js');
        $response->assertStatus(200);
        $this->assertStringContainsString('no-store', strtolower((string) $response->headers->get('Cache-Control')));
    }
}
