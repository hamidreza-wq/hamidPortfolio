<?php

use App\Http\Controllers\Admin\AnalyticsController;
use App\Http\Controllers\Admin\AuthController;
use App\Http\Controllers\Admin\BackupController;
use App\Http\Controllers\Admin\ContactMessageController;
use App\Http\Controllers\Admin\ContentRevisionController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\FontController;
use App\Http\Controllers\Admin\HealthController;
use App\Http\Controllers\Admin\HelpController;
use App\Http\Controllers\Admin\MediaController;
use App\Http\Controllers\Admin\MenuController;
use App\Http\Controllers\Admin\PlatformController;
use App\Http\Controllers\Admin\PrivacyController;
use App\Http\Controllers\Admin\ProjectController;
use App\Http\Controllers\Admin\SearchController;
use App\Http\Controllers\Admin\SectionController;
use App\Http\Controllers\Admin\SecurityController;
use App\Http\Controllers\Admin\SiteEditorController;
use App\Http\Controllers\Admin\SocialLinkController;
use App\Http\Controllers\Admin\TestimonialController;
use App\Http\Controllers\Admin\ThemeController;
use App\Http\Controllers\Admin\ThemePresetController;
use App\Http\Controllers\ProjectDetailController;
use App\Http\Controllers\PublicContactController;
use App\Http\Controllers\PublicPortfolioController;
use App\Http\Controllers\PublicTestimonialController;
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
Route::get('/testimonials/new', [PublicTestimonialController::class, 'create'])->name('testimonials.create');
Route::post('/testimonials', [PublicTestimonialController::class, 'store'])
    ->middleware('throttle:3,10')
    ->name('testimonials.store');
Route::get('/preview', [PublicPortfolioController::class, 'preview'])
    ->middleware(['auth', 'auth.session', 'admin'])
    ->name('portfolio.preview');
Route::get('/{asset}', [PublicPortfolioController::class, 'asset'])
    ->where('asset', 'styles\\.css|app\\.mjs|portfolio-data\\.mjs|favicon\\.svg');

Route::middleware('guest')->group(function () {
    Route::get('/admin/login', [AuthController::class, 'create'])->name('login');
    Route::post('/admin/login', [AuthController::class, 'store'])->middleware('throttle:5,1')->name('admin.login');
    Route::get('/admin/login/two-factor', [AuthController::class, 'challenge'])->name('admin.two-factor');
    Route::post('/admin/login/two-factor', [AuthController::class, 'verifyChallenge'])->middleware('throttle:10,1')->name('admin.two-factor.verify');
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
    Route::get('/help', [HelpController::class, 'index'])->name('help.index');
    Route::get('/help/{topic}', [HelpController::class, 'show'])->where('topic', '[a-z]+(?:-[a-z]+)*')->name('help.show');
    Route::get('/search', [SearchController::class, 'index'])->middleware('throttle:admin-search')->name('search');
    Route::get('/health', [HealthController::class, 'index'])->name('health');
    Route::post('/health/check', [HealthController::class, 'check'])->middleware('throttle:5,1')->name('health.check');
    Route::get('/backups', [BackupController::class, 'index'])->name('backups.index');
    Route::post('/backups', [BackupController::class, 'store'])->middleware('throttle:5,1')->name('backups.store');
    Route::post('/backups/{name}/verify', [BackupController::class, 'verify'])->middleware('throttle:10,1')->name('backups.verify');
    Route::post('/backups/{name}/download', [BackupController::class, 'download'])->middleware('throttle:5,1')->name('backups.download');
    Route::delete('/backups/{name}', [BackupController::class, 'destroy'])->middleware('throttle:5,1')->name('backups.destroy');
    Route::get('/privacy', [PrivacyController::class, 'index'])->name('privacy');
    Route::post('/privacy/lookup', [PrivacyController::class, 'lookup'])->middleware('throttle:30,1')->name('privacy.lookup');
    Route::put('/privacy/retention', [PrivacyController::class, 'retention'])->name('privacy.retention');
    Route::post('/privacy/export', [PrivacyController::class, 'export'])->middleware('throttle:10,1')->name('privacy.export');
    Route::delete('/privacy/erase', [PrivacyController::class, 'erase'])->middleware('throttle:5,1')->name('privacy.erase');
    Route::get('/security', [SecurityController::class, 'index'])->name('security');
    Route::delete('/security/sessions', [SecurityController::class, 'logoutOtherSessions'])->middleware('throttle:5,1')->name('security.sessions.destroy');
    Route::post('/security/two-factor', [SecurityController::class, 'startTwoFactor'])->middleware('throttle:5,1')->name('security.two-factor.start');
    Route::post('/security/two-factor/confirm', [SecurityController::class, 'confirmTwoFactor'])->middleware('throttle:5,1')->name('security.two-factor.confirm');
    Route::post('/security/two-factor/recovery-codes', [SecurityController::class, 'regenerateRecoveryCodes'])->middleware('throttle:5,1')->name('security.two-factor.recovery');
    Route::delete('/security/two-factor', [SecurityController::class, 'disableTwoFactor'])->middleware('throttle:5,1')->name('security.two-factor.disable');
    Route::put('/notification-preferences', [PlatformController::class, 'preferences'])->name('platform.preferences');
    Route::post('/notifications/read-all', [PlatformController::class, 'readAll'])->name('platform.notices.read-all');
    Route::post('/platform/check/{kind}', [PlatformController::class, 'scan'])->middleware('throttle:6,1')->name('platform.scan');
    Route::post('/updates/{releaseCandidate}/verify', [PlatformController::class, 'prepare'])->middleware('throttle:3,1')->name('platform.prepare');
    Route::post('/updates/{releaseCandidate}/approve', [PlatformController::class, 'approve'])->middleware('throttle:3,1')->name('platform.approve');
    Route::post('/updates/{releaseCandidate}/deploy', [PlatformController::class, 'deploy'])->middleware('throttle:3,1')->name('platform.deploy');
    Route::post('/updates/{releaseCandidate}/deployment-status', [PlatformController::class, 'deploymentStatus'])->middleware('throttle:6,1')->name('platform.deployment-status');
    Route::post('/github-projects/{githubRepository}/review', [PlatformController::class, 'review'])->name('platform.review');
    Route::patch('/notifications/{platformNotice}', [PlatformController::class, 'notice'])->name('platform.notice');
    Route::get('/site-editor', [SiteEditorController::class, 'edit'])->name('site-editor.edit');
    Route::put('/site-editor', [SiteEditorController::class, 'update'])->name('site-editor.update');
    Route::get('/themes', [ThemeController::class, 'index'])->name('themes.index');
    Route::post('/themes', [ThemePresetController::class, 'store'])->name('themes.store');
    Route::post('/themes/save', [ThemeController::class, 'store'])->name('themes.save');
    Route::post('/themes/apply', [ThemeController::class, 'apply'])->middleware('throttle:10,1')->name('themes.apply');
    Route::post('/themes/export', [ThemeController::class, 'export'])->name('themes.export');
    Route::post('/themes/import', [ThemeController::class, 'import'])->middleware('throttle:10,1')->name('themes.import');
    Route::put('/themes/{themePreset}', [ThemeController::class, 'update'])->name('themes.update');
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
    Route::get('/media/{mediaAsset}/download', [MediaController::class, 'download'])->name('media.download');
    Route::put('/media/{mediaAsset}', [MediaController::class, 'update'])->name('media.update');
    Route::delete('/media/{mediaAsset}', [MediaController::class, 'destroy'])->name('media.destroy');
    Route::get('/fonts', [FontController::class, 'index'])->name('fonts.index');
    Route::post('/fonts', [FontController::class, 'store'])->name('fonts.store');
    Route::put('/fonts/{fontAsset}', [FontController::class, 'update'])->name('fonts.update');
    Route::delete('/fonts/{fontAsset}', [FontController::class, 'destroy'])->name('fonts.destroy');
    Route::get('/testimonials', [TestimonialController::class, 'index'])->name('testimonials.index');
    Route::post('/testimonials', [TestimonialController::class, 'store'])->name('testimonials.store');
    Route::put('/testimonials/{testimonial}', [TestimonialController::class, 'update'])->name('testimonials.update');
    Route::patch('/testimonials/{testimonial}/status', [TestimonialController::class, 'moderate'])->name('testimonials.moderate');
    Route::delete('/testimonials/{testimonial}', [TestimonialController::class, 'destroy'])->name('testimonials.destroy');
    Route::post('/testimonials/submissions', [TestimonialController::class, 'submissions'])->name('testimonials.submissions');
    Route::get('/menus', [MenuController::class, 'index'])->name('menus.index');
    Route::post('/menus/customise', [MenuController::class, 'customise'])->name('menus.customise');
    Route::post('/menus/automatic', [MenuController::class, 'automatic'])->name('menus.automatic');
    Route::post('/menus/items', [MenuController::class, 'store'])->name('menus.store');
    Route::put('/menus/items/{menuItem}', [MenuController::class, 'update'])->name('menus.update');
    Route::patch('/menus/items/{menuItem}/move', [MenuController::class, 'move'])->name('menus.move');
    Route::delete('/menus/items/{menuItem}', [MenuController::class, 'destroy'])->name('menus.destroy');
    Route::get('/social', [SocialLinkController::class, 'index'])->name('social.index');
    Route::post('/social', [SocialLinkController::class, 'store'])->name('social.store');
    Route::put('/social/{socialLink}', [SocialLinkController::class, 'update'])->name('social.update');
    Route::patch('/social/{socialLink}/move', [SocialLinkController::class, 'move'])->name('social.move');
    Route::delete('/social/{socialLink}', [SocialLinkController::class, 'destroy'])->name('social.destroy');
    Route::get('/inbox', [ContactMessageController::class, 'index'])->name('inbox.index');
    Route::patch('/inbox/{contactMessage}', [ContactMessageController::class, 'update'])->name('inbox.update');
    Route::delete('/inbox/{contactMessage}', [ContactMessageController::class, 'destroy'])->name('inbox.destroy');
});
