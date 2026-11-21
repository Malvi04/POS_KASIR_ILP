<?php

use App\Models\User;
use App\Models\Location;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    Location::firstOrCreate(
        ['code' => 'CAB-001'],
        ['name' => 'Toko Pusat', 'type' => 'STORE', 'is_active' => true]
    );
    Location::firstOrCreate(
        ['code' => 'CAB-002'],
        ['name' => 'Cabang A', 'type' => 'STORE', 'is_active' => true]
    );
    Location::firstOrCreate(
        ['code' => 'CAB-003'],
        ['name' => 'Cabang B', 'type' => 'STORE', 'is_active' => true]
    );
});

test('manager can update cashier branch assignment and persist to database', function () {
    $manager = User::firstOrCreate(
        ['username' => 'test_manager_assign'],
        [
            'name' => 'Test Manager Assign',
            'email' => 'test_manager_assign@ilp.com',
            'role' => 'Manager',
            'branch' => 'Pusat',
            'password' => bcrypt('password123'),
            'is_active' => true,
        ]
    );

    $cashier = User::firstOrCreate(
        ['username' => 'test_kasir_assign'],
        [
            'name' => 'Kasir Penugasan',
            'email' => 'kasir_assign@ilp.com',
            'role' => 'Kasir',
            'branch' => 'Pusat',
            'password' => bcrypt('password123'),
            'is_active' => true,
        ]
    );

    // Manager mengubah penugasan kasir ke Cabang A dan Cabang B
    $response = $this->actingAs($manager)->putJson("/api/users/{$cashier->id}/branch-assignment", [
        'assigned_branches' => [
            ['branch' => 'Cabang A', 'status' => 'AKTIF', 'assignedAt' => '2026-11-21'],
            ['branch' => 'Cabang B', 'status' => 'AKTIF', 'assignedAt' => '2026-11-21'],
        ]
    ]);

    $response->assertStatus(200)
             ->assertJsonPath('success', true)
             ->assertJsonPath('user.branch', 'Cabang A, Cabang B');

    // Verifikasi langsung ke database PostgreSQL
    $cashier->refresh();
    expect($cashier->branch)->toBe('Cabang A, Cabang B');
    expect($cashier->assigned_branches)->toHaveCount(2);
    expect($cashier->getAssignedBranchesList())->toHaveCount(2);

    // Verifikasi user_location_access table
    $locAccess = DB::table('user_location_access')->where('user_id', $cashier->id)->get();
    expect($locAccess->count())->toBeGreaterThanOrEqual(1);

    // Kasir login / cek profil /api/auth/me
    $meResponse = $this->actingAs($cashier)->getJson('/api/auth/me');
    $meResponse->assertStatus(200)
               ->assertJsonPath('authenticated', true)
               ->assertJsonPath('user.branch', 'Cabang A, Cabang B')
               ->assertJsonPath('user.assigned_branches.0.branch', 'Cabang A')
               ->assertJsonPath('user.assigned_branches.1.branch', 'Cabang B');

    // Cek daftar kasir di /api/auth/cashiers
    $cashiersResponse = $this->getJson('/api/auth/cashiers');
    $cashiersResponse->assertStatus(200);
    $found = collect($cashiersResponse->json('data'))->firstWhere('id', $cashier->id);
    expect($found)->not->toBeNull();
    expect($found['branch'])->toBe('Cabang A, Cabang B');
    expect($found['assigned_branches'])->toHaveCount(2);
});

test('manager can update branch using general user update PUT endpoint', function () {
    $manager = User::firstOrCreate(
        ['username' => 'test_manager_put'],
        [
            'name' => 'Test Manager PUT',
            'email' => 'test_manager_put@ilp.com',
            'role' => 'Manager',
            'branch' => 'Pusat',
            'password' => bcrypt('password123'),
            'is_active' => true,
        ]
    );

    $cashier = User::firstOrCreate(
        ['username' => 'test_kasir_put'],
        [
            'name' => 'Kasir PUT',
            'email' => 'kasir_put@ilp.com',
            'role' => 'Kasir',
            'branch' => 'Pusat',
            'password' => bcrypt('password123'),
            'is_active' => true,
        ]
    );

    $response = $this->actingAs($manager)->putJson("/api/users/{$cashier->id}", [
        'name' => $cashier->name,
        'username' => $cashier->username,
        'email' => $cashier->email,
        'role' => $cashier->role,
        'branch' => 'Cabang B',
        'is_active' => true,
    ]);

    $response->assertStatus(200)
             ->assertJsonPath('success', true);

    $cashier->refresh();
    expect($cashier->branch)->toBe('Cabang B');
    expect($cashier->getActiveAssignedBranches())->toContain('Cabang B');

    // Cek /api/auth/me untuk kasir ini
    $meResponse = $this->actingAs($cashier)->getJson('/api/auth/me');
    $meResponse->assertStatus(200)
               ->assertJsonPath('user.branch', 'Cabang B');
});
