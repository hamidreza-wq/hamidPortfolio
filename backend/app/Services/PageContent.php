<?php

namespace App\Services;

use Illuminate\Support\Str;

/**
 * Renders page Markdown safely. Raw HTML is shown as text, javascript:/data:
 * links are dropped, and images are only kept when they come from this site's
 * media library (/media/{id}); anything else is removed.
 */
class PageContent
{
    public const MARKDOWN = ['html_input' => 'escape', 'allow_unsafe_links' => false, 'max_nesting_level' => 20];

    public function render(string $markdown): string
    {
        $html = Str::markdown($markdown, self::MARKDOWN);

        $html = preg_replace_callback('~<img\b[^>]*>~i', function ($match) {
            if (! preg_match('~\ssrc="([^"]*)"~', $match[0], $src) || ! $this->isLibraryImage(html_entity_decode($src[1]))) {
                return '';
            }

            return str_replace('<img ', '<img loading="lazy" ', $match[0]);
        }, $html);

        // Links to other sites open safely in a new tab.
        return preg_replace_callback('~<a href="(https?://[^"]+)"~i', function ($match) {
            $host = parse_url(html_entity_decode($match[1]), PHP_URL_HOST);

            return $host && $host !== request()->getHost()
                ? '<a href="'.$match[1].'" target="_blank" rel="noopener noreferrer"'
                : $match[0];
        }, $html);
    }

    /** Media references used by a page body, for "this image is in use" checks. */
    public function mediaIds(string $markdown): array
    {
        preg_match_all('~/media/(\d+)~', $markdown, $matches);

        return array_values(array_unique(array_map('intval', $matches[1])));
    }

    private function isLibraryImage(string $src): bool
    {
        $path = parse_url($src, PHP_URL_PATH) ?? '';
        $host = parse_url($src, PHP_URL_HOST);

        return preg_match('~^/media/\d+$~', $path) && ($host === null || $host === request()->getHost());
    }
}
