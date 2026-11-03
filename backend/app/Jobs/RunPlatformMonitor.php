<?php

namespace App\Jobs;

use App\Services\Platform\Monitor;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class RunPlatformMonitor implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 150;

    public int $uniqueFor = 300;

    public function __construct(public string $kind) {}

    public function uniqueId(): string
    {
        return $this->kind;
    }

    public function handle(Monitor $monitor): void
    {
        $monitor->run($this->kind);
    }
}
