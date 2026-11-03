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

        return [
            'title' => $title,
            'description' => $description,
            'canonical' => $canonical,
            'language' => $settings->language,
            'robots' => ($indexable ? 'index' : 'noindex').', '.($settings->robots_follow && ! $preview ? 'follow' : 'nofollow'),
            'ogTitle' => $project ? $title : ($settings->og_title ?: $title),
            'ogDescription' => $project ? $description : ($settings->og_description ?: $description),
            'image' => $image,
            'imageAlt' => $media['alt'] ?? '',
            'structured' => $project ? [
                '@context' => 'https://schema.org',
                '@type' => 'CreativeWork',
                'name' => $project->name,
                'description' => $description,
                'url' => $canonical,
                'author' => ['@type' => 'Person', 'name' => $settings->name],
                'keywords' => implode(', ', $project->technologies ?? []),
                ...($image ? ['image' => $image] : []),
            ] : [
                '@context' => 'https://schema.org',
                '@type' => 'WebSite',
                'name' => $title,
                'description' => $description,
                'url' => $canonical,
                'inLanguage' => $settings->language,
                'author' => ['@type' => 'Person', 'name' => $settings->name],
            ],
        ];
    }

    public function tags(array $seo): string
    {
        return view('portfolio.seo', compact('seo'))->render();
    }
}
