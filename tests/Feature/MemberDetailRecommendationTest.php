<?php

namespace Tests\Feature;

use App\Models\Member;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MemberDetailRecommendationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
    }

    public function test_manager_can_access_member_detail_page(): void
    {
        $manager = User::factory()->create([
            'role' => 'MANAGER',
            'is_active' => true,
        ]);

        $response = $this->actingAs($manager)->get('/member-detail');
        $response->assertStatus(200);
        $response->assertSee('Detail &amp; Riwayat Anggota', false);
        $response->assertSee('Rekomendasi Anggota');
        $response->assertSee('Rekomendasi NIK');
    }

    public function test_api_members_search_returns_recommendations_by_name_and_nik(): void
    {
        $manager = User::factory()->create([
            'role' => 'MANAGER',
            'is_active' => true,
        ]);

        $member1 = Member::create([
            'member_code' => '01-0001',
            'nik' => '199701002',
            'name' => 'Ir. H. Mudjiono',
            'department' => 'Central Personnel',
            'branch' => 'Cabang Pusat',
            'credit_limit' => 1000000,
            'current_debt' => 0,
            'status' => 'AKTIF',
            'is_active' => true,
        ]);

        $member2 = Member::create([
            'member_code' => '01-0002',
            'nik' => '199501093',
            'name' => 'Suparlan, SE',
            'department' => 'Accounting',
            'branch' => 'Cabang Pusat',
            'credit_limit' => 1000000,
            'current_debt' => 0,
            'status' => 'AKTIF',
            'is_active' => true,
        ]);

        // Search by name
        $resName = $this->actingAs($manager)->getJson('/api/members?search=Mudjiono');
        $resName->assertStatus(200);
        $resName->assertJsonPath('success', true);
        $resName->assertJsonCount(1, 'data');
        $this->assertEquals('Ir. H. Mudjiono', $resName->json('data.0.name'));
        $this->assertEquals('199701002', $resName->json('data.0.nik'));

        // Search by NIK
        $resNik = $this->actingAs($manager)->getJson('/api/members?search=199501093');
        $resNik->assertStatus(200);
        $resNik->assertJsonPath('success', true);
        $resNik->assertJsonCount(1, 'data');
        $this->assertEquals('Suparlan, SE', $resNik->json('data.0.name'));

        // Search non-existent
        $resInvalid = $this->actingAs($manager)->getJson('/api/members?search=BukanAnggotaX999');
        $resInvalid->assertStatus(200);
        $resInvalid->assertJsonCount(0, 'data');
    }
}
