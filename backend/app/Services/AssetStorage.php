<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

class AssetStorage
{
    public function put(string $directory, string $extension, string $bytes): string
    {
        $path = $directory.'/'.bin2hex(random_bytes(24)).'.'.$extension;
        try {
            if (! Storage::disk('public')->put($path, $bytes)) {
                throw new RuntimeException('The asset could not be saved.');
            }
        } catch (Throwable $exception) {
            $this->cleanup('public', $path);
            throw $exception;
        }

        return $path;
    }

    public function create(string $path, callable $operation): mixed
    {
        try {
            return DB::transaction($operation);
        } catch (Throwable $exception) {
            $this->cleanup('public', $path);
            throw $exception;
        }
    }

    public function remove(string $disk, string $path, callable $operation, ?string $replacement = null, ?callable $beforeMove = null): void
    {
        $storage = Storage::disk($disk);
        $backup = $path.'.'.bin2hex(random_bytes(12)).'.pending-delete';
        $quarantined = false;
        try {
            DB::transaction(function () use ($storage, $path, $backup, $operation, $beforeMove, &$quarantined) {
                if ($beforeMove) {
                    $beforeMove();
                }
                if ($storage->exists($path)) {
                    if (! $storage->move($path, $backup)) {
                        throw new RuntimeException('The stored asset could not be removed.');
                    }
                    $quarantined = true;
                }
                $operation();
            });
        } catch (Throwable $exception) {
            if ($quarantined) {
                try {
                    if (! $storage->move($backup, $path)) {
                        throw new RuntimeException('Asset rollback could not restore '.$path);
                    }
                } catch (Throwable $restoreException) {
                    report($restoreException);
                }
            }
            if ($replacement) {
                $this->cleanup('public', $replacement);
            }
            throw $exception;
        }
        if ($quarantined) {
            $this->cleanup($disk, $backup);
        }
    }

    private function cleanup(string $disk, string $path): void
    {
        try {
            if (! Storage::disk($disk)->delete($path)) {
                throw new RuntimeException('Asset cleanup failed for '.$path);
            }
        } catch (Throwable $exception) {
            report($exception);
        }
    }
}
