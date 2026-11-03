<?php

namespace App\Console\Commands;

use App\Services\Platform\IntegrationFailure;
use App\Services\Platform\Monitor;
use Illuminate\Console\Command;

class CheckPlatform extends Command
{
    protected $signature = 'platform:check {kind : releases, github or seo}';

    protected $description = 'Run a bounded read-only platform check and record the actual result';

    public function handle(Monitor $monitor): int
    {
        try {
            $run = $monitor->run($this->argument('kind'));
        } catch (IntegrationFailure $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }
        $this->line($run->status.': '.$run->message);

        return $run->status === 'successful' ? self::SUCCESS : self::FAILURE;
    }
}
