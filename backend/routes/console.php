<?php

use App\Jobs\RunPlatformMonitor;
use App\Models\PageView;
use App\Models\Testimonial;
use App\Services\Health\HealthMonitor;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

foreach (['releases', 'github', 'seo'] as $kind) {
    Schedule::job(new RunPlatformMonitor($kind))
        ->name('platform:'.$kind)->cron(config('platform.schedule.'.$kind))
        ->withoutOverlapping(5)->onOneServer();
}

// Lets the Health page tell whether the server cron job is running.
Schedule::call(fn () => Cache::forever(HealthMonitor::HEARTBEAT_KEY, now()->utc()->toIso8601String()))
    ->everyMinute()->name('health:heartbeat')->onOneServer();
Schedule::command('portfolio:health:check')->hourlyAt(7)->withoutOverlapping(10)->onOneServer();
Schedule::command('platform:audit')->dailyAt('02:00')->withoutOverlapping(10)->onOneServer();
Schedule::command('portfolio:backup:create', ['--trigger' => 'scheduled'])
    ->cron(config('portfolio.backups.schedule'))->withoutOverlapping(30)->onOneServer();
Schedule::command('platform:digest')->dailyAt('08:00')->withoutOverlapping(5)->onOneServer();
Schedule::call(fn () => Testimonial::where('status', 'rejected')->where('updated_at', '<', now()->subDays(90))->delete())
    ->daily()->name('testimonials:prune-rejected')->withoutOverlapping(10)->onOneServer();
Schedule::call(fn () => PageView::where('viewed_at', '<', now()->subDays(config('portfolio.analytics_retention_days')))->delete())
    ->weekly()->name('analytics:prune')->withoutOverlapping(10)->onOneServer();
