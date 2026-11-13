<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class WebRoutesSmokeTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Ensure test user exists and authenticate as manager / cashier
        $user = User::firstOrCreate(
            ['username' => 'test_admin'],
            [
                'nik' => '202409829999',
                'name' => 'Test Admin',
                'email' => 'admin@test.com',
                'role' => 'Manager',
                'pin' => '123456',
                'branch' => 'Pusat',
                'password' => Hash::make('password'),
            ]
        );
        $this->actingAs($user);
    }

    public function test_all_web_routes_render_successfully(): void
    {
        $routes = [
            '/',
            '/login-kasir',
            '/login-manager',
            '/manager/login',
            '/owner',
            '/owner/dashboard',
            '/owner/cabang',
            '/owner/products',
            '/owner/inventory',
            '/owner/members',
            '/owner/umkm-partners',
            '/owner/reports',
            '/owner/kelola-akun',
            '/owner/users',
            '/owner/audit-log',
            '/owner/activity-log',
            '/owner/settings',
            '/owner/profile',
            '/manager/dashboard',
            '/manager/inventory',
            '/manager/kirim-barang',
            '/manager/stock-opname',
            '/manager/retur',
            '/manager/retur-masuk',
            '/manager/members',
            '/manager/member-detail',
            '/manager/umkm-partners',
            '/manager/promosi',
            '/manager/absensi',
            '/manager/reports',
            '/manager/kelola-akun',
            '/manager/settings',
            '/dashboard',
            '/inventory',
            '/cabang',
            '/members',
            '/member-detail',
            '/umkm-partners',
            '/promosi',
            '/absensi',
            '/reports',
            '/kelola-akun',
            '/audit-log',
            '/settings',
            '/stock-opname',
            '/retur',
            '/retur-masuk',
            '/kirim-barang',
            '/pos',
            '/kasir/pos',
            '/kasir/sesi',
            '/cashier-session',
            '/kasir/produk/masuk',
            '/cashier-goods-in',
            '/kasir/inventory',
            '/cashier-inventory',
            '/kasir/retur',
            '/cashier-retur',
            '/kasir/retur/masuk',
            '/cashier-return-in',
            '/kasir/transaksi/riwayat',
            '/kasir/transactions',
            '/cashier-transactions',
            '/kasir/transaksi/ditahan',
            '/cashier-held-bills',
            '/kasir/transaksi/refund',
            '/cashier-refund',
            '/kasir/members',
            '/cashier-members',
            '/kasir/reports',
            '/cashier-reports',
            '/kasir/laporan/kasir',
            '/cashier-report-by-cashier',
            '/kasir/laporan/sesi',
            '/cashier-report-sessions',
            '/kasir/laporan/operasional',
            '/cashier-report-operational',
            '/kupon',
            '/kasir/kupon',
            '/kasir/stock-opname',
            '/cashier-stock-opname',
            '/kasir/settings',
            '/cashier-settings',
            '/select-shift',
            '/kasir/select-shift',
            '/proses-kredit',
            '/kasir/proses-kredit',
            '/cetak-struk',
            '/kasir/cetak-struk',
        ];

        foreach ($routes as $route) {
            $response = $this->get($route);
            $status = $response->status();
            $this->assertTrue(
                in_array($status, [200, 302]),
                "Route [{$route}] returned unexpected status {$status}."
            );
        }
    }

    public function test_shared_routes_default_to_manager_view_unless_owner_specified(): void
    {
        // When manager visits /umkm-partners, it must serve the manager view
        $response = $this->get('/umkm-partners');
        $response->assertStatus(200);
        $response->assertViewIs('manager.umkm-partners');

        // When accessed with role=owner, it must serve owner view
        $responseOwner = $this->get('/umkm-partners?role=owner');
        $responseOwner->assertStatus(200);
        $responseOwner->assertViewIs('owner.umkm-partners');

        // Members
        $this->get('/members')->assertOk()->assertViewIs('manager.members');
        $this->get('/members?role=owner')->assertOk()->assertViewIs('owner.members');

        // Reports
        $this->get('/reports')->assertOk()->assertViewIs('manager.reports');
        $this->get('/reports?role=owner')->assertOk()->assertViewIs('owner.reports');

        // Kelola Akun
        $this->get('/kelola-akun')->assertOk()->assertViewIs('manager.kelola-akun');
        $this->get('/kelola-akun?role=owner')->assertOk()->assertViewIs('owner.kelola-akun');

        // Settings
        $this->get('/settings')->assertOk()->assertViewIs('manager.settings');
        $this->get('/settings?role=owner')->assertOk()->assertViewIs('owner.settings');
    }
}
