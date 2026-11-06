<?php

namespace App\Http\Controllers;

use App\Models\PortfolioSection;
use App\Models\PortfolioSetting;
use App\Models\Project;
use App\Models\SkillCategory;
use App\Services\ContactSubmissionGuard;
use App\Services\FontLibrary;
use App\Services\MediaLibrary;
use App\Services\PortfolioRenderer;
use App\Services\PortfolioSeo;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PublicPortfolioController extends Controller
{
    public function index(Request $request): Response
    {
        return $this->renderPortfolio($request, false);
    }

    public function preview(Request $request): Response
    {
        return $this->renderPortfolio($request, true);
    }

    public function previewPayloadForAdmin(): array
    {
        return $this->payload(PortfolioSetting::query()->findOrFail(1), true);
    }

    public function asset(string $asset): Response
    {
        $allowed = ['styles.css', 'app.mjs', 'portfolio-data.mjs', 'favicon.svg'];
        abort_unless(in_array($asset, $allowed, true), 404);

        $path = dirname(base_path()).DIRECTORY_SEPARATOR.$asset;
        abort_unless(is_file($path) && is_readable($path), 404);

        $types = [
            'styles.css' => 'text/css; charset=UTF-8',
            'app.mjs' => 'text/javascript; charset=UTF-8',
            'portfolio-data.mjs' => 'text/javascript; charset=UTF-8',
            'favicon.svg' => 'image/svg+xml',
        ];

        return response(file_get_contents($path), 200, [
            'Content-Type' => $types[$asset],
            'Cache-Control' => app()->isProduction() ? 'public, max-age=300' : 'no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private function renderPortfolio(Request $request, bool $isPreview): Response
    {
        $settings = PortfolioSetting::query()->find(1);
        abort_unless($settings, 503, 'Portfolio content has not been installed. Run the migrations and seed command first.');

        if ($settings->maintenance_mode && ! $isPreview) {
            return response()->view('maintenance', [], 503)
                ->header('X-Robots-Tag', 'noindex, nofollow')
                ->header('Cache-Control', 'private, no-store');
        }

        $htmlPath = dirname(base_path()).DIRECTORY_SEPARATOR.'index.html';
        abort_unless(is_file($htmlPath) && is_readable($htmlPath), 503, 'Portfolio frontend could not be loaded.');
        $html = file_get_contents($htmlPath);
        $payload = $this->payload($settings, $isPreview);
        $html = app(PortfolioRenderer::class)->render($html, $payload);
        $serialized = json_encode($payload, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_THROW_ON_ERROR);
        $previewFlag = $isPreview ? 'window.portfolioPreview = true;' : '';
        $marker = '<script type="module" src="/app.mjs"></script>';
        $script = '<script>window.portfolioPayload = '.$serialized.';'.$previewFlag.'</script>'.$marker;
        $html = str_replace($marker, $script, $html, $replacements);
        abort_unless($replacements === 1, 500, 'Portfolio integration marker is missing from index.html.');

        $seo = app(PortfolioSeo::class)->metadata($settings, preview: $isPreview);
        $html = preg_replace('/<title>.*?<\\/title>/s', '', $html, 1);
        $html = preg_replace_callback('/<meta\\b[^>]*>/i', function (array $match) use ($settings): string {
            $tag = $match[0];
            if (preg_match('/\\bname="theme-color"/i', $tag)) {
                return '<meta name="theme-color" content="'.e($settings->color_background).'">';
            }
            if (preg_match('/\\bname="description"/i', $tag)
                || preg_match('/\\b(?:property|name)="(?:og:|twitter:|robots)/i', $tag)) {
                return '';
            }

            return $tag;
        }, $html) ?? $html;
        $html = preg_replace('/<link\\s+rel="canonical"[^>]*>/i', '', $html) ?? $html;
        $html = preg_replace('/<html\\b[^>]*>/i', '<html lang="'.e($seo['language']).'">', $html, 1) ?? $html;
        $html = str_replace('</head>', app(PortfolioSeo::class)->tags($seo).'<style>'.app(FontLibrary::class)->css().'</style></head>', $html);
        if (! $isPreview) {
            $consentUi = view('portfolio.analytics-consent', [
                'measurementId' => config('services.google_analytics.measurement_id'),
            ])->render();
            $html = str_replace('</body>', $consentUi.'</body>', $html);
        }

        return response($html)
            ->header('Content-Type', 'text/html; charset=UTF-8')
            ->header('X-Content-Type-Options', 'nosniff')
            ->header('Referrer-Policy', 'strict-origin-when-cross-origin')
            ->header('X-Robots-Tag', $seo['robots'])
            ->header('Cache-Control', 'private, no-store');
    }

    private function payload(PortfolioSetting $settings, bool $isPreview = false): array
    {
        $projects = Project::query()
            ->where('is_published', true)
            ->orderBy('sort_order')
            ->get()
            ->map(fn (Project $project) => [
                'name' => $project->name,
                'slug' => $project->slug,
                'detailUrl' => route('portfolio.projects.show', $project->slug),
                'status' => $project->status,
                'featured' => $project->is_featured,
                'image' => app(MediaLibrary::class)->publicPayload($project->media_asset_id),
                'gallery' => collect($project->gallery_media_ids ?? [])->map(fn ($id) => app(MediaLibrary::class)->publicPayload($id))->filter()->values()->all(),
                'category' => $project->category,
                'categoryLabel' => $project->category_label,
                'repo' => basename(parse_url($project->repository_url, PHP_URL_PATH) ?: ''),
                'sourcePublic' => $project->repository_is_public,
                'repositoryUrl' => $project->repository_is_public ? $project->repository_url : null,
                'sortOrder' => $project->sort_order,
                'liveUrl' => $project->live_url_verified ? $project->live_url : null,
                'description' => $project->description,
                'detail' => $project->details,
                'technologies' => $project->technologies ?? [],
                'features' => $project->features ?? [],
                'visual' => $project->visual,
                'visualLabel' => $project->visual_label,
            ])->all();

        return [
            'profile' => [
                'name' => $settings->name,
                'title' => $settings->professional_title,
                'github' => $settings->github_url,
                'email' => $settings->email,
                'linkedin' => $settings->linkedin_url,
            ],
            'settings' => [
                'footer_text' => $settings->footer_text,
                'site_title' => $settings->site_title,
                'site_description' => $settings->site_description,
                'canonical_url' => $settings->canonical_url,
                'language' => $settings->language,
                'contact_action_url' => route('contact.store'),
                'csrf_token' => csrf_token(),
                'contact_feedback' => session('contact_status') ?? session('errors')?->first(),
                'contact_submission_token' => app(ContactSubmissionGuard::class)->issueToken(),
            ],
            'theme' => [
                'background' => $settings->color_background,
                'foreground' => $settings->color_foreground,
                'primary' => $settings->color_primary,
                'accent' => $settings->color_accent,
                'cyan' => $settings->color_cyan,
                'font_body' => $settings->font_body,
                'font_heading' => $settings->font_heading,
                'max_width' => $settings->max_width,
                'section_spacing' => $settings->section_spacing,
                'border_radius' => $settings->border_radius,
                'animations_enabled' => $settings->animations_enabled,
                'particles_enabled' => $settings->particles_enabled,
            ],
            'fonts' => app(FontLibrary::class)->payload(),
            'media' => $isPreview ? app(MediaLibrary::class)->options()->map(fn ($media) => app(MediaLibrary::class)->publicPayload($media->id))->filter()->values()->all() : [],
            'sections' => PortfolioSection::query()
                ->when(! $isPreview, fn ($query) => $query->where('is_visible', true))
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get()
                ->map(fn (PortfolioSection $section) => [
                    'key' => $section->key,
                    'type' => $section->type,
                    'label' => $section->label,
                    'show_in_navigation' => $section->show_in_navigation,
                    'items' => $section->items ?? [],
                    'image' => app(MediaLibrary::class)->publicPayload($section->image_media_id),
                    'background' => app(MediaLibrary::class)->publicPayload($section->background_media_id),
                    'background_effect' => $section->background_effect,
                    'spacing_top' => $section->spacing_top,
                    'spacing_bottom' => $section->spacing_bottom,
                    'title' => $section->title,
                    'subtitle' => $section->subtitle,
                    'body' => $section->body,
                    'is_visible' => $section->is_visible,
                    'sort_order' => $section->sort_order,
                ])->all(),
            'projects' => $projects,
            'craft' => SkillCategory::query()
                ->where('is_visible', true)
                ->with(['skills' => fn ($query) => $query->where('is_visible', true)->orderBy('sort_order')])
                ->orderBy('sort_order')
                ->get()
                ->map(fn (SkillCategory $category) => [
                    'title' => $category->title,
                    'items' => $category->skills->pluck('name')->all(),
                ])->all(),
        ];
    }
}
