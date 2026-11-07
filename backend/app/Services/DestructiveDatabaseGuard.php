<?php

namespace App\Services;

use Illuminate\Console\Events\CommandStarting;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class DestructiveDatabaseGuard
{
    public function handle(CommandStarting $event): void
    {
        if (! in_array($event->command, ['db:wipe', 'migrate:fresh', 'migrate:refresh', 'migrate:reset', 'migrate:rollback'], true)) {
            return;
        }
        $name = $event->input->hasOption('database') ? $event->input->getOption('database') : null;
        $connection = DB::connection($name);
        if (! app()->environment('testing') || $connection->getDriverName() !== 'mysql'
            || ! str_ends_with($connection->getDatabaseName(), '_test')
            || ! str_ends_with((string) $connection->selectOne('SELECT DATABASE() AS name')->name, '_test')) {
            throw new RuntimeException('Destructive database commands are forbidden except against a verified MySQL *_test database in the testing environment. --env=testing alone does not select a test database.');
        }
    }
}
