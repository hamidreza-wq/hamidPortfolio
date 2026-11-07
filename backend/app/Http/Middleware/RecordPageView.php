<?php

namespace App\Http\Middleware;

use App\Models\PageView;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RecordPageView
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        $route = $request->route();

        if ($response->getStatusCode() !== 200
            || ! str_starts_with((string) $response->headers->get('Content-Type'), 'text/html')
            || ! $route) {
            return $response;
        }

        $path = match ($route->getName()) {
            'portfolio.home' => '/',
            'portfolio.projects.show' => '/projects/'.$route->parameter('slug'),
            default => null,
        };

        if ($path === null) {
            return $response;
        }

        PageView::create([
            'path' => $path,
            'referrer_host' => $this->externalReferrerHost($request),
        ]);

        return $response;
    }

    private function externalReferrerHost(Request $request): ?string
    {
        $referer = $request->headers->get('referer');
        if (! $referer) {
            return null;
        }

        $scheme = parse_url($referer, PHP_URL_SCHEME);
        $host = parse_url($referer, PHP_URL_HOST);
        if (! in_array(strtolower((string) $scheme), ['http', 'https'], true) || ! is_string($host)) {
            return null;
        }

        $host = strtolower(rtrim($host, '.'));
        $requestHost = strtolower(rtrim($request->getHost(), '.'));
        if (str_starts_with($host, 'www.')) {
            $host = substr($host, 4);
        }
        if (str_starts_with($requestHost, 'www.')) {
            $requestHost = substr($requestHost, 4);
        }

        return $host === $requestHost ? null : substr($host, 0, 255);
    }
}
