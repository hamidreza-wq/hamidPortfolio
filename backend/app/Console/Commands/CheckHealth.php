<?php

namespace App\Console\Commands;

use App\Services\Health\HealthMonitor;
use Illuminate\Console\Command;

class CheckHealth extends Command
{
    protected $signature = 'portfolio:health:check';

    protected $description = 'Run every health check, store site results and raise notifications for critical problems';

    public function handle(HealthMonitor $health): int
    {
        $groups = $health->run();
        foreach ($groups as $group => $checks) {
            $this->line($group);
            foreach ($checks as $check) {
                $this->line(sprintf('  [%s] %s: %s', strtoupper($check['state']), $check['label'], $check['detail']));
            }
        }
        $overall = HealthMonitor::overall($groups);
        $this->info('Overall: '.$overall);

        return $overall === 'critical' ? self::FAILURE : self::SUCCESS;
    }
}
