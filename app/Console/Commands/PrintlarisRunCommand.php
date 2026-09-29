<?php

namespace App\Console\Commands;

use App\Services\HubCredentials;
use App\Services\PrintJobProcessor;
use App\Services\PrintlarisApiClient;
use App\Services\StateStore;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

#[Signature('printlaris:run {--once : Poll a single time, then exit.}')]
#[Description('Poll circlaris for print jobs and print them via CUPS.')]
class PrintlarisRunCommand extends Command
{
    private const IDLE_WITHOUT_KEY_SECONDS = 5;

    private const MAX_BACKOFF_SECONDS = 30;

    public function handle(
        HubCredentials $credentials,
        PrintlarisApiClient $api,
        PrintJobProcessor $processor,
        StateStore $state,
    ): int {
        $running = true;
        $failures = 0;

        if (function_exists('pcntl_signal')) {
            $this->trap([SIGTERM, SIGINT], function () use (&$running): void {
                $running = false;
            });
        }

        do {
            if (! $credentials->hasKey()) {
                if ($this->option('once')) {
                    $this->components->error('No connection key is configured.');

                    return self::FAILURE;
                }

                sleep(self::IDLE_WITHOUT_KEY_SECONDS);

                continue;
            }

            try {
                $job = $api->nextJob();
                $failures = 0;
            } catch (Throwable $exception) {
                Log::warning('Printlaris poll failed', ['reason' => PrintlarisApiClient::describe($exception)]);

                if ($this->option('once')) {
                    return self::FAILURE;
                }

                sleep(min(self::MAX_BACKOFF_SECONDS, 2 ** ++$failures));

                continue;
            }

            if ($job !== null) {
                $this->printJob($job, $api, $processor, $state);
            }

            if ($this->option('once')) {
                return self::SUCCESS;
            }

            sleep((int) config('printlaris.poll_interval'));
        } while ($running);

        return self::SUCCESS;
    }

    /**
     * @param  array{id: int, filename: string, queue: ?string, printer: ?string, copies: int}  $job
     */
    private function printJob(array $job, PrintlarisApiClient $api, PrintJobProcessor $processor, StateStore $state): void
    {
        $printer = $job['printer'];
        $error = null;

        try {
            $printer = $processor->process($job)['printer'];
        } catch (Throwable $exception) {
            $error = $exception->getMessage();
            Log::error('Printlaris job failed', ['job_id' => $job['id'], 'exception' => $exception]);
        }

        $status = $error === null ? 'printed' : 'failed';

        $state->appendJob([
            'id' => $job['id'],
            'filename' => $job['filename'],
            'printer' => $printer,
            'status' => $status,
            'error' => $error,
            'at' => now()->toIso8601String(),
        ]);

        // Retries because an unreported job is requeued by circlaris and would print twice.
        try {
            retry(3, fn () => $api->reportResult($job['id'], $status, $error), 1000);
        } catch (Throwable $exception) {
            Log::error('Printlaris job result could not be reported', ['job_id' => $job['id'], 'exception' => $exception]);
        }
    }
}
