<?php

namespace App\Providers;

use App\Models\JobType;
use App\Observers\JobTypeObserver;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        JobType::observe(JobTypeObserver::class);
    }
}
