<?php

namespace App\Console\Commands;

use App\Services\TestDatabaseSafety;
use Illuminate\Console\Command;

class VerifyTestDatabase extends Command
{
    protected $signature = 'portfolio:test-database:verify';

    protected $description = 'Verify and print the effective isolated MySQL test database before QA operations';

    public function handle(TestDatabaseSafety $safety): int
    {
        $this->line(json_encode($safety->verify(), JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));

        return self::SUCCESS;
    }
}
