<?php

use App\Jobs\RunPlatformMonitor;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

foreach (['releases', 'github', 'seo'] as $kind) {
    Schedule::job(new RunPlatformMonitor($kind))
        ->name('platform:'.$kind)->cron(config('platform.schedule.'.$kind))
        ->withoutOverlapping(5)->onOneServer();
}

Schedule::command('platform:audit')->dailyAt('02:00')->withoutOverlapping(10)->onOneServer();
Schedule::command('platform:digest')->dailyAt('08:00')->withoutOverlapping(5)->onOneServer();
