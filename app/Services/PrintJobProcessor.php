<?php

namespace App\Services;

use RuntimeException;

class PrintJobProcessor
{
    public function __construct(
        protected PrintlarisApiClient $api,
        protected CupsPrinterService $cups,
        protected StateStore $state,
    ) {}

    /**
     * @param  array{id: int, filename: string, queue: ?string, printer: ?string, copies: int}  $job
     * @return array{printer: string, cups_job: ?string}
     */
    public function process(array $job): array
    {
        $printer = $this->resolvePrinter($job);
        $path = $this->temporaryPath($job['filename']);

        try {
            $this->api->downloadJobFile($job['id'], $path);

            return ['printer' => $printer, 'cups_job' => $this->cups->submit($path, $printer, $job['copies'])];
        } finally {
            if (is_file($path)) {
                unlink($path);
            }
        }
    }

    /**
     * The queue-to-printer mapping in the pulled config is not defined yet; a printer on the job always wins.
     *
     * @param  array{queue: ?string, printer: ?string}  $job
     */
    protected function resolvePrinter(array $job): string
    {
        $printer = $job['printer'];

        if ($printer === null && $job['queue'] !== null) {
            foreach ($this->state->read('config')['queues'] ?? [] as $queue) {
                if (is_array($queue) && ($queue['key'] ?? null) === $job['queue']) {
                    $printer = is_string($queue['printer'] ?? null) ? $queue['printer'] : null;
                }
            }
        }

        if ($printer === null) {
            throw new RuntimeException('No printer could be resolved for this job.');
        }

        // Rejects names that lp could read as options.
        if (preg_match('/^[A-Za-z0-9][A-Za-z0-9_.\-]*$/', $printer) !== 1) {
            throw new RuntimeException('The printer name is not a valid CUPS queue name.');
        }

        return $printer;
    }

    protected function temporaryPath(string $filename): string
    {
        $base = tempnam(sys_get_temp_dir(), 'printlaris-');

        if ($base === false) {
            throw new RuntimeException('A temporary file for the print job could not be created.');
        }

        $extension = pathinfo($filename, PATHINFO_EXTENSION);

        if (preg_match('/^[a-z0-9]{1,10}$/i', $extension) !== 1) {
            return $base;
        }

        // CUPS picks the file type filter from the extension.
        rename($base, $base.'.'.$extension);

        return $base.'.'.$extension;
    }
}
