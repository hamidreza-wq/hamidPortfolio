<?php

namespace App\Services;

use Illuminate\Support\Str;

/**
 * Dashboard help articles. Each one is a Markdown file in resources/help,
 * kept in the codebase so the help always matches the release it ships with.
 * Raw HTML in the files is escaped and unsafe links are dropped.
 */
class HelpCentre
{
    /** Raw HTML is shown as text and javascript:/data: links are dropped. */
    public const MARKDOWN = ['html_input' => 'escape', 'allow_unsafe_links' => false, 'max_nesting_level' => 20];

    /** slug => [title, one-line summary, group, admin route names it explains] */
    public const TOPICS = [
        'getting-started' => ['Getting started', 'A tour of the dashboard and the first things to set up.', 'Start here', ['admin.dashboard']],
        'search' => ['Searching the dashboard', 'Find any page, project, image or message with Ctrl K.', 'Start here', ['admin.search']],
        'live-editor' => ['Live site editor', 'Change text, section order, colours, fonts and search details with a live preview.', 'Your site', ['admin.site-editor.edit']],
        'pages' => ['Pages', 'Write standalone pages, schedule them, and add them to a menu.', 'Your site', ['admin.pages.index', 'admin.pages.create', 'admin.pages.edit', 'admin.pages.preview']],
        'projects' => ['Projects', 'Add, edit, publish and order the work shown on your portfolio.', 'Your site', ['admin.projects.index']],
        'sections' => ['Sections', 'Add experience, education, services, testimonials or custom sections.', 'Your site', ['admin.sections.index']],
        'menus' => ['Menus', 'Choose the links in the header and footer.', 'Your site', ['admin.menus.index']],
        'themes' => ['Themes', 'Change the whole look at once, preview it, and undo it.', 'Your site', ['admin.themes.index']],
        'content-history' => ['Content history', 'See earlier versions and restore one.', 'Your site', ['admin.revisions.index']],
        'media' => ['Media library', 'Upload, describe, find and reuse images.', 'Library', ['admin.media.index']],
        'fonts' => ['Fonts', 'Upload licensed fonts to use for headings or body text.', 'Library', ['admin.fonts.index']],
        'inbox' => ['Contact inbox', 'Read, sort and note messages from the contact form.', 'People', ['admin.inbox.index']],
        'testimonials' => ['Testimonials', 'Collect, review and publish what clients say.', 'People', ['admin.testimonials.index']],
        'social-links' => ['Social links', 'List your profiles and choose where each appears.', 'People', ['admin.social.index']],
        'analytics' => ['Analytics', 'Visits, popular pages and Google Search Console results.', 'Insight', ['admin.analytics']],
        'seo' => ['SEO monitoring', 'Technical checks that help search engines read your site.', 'Insight', ['admin.platform.seo']],
        'health' => ['Health', 'Whether the site, server and background jobs are working.', 'Running the site', ['admin.health']],
        'self-test' => ['Self-test', 'Check every page, link, form and upload still works, with fixes for anything broken.', 'Running the site', ['admin.self-test']],
        'backups' => ['Backups', 'Create, check, download and restore backups.', 'Running the site', ['admin.backups.index']],
        'updates' => ['Software updates', 'Check for and install signed releases safely.', 'Running the site', ['admin.platform.updates']],
        'github-projects' => ['GitHub projects', 'Review public repositories found on your GitHub account.', 'Running the site', ['admin.platform.github']],
        'notifications' => ['Notifications', 'Alerts about updates, health, backups and SEO, and email digests.', 'Running the site', ['admin.platform.notifications']],
        'security' => ['Security', 'Two-factor sign-in, sessions, protection checks and the activity log.', 'Protection', ['admin.security']],
        'users' => ['Users and roles', 'Give people access with the right role, and remove it.', 'Protection', ['admin.users.index']],
        'privacy' => ['Privacy', 'Personal data, retention, and answering data requests.', 'Protection', ['admin.privacy']],
    ];

    public function exists(string $slug): bool
    {
        return isset(self::TOPICS[$slug]) && is_file($this->path($slug));
    }

    public function title(string $slug): string
    {
        return self::TOPICS[$slug][0];
    }

    public function summary(string $slug): string
    {
        return self::TOPICS[$slug][1];
    }

    /** @return array<string, list<string>> group => slugs */
    public function groups(): array
    {
        $groups = [];
        foreach (self::TOPICS as $slug => [, , $group]) {
            $groups[$group][] = $slug;
        }

        return $groups;
    }

    public function topicForRoute(?string $route): ?string
    {
        foreach (self::TOPICS as $slug => [, , , $routes]) {
            if (in_array($route, $routes, true)) {
                return $slug;
            }
        }

        return null;
    }

    /** @return array{html: string, headings: list<array{id: string, text: string}>} */
    public function render(string $slug): array
    {
        $html = Str::markdown((string) file_get_contents($this->path($slug)), self::MARKDOWN);
        $headings = [];
        $html = preg_replace_callback('~<h2>(.*?)</h2>~s', function ($match) use (&$headings) {
            $text = html_entity_decode(strip_tags($match[1]), ENT_QUOTES);
            $id = Str::slug($text) ?: 'section-'.(count($headings) + 1);
            $headings[] = ['id' => $id, 'text' => $text];

            return '<h2 id="'.e($id).'">'.$match[1].'</h2>';
        }, $html);

        return ['html' => $html, 'headings' => $headings];
    }

    /** @return list<array{slug: string, title: string, excerpt: string}> */
    public function search(string $query, int $limit = 8): array
    {
        $needle = Str::lower(trim($query));
        if (mb_strlen($needle) < 2) {
            return [];
        }
        $results = [];
        foreach (array_keys(self::TOPICS) as $slug) {
            if (! $this->exists($slug)) {
                continue;
            }
            $text = $this->plainText($slug);
            $inTitle = str_contains(Str::lower($this->title($slug).' '.$this->summary($slug)), $needle);
            $at = mb_stripos($text, $needle);
            if (! $inTitle && $at === false) {
                continue;
            }
            $excerpt = $at === false ? $this->summary($slug)
                : ($at > 40 ? '…' : '').Str::limit(mb_substr($text, max(0, $at - 40)), 140);
            $results[] = ['slug' => $slug, 'title' => $this->title($slug), 'excerpt' => $excerpt, 'score' => $inTitle ? 0 : 1];
        }
        usort($results, fn ($a, $b) => $a['score'] <=> $b['score']);

        return array_map(fn ($r) => array_diff_key($r, ['score' => 1]), array_slice($results, 0, $limit));
    }

    private function plainText(string $slug): string
    {
        $markdown = (string) file_get_contents($this->path($slug));

        return trim(preg_replace('/\s+/', ' ', preg_replace(['/[#>*_`\[\]|]+/', '/\((?:https?:\/\/|\/)[^)]*\)/'], ' ', $markdown)));
    }

    private function path(string $slug): string
    {
        return resource_path('help/'.$slug.'.md');
    }
}
