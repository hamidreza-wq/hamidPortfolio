<?php

namespace App\Http\Controllers;

use App\Models\PortfolioSetting;
use App\Models\Project;
use App\Services\FontLibrary;
use App\Services\MediaLibrary;
use App\Services\PortfolioSeo;
use Illuminate\Support\Facades\DB;

class ProjectDetailController extends Controller
{
    public function show(string $slug)
    {
        $project = Project::query()->where('is_published', true)->where('slug', $slug)->first();
        if (! $project) {
            $projectId = DB::table('project_slug_redirects')->where('slug', $slug)->value('project_id');
            $redirectedProject = $projectId ? Project::query()->where('is_published', true)->find($projectId) : null;
            abort_unless($redirectedProject, 404);

            return redirect()->route('portfolio.projects.show', $redirectedProject->slug, 301);
        }
        $settings = PortfolioSetting::query()->findOrFail(1);
        if ($settings->maintenance_mode) {
            return response()->view('maintenance', [], 503)->header('X-Robots-Tag', 'noindex, nofollow');
        }
        $seo = app(PortfolioSeo::class)->metadata($settings, $project);

        return response()->view('portfolio.project', [
            'project' => $project,
            'settings' => $settings,
            'seo' => $seo,
            'fontCss' => app(FontLibrary::class)->css(),
            'image' => app(MediaLibrary::class)->publicPayload($project->media_asset_id),
            'gallery' => collect($project->gallery_media_ids ?? [])->map(fn ($id) => app(MediaLibrary::class)->publicPayload($id))->filter(),
            'analyticsMeasurementId' => config('services.google_analytics.measurement_id'),
        ])->header('X-Robots-Tag', $seo['robots'])->header('Cache-Control', 'private, no-store');
    }

    public function sitemap()
    {
        $settings = PortfolioSetting::query()->findOrFail(1);
        $projects = $settings->indexing_enabled && ! $settings->maintenance_mode
            ? Project::query()->where('is_published', true)->orderBy('id')->get()
            : collect();

        return response()->view('portfolio.sitemap', [
            'settings' => $settings,
            'projects' => $projects,
            'baseUrl' => app(PortfolioSeo::class)->baseUrl($settings),
        ])->header('Content-Type', 'application/xml; charset=UTF-8');
    }

    public function robots()
    {
        $settings = PortfolioSetting::query()->findOrFail(1);
        $body = "User-agent: *\n";
        if (! $settings->indexing_enabled || $settings->maintenance_mode) {
            $body .= "Disallow: /\n";
        } else {
            $body .= "Allow: /\nDisallow: /admin\nDisallow: /preview\n";
        }
        $body .= 'Sitemap: '.app(PortfolioSeo::class)->baseUrl($settings)."/sitemap.xml\n";

        return response($body)->header('Content-Type', 'text/plain; charset=UTF-8');
    }
}
