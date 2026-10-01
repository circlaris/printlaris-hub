<?php

namespace App\Services\Zebra;

use App\Services\StateStore;
use Illuminate\Support\Carbon;
use RuntimeException;
use Throwable;

/**
 * Label printers found on the LAN, identified by their certificate fingerprint so a changed DHCP address does not matter.
 *
 * @phpstan-type Printer array{name: string, host: string, port: int, model: string, firmware: string, fingerprint: string, online: bool, last_seen_at: string}
 */
class ZebraPrinters
{
    private const FORGET_AFTER_DAYS = 7;

    public function __construct(
        protected ZebraScanner $scanner,
        protected ZebraTlsClient $client,
        protected StateStore $state,
    ) {}

    /** @return array<string, Printer> */
    public function known(): array
    {
        return $this->state->read('zebra')['printers'] ?? [];
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
        $port = (int) config('printlaris.zebra.port');
        $now = now();
        $printers = [];

        foreach ($this->known() as $name => $printer) {
            if (Carbon::parse($printer['last_seen_at'])->lt($now->copy()->subDays(self::FORGET_AFTER_DAYS))) {
                continue;
            }

            $printers[$name] = [...$printer, 'online' => false];
        }

        foreach ($this->scanner->openHosts($this->scanner->candidateHosts(), $port) as $host) {
            try {
                $identity = $this->client->identify($host, $port);
            } catch (Throwable) {
                continue;
            }

            $name = 'zebra-'.substr($identity['fingerprint'], 0, 12);

            $printers[$name] = [
                'name' => $name,
                'host' => $host,
                'port' => $port,
                'model' => $identity['model'],
                'firmware' => $identity['firmware'],
                'fingerprint' => $identity['fingerprint'],
                'online' => true,
                'last_seen_at' => $now->toIso8601String(),
            ];
        }

        $this->state->write('zebra', ['scanned_at' => $now->toIso8601String(), 'printers' => $printers]);

        return $printers;
    }

    public function send(string $name, string $zpl): void
    {
        $printer = $this->find($name) ?? throw new RuntimeException("Unknown label printer {$name}.");

        try {
            $this->client->send($printer['host'], $printer['port'], $zpl, $printer['fingerprint']);
        } catch (Throwable $exception) {
            $moved = $this->discover()[$name] ?? null;

            if ($moved === null || ! $moved['online'] || $moved['host'] === $printer['host']) {
                throw $exception;
            }

            $this->client->send($moved['host'], $moved['port'], $zpl, $moved['fingerprint']);
        }
    }

    /**
     * @return list<array{name: string, state: string, description: string, location: null, type: string, address: string}>
     */
    public function report(): array
    {
        return array_values(array_map(fn (array $printer): array => [
            'name' => $printer['name'],
            'state' => $printer['online'] ? 'idle' : 'offline',
            'description' => 'Zebra '.$printer['model'],
            'location' => null,
            'type' => 'zpl-tls',
            'address' => $printer['host'].':'.$printer['port'],
        ], $this->known()));
    }
}
