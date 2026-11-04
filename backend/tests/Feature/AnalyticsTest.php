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

    public function test_unconfigured_search_console_fails_explicitly_without_affecting_pageviews(): void
    {
        config(['services.google_search_console.client_id' => null, 'services.google_search_console.client_secret' => null]);
        $this->actingAs(User::factory()->create(['is_admin' => true]));
        $this->post('/admin/analytics/google/connect', ['site_url' => 'https://portfolio.example/'])->assertStatus(503);
        $this->get('/admin/analytics')->assertOk()->assertSee('Optional');
        Http::assertNothingSent();
    }

    public function test_invalid_callback_state_cannot_create_a_connection(): void
    {
        $this->actingAs(User::factory()->create(['is_admin' => true]))
            ->withSession(['google_search_console_state' => 'expected-state', 'google_search_console_site' => 'https://portfolio.example/'])
            ->get('/admin/analytics/google/callback?state=wrong&code=example')
            ->assertSessionHasErrors('google');
        $this->assertSame(0, GoogleSearchConsoleConnection::count());
        Http::assertNothingSent();
    }

    public function test_unavailable_property_is_rejected_without_storing_tokens(): void
    {
        config(['services.google_search_console.client_id' => 'fixture', 'services.google_search_console.client_secret' => 'fixture']);
        Http::fake([
            'oauth2.googleapis.com/token' => Http::response(['access_token' => 'fixture-access', 'refresh_token' => 'fixture-refresh']),
            'searchconsole.googleapis.com/*' => Http::response(['siteEntry' => [['siteUrl' => 'https://different.example/']]]),
        ]);
        $this->actingAs(User::factory()->create(['is_admin' => true]))
            ->withSession(['google_search_console_state' => 'expected-state', 'google_search_console_site' => 'https://portfolio.example/'])
            ->get('/admin/analytics/google/callback?state=expected-state&code=example')
            ->assertRedirect(route('admin.analytics'))->assertSessionHasErrors('google');
        $this->assertSame(0, GoogleSearchConsoleConnection::count());
    }

    public function test_disconnect_removes_stored_authorization_but_retains_first_party_data(): void
    {
        GoogleSearchConsoleConnection::create(['site_url' => 'https://portfolio.example/', 'refresh_token' => 'fixture-refresh', 'connected_at' => now()]);
        PageView::create(['path' => '/', 'referrer_host' => null]);
        $this->actingAs(User::factory()->create(['is_admin' => true]))->delete('/admin/analytics/google')
            ->assertRedirect(route('admin.analytics'))->assertSessionHas('status');
        $this->assertSame(0, GoogleSearchConsoleConnection::count());
        $this->assertSame(1, PageView::count());
        Http::assertNothingSent();
    }
}
