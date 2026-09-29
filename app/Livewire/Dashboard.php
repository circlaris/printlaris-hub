<?php

namespace App\Livewire;

use App\Http\Middleware\EnsureAdmin;
use App\Services\HubCredentials;
use App\Services\PrintlarisApiClient;
use App\Services\StateStore;
use Illuminate\Support\Carbon;
use Livewire\Component;
use Throwable;

class Dashboard extends Component
{
    private const CONNECTED_WITHIN_SECONDS = 60;

    public bool $changingKey = false;

    public string $newKey = '';

    public ?string $checkMessage = null;

    public bool $checkPassed = false;

    public function checkConnection(PrintlarisApiClient $api): void
    {
        try {
            $customer = $api->ping()['customer'];

            $this->checkPassed = true;
            $this->checkMessage = "Key is valid. Connected as {$customer}.";
        } catch (Throwable $exception) {
            $this->checkPassed = false;
            $this->checkMessage = PrintlarisApiClient::describe($exception);
        }
    }

    public function changeKey(HubCredentials $credentials, PrintlarisApiClient $api): void
    {
        $this->newKey = trim($this->newKey);

        $this->validate(['newKey' => ['required', 'string', 'starts_with:prl_']], [
            'newKey.starts_with' => 'This does not look like a Printlaris key.',
        ]);

        try {
            $api->ping($this->newKey);
        } catch (Throwable $exception) {
            $this->addError('newKey', PrintlarisApiClient::describe($exception));

            return;
        }

        $credentials->storeKey($this->newKey);

        $this->reset('newKey', 'changingKey');
        $this->checkPassed = true;
        $this->checkMessage = 'The new key is valid and saved.';
    }

    public function logout(): mixed
    {
        session()->forget(EnsureAdmin::SESSION_KEY);
        session()->regenerate();

        return $this->redirectRoute('login');
    }

    public function render(HubCredentials $credentials, StateStore $state)
    {
        $connection = $state->read('connection');
        $lastContact = isset($connection['last_contact_at']) ? Carbon::parse($connection['last_contact_at']) : null;
        $printers = $state->read('printers');

        return view('livewire.dashboard', [
            'maskedKey' => $credentials->maskedKey(),
            'connected' => $lastContact !== null
                && $lastContact->gte(now()->subSeconds(self::CONNECTED_WITHIN_SECONDS))
                && ($connection['last_error'] ?? null) === null,
            'lastContact' => $lastContact,
            'lastError' => $connection['last_error'] ?? null,
            'lastConfigPull' => isset($connection['last_config_pull_at']) ? Carbon::parse($connection['last_config_pull_at']) : null,
            'lastPrinterPush' => isset($printers['pushed_at']) ? Carbon::parse($printers['pushed_at']) : null,
            'printers' => $printers['list'] ?? [],
            'config' => $state->read('config'),
            'jobs' => $state->recentJobs(),
        ]);
    }
}
