<?php

namespace Tests;

use App\Services\HubCredentials;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;

abstract class TestCase extends BaseTestCase
{
    protected string $statePath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->statePath = sys_get_temp_dir().'/printlaris-test-'.uniqid();

        config([
            'printlaris.state_path' => $this->statePath,
            'printlaris.endpoint' => 'https://circlaris.test/printlaris/client',
        ]);

        Http::preventStrayRequests();
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->statePath);

        parent::tearDown();
    }

    protected function configureHub(string $key = 'prl_valid', string $password = 'secret-password'): void
    {
        $credentials = $this->app->make(HubCredentials::class);
        $credentials->storeKey($key);
        $credentials->storePassword($password);
    }
}
