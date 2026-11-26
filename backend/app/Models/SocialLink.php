<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class SocialLink extends Model
{
    /**
     * Platform => [display name, allowed hosts]. A null host list accepts any
     * HTTPS address (for self-hosted services and personal sites).
     */
    public const PLATFORMS = [
        'github' => ['GitHub', ['github.com']],
        'linkedin' => ['LinkedIn', ['linkedin.com']],
        'x' => ['X', ['x.com', 'twitter.com']],
        'bluesky' => ['Bluesky', ['bsky.app']],
        'mastodon' => ['Mastodon', null],
        'instagram' => ['Instagram', ['instagram.com']],
        'facebook' => ['Facebook', ['facebook.com']],
        'youtube' => ['YouTube', ['youtube.com', 'youtu.be']],
        'tiktok' => ['TikTok', ['tiktok.com']],
        'dribbble' => ['Dribbble', ['dribbble.com']],
        'behance' => ['Behance', ['behance.net']],
        'stackoverflow' => ['Stack Overflow', ['stackoverflow.com']],
        'medium' => ['Medium', ['medium.com']],
        'devto' => ['DEV', ['dev.to']],
        'website' => ['Website', null],
        'other' => ['Other', null],
    ];

    protected $fillable = [
        'platform', 'label', 'url', 'is_enabled', 'show_in_header', 'show_in_footer', 'show_in_contact', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_enabled' => 'boolean', 'show_in_header' => 'boolean', 'show_in_footer' => 'boolean',
            'show_in_contact' => 'boolean', 'sort_order' => 'integer',
        ];
    }

    public function scopeVisible(Builder $query): Builder
    {
        return $query->where('is_enabled', true)->orderBy('sort_order')->orderBy('id');
    }

    public function displayLabel(): string
    {
        return $this->label ?: (self::PLATFORMS[$this->platform][0] ?? 'Link');
    }

    /** True when the URL is HTTPS, has no credentials, and its host belongs to the platform. */
    public static function urlMatchesPlatform(string $url, string $platform): bool
    {
        $parts = parse_url($url);
        if (! is_array($parts) || strtolower($parts['scheme'] ?? '') !== 'https' || empty($parts['host'])
            || isset($parts['user']) || isset($parts['pass'])) {
            return false;
        }
        $hosts = self::PLATFORMS[$platform][1] ?? null;
        if ($hosts === null) {
            return true;
        }
        $host = strtolower($parts['host']);
        foreach ($hosts as $allowed) {
            if ($host === $allowed || str_ends_with($host, '.'.$allowed)) {
                return true;
            }
        }

        return false;
    }

    /** @return array{platform: string, label: string, url: string, header: bool, footer: bool, contact: bool} */
    public function toPayload(): array
    {
        return [
            'platform' => $this->platform, 'label' => $this->displayLabel(), 'url' => $this->url,
            'header' => $this->show_in_header, 'footer' => $this->show_in_footer, 'contact' => $this->show_in_contact,
        ];
    }
}
