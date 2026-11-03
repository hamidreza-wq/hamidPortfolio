# Platform development and verification report

## Critical incident: local database data was removed

During implementation I mistakenly ran `artisan migrate:fresh --env=testing`
without explicitly selecting `DB_DATABASE`. Because there was no `.env.testing`,
Laravel used the local owner database, `hamid_portfolio`. Its tables were
dropped and recreated, then the new migration failed on a MariaDB timestamp
default. This was my mistake, not a successful migration or a harmless test.

I immediately disclosed it and stopped database writes. The owner authorised a
read-only recovery assessment. That assessment confirmed zero users, projects,
settings, messages and audit records; binary logging was disabled. No portfolio
backup was found in the checked project storage/XAMPP backup locations. Windows
shadow-copy enumeration failed, so snapshots were not verified. This assessment
does not prove there is no external backup.

The owner then explicitly authorised rebuilding **repository defaults only**.
That rebuild was transactionally guarded against the exact database name and
nonempty target tables. Result: 5 projects, 1 settings row, 6 sections, 4 skill
groups, 17 skills and 1 theme preset. The rebuilt public homepage returned
HTTP 200 and contained the default projects.

**Previous accounts, custom edits, inbox messages and audit history were not
recovered.** I did not create or reset an owner administrator or set a default
password. If an administrator still needs to be created, first independently
back up the rebuilt database, review/apply the pending additive migrations, then
run `php artisan portfolio:admin:create` interactively from `backend`.
Do not rotate the existing application key or reseed over new edits.

The final read-only check still returned HTTP 200 with five default projects
and no inbox messages; it found one user account now present. That account was
left untouched. This does not constitute recovery of the previous account data.

Corrective measures:

- Added a global Artisan destructive-command guard. Reset/wipe/rollback
  commands require both `testing` and configured/effective MySQL `*_test` names,
  including explicit connection selections.
- Strengthened the PHPUnit bootstrap guard with `SELECT DATABASE()`.
- All subsequent tests/fixtures explicitly selected either
  `hamid_portfolio_test` or `hamid_portfolio_browser_test`.
- Fixed the required timestamp defaults and allowed the first monitoring table
  from the known partial failed migration to be preserved on retry.
- After the owner reported missing dashboard tables and explicitly approved
  the repair, a private SQL backup was saved and checked for expected schema,
  data and completion markers. Restore of that backup has not been tested.
  Only the two reviewed additive platform migrations were then applied.
  User, project, settings, inbox and audit record counts were unchanged.
  Dashboard, Updates, GitHub Projects, SEO Monitor and Notifications all
  rendered HTTP 200. No reset, rollback or reseeding was performed.
- A separate login problem was resolved by restarting only the portfolio
  server on port 8000 with the correct local database explicitly selected.
  The owner confirmed successful login; credentials were not changed.

## Overall outcome

**NOT READY FOR PRODUCTION.** Useful integrated functionality is implemented and
tested, but deployment, verified backups and rollback are not configured;
Oracle MySQL validation and frontend dependency/build gates are outstanding.
The data-loss incident is independently a serious operational failure.

Branch: `copilot/portfolio-security-updates-seo`, created from fetched
`origin/main`, then fast-forwarded to preserve the existing hardening commit
`6d8aa76`, with the owner's explicit agreement. The owner subsequently requested
commit and push of this development branch. No GitHub release or production
deployment is part of that request.

## Existing implementation retained

The previous hardening already supplied administrator-only authentication,
session invalidation, login throttling, contact CSRF/spam/replay controls,
nonce-based CSP, validated image/font uploads, media reference safeguards,
managed fonts and sections, project SEO/detail pages, sitemap/robots, revision
history, and the same-origin unsaved preview of the actual cosmic frontend.
The root HTML/CSS/ES modules remain canonical; no disconnected admin template
or replacement frontend was created.

## New implementation

### Trusted release review

- Installed development version and changelog.
- Fixed official Releases API source, stable semantic tags, clear no-release
  warning, history and deduplicated notices.
- Exact-byte detached Ed25519 manifest verification against a server trust key.
- Signed source/version/PHP/extensions/database-family/version/schema
  compatibility; GitHub asset digest plus downloaded SHA-256 verification.
- Bounded official-asset download, allowlisted single redirect, private random
  artifact storage/readback, permission and disk-space checks.
- ZIP path/duplicate/reserved-name/symlink/special-file/encryption/expansion
  checks, entry decompression/CRC, heuristic migration review.
- Password reauthentication, throttling, locking, verification failure history,
  artifact/trust-key rechecks before recording review approval.
- Approval is explicitly `manual_action_required`. Nothing is marked applied.

The owner chose future protected GitHub Actions hosting but has not selected a
hosting layout. They explicitly requested activation remain disabled. Therefore
staging/extraction, verified database/media/config backups, deployment dispatch,
activation, application health validation and rollback **are not implemented or
tested**. There is no shell/download-and-execute path in the web application.

### GitHub discovery and attribution

- Automatic scheduled/manual anonymous discovery for the fixed public account.
- Account login/numeric owner validation; bounded pagination, timeouts, rate
  limits and fail-before-write handling.
- Repository IDs are primary identity; existing project URLs are linked rather
  than duplicated; forks/archives excluded by default.
- Real unpublished draft projects plus a review queue. Approving a candidate
  does not publish; unapproved imports cannot publish through the editor.
- Publication approval is persisted independently of discovery state, so a
  source disappearing and returning cannot bypass its initial draft review.
- Renames/changes/unavailable sources go to review. Subsequent scans never
  overwrite existing editorial content or silently delete projects.
- Bounded plain-text metadata, real language/licence fields; README/code is
  never fetched or executed and product features are not invented.
- Explicit authorship/copyright confirmation; maintainer/licence/attribution
  fields in the existing editor and real public detail page/JSON-LD.
  Account ownership alone makes no authorship or exclusive-copyright claim.

Private repositories, OAuth and GitHub App authentication are intentionally not
implemented in this public-only option. No tokens/credentials are accepted or
stored. The dashboard clearly states this limitation.

### SEO monitoring

- Actual observed HTML responses from a server-configured canonical HTTPS
  origin; public IPv4 DNS policy/pinning, TLS verification, no proxy/redirects,
  strict public routes, valid slugs, bounded pages/bytes/time.
- Titles/descriptions/duplicates, canonical/noindex, basic OG/JSON-LD/h1/
  viewport/alt, known project-link consistency, thin-content and server-response
  review hints, robots and published-only sitemap consistency.
- Persistent findings/priorities/resolution history, editor links, notices,
  and no automatic publication or keyword/ranking claims.

Search Console is explicitly disconnected and **not implemented**. This monitor
is not a full crawler, external broken-link/image checker, full structured-data
validator, browser performance measurement, accessibility certification or
proof of search indexing. Production-origin crawling is not yet configured or
verified; automated HTTP fixtures validate parsing and safety policy.

### Notifications, maintenance and delivery

- Unified fingerprint-deduplicated update/GitHub/SEO history and unread badge.
- Priority, read/dismiss/24-hour snooze, related-page links; changed or
  resolved-then-recurring issues reopen instead of generating duplicates.
- Per-administrator category preferences and opt-in daily/weekly summaries.
  Shared read/dismiss state reflects the existing single-owner studio.
- Bounded mail digests to the administrator account address only; transport
  failures remain explicit. No private contact messages or credentials included.
- Configurable scheduled monitor jobs, uniqueness/runtime locks and separate
  CLI-only fixed-argument Composer/npm audits/version checks.

Real SMTP delivery, a supervised production worker, distributed locks and the
host scheduler still require configuration. Mocked email transport tests passed;
no actual mailbox delivery is claimed.

## Files and migrations

Added:

- Root `VERSION`, `CHANGELOG.md` and this report.
- `backend/config/platform.php`.
- `backend/app/Services/DestructiveDatabaseGuard.php`.
- `backend/app/Services/Platform/`: `IntegrationFailure`, `GithubApi`,
  `GithubDiscovery`, `Releases`, `PublicDns`, `SeoMonitor`, `Monitor`, `Notices`.
- Models: `PlatformRun`, `PlatformNotice`, `ReleaseCandidate`,
  `GithubRepository`, `SeoFinding`, `NotificationPreference`.
- `Admin/PlatformController`, `Jobs/RunPlatformMonitor`, `Mail/PlatformDigest`.
- Console commands: `CheckPlatform`, `AuditPlatformDependencies`,
  `SendPlatformDigests`.
- Six `admin/platform` Blade views and the escaped mail digest view.
- `tests/Feature/PlatformIntegrationTest.php`.
- `docs/platform-operations.md`.
- Additive migrations `2026_11_04_010000_add_platform_monitoring` and
  `2026_11_04_010100_add_notification_preferences`.

Changed existing files:

- `README.md`, `DEPLOYMENT.md`, `backend/.env.example`.
- `ProjectController`, `Project`, `PortfolioSeo`, `AppServiceProvider`.
- Shared admin layout, project fields and actual public project view.
- Web/console routes and test bootstrap guard.

No dependency manifest or lockfile was changed; no credentials were committed.
Audit records are not deleted when a project or administrator is removed.
Test-only browser fixtures were explicitly cleaned while retaining audit
history and original seeded projects.

## Verification commands and actual results

All PHP commands below were run from `backend`. Database tests explicitly set
`APP_ENV=testing`, `DB_CONNECTION=mysql`, `DB_DATABASE=hamid_portfolio_test`,
`DB_URL=''`. Browser fixtures used `hamid_portfolio_browser_test` instead.

**Important database distinction:** the available local server reports MariaDB
10.4.32, using Laravel's MySQL/PDO driver. These results are not a claim that the
suite ran against Oracle MySQL 8. The required Oracle MySQL run remains open.

| Command/check | Actual result |
|---|---|
| Baseline `php -d extension=gd vendor\bin\phpunit` | 64 tests, 502 assertions, 1 conditional skip |
| `php -d extension=gd -d extension=sodium vendor\bin\phpunit` | Final run: 131 tests, 763 assertions, 1 conditional skip; 130 passed, no failures |
| `php vendor\bin\phpunit --filter=test_missing_gd_fails_closed` | 1 test passed, 3 assertions |
| New focused integration suite before the final slug/guard regressions | 64 passed, 252 assertions |
| `composer validate --strict --no-interaction` | Exit 0; manifest valid; Composer emitted PHP 8.5 deprecation notices |
| `composer audit --no-interaction` | Exit 0; no reported advisories |
| `npm audit --json` | Exit 1, ENOLOCK; no lockfile, advisory status unknown |
| `npm run build` | Exit 1; Vite is not installed |
| `php artisan platform:audit` (isolated test DB) | Exit 1 / needs attention: Composer validate/audit/outdated exit 0; npm audit exit 1 ENOLOCK; npm outdated exit 0; no packages changed |
| `composer outdated --direct --format=json --no-interaction` within audit | Exit 0, one reported direct version entry; availability is not a compatibility guarantee |
| `npm outdated --json --ignore-scripts` within audit | Exit 0; without installed/locked dependencies this does not certify dependency safety |
| `php artisan schedule:list --no-ansi` | All three monitors, dependency audit and digest schedules registered |
| `php artisan view:cache` then `view:clear` | Successful compilation and cleanup |
| `php -l` over changed non-Blade PHP | 32 files passed at inventory run |
| Pint over all changed/new PHP | Passed after repository formatter fixes |
| `node --check app.mjs` and `node --check portfolio-data.mjs` | Passed |
| `git diff --check` | Passed |

The first post-change regression attempt failed on invalid timestamp defaults;
I stopped that run, exposed the failure and fixed the migration. The unsafe
manual command used during that diagnosis caused the separately disclosed
owner-database incident. These failures are not excluded from the record.
Later sequential HTTP fixture tests also exposed a fixture-reset mistake;
replacing accumulated fake responses fixed the tests without weakening them.

The prior npm dependency fetch attempt was blocked by the environment's
`EALLOWREMOTE` policy. That restriction was not bypassed. A successful PHP
suite does not make the npm audit/build gates pass.

### Real API and browser checks

- `php artisan platform:check github` on the isolated browser DB: exit 0,
  4 public repositories discovered, 3 new actual unpublished drafts; original
  project data retained.
- `php artisan platform:check releases` on that DB: exit 1 with the correct
  no-published-official-release setup warning. No update was fabricated.
- Local server readiness: `/admin/login` HTTP 200.
- Actual browser sign-in, candidate review approval (database verified it
  remained unpublished), mark-read, category preferences, mobile navigation and
  logout worked.
- Updates, GitHub Projects, SEO Monitor and Notifications returned HTTP 200
  with no horizontal overflow at 320, 390, 768, 1024 and 1440px.
- Long release notes/verification forms and SEO finding fixtures additionally
  passed those widths; the updated notification preference form passed them.
- No browser page-error events occurred in the initial four-page sweep.
- Temporary browser-only account, three discovered draft fixtures, source rows,
  synthetic release/finding/notice were removed. Original five projects remain.
  The helper server was stopped. No real email, release activation or rollback
  was attempted.

### Security review

The read-only static review found no high-confidence vulnerabilities in the
previous hardening baseline or in its follow-up inspection of the new platform
services, routes, guard and digest changes. The reviewer did not execute tests,
connect to the database or verify production/deployment configuration; it relied
on the separately reported test results. A final publication-state regression
was then added and tested to retain review approval independently of source
availability. This is not a runtime/production certification and does not
mitigate or excuse the disclosed database incident.

| # | Severity | File | Lines | Vulnerability | Confidence |
|---|----------|------|-------|---------------|------------|
| - | - | - | - | No high-confidence vulnerabilities reported in the bounded static review | - |

## Remaining release gates / operator actions

1. Treat the database incident as actual data loss, not restored history.
   Locate any independent backup/snapshot if available; restore it only to an
   isolated database before deciding whether to switch configurations.
2. Keep independent verified backups of current data. The two platform
   migrations are now applied locally and the owner confirmed login. Other
   installations must review/apply pending migrations explicitly; create an
   owner admin interactively if absent. Do not reset/wipe/reseed.
3. Use a currently supported, separately configured Oracle MySQL server for the
   requested MySQL acceptance run, with test-only credentials.
4. Enable GD and sodium in the actual web PHP runtime, not only CLI flags.
5. In an authorised package-download environment, create/review the correct npm
   lockfile, install dependencies, rerun npm audit and the build. Do not bypass
   the environment package-fetch restriction.
6. Publish a genuine signed official release and configure its trusted public
   key before relying on update verification.
7. Configure the public canonical origin, HTTPS/proxy/session settings, shared
   cache, database queue, supervisor/Task Scheduler/cron and SMTP. Verify their
   real behaviour and backup recovery independently.
8. Configure/test the protected Actions hosting/staging/backup/health/rollback
   workflow before adding any activation endpoint.
9. Search Console OAuth/statistics and private GitHub access require separate
   implementation/security verification if wanted; the UI currently reports
   them as disconnected/disabled, not working features.
10. Review the [operations guide](backend/docs/platform-operations.md) and
    [deployment checklist](DEPLOYMENT.md). No build or audit alone authorises
    production deployment.
