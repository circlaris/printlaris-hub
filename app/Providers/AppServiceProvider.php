<?php

namespace App\Providers;

use App\Http\Middleware\EnsureAdmin;
use App\Http\Middleware\EnsureHubIsNotSetUp;
use App\Http\Middleware\EnsureHubIsSetUp;
use App\Services\PrintlarisApiClient;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // One instance per process so the runner can throttle its state writes.
        $this->app->singleton(PrintlarisApiClient::class);
    }

    public function boot(): void
    {
        // Livewire update requests must pass the same gates as the page they belong to.
        Livewire::addPersistentMiddleware([EnsureHubIsSetUp::class, EnsureHubIsNotSetUp::class, EnsureAdmin::class]);
    }
}
