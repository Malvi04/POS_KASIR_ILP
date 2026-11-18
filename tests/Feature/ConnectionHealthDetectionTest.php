<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ConnectionHealthDetectionTest extends TestCase
{
    /**
     * Test /api/health returns HTTP 200 with correct JSON structure.
     */
    public function test_health_check_returns_ok_and_database_status(): void
    {
        $response = $this->getJson('/api/health');

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'ok',
                'database' => 'postgresql',
                'db_status' => 'connected',
            ]);

        $json = $response->json();
        $this->assertArrayHasKey('timestamp', $json);
        $this->assertNotEmpty($json['timestamp']);
    }

    /**
     * Test /api/health sends strict no-cache headers to prevent browser/SW caching.
     */
    public function test_health_check_sends_strict_no_cache_headers(): void
    {
        $response = $this->getJson('/api/health');

        $cacheControl = $response->headers->get('Cache-Control');
        $this->assertStringContainsString('no-store', $cacheControl);
        $this->assertStringContainsString('no-cache', $cacheControl);
        $this->assertStringContainsString('must-revalidate', $cacheControl);
        $this->assertEquals('no-cache', $response->headers->get('Pragma'));
        $this->assertEquals('0', $response->headers->get('Expires'));
    }

    /**
     * Test /api/health is publicly accessible without authentication.
     */
    public function test_health_check_is_publicly_accessible(): void
    {
        $response = $this->get('/api/health');
        $response->assertStatus(200);
    }
}
