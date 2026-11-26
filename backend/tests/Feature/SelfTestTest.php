<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\MenuItem;
use App\Models\Page;
use App\Models\PlatformNotice;
use App\Models\User;
use App\Services\Backups\BackupManager;
use App\Services\Health\HealthMonitor;
use App\Services\Platform\Notices;
use App\Services\Platform\PublicDns;
use App\Services\SelfTest\SelfTest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SelfTestTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Storage::fake('public');
        $this->seed();
        $this->owner = User::factory()->create(['is_admin' => true]);
        config(['platform.seo_origin' => 'https://portfolio.example']);
        $this->app->instance(PublicDns::class, new class extends PublicDns
        {
            public function ipv4(string $host): string
            {
                return '93.184.215.14';
            }
        });
        // The "public site" is this app itself: each crawl request is answered by a real internal request.
        Http::fake(function (HttpRequest $request) {
            $path = parse_url($request->url(), PHP_URL_PATH) ?: '/';
            $response = $this->call('GET', $path);

            return Http::response($response->getContent() ?: '', $response->getStatusCode(), ['Content-Type' => $response->headers->get('Content-Type')]);
        });
    }

    private function check(array $report, string $check, ?string $location = null): ?array
    {
        return collect($report['results'])->first(fn ($r) => $r['check'] === $check && ($location === null || $r['location'] === $location));
    }

    public function test_a_healthy_site_passes_its_crawl_and_functional_checks(): void
    {
        $report = app(SelfTest::class)->run();

        foreach (['Home page', 'Sitemap', 'robots.txt', 'Contact form', 'Image processing', 'Font checks', 'Rejects disguised files', 'Search', 'Permissions', 'Owner account', 'Write and read', 'Pages, links and images'] as $check) {
            $this->assertSame('pass', $this->check($report, $check)['status'] ?? null, $check.': '.json_encode($this->check($report, $check)));
        }
        $this->assertSame('pass', $this->check($report, '/styles.css is served')['status']);
        $this->assertSame('pass', $this->check($report, '/app.mjs is served')['status']);
        $this->assertSame('skip', $this->check($report, 'JavaScript errors and phone layouts')['status'], 'honest about what needs a browser');
        $this->assertSame(0, AuditLog::where('action', 'self-test.probe')->count(), 'the database probe is rolled back');
        Http::assertNotSent(fn (HttpRequest $request) => ! str_starts_with($request->url(), 'https://portfolio.example/'));
    }

    public function test_broken_links_and_missing_sections_are_reported_with_location_and_fix(): void
    {
        MenuItem::create(['menu' => 'footer', 'type' => 'url', 'target' => 'https://portfolio.example/gone-page', 'label' => 'Gone', 'is_enabled' => true]);
        Page::create(['title' => 'Live page', 'slug' => 'live-page', 'body' => 'Hi', 'status' => 'published']);

        $report = app(SelfTest::class)->run();

        $broken = $this->check($report, 'Broken page', '/gone-page');
        $this->assertSame('fail', $broken['status']);
        $this->assertStringContainsString('404', $broken['message']);
        $this->assertNotEmpty($broken['fix']);
        $this->assertNull($this->check($report, 'Broken page', '/live-page'), 'published pages that load are fine');
    }

    public function test_failures_raise_a_notification_that_clears_when_fixed(): void
    {
        MenuItem::create(['menu' => 'footer', 'type' => 'url', 'target' => 'https://portfolio.example/gone-page', 'label' => 'Gone', 'is_enabled' => true]);
        app(SelfTest::class)->run();
        $this->assertTrue(PlatformNotice::where('title', 'Self-test found problems')->whereNull('resolved_at')->exists());

        // A server where every Health check passes, so the only failure was the broken link.
        $this->app->instance(HealthMonitor::class, new class(app(Notices::class), app(BackupManager::class)) extends HealthMonitor
        {
            public function local(): array
            {
                return ['Server' => [['label' => 'Everything', 'state' => 'ok', 'detail' => 'Fine.']]];
            }
        });
        MenuItem::query()->delete();
        $report = app(SelfTest::class)->run();
        $this->assertSame([], collect($report['results'])->where('status', 'fail')->pluck('check')->all());
        $this->assertFalse(PlatformNotice::where('title', 'Self-test found problems')->whereNull('resolved_at')->exists(), 'clears once a run passes');
    }

    public function test_without_an_origin_the_crawl_is_skipped_not_faked(): void
    {
        config(['platform.seo_origin' => null]);
        $report = app(SelfTest::class)->run();

        $this->assertSame('skip', $this->check($report, 'Crawl')['status']);
        $this->assertStringContainsString('SEO_MONITOR_ORIGIN', $this->check($report, 'Crawl')['fix']);
        Http::assertNothingSent();
    }

    public function test_page_command_and_permissions(): void
    {
        $this->get('/admin/self-test')->assertRedirect('/admin/login');
        $this->actingAs($this->owner)->get('/admin/self-test')->assertOk()->assertSee('No self-test has run yet');

        $this->actingAs($this->owner)->post('/admin/self-test/run')->assertRedirect('/admin/self-test')->assertSessionHas('status');
        $this->actingAs($this->owner)->get('/admin/self-test?show=all')->assertOk()->assertSee('Home page')->assertSee('Not tested');
        $this->assertDatabaseHas('audit_logs', ['action' => 'self-test.ran']);

        $this->artisan('portfolio:self-test')->expectsOutputToContain('passed');

        $this->flushSession();
        $this->actingAs(User::factory()->create(['is_admin' => true, 'role' => 'editor']))->get('/admin/self-test')->assertForbidden();
    }
}
