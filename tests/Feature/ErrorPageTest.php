<?php

namespace Tests\Feature;

use Tests\TestCase;

class ErrorPageTest extends TestCase
{
    public function test_non_existent_page_renders_404_error_page_with_it_note(): void
    {
        $response = $this->get('/halaman-acak-yang-tidak-pernah-ada-12345');

        $response->assertStatus(404);
        $response->assertSee('Silahkan Hubungi Team IT', false);
        $response->assertSee('it-support@ilp.co.id', false);
        $response->assertSee('PT Indo Lampung Perkasa', false);
    }

    public function test_dedicated_error_route_renders_500(): void
    {
        $response = $this->get('/error');

        $response->assertStatus(500);
        $response->assertSee('Silahkan Hubungi Team IT', false);
        $response->assertSee('Terjadi Kesalahan pada Sistem', false);
    }

    public function test_dedicated_error_route_with_custom_code(): void
    {
        $response = $this->get('/error?code=403');
        $response->assertStatus(403);
        $response->assertSee('Akses Ditolak', false);
        $response->assertSee('Silahkan Hubungi Team IT', false);

        $response = $this->get('/error?code=419');
        $response->assertStatus(419);
        $response->assertSee('Sesi Halaman Berakhir', false);
        $response->assertSee('Silahkan Hubungi Team IT', false);

        $response = $this->get('/error?code=503');
        $response->assertStatus(503);
        $response->assertSee('Layanan Sedang Dalam Pemeliharaan', false);
        $response->assertSee('Silahkan Hubungi Team IT', false);
    }

    public function test_custom_message_parameter_is_displayed(): void
    {
        $response = $this->get('/error?code=500&message=Koneksi+database+terputus');

        $response->assertStatus(500);
        $response->assertSee('Koneksi database terputus', false);
        $response->assertSee('Silahkan Hubungi Team IT', false);
    }

    public function test_unhandled_exception_renders_500_error_page_when_debug_is_false(): void
    {
        config(['app.debug' => false]);

        \Illuminate\Support\Facades\Route::get('/test-server-error-crash', function () {
            throw new \RuntimeException('Koneksi database internal terputus');
        });

        $response = $this->get('/test-server-error-crash');

        $response->assertStatus(500);
        $response->assertSee('Terjadi Kesalahan pada Sistem', false);
        $response->assertSee('Silahkan Hubungi Team IT', false);
        $response->assertSee('it-support@ilp.co.id', false);
    }
}
