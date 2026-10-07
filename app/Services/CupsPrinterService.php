<?php

namespace App\Services;

use Illuminate\Support\Collection;
use Symfony\Component\Process\Process;

class CupsPrinterService
{
    /**
     * Web servers such as php-fpm start with a short PATH that lacks the sbin directories holding lpadmin and lpinfo.
     *
     * @param  list<string>  $command
     */
    public static function process(array $command): Process
    {
        return new Process($command, null, ['PATH' => '/usr/local/bin:/usr/bin:/bin:/usr/sbin:/sbin']);
    }

    /**
     * Detect the LAN IP via the default route; UDP "connect" sends no packets.
     */
    public function lanIpAddress(): ?string
    {
        $socket = @stream_socket_client('udp://1.1.1.1:53', $errorCode, $errorMessage, 1);

        if ($socket !== false) {
            $localAddress = stream_socket_get_name($socket, false);
            fclose($socket);

            if (is_string($localAddress)) {
                $ip = substr($localAddress, 0, strrpos($localAddress, ':') ?: null);

                if (filter_var($ip, FILTER_VALIDATE_IP) && $ip !== '0.0.0.0') {
                    return $ip;
                }
            }
        }

        $ip = gethostbyname(gethostname());

        return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : null;
    }

    /**
     * @return Collection<int, array{name:string,state:string,description:?string,location:?string}>
     */
    public function listPrinters(): Collection
    {
        $process = self::process(['lpstat', '-p', '-l']);
        $process->run();

        if (! $process->isSuccessful()) {
            return collect();
        }

        $lines = preg_split('/\r\n|\r|\n/', $process->getOutput()) ?: [];
        $printers = collect();
        $current = null;

        foreach ($lines as $line) {
            $trimmed = trim($line);

            if (preg_match('/^printer\s+(\S+)\s+is\s+([a-z]+)/i', $trimmed, $matches)) {
                if ($current !== null) {
                    $printers->push($current);
                }

                $current = [
                    'name' => $matches[1],
                    'state' => strtolower($matches[2]),
                    'description' => null,
                    'location' => null,
                ];

                continue;
            }

            if ($current === null) {
                continue;
            }

            if (preg_match('/^Description:\s*(.*)$/i', $trimmed, $matches)) {
                $current['description'] = trim($matches[1]);

                continue;
            }

            if (preg_match('/^Location:\s*(.*)$/i', $trimmed, $matches)) {
                $current['location'] = trim($matches[1]);
            }
        }

        if ($current !== null) {
            $printers->push($current);
        }

        return $printers->sortBy('name');
    }

    /**
     * Creates (or repoints) a queue for an IPP printer: driverless where possible, otherwise with a driver matching the model.
     */
    public function ensureQueue(string $queue, string $uri, string $model = ''): void
    {
        $existing = self::process(['lpstat', '-v', $queue]);
        $existing->run();

        if ($existing->isSuccessful() && str_contains($existing->getOutput(), $uri)) {
            return;
        }

        $process = $this->createQueue($queue, $uri, 'everywhere');

        if (! $process->isSuccessful() && $model !== '') {
            $driver = $this->driverFor($model);

            if ($driver !== null) {
                $process = $this->createQueue($queue, $uri, $driver);
            }
        }

        if (! $process->isSuccessful()) {
            throw new \RuntimeException(sprintf(
                'The CUPS queue %s could not be created. Output: %s',
                $queue,
                trim($process->getErrorOutput() ?: $process->getOutput()),
            ));
        }
    }

    protected function createQueue(string $queue, string $uri, string $driver): Process
    {
        $process = self::process(['lpadmin', '-p', $queue, '-E', '-v', $uri, '-m', $driver]);
        $process->setTimeout(60);
        $process->run();

        return $process;
    }

    protected function driverFor(string $model): ?string
    {
        $process = self::process(['lpinfo', '-l', '--make-and-model', $model, '-m']);
        $process->setTimeout(60);
        $process->run();

        preg_match_all('/^Model:\s+name = (.+)$/m', $process->getOutput(), $matches);

        foreach ($matches[1] as $driver) {
            if (trim($driver) !== 'everywhere') {
                return trim($driver);
            }
        }

        return null;
    }

    /**
     * @return string|null The CUPS request id, e.g. "zebra-12".
     */
    public function submit(string $filePath, string $queue, int $copies = 1): ?string
    {
        $process = self::process(['lp', '-d', $queue, '-n', (string) max(1, $copies), $filePath]);
        $process->setTimeout(60);
        $process->run();

        if (! $process->isSuccessful()) {
            $message = trim($process->getErrorOutput() ?: $process->getOutput());

            throw new \RuntimeException(sprintf(
                'The print job for %s could not be submitted to queue %s. Output: %s',
                $filePath,
                $queue,
                $message,
            ));
        }

        return preg_match('/request id is (\S+)/i', $process->getOutput(), $matches) === 1 ? $matches[1] : null;
    }
}
