<?php

use App\Http\Controllers\Admin\AnalyticsController;
use App\Http\Controllers\Admin\AuthController;
use App\Http\Controllers\Admin\ContactMessageController;
use App\Http\Controllers\Admin\ContentRevisionController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\FontController;
use App\Http\Controllers\Admin\MediaController;
use App\Http\Controllers\Admin\PlatformController;
use App\Http\Controllers\Admin\ProjectController;
use App\Http\Controllers\Admin\SectionController;
use App\Http\Controllers\Admin\SiteEditorController;
use App\Http\Controllers\Admin\ThemePresetController;
use App\Http\Controllers\ProjectDetailController;
use App\Http\Controllers\PublicContactController;
use App\Http\Controllers\PublicPortfolioController;
use Illuminate\Support\Facades\Route;

Route::get('/', [PublicPortfolioController::class, 'index'])->middleware('record.pageview')->name('portfolio.home');
Route::get('/projects/{slug}', [ProjectDetailController::class, 'show'])->where('slug', '[a-z0-9]+(?:-[a-z0-9]+)*')->middleware('record.pageview')->name('portfolio.projects.show');
Route::get('/sitemap.xml', [ProjectDetailController::class, 'sitemap'])->name('portfolio.sitemap');
Route::get('/robots.txt', [ProjectDetailController::class, 'robots'])->name('portfolio.robots');
Route::view('/privacy', 'portfolio.privacy')->name('portfolio.privacy');
Route::get('/fonts/{fontAsset}', [FontController::class, 'show'])->name('fonts.show');
Route::get('/media/{mediaAsset}', [MediaController::class, 'show'])->name('media.show');
Route::post('/contact', [PublicContactController::class, 'store'])
    ->middleware('throttle:3,10')
    ->name('contact.store');
Route::get('/preview', [PublicPortfolioController::class, 'preview'])
    ->middleware(['auth', 'auth.session', 'admin'])
    ->name('portfolio.preview');
Route::get('/{asset}', [PublicPortfolioController::class, 'asset'])
    ->where('asset', 'styles\\.css|app\\.mjs|portfolio-data\\.mjs|favicon\\.svg');

Route::middleware('guest')->group(function () {
    Route::get('/admin/login', [AuthController::class, 'create'])->name('login');
    Route::post('/admin/login', [AuthController::class, 'store'])->middleware('throttle:5,1')->name('admin.login');
});

Route::middleware(['auth', 'auth.session', 'admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::post('/logout', [AuthController::class, 'destroy'])->name('logout');
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/analytics', [AnalyticsController::class, 'index'])->name('analytics');
    Route::post('/analytics/google/connect', [AnalyticsController::class, 'connect'])->middleware('throttle:5,1')->name('analytics.google.connect');
    Route::get('/analytics/google/callback', [AnalyticsController::class, 'callback'])->name('analytics.google.callback');
    Route::delete('/analytics/google', [AnalyticsController::class, 'disconnect'])->name('analytics.google.disconnect');
    Route::get('/updates', [PlatformController::class, 'updates'])->name('platform.updates');
    Route::get('/github-projects', [PlatformController::class, 'github'])->name('platform.github');
    Route::get('/seo-monitor', [PlatformController::class, 'seo'])->name('platform.seo');
    Route::get('/notifications', [PlatformController::class, 'notifications'])->name('platform.notifications');
    Route::put('/notification-preferences', [PlatformController::class, 'preferences'])->name('platform.preferences');
    Route::post('/platform/check/{kind}', [PlatformController::class, 'scan'])->middleware('throttle:6,1')->name('platform.scan');
    Route::post('/updates/{releaseCandidate}/verify', [PlatformController::class, 'prepare'])->middleware('throttle:3,1')->name('platform.prepare');
    Route::post('/updates/{releaseCandidate}/approve', [PlatformController::class, 'approve'])->middleware('throttle:3,1')->name('platform.approve');
    Route::post('/github-projects/{githubRepository}/review', [PlatformController::class, 'review'])->name('platform.review');
    Route::patch('/notifications/{platformNotice}', [PlatformController::class, 'notice'])->name('platform.notice');
    Route::get('/site-editor', [SiteEditorController::class, 'edit'])->name('site-editor.edit');
    Route::put('/site-editor', [SiteEditorController::class, 'update'])->name('site-editor.update');
    Route::post('/themes', [ThemePresetController::class, 'store'])->name('themes.store');
    Route::delete('/themes/{themePreset}', [ThemePresetController::class, 'destroy'])->name('themes.destroy');
    Route::get('/sections', [SectionController::class, 'index'])->name('sections.index');
    Route::post('/sections', [SectionController::class, 'store'])->name('sections.store');
    Route::put('/sections/{portfolioSection}', [SectionController::class, 'update'])->name('sections.update');
    Route::delete('/sections/{portfolioSection}', [SectionController::class, 'destroy'])->name('sections.destroy');
    Route::get('/revisions', [ContentRevisionController::class, 'index'])->name('revisions.index');
    Route::post('/revisions/{contentRevision}/restore', [ContentRevisionController::class, 'restore'])->name('revisions.restore');
    Route::get('/projects', [ProjectController::class, 'index'])->name('projects.index');
    Route::post('/projects', [ProjectController::class, 'store'])->name('projects.store');
    Route::put('/projects/{project}', [ProjectController::class, 'update'])->name('projects.update');
    Route::delete('/projects/{project}', [ProjectController::class, 'destroy'])->name('projects.destroy');
    Route::get('/media', [MediaController::class, 'index'])->name('media.index');
    Route::post('/media', [MediaController::class, 'store'])->name('media.store');
    Route::put('/media/{mediaAsset}', [MediaController::class, 'update'])->name('media.update');
    Route::delete('/media/{mediaAsset}', [MediaController::class, 'destroy'])->name('media.destroy');
    Route::get('/fonts', [FontController::class, 'index'])->name('fonts.index');
    Route::post('/fonts', [FontController::class, 'store'])->name('fonts.store');
    Route::put('/fonts/{fontAsset}', [FontController::class, 'update'])->name('fonts.update');
    Route::delete('/fonts/{fontAsset}', [FontController::class, 'destroy'])->name('fonts.destroy');
    Route::get('/inbox', [ContactMessageController::class, 'index'])->name('inbox.index');
    Route::patch('/inbox/{contactMessage}', [ContactMessageController::class, 'update'])->name('inbox.update');
    Route::delete('/inbox/{contactMessage}', [ContactMessageController::class, 'destroy'])->name('inbox.destroy');
});
