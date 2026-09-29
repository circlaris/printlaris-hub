<?php

namespace App\Console\Commands;

use App\Services\CupsPrinterService;
use App\Services\HubCredentials;
use App\Services\PrintlarisApiClient;
use App\Services\StateStore;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Throwable;

#[Signature('printlaris:push-printers')]
#[Description('Push the local CUPS printers to circlaris.')]
class PushPrintersCommand extends Command
{
    public function handle(
        HubCredentials $credentials,
        CupsPrinterService $cups,
        PrintlarisApiClient $api,
        StateStore $state,
    ): int {
        if (! $credentials->hasKey()) {
            return self::SUCCESS;
        }

        $printers = $cups->listPrinters()->values()->all();

        try {
            $api->pushPrinters($printers);
        } catch (Throwable $exception) {
            $this->components->error(PrintlarisApiClient::describe($exception));

            return self::FAILURE;
        }

        $state->merge('printers', ['pushed_at' => now()->toIso8601String(), 'list' => $printers]);

        return self::SUCCESS;
    }
}
