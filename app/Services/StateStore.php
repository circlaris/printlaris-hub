<?php

namespace App\Services;

use RuntimeException;

/**
 * File-backed key/value state shared by the web UI, the runner and the scheduler.
 */
class StateStore
{
    /** @return array<string, mixed> */
    public function read(string $name): array
    {
        $contents = @file_get_contents($this->path($name));

        if ($contents === false) {
            return [];
        }

        $decoded = json_decode($contents, true);

        return is_array($decoded) ? $decoded : [];
    }

    /** @param array<string, mixed> $data */
    public function write(string $name, array $data): void
    {
        $this->locked(fn () => $this->writeUnlocked($name, $data));
    }

    /** @param array<string, mixed> $patch */
    public function merge(string $name, array $patch): void
    {
        $this->locked(fn () => $this->writeUnlocked($name, [...$this->read($name), ...$patch]));
    }

    public function forget(string $name): void
    {
        $this->locked(fn () => @unlink($this->path($name)));
    }

    public function exists(string $name): bool
    {
        return is_file($this->path($name));
    }

    /**
     * @param  array<string, mixed>  $entry
     */
    public function appendJob(array $entry): void
    {
        $this->locked(function () use ($entry): void {
            $jobs = $this->read('jobs');
            array_unshift($jobs, $entry);

            $this->writeUnlocked('jobs', array_slice($jobs, 0, (int) config('printlaris.job_history_limit')));
        });
    }

    /** @return list<array<string, mixed>> */
    public function recentJobs(): array
    {
        return array_values($this->read('jobs'));
    }

    protected function path(string $name): string
    {
        return $this->directory().'/'.$name.'.json';
    }

    protected function directory(): string
    {
        $directory = (string) config('printlaris.state_path');

        if (! is_dir($directory) && ! @mkdir($directory, 0700, true) && ! is_dir($directory)) {
            throw new RuntimeException("The state directory [{$directory}] could not be created.");
        }

        return $directory;
    }

    /** @param array<string, mixed> $data */
    protected function writeUnlocked(string $name, array $data): void
    {
        $path = $this->path($name);
        $temporary = $path.'.'.getmypid().'.tmp';

        if (file_put_contents($temporary, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) === false) {
            throw new RuntimeException("The state file [{$name}] could not be written.");
        }

        chmod($temporary, 0600);
        rename($temporary, $path);
    }

    protected function locked(callable $callback): mixed
    {
        $lock = fopen($this->directory().'/.lock', 'c');

        try {
            flock($lock, LOCK_EX);

            return $callback();
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }
}
