<?php

use App\Http\Middleware\EnsureAdmin;
use App\Http\Middleware\EnsureHubIsNotSetUp;
use App\Http\Middleware\EnsureHubIsSetUp;
use App\Livewire\Dashboard;
use App\Livewire\Login;
use App\Livewire\Setup;
use Illuminate\Support\Facades\Route;

Route::livewire('setup', Setup::class)->middleware(EnsureHubIsNotSetUp::class)->name('setup');

Route::livewire('login', Login::class)->middleware(EnsureHubIsSetUp::class)->name('login');

Route::livewire('/', Dashboard::class)->middleware([EnsureHubIsSetUp::class, EnsureAdmin::class])->name('dashboard');
