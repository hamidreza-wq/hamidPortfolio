<?php

namespace App\Services;

use App\Models\PortfolioSetting;
use App\Models\Project;

class PortfolioSeo
{
    public function baseUrl(PortfolioSetting $settings): string
    {
        return rtrim($settings->canonical_url ?: config('app.url'), '/');
    }

    public function metadata(PortfolioSetting $settings, ?Project $project = null, bool $preview = false): array
    {
        $canonical = $this->baseUrl($settings).($project ? '/projects/'.$project->slug : '/');
        $title = $project ? ($project->seo_title ?: $project->name.' — '.$settings->name) : $settings->site_title;
        $description = $project ? ($project->seo_description ?: $project->description) : $settings->site_description;
        $media = app(MediaLibrary::class)->publicPayload($project?->media_asset_id ?: $settings->og_image_media_id);
        $image = $media['url'] ?? $settings->og_image;
        $indexable = $settings->indexing_enabled && ! $settings->maintenance_mode && ! $preview;
        $base = $this->baseUrl($settings).'/';
        $person = [
            '@type' => 'Person',
            '@id' => $base.'#person',
            'name' => $settings->name,
            'url' => $base,
            'jobTitle' => $settings->professional_title,
            'sameAs' => array_values(array_filter([$settings->github_url, $settings->linkedin_url])),
        ];
        $website = [
            '@type' => 'WebSite',
            '@id' => $base.'#website',
            'name' => $settings->site_title,
            'url' => $base,
            'inLanguage' => $settings->language,
            'publisher' => ['@id' => $person['@id']],
        ];

        return [
            'title' => $title,
            'description' => $description,
            'canonical' => $canonical,
            'language' => $settings->language,
            'robots' => ($indexable ? 'index' : 'noindex').', '.($settings->robots_follow && ! $preview ? 'follow' : 'nofollow'),
            'indexable' => $indexable,
            'siteName' => $settings->site_title,
            'author' => $project ? ($project->rights_confirmed_at ? $project->author_name : null) : $settings->name,
            'ogTitle' => $project ? $title : ($settings->og_title ?: $title),
            'ogDescription' => $project ? $description : ($settings->og_description ?: $description),
            'image' => $image,
            'imageAlt' => ($media['alt'] ?? '') ?: ($project?->name ?? $settings->site_title),
            'imageWidth' => $media['width'] ?? null,
            'imageHeight' => $media['height'] ?? null,
            'structured' => $project ? [
                '@context' => 'https://schema.org',
                '@type' => 'CreativeWork',
                '@id' => $canonical.'#project',
                'name' => $project->name,
                'description' => $description,
                'url' => $canonical,
                'inLanguage' => $settings->language,
                'mainEntityOfPage' => [
                    '@type' => 'WebPage',
                    '@id' => $canonical,
                    'url' => $canonical,
                    'name' => $title,
                    'isPartOf' => ['@type' => 'WebSite', '@id' => $website['@id'], 'url' => $base, 'name' => $settings->site_title],
                    'breadcrumb' => [
                        '@type' => 'BreadcrumbList',
                        'itemListElement' => [
                            ['@type' => 'ListItem', 'position' => 1, 'name' => 'Home', 'item' => $base],
                            ['@type' => 'ListItem', 'position' => 2, 'name' => $project->name, 'item' => $canonical],
                        ],
                    ],
                ],
                ...($project->rights_confirmed_at && $project->author_name ? ['author' => ['@type' => 'Person', 'name' => $project->author_name]] : []),
                ...($project->license_name ? ['license' => $project->license_name] : []),
                ...($project->rights_confirmed_at && $project->copyright_holder ? ['copyrightHolder' => ['@type' => 'Person', 'name' => $project->copyright_holder]] : []),
                'keywords' => implode(', ', $project->technologies ?? []),
                ...($image ? ['image' => $image] : []),
            ] : [
                '@context' => 'https://schema.org',
                '@graph' => [
                    $website,
                    $person,
                    [
                        '@type' => 'ProfilePage',
                        '@id' => $canonical.'#profile',
                        'name' => $title,
                        'description' => $description,
                        'url' => $canonical,
                        'inLanguage' => $settings->language,
                        'isPartOf' => ['@id' => $website['@id']],
                        'mainEntity' => ['@id' => $person['@id']],
                        ...($image ? ['image' => $image] : []),
                    ],
                ],
            ],
        ];
    }

    public function tags(array $seo): string
    {
        return view('portfolio.seo', compact('seo'))->render();
    }
}
