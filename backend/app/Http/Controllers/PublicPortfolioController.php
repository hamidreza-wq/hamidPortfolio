<?php

namespace App\Http\Controllers;

use App\Models\PortfolioSection;
use App\Models\PortfolioSetting;
use App\Models\Project;
use App\Models\SkillCategory;
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
        return $this->payload(PortfolioSetting::query()->findOrFail(1));
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
            return response()->view('maintenance', [], 503);
        }

        $htmlPath = dirname(base_path()).DIRECTORY_SEPARATOR.'index.html';
        abort_unless(is_file($htmlPath) && is_readable($htmlPath), 503, 'Portfolio frontend could not be loaded.');
        $html = file_get_contents($htmlPath);
        $payload = $this->payload($settings);
        $serialized = json_encode($payload, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_THROW_ON_ERROR);
        $previewFlag = $isPreview ? 'window.portfolioPreview = true;' : '';
        $marker = '<script type="module" src="/app.mjs"></script>';
        $script = '<script>window.portfolioPayload = '.$serialized.';'.$previewFlag.'</script>'.$marker;
        $html = str_replace($marker, $script, $html, $replacements);
        abort_unless($replacements === 1, 500, 'Portfolio integration marker is missing from index.html.');

        $safeTitle = e($settings->site_title);
        $safeDescription = e($settings->site_description);
        $safeThemeColor = e($settings->color_background);
        $html = preg_replace('/<title>.*?<\\/title>/s', '<title>'.$safeTitle.'</title>', $html, 1);
        $html = preg_replace_callback('/<meta\\b[^>]*>/i', function (array $match) use ($safeTitle, $safeDescription, $safeThemeColor): string {
            $tag = $match[0];
            if (preg_match('/\\bname="theme-color"/i', $tag)) {
                return preg_replace('/\\bcontent="[^"]*"/i', 'content="'.$safeThemeColor.'"', $tag, 1) ?? $tag;
            }
            if (preg_match('/\\bname="description"/i', $tag)
                || preg_match('/\\bproperty="og:description"/i', $tag)
                || preg_match('/\\bname="twitter:description"/i', $tag)) {
                return preg_replace('/\\bcontent="[^"]*"/i', 'content="'.$safeDescription.'"', $tag, 1) ?? $tag;
            }
            if (preg_match('/\\bproperty="og:title"/i', $tag)
                || preg_match('/\\bname="twitter:title"/i', $tag)) {
                return preg_replace('/\\bcontent="[^"]*"/i', 'content="'.$safeTitle.'"', $tag, 1) ?? $tag;
            }

            return $tag;
        }, $html) ?? $html;
        if ($settings->canonical_url) {
            $canonical = '<link rel="canonical" href="'.e($settings->canonical_url).'" />';
            if (str_contains($html, 'rel="canonical"')) {
                $html = preg_replace('/<link\\s+rel="canonical"[^>]*>/i', $canonical, $html, 1) ?? $html;
            } else {
                $html = str_replace('</head>', $canonical.'</head>', $html);
            }
        } else {
            $html = preg_replace('/<link\\s+rel="canonical"[^>]*>/i', '', $html) ?? $html;
        }

        return response($html)
            ->header('Content-Type', 'text/html; charset=UTF-8')
            ->header('X-Content-Type-Options', 'nosniff')
            ->header('Referrer-Policy', 'strict-origin-when-cross-origin')
            ->header('Cache-Control', $isPreview ? 'private, no-store' : 'public, max-age=60');
    }

    private function payload(PortfolioSetting $settings): array
    {
        $projects = Project::query()
            ->where('is_published', true)
            ->orderBy('sort_order')
            ->get()
            ->map(fn (Project $project) => [
                'name' => $project->name,
                'category' => $project->category,
                'categoryLabel' => $project->category_label,
                'repo' => basename(parse_url($project->repository_url, PHP_URL_PATH) ?: ''),
                'repositoryUrl' => $project->repository_url,
                'sortOrder' => $project->sort_order,
                'liveUrl' => $project->live_url,
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
                'contact_action_url' => route('contact.store'),
                'csrf_token' => csrf_token(),
                'contact_feedback' => session('contact_status') ?? session('errors')?->first(),
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
            'sections' => PortfolioSection::query()
                ->orderBy('sort_order')
                ->get(['key', 'title', 'subtitle', 'body', 'is_visible', 'sort_order'])
                ->map(fn (PortfolioSection $section) => [
                    'key' => $section->key,
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
