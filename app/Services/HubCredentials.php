<?php

namespace App\Services;

/**
 * Holds the connection key and the admin password hash in the state directory.
 */
class HubCredentials
{
    public function __construct(protected StateStore $state) {}

    public function key(): ?string
    {
        $key = $this->state->read('credentials')['key'] ?? null;

        return is_string($key) && $key !== '' ? $key : null;
    }

    public function hasKey(): bool
    {
        return $this->key() !== null;
    }

    public function isConfigured(): bool
    {
        return $this->hasKey() && $this->passwordHash() !== null;
    }

    public function storeKey(string $key): void
    {
        $this->state->merge('credentials', ['key' => $key]);
    }

    public function storePassword(string $password): void
    {
        $this->state->merge('credentials', ['password_hash' => password_hash($password, PASSWORD_DEFAULT)]);
    }

    public function passwordMatches(string $password): bool
    {
        $hash = $this->passwordHash();

        return $hash !== null && password_verify($password, $hash);
    }

    public function maskedKey(): ?string
    {
        $key = $this->key();

        return $key === null ? null : substr($key, 0, 4).str_repeat('•', 8).substr($key, -4);
    }

    protected function passwordHash(): ?string
    {
        $hash = $this->state->read('credentials')['password_hash'] ?? null;

        return is_string($hash) && $hash !== '' ? $hash : null;
    }
}
