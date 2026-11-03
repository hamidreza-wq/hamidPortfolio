<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\ContactMessage;
use App\Models\MediaAsset;
use App\Models\PortfolioSection;
use App\Models\PortfolioSetting;
use App\Models\Project;

class DashboardController extends Controller
{
    public function index()
    {
        $settings = PortfolioSetting::findOrFail(1);
        $seoIssues = [];
        if (! $settings->canonical_url) {
            $seoIssues[] = 'Set the canonical URL after configuring the production domain.';
        }
        if (! $settings->site_title || ! $settings->site_description) {
            $seoIssues[] = 'The global page title and description are required.';
        }
        if (! $settings->getAttribute('og_image_media_id')) {
            $seoIssues[] = 'Select a default social sharing image.';
        }
        $missingProjectSeo = Project::where('is_published', true)
            ->where(fn ($query) => $query->whereNull('seo_title')->orWhere('seo_title', '')->orWhereNull('seo_description')->orWhere('seo_description', ''))
            ->count();
        if ($missingProjectSeo) {
            $seoIssues[] = "{$missingProjectSeo} published project(s) use fallback titles or descriptions.";
        }
        $missingAlt = MediaAsset::whereNull('alt_text')->orWhere('alt_text', '')->count();
        if ($missingAlt) {
            $seoIssues[] = "{$missingAlt} library image(s) are missing descriptive alt text.";
        }

        return view('admin.dashboard', [
            'projectCount' => Project::count(),
            'publishedCount' => Project::where('is_published', true)->count(),
            'draftCount' => Project::where('is_published', false)->count(),
            'unreadMessages' => ContactMessage::where('status', 'unread')->count(),
            'mediaBytes' => MediaAsset::sum('size_bytes'),
            'sectionCount' => PortfolioSection::where('is_visible', true)->count(),
            'totalSections' => PortfolioSection::count(),
            'settings' => $settings,
            'seoIssues' => $seoIssues,
            'recentMessages' => ContactMessage::latest()->limit(5)->get(),
            'activity' => AuditLog::with('user')->latest()->limit(8)->get(),
        ]);
    }
}
