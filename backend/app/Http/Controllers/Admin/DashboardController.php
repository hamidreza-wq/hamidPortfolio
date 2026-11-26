<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\ContactMessage;
use App\Models\MediaAsset;
use App\Models\PageView;
use App\Models\PortfolioSection;
use App\Models\PortfolioSetting;
use App\Models\Project;
use Illuminate\Support\Collection;

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
            'pageViews' => PageView::where('viewed_at', '>=', now()->subDays(29)->startOfDay())->count(),
            'sectionCount' => PortfolioSection::where('is_visible', true)->count(),
            'totalSections' => PortfolioSection::count(),
            'settings' => $settings,
            'seoIssues' => $seoIssues,
            'recentMessages' => ContactMessage::latest()->limit(5)->get(),
            'activity' => $this->groupedActivity(),
        ]);
    }

    /**
     * Collapse runs of the same action by the same user (for example eight
     * consecutive media saves) into one row, so the overview stays readable.
     *
     * @return Collection<int, array{item: AuditLog, count: int}>
     */
    private function groupedActivity(int $groups = 8)
    {
        $grouped = collect();
        foreach (AuditLog::with('user')->latest()->latest('id')->limit(60)->get() as $item) {
            $last = $grouped->last();
            if ($last && $last['item']->action === $item->action && $last['item']->user_id === $item->user_id) {
                $grouped->put($grouped->count() - 1, ['item' => $last['item'], 'count' => $last['count'] + 1]);

                continue;
            }
            if ($grouped->count() === $groups) {
                break;
            }
            $grouped->push(['item' => $item, 'count' => 1]);
        }

        return $grouped;
    }
}
