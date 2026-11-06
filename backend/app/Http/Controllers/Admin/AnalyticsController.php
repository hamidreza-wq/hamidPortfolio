<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\GoogleSearchConsoleConnection;
use App\Models\PageView;
use App\Services\Analytics\GoogleSearchConsole;
use App\Services\Platform\IntegrationFailure;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AnalyticsController extends Controller
{
    public function index(GoogleSearchConsole $searchConsole)
    {
        $start = now()->subDays(29)->startOfDay();
        $measurementId = config('services.google_analytics.measurement_id');
        $googleAnalyticsConfigured = app()->environment('production')
            && is_string($measurementId)
            && preg_match('/\AG-[A-Z0-9]+\z/', $measurementId) === 1;
        $dailyCounts = PageView::query()->where('viewed_at', '>=', $start)
            ->selectRaw('DATE(viewed_at) as day, COUNT(*) as views')
            ->groupBy('day')->pluck('views', 'day');
        $dailyViews = collect(range(0, 29))->map(function (int $offset) use ($start, $dailyCounts): array {
            $day = $start->copy()->addDays($offset);

            return ['date' => $day->toDateString(), 'label' => $day->format('M j'), 'views' => (int) ($dailyCounts[$day->toDateString()] ?? 0)];
        });

        $connection = GoogleSearchConsoleConnection::query()->first();
        $searchReport = null;
        $searchError = null;
        $searchDateEnd = now()->subDays(3)->toDateString();
        $searchDateStart = Carbon::parse($searchDateEnd)->subDays(27)->toDateString();

        if ($connection) {
            try {
                $searchReport = $searchConsole->report($connection, $searchDateStart, $searchDateEnd);
            } catch (IntegrationFailure $exception) {
                $searchError = $exception->getMessage();
            }
        }

        return view('admin.analytics', [
            'googleAnalyticsConfigured' => $googleAnalyticsConfigured,
            'googleAnalyticsMeasurementId' => $googleAnalyticsConfigured ? $measurementId : null,
            'pageViews' => PageView::query()->where('viewed_at', '>=', $start)->count(),
            'recentViews' => PageView::query()->where('viewed_at', '>=', now()->subDays(6)->startOfDay())->count(),
            'topPages' => PageView::query()->where('viewed_at', '>=', $start)
                ->selectRaw('path, COUNT(*) as views')->groupBy('path')->orderByDesc('views')->limit(8)->get(),
            'referrers' => PageView::query()->where('viewed_at', '>=', $start)->whereNotNull('referrer_host')
                ->selectRaw('referrer_host, COUNT(*) as views')->groupBy('referrer_host')->orderByDesc('views')->limit(8)->get(),
            'dailyViews' => $dailyViews,
            'chartMaximum' => max(1, (int) $dailyViews->max('views')),
            'connection' => $connection,
            'searchConsoleConfigured' => $this->searchConsoleConfigured(),
            'searchReport' => $searchReport,
            'searchError' => $searchError,
            'searchDateStart' => $searchDateStart,
            'searchDateEnd' => $searchDateEnd,
        ]);
    }

    public function connect(Request $request, GoogleSearchConsole $searchConsole)
    {
        abort_unless($this->searchConsoleConfigured(), 503, 'Google Search Console OAuth is not configured.');

        $siteUrl = $request->validate([
            'site_url' => ['required', 'string', 'max:255', function (string $attribute, mixed $value, \Closure $fail): void {
                $domainProperty = is_string($value) && preg_match('/^sc-domain:[a-z0-9.-]+$/i', $value);
                $urlProperty = is_string($value)
                    && filter_var($value, FILTER_VALIDATE_URL)
                    && in_array(strtolower((string) parse_url($value, PHP_URL_SCHEME)), ['http', 'https'], true)
                    && is_string(parse_url($value, PHP_URL_HOST));
                if (! $domainProperty && ! $urlProperty) {
                    $fail('Enter a Search Console URL-prefix property or sc-domain:example.com property.');
                }
            }],
        ])['site_url'];

        $state = Str::random(64);
        $request->session()->put([
            'google_search_console_state' => $state,
            'google_search_console_site' => $siteUrl,
        ]);

        return redirect()->away($searchConsole->authorizationUrl($this->redirectUri(), $state));
    }

    public function callback(Request $request, GoogleSearchConsole $searchConsole)
    {
        $expectedState = $request->session()->pull('google_search_console_state');
        $siteUrl = $request->session()->pull('google_search_console_site');
        if (! is_string($expectedState) || ! is_string($siteUrl)
            || ! hash_equals($expectedState, (string) $request->query('state'))) {
            throw ValidationException::withMessages(['google' => 'The Google authorization response was invalid or expired. Restart the connection process.']);
        }

        if ($request->query('error')) {
            return redirect()->route('admin.analytics')->withErrors(['google' => 'Google Search Console authorization was not granted.']);
        }

        $code = $request->query('code');
        if (! is_string($code) || $code === '') {
            throw ValidationException::withMessages(['google' => 'The Google authorization response did not include a code. Restart the connection process.']);
        }

        try {
            $tokens = $searchConsole->exchangeAuthorizationCode($code, $this->redirectUri());
            if (empty($tokens['refresh_token'])) {
                throw new IntegrationFailure('Google did not issue a refresh token. Revoke the existing grant in Google Account permissions and connect again.');
            }
            if (! $searchConsole->canAccessProperty($siteUrl, $tokens['access_token'])) {
                throw new IntegrationFailure('This Google account does not have access to the selected Search Console property.');
            }
        } catch (IntegrationFailure $exception) {
            return redirect()->route('admin.analytics')->withErrors(['google' => $exception->getMessage()]);
        }

        GoogleSearchConsoleConnection::query()->updateOrCreate([], [
            'site_url' => $siteUrl,
            'refresh_token' => $tokens['refresh_token'],
            'connected_at' => now(),
        ]);

        return redirect()->route('admin.analytics')->with('status', 'Google Search Console connected with read-only access. Its search metrics remain separate from first-party pageviews.');
    }

    public function disconnect(Request $request)
    {
        GoogleSearchConsoleConnection::query()->delete();

        return redirect()->route('admin.analytics')->with('status', 'Google Search Console disconnected. First-party pageview analytics remain available.');
    }

    private function searchConsoleConfigured(): bool
    {
        return filled(config('services.google_search_console.client_id'))
            && filled(config('services.google_search_console.client_secret'));
    }

    private function redirectUri(): string
    {
        return config('services.google_search_console.redirect_uri')
            ?: route('admin.analytics.google.callback');
    }
}
