<?php

namespace App\Livewire;

use App\Http\Middleware\EnsureAdmin;
use App\Services\HubCredentials;
use App\Services\PrintlarisApiClient;
use Livewire\Component;
use Throwable;

class Setup extends Component
{
    public string $key = '';

    public string $password = '';

    public string $password_confirmation = '';

    public function save(HubCredentials $credentials, PrintlarisApiClient $api): mixed
    {
        $this->key = trim($this->key);

        $this->validate([
            'key' => ['required', 'string', 'starts_with:prl_'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ], [
            'key.starts_with' => 'This does not look like a Printlaris key.',
        ]);

        try {
            $api->ping($this->key);
        } catch (Throwable $exception) {
            $this->addError('key', PrintlarisApiClient::describe($exception));

            return null;
        }

        $credentials->storeKey($this->key);
        $credentials->storePassword($this->password);

        session()->regenerate();
        session()->put(EnsureAdmin::SESSION_KEY, true);

        return $this->redirectRoute('dashboard');
    }

    public function render()
    {
        return view('livewire.setup');
    }
}
