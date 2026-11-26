<?php

namespace App\Console\Commands;

use App\Services\Backups\BackupManager;
use Illuminate\Console\Command;
use Throwable;

class CreateBackup extends Command
{
    protected $signature = 'portfolio:backup:create {--trigger=command : Recorded reason, e.g. scheduled}';

    protected $description = 'Write and verify a backup of the database rows and uploaded files';

    public function handle(BackupManager $backups): int
    {
        $trigger = in_array($this->option('trigger'), ['command', 'scheduled'], true) ? $this->option('trigger') : 'command';
        try {
            $backup = $backups->create($trigger);
        } catch (Throwable $exception) {
            $this->error('Backup failed: '.$exception->getMessage());

            return self::FAILURE;
        }
        $this->info("Verified backup {$backup['name']}: {$backup['tables']} tables, {$backup['rows']} rows, {$backup['files']} files.");

        return self::SUCCESS;
    }
}
