<?php

namespace App\Services;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;

class ContactSubmissionGuard
{
    public function issueToken(): string
    {
        return Crypt::encryptString((string) time());
    }

    public function isValid(string $token): bool
    {
        try {
            $timestamp = Crypt::decryptString($token);
        } catch (DecryptException) {
            return false;
        }

        if (! ctype_digit($timestamp)) {
            return false;
        }

        $elapsed = time() - (int) $timestamp;

        return $elapsed >= config('portfolio.contact_min_seconds')
            && $elapsed <= config('portfolio.contact_max_seconds');
    }
}
