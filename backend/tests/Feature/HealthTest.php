<?php

namespace Tests\Feature;

use App\Models\PlatformNotice;
use App\Models\User;
use App\Services\Backups\BackupManager;
use App\Services\Health\CertificateInspector;
use App\Services\Health\HealthMonitor;
use App\Services\Platform\PublicDns;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class HealthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Storage::fake('public');
        Http::preventStrayRequests();
        $this->seed();
        config(['platform.seo_origin' => 'https://portfolio.example']);
        $this->app->instance(PublicDns::class, new class extends PublicDns
        {
            public function ipv4(string $host): string
            {
                return '93.184.215.14';
            }
        });
        $this->certificateExpires(now()->addDays(60));
    }

    private function certificateExpires(Carbon $expires): void
    {
        $this->app->instance(CertificateInspector::class, new class($expires) extends CertificateInspector
        {
            public function __construct(private Carbon $expires) {}

            public function expiry(string $host, string $ip): Carbon
            {
                return $this->expires;
            }
        });
    }

    private int $homeStatus = 200;

    private array $homeHeaders = [];

    private bool $faked = false;

    /** One closure fake that reads the current settings, since registered fakes stack. */
    private function fakeSite(int $homeStatus = 200, array $homeHeaders = []): void
    {
        $this->homeStatus = $homeStatus;
        $this->homeHeaders = $homeHeaders ?: [
            'Content-Security-Policy' => "default-src 'self'", 'Strict-Transport-Security' => 'max-age=31536000',
            'X-Content-Type-Options' => 'nosniff', 'X-Frame-Options' => 'SAMEORIGIN', 'Referrer-Policy' => 'strict-origin-when-cross-origin',
        ];
        if ($this->faked) {
            return;
        }
        $this->faked = true;
        $version = trim(file_get_contents(base_path('../VERSION')));
        Http::fake(function ($request) use ($version) {
            return match (parse_url($request->url(), PHP_URL_PATH)) {
                '/up' => Http::response('', 200, ['X-Portfolio-Version' => $version]),
                '/' => Http::response('<html></html>', $this->homeStatus, $this->homeHeaders),
                default => Http::response('ok', 200),
            };
        });
    }

    private function find(array $groups, string $label): array
    {
        return collect($groups)->flatten(1)->firstWhere('label', $label);
    }

    public function test_health_page_requires_an_administrator(): void
    {
        $this->get('/admin/health')->assertRedirect('/admin/login');
        $this->actingAs(User::factory()->create(['is_admin' => false]))->get('/admin/health')->assertForbidden();
    }

    public function test_local_checks_report_database_storage_cache_and_migrations(): void
    {
        $groups = app(HealthMonitor::class)->local();

        $this->assertSame('ok', $this->find($groups, 'Database')['state']);
        $this->assertSame('ok', $this->find($groups, 'Database migrations')['state']);
        $this->assertSame('ok', $this->find($groups, 'File storage')['state']);
        $this->assertSame('ok', $this->find($groups, 'Cache')['state']);
        $this->assertSame('ok', $this->find($groups, 'Queue')['state'], 'tests use the sync queue');
    }

    public function test_scheduler_and_backup_states(): void
    {
        $monitor = app(HealthMonitor::class);
        $this->assertSame('critical', $this->find($monitor->local(), 'Scheduler')['state'], 'never ran');
        $this->assertSame('critical', $this->find($monitor->local(), 'Backups')['state'], 'no backup yet');

        Cache::forever(HealthMonitor::HEARTBEAT_KEY, now()->toIso8601String());
        app(BackupManager::class)->create('command');
        $this->assertSame('ok', $this->find($monitor->local(), 'Scheduler')['state']);
        $this->assertSame('ok', $this->find($monitor->local(), 'Backups')['state']);

        $this->travel(20)->minutes();
        $this->assertSame('critical', $this->find($monitor->local(), 'Scheduler')['state'], 'stopped running');
    }

    public function test_server_errors_are_counted_from_the_log(): void
    {
        $log = storage_path('logs/laravel.log');
        $original = is_file($log) ? file_get_contents($log) : null;
        try {
            file_put_contents($log, '['.now()->subHours(2)->format('Y-m-d H:i:s').'] testing.ERROR: Something broke in a test'.PHP_EOL
                .'['.now()->subDays(3)->format('Y-m-d H:i:s').'] testing.ERROR: Old error'.PHP_EOL
                .'['.now()->format('Y-m-d H:i:s').'] testing.INFO: Fine'.PHP_EOL);
            touch($log);
            $check = $this->find(app(HealthMonitor::class)->local(), 'Server errors');
            $this->assertSame('warning', $check['state']);
            $this->assertStringContainsString('1 error(s)', $check['detail']);
            $this->assertStringContainsString('Something broke', $check['detail']);
        } finally {
            $original === null ? @unlink($log) : file_put_contents($log, $original);
        }
    }

    public function test_site_checks_pages_certificate_headers_and_version(): void
    {
        $this->fakeSite();
        $site = app(HealthMonitor::class)->checkSite();

        $this->assertNull($site['error']);
        $this->assertSame('ok', $this->find($site['groups'], 'Home page')['state']);
        $this->assertSame('ok', $this->find($site['groups'], 'HTTPS certificate')['state']);
        $this->assertSame('ok', $this->find($site['groups'], 'Security headers')['state']);
        $this->assertSame('ok', $this->find($site['groups'], 'Live version')['state']);
        Http::assertSent(fn ($request) => str_starts_with($request->url(), 'https://portfolio.example/'));
        Http::assertNotSent(fn ($request) => ! str_starts_with($request->url(), 'https://portfolio.example/'));
    }

    public function test_site_problems_are_reported_and_raise_notifications(): void
    {
        $this->fakeSite(500, ['X-Frame-Options' => 'SAMEORIGIN']);
        $this->certificateExpires(now()->addDays(3));

        $groups = app(HealthMonitor::class)->run();

        $this->assertSame('critical', $this->find($groups, 'Home page')['state']);
        $this->assertStringContainsString('HTTP 500', $this->find($groups, 'Home page')['detail']);
        $this->assertSame('critical', $this->find($groups, 'HTTPS certificate')['state']);
        $this->assertSame('warning', $this->find($groups, 'Security headers')['state']);
        $this->assertTrue(PlatformNotice::where('title', 'Health: Home page')->whereNull('resolved_at')->exists());

        $this->fakeSite();
        $this->certificateExpires(now()->addDays(60));
        app(HealthMonitor::class)->run();
        $this->assertFalse(PlatformNotice::where('title', 'Health: Home page')->whereNull('resolved_at')->exists(), 'resolves when healthy again');
    }

    public function test_site_checks_need_a_configured_origin(): void
    {
        config(['platform.seo_origin' => null]);
        $site = app(HealthMonitor::class)->checkSite();

        $this->assertSame('unknown', $this->find($site['groups'], 'Site checks')['state']);
        Http::assertNothingSent();
    }

    public function test_page_and_button(): void
    {
        $this->fakeSite();
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)->get('/admin/health')->assertOk()->assertSee('Database')->assertSee('Not yet');
        $this->actingAs($admin)->post('/admin/health/check')->assertSessionHas('status', 'Public site checked just now.');
        $this->actingAs($admin)->get('/admin/health')->assertOk()->assertSee('HTTPS certificate')->assertSee('Public pages');
        $this->artisan('portfolio:health:check')->assertExitCode(1); // scheduler and backups are critical in a fresh test install
    }
}
