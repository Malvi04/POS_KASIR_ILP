<?php

namespace Tests;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase;

    public function createApplication()
    {
        putenv('APP_ENV=testing');
        $_ENV['APP_ENV'] = 'testing';
        $_SERVER['APP_ENV'] = 'testing';

        $app = parent::createApplication();
        $app['config']->set('database.connections.pgsql.database', 'pos_kasir_ilp_test');

        return $app;
    }

    protected function setUp(): void
    {
        parent::setUp();

        if (!file_exists(public_path('build/manifest.json'))) {
            $this->withoutVite();
        }
    }
}
