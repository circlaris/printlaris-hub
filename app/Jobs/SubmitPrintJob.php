<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;

class SubmitPrintJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public array $backoff = [5, 15, 30];

    public function __construct(
        public string $filePath,
        public string $queue,
    ) {
        //
    }

    public function handle(): void
    {
        $command = sprintf('lp -d %s %s', escapeshellarg($this->queue), escapeshellarg($this->filePath));
        $process = Process::fromShellCommandline($command);
        $process->setTimeout(60);
        $process->run();

        if (! $process->isSuccessful()) {
            $message = trim($process->getErrorOutput() ?: $process->getOutput());

            Log::error('Print job failed via lp', [
                'file_path' => $this->filePath,
                'queue' => $this->queue,
                'output' => $message,
            ]);

            throw new \RuntimeException(sprintf(
                'The print job for %s could not be submitted to queue %s. Output: %s',
                $this->filePath,
                $this->queue,
                $message,
            ));
        }
    }
}
