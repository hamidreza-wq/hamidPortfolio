# Production deployment and rollback

## Owner-authorized Hostinger deployment - 2026-11-04

The owner subsequently authorized deployment to
**https://hamidbayat.co.uk**, selecting a copy of the current local content and
uploads without changing the local database. This supersedes the earlier
no-deployment instruction below, not the prohibition on merging to `main`.
The deployed archive is commit `6bac31edbf0a52c55ea74f35e5e832367209b4fa`
from the existing hardening branch. Its
[actual CI run](https://github.com/hamidreza-wq/hamidPortfolio/actions/runs/41874992304)
passed both PHP jobs. PR #1 remains draft and unmerged.

Executed and verified:

- Created an isolated application database and restored a fresh, read-only
  snapshot of all 29 local tables, excluding ephemeral table data. Verified
  imported counts and all three copied storage-file hashes, then applied the
  single pending additive link-visibility migration. No production seed/reset
  was run. All 29 local table counts/checksums still matched after deployment.
  This copies the current installation; **it does not recover the content lost
  in the previously disclosed database incident**.
- Installed production dependencies on PHP 8.3.36 / MariaDB 11.8.9, checked
  platform requirements, and cached configuration/views. Because `proc_open`
  is disabled, Composer ran with `--no-scripts`; its package discovery and
  Laravel asset-publication hooks were executed directly with PHP instead.
- Kept source, environment, durable storage and backups outside the document
  root. `public_html` points only to the release's `backend/public`.
  Initial origin/CDN 404 responses were caused by private-only traversal
  permissions on the new public-root ancestors, not DNS. Giving those
  ancestors/public directories `0755` and public files `0644` fixed routing;
  the private environment and cached configuration remain `0600`.
- Live HTTPS checks passed for home, health, sitemap/robots, all six published
  project URLs, four byte-identical frontend assets, private-source labels,
  authentication redirects, secure session cookies and private-file denial.
  HTTP redirects to HTTPS. `www` is a valid HTTPS alias with the apex canonical
  URL; it is not configured as an apex redirect.
- The owner signed into the retained administrator account. Authenticated
  dashboard/platform pages and the live editor's functioning preview passed.
  A real synthetic contact submission reached the inbox; a temporary 2x2 PNG
  was uploaded, safely re-encoded and publicly served. Both test records were
  deleted through their administrator controls; audit history was retained.
  The imported media/font libraries had zero database records, despite the
  three preserved storage files. Missing asset IDs correctly return 404.
- Desktop (1440px) and mobile (390px) browser smoke checks passed without
  horizontal overflow or browser console errors. New first-party pageviews
  were confirmed in the production database.
- Preserved private pre-publication SQL/uploads/environment backups and the
  original placeholder webroot. SQL/uploads and operational-script archives
  were copied off-host and their hashes verified. Removed temporary server
  staging and duplicate credential files; revoked the temporary deployment
  SSH key and verified that it can no longer authenticate.

### Host-specific operations and remaining limits

Only this portfolio received the following Hostinger cron entries; existing
sites, their cron entries, shared PHP settings and DNS/email records were not
modified:

- Weekly Sunday analytics retention invokes the existing named
  `analytics:prune` callback through a private
  `shared/ops/analytics-retention.php` adapter. This preserves application
  retention configuration and overlap protection without invoking disabled
  subprocess APIs. Direct execution succeeded with zero expired rows; the
  first weekly cron occurrence has not yet been observed.
- The owner separately authorized a bounded dashboard queue worker.
  An every-minute cron invokes private `shared/ops/queue-worker.sh`, with an
  effective-production-database preflight and nonblocking `flock`.
  Each worker stops when empty or after its 50-second between-job budget;
  jobs retain their 150-second timeout. An OS limit sends TERM at 160 seconds
  and KILL after another five, below the 180-second queue visibility interval.
  The overlap-skip path passed. The pending SEO check completed successfully,
  with zero pending/failed jobs at verification. Actual Hostinger cron output
  recorded `Worker STOPPED Queue empty` at 17:48:03 UTC, independently of the
  manual execution.

The full Laravel scheduler and automatic recurring platform checks were not
enabled. SMTP delivery, Google Search Console OAuth and signed-release
verification remain unconfigured; mail uses the log transport and sodium is
unavailable. The queue worker does not make log mail deliver to an inbox.
In-app release activation remains disabled. No owner edits were published by
the deployment smoke tests. Long-term encrypted backup retention and a
dedicated intended-host recovery rehearsal remain owner operational gates.
**The site is deployed and live-smoke-verified, not comprehensively certified
production-ready.**

## Owner-authorized SEO rollout - 2026-11-05

The owner approved the software-development search focus, live publication and
a screenshot of the portfolio as its sharing image. Release
`c77d9087cfb37819130e4562231bd0287d97a71c` was activated without merging `main`.
Its [fresh CI run](https://github.com/hamidreza-wq/hamidPortfolio/actions/runs/41882708811)
completed successfully for PHP 8.3/8.5, including the browser regression step.
An initial bounded CI wait expired while browser installation was still
running; deployment stayed blocked until the subsequent completed result was
verified for the exact commit and both jobs.

- Focused local SEO tests passed: 27 tests, 255 assertions; PHP formatting passed.
  New schema and templates also rendered on hosting PHP before activation.
- A new private release reused the existing dependencies only after confirming
  an identical Composer lockfile. Production configuration, encryption key,
  database and durable storage were retained; no migration or seeder ran.
- Native pre-SEO SQL/uploads/environment backups were preserved privately.
  SQL and uploads were copied off-host and verified against their SHA256 hashes.
- The approved portfolio screenshot was cropped to remove browser chrome and
  sized to exactly 1200 x 630 pixels, then uploaded and safely re-encoded through
  the media library. It is the selected sharing image at `/media/2`.
- Only the site description, Open Graph title/description and default sharing
  image selection were published. The browser session had expired by the end
  of CI, so these four approved fields were saved using the hosting connection,
  repository validation rules, a locked transaction, the existing content-history
  helper and an explicit deployment audit entry with no impersonated user.
  Other settings, sections, skills and projects were not overwritten.
- Live checks passed for homepage plus all six published project pages:
  unique metadata, apex canonical URLs, linked profile/person/website JSON-LD,
  project breadcrumbs, index/follow controls and matching Open Graph/Twitter
  image/description tags. The actual image response was verified as PNG,
  1200 x 630. Existing queue and analytics-retention scripts resolved the new
  release successfully.
- The broader live regression passed all 31 HTTP checks, including security
  headers, protected files, anonymous admin/preview access and public assets.
  A final read-only comparison confirmed all 29 local owner tables unchanged.
- Post-SEO SQL and uploads backups now also include the published metadata and
  sharing image. Their off-host copies matched the native SHA256 hashes.
  The pre-SEO snapshots remain available separately for rollback.
- Temporary staging archives, the publishing helper and backup client options
  were removed. The separate SEO deployment SSH key was deleted in hPanel;
  public-key-only authentication then failed with `Permission denied` and
  exit 255. Its local private/public key files were deleted.

This improves truthful search and social metadata, not guaranteed ranking or
confirmed Google indexing. A simulated `Googlebot` user-agent from the
workstation received a Hostinger 403 challenge, while simulated Twitter and
Facebook sharing requests succeeded. That is **not a verified Google crawl**
and may be anti-spoofing protection. CDN security settings were inspected but
not weakened. The owner should verify actual live crawl access in Search Console
and submit `https://hamidbayat.co.uk/sitemap.xml`; investigate Hostinger crawler
handling if Google's real inspection fails.

## Owner-authorized Google Analytics deployment - 2026-11-06

The owner authorized deployment through Hostinger to **https://hamidbayat.co.uk**.
The `ga4-20261106` release is a copy of the previous `c77d908` runtime with the
18-file analytics working-tree package applied. This is a manifest-verified local
snapshot, not a new committed or CI-certified release. No branch was merged.

- Created a separate GA4 property, **Hamid Bayat Portfolio**, and web stream for
  the exact `https://hamidbayat.co.uk` origin. Measurement ID: `G-LCT08K6TVX`.
  The existing stream for a different domain was not changed.
- Verified the old runtime files against the expected source baseline, ignoring
  only CRLF/LF differences. The Composer lockfile matched, so the existing
  production dependencies were copied unchanged.
- Preserved private native MySQL, uploads, environment and source backups under
  `backups/pre-ga4-20261106`. Off-host copies matched their SHA256 hashes.
  No migration, seeding, database reset, account change or content publication ran.
- Passed 27 focused PHP tests (179 assertions), PHP formatting, hosting PHP
  syntax checks and candidate rendering. A real Edge test of the exact
  production-rendered consent code covered no pre-consent requests, persisted
  rejection, one-tag opt-in, withdrawal, CSP and 320/390/768/1440px layouts.
- Cached candidate configuration/views and atomically switched only this site's
  public-root symlink. The previous release remains available for rollback.
- Live Edge verification received HTTP **204** from Google's collection endpoint
  with the correct Measurement ID. Rejection produced no Google requests;
  project/privacy navigation retained the choice and withdrawal removed
  analytics cookies. No browser script errors were observed.
- Live homepage, privacy and health checks passed. Anonymous analytics access
  still redirects to login, private environment/configuration paths remain
  denied, and nine content/account tables retained their predeployment hashes.
- After the owner signed in, the production admin Analytics page displayed
  **Google Analytics 4 · Configured** with the correct Measurement ID. Google's
  live last-30-minutes card subsequently showed a visitor from the test traffic.
- Removed private staging and native-backup client options. Deleted the temporary
  SSH key in hPanel and independently verified authentication failure before
  deleting the local key material. Other websites and hosting settings were not
  changed.

Google Analytics reports remain separate from first-party counts in the website
dashboard. Standard GA4 reports can take time to populate; an accepted collection
request is not a claim that every report has finished processing.

## Owner-authorized contact email deployment - 2026-11-06

The owner requested contact-form delivery to their existing domain mailbox.
The active `contact-20261106` release copies `ga4-20261106` and overlays a
manifest-verified nine-file contact email package. This remains an uncommitted
working-tree snapshot, not a new CI-certified release; no branch was merged.

- The existing public contact email was already correct and was not republished.
  New submissions remain in the private website inbox and are also emailed
  through authenticated Hostinger SMTP over implicit TLS on port 465.
  The domain mailbox is From; the validated visitor address is Reply-To.
- Contact notifications explicitly use SMTP without a log/failover fallback or
  queue worker. Transport failure preserves the saved message, records only its
  ID in an error log, and displays explicit saved-but-not-emailed feedback.
- A password-free PHP mail probe was accepted locally but delivery was not
  verified, so that method was not enabled. The owner supplied an existing
  mailbox password through a private local file. It was transferred directly
  into the isolated release's private environment configuration without being
  displayed, committed or included in the source package.
- Passed 25 focused PHP tests (150 assertions), targeted PHP formatting and
  diff whitespace checks. Hosting PHP 8.3 syntax, configuration/view caches,
  candidate file hashes and SMTP TLS authentication passed before activation.
- Preserved and SHA256-verified native database, uploads, environment and source
  backups under `backups/pre-contact-20261106`. The previous release remains
  available for rollback. No migration, seeding, reset or account changes ran.
- Atomically activated this site's public-root symlink. Live homepage, privacy
  and health returned 200; environment/configuration paths remained denied.
  One explicitly approved public contact test was saved, SMTP accepted it, and
  the owner confirmed receipt in their Inbox. All pre-existing records across
  nine content/account tables retained their predeployment hashes.
- Removed private staging and backup-client options. Deleted the temporary SSH
  key in hPanel with owner approval and verified subsequent SSH authentication
  rejection. Removed local temporary key/client material and, with separate owner
  approval, the local plaintext password file. The deployment manifest remains
  in the release and backup.

## Earlier release decision - before owner deployment authorization

See [final release readiness](docs/qa/FINAL-RELEASE-READINESS.md) for the
2026-11-04 evidence on `copilot/final-security-deployment-hardening`, application
source `95ef441f2f524f5796ce3aabfa873bbf06246385`, actual CI runs,
isolated restore results and remaining owner-controlled gates.
Production is not approved; keep [PR #1](https://github.com/hamidreza-wq/hamidPortfolio/pull/1)
draft and do not deploy, merge or change DNS/hosting.
Corrected-source CI passed both PHP 8.3/8.5 jobs on Oracle MySQL 8.4.13 and all
11 browser cases. **READY FOR STAGING** was the evidence-based status then;
the intended host and operational/staging gates below remain owner actions.

## Release gates

- Review current [test evidence](docs/qa/FINAL-RELEASE-READINESS.md), including actual successful/failed CI conclusions. Local Composer/npm audits/builds and isolated SQL/assets restore passed; intended-host recovery remains unverified.
- [CI](.github/workflows/ci.yml) runs for PRs, `main` and the existing hardening branch on PHP 8.3/8.5 with MySQL 8.4. It includes audits/build, formatting/syntax, views, PHPUnit fresh/upgrade suites and Edge/Chromium/Firefox/WebKit browser coverage. It is verification-only, never deployment.
- Owner must require both PHP matrix checks and reviewed PRs, blocking force/direct pushes to `main`. Read-only API inspection found `main` unprotected, no rulesets, secret scanning/push protection enabled and Dependabot security updates disabled. Vulnerability-alerts endpoint returned 404; alert enablement is not verified. These settings were not changed.
- Read [the current platform report](PLATFORM-REPORT.md), including its database incident disclosure, and [platform operations](backend/docs/platform-operations.md). The update centre does not deploy or create verified backups. Hosting/Actions activation remains disabled by explicit owner decision.
- Enable PHP sodium and ZIP for signed release inspection, and cURL/DOM for public-site monitoring. Configure only the trusted release **public** key; signing private keys stay outside production.

- Review `HARDENING-REPORT.md` for actual verification results and unresolved limitations. A successful build alone is not a deployment approval.
- Use PHP 8.3+ with PDO MySQL, Fileinfo and GD. Enable GD before accepting image uploads. Install Composer dependencies with `composer install --no-dev --optimize-autoloader` from `backend`.
- Use a dedicated MySQL application account with access only to the application database. Use a different database and account for tests. Tests require a database name ending in `_test`.
- Run `composer validate`, `composer audit`, `npm audit`, `npm run build` and `php artisan test` in a development/CI environment. Do not run the database-refresh tests on production.
- Verify uploaded fonts are owned/licensed for web use. No third-party fonts are bundled.

## Server configuration

- The only web document root is `backend/public`, never the repository root. Preserve root portfolio assets beside `backend`; Laravel delivers the allowlisted files.
- Route `/robots.txt` to Laravel. Do not add a static robots file or CDN override that bypasses the dashboard's indexing/maintenance policy; verify actual HTTP robots/sitemap responses after deployment.
- Deny dotfiles and prohibit PHP/CGI execution in `/storage`, `/media` and `/fonts`. Do not serve private storage, logs, dumps, Composer credentials, Git metadata or test configuration.
- Use HTTPS. Set `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL=https://your-domain`, `SESSION_SECURE_COOKIE=true`, `SESSION_HTTP_ONLY=true`, `SESSION_SAME_SITE=lax` and a suitable `SESSION_LIFETIME`. Generate `APP_KEY` once and preserve it across releases.
- Set database credentials outside Git. Grant write access only to `storage` and `bootstrap/cache`; do not make application source writable by the web server.
- Optional Search Console metrics require a Google OAuth web client configured with the exact production callback URI. Set `GOOGLE_SEARCH_CONSOLE_CLIENT_ID`, `GOOGLE_SEARCH_CONSOLE_CLIENT_SECRET` and `GOOGLE_SEARCH_CONSOLE_REDIRECT_URI` in the private environment; never put these credentials in source control. The integration requests read-only access and verifies the selected property.
- Google Analytics 4 uses `GOOGLE_ANALYTICS_MEASUREMENT_ID` (default: `G-LCT08K6TVX`). The public tag loads only after explicit visitor consent; the CSP allowlist includes the Google tag and Analytics endpoints. Verify both consent choices and live Realtime data after deploying.
- Configure TLS termination and trusted proxies explicitly when using a reverse proxy. Verify Laravel sees requests as HTTPS before relying on HSTS and secure cookies. HSTS is emitted only for HTTPS production requests.
- Check CSP in a browser: scripts/styles receive nonces, external framing is blocked, and the same-origin admin preview is allowed. Inline style attributes remain allowed because existing theme/layout controls rely on them; inline script handlers are not allowed.
- Keep MySQL, PHP, web server and system dependencies patched. Review private contact and authentication logs under an explicit retention policy.

## Controlled deployment

1. Back up MySQL and uploaded media/font files before schema changes. Use database-native tools such as `mysqldump` with a protected credentials option file; never put passwords in command arguments or Git.
2. Restore that backup to a separate staging database and verify that the application and assets can be recovered.
3. Install the reviewed release, retaining private environment configuration and durable storage.
4. Review pending migrations with `php artisan migrate:status`. Run `php artisan migrate --force` only as an explicit deployment step. Composer installation does not migrate the database.
   Never run seeders on an existing edited installation; they update repository defaults.
5. Run `php artisan storage:link` where filesystem/web server permissions allow it; uploaded asset routes must remain available.
6. Run `php artisan config:cache` and `php artisan view:cache`. Restart long-lived application processes so their environment/configuration is refreshed.
7. Create the owner account with `php artisan portfolio:admin:create` if absent. Resetting an existing administrator requires explicit confirmation. Existing ordinary users are never promoted by this command.
8. Smoke-test public pages, published project URLs, sitemap/robots, admin login/logout, upload rejection, publish/preview and contact submissions over HTTPS.

## Rollback and restoration

### Coordinated backup and isolated rehearsal

These are general recovery instructions. The dated deployment record above
distinguishes executed backup/import checks from a still-unverified dedicated
intended-host recovery rehearsal.

1. Establish a maintenance/quiescent-write window so SQL and uploaded assets
   represent the same point in time. Keep private environment/application key
   recovery material separately encrypted and access-controlled.
2. Use a protected MySQL client option file outside the repository. Do not put
   passwords on the command line. A native example, with deliberately private
   placeholder paths, is:

   ```powershell
   & 'C:\mysql\bin\mysqldump.exe' --defaults-extra-file=C:\private\source-client.cnf --single-transaction --routines --triggers --result-file=C:\private\portfolio.sql portfolio_database
   if ($LASTEXITCODE -ne 0) { throw 'Backup failed' }
   Get-FileHash -Algorithm SHA256 C:\private\portfolio.sql
   ```

3. Copy the corresponding public storage tree, preserving relative paths;
   record each file's size/hash and SQL completion marker. Keep an encrypted
   off-host copy and a documented retention/recovery owner. Never commit dumps,
   credential files or copied contact/account data.
4. Provision a **new empty isolated `*_test` schema** and test-only account.
   Explicitly select it in testing configuration, clear `DB_URL`, and run
   `php artisan portfolio:test-database:verify` before any import/migration.
   Refuse a nonempty target and any dump containing `USE`, `CREATE DATABASE`
   or `DROP DATABASE`; do not import a dump that can switch away from the target.
5. Restore with the native MySQL client's explicit `--database` and a separate
   test-only option file. In PowerShell, `Start-Process` with
   `-RedirectStandardInput` can supply the SQL file without altering its
   encoding; use `-Wait -PassThru`, capture stderr privately, check `ExitCode`,
   and stop on failure. Never restore into the source/owner database.
6. Restore media/fonts to a **separate directory** and configure
   `LARAVEL_STORAGE_PATH`/disk roots there. Compare every table count/checksum
   and asset size/hash, run reviewed additive migrations on that clone, then
   smoke-test the restored app and URLs. Remove temporary client files afterward.
7. Record duration, matching application version, recovery point and failures.
   An isolated local restore is not a production failover/rollback exercise.
   Rehearse intended-host recovery separately before approving deployment.

- If smoke tests fail, stop publishing and roll back the release to the previously verified application version.
- Do not blindly run `migrate:rollback`: removing columns can destroy content. Use the reviewed database backup and matching application release if the schema is incompatible.
- Destructive Artisan reset/wipe/rollback commands are blocked outside verified `*_test` databases in the testing environment. A destructive command is not a recovery procedure. Never restore an old backup over newer user data.
- Restore MySQL to an isolated database first, then switch application configuration under maintenance control. Restore media/fonts from the matching backup. Preserve `APP_KEY`.
- Clear/rebuild configuration and view caches; restart processes and repeat smoke tests.
- Content revisions are not substitutes for MySQL/asset backups.

## Remaining controls

Protected updates restore web-server traversal on the isolated release root and
backend directory, and readable modes only within its public webroot after
extracting a private-mode runtime archive. Private environment/configuration
files and shared-storage permissions are not broadened.

See [operational requirements](backend/docs/platform-operations.md) for
supervised workers, minute scheduling, shared locks and digest failure handling.
Queue visibility defaults to 180 seconds, above the 150-second monitor timeout;
preserve this ordering for environment overrides. Confirm actual worker execution,
failed-job alerting and log rotation on the intended host. Mail currently uses
`log` locally; no real SMTP receipt or live GSC/SEO-origin validation is claimed.
Leave digests off, GSC disconnected and release installation disabled until
their documented owner checks pass.

No public self-registration or emailed password reset is provided. Recovery requires trusted shell access and the administrator command. Optional two-factor authentication and CAPTCHA require a separately reviewed provider/configuration; do not treat them as implemented.

## Owner-authorized SEO and protected-update bootstrap - 2026-11-06

- Activated `admin-bootstrap-20261106` after verifying source baselines,
  unchanged dependency locks, isolated HTTP rendering and private production
  configuration. Existing content/account records, SMTP and GA4 configuration
  were preserved. No migration, seeder or database restore ran.
- Created private SQL/uploads/environment/source backups, verified their SHA256
  hashes on hosting and again in an owner-restricted off-host directory.
  Atomic activation passed homepage, privacy, project, sitemap, robots, health
  version-header and private-file-denial checks. Previous releases remain.
- Fixed the homepage JSON-LD graph false positive and published only approved,
  repository-grounded description/details/features for projects 7, 6 and 9.
  The actual production SEO scan at 23:11:01 UTC reported zero open findings;
  the notification centre shows the matching warnings resolved, not dismissed.
- Local related PHP validation passed 141 tests / 734 assertions, with PHP
  formatting and Blade compilation passing. Signature/archive utility tests
  passed; all seven isolated hosting-runner backup, cache, health/version,
  exit-trap rollback and concurrency tests passed without disrupting production.
- Update implementation was pushed as `808fb26`. The separately approved
  default-branch publication `fa38e2a` contains only the deployment workflow,
  utility scripts/tests and their LF attribute; historical application changes
  were not merged into `main`. GitHub registered the workflow as active.
- Enabled sodium for the selected website and confirmed sodium/ZIP availability
  in the live admin panel. The dedicated selected-repository App and private
  hosting credential are provisioned. Production requires independent review,
  prevents self-review, disables administrator bypass and permits only `main`.
  Public trust/host-key variables are saved; the SSH secret was independently
  verified by name after the owner's manual save. Real scoped App authentication
  to the registered workflow passed without dispatching a deployment.
- A signed `v0.3.0` package was prepared and independently verified using actual
  MariaDB 11.8 compatibility bounds. It has not been published or installed.
  The bootstrap intentionally retains installed `VERSION` `0.2.0-dev` so the
  signed release can later be genuinely exercised through Update. Activation
  remains disabled until secret setup and the protected end-to-end run pass.
- The exact implementation CI run
  [42309496823](https://github.com/hamidreza-wq/hamidPortfolio/actions/runs/42309496823)
  passed PHP/audit/build and isolated MySQL/utility checks on both runtimes,
  but exceeded the 25-minute limit during repeated browser dependency installs
  on the rolling Ubuntu runner. CI/deployment are now pinned to Ubuntu 24.04,
  with one shared browser dependency-installation pass. Release publication
  remains blocked pending a successful replacement CI run. No full CI or live
  Update success is claimed yet.
