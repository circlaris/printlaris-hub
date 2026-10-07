<?php

namespace App\Services\Ipp;

use App\Services\CupsPrinterService;
use App\Services\StateStore;
use App\Services\Zebra\ZebraScanner;
use Illuminate\Support\Carbon;
use RuntimeException;
use Throwable;

/**
 * Office printers found on the LAN via IPP, identified by their printer UUID so a changed DHCP address does not matter.
 *
 * @phpstan-type Printer array{name: string, host: string, port: int, uri: string, model: string, uuid: string, pdf: bool, state: string, online: bool, last_seen_at: string}
 */
class IppPrinters
{
    private const FORGET_AFTER_DAYS = 7;

    public function __construct(
        protected ZebraScanner $scanner,
        protected IppTool $tool,
        protected CupsPrinterService $cups,
        protected StateStore $state,
    ) {}

    /** @return array<string, Printer> */
    public function known(): array
    {
        return $this->state->read('ipp')['printers'] ?? [];
    }

    /** @return Printer|null */
    public function find(string $name): ?array
    {
        return $this->known()[$name] ?? null;
    }

    /**
     * @return array<string, Printer>
     */
    public function discover(): array
    {
        $port = (int) config('printlaris.ipp.port');
        $now = now();
        $printers = [];

        foreach ($this->known() as $name => $printer) {
            if (Carbon::parse($printer['last_seen_at'])->lt($now->copy()->subDays(self::FORGET_AFTER_DAYS))) {
                continue;
            }

            $printers[$name] = [...$printer, 'online' => false];
        }

        foreach ($this->scanner->openHosts($this->scanner->candidateHosts(), $port) as $host) {
            $attributes = null;

            foreach (config('printlaris.ipp.paths') as $path) {
                $uri = "ipp://{$host}:{$port}{$path}";

                try {
                    $attributes = $this->tool->attributes($uri);

                    break;
                } catch (Throwable) {
                }
            }

            if ($attributes === null) {
                continue;
            }

            $uuid = strtolower(str_replace('urn:uuid:', '', $attributes['printer-uuid'] ?? '')) ?: $host;
            $name = 'ipp-'.substr(preg_replace('/[^a-z0-9]/', '', $uuid) ?: sha1($host), 0, 12);

            $printers[$name] = [
                'name' => $name,
                'host' => $host,
                'port' => $port,
                'uri' => $uri,
                'model' => $attributes['printer-make-and-model'] ?: ($attributes['printer-name'] ?? 'Printer'),
                'uuid' => $uuid,
                'pdf' => str_contains($attributes['document-format-supported'] ?? '', 'application/pdf'),
                'state' => $attributes['printer-state'] ?: 'idle',
                'online' => true,
                'last_seen_at' => $now->toIso8601String(),
            ];
        }

        $this->state->write('ipp', ['scanned_at' => $now->toIso8601String(), 'printers' => $printers]);

        return $printers;
    }

    /**
     * @return string|null The job id; printers without native PDF support go through a CUPS queue that converts the file.
     */
    public function print(string $name, string $path, int $copies): ?string
    {
        $printer = $this->find($name) ?? throw new RuntimeException("Unknown printer {$name}.");

        if ($printer['pdf']) {
            return $this->tool->printPdf($printer['uri'], $path, $copies);
        }

        $this->cups->ensureQueue($name, $printer['uri'], $printer['model']);

        return $this->cups->submit($path, $name, $copies);
    }

    /**
     * @return list<array{name: string, state: string, description: string, location: null, type: string, address: string}>
     */
    public function report(): array
    {
        return array_values(array_map(fn (array $printer): array => [
            'name' => $printer['name'],
            'state' => $printer['online'] ? $printer['state'] : 'offline',
            'description' => $printer['model'],
            'location' => null,
            'type' => 'ipp',
            'address' => $printer['host'].':'.$printer['port'],
        ], $this->known()));
    }
}
