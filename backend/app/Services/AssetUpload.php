<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;

class AssetUpload
{
    public function font(UploadedFile $file): array
    {
        $bytes = $this->bytes($file, 2 * 1024 * 1024);
        $extension = strtolower($file->getClientOriginalExtension());
        $signature = substr($bytes, 0, 4);
        $valid = match ($extension) {
            'woff' => $signature === 'wOFF' && strlen($bytes) >= 44 && $this->uint32($bytes, 8) === strlen($bytes)
                && $this->uint16($bytes, 12) > 0 && strlen($bytes) >= 44 + 20 * $this->uint16($bytes, 12),
            'woff2' => $signature === 'wOF2' && strlen($bytes) >= 48 && $this->uint32($bytes, 8) === strlen($bytes)
                && $this->uint16($bytes, 12) > 0 && $this->uint32($bytes, 20) > 0,
            'ttf' => $signature === "\x00\x01\x00\x00" && $this->validSfnt($bytes),
            'otf' => $signature === 'OTTO' && $this->validSfnt($bytes),
            default => false,
        };
        if (! $valid) {
            $this->invalid('Upload a valid WOFF2, WOFF, TTF, or OTF font with a matching extension.');
        }
        if (in_array($extension, ['woff', 'woff2'], true)) {
            $flavor = substr($bytes, 4, 4);
            if (! in_array($flavor, ["\x00\x01\x00\x00", 'OTTO'], true)) {
                $this->invalid('The font container has an unsupported font signature.');
            }
            if ($this->uint16($bytes, 14) !== 0 || $this->uint32($bytes, 16) < 12
                || $this->uint32($bytes, 16) > 16 * 1024 * 1024) {
                $this->invalid('The font container has invalid or excessive decompressed dimensions.');
            }
            if ($extension === 'woff2' && $this->uint32($bytes, 20) > strlen($bytes) - 48) {
                $this->invalid('The font compressed stream exceeds its container.');
            }
            if ($extension === 'woff') {
                for ($index = 0; $index < $this->uint16($bytes, 12); $index++) {
                    $start = 44 + $index * 20;
                    $offset = $this->uint32($bytes, $start + 4);
                    $compressed = $this->uint32($bytes, $start + 8);
                    $original = $this->uint32($bytes, $start + 12);
                    if ($offset < 44 + 20 * $this->uint16($bytes, 12) || $compressed < 1
                        || $compressed > $original || $offset + $compressed > strlen($bytes)) {
                        $this->invalid('The font table exceeds its container.');
                    }
                }
            }
        }

        return ['bytes' => $bytes, 'extension' => $extension, 'size_bytes' => strlen($bytes)];
    }

    public function image(UploadedFile $file): array
    {
        $bytes = $this->bytes($file, 5 * 1024 * 1024);
        $dimensions = @getimagesizefromstring($bytes);
        $extension = strtolower($file->getClientOriginalExtension());
        $mime = $dimensions['mime'] ?? '';
        $signature = match ($mime) {
            'image/jpeg' => str_starts_with($bytes, "\xff\xd8\xff") && in_array($extension, ['jpg', 'jpeg'], true),
            'image/png' => str_starts_with($bytes, "\x89PNG\r\n\x1a\n") && $extension === 'png',
            'image/webp' => str_starts_with($bytes, 'RIFF') && substr($bytes, 8, 4) === 'WEBP' && $extension === 'webp',
            default => false,
        };
        if (! $signature || ! $dimensions || $dimensions[0] < 1 || $dimensions[1] < 1
            || $dimensions[0] > 8192 || $dimensions[1] > 8192 || $dimensions[0] * $dimensions[1] > 20000000) {
            $this->invalid('Use a valid JPG, PNG, or WebP: at most 8192 pixels per side and 20 million pixels.');
        }
        if (! function_exists('imagecreatefromstring')) {
            $this->invalid('Image uploads require the PHP GD extension for safe decoding and re-encoding. Enable ext-gd on the server.');
        }
        $image = @imagecreatefromstring($bytes);
        if ($image === false) {
            $this->invalid('The image could not be decoded.');
        }
        ob_start();
        try {
            $success = match ($mime) {
                'image/jpeg' => function_exists('imagejpeg') && imagejpeg($image, null, 90),
                'image/png' => function_exists('imagepng') && imagesavealpha($image, true) && imagepng($image, null, 6),
                'image/webp' => function_exists('imagewebp') && imagewebp($image, null, 90),
            };
            $encoded = ob_get_contents();
        } finally {
            ob_end_clean();
            unset($image);
        }
        if (! $success || ! $encoded) {
            $this->invalid('The image could not be re-encoded.');
        }
        $bytes = $encoded;

        return ['bytes' => $bytes, 'extension' => $mime === 'image/jpeg' ? 'jpg' : $extension,
            'mime_type' => $mime, 'width' => $dimensions[0], 'height' => $dimensions[1],
            'size_bytes' => strlen($bytes), 'reencoded' => true];
    }

    private function bytes(UploadedFile $file, int $limit): string
    {
        if (! $file->isValid() || $file->getSize() < 12 || $file->getSize() > $limit) {
            $this->invalid('The upload is empty, failed, or exceeds the size limit.');
        }
        $bytes = file_get_contents($file->getRealPath());
        if ($bytes === false || strlen($bytes) > $limit) {
            $this->invalid('The upload could not be read.');
        }

        return $bytes;
    }

    private function validSfnt(string $bytes): bool
    {
        $tables = $this->uint16($bytes, 4);
        if ($tables < 1 || strlen($bytes) < 12 + $tables * 16) {
            return false;
        }
        for ($index = 0; $index < $tables; $index++) {
            $offset = $this->uint32($bytes, 12 + $index * 16 + 8);
            $length = $this->uint32($bytes, 12 + $index * 16 + 12);
            if ($offset < 12 + $tables * 16 || $length < 1 || $offset + $length > strlen($bytes)) {
                return false;
            }
        }

        return true;
    }

    private function uint16(string $bytes, int $offset): int
    {
        return unpack('n', substr($bytes, $offset, 2))[1] ?? 0;
    }

    private function uint32(string $bytes, int $offset): int
    {
        return unpack('N', substr($bytes, $offset, 4))[1] ?? 0;
    }

    private function invalid(string $message): never
    {
        throw ValidationException::withMessages(['file' => $message]);
    }
}
