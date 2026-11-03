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
        return view('admin.dashboard', [
            'projectCount' => Project::count(),
            'publishedCount' => Project::where('is_published', true)->count(),
            'draftCount' => Project::where('is_published', false)->count(),
            'unreadMessages' => ContactMessage::where('status', 'unread')->count(),
            'mediaBytes' => MediaAsset::sum('size_bytes'),
            'sectionCount' => PortfolioSection::where('is_visible', true)->count(),
            'totalSections' => PortfolioSection::count(),
            'settings' => PortfolioSetting::find(1),
            'recentMessages' => ContactMessage::latest()->limit(5)->get(),
            'activity' => AuditLog::with('user')->latest()->limit(8)->get(),
        ]);
    }
}
