<?php

namespace Tests\Feature;

use App\Models\GoogleSearchConsoleConnection;
use App\Models\PageView;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AnalyticsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        Http::preventStrayRequests();
    }

    public function test_first_party_analytics_records_only_successful_public_pages_and_external_hosts(): void
    {
        $project = Project::firstOrFail();
        $project->update(['is_published' => true]);

        $this->withHeader('Referer', 'https://www.google.com/search?q=private-query')->get('/?campaign=summer')->assertOk();
        $this->withHeader('Referer', 'http://localhost/another-page')->get('/projects/'.$project->slug)->assertOk();
        $this->get('/admin')->assertRedirect('/admin/login');
        $this->get('/styles.css')->assertOk();
        $this->get('/projects/missing-project')->assertNotFound();

        $this->assertSame(2, PageView::count());
        $this->assertDatabaseHas('page_views', ['path' => '/', 'referrer_host' => 'google.com']);
        $this->assertDatabaseHas('page_views', ['path' => '/projects/'.$project->slug, 'referrer_host' => null]);
        $this->assertDatabaseMissing('page_views', ['path' => '/?campaign=summer']);
    }

    public function test_analytics_dashboard_is_admin_only_and_reports_first_party_pageviews(): void
    {
        PageView::create(['path' => '/', 'referrer_host' => 'example.org']);
        $this->get('/admin/analytics')->assertRedirect('/admin/login');

        $admin = User::factory()->create(['is_admin' => true]);
        $this->actingAs($admin)->get('/admin/analytics')
            ->assertOk()
            ->assertSee('Traffic, without guesswork.')
            ->assertSee('Pageviews / 30 days')
            ->assertSee('1', false)
            ->assertSee('example.org')
            ->assertSee('Google Search Console')
            ->assertSee('no search performance data is shown');
    }

    public function test_search_console_oauth_uses_read_only_scope_and_reports_real_separate_metrics(): void
    {
        config([
            'services.google_search_console.client_id' => 'client-id',
            'services.google_search_console.client_secret' => 'client-secret',
            'services.google_search_console.redirect_uri' => 'https://portfolio.example/admin/analytics/google/callback',
        ]);
        Http::fake([
            'oauth2.googleapis.com/token' => Http::sequence()
                ->push(['access_token' => 'initial-access-token', 'refresh_token' => 'refresh-token', 'expires_in' => 3600])
                ->push(['access_token' => 'report-access-token', 'expires_in' => 3600]),
            '*' => function ($request) {
                if (str_ends_with($request->url(), '/webmasters/v3/sites')) {
                    return Http::response(['siteEntry' => [['siteUrl' => 'https://portfolio.example/']]]);
                }
                if (str_contains($request->url(), '/searchAnalytics/query')) {
                    return Http::response(['rows' => [
                        ['keys' => ['2026-10-01'], 'clicks' => 3, 'impressions' => 10, 'ctr' => 0.3, 'position' => 3],
                        ['keys' => ['2026-10-02'], 'clicks' => 7, 'impressions' => 30, 'ctr' => 0.2333, 'position' => 7],
                    ]]);
                }

                return Http::response([], 404);
            },
        ]);

        $admin = User::factory()->create(['is_admin' => true]);
        $connectResponse = $this->actingAs($admin)->post('/admin/analytics/google/connect', [
            'site_url' => 'https://portfolio.example/',
        ])->assertRedirect();

        $state = session('google_search_console_state');
        $this->assertNotNull($state);
        $this->assertStringContainsString('webmasters.readonly', urldecode((string) $connectResponse->headers->get('Location')));

        $this->get('/admin/analytics/google/callback?'.http_build_query([
            'code' => 'auth-code',
            'state' => $state,
        ]))->assertRedirect(route('admin.analytics'))
            ->assertSessionHas('status');

        $connection = GoogleSearchConsoleConnection::firstOrFail();
        $this->assertSame('refresh-token', $connection->refresh_token);
        $this->assertNotSame('refresh-token', $connection->getRawOriginal('refresh_token'));

        $this->get('/admin/analytics')->assertOk()
            ->assertSee('Connected')
            ->assertSee('Property: <strong>https://portfolio.example/</strong>', false)
            ->assertSee('10', false)
            ->assertSee('40', false)
            ->assertSee('6.0', false);
    }
}
