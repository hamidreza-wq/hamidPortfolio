<?php

namespace App\Services\Health;

use App\Models\Project;
use App\Services\Backups\BackupManager;
use App\Services\Platform\GithubApi;
use App\Services\Platform\Notices;
use App\Services\Platform\PublicDns;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * Read-only health checks. Local checks run on every page view; site checks
 * request the public site over HTTPS (only the server-configured origin, at a
 * pinned public IP, never following redirects) and are cached between runs.
 *
 * Each check: ['label', 'state' => ok|warning|critical|unknown, 'detail'].
 */
class HealthMonitor
{
    public const HEARTBEAT_KEY = 'health:scheduler-heartbeat';

    public const SITE_KEY = 'health:site-checks';

    private const EXTENSIONS = ['pdo_mysql', 'fileinfo', 'gd', 'sodium', 'zip', 'curl', 'dom', 'mbstring', 'openssl'];

    private const HEADERS = [
        'content-security-policy' => 'Content-Security-Policy',
        'strict-transport-security' => 'Strict-Transport-Security',
        'x-content-type-options' => 'X-Content-Type-Options',
        'x-frame-options' => 'X-Frame-Options',
        'referrer-policy' => 'Referrer-Policy',
    ];

    public function __construct(private Notices $notices, private BackupManager $backups) {}

    /** @return array<string, list<array{label: string, state: string, detail: string}>> */
    public function local(): array
    {
        return [
            'Application' => [$this->database(), $this->migrations(), $this->php(), $this->errors()],
            'Server' => [$this->storage(), $this->disk(), $this->cache()],
            'Background work' => [$this->scheduler(), $this->queue(), $this->backup()],
        ];
    }

    /** @return array{checked_at: ?string, groups: array<string, list<array>>, error: ?string} */
    public function cachedSite(): array
    {
        return Cache::get(self::SITE_KEY, ['checked_at' => null, 'groups' => [], 'error' => null]);
    }

    /** Requests the public site and stores the result. */
    public function checkSite(): array
    {
        $result = ['checked_at' => now()->utc()->toIso8601String(), 'groups' => [], 'error' => null];
        try {
            $result['groups'] = $this->site();
        } catch (Throwable $exception) {
            $result['error'] = $exception->getMessage();
        }
        Cache::forever(self::SITE_KEY, $result);

        return $result;
    }

    /** Runs everything, then raises or resolves one notification per failing check. */
    public function run(): array
    {
        $site = $this->checkSite();
        $groups = $this->local() + $site['groups'];
        if ($site['error']) {
            $groups['Public site'] = [['label' => 'Site checks', 'state' => 'critical', 'detail' => $site['error']]];
        }
        foreach ($groups as $group => $checks) {
            foreach ($checks as $check) {
                $key = 'health:'.Str::slug($group.' '.$check['label']);
                if ($check['state'] === 'critical') {
                    $this->notices->put($key, 'health', 'high', 'Health: '.$check['label'], $check['detail'], 'admin.health');
                } elseif ($check['state'] === 'ok') {
                    $this->notices->resolve($key);
                }
            }
        }

        return $groups;
    }

    public static function overall(array $groups): string
    {
        $states = collect($groups)->flatten(1)->pluck('state');

        return match (true) {
            $states->contains('critical') => 'critical',
            $states->contains('warning') || $states->contains('unknown') => 'warning',
            default => 'ok',
        };
    }

    private function database(): array
    {
        try {
            $start = microtime(true);
            DB::select('select 1');
            $ms = (int) round((microtime(true) - $start) * 1000);
            $version = DB::connection()->getServerVersion();

            return ['label' => 'Database', 'state' => $ms > 500 ? 'warning' : 'ok', 'detail' => "Connected ({$version}), answered in {$ms} ms."];
        } catch (Throwable) {
            return ['label' => 'Database', 'state' => 'critical', 'detail' => 'The database did not answer. Check the server database credentials and that MySQL is running.'];
        }
    }

    private function migrations(): array
    {
        try {
            $files = collect(glob(database_path('migrations/*.php')) ?: [])->map(fn ($file) => basename($file, '.php'));
            $ran = DB::table('migrations')->pluck('migration');
            $pending = $files->diff($ran)->values();
        } catch (Throwable) {
            return ['label' => 'Database migrations', 'state' => 'unknown', 'detail' => 'The migrations table could not be read.'];
        }

        return $pending->isEmpty()
            ? ['label' => 'Database migrations', 'state' => 'ok', 'detail' => 'Every migration in this release has been applied (schema '.config('platform.schema_version').').']
            : ['label' => 'Database migrations', 'state' => 'critical', 'detail' => $pending->count().' migration(s) not applied: '.$pending->implode(', ').'. Back up, then run php artisan migrate --force on the server.'];
    }

    private function php(): array
    {
        $missing = array_values(array_filter(self::EXTENSIONS, fn ($extension) => ! extension_loaded($extension)));
        if (version_compare(PHP_VERSION, '8.3.0', '<')) {
            return ['label' => 'PHP', 'state' => 'critical', 'detail' => 'PHP '.PHP_VERSION.' is older than the required 8.3.'];
        }

        return $missing
            ? ['label' => 'PHP', 'state' => 'critical', 'detail' => 'PHP '.PHP_VERSION.' is missing: '.implode(', ', $missing).'.']
            : ['label' => 'PHP', 'state' => 'ok', 'detail' => 'PHP '.PHP_VERSION.' with every required extension.'];
    }

    /** Counts ERROR-or-worse entries in the newest log file from the last 24 hours. */
    private function errors(): array
    {
        $files = glob(storage_path('logs/laravel*.log')) ?: [];
        usort($files, fn ($a, $b) => filemtime($b) <=> filemtime($a));
        if (! $files) {
            return ['label' => 'Server errors', 'state' => 'ok', 'detail' => 'No log file yet, so nothing has been logged.'];
        }
        $handle = fopen($files[0], 'rb');
        $size = filesize($files[0]);
        fseek($handle, max(0, $size - 524288));
        $tail = (string) stream_get_contents($handle);
        fclose($handle);

        $since = now()->subDay();
        $count = 0;
        $last = null;
        preg_match_all('/^\[(\d{4}-\d\d-\d\d[ T]\d\d:\d\d:\d\d)[^\]]*\] \w+\.(ERROR|CRITICAL|ALERT|EMERGENCY): ([^\r\n]*)/m', $tail, $matches, PREG_SET_ORDER);
        foreach ($matches as [, $time, , $message]) {
            if (Carbon::parse($time)->gte($since)) {
                $count++;
                $last = $message;
            }
        }
        if ($count === 0) {
            return ['label' => 'Server errors', 'state' => 'ok', 'detail' => 'No errors logged in the last 24 hours.'];
        }

        return ['label' => 'Server errors', 'state' => $count >= 20 ? 'critical' : 'warning',
            'detail' => "{$count} error(s) logged in the last 24 hours. Latest: ".Str::limit($last, 160).' Full details are in storage/logs on the server.'];
    }

    private function storage(): array
    {
        $failed = [];
        foreach (['local' => 'private', 'public' => 'public uploads'] as $disk => $label) {
            $probe = '.health-'.bin2hex(random_bytes(4));
            try {
                if (! Storage::disk($disk)->put($probe, 'ok') || Storage::disk($disk)->get($probe) !== 'ok') {
                    $failed[] = $label;
                }
            } catch (Throwable) {
                $failed[] = $label;
            } finally {
                try {
                    Storage::disk($disk)->delete($probe);
                } catch (Throwable) {
                }
            }
        }

        return $failed
            ? ['label' => 'File storage', 'state' => 'critical', 'detail' => 'Cannot write to '.implode(' and ', $failed).' storage. Uploads and backups will fail; check folder permissions.']
            : ['label' => 'File storage', 'state' => 'ok', 'detail' => 'Private and public upload storage are writable.'];
    }

    private function disk(): array
    {
        $free = @disk_free_space(storage_path());
        $total = @disk_total_space(storage_path());
        if ($free === false || ! $total) {
            return ['label' => 'Disk space', 'state' => 'unknown', 'detail' => 'The host does not report disk space.'];
        }
        $gb = fn ($bytes) => number_format($bytes / 1073741824, 1).' GB';
        $detail = $gb($free).' free of '.$gb($total).'.';

        return match (true) {
            $free < 200 * 1048576 => ['label' => 'Disk space', 'state' => 'critical', 'detail' => $detail.' Backups stop below 200 MB.'],
            $free < 1073741824 || $free / $total < 0.1 => ['label' => 'Disk space', 'state' => 'warning', 'detail' => $detail.' Consider removing old backups or files.'],
            default => ['label' => 'Disk space', 'state' => 'ok', 'detail' => $detail],
        };
    }

    private function cache(): array
    {
        $key = 'health:probe:'.bin2hex(random_bytes(4));
        try {
            Cache::put($key, 'ok', 60);
            $ok = Cache::get($key) === 'ok';
            Cache::forget($key);
        } catch (Throwable) {
            $ok = false;
        }

        return $ok
            ? ['label' => 'Cache', 'state' => 'ok', 'detail' => 'The "'.config('cache.default').'" cache stores and returns values.']
            : ['label' => 'Cache', 'state' => 'critical', 'detail' => 'The "'.config('cache.default').'" cache did not return a stored value. Sign-in throttling and locks depend on it.'];
    }

    private function scheduler(): array
    {
        $beat = Cache::get(self::HEARTBEAT_KEY);
        if (! $beat) {
            return ['label' => 'Scheduler', 'state' => 'critical', 'detail' => 'No scheduler run has been recorded. Add a cron job that runs "php artisan schedule:run" every minute; automatic backups, update checks and SEO scans depend on it.'];
        }
        $at = Carbon::parse($beat);

        return $at->lt(now()->subMinutes(10))
            ? ['label' => 'Scheduler', 'state' => 'critical', 'detail' => 'The scheduler last ran '.$at->diffForHumans().'. Check the server cron job.']
            : ['label' => 'Scheduler', 'state' => 'ok', 'detail' => 'Last ran '.$at->diffForHumans().'.'];
    }

    private function queue(): array
    {
        $connection = config('queue.default');
        if ($connection === 'sync') {
            return ['label' => 'Queue', 'state' => 'ok', 'detail' => 'Jobs run immediately (sync), so there is no backlog.'];
        }
        if (config("queue.connections.{$connection}.driver") !== 'database') {
            return ['label' => 'Queue', 'state' => 'unknown', 'detail' => "The \"{$connection}\" queue can't be inspected from here."];
        }
        try {
            $table = config("queue.connections.{$connection}.table", 'jobs');
            $waiting = DB::table($table)->count();
            $oldest = DB::table($table)->min('created_at');
            $failed = Schema::hasTable('failed_jobs')
                ? DB::table('failed_jobs')->where('failed_at', '>=', now()->subDay())->count() : 0;
        } catch (Throwable) {
            return ['label' => 'Queue', 'state' => 'unknown', 'detail' => 'The queue tables could not be read.'];
        }
        $age = $oldest ? now()->timestamp - (int) $oldest : 0;
        $detail = "{$waiting} job(s) waiting".($oldest ? ', oldest '.Carbon::createFromTimestamp((int) $oldest)->diffForHumans() : '').", {$failed} failed in 24 hours.";

        return match (true) {
            $age > 3600 => ['label' => 'Queue', 'state' => 'critical', 'detail' => $detail.' Jobs are not being processed; check the queue worker.'],
            $age > 900 || $failed > 0 => ['label' => 'Queue', 'state' => 'warning', 'detail' => $detail],
            default => ['label' => 'Queue', 'state' => 'ok', 'detail' => $detail],
        };
    }

    private function backup(): array
    {
        try {
            $latest = $this->backups->latestVerified();
        } catch (Throwable) {
            return ['label' => 'Backups', 'state' => 'unknown', 'detail' => 'The backup folder could not be read.'];
        }
        if (! $latest) {
            return ['label' => 'Backups', 'state' => 'critical', 'detail' => 'No verified backup exists. Create one on the Backups page.'];
        }
        $at = BackupManager::createdAt($latest);

        return match (true) {
            $at->lt(now()->subDays(7)) => ['label' => 'Backups', 'state' => 'critical', 'detail' => 'The newest verified backup is from '.$at->diffForHumans().'.'],
            $at->lt(now()->subHours(36)) => ['label' => 'Backups', 'state' => 'warning', 'detail' => 'The newest verified backup is from '.$at->diffForHumans().'. Daily backups may not be running.'],
            default => ['label' => 'Backups', 'state' => 'ok', 'detail' => 'Newest verified backup: '.$at->diffForHumans().'.'],
        };
    }

    /** @return array<string, list<array>> */
    private function site(): array
    {
        $origin = rtrim((string) config('platform.seo_origin'), '/');
        $parts = parse_url($origin);
        if (! $origin || ($parts['scheme'] ?? '') !== 'https' || empty($parts['host'])
            || isset($parts['port']) || isset($parts['user']) || ! empty($parts['path']) || isset($parts['query'])) {
            return ['Public site' => [['label' => 'Site checks', 'state' => 'unknown',
                'detail' => 'Set SEO_MONITOR_ORIGIN on the server (for example https://hamidbayat.co.uk) to check the public site.']]];
        }
        $host = $parts['host'];
        $ip = app(PublicDns::class)->ipv4($host);

        $paths = ['/' => 'Home page', '/privacy' => 'Privacy notice', '/sitemap.xml' => 'Sitemap', '/robots.txt' => 'robots.txt', '/up' => 'Health endpoint'];
        foreach (Project::where('is_published', true)->orderBy('sort_order')->limit(5)->get() as $project) {
            if (preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/D', $project->slug)) {
                $paths['/projects/'.$project->slug] = $project->name;
            }
        }

        $pages = [];
        $homeHeaders = null;
        $version = null;
        foreach ($paths as $path => $label) {
            try {
                $start = microtime(true);
                $response = app(GithubApi::class)->request($origin.$path, 2_000_000, extraOptions: [
                    'curl' => [CURLOPT_RESOLVE => [$host.':443:'.$ip]], 'proxy' => '',
                ]);
                $ms = (int) round((microtime(true) - $start) * 1000);
                $status = $response->status();
                if ($path === '/') {
                    $homeHeaders = array_change_key_case($response->headers(), CASE_LOWER);
                }
                if ($path === '/up') {
                    $version = $response->header('X-Portfolio-Version');
                }
                $pages[] = match (true) {
                    $status !== 200 => ['label' => $label, 'state' => 'critical', 'detail' => "{$path} returned HTTP {$status} (redirects are not followed)."],
                    $ms > 2500 => ['label' => $label, 'state' => 'warning', 'detail' => "{$path} answered in {$ms} ms, which is slow."],
                    default => ['label' => $label, 'state' => 'ok', 'detail' => "{$path} answered HTTP 200 in {$ms} ms."],
                };
            } catch (Throwable $exception) {
                $pages[] = ['label' => $label, 'state' => 'critical', 'detail' => "{$path} could not be loaded: ".$exception->getMessage()];
            }
        }

        $installed = trim((string) @file_get_contents(base_path('../VERSION')));
        $security = [$this->certificate($host, $ip)];
        $security[] = $version === null
            ? ['label' => 'Live version', 'state' => 'unknown', 'detail' => 'The health endpoint did not report a version.']
            : ($version === $installed
                ? ['label' => 'Live version', 'state' => 'ok', 'detail' => "The public site is serving v{$version}, matching this installation."]
                : ['label' => 'Live version', 'state' => 'warning', 'detail' => "The public site reports v{$version}, but this installation is v{$installed}. A cache or proxy may be serving an old release."]);
        if ($homeHeaders !== null) {
            $missing = array_values(array_filter(self::HEADERS, fn ($name, $key) => empty($homeHeaders[$key]), ARRAY_FILTER_USE_BOTH));
            $security[] = $missing
                ? ['label' => 'Security headers', 'state' => 'warning', 'detail' => 'The home page is missing: '.implode(', ', $missing).'.']
                : ['label' => 'Security headers', 'state' => 'ok', 'detail' => 'The home page sends '.implode(', ', self::HEADERS).'.'];
        }

        return ['Public pages' => $pages, 'HTTPS and security' => $security];
    }

    private function certificate(string $host, string $ip): array
    {
        try {
            $expires = app(CertificateInspector::class)->expiry($host, $ip);
        } catch (Throwable $exception) {
            return ['label' => 'HTTPS certificate', 'state' => 'critical', 'detail' => $exception->getMessage()];
        }
        $days = (int) floor(now()->diffInDays($expires, false));
        $detail = 'Valid for '.$host.', expires '.$expires->format('j M Y')." ({$days} days).";

        return match (true) {
            $days < 7 => ['label' => 'HTTPS certificate', 'state' => 'critical', 'detail' => $detail.' Renew it now in the hosting panel.'],
            $days < 21 => ['label' => 'HTTPS certificate', 'state' => 'warning', 'detail' => $detail.' Auto-renewal usually happens around now; check it does.'],
            default => ['label' => 'HTTPS certificate', 'state' => 'ok', 'detail' => $detail],
        };
    }
}
