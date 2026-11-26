<?php

namespace App\Console\Commands;

use App\Services\SelfTest\SelfTest;
use Illuminate\Console\Command;

class RunSelfTest extends Command
{
    protected $signature = 'portfolio:self-test';

    protected $description = 'Check that the site, forms, uploads, search, permissions and links all work, and save a report';

    public function handle(SelfTest $selfTest): int
    {
        $report = $selfTest->run();
        foreach ($report['results'] as $result) {
            if ($result['status'] !== 'pass') {
                $this->line(sprintf('[%s] %s / %s%s: %s', strtoupper($result['status']), $result['area'], $result['check'],
                    $result['location'] ? ' ('.$result['location'].')' : '', $result['message']));
            }
        }
        $counts = $report['counts'];
        $this->info("{$counts['pass']} passed, {$counts['warn']} warnings, {$counts['fail']} failed, {$counts['skip']} not tested ({$report['seconds']} s).");

        return $counts['fail'] > 0 ? self::FAILURE : self::SUCCESS;
    }
}
