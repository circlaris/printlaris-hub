<?php

namespace App\Console\Commands;

use App\Services\HubCredentials;
use App\Services\PrintlarisApiClient;
use App\Services\StateStore;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Throwable;

#[Signature('printlaris:pull-config')]
#[Description('Pull the print configuration from circlaris.')]
class PullConfigCommand extends Command
{
    public function handle(HubCredentials $credentials, PrintlarisApiClient $api, StateStore $state): int
    {
        if (! $credentials->hasKey()) {
            return self::SUCCESS;
        }

        try {
            $config = $api->fetchConfig();
        } catch (Throwable $exception) {
            $this->components->error(PrintlarisApiClient::describe($exception));

            return self::FAILURE;
        }

        $state->write('config', $config);
        $state->merge('connection', ['last_config_pull_at' => now()->toIso8601String()]);

        return self::SUCCESS;
    }
}
