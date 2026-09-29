<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

class PrintlarisApiClient
{
    protected int $lastContactWrite = 0;

    protected bool $failing = false;

    public function __construct(
        protected HubCredentials $credentials,
        protected StateStore $state,
    ) {}

    /**
     * Checks a key without touching the stored connection state; defaults to the stored key.
     *
     * @return array{ok: bool, customer: string, server_time: string}
     */
    public function ping(?string $key = null): array
    {
        $ping = function () use ($key): array {
            $response = $this->request($key)->get('ping')->throw();

            if ($response->json('ok') !== true) {
                throw new RuntimeException('Circlaris returned an unexpected ping response.');
            }

            return $response->json();
        };

        return $key === null ? $this->tracked($ping) : $ping();
    }

    /**
     * @param  list<array{name: string, state: ?string, description: ?string, location: ?string}>  $printers
     */
    public function pushPrinters(array $printers): void
    {
        $this->tracked(fn () => $this->request()->post('printers', ['printers' => $printers])->throw());
    }

    /**
     * @return array<string, mixed>
     */
    public function fetchConfig(): array
    {
        $config = $this->tracked(fn () => $this->request()->get('config')->throw()->json());

        if (! is_array($config) || ! is_string($config['version'] ?? null)) {
            throw new RuntimeException('Circlaris returned an invalid configuration.');
        }

        return $config;
    }

    /**
     * @return array{id: int, filename: string, queue: ?string, printer: ?string, copies: int}|null
     */
    public function nextJob(): ?array
    {
        $response = $this->tracked(fn () => $this->request()->get('jobs/next')->throw());

        if ($response->status() === 204) {
            return null;
        }

        $job = $response->json('job');

        if (! is_array($job) || ! is_int($job['id'] ?? null) || ! is_string($job['filename'] ?? null)) {
            throw new RuntimeException('Circlaris returned an invalid print job.');
        }

        return [
            'id' => $job['id'],
            'filename' => $job['filename'],
            'queue' => is_string($job['queue'] ?? null) ? $job['queue'] : null,
            'printer' => is_string($job['printer'] ?? null) ? $job['printer'] : null,
            'copies' => max(1, (int) ($job['copies'] ?? 1)),
        ];
    }

    public function downloadJobFile(int $jobId, string $path): void
    {
        $body = $this->tracked(fn () => $this->request()->timeout(120)->get("jobs/{$jobId}/file")->throw()->body());

        if (file_put_contents($path, $body, LOCK_EX) === false) {
            throw new RuntimeException('The print job file could not be written to disk.');
        }
    }

    public function reportResult(int $jobId, string $status, ?string $error = null): void
    {
        $this->tracked(fn () => $this->request()->post("jobs/{$jobId}/result", array_filter([
            'status' => $status,
            'error' => $error !== null ? mb_substr($error, 0, 2000) : null,
        ]))->throw());
    }

    /**
     * A human-readable reason for the UI, without leaking response bodies.
     */
    public static function describe(Throwable $exception): string
    {
        if ($exception instanceof RequestException) {
            return match ($exception->response->status()) {
                401 => 'The key is not valid (anymore).',
                403 => 'The Printlaris module is not enabled for this customer.',
                429 => 'Too many requests, circlaris is throttling this hub.',
                default => 'Circlaris answered with HTTP '.$exception->response->status().'.',
            };
        }

        if ($exception instanceof ConnectionException) {
            return 'Circlaris could not be reached.';
        }

        return $exception->getMessage();
    }

    protected function request(?string $key = null): PendingRequest
    {
        $key ??= $this->credentials->key();

        if ($key === null) {
            throw new RuntimeException('No connection key is configured.');
        }

        return Http::acceptJson()
            ->baseUrl($this->endpoint().'/')
            ->withToken($key)
            ->withOptions(['allow_redirects' => false])
            ->connectTimeout(5)
            ->timeout(20);
    }

    protected function endpoint(): string
    {
        $endpoint = (string) config('printlaris.endpoint');

        if (filter_var($endpoint, FILTER_VALIDATE_URL) === false || parse_url($endpoint, PHP_URL_SCHEME) !== 'https') {
            throw new RuntimeException('PRINTLARIS_ENDPOINT must be a valid HTTPS URL.');
        }

        return rtrim($endpoint, '/');
    }

    /**
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    protected function tracked(callable $callback): mixed
    {
        try {
            $result = $callback();
        } catch (Throwable $exception) {
            $this->recordFailure($exception);

            throw $exception;
        }

        $this->recordSuccess();

        return $result;
    }

    protected function recordSuccess(): void
    {
        $now = time();

        if (! $this->failing && $now - $this->lastContactWrite < (int) config('printlaris.state_write_interval')) {
            return;
        }

        $this->lastContactWrite = $now;
        $this->failing = false;

        $this->state->merge('connection', [
            'last_contact_at' => now()->toIso8601String(),
            'last_error' => null,
            'last_error_at' => null,
        ]);
    }

    protected function recordFailure(Throwable $exception): void
    {
        $message = self::describe($exception);
        $this->failing = true;
        $connection = $this->state->read('connection');

        if (($connection['last_error'] ?? null) === $message) {
            return;
        }

        $this->state->merge('connection', ['last_error' => $message, 'last_error_at' => now()->toIso8601String()]);
    }
}
