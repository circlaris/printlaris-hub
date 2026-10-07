<?php

namespace App\Livewire;

use App\Http\Middleware\EnsureAdmin;
use App\Services\HubCredentials;
use App\Services\Ipp\IppPrinters;
use App\Services\PrintJobProcessor;
use App\Services\PrintlarisApiClient;
use App\Services\StateStore;
use App\Services\Zebra\ZebraPrinters;
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

    public ?string $scanMessage = null;

    public ?string $testMessage = null;

    public bool $testPassed = false;

    public function printTestPage(string $printer, PrintJobProcessor $processor): void
    {
        try {
            $processor->printTestPage($printer);

            $this->testPassed = true;
            $this->testMessage = "Test page sent to {$printer}.";
        } catch (Throwable $exception) {
            $this->testPassed = false;
            $this->testMessage = $exception->getMessage();
        }
    }

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

    public function scanPrinters(ZebraPrinters $zebras, IppPrinters $ipp): void
    {
        $online = fn (array $printer): bool => $printer['online'];
        $found = count(array_filter($zebras->discover(), $online)) + count(array_filter($ipp->discover(), $online));

        $this->scanMessage = "Scan finished: {$found} ".($found === 1 ? 'printer' : 'printers').' found.';
    }

    public function logout(): mixed
    {
        session()->forget(EnsureAdmin::SESSION_KEY);
        session()->regenerate();

        return $this->redirectRoute('login');
    }

    public function render(HubCredentials $credentials, StateStore $state, ZebraPrinters $zebras, IppPrinters $ipp)
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
            'printers' => [
                ...array_filter($printers['list'] ?? [], fn (array $printer): bool => ($printer['type'] ?? 'cups') === 'cups'),
                ...$zebras->report(),
                ...$ipp->report(),
            ],
            'config' => $state->read('config'),
            'jobs' => $state->recentJobs(),
        ]);
    }
}
