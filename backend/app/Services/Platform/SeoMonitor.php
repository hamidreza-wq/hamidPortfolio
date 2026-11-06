<?php

namespace App\Services\Platform;

use App\Models\PortfolioSetting;
use App\Models\Project;
use App\Models\SeoFinding;
use DOMDocument;
use DOMXPath;

class SeoMonitor
{
    public function scan(): array
    {
        $origin = rtrim((string) config('platform.seo_origin'), '/');
        $parts = parse_url($origin);
        if (! is_array($parts) || ($parts['scheme'] ?? '') !== 'https'
            || ! preg_match('/^(?=.{1,253}$)(?:[a-z0-9](?:[a-z0-9-]*[a-z0-9])?\.)+[a-z]{2,63}$/Di', $parts['host'] ?? '')
            || isset($parts['port']) || isset($parts['user']) || isset($parts['pass'])
            || isset($parts['query']) || isset($parts['fragment']) || ! empty($parts['path'])) {
            throw new IntegrationFailure('SEO_MONITOR_ORIGIN must be a server-configured HTTPS public hostname without path, credentials or port.');
        }
        $settings = PortfolioSetting::findOrFail(1);
        if (rtrim($settings->canonical_url ?: config('app.url'), '/') !== $origin) {
            throw new IntegrationFailure('The configured SEO origin does not match the public canonical origin. No request was sent.');
        }
        if (! extension_loaded('curl') || ! class_exists(DOMDocument::class)) {
            throw new IntegrationFailure('SEO monitoring requires curl and DOM extensions.');
        }
        $ip = app(PublicDns::class)->ipv4($parts['host']);
        $projects = Project::where('is_published', true)->orderBy('id')->limit(51)->get();
        if ($projects->count() > 50) {
            throw new IntegrationFailure('This monitor supports up to 50 published project pages. Increase the reviewed scan budget before enabling more pages.');
        }
        foreach ($projects as $project) {
            if (! preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/D', $project->slug)) {
                throw new IntegrationFailure('A published project has an invalid public slug. No scan was performed; repair its URL in the project editor.');
            }
        }
        $paths = ['/', ...$projects->map(fn (Project $project) => '/projects/'.$project->slug)->all()];
        $seen = [];
        $titles = $descriptions = [];
        $findings = [];
        $start = microtime(true);
        foreach ([...$paths, '/robots.txt', '/sitemap.xml'] as $path) {
            if (microtime(true) - $start > 100) {
                throw new IntegrationFailure('SEO scan exceeded its time budget. Earlier findings were preserved; no false resolution was recorded.');
            }
            $time = microtime(true);
            $response = app(GithubApi::class)->request($origin.$path, 1_000_000, extraOptions: [
                'curl' => [CURLOPT_RESOLVE => [$parts['host'].':443:'.$ip]],
                'proxy' => '',
            ]);
            $seconds = microtime(true) - $time;
            if ($response->status() !== 200) {
                $this->add($findings, $path, 'http-status', 'high', 'Expected HTTP 200; received '.$response->status().'. Redirects are deliberately not followed.');

                continue;
            }
            $body = $response->body();
            if ($path === '/robots.txt') {
                if (! str_contains($body, 'Sitemap: '.$origin.'/sitemap.xml')) {
                    $this->add($findings, $path, 'robots-sitemap', 'medium', 'Add the configured canonical sitemap URL to robots.txt.');
                }
                if ($settings->indexing_enabled && preg_match('~Disallow:\s*/\s*(?:\r?\n|$)~i', $body)) {
                    $this->add($findings, $path, 'robots-blocked', 'high', 'robots.txt disallows the whole site while indexing is enabled.');
                }

                continue;
            }
            if ($path === '/sitemap.xml') {
                $dom = $this->document($body);
                $locations = [];
                foreach ($dom->getElementsByTagName('loc') as $loc) {
                    $locations[] = trim($loc->textContent);
                }
                $expected = $settings->indexing_enabled && ! $settings->maintenance_mode ? array_map(fn ($path) => $origin.$path, $paths) : [];
                if (array_diff($expected, $locations) || array_diff($locations, $expected)) {
                    $this->add($findings, $path, 'sitemap-content', 'high', 'Sitemap URLs do not match the current indexable published pages. Check draft exclusion, deleted pages and canonical origins.');
                }

                continue;
            }
            $dom = $this->document($body);
            $xpath = new DOMXPath($dom);
            $title = trim($xpath->evaluate('string(//title)'));
            $description = trim($xpath->evaluate('string(//meta[translate(@name,"ABCDEFGHIJKLMNOPQRSTUVWXYZ","abcdefghijklmnopqrstuvwxyz")="description"]/@content)'));
            $canonical = trim($xpath->evaluate('string(//link[@rel="canonical"]/@href)'));
            if ($title === '' || mb_strlen($title) > 80) {
                $this->add($findings, $path, 'title', 'medium', 'Write a clear page-specific title (avoid missing or excessively long titles).');
            }
            if ($description === '' || mb_strlen($description) > 320) {
                $this->add($findings, $path, 'description', 'medium', 'Write a useful, page-specific description; avoid missing or excessively long descriptions.');
            }
            foreach (['title' => $title, 'description' => $description] as $field => $value) {
                $map = $field === 'title' ? $titles : $descriptions;
                if ($value !== '' && isset($map[$value])) {
                    $this->add($findings, $path, 'duplicate-'.$field, 'medium', 'This '.$field.' duplicates '.$map[$value].'; use an accurate page-specific summary.');
                }
            }
            $titles[$title] = $path;
            $descriptions[$description] = $path;
            if ($canonical !== $origin.$path) {
                $this->add($findings, $path, 'canonical', 'high', 'Canonical URL must match '.$origin.$path.'.');
            }
            $robots = strtolower($xpath->evaluate('string(//meta[@name="robots"]/@content)').' '.$response->header('X-Robots-Tag'));
            if ($settings->indexing_enabled && ! $settings->maintenance_mode && str_contains($robots, 'noindex')) {
                $this->add($findings, $path, 'noindex', 'high', 'This published page unexpectedly disables indexing. Review site indexing settings and response headers.');
            }
            if ($xpath->query('//h1')->length !== 1) {
                $this->add($findings, $path, 'headings', 'medium', 'Use one descriptive primary h1 heading.');
            }
            if ($xpath->query('//meta[@property="og:title"]')->length === 0 || $xpath->query('//meta[@property="og:description"]')->length === 0) {
                $this->add($findings, $path, 'open-graph', 'medium', 'Add title and description Open Graph metadata.');
            }
            if ($xpath->query('//meta[@property="og:image" and string-length(@content)>0]')->length === 0) {
                $this->add($findings, $path, 'sharing-image', 'low', 'Select a relevant sharing image in the site or project editor.');
            }
            $validSchema = false;
            foreach ($xpath->query('//script[@type="application/ld+json"]') as $script) {
                $schema = json_decode($script->textContent, true);
                if (! is_array($schema) || ($schema['@context'] ?? '') !== 'https://schema.org') {
                    continue;
                }
                $nodes = $schema['@graph'] ?? [$schema];
                $validSchema = $validSchema || (is_array($nodes) && array_is_list($nodes) && $nodes !== []
                    && count(array_filter($nodes, function ($node) {
                        if (! is_array($node)) {
                            return false;
                        }
                        $types = $node['@type'] ?? null;
                        $types = is_string($types) ? [$types] : $types;

                        return is_array($types) && array_is_list($types) && $types !== []
                            && count(array_filter($types, fn ($type) => is_string($type) && trim($type) !== '')) === count($types);
                    })) === count($nodes));
            }
            if (! $validSchema) {
                $this->add($findings, $path, 'schema', 'medium', 'Add valid, accurate JSON-LD describing the visible page.');
            }
            if ($xpath->query('//meta[@name="viewport" and contains(@content,"width=device-width")]')->length === 0) {
                $this->add($findings, $path, 'viewport', 'medium', 'Add a device-width viewport; manually test mobile layouts too.');
            }
            foreach ($xpath->query('//img[not(@alt) or (@alt="" and not(ancestor::*[@aria-hidden="true"]))]') as $image) {
                $this->add($findings, $path, 'image-alt', 'medium', 'Review content images for meaningful alternative text; keep decorative images hidden from assistive technology.');
                break;
            }
            foreach ($xpath->query('//a[@href]') as $link) {
                $href = $link->getAttribute('href');
                $relative = str_starts_with($href, $origin.'/') ? substr($href, strlen($origin)) : $href;
                $target = explode('#', $relative, 2)[0];
                if (str_starts_with($target, '/projects/') && ! in_array($target, $paths, true)) {
                    $this->add($findings, $path, 'broken-project-link', 'medium', 'A link references an unpublished, missing or noncanonical project path. Review links in the editor.');
                }
            }
            $main = $xpath->query('//main')->item(0);
            if ($main === null || preg_match_all('/[\p{L}\p{N}]+/u', $main->textContent) < 60) {
                $this->add($findings, $path, 'thin-content', 'low', 'Consider adding useful original context about the work. Word count is a review hint, not a ranking target.');
            }
            if ($seconds > 2) {
                $this->add($findings, $path, 'response-time', 'low', 'This server-side response took over two seconds. Investigate hosting/cache performance; this is not a browser Core Web Vitals measurement.');
            }
        }
        foreach ($findings as $finding) {
            $fingerprint = hash('sha256', $finding['path'].'|'.$finding['check']);
            $seen[] = $fingerprint;
            SeoFinding::updateOrCreate(['fingerprint' => $fingerprint], [...$finding, 'last_seen_at' => now(), 'resolved_at' => null]);
            app(Notices::class)->put('seo:'.$fingerprint, 'seo', $finding['priority'],
                'SEO: '.$finding['check'], $finding['path'].' - '.$finding['message'], 'admin.platform.seo');
        }
        foreach (SeoFinding::whereNull('resolved_at')->whereNotIn('fingerprint', $seen)->get() as $resolved) {
            $resolved->update(['resolved_at' => now()]);
            app(Notices::class)->resolve('seo:'.$resolved->fingerprint);
        }

        return ['message' => 'Bounded public-site scan completed. No editorial content was changed.', 'pages' => count($paths), 'findings' => count($seen)];
    }

    private function add(array &$findings, string $path, string $check, string $priority, string $message): void
    {
        $findings[$path.'|'.$check] = compact('path', 'check', 'priority', 'message');
    }

    private function document(string $body): DOMDocument
    {
        $dom = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        try {
            $dom->loadHTML('<?xml encoding="UTF-8">'.$body, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }

        return $dom;
    }
}
