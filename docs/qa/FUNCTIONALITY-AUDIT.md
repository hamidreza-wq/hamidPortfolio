# Functionality audit

## Scope and evidence policy

QA started 2026-11-03 at commit `ce7787c`. Owner database and port 8000 are not test targets. Dedicated `hamid_portfolio_qa_test`, port 8147 and session-private storage are used. Local server is MariaDB 10.4.32 via PDO MySQL, **not Oracle MySQL**. Do not interpret pending checks as passed.

## Complete tracked-file inventory

### Canonical frontend (5)

- [app.mjs](../../app.mjs)
- [favicon.svg](../../favicon.svg)
- [index.html](../../index.html)
- [portfolio-data.mjs](../../portfolio-data.mjs)
- [styles.css](../../styles.css)

### Controllers (15)

- [backend/app/Http/Controllers/Admin/AuthController.php](../../backend/app/Http/Controllers/Admin/AuthController.php)
- [backend/app/Http/Controllers/Admin/ContactMessageController.php](../../backend/app/Http/Controllers/Admin/ContactMessageController.php)
- [backend/app/Http/Controllers/Admin/ContentRevisionController.php](../../backend/app/Http/Controllers/Admin/ContentRevisionController.php)
- [backend/app/Http/Controllers/Admin/DashboardController.php](../../backend/app/Http/Controllers/Admin/DashboardController.php)
- [backend/app/Http/Controllers/Admin/FontController.php](../../backend/app/Http/Controllers/Admin/FontController.php)
- [backend/app/Http/Controllers/Admin/MediaController.php](../../backend/app/Http/Controllers/Admin/MediaController.php)
- [backend/app/Http/Controllers/Admin/PlatformController.php](../../backend/app/Http/Controllers/Admin/PlatformController.php)
- [backend/app/Http/Controllers/Admin/ProjectController.php](../../backend/app/Http/Controllers/Admin/ProjectController.php)
- [backend/app/Http/Controllers/Admin/SectionController.php](../../backend/app/Http/Controllers/Admin/SectionController.php)
- [backend/app/Http/Controllers/Admin/SiteEditorController.php](../../backend/app/Http/Controllers/Admin/SiteEditorController.php)
- [backend/app/Http/Controllers/Admin/ThemePresetController.php](../../backend/app/Http/Controllers/Admin/ThemePresetController.php)
- [backend/app/Http/Controllers/Controller.php](../../backend/app/Http/Controllers/Controller.php)
- [backend/app/Http/Controllers/ProjectDetailController.php](../../backend/app/Http/Controllers/ProjectDetailController.php)
- [backend/app/Http/Controllers/PublicContactController.php](../../backend/app/Http/Controllers/PublicContactController.php)
- [backend/app/Http/Controllers/PublicPortfolioController.php](../../backend/app/Http/Controllers/PublicPortfolioController.php)

### Models (18)

- [backend/app/Models/AuditLog.php](../../backend/app/Models/AuditLog.php)
- [backend/app/Models/ContactMessage.php](../../backend/app/Models/ContactMessage.php)
- [backend/app/Models/ContentRevision.php](../../backend/app/Models/ContentRevision.php)
- [backend/app/Models/FontAsset.php](../../backend/app/Models/FontAsset.php)
- [backend/app/Models/GithubRepository.php](../../backend/app/Models/GithubRepository.php)
- [backend/app/Models/MediaAsset.php](../../backend/app/Models/MediaAsset.php)
- [backend/app/Models/NotificationPreference.php](../../backend/app/Models/NotificationPreference.php)
- [backend/app/Models/PlatformNotice.php](../../backend/app/Models/PlatformNotice.php)
- [backend/app/Models/PlatformRun.php](../../backend/app/Models/PlatformRun.php)
- [backend/app/Models/PortfolioSection.php](../../backend/app/Models/PortfolioSection.php)
- [backend/app/Models/PortfolioSetting.php](../../backend/app/Models/PortfolioSetting.php)
- [backend/app/Models/Project.php](../../backend/app/Models/Project.php)
- [backend/app/Models/ReleaseCandidate.php](../../backend/app/Models/ReleaseCandidate.php)
- [backend/app/Models/SeoFinding.php](../../backend/app/Models/SeoFinding.php)
- [backend/app/Models/Skill.php](../../backend/app/Models/Skill.php)
- [backend/app/Models/SkillCategory.php](../../backend/app/Models/SkillCategory.php)
- [backend/app/Models/ThemePreset.php](../../backend/app/Models/ThemePreset.php)
- [backend/app/Models/User.php](../../backend/app/Models/User.php)

### Services (18)

- [backend/app/Services/AssetStorage.php](../../backend/app/Services/AssetStorage.php)
- [backend/app/Services/AssetUpload.php](../../backend/app/Services/AssetUpload.php)
- [backend/app/Services/AssetUsage.php](../../backend/app/Services/AssetUsage.php)
- [backend/app/Services/ContactSubmissionGuard.php](../../backend/app/Services/ContactSubmissionGuard.php)
- [backend/app/Services/ContentHistory.php](../../backend/app/Services/ContentHistory.php)
- [backend/app/Services/DestructiveDatabaseGuard.php](../../backend/app/Services/DestructiveDatabaseGuard.php)
- [backend/app/Services/FontLibrary.php](../../backend/app/Services/FontLibrary.php)
- [backend/app/Services/MediaLibrary.php](../../backend/app/Services/MediaLibrary.php)
- [backend/app/Services/Platform/GithubApi.php](../../backend/app/Services/Platform/GithubApi.php)
- [backend/app/Services/Platform/GithubDiscovery.php](../../backend/app/Services/Platform/GithubDiscovery.php)
- [backend/app/Services/Platform/IntegrationFailure.php](../../backend/app/Services/Platform/IntegrationFailure.php)
- [backend/app/Services/Platform/Monitor.php](../../backend/app/Services/Platform/Monitor.php)
- [backend/app/Services/Platform/Notices.php](../../backend/app/Services/Platform/Notices.php)
- [backend/app/Services/Platform/PublicDns.php](../../backend/app/Services/Platform/PublicDns.php)
- [backend/app/Services/Platform/Releases.php](../../backend/app/Services/Platform/Releases.php)
- [backend/app/Services/Platform/SeoMonitor.php](../../backend/app/Services/Platform/SeoMonitor.php)
- [backend/app/Services/PortfolioRenderer.php](../../backend/app/Services/PortfolioRenderer.php)
- [backend/app/Services/PortfolioSeo.php](../../backend/app/Services/PortfolioSeo.php)

### Migrations (17)

- [backend/database/migrations/0001_01_01_000000_create_users_table.php](../../backend/database/migrations/0001_01_01_000000_create_users_table.php)
- [backend/database/migrations/0001_01_01_000001_create_cache_table.php](../../backend/database/migrations/0001_01_01_000001_create_cache_table.php)
- [backend/database/migrations/0001_01_01_000002_create_jobs_table.php](../../backend/database/migrations/0001_01_01_000002_create_jobs_table.php)
- [backend/database/migrations/2026_11_02_233412_create_portfolio_settings_table.php](../../backend/database/migrations/2026_11_02_233412_create_portfolio_settings_table.php)
- [backend/database/migrations/2026_11_02_233415_create_portfolio_sections_table.php](../../backend/database/migrations/2026_11_02_233415_create_portfolio_sections_table.php)
- [backend/database/migrations/2026_11_02_233419_create_projects_table.php](../../backend/database/migrations/2026_11_02_233419_create_projects_table.php)
- [backend/database/migrations/2026_11_02_233422_create_skill_categories_table.php](../../backend/database/migrations/2026_11_02_233422_create_skill_categories_table.php)
- [backend/database/migrations/2026_11_02_233425_create_skills_table.php](../../backend/database/migrations/2026_11_02_233425_create_skills_table.php)
- [backend/database/migrations/2026_11_02_233428_create_theme_presets_table.php](../../backend/database/migrations/2026_11_02_233428_create_theme_presets_table.php)
- [backend/database/migrations/2026_11_02_233431_create_font_assets_table.php](../../backend/database/migrations/2026_11_02_233431_create_font_assets_table.php)
- [backend/database/migrations/2026_11_02_233433_create_contact_messages_table.php](../../backend/database/migrations/2026_11_02_233433_create_contact_messages_table.php)
- [backend/database/migrations/2026_11_02_233437_create_audit_logs_table.php](../../backend/database/migrations/2026_11_02_233437_create_audit_logs_table.php)
- [backend/database/migrations/2026_11_02_233500_create_media_assets_table.php](../../backend/database/migrations/2026_11_02_233500_create_media_assets_table.php)
- [backend/database/migrations/2026_11_03_010000_complete_content_and_seo.php](../../backend/database/migrations/2026_11_03_010000_complete_content_and_seo.php)
- [backend/database/migrations/2026_11_03_010100_extend_managed_assets.php](../../backend/database/migrations/2026_11_03_010100_extend_managed_assets.php)
- [backend/database/migrations/2026_11_04_010000_add_platform_monitoring.php](../../backend/database/migrations/2026_11_04_010000_add_platform_monitoring.php)
- [backend/database/migrations/2026_11_04_010100_add_notification_preferences.php](../../backend/database/migrations/2026_11_04_010100_add_notification_preferences.php)

### Views (30)

- [backend/resources/views/admin/dashboard.blade.php](../../backend/resources/views/admin/dashboard.blade.php)
- [backend/resources/views/admin/fonts.blade.php](../../backend/resources/views/admin/fonts.blade.php)
- [backend/resources/views/admin/inbox.blade.php](../../backend/resources/views/admin/inbox.blade.php)
- [backend/resources/views/admin/layout.blade.php](../../backend/resources/views/admin/layout.blade.php)
- [backend/resources/views/admin/login.blade.php](../../backend/resources/views/admin/login.blade.php)
- [backend/resources/views/admin/media.blade.php](../../backend/resources/views/admin/media.blade.php)
- [backend/resources/views/admin/partials/font-select.blade.php](../../backend/resources/views/admin/partials/font-select.blade.php)
- [backend/resources/views/admin/partials/media-select.blade.php](../../backend/resources/views/admin/partials/media-select.blade.php)
- [backend/resources/views/admin/partials/project-fields.blade.php](../../backend/resources/views/admin/partials/project-fields.blade.php)
- [backend/resources/views/admin/partials/section-fields.blade.php](../../backend/resources/views/admin/partials/section-fields.blade.php)
- [backend/resources/views/admin/partials/theme-presets.blade.php](../../backend/resources/views/admin/partials/theme-presets.blade.php)
- [backend/resources/views/admin/platform/check.blade.php](../../backend/resources/views/admin/platform/check.blade.php)
- [backend/resources/views/admin/platform/github.blade.php](../../backend/resources/views/admin/platform/github.blade.php)
- [backend/resources/views/admin/platform/history.blade.php](../../backend/resources/views/admin/platform/history.blade.php)
- [backend/resources/views/admin/platform/notifications.blade.php](../../backend/resources/views/admin/platform/notifications.blade.php)
- [backend/resources/views/admin/platform/seo.blade.php](../../backend/resources/views/admin/platform/seo.blade.php)
- [backend/resources/views/admin/platform/updates.blade.php](../../backend/resources/views/admin/platform/updates.blade.php)
- [backend/resources/views/admin/projects.blade.php](../../backend/resources/views/admin/projects.blade.php)
- [backend/resources/views/admin/revisions.blade.php](../../backend/resources/views/admin/revisions.blade.php)
- [backend/resources/views/admin/sections.blade.php](../../backend/resources/views/admin/sections.blade.php)
- [backend/resources/views/admin/site-editor.blade.php](../../backend/resources/views/admin/site-editor.blade.php)
- [backend/resources/views/mail/platform-digest.blade.php](../../backend/resources/views/mail/platform-digest.blade.php)
- [backend/resources/views/maintenance.blade.php](../../backend/resources/views/maintenance.blade.php)
- [backend/resources/views/portfolio/project-links.blade.php](../../backend/resources/views/portfolio/project-links.blade.php)
- [backend/resources/views/portfolio/project.blade.php](../../backend/resources/views/portfolio/project.blade.php)
- [backend/resources/views/portfolio/sections.blade.php](../../backend/resources/views/portfolio/sections.blade.php)
- [backend/resources/views/portfolio/seo.blade.php](../../backend/resources/views/portfolio/seo.blade.php)
- [backend/resources/views/portfolio/sitemap.blade.php](../../backend/resources/views/portfolio/sitemap.blade.php)
- [backend/resources/views/portfolio/skills.blade.php](../../backend/resources/views/portfolio/skills.blade.php)
- [backend/storage/framework/views/.gitignore](../../backend/storage/framework/views/.gitignore)

### Configuration and bootstrapping (17)

- [backend/bootstrap/app.php](../../backend/bootstrap/app.php)
- [backend/bootstrap/cache/.gitignore](../../backend/bootstrap/cache/.gitignore)
- [backend/bootstrap/providers.php](../../backend/bootstrap/providers.php)
- [backend/config/app.php](../../backend/config/app.php)
- [backend/config/auth.php](../../backend/config/auth.php)
- [backend/config/cache.php](../../backend/config/cache.php)
- [backend/config/database.php](../../backend/config/database.php)
- [backend/config/filesystems.php](../../backend/config/filesystems.php)
- [backend/config/logging.php](../../backend/config/logging.php)
- [backend/config/mail.php](../../backend/config/mail.php)
- [backend/config/platform.php](../../backend/config/platform.php)
- [backend/config/portfolio.php](../../backend/config/portfolio.php)
- [backend/config/queue.php](../../backend/config/queue.php)
- [backend/config/services.php](../../backend/config/services.php)
- [backend/config/session.php](../../backend/config/session.php)
- [backend/routes/console.php](../../backend/routes/console.php)
- [backend/routes/web.php](../../backend/routes/web.php)

### Tests and automation (16)

- [backend/app/Console/Commands/AuditPlatformDependencies.php](../../backend/app/Console/Commands/AuditPlatformDependencies.php)
- [backend/app/Console/Commands/CheckPlatform.php](../../backend/app/Console/Commands/CheckPlatform.php)
- [backend/app/Console/Commands/CreatePortfolioAdmin.php](../../backend/app/Console/Commands/CreatePortfolioAdmin.php)
- [backend/app/Console/Commands/SendPlatformDigests.php](../../backend/app/Console/Commands/SendPlatformDigests.php)
- [backend/app/Jobs/RunPlatformMonitor.php](../../backend/app/Jobs/RunPlatformMonitor.php)
- [backend/app/Mail/PlatformDigest.php](../../backend/app/Mail/PlatformDigest.php)
- [backend/tests/Feature/AdminPortfolioTest.php](../../backend/tests/Feature/AdminPortfolioTest.php)
- [backend/tests/Feature/AssetManagementTest.php](../../backend/tests/Feature/AssetManagementTest.php)
- [backend/tests/Feature/ContentSeoTest.php](../../backend/tests/Feature/ContentSeoTest.php)
- [backend/tests/Feature/ExampleTest.php](../../backend/tests/Feature/ExampleTest.php)
- [backend/tests/Feature/PlatformIntegrationTest.php](../../backend/tests/Feature/PlatformIntegrationTest.php)
- [backend/tests/Feature/PublicPortfolioTest.php](../../backend/tests/Feature/PublicPortfolioTest.php)
- [backend/tests/Feature/RequestSecurityTest.php](../../backend/tests/Feature/RequestSecurityTest.php)
- [backend/tests/Feature/ThemePresetTest.php](../../backend/tests/Feature/ThemePresetTest.php)
- [backend/tests/TestCase.php](../../backend/tests/TestCase.php)
- [backend/tests/Unit/ExampleTest.php](../../backend/tests/Unit/ExampleTest.php)

### Other tracked files (43)

- [CHANGELOG.md](../../CHANGELOG.md)
- [DEPLOYMENT.md](../../DEPLOYMENT.md)
- [HARDENING-REPORT.md](../../HARDENING-REPORT.md)
- [PLATFORM-REPORT.md](../../PLATFORM-REPORT.md)
- [README.md](../../README.md)
- [VERSION](../../VERSION)
- [backend/.editorconfig](../../backend/.editorconfig)
- [backend/.env.example](../../backend/.env.example)
- [backend/.env.testing.example](../../backend/.env.testing.example)
- [backend/.gitattributes](../../backend/.gitattributes)
- [backend/.gitignore](../../backend/.gitignore)
- [backend/README.md](../../backend/README.md)
- [backend/app/Http/Middleware/EnsureAdmin.php](../../backend/app/Http/Middleware/EnsureAdmin.php)
- [backend/app/Http/Middleware/SecurityHeaders.php](../../backend/app/Http/Middleware/SecurityHeaders.php)
- [backend/app/Providers/AppServiceProvider.php](../../backend/app/Providers/AppServiceProvider.php)
- [backend/artisan](../../backend/artisan)
- [backend/composer.json](../../backend/composer.json)
- [backend/composer.lock](../../backend/composer.lock)
- [backend/database/factories/UserFactory.php](../../backend/database/factories/UserFactory.php)
- [backend/database/seeders/DatabaseSeeder.php](../../backend/database/seeders/DatabaseSeeder.php)
- [backend/database/seeders/PortfolioSeeder.php](../../backend/database/seeders/PortfolioSeeder.php)
- [backend/docs/content-and-seo.md](../../backend/docs/content-and-seo.md)
- [backend/docs/managed-assets.md](../../backend/docs/managed-assets.md)
- [backend/docs/platform-operations.md](../../backend/docs/platform-operations.md)
- [backend/package.json](../../backend/package.json)
- [backend/phpunit.xml](../../backend/phpunit.xml)
- [backend/public/.htaccess](../../backend/public/.htaccess)
- [backend/public/favicon.ico](../../backend/public/favicon.ico)
- [backend/public/index.php](../../backend/public/index.php)
- `backend/public/robots.txt` existed at baseline; removed because it shadowed dynamic robots policy (QA-03).
- [backend/resources/css/app.css](../../backend/resources/css/app.css)
- [backend/resources/js/app.js](../../backend/resources/js/app.js)
- [backend/resources/js/bootstrap.js](../../backend/resources/js/bootstrap.js)
- [backend/storage/app/.gitignore](../../backend/storage/app/.gitignore)
- [backend/storage/app/private/.gitignore](../../backend/storage/app/private/.gitignore)
- [backend/storage/app/public/.gitignore](../../backend/storage/app/public/.gitignore)
- [backend/storage/framework/.gitignore](../../backend/storage/framework/.gitignore)
- [backend/storage/framework/cache/.gitignore](../../backend/storage/framework/cache/.gitignore)
- [backend/storage/framework/cache/data/.gitignore](../../backend/storage/framework/cache/data/.gitignore)
- [backend/storage/framework/sessions/.gitignore](../../backend/storage/framework/sessions/.gitignore)
- [backend/storage/framework/testing/.gitignore](../../backend/storage/framework/testing/.gitignore)
- [backend/storage/logs/.gitignore](../../backend/storage/logs/.gitignore)
- [backend/vite.config.js](../../backend/vite.config.js)

## Route inventory

Middleware below is resolved by Laravel route:list; guest/admin redirects are intentional, not broken links. Mutation routes require CSRF in live HTTP, plus listed authorization.

| Method | URI | Handler | Middleware |
|---|---|---|---|
| GET / HEAD | `/` | `App\Http\Controllers\PublicPortfolioController@index` | web |
| GET / HEAD | `admin` | `App\Http\Controllers\Admin\DashboardController@index` | web, auth, auth.session, admin |
| GET / HEAD | `admin/fonts` | `App\Http\Controllers\Admin\FontController@index` | web, auth, auth.session, admin |
| POST | `admin/fonts` | `App\Http\Controllers\Admin\FontController@store` | web, auth, auth.session, admin |
| PUT | `admin/fonts/{fontAsset}` | `App\Http\Controllers\Admin\FontController@update` | web, auth, auth.session, admin |
| DELETE | `admin/fonts/{fontAsset}` | `App\Http\Controllers\Admin\FontController@destroy` | web, auth, auth.session, admin |
| GET / HEAD | `admin/github-projects` | `App\Http\Controllers\Admin\PlatformController@github` | web, auth, auth.session, admin |
| POST | `admin/github-projects/{githubRepository}/review` | `App\Http\Controllers\Admin\PlatformController@review` | web, auth, auth.session, admin |
| GET / HEAD | `admin/inbox` | `App\Http\Controllers\Admin\ContactMessageController@index` | web, auth, auth.session, admin |
| PATCH | `admin/inbox/{contactMessage}` | `App\Http\Controllers\Admin\ContactMessageController@update` | web, auth, auth.session, admin |
| DELETE | `admin/inbox/{contactMessage}` | `App\Http\Controllers\Admin\ContactMessageController@destroy` | web, auth, auth.session, admin |
| GET / HEAD | `admin/login` | `App\Http\Controllers\Admin\AuthController@create` | web, guest |
| POST | `admin/login` | `App\Http\Controllers\Admin\AuthController@store` | web, guest, throttle:5,1 |
| POST | `admin/logout` | `App\Http\Controllers\Admin\AuthController@destroy` | web, auth, auth.session, admin |
| GET / HEAD | `admin/media` | `App\Http\Controllers\Admin\MediaController@index` | web, auth, auth.session, admin |
| POST | `admin/media` | `App\Http\Controllers\Admin\MediaController@store` | web, auth, auth.session, admin |
| PUT | `admin/media/{mediaAsset}` | `App\Http\Controllers\Admin\MediaController@update` | web, auth, auth.session, admin |
| DELETE | `admin/media/{mediaAsset}` | `App\Http\Controllers\Admin\MediaController@destroy` | web, auth, auth.session, admin |
| PUT | `admin/notification-preferences` | `App\Http\Controllers\Admin\PlatformController@preferences` | web, auth, auth.session, admin |
| GET / HEAD | `admin/notifications` | `App\Http\Controllers\Admin\PlatformController@notifications` | web, auth, auth.session, admin |
| PATCH | `admin/notifications/{platformNotice}` | `App\Http\Controllers\Admin\PlatformController@notice` | web, auth, auth.session, admin |
| POST | `admin/platform/check/{kind}` | `App\Http\Controllers\Admin\PlatformController@scan` | web, auth, auth.session, admin, throttle:6,1 |
| GET / HEAD | `admin/projects` | `App\Http\Controllers\Admin\ProjectController@index` | web, auth, auth.session, admin |
| POST | `admin/projects` | `App\Http\Controllers\Admin\ProjectController@store` | web, auth, auth.session, admin |
| PUT | `admin/projects/{project}` | `App\Http\Controllers\Admin\ProjectController@update` | web, auth, auth.session, admin |
| DELETE | `admin/projects/{project}` | `App\Http\Controllers\Admin\ProjectController@destroy` | web, auth, auth.session, admin |
| GET / HEAD | `admin/revisions` | `App\Http\Controllers\Admin\ContentRevisionController@index` | web, auth, auth.session, admin |
| POST | `admin/revisions/{contentRevision}/restore` | `App\Http\Controllers\Admin\ContentRevisionController@restore` | web, auth, auth.session, admin |
| GET / HEAD | `admin/sections` | `App\Http\Controllers\Admin\SectionController@index` | web, auth, auth.session, admin |
| POST | `admin/sections` | `App\Http\Controllers\Admin\SectionController@store` | web, auth, auth.session, admin |
| PUT | `admin/sections/{portfolioSection}` | `App\Http\Controllers\Admin\SectionController@update` | web, auth, auth.session, admin |
| DELETE | `admin/sections/{portfolioSection}` | `App\Http\Controllers\Admin\SectionController@destroy` | web, auth, auth.session, admin |
| GET / HEAD | `admin/seo-monitor` | `App\Http\Controllers\Admin\PlatformController@seo` | web, auth, auth.session, admin |
| GET / HEAD | `admin/site-editor` | `App\Http\Controllers\Admin\SiteEditorController@edit` | web, auth, auth.session, admin |
| PUT | `admin/site-editor` | `App\Http\Controllers\Admin\SiteEditorController@update` | web, auth, auth.session, admin |
| POST | `admin/themes` | `App\Http\Controllers\Admin\ThemePresetController@store` | web, auth, auth.session, admin |
| DELETE | `admin/themes/{themePreset}` | `App\Http\Controllers\Admin\ThemePresetController@destroy` | web, auth, auth.session, admin |
| GET / HEAD | `admin/updates` | `App\Http\Controllers\Admin\PlatformController@updates` | web, auth, auth.session, admin |
| POST | `admin/updates/{releaseCandidate}/approve` | `App\Http\Controllers\Admin\PlatformController@approve` | web, auth, auth.session, admin, throttle:3,1 |
| POST | `admin/updates/{releaseCandidate}/verify` | `App\Http\Controllers\Admin\PlatformController@prepare` | web, auth, auth.session, admin, throttle:3,1 |
| POST | `contact` | `App\Http\Controllers\PublicContactController@store` | web, throttle:3,10 |
| GET / HEAD | `fonts/{fontAsset}` | `App\Http\Controllers\Admin\FontController@show` | web |
| GET / HEAD | `media/{mediaAsset}` | `App\Http\Controllers\Admin\MediaController@show` | web |
| GET / HEAD | `preview` | `App\Http\Controllers\PublicPortfolioController@preview` | web, auth, auth.session, admin |
| GET / HEAD | `projects/{slug}` | `App\Http\Controllers\ProjectDetailController@show` | web |
| GET / HEAD | `robots.txt` | `App\Http\Controllers\ProjectDetailController@robots` | web |
| GET / HEAD | `sitemap.xml` | `App\Http\Controllers\ProjectDetailController@sitemap` | web |
| GET / HEAD | `storage/{path}` | `Closure` |  |
| PUT | `storage/{path}` | `Closure` |  |
| GET / HEAD | `up` | `Illuminate\Foundation\Http\HealthCheck` |  |
| GET / HEAD | `{asset}` | `App\Http\Controllers\PublicPortfolioController@asset` | web |

## Form and interactive-source inventory

This inventory locates literal form/button/input/select/textarea/link/dialog declarations. Runtime-generated controls are inventoried separately in browser evidence.

| Source | Forms | Buttons | Inputs | Selects | Textareas | Links | Dialogs |
|---|---:|---:|---:|---:|---:|---:|---:|
| backend/resources/views/admin/dashboard.blade.php | 0 | 0 | 0 | 0 | 0 | 7 | 0 |
| backend/resources/views/admin/fonts.blade.php | 3 | 3 | 6 | 2 | 0 | 1 | 0 |
| backend/resources/views/admin/inbox.blade.php | 3 | 3 | 1 | 2 | 1 | 1 | 0 |
| backend/resources/views/admin/layout.blade.php | 1 | 2 | 0 | 0 | 0 | 15 | 0 |
| backend/resources/views/admin/login.blade.php | 1 | 1 | 2 | 0 | 0 | 1 | 0 |
| backend/resources/views/admin/media.blade.php | 4 | 4 | 8 | 0 | 0 | 1 | 0 |
| backend/resources/views/admin/partials/font-select.blade.php | 0 | 0 | 0 | 1 | 0 | 1 | 0 |
| backend/resources/views/admin/partials/media-select.blade.php | 0 | 0 | 0 | 1 | 0 | 1 | 0 |
| backend/resources/views/admin/partials/project-fields.blade.php | 0 | 0 | 16 | 2 | 6 | 0 | 0 |
| backend/resources/views/admin/partials/section-fields.blade.php | 0 | 0 | 9 | 2 | 2 | 0 | 0 |
| backend/resources/views/admin/partials/theme-presets.blade.php | 2 | 3 | 2 | 0 | 0 | 0 | 0 |
| backend/resources/views/admin/platform/check.blade.php | 1 | 1 | 1 | 0 | 0 | 0 | 0 |
| backend/resources/views/admin/platform/github.blade.php | 2 | 3 | 2 | 0 | 0 | 2 | 0 |
| backend/resources/views/admin/platform/history.blade.php | 0 | 0 | 0 | 0 | 0 | 0 | 0 |
| backend/resources/views/admin/platform/notifications.blade.php | 2 | 4 | 1 | 1 | 0 | 1 | 0 |
| backend/resources/views/admin/platform/seo.blade.php | 0 | 0 | 0 | 0 | 0 | 3 | 0 |
| backend/resources/views/admin/platform/updates.blade.php | 2 | 2 | 4 | 0 | 0 | 0 | 0 |
| backend/resources/views/admin/projects.blade.php | 4 | 4 | 9 | 0 | 0 | 3 | 0 |
| backend/resources/views/admin/revisions.blade.php | 1 | 1 | 1 | 0 | 0 | 0 | 0 |
| backend/resources/views/admin/sections.blade.php | 3 | 3 | 0 | 0 | 0 | 2 | 0 |
| backend/resources/views/admin/site-editor.blade.php | 1 | 1 | 38 | 1 | 4 | 5 | 0 |
| backend/resources/views/mail/platform-digest.blade.php | 0 | 0 | 0 | 0 | 0 | 1 | 0 |
| backend/resources/views/maintenance.blade.php | 0 | 0 | 0 | 0 | 0 | 0 | 0 |
| backend/resources/views/portfolio/project-links.blade.php | 0 | 0 | 0 | 0 | 0 | 1 | 0 |
| backend/resources/views/portfolio/project.blade.php | 0 | 0 | 0 | 0 | 0 | 3 | 0 |
| backend/resources/views/portfolio/sections.blade.php | 0 | 0 | 0 | 0 | 0 | 0 | 0 |
| backend/resources/views/portfolio/seo.blade.php | 0 | 0 | 0 | 0 | 0 | 0 | 0 |
| backend/resources/views/portfolio/sitemap.blade.php | 0 | 0 | 0 | 0 | 0 | 0 | 0 |
| backend/resources/views/portfolio/skills.blade.php | 0 | 0 | 0 | 0 | 0 | 0 | 0 |
| index.html | 0 | 6 | 1 | 0 | 0 | 15 | 1 |

## Execution matrix

Inventory completeness is not the same as exhaustive behavioral coverage. The 179 baseline tracked files and 51 resolved routes above were inventoried; they were not all manually inspected line-by-line. The full automated PHP suite was run, the security reviewer inspected the relevant trust boundaries, and browser checks below exercised representative real operations rather than claiming every combination.

| Surface | Real visible-browser/HTTP evidence | Automated evidence | Status/limit |
|---|---|---|---|
| Public home/about/work/craft/journey/contact/footer | Real canonical HTML/CSS/modules, managed headings, mobile menu/anchor close, all baseline fragment targets exist | Edge keyboard skip link and public initialization | Passed for exercised states |
| Search/filter/empty state | Unmatched query hides grid; OnePrayer search gives one card; web/tools/mobile/all filters give 3/1/1/5 cards | Edge search/filter assertions | Passed with seeded portfolio |
| Project dialog | Open, repository/detail link roles, Escape/backdrop close, focus returned to trigger; dimensions at ten sizes | Native Edge dialog/Escape/focus test | Passed |
| Contact | Required/email native validation; legitimate synthetic submission acknowledged and appeared in QA inbox | PHP timing/honeypot/replay/rates/validation tests; raw missing-forgery-metadata POST 419 | Passed locally; no real email delivery |
| Login/authorization | Private fixture login; anonymous admin/preview redirects; actual repeated-run 429 | PHP non-admin/session/logout/rate tests | Passed locally; no visible ordinary-user login |
| Dashboard | Real seeded totals, draft changes after discovery, unread inbox state; no invented analytics | PHP dashboard/admin assertions | Passed; production analytics not configured |
| Actual-frontend preview | Display name, primary color and Georgia font changed in real iframe; anonymous HTML unchanged before save; explicit publication changed public HTML | Private preview/source-origin PHP tests and real Edge publication boundary test | Passed; no separate template |
| Site editor/skills | Name/theme/font/media changes saved; skill-group/name/item changes appeared publicly and were restored | PHP validation and full site/revision tests | Partial: representative fields, not every input/value combination |
| Visibility/publication | All built-ins hidden; public JSON empty and DOM removed; authenticated preview still available; restored afterward | New privacy PHP/Edge regressions | Fixed and passed |
| Maintenance/indexing | QA maintenance home 503, authenticated preview 200, robots disallow and sitemap empty; restored to allow/admin+preview exclusions/sitemap | PHP indexing/maintenance/canonical tests | Passed live after static robots fix |
| Project CRUD/search/SEO | Create unpublished fixture (detail 404), publish (200/title/description/sitemap), search one result, confirm delete (404) | PHP project validation/slugs/gallery/attribution/drafts | Passed for exercised operations |
| Structured sections | Create hidden custom block with items; absent from public body/JSON; publish then delete | PHP structured types/escape/navigation/order/protected built-ins | Passed; every custom type not manually instantiated |
| Presets | Saved named draft palette, loaded through editor button without automatic publication | PHP palette validation/save tests | Partial: browser save/load; deletion not manually exercised |
| Content history | Saved known site change, confirmed restore, public display name restored; replaced content retained in history | PHP site/project/section restore tests | Partial: site restore visible; other scopes automated |
| Media | Generated PNG uploaded/re-encoded (400x240), served HTTP 200; alt/label edit/replacement with stable URL; referenced delete disabled; separate unused image deleted | PHP invalid signatures/limits/GD failure/reference/storage tests | Passed for exercised operations; not all formats manually uploaded |
| Fonts | OFL-licensed Abel fixture uploaded with source/licence, selected and published; actual `document.fonts` loaded; history reference protected it; separate unused font deactivate/reactivate/delete | PHP signatures/licence/references/font serving | Passed for exercised operations; not every format/weight manually uploaded |
| Inbox | Synthetic contact appeared, status archived/private note saved; search empty/filter archived; confirm delete | PHP status/search/validation/escaping | Partial: pagination and large-volume inbox not manually generated |
| Official updates | Real check failed explicitly: no published official release; activation-disabled message and history shown | PHP valid/bad signatures, digests/ZIP/runtime/password-confirmed approval fixtures | Partial: fixtures pass, no real signed release/activation |
| GitHub discovery | Actual anonymous API scan successful; four review records; real drafts created; review approval leaves publication separate | PHP identity/forks/archives/renames/editor preservation/stable approval tests | Passed public discovery; private auth not implemented |
| GitHub queue search/reject/pagination | Page visible and responsive | PHP integration tests cover queue state changes | Partial: browser approval only |
| SEO monitor | Actual scan failed clearly for absent public HTTPS origin; no fake clean result | PHP real-HTML fixtures, findings/history/DNS/redirect bounds | Partial: production scan blocked by configuration; Search Console not implemented |
| Notifications | Category form/daily frequency persisted, unread notice marked read, no real mail sent (`array` transport) | PHP dedup/read/dismiss/snooze/digest/submission-failure tests | Partial: remaining actions automated, SMTP receipt not tested |
| Schedules/jobs/commands | Inventory includes four platform/admin commands, monitor job and digest mail | Schedules/source: releases `0 */6 * * *`, GitHub `15 */6 * * *`, SEO `30 3 * * *`, audit 02:00, digest 08:00 UTC, overlap/single-server locks | Not verified as installed/running on a host |
| Dependencies/build | Initial missing-lock/Vite failures, ordinary npm restoration succeeded, audits/build/Pint/syntax run | See test-results report | Passed final tools; not a deployment approval |
| Links | 45 occurrences, 25 unique HTTP destinations, finite public-only external HEAD/GET | URL/location/status/final/result table | Four repository destinations unavailable; no fabricated replacements |
| Responsive/accessibility/performance | Ten public/dialog sizes and 120 admin combinations; evidence captures; local timings | Edge skip/dialog/drawer/particle tests | Partial certification; see dedicated report |

### Added QA source/configuration surfaces

- [package-lock.json](../../backend/package-lock.json): reproducible audited dependency tree.
- [playwright.config.mjs](../../backend/playwright.config.mjs): explicit isolated-server guard, installed Edge, one worker, no credential-bearing traces/screenshots on failure.
- [portfolio.spec.mjs](../../backend/tests/browser/portfolio.spec.mjs): functional, privacy, accessibility, animation and screenshot regressions.
- Six reports in this folder and [screenshot evidence](screenshots/); generated build/node_modules/test outputs are ignored.

## Assessment and local access

**Partial, not deployment-ready.** Tests are meaningful and bugs were repaired, but unavailable repository URLs and unconfigured real integrations remain failures/open gates, not “passed.”

- QA public: <http://127.0.0.1:8147/>
- QA dashboard: <http://127.0.0.1:8147/admin> (the visible browser remains authenticated to the temporary QA administrator).
- Owner application: existing port 8000, not used as a QA mutation target.

QA fixtures and server storage are separate from owner storage. No public registration, bypass login endpoint, default repository password or fake production credentials were added. The QA server is left running as requested. Final evidence is in [TEST-RESULTS.md](TEST-RESULTS.md), [LINK-AUDIT.md](LINK-AUDIT.md), [SECURITY-AUDIT.md](SECURITY-AUDIT.md), [RESPONSIVE-AUDIT.md](RESPONSIVE-AUDIT.md) and [BUG-FIX-REPORT.md](BUG-FIX-REPORT.md).
