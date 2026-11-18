<?php

namespace Tests\Feature;

use App\Models\Member;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class DuplicateSubmitProtectionTest extends TestCase
{
    use DatabaseTransactions;

    protected User $manager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->manager = User::firstOrCreate(
            ['username' => 'test_manager_dup_guard'],
            [
                'nik' => '8877665544',
                'name' => 'Test Manager Guard',
                'email' => 'mgr.guard@test.local',
                'role' => 'Manager',
                'pin' => '123456',
                'branch' => 'Pusat',
                'password' => Hash::make('password'),
                'is_active' => true,
            ]
        );
        $this->actingAs($this->manager);
    }

    public function test_supplier_create_with_same_idempotency_key_does_not_duplicate(): void
    {
        $uniqueName = 'Tukang Oreo Test ' . uniqid();
        $idemKey = 'sup-test-' . uniqid();

        $first = $this->postJson('/api/purchases/suppliers', [
            'name' => $uniqueName,
            'owner_name' => 'Pak Budi',
            'phone' => '08123456789',
        ], ['X-Idempotency-Key' => $idemKey]);

        $first->assertStatus(201)
            ->assertJsonPath('success', true);
        $firstId = $first->json('data.id');

        $second = $this->postJson('/api/purchases/suppliers', [
            'name' => $uniqueName,
            'owner_name' => 'Pak Budi',
            'phone' => '08123456789',
        ], ['X-Idempotency-Key' => $idemKey]);

        $second->assertStatus(201)
            ->assertHeader('X-Idempotent-Replay', 'true')
            ->assertJsonPath('data.id', $firstId);

        $this->assertEquals(1, Supplier::where('name', $uniqueName)->count());
    }

    public function test_supplier_create_rejects_duplicate_name_even_without_idempotency_key(): void
    {
        $uniqueName = 'Supplier Unik ' . uniqid();

        $first = $this->postJson('/api/purchases/suppliers', [
            'name' => $uniqueName,
        ]);
        $first->assertStatus(201);

        // Simulate rapid second request with different/no idempotency key and whitespace/case variation
        $second = $this->postJson('/api/purchases/suppliers', [
            'name' => '  ' . strtoupper($uniqueName) . '  ',
        ]);
        $second->assertStatus(422)
            ->assertJsonPath('success', false);

        $this->assertEquals(1, Supplier::whereRaw('LOWER(TRIM(name)) = ?', [mb_strtolower($uniqueName)])->count());
    }

    public function test_consignment_partner_rejects_duplicate_and_supports_idempotency(): void
    {
        $uniqueName = 'Mitra UMKM Unik ' . uniqid();
        $idemKey = 'umkm-test-' . uniqid();

        $payload = [
            'name' => $uniqueName,
            'commission_percent' => 15,
            'settlement_period' => 'BULANAN',
        ];

        $first = $this->postJson('/api/consignment/partners', $payload, ['X-Idempotency-Key' => $idemKey]);
        $first->assertStatus(201);
        $firstId = $first->json('data.id');

        $replay = $this->postJson('/api/consignment/partners', $payload, ['X-Idempotency-Key' => $idemKey]);
        $replay->assertStatus(201)
            ->assertHeader('X-Idempotent-Replay', 'true')
            ->assertJsonPath('data.id', $firstId);

        $duplicate = $this->postJson('/api/consignment/partners', $payload);
        $duplicate->assertStatus(422);

        $this->assertEquals(1, Supplier::where('name', $uniqueName)->count());
    }

    public function test_idempotency_middleware_does_not_cache_validation_errors(): void
    {
        $idemKey = 'mbr-retry-' . uniqid();
        $uniqueNik = 'NIK' . rand(10000000, 99999999);

        // First attempt fails validation (missing name)
        $failed = $this->postJson('/api/members', [
            'nik' => $uniqueNik,
            'name' => '',
            'department' => 'Logistik',
            'branch' => 'Pusat',
            'status' => 'AKTIF',
        ], ['X-Idempotency-Key' => $idemKey]);
        $failed->assertStatus(422);

        // Retry with same key after fixing input should succeed
        $ok = $this->postJson('/api/members', [
            'nik' => $uniqueNik,
            'name' => 'Anggota Valid',
            'department' => 'Logistik',
            'branch' => 'Pusat',
            'credit_limit' => 1000000,
            'status' => 'AKTIF',
        ], ['X-Idempotency-Key' => $idemKey]);
        $ok->assertStatus(201);
        $this->assertEquals(1, Member::where('nik', $uniqueNik)->count());

        // Third replay with same key should replay 201 and not fail or duplicate
        $replay = $this->postJson('/api/members', [
            'nik' => $uniqueNik,
            'name' => 'Anggota Valid',
            'department' => 'Logistik',
            'branch' => 'Pusat',
            'credit_limit' => 1000000,
            'status' => 'AKTIF',
        ], ['X-Idempotency-Key' => $idemKey]);
        $replay->assertStatus(201)
            ->assertHeader('X-Idempotent-Replay', 'true');
        $this->assertEquals(1, Member::where('nik', $uniqueNik)->count());
    }

    public function test_pembelian_barang_view_includes_supplier_double_submit_guard(): void
    {
        $response = $this->get('/manager/pembelian-barang');
        $response->assertStatus(200);
        $response->assertSee('isSavingSupplier', false);
        $response->assertSee('X-Idempotency-Key', false);
        $response->assertSee(':disabled="isSavingSupplier"', false);
    }
}
