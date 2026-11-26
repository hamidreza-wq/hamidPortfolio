<?php

namespace App\Services\Health;

use Illuminate\Support\Carbon;
use RuntimeException;

/** Opens a verified TLS connection to the site and reads its certificate expiry. */
class CertificateInspector
{
    public function expiry(string $host, string $ip): Carbon
    {
        $context = stream_context_create(['ssl' => [
            'capture_peer_cert' => true, 'verify_peer' => true, 'verify_peer_name' => true,
            'peer_name' => $host, 'SNI_enabled' => true,
        ]]);
        $socket = @stream_socket_client("ssl://{$ip}:443", $errno, $error, 8, STREAM_CLIENT_CONNECT, $context);
        if (! $socket) {
            throw new RuntimeException('The certificate for '.$host.' could not be verified'.($error ? ': '.$error : '.'));
        }
        $certificate = stream_context_get_params($socket)['options']['ssl']['peer_certificate'] ?? null;
        fclose($socket);
        $parsed = $certificate ? openssl_x509_parse($certificate) : false;
        if (! $parsed || empty($parsed['validTo_time_t'])) {
            throw new RuntimeException('The certificate was accepted but its expiry date could not be read.');
        }

        return Carbon::createFromTimestamp($parsed['validTo_time_t']);
    }
}
