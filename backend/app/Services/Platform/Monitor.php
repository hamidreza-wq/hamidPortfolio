<?php

namespace App\Services\Platform;

use App\Models\PlatformRun;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class Monitor
{
    public function run(string $kind): PlatformRun
    {
        if (! in_array($kind, ['releases', 'github', 'seo'], true)) {
            throw new IntegrationFailure('Unknown monitor.');
        }
        $lock = Cache::lock('platform:'.$kind, 180);
        if (! $lock->get()) {
            throw new IntegrationFailure('This check is already running. Review its history before retrying.');
        }
        $run = null;
        try {
            $run = PlatformRun::create(['kind' => $kind, 'status' => 'running']);
            try {
                $summary = match ($kind) {
                    'releases' => app(Releases::class)->check(),
                    'github' => app(GithubDiscovery::class)->scan(),
                    'seo' => app(SeoMonitor::class)->scan(),
                };
                $run->update(['status' => 'successful', 'message' => $summary['message'], 'summary' => $summary]);
                app(Notices::class)->resolve('monitor:'.$kind);
            } catch (IntegrationFailure $exception) {
                $run->update(['status' => 'failed', 'message' => $exception->getMessage()]);
                Log::warning('Platform monitor failed', ['kind' => $kind, 'reason' => $exception->getMessage()]);
                app(Notices::class)->put('monitor:'.$kind, $kind === 'releases' ? 'updates' : $kind,
                    'high', ucfirst($kind).' check needs attention', $exception->getMessage(), $this->route($kind));
            }

            return $run;
        } finally {
            if ($run?->status === 'running') {
                $run->update(['status' => 'failed', 'message' => 'Unexpected internal failure. Consult the private application logs; this check did not succeed.']);
            }
            $lock->release();
        }
    }

    private function route(string $kind): string
    {
        return 'admin.platform.'.($kind === 'releases' ? 'updates' : $kind);
    }
}
