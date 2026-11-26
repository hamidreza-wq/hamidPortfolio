<?php

namespace App\Console\Commands;

use App\Services\Backups\BackupManager;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Throwable;

/**
 * Restore runs from the server shell only. It replaces all portfolio content,
 * so it always takes and verifies a safety backup first, and puts the site in
 * maintenance mode while it works.
 */
class RestoreBackup extends Command
{
    protected $signature = 'portfolio:backup:restore {name : Backup file name, e.g. backup-20261126-031500-a1b2c3.zip}';

    protected $description = 'Replace the database rows and uploaded files with a verified backup';

    public function handle(BackupManager $backups): int
    {
        $name = (string) $this->argument('name');
        try {
            $backups->path($name);
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->warn('This replaces every project, section, setting, message, account and uploaded file with the contents of');
        $this->warn("{$name}. Anything changed since that backup is lost from the live site (it stays in the safety backup).");
        if (! $this->confirm('Restore this backup?')) {
            return self::FAILURE;
        }

        $this->line('Writing and verifying a safety backup of the current site...');
        try {
            $safety = $backups->create('before-restore');
        } catch (Throwable $exception) {
            $this->error('Safety backup failed, so nothing was restored: '.$exception->getMessage());

            return self::FAILURE;
        }
        $this->info("Safety backup: {$safety['name']}");

        $wasDown = app()->isDownForMaintenance();
        if (! $wasDown) {
            Artisan::call('down', ['--retry' => 60]);
        }
        try {
            $result = $backups->restore($name);
        } catch (Throwable $exception) {
            $this->error('Restore failed: '.$exception->getMessage());
            $this->line('Unless the message above says the database was restored, the database changes were rolled back.');
            $this->line('To return to the state before this command, restore '.$safety['name'].'.');

            return self::FAILURE;
        } finally {
            if (! $wasDown) {
                Artisan::call('up');
            }
        }

        $this->info("Restored {$result['tables']} tables ({$result['rows']} rows) and {$result['files']} files.");
        $this->line('Run php artisan optimize:clear if cached pages show old content.');

        return self::SUCCESS;
    }
}
