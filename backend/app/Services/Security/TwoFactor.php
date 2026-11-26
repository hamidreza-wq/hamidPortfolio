<?php

namespace App\Services\Security;

use App\Models\User;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Time-based one-time passwords (RFC 6238: SHA-1, 6 digits, 30-second steps),
 * which every common authenticator app supports, plus single-use recovery codes.
 */
class TwoFactor
{
    private const ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    private const PERIOD = 30;

    private const RECOVERY_CODES = 8;

    public function generateSecret(): string
    {
        return $this->base32Encode(random_bytes(20));
    }

    /**
     * Returns the matched time step when the code is valid, otherwise null.
     * Accepts one step of clock drift either way, and never accepts a step at
     * or before $lastStep, so an observed code cannot be replayed.
     */
    public function verify(string $secret, string $code, ?int $lastStep = null, ?int $timestamp = null): ?int
    {
        $code = preg_replace('/\s+/', '', $code);
        if (! preg_match('/^\d{6}$/', $code)) {
            return null;
        }
        $current = intdiv($timestamp ?? now()->timestamp, self::PERIOD);
        $key = $this->base32Decode($secret);
        foreach ([$current - 1, $current, $current + 1] as $step) {
            if ($lastStep !== null && $step <= $lastStep) {
                continue;
            }
            if (hash_equals($this->codeAt($key, $step), $code)) {
                return $step;
            }
        }

        return null;
    }

    public function codeAt(string $binaryKey, int $step): string
    {
        $hash = hash_hmac('sha1', pack('J', $step), $binaryKey, true);
        $offset = ord($hash[19]) & 0x0F;
        $value = ((ord($hash[$offset]) & 0x7F) << 24)
            | (ord($hash[$offset + 1]) << 16)
            | (ord($hash[$offset + 2]) << 8)
            | ord($hash[$offset + 3]);

        return str_pad((string) ($value % 1_000_000), 6, '0', STR_PAD_LEFT);
    }

    public function otpauthUri(User $user, string $secret): string
    {
        $issuer = config('app.name', 'Portfolio');

        return 'otpauth://totp/'.rawurlencode($issuer.':'.$user->email)
            .'?secret='.$secret.'&issuer='.rawurlencode($issuer).'&algorithm=SHA1&digits=6&period='.self::PERIOD;
    }

    /** An inline SVG QR code. Generated on the server, so the secret never leaves this site. */
    public function qrSvg(string $uri): string
    {
        $svg = (new Writer(new ImageRenderer(new RendererStyle(200, 1), new SvgImageBackEnd)))->writeString($uri);

        return preg_replace('/^<\?xml[^>]*>\s*/', '', $svg);
    }

    /**
     * @return array{plain: list<string>, hashed: list<string>}
     */
    public function makeRecoveryCodes(): array
    {
        $plain = [];
        for ($i = 0; $i < self::RECOVERY_CODES; $i++) {
            $plain[] = Str::lower(Str::random(5).'-'.Str::random(5));
        }

        return ['plain' => $plain, 'hashed' => array_map(fn ($code) => Hash::make($code), $plain)];
    }

    /**
     * Consumes a recovery code. Returns the remaining hashes, or null when the
     * code does not match any stored code.
     *
     * @param  list<string>  $hashes
     * @return list<string>|null
     */
    public function useRecoveryCode(array $hashes, string $code): ?array
    {
        $code = Str::lower(trim($code));
        if ($code === '') {
            return null;
        }
        foreach ($hashes as $index => $hash) {
            if (Hash::check($code, $hash)) {
                unset($hashes[$index]);

                return array_values($hashes);
            }
        }

        return null;
    }

    private function base32Encode(string $bytes): string
    {
        $bits = '';
        foreach (str_split($bytes) as $byte) {
            $bits .= str_pad(decbin(ord($byte)), 8, '0', STR_PAD_LEFT);
        }
        $out = '';
        foreach (str_split($bits, 5) as $chunk) {
            $out .= self::ALPHABET[bindec(str_pad($chunk, 5, '0'))];
        }

        return $out;
    }

    private function base32Decode(string $text): string
    {
        $bits = '';
        foreach (str_split(strtoupper(rtrim($text, '='))) as $char) {
            $index = strpos(self::ALPHABET, $char);
            if ($index === false) {
                return '';
            }
            $bits .= str_pad(decbin($index), 5, '0', STR_PAD_LEFT);
        }
        $out = '';
        foreach (str_split($bits, 8) as $byte) {
            if (strlen($byte) === 8) {
                $out .= chr(bindec($byte));
            }
        }

        return $out;
    }
}
