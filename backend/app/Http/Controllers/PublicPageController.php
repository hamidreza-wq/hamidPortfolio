<?php

namespace App\Http\Controllers;

use App\Models\Page;
use App\Models\PortfolioSetting;
use App\Services\FontLibrary;
use App\Services\MediaLibrary;
use App\Services\PageContent;
use App\Services\PortfolioSeo;

class PublicPageController extends Controller
{
    public function show(string $pageSlug, PageContent $content)
    {
        $page = Page::live()->where('slug', $pageSlug)->first();
        abort_unless($page, 404);

        return $this->render($page, $content);
    }

    public function render(Page $page, PageContent $content, bool $preview = false)
    {
        $settings = PortfolioSetting::query()->findOrFail(1);
        if ($settings->maintenance_mode && ! $preview) {
            return response()->view('maintenance', [], 503)->header('X-Robots-Tag', 'noindex, nofollow');
        }
        $seo = app(PortfolioSeo::class)->metadata($settings, null, $preview);
        $media = app(MediaLibrary::class)->publicPayload($page->media_asset_id);
        $canonical = app(PortfolioSeo::class)->baseUrl($settings).'/'.$page->slug;
        $indexable = $seo['indexable'] && ! $page->noindex;
        $title = $page->seo_title ?: $page->title.' — '.$settings->name;
        $description = $page->seo_description ?: ($page->summary ?: $settings->site_description);
        $seo = array_merge($seo, [
            'title' => $title, 'ogTitle' => $title, 'description' => $description, 'ogDescription' => $description,
            'canonical' => $canonical, 'indexable' => $indexable,
            'robots' => ($indexable ? 'index' : 'noindex').', '.($settings->robots_follow && ! $preview ? 'follow' : 'nofollow'),
            'image' => $media['url'] ?? $seo['image'], 'imageAlt' => $media['alt'] ?? $seo['imageAlt'],
            'imageWidth' => $media['width'] ?? $seo['imageWidth'], 'imageHeight' => $media['height'] ?? $seo['imageHeight'],
            'structured' => [
                '@context' => 'https://schema.org', '@type' => 'WebPage', '@id' => $canonical,
                'url' => $canonical, 'name' => $title, 'description' => $description, 'inLanguage' => $settings->language,
                'isPartOf' => ['@type' => 'WebSite', 'url' => app(PortfolioSeo::class)->baseUrl($settings).'/', 'name' => $settings->site_title],
            ],
        ]);

        return response()->view('portfolio.page', [
            'page' => $page, 'settings' => $settings, 'seo' => $seo, 'preview' => $preview,
            'html' => $content->render($page->body),
            'image' => $media,
            'fontCss' => app(FontLibrary::class)->css(),
        ])->header('X-Robots-Tag', $seo['robots'])->header('Cache-Control', 'private, no-store');
    }
}
