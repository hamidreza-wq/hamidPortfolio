<?php

namespace App\Services\Backups;

use App\Models\AuditLog;
use App\Services\Platform\Notices;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use Throwable;
use ZipArchive;

/**
 * Self-contained backups of the portfolio's database rows and uploaded files.
 *
 * Each backup is one ZIP in private storage holding a manifest, one JSON Lines
 * file per table (data only; the schema comes from migrations), and the public
 * upload disk. A sidecar JSON file records its status. Every backup is verified
 * by re-reading it before it counts as good.
 *
 * Not included: the .env file and APP_KEY. Encrypted columns (two-factor
 * secrets, Search Console tokens) can only be read with the same APP_KEY.
 */
class BackupManager
{
    public const FORMAT = 1;

    public const NAME_PATTERN = '/^backup-\d{8}-\d{6}-[a-f0-9]{6}\.zip$/';

    /** Short-lived or regenerable tables that must not be restored over live data. */
    private const SKIP_TABLES = ['sessions', 'cache', 'cache_locks', 'jobs', 'job_batches', 'failed_jobs'];

    private const NOTICE_KEY = 'backups:latest';

    public function __construct(private Notices $notices) {}

    public function directory(): string
    {
        $path = Storage::disk('local')->path('backups');
        File::ensureDirectoryExists($path, 0700);

        return $path;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function list(): array
    {
        $backups = [];
        foreach (glob($this->directory().DIRECTORY_SEPARATOR.'backup-*.zip') ?: [] as $file) {
            $name = basename($file);
            if (! preg_match(self::NAME_PATTERN, $name)) {
                continue;
            }
            $backups[] = $this->status($name) + ['name' => $name, 'bytes' => filesize($file)];
        }
        usort($backups, fn ($a, $b) => strcmp($b['name'], $a['name']));

        return $backups;
    }

    public function path(string $name): string
    {
        if (! preg_match(self::NAME_PATTERN, $name)) {
            throw new RuntimeException('Not a backup file name.');
        }
        $path = $this->directory().DIRECTORY_SEPARATOR.$name;
        if (! is_file($path)) {
            throw new RuntimeException('That backup does not exist.');
        }

        return $path;
    }

    /** @return array<string, mixed> */
    public function create(string $trigger, ?int $userId = null): array
    {
        $lock = Cache::lock('portfolio-backup', 900);
        if (! $lock->get()) {
            throw new RuntimeException('Another backup or restore is already running.');
        }

        $name = 'backup-'.now()->utc()->format('Ymd-His').'-'.bin2hex(random_bytes(3)).'.zip';
        $final = $this->directory().DIRECTORY_SEPARATOR.$name;
        $partial = $final.'.partial';
        $work = $this->directory().DIRECTORY_SEPARATOR.'.work-'.bin2hex(random_bytes(6));

        try {
            $free = @disk_free_space($this->directory());
            if ($free !== false && $free < config('portfolio.backups.min_free_bytes')) {
                throw new RuntimeException('Less than 200 MB of disk space is free, so no backup was written.');
            }
            File::ensureDirectoryExists($work, 0700);

            $zip = new ZipArchive;
            if ($zip->open($partial, ZipArchive::CREATE | ZipArchive::EXCL) !== true) {
                throw new RuntimeException('The backup file could not be created.');
            }

            $tables = [];
            foreach ($this->tables() as $table) {
                $file = $work.DIRECTORY_SEPARATOR.$table.'.jsonl';
                $tables[$table] = $this->dumpTable($table, $file);
                $zip->addFile($file, "database/{$table}.jsonl");
            }

            $files = [];
            $root = Storage::disk('public')->path('');
            foreach ($this->filesUnder($root) as $relative) {
                $absolute = $root.DIRECTORY_SEPARATOR.$relative;
                $files[$relative] = ['bytes' => filesize($absolute), 'sha256' => hash_file('sha256', $absolute)];
                $zip->addFile($absolute, 'files/public/'.$relative);
            }

            $manifest = [
                'format' => self::FORMAT,
                'app_version' => $this->appVersion(),
                'schema_version' => config('platform.schema_version'),
                'created_at' => now()->utc()->toIso8601String(),
                'trigger' => $trigger,
                'database' => DB::connection()->getDriverName(),
                'tables' => $tables,
                'skipped_tables' => self::SKIP_TABLES,
                'files' => $files,
                'note' => 'Data only. Restore into the same schema version. Encrypted columns need the same APP_KEY.',
            ];
            $zip->addFromString('manifest.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
            if (! $zip->close()) {
                throw new RuntimeException('The backup file could not be finished.');
            }
            rename($partial, $final);
            chmod($final, 0600);

            $check = $this->verify($name);
            $status = [
                'created_at' => $manifest['created_at'],
                'trigger' => $trigger,
                'user_id' => $userId,
                'app_version' => $manifest['app_version'],
                'schema_version' => $manifest['schema_version'],
                'tables' => count($tables),
                'rows' => array_sum(array_column($tables, 'rows')),
                'files' => count($files),
            ] + $check;
            $this->writeStatus($name, $status);

            if (! $check['verified']) {
                throw new RuntimeException('The backup was written but failed verification: '.$check['error']);
            }

            AuditLog::create(['user_id' => $userId, 'action' => 'backup.created', 'details' => ['name' => $name, 'trigger' => $trigger]]);
            $this->notices->resolve(self::NOTICE_KEY);
            $this->prune();

            return $status + ['name' => $name];
        } catch (Throwable $exception) {
            @unlink($partial);
            AuditLog::create(['user_id' => $userId, 'action' => 'backup.failed', 'details' => ['trigger' => $trigger, 'error' => $exception->getMessage()]]);
            $this->notices->put(self::NOTICE_KEY, 'backups', 'high', 'Backup failed',
                'The '.$trigger.' backup did not complete: '.$exception->getMessage(), 'admin.backups.index');

            throw $exception;
        } finally {
            File::deleteDirectory($work);
            $lock->release();
        }
    }

    /**
     * Re-reads the archive: ZIP consistency, manifest, every file's SHA-256 and
     * size, and every table's row count.
     *
     * @return array{verified: bool, verified_at: string, error: ?string}
     */
    public function verify(string $name): array
    {
        $result = fn (?string $error) => ['verified' => $error === null, 'verified_at' => now()->utc()->toIso8601String(), 'error' => $error];
        $zip = new ZipArchive;
        if ($zip->open($this->path($name), ZipArchive::CHECKCONS) !== true) {
            return $result('The ZIP structure is damaged.');
        }
        try {
            $manifest = $this->manifest($zip);
            if (($manifest['format'] ?? null) !== self::FORMAT) {
                return $result('Unknown backup format.');
            }
            foreach ($manifest['tables'] as $table => $meta) {
                [$sha, $lines] = $this->hashEntry($zip, "database/{$table}.jsonl", true);
                if ($sha !== $meta['sha256'] || $lines !== $meta['rows']) {
                    return $result("Table {$table} does not match its checksum or row count.");
                }
            }
            foreach ($manifest['files'] as $relative => $meta) {
                [$sha] = $this->hashEntry($zip, 'files/public/'.$relative);
                if ($sha !== $meta['sha256']) {
                    return $result("File {$relative} does not match its checksum.");
                }
            }
        } catch (Throwable $exception) {
            return $result($exception->getMessage());
        } finally {
            $zip->close();
        }

        return $result(null);
    }

    public function recordVerification(string $name, ?int $userId): array
    {
        $check = $this->verify($name);
        $this->writeStatus($name, $check + $this->status($name));
        AuditLog::create(['user_id' => $userId, 'action' => $check['verified'] ? 'backup.verified' : 'backup.verification_failed', 'details' => ['name' => $name]]);

        return $check;
    }

    public function delete(string $name, ?int $userId): void
    {
        $path = $this->path($name);
        unlink($path);
        @unlink($path.'.json');
        AuditLog::create(['user_id' => $userId, 'action' => 'backup.deleted', 'details' => ['name' => $name]]);
    }

    /**
     * Replaces every backed-up table's rows and the public upload disk with the
     * backup's contents. The database part runs in one transaction and rolls
     * back completely on any error. Callers must take a safety backup first.
     *
     * @return array{tables: int, rows: int, files: int}
     */
    public function restore(string $name, ?int $userId = null): array
    {
        $check = $this->verify($name);
        if (! $check['verified']) {
            throw new RuntimeException('Refusing to restore: '.$check['error']);
        }
        $zip = new ZipArchive;
        $zip->open($this->path($name), ZipArchive::CHECKCONS);
        $manifest = $this->manifest($zip);
        if ($manifest['schema_version'] !== config('platform.schema_version')) {
            $zip->close();
            throw new RuntimeException("This backup is for database schema {$manifest['schema_version']}, but the installed code uses schema "
                .config('platform.schema_version').'. Deploy the matching release first.');
        }
        $missing = array_diff(array_keys($manifest['tables']), $this->tables());
        if ($missing) {
            $zip->close();
            throw new RuntimeException('These tables no longer exist: '.implode(', ', $missing));
        }

        $lock = Cache::lock('portfolio-backup', 900);
        if (! $lock->get()) {
            $zip->close();
            throw new RuntimeException('Another backup or restore is already running.');
        }
        $staging = null;
        try {
            // Stage the files first, so a broken file never leaves the database half-restored.
            $root = rtrim(Storage::disk('public')->path(''), '/\\');
            $staging = $root.'.restore-'.bin2hex(random_bytes(6));
            File::ensureDirectoryExists($staging, 0755);
            foreach (array_keys($manifest['files']) as $relative) {
                $target = $staging.DIRECTORY_SEPARATOR.$relative;
                File::ensureDirectoryExists(dirname($target), 0755);
                if (file_put_contents($target, $zip->getStream('files/public/'.$relative)) === false) {
                    throw new RuntimeException("Could not stage {$relative}.");
                }
            }

            $rows = 0;
            DB::transaction(function () use ($zip, $manifest, &$rows) {
                Schema::disableForeignKeyConstraints();
                try {
                    foreach (array_keys($manifest['tables']) as $table) {
                        DB::table($table)->delete();
                        $batch = [];
                        $stream = $zip->getStream("database/{$table}.jsonl");
                        while (($line = fgets($stream)) !== false) {
                            if (trim($line) === '') {
                                continue;
                            }
                            $batch[] = json_decode($line, true, 512, JSON_THROW_ON_ERROR);
                            if (count($batch) === 200) {
                                DB::table($table)->insert($batch);
                                $rows += 200;
                                $batch = [];
                            }
                        }
                        fclose($stream);
                        if ($batch) {
                            DB::table($table)->insert($batch);
                            $rows += count($batch);
                        }
                    }
                } finally {
                    Schema::enableForeignKeyConstraints();
                }
            });

            // Database committed: swap the upload directory in.
            $previous = $root.'.before-restore-'.bin2hex(random_bytes(6));
            if (is_dir($root) && ! rename($root, $previous)) {
                throw new RuntimeException('Database restored, but the upload folder could not be swapped. Uploaded files are unchanged.');
            }
            if (! rename($staging, $root)) {
                is_dir($previous) && rename($previous, $root);
                throw new RuntimeException('Database restored, but the upload folder could not be swapped. Uploaded files are unchanged.');
            }
            $staging = null;
            File::deleteDirectory($previous);

            // Written after the restore, so the restored audit log records it.
            AuditLog::create(['user_id' => $userId, 'action' => 'backup.restored', 'details' => ['name' => $name]]);

            return ['tables' => count($manifest['tables']), 'rows' => $rows, 'files' => count($manifest['files'])];
        } finally {
            $zip->close();
            $staging && File::deleteDirectory($staging);
            $lock->release();
        }
    }

    public function latestVerified(): ?array
    {
        foreach ($this->list() as $backup) {
            if ($backup['verified'] ?? false) {
                return $backup;
            }
        }

        return null;
    }

    /** @return list<string> */
    private function tables(): array
    {
        $tables = array_column(Schema::getTables(DB::connection()->getDatabaseName()), 'name');
        sort($tables);

        return array_values(array_diff($tables, self::SKIP_TABLES));
    }

    /** @return array{rows: int, sha256: string} */
    private function dumpTable(string $table, string $file): array
    {
        $handle = fopen($file, 'wb');
        $hash = hash_init('sha256');
        $rows = 0;
        $columns = Schema::getColumnListing($table);
        $order = in_array('id', $columns, true) ? 'id' : $columns[0];
        foreach (DB::table($table)->orderBy($order)->cursor() as $row) {
            $line = json_encode((array) $row, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)."\n";
            fwrite($handle, $line);
            hash_update($hash, $line);
            $rows++;
        }
        fclose($handle);

        return ['rows' => $rows, 'sha256' => hash_final($hash)];
    }

    /** @return list<string> */
    private function filesUnder(string $root): array
    {
        if (! is_dir($root)) {
            return [];
        }
        $files = [];
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, RecursiveDirectoryIterator::SKIP_DOTS));
        foreach ($iterator as $file) {
            if ($file->isFile() && ! $file->isLink() && ! str_ends_with($file->getFilename(), '.pending-delete')
                && $file->getFilename() !== '.gitignore') {
                $files[] = str_replace('\\', '/', substr($file->getPathname(), strlen(rtrim($root, '/\\')) + 1));
            }
        }
        sort($files);

        return $files;
    }

    /** @return array<string, mixed> */
    private function manifest(ZipArchive $zip): array
    {
        $raw = $zip->getFromName('manifest.json');
        if ($raw === false) {
            throw new RuntimeException('The manifest is missing.');
        }
        $manifest = json_decode($raw, true, 64, JSON_THROW_ON_ERROR);
        foreach (array_keys($manifest['files'] ?? []) as $relative) {
            if (! is_string($relative) || str_contains($relative, '..') || str_starts_with($relative, '/')
                || str_contains($relative, '\\') || preg_match('/^[A-Za-z]:/', $relative)) {
                throw new RuntimeException('The manifest contains an unsafe file path.');
            }
        }
        foreach (array_keys($manifest['tables'] ?? []) as $table) {
            if (! preg_match('/^[a-z0-9_]+$/', (string) $table)) {
                throw new RuntimeException('The manifest contains an unsafe table name.');
            }
        }

        return $manifest;
    }

    /** @return array{0: string, 1: int} */
    private function hashEntry(ZipArchive $zip, string $entry, bool $countLines = false): array
    {
        $stream = $zip->getStream($entry);
        if ($stream === false) {
            throw new RuntimeException("{$entry} is missing.");
        }
        $hash = hash_init('sha256');
        $lines = 0;
        while (! feof($stream)) {
            $chunk = fread($stream, 1 << 16);
            if ($chunk === false) {
                break;
            }
            hash_update($hash, $chunk);
            $countLines && $lines += substr_count($chunk, "\n");
        }
        fclose($stream);

        return [hash_final($hash), $lines];
    }

    private function prune(): void
    {
        $keep = config('portfolio.backups.keep');
        $verifiedSeen = false;
        foreach ($this->list() as $index => $backup) {
            $verified = $backup['verified'] ?? false;
            if ($index >= $keep && ($verifiedSeen || ! $verified)) {
                $path = $this->directory().DIRECTORY_SEPARATOR.$backup['name'];
                @unlink($path);
                @unlink($path.'.json');
            }
            $verifiedSeen = $verifiedSeen || $verified;
        }
    }

    /** @return array<string, mixed> */
    private function status(string $name): array
    {
        $file = $this->directory().DIRECTORY_SEPARATOR.$name.'.json';
        $status = is_file($file) ? json_decode((string) file_get_contents($file), true) : null;

        return is_array($status) ? $status : ['verified' => false, 'error' => 'No status recorded for this file.'];
    }

    private function writeStatus(string $name, array $status): void
    {
        $file = $this->directory().DIRECTORY_SEPARATOR.$name.'.json';
        file_put_contents($file, json_encode($status, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        @chmod($file, 0600);
    }

    private function appVersion(): string
    {
        $file = base_path('../VERSION');

        return is_file($file) ? trim((string) file_get_contents($file)) : 'unknown';
    }

    public static function createdAt(array $backup): ?Carbon
    {
        return isset($backup['created_at']) ? Carbon::parse($backup['created_at']) : null;
    }
}
