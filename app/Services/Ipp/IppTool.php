<?php

namespace App\Services\Ipp;

use App\Services\CupsPrinterService;
use RuntimeException;

/**
 * Thin wrapper around CUPS' `ipptool`, which is available on the Pi image and on macOS.
 */
class IppTool
{
    /**
     * @return array<string, string> The displayed attributes of the printer, keyed by attribute name.
     */
    public function attributes(string $uri): array
    {
        $output = $this->run(['ipptool', '-t', $uri, resource_path('ipptool/get-printer-attributes.test')], 10);

        preg_match_all('/^\s+([a-z-]+) \([^)]*\) = (.*)$/m', $output, $matches, PREG_SET_ORDER);

        $attributes = [];

        foreach ($matches as [, $name, $value]) {
            $attributes[$name] = trim(preg_replace('/\[[a-z-]+\]$/i', '', trim($value)));
        }

        if (! isset($attributes['printer-make-and-model']) && ! isset($attributes['printer-name'])) {
            throw new RuntimeException("Unexpected ipptool output for {$uri}.");
        }

        return $attributes;
    }

    /**
     * @return string|null The job id reported by the printer.
     */
    public function printPdf(string $uri, string $path, int $copies): ?string
    {
        $output = $this->run([
            'ipptool', '-c', '-f', $path, '-d', 'copies='.max(1, $copies), $uri, resource_path('ipptool/print-pdf.test'),
        ], 120);

        $lines = array_values(array_filter(explode("\n", trim($output))));

        return isset($lines[1]) && $lines[1] !== '' ? trim($lines[1]) : null;
    }

    /**
     * @param  list<string>  $command
     */
    protected function run(array $command, int $timeout): string
    {
        $process = CupsPrinterService::process($command);
        $process->setTimeout($timeout);
        $process->run();

        if (! $process->isSuccessful()) {
            throw new RuntimeException('ipptool failed: '.trim($process->getErrorOutput() ?: $process->getOutput()));
        }

        return $process->getOutput();
    }
}
