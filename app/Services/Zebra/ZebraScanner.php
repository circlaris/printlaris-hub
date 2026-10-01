<?php

namespace App\Services\Zebra;

use InvalidArgumentException;
use Symfony\Component\Process\Process;

class ZebraScanner
{
    private const BATCH_SIZE = 200;

    private const MIN_PREFIX = 22;

    /**
     * @return list<string>
     */
    public function candidateHosts(): array
    {
        $cidrs = config('printlaris.zebra.subnets') ?: $this->localCidrs();

        return array_values(array_unique(array_merge(...array_map($this->hostsIn(...), $cidrs))));
    }

    /**
     * @param  list<string>  $hosts
     * @return list<string> The hosts that accept a TCP connection on the port.
     */
    public function openHosts(array $hosts, int $port, float $timeout = 1.0): array
    {
        $open = [];

        foreach (array_chunk($hosts, self::BATCH_SIZE) as $batch) {
            $pending = [];

            foreach ($batch as $host) {
                $socket = @stream_socket_client("tcp://{$host}:{$port}", $errorCode, $error, 0, STREAM_CLIENT_CONNECT | STREAM_CLIENT_ASYNC_CONNECT);

                if ($socket !== false) {
                    $pending[(int) $socket] = [$host, $socket];
                }
            }

            $deadline = microtime(true) + $timeout;

            while ($pending !== [] && microtime(true) < $deadline) {
                $write = array_map(fn (array $entry) => $entry[1], $pending);
                $read = $except = null;

                if (stream_select($read, $write, $except, 0, 100_000) === false) {
                    break;
                }

                foreach ($write as $socket) {
                    [$host] = $pending[(int) $socket];

                    // A refused connection also becomes writable but has no peer address.
                    if (@stream_socket_get_name($socket, true) !== false) {
                        $open[] = $host;
                    }

                    fclose($socket);
                    unset($pending[(int) $socket]);
                }
            }

            foreach ($pending as [, $socket]) {
                fclose($socket);
            }
        }

        return $open;
    }

    /**
     * @return list<string>
     */
    public function hostsIn(string $cidr): array
    {
        if (preg_match('/^(\d{1,3}(?:\.\d{1,3}){3})\/(\d{1,2})$/', $cidr, $matches) !== 1
            || ($address = ip2long($matches[1])) === false
            || ($prefix = (int) $matches[2]) < self::MIN_PREFIX
            || $prefix > 32) {
            throw new InvalidArgumentException("Unsupported subnet [{$cidr}]; use a CIDR of /".self::MIN_PREFIX.' or smaller.');
        }

        if ($prefix >= 31) {
            return [long2ip($address)];
        }

        $mask = (0xFFFFFFFF << (32 - $prefix)) & 0xFFFFFFFF;
        $network = $address & $mask;
        $broadcast = $network | (~$mask & 0xFFFFFFFF);

        return array_map(long2ip(...), range($network + 1, $broadcast - 1));
    }

    /**
     * @return list<string> The /24 (or smaller) subnets of the Pi's own interfaces.
     */
    protected function localCidrs(): array
    {
        $process = new Process(['ip', '-4', '-o', 'addr', 'show', 'scope', 'global']);
        $process->run();

        preg_match_all('/inet (\d+\.\d+\.\d+\.\d+)\/(\d+)/', $process->getOutput(), $matches, PREG_SET_ORDER);

        return array_values(array_unique(array_map(
            fn (array $match): string => $match[1].'/'.max(24, (int) $match[2]),
            $matches,
        )));
    }
}
