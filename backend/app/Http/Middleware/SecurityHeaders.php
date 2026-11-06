<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        $nonce = base64_encode(random_bytes(24));
        if (str_contains((string) $response->headers->get('Content-Type'), 'text/html')) {
            $content = $response->getContent();
            if (is_string($content)) {
                $response->setContent(preg_replace('/<(script|style)\b(?![^>]*\bnonce=)/i', '<$1 nonce="'.$nonce.'"', $content));
            }
            $response->headers->set('Cache-Control', 'private, no-store');
        }

        $response->headers->set('Content-Security-Policy', "default-src 'self'; script-src 'self' 'nonce-{$nonce}' https://www.googletagmanager.com; style-src 'self' 'nonce-{$nonce}'; style-src-attr 'unsafe-inline'; img-src 'self' data: https://*.google-analytics.com; font-src 'self'; connect-src 'self' https://*.google-analytics.com https://analytics.google.com; frame-src 'self'; frame-ancestors 'self'; base-uri 'self'; form-action 'self'; object-src 'none'");
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');
        if (app()->isProduction() && $request->isSecure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000');
        }

        return $response;
    }
}
