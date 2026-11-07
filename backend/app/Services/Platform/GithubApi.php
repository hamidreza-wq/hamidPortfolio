<?php

namespace App\Services\Platform;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

class GithubApi
{
    public function json(string $path, array $query = [], bool $allowMissing = false): ?array
    {
        if (! preg_match('~^/(?:users/hamidreza-wq(?:/repos)?|repos/hamidreza-wq/hamidPortfolio/releases/latest)$~D', $path)) {
            throw new IntegrationFailure('GitHub API path is not allowlisted.');
        }
        $response = $this->request('https://api.github.com'.$path, 2_000_000, $query);
        if ($allowMissing && $response->status() === 404) {
            return null;
        }
        $this->successful($response);
        $data = $response->json();
        if (! is_array($data)) {
            throw new IntegrationFailure('GitHub returned malformed JSON.');
        }

        return $data;
    }

    public function asset(array $asset, int $limit): string
    {
        $id = $asset['id'] ?? null;
        if (! is_int($id) || $id < 1 || ($asset['size'] ?? PHP_INT_MAX) > $limit) {
            throw new IntegrationFailure('Release asset ID or size is invalid.');
        }
        $response = $this->request('https://api.github.com/repos/hamidreza-wq/hamidPortfolio/releases/assets/'.$id, $limit, [], true);
        if (in_array($response->status(), [301, 302, 307], true)) {
            $url = $response->header('Location');
            $parts = parse_url($url);
            if (! is_array($parts) || ($parts['scheme'] ?? '') !== 'https'
                || ! in_array($parts['host'] ?? '', ['release-assets.githubusercontent.com', 'objects.githubusercontent.com'], true)
                || isset($parts['user']) || isset($parts['pass']) || isset($parts['port']) || isset($parts['fragment'])) {
                throw new IntegrationFailure('Release asset redirect is not trusted.');
            }
            $response = $this->request($url, $limit);
        }
        $this->successful($response);

        return $response->body();
    }

    public function request(string $url, int $limit, array $query = [], bool $binary = false, array $extraOptions = []): Response
    {
        try {
            $response = Http::accept($binary ? 'application/octet-stream' : 'application/vnd.github+json')
                ->withHeaders(['User-Agent' => 'HamidPortfolio-Monitor', 'X-GitHub-Api-Version' => '2022-11-28'])
                ->connectTimeout(5)->timeout(40)->withoutRedirecting()
                ->withOptions([
                    ...$extraOptions,
                    'on_headers' => function ($response) use ($limit): void {
                        if ((int) $response->getHeaderLine('Content-Length') > $limit) {
                            throw new IntegrationFailure('Remote response exceeds the permitted size.');
                        }
                    },
                    'progress' => function ($total, $downloaded) use ($limit): void {
                        if ($downloaded > $limit || $total > $limit) {
                            throw new IntegrationFailure('Remote response exceeds the permitted size.');
                        }
                    },
                ])->send('GET', $url, $query === [] ? [] : ['query' => $query]);
        } catch (ConnectionException) {
            throw new IntegrationFailure('Remote connection failed or exceeded its time/size limit. Try again later.');
        }
        if (strlen($response->body()) > $limit) {
            throw new IntegrationFailure('Remote response exceeds the permitted size.');
        }

        return $response;
    }

    private function successful(Response $response): void
    {
        if (in_array($response->status(), [403, 429], true)) {
            throw new IntegrationFailure('GitHub denied the request or rate limited this server. No import was completed; retry after the rate limit resets.');
        }
        if (! $response->successful()) {
            throw new IntegrationFailure('GitHub request failed (HTTP '.$response->status().').');
        }
    }
}
