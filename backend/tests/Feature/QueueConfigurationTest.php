<?php

namespace Tests\Feature;

use App\Jobs\RunPlatformMonitor;
use Tests\TestCase;

class QueueConfigurationTest extends TestCase
{
    public function test_queue_visibility_timeout_exceeds_the_monitor_runtime_limit(): void
    {
        $job = new RunPlatformMonitor('seo');
        foreach (['database', 'redis', 'beanstalkd'] as $connection) {
            $this->assertGreaterThan($job->timeout, config('queue.connections.'.$connection.'.retry_after'), $connection);
        }
        $this->assertGreaterThan($job->timeout, $job->uniqueFor);
        $this->assertSame(1, $job->tries);
    }
}
