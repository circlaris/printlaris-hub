<?php

namespace App\Services\Zebra;

use RuntimeException;

/**
 * Talks ZPL to a Link-OS printer over its TLS raw port (default 9143).
 */
class ZebraTlsClient
{
    private const TIMEOUT_SECONDS = 5;

    /**
     * @return array{fingerprint: string, model: string, firmware: string}
     */
    public function identify(string $host, int $port): array
    {
        [$stream, $fingerprint] = $this->open($host, $port);

        try {
            fwrite($stream, '~HI');
            $reply = $this->readReply($stream);
        } finally {
            fclose($stream);
        }

        $parts = explode(',', trim($reply, "\x02\x03\r\n "));

        if (count($parts) < 2 || $parts[0] === '') {
            throw new RuntimeException("{$host}:{$port} did not answer like a Zebra printer.");
        }

        return ['fingerprint' => $fingerprint, 'model' => $parts[0], 'firmware' => $parts[1]];
    }

    public function send(string $host, int $port, string $zpl, string $expectedFingerprint): void
    {
        [$stream, $fingerprint] = $this->open($host, $port);

        try {
            if (! hash_equals($expectedFingerprint, $fingerprint)) {
                throw new RuntimeException("The certificate of {$host}:{$port} changed; refusing to print.");
            }

            while ($zpl !== '') {
                $written = fwrite($stream, $zpl);

                if ($written === false || $written === 0) {
                    throw new RuntimeException("Writing to the printer at {$host}:{$port} failed.");
                }

                $zpl = substr($zpl, $written);
            }
        } finally {
            fclose($stream);
        }
    }

    /**
     * @return array{0: resource, 1: string} The stream and the SHA-256 fingerprint of the peer certificate.
     */
    protected function open(string $host, int $port): array
    {
        // The device certificate is self-signed, so identity is enforced through the pinned fingerprint instead.
        $context = stream_context_create(['ssl' => [
            'verify_peer' => false,
            'verify_peer_name' => false,
            'capture_peer_cert' => true,
        ]]);

        $stream = @stream_socket_client("tls://{$host}:{$port}", $errorCode, $error, self::TIMEOUT_SECONDS, STREAM_CLIENT_CONNECT, $context);

        if ($stream === false) {
            throw new RuntimeException("Could not connect to the printer at {$host}:{$port}: {$error}");
        }

        stream_set_timeout($stream, self::TIMEOUT_SECONDS);

        $certificate = stream_context_get_params($stream)['options']['ssl']['peer_certificate'] ?? null;
        $fingerprint = $certificate !== null ? openssl_x509_fingerprint($certificate, 'sha256') : false;

        if (! is_string($fingerprint)) {
            fclose($stream);

            throw new RuntimeException("The printer at {$host}:{$port} presented no certificate.");
        }

        return [$stream, $fingerprint];
    }

    /** @param resource $stream */
    protected function readReply($stream): string
    {
        $reply = '';

        while (! feof($stream) && ! str_contains($reply, "\x03")) {
            $chunk = fread($stream, 1024);

            if ($chunk === false || $chunk === '' || stream_get_meta_data($stream)['timed_out']) {
                break;
            }

            $reply .= $chunk;
        }

        return $reply;
    }
}
