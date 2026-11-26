<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MenuItem extends Model
{
    public const MENUS = ['header' => 'Header menu', 'footer' => 'Footer menu'];

    public const TYPES = ['section' => 'A section of the home page', 'project' => 'A project page', 'custom_page' => 'One of your pages', 'page' => 'A site page', 'url' => 'Another website or email address'];

    /** Site pages that can be linked, with their default labels. */
    public const PAGES = ['home' => 'Home (top of the page)', 'privacy' => 'Privacy notice', 'testimonials' => 'Testimonial form'];

    protected $fillable = ['menu', 'type', 'target', 'label', 'open_in_new_tab', 'is_button', 'is_enabled', 'sort_order'];

    protected function casts(): array
    {
        return ['open_in_new_tab' => 'boolean', 'is_button' => 'boolean', 'is_enabled' => 'boolean', 'sort_order' => 'integer'];
    }

    /** https:// addresses, or mailto: with a plausible email address. */
    public static function isAllowedUrl(string $url): bool
    {
        if (preg_match('/^mailto:([^?\s]+)$/i', $url, $match)) {
            return filter_var(rawurldecode($match[1]), FILTER_VALIDATE_EMAIL) !== false;
        }
        $parts = parse_url($url);

        return is_array($parts) && strtolower($parts['scheme'] ?? '') === 'https' && ! empty($parts['host'])
            && ! isset($parts['user']) && ! isset($parts['pass'])
            && filter_var($url, FILTER_VALIDATE_URL) !== false;
    }
}
