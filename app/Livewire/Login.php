<?php

namespace App\Livewire;

use App\Http\Middleware\EnsureAdmin;
use App\Services\HubCredentials;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Component;

class Login extends Component
{
    private const MAX_ATTEMPTS = 5;

    public string $password = '';

    public function login(HubCredentials $credentials): mixed
    {
        $throttleKey = 'hub-login|'.request()->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, self::MAX_ATTEMPTS)) {
            $this->addError('password', 'Too many attempts. Try again in '.RateLimiter::availableIn($throttleKey).' seconds.');

            return null;
        }

        if (! $credentials->passwordMatches($this->password)) {
            RateLimiter::hit($throttleKey);
            $this->addError('password', 'The password is not correct.');

            return null;
        }

        RateLimiter::clear($throttleKey);

        session()->regenerate();
        session()->put(EnsureAdmin::SESSION_KEY, true);

        return $this->redirectRoute('dashboard');
    }

    public function render()
    {
        return view('livewire.login');
    }
}
