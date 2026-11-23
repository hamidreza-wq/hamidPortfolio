<?php

namespace App\Services\Analytics;

use App\Models\GoogleSearchConsoleConnection;
use App\Services\Platform\IntegrationFailure;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GoogleSearchConsole
{
    private const TOKEN_URL = 'https://oauth2.googleapis.com/token';

    public function authorizationUrl(string $redirectUri, string $state): string
    {
        return 'https://accounts.google.com/o/oauth2/v2/auth?'.http_build_query([
            'client_id' => config('services.google_search_console.client_id'),
            'redirect_uri' => $redirectUri,
            'response_type' => 'code',
            'scope' => 'https://www.googleapis.com/auth/webmasters.readonly',
            'access_type' => 'offline',
            'prompt' => 'consent',
            'state' => $state,
        ], '', '&', PHP_QUERY_RFC3986);
    }

    public function exchangeAuthorizationCode(string $code, string $redirectUri): array
    {
        return $this->tokenRequest([
            'client_id' => config('services.google_search_console.client_id'),
            'client_secret' => config('services.google_search_console.client_secret'),
            'code' => $code,
            'grant_type' => 'authorization_code',
            'redirect_uri' => $redirectUri,
        ], 'Google did not complete the Search Console authorization.');
    }

    public function canAccessProperty(string $siteUrl, string $accessToken): bool
    {
        try {
            $response = Http::timeout(10)->withToken($accessToken)
                ->get('https://searchconsole.googleapis.com/webmasters/v3/sites');
        } catch (ConnectionException) {
            throw new IntegrationFailure('Google Search Console could not be reached.');
        }

        if (! $response->successful()) {
            throw new IntegrationFailure('Google Search Console could not verify the selected property.');
        }

        $sites = $response->json('siteEntry');

        return is_array($sites) && collect($sites)->contains(
            fn ($site) => is_array($site) && ($site['siteUrl'] ?? null) === $siteUrl
        );
    }

    public function report(GoogleSearchConsoleConnection $connection, string $startDate, string $endDate): array
    {
        $accessToken = $this->refreshAccessToken($connection->refresh_token);
        $url = 'https://searchconsole.googleapis.com/webmasters/v3/sites/'.rawurlencode($connection->site_url).'/searchAnalytics/query';

        try {
            $response = Http::timeout(15)->withToken($accessToken)->post($url, [
                'startDate' => $startDate,
                'endDate' => $endDate,
                'dimensions' => ['date'],
                'rowLimit' => 28,
            ]);
        } catch (ConnectionException) {
            throw new IntegrationFailure('Google Search Console report could not be reached.');
        }

        if (! $response->successful() || ! is_array($response->json())) {
            throw new IntegrationFailure('Google Search Console did not return a usable search report.');
        }

        $rows = collect($response->json('rows', []))
            ->filter(fn ($row) => is_array($row) && isset($row['keys'][0]))
            ->mapWithKeys(fn ($row) => [
                $row['keys'][0] => [
                    'clicks' => (int) ($row['clicks'] ?? 0),
                    'impressions' => (int) ($row['impressions'] ?? 0),
                    'ctr' => (float) ($row['ctr'] ?? 0),
                    'position' => (float) ($row['position'] ?? 0),
                ],
            ])->all();

        $impressions = array_sum(array_column($rows, 'impressions'));
        $weightedPosition = $impressions
            ? array_sum(array_map(fn ($row) => $row['position'] * $row['impressions'], $rows)) / $impressions
            : 0;

        return [
            'rows' => $rows,
            'clicks' => array_sum(array_column($rows, 'clicks')),
            'impressions' => $impressions,
            'average_position' => $weightedPosition,
        ];
    }

    private function refreshAccessToken(string $refreshToken): string
    {
        $tokens = $this->tokenRequest([
            'client_id' => config('services.google_search_console.client_id'),
            'client_secret' => config('services.google_search_console.client_secret'),
            'refresh_token' => $refreshToken,
            'grant_type' => 'refresh_token',
        ], 'Google Search Console could not refresh its read-only authorization.');

        return $tokens['access_token'];
    }

    private function tokenRequest(array $parameters, string $failureMessage): array
    {
        try {
            $response = Http::asForm()->timeout(10)->post(self::TOKEN_URL, $parameters);
        } catch (ConnectionException) {
            throw new IntegrationFailure('Google OAuth could not be reached.');
        }

        $tokens = $response->json();
        if ($response->successful() && is_array($tokens) && ! empty($tokens['access_token'])) {
            return $tokens;
        }

        // Google's OAuth error code says why the request failed; it never contains token material.
        $error = is_array($tokens) && is_string($tokens['error'] ?? null) ? $tokens['error'] : null;
        Log::warning('Google Search Console token request failed.', [
            'grant_type' => $parameters['grant_type'] ?? null,
            'status' => $response->status(),
            'error' => $error,
            'error_description' => is_array($tokens) && is_string($tokens['error_description'] ?? null)
                ? mb_substr($tokens['error_description'], 0, 200) : null,
        ]);

        if (($parameters['grant_type'] ?? null) === 'refresh_token') {
            if ($error === 'invalid_grant') {
                throw new SearchConsoleReauthorizationRequired(
                    'Google Search Console authorization has expired or was revoked. Reconnect to restore search data. '
                    .'If this keeps happening about a week after connecting, publish the Google OAuth consent screen to "In production" — apps left in "Testing" have their refresh tokens expire after 7 days.'
                );
            }
            if (in_array($error, ['invalid_client', 'unauthorized_client'], true)) {
                throw new SearchConsoleReauthorizationRequired(
                    'Google rejected the Search Console OAuth client. Check GOOGLE_SEARCH_CONSOLE_CLIENT_ID and GOOGLE_SEARCH_CONSOLE_CLIENT_SECRET on the server match the client that was used to connect, then reconnect.'
                );
            }
        }

        throw new IntegrationFailure($error ? "{$failureMessage} (Google: {$error})" : $failureMessage);
    }
}
