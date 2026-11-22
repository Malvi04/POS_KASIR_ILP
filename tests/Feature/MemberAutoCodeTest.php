<?php

namespace Tests\Feature;

use App\Http\Controllers\Api\Manager\MemberManagementController;
use App\Models\Member;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MemberAutoCodeTest extends TestCase
{
    public function test_calculate_next_id_and_code_increments_properly()
    {
        $info = MemberManagementController::calculateNextIdAndCode();
        $this->assertIsArray($info);
        $this->assertArrayHasKey('next_id', $info);
        $this->assertArrayHasKey('next_code', $info);
        $this->assertGreaterThan(0, $info['next_id']);
        $this->assertNotEmpty($info['next_code']);
    }

    public function test_next_code_endpoint_returns_json()
    {
        $manager = User::where('role', 'manager')->first() ?? User::factory()->create(['role' => 'manager']);

        $response = $this->actingAs($manager)
            ->withSession(['pos_auth_uid_manager' => $manager->id])
            ->getJson('/api/members/next-code', ['X-POS-Role' => 'manager']);

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'data' => [
                'next_id',
                'next_code',
                'latest_id',
                'latest_code',
            ]
        ]);
        $this->assertTrue($response->json('success'));
    }

    public function test_store_member_auto_assigns_id_and_code_when_omitted()
    {
        $manager = User::where('role', 'manager')->first() ?? User::factory()->create(['role' => 'manager']);

        $nextInfo = MemberManagementController::calculateNextIdAndCode();
        $uniqueNik = 'TESTNIK_' . time() . rand(10, 99);

        $response = $this->actingAs($manager)
            ->withSession(['pos_auth_uid_manager' => $manager->id])
            ->postJson('/api/members', [
                'nik' => $uniqueNik,
                'name' => 'Karyawan Uji Otomatis',
                'department' => 'Keuangan',
                'division' => 'Akuntansi',
                'fax' => 'I/99',
                'credit_limit' => 2500000,
                'status' => 'AKTIF',
            ], ['X-POS-Role' => 'manager']);

        $response->assertStatus(201);
        $data = $response->json('data');

        $this->assertEquals((string) $nextInfo['next_id'], (string) $data['customer_id']);
        $this->assertEquals($nextInfo['next_code'], $data['member_code']);
        $this->assertEquals('Karyawan Uji Otomatis', $data['name']);
        $this->assertEquals('Keuangan', $data['department']);
        $this->assertEquals('Akuntansi', $data['division']);
        $this->assertEquals('I/99', $data['fax']);
        $this->assertEquals(2500000, (float) $data['credit_limit']);

        // Clean up test member
        Member::where('nik', $uniqueNik)->delete();
    }
}
