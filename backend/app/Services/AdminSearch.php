<?php

namespace App\Services;

use App\Models\ContactMessage;
use App\Models\FontAsset;
use App\Models\MediaAsset;
use App\Models\PortfolioSection;
use App\Models\PortfolioSetting;
use App\Models\Project;
use App\Models\Skill;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

/**
 * Searches the dashboard's own pages and the content administrators manage.
 * Every result links to the place where that item is edited.
 */
class AdminSearch
{
    public const TYPES = [
        'pages' => 'Dashboard pages',
        'projects' => 'Projects',
        'sections' => 'Sections',
        'settings' => 'Site settings',
        'skills' => 'Skills',
        'media' => 'Media',
        'fonts' => 'Fonts',
        'messages' => 'Contact messages',
    ];

    /**
     * Each page with the words people use for it, so "2fa" finds Security and
     * "colours" finds the live editor.
     */
    private const PAGES = [
        ['Overview', 'admin.dashboard', 'home dashboard start summary recent changes'],
        ['Analytics', 'admin.analytics', 'traffic visitors pageviews google analytics search console clicks impressions'],
        ['Live site editor', 'admin.site-editor.edit', 'appearance colours colors typography fonts theme preset profile links seo title description canonical open graph sharing image maintenance publish'],
        ['Projects', 'admin.projects.index', 'portfolio work repository demo case study'],
        ['Sections', 'admin.sections.index', 'experience education services testimonials custom navigation menu'],
        ['Content history', 'admin.revisions.index', 'revisions restore undo previous version history'],
        ['Media library', 'admin.media.index', 'images uploads pictures alt text gallery'],
        ['Fonts', 'admin.fonts.index', 'typography typeface woff upload licence license'],
        ['Contact inbox', 'admin.inbox.index', 'messages email enquiries contact form'],
        ['Software updates', 'admin.platform.updates', 'release version upgrade deploy laravel php dependencies'],
        ['GitHub projects', 'admin.platform.github', 'repositories discovery import review'],
        ['SEO monitoring', 'admin.platform.seo', 'search engine optimisation optimization sitemap robots scan findings'],
        ['Notifications', 'admin.platform.notifications', 'alerts warnings email digest preferences'],
        ['Security', 'admin.security', 'two-factor 2fa authenticator password sessions sign-in login failed attempts activity audit log'],
        ['Backups', 'admin.backups.index', 'backup restore recovery download archive'],
    ];

    /**
     * @return array<string, list<array{title: string, detail: string, url: string}>>
     */
    public function search(string $query, int $perType = 5, ?string $only = null): array
    {
        $query = trim(preg_replace('/\s+/', ' ', $query));
        if (mb_strlen($query) < 2) {
            return [];
        }
        $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $query).'%';
        $results = [];
        foreach (array_keys(self::TYPES) as $type) {
            if ($only !== null && $only !== $type) {
                continue;
            }
            $items = $this->{$type}($query, $like, $perType);
            if ($items) {
                $results[$type] = $items;
            }
        }

        return $results;
    }

    private function pages(string $query, string $like, int $limit): array
    {
        $needle = Str::lower($query);
        $matches = [];
        foreach (self::PAGES as [$title, $route, $keywords]) {
            if (str_contains(Str::lower($title.' '.$keywords), $needle)) {
                $matches[] = ['title' => $title, 'detail' => 'Dashboard page', 'url' => route($route)];
            }
        }

        return array_slice($matches, 0, $limit);
    }

    private function projects(string $query, string $like, int $limit): array
    {
        return $this->matching(Project::query(), ['name', 'slug', 'description', 'details', 'technologies', 'seo_title', 'seo_description'], $like, $limit)
            ->map(fn (Project $project) => [
                'title' => $project->name,
                'detail' => ($project->is_published ? 'Published' : 'Draft').' · '.$this->excerpt($project->description.' '.$project->details, $query),
                'url' => route('admin.projects.index').'#project-'.$project->id.'-name',
            ])->all();
    }

    private function sections(string $query, string $like, int $limit): array
    {
        return $this->matching(PortfolioSection::query(), ['label', 'title', 'subtitle', 'body', 'items'], $like, $limit)
            ->map(function (PortfolioSection $section) use ($query) {
                $builtIn = in_array($section->key, PortfolioSection::BUILT_IN_KEYS, true);

                return [
                    'title' => $section->title ?: $section->label ?: $section->key,
                    'detail' => ($builtIn ? 'Built-in section' : 'Custom section').($section->is_visible ? '' : ' · hidden').' · '.$this->excerpt($section->subtitle.' '.$section->body, $query),
                    'url' => $builtIn
                        ? route('admin.site-editor.edit').'#section-'.$section->key.'-label'
                        : route('admin.sections.index').'#section-'.$section->id.'-label',
                ];
            })->all();
    }

    private function settings(string $query, string $like, int $limit): array
    {
        $fields = [
            'name' => ['Display name', 'setting-name'], 'professional_title' => ['Professional title', 'setting-title'],
            'email' => ['Contact email', 'setting-email'], 'github_url' => ['GitHub profile URL', 'setting-github'],
            'linkedin_url' => ['LinkedIn URL', 'setting-linkedin'], 'site_title' => ['Page title', 'setting-site-title'],
            'site_description' => ['Page description', 'setting-description'], 'canonical_url' => ['Canonical base URL', 'setting-canonical'],
        ];
        $settings = PortfolioSetting::find(1);
        if (! $settings) {
            return [];
        }
        $needle = Str::lower($query);
        $matches = [];
        foreach ($fields as $column => [$label, $anchor]) {
            $value = (string) $settings->getAttribute($column);
            if (str_contains(Str::lower($label.' '.$value), $needle)) {
                $matches[] = ['title' => $label, 'detail' => Str::limit($value ?: 'Not set', 90), 'url' => route('admin.site-editor.edit').'#'.$anchor];
            }
        }

        return array_slice($matches, 0, $limit);
    }

    private function skills(string $query, string $like, int $limit): array
    {
        return $this->matching(Skill::query()->with('category'), ['name', 'description'], $like, $limit)
            ->map(fn (Skill $skill) => [
                'title' => $skill->name,
                'detail' => 'Skill'.($skill->category ? ' in '.$skill->category->title : ''),
                'url' => route('admin.site-editor.edit'),
            ])->all();
    }

    private function media(string $query, string $like, int $limit): array
    {
        return $this->matching(MediaAsset::query(), ['label', 'original_name', 'alt_text'], $like, $limit)
            ->map(fn (MediaAsset $asset) => [
                'title' => $asset->label ?: $asset->original_name,
                'detail' => $asset->alt_text ? 'Alt text: '.Str::limit($asset->alt_text, 80) : 'Missing alt text',
                'url' => route('admin.media.index', ['q' => $query]).'#edit-label-'.$asset->id,
            ])->all();
    }

    private function fonts(string $query, string $like, int $limit): array
    {
        return $this->matching(FontAsset::query(), ['family', 'original_name'], $like, $limit)
            ->map(fn (FontAsset $font) => [
                'title' => $font->family,
                'detail' => $font->weight.' '.$font->style.($font->is_active ? ' · active' : ' · inactive'),
                'url' => route('admin.fonts.index'),
            ])->all();
    }

    private function messages(string $query, string $like, int $limit): array
    {
        return $this->matching(ContactMessage::query(), ['name', 'email', 'subject', 'message'], $like, $limit)
            ->map(fn (ContactMessage $message) => [
                'title' => $message->subject ?: '(no subject)',
                'detail' => $message->name.' · '.ucfirst($message->status).' · '.$message->created_at?->format('j M Y'),
                'url' => route('admin.inbox.index', ['q' => $message->email]),
            ])->all();
    }

    private function matching(Builder $builder, array $columns, string $like, int $limit)
    {
        return $builder->where(function (Builder $where) use ($columns, $like) {
            foreach ($columns as $column) {
                $where->orWhere($column, 'like', $like);
            }
        })->latest($builder->getModel()->getCreatedAtColumn())->limit($limit)->get();
    }

    private function excerpt(string $text, string $query): string
    {
        $text = trim(preg_replace('/\s+/', ' ', strip_tags($text)));
        $at = mb_stripos($text, $query);
        if ($at === false || $at < 40) {
            return Str::limit($text, 90);
        }

        return '…'.Str::limit(mb_substr($text, $at - 30), 88);
    }
}
