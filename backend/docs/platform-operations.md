# Platform operations

## Status and deployment boundary

The update centre implements discovery, cryptographic verification, archive
inspection and administrator-confirmed requests to the protected
`deploy.yml` GitHub Actions workflow. Deployment is disabled by default until
the selected-repository GitHub App and hosting secrets are configured.
The web application never runs shell commands or executes downloaded PHP.
Initial one-click updates are limited to **schema-preserving releases**;
database migrations and host runtime changes remain separately reviewed.

Read [the development report](../../PLATFORM-REPORT.md), including its database
incident disclosure, before using these changes. Back up the database before
applying the additive migrations. A default seed is not a data recovery.

The only document root remains `backend/public`. Private ZIPs, logs, configuration
and backups must never be exposed by a web server. The root HTML/CSS/ES modules
remain the actual public frontend and preview.

## Schema and existing installations

Review these migrations, then apply them explicitly using a deployment account:

- `2026_11_04_010000_add_platform_monitoring`: run history, notifications,
  release candidates, repository review records, SEO findings and nullable
  project attribution fields. Existing projects are not republished or replaced.
- `2026_11_04_010100_add_notification_preferences`: per-administrator category and
  digest choices; email is disabled by default.
- `2026_11_04_010200_create_analytics_tables`: first-party pageview aggregates
  and optional encrypted Google Search Console OAuth connection.

```powershell
Set-Location E:\hamidPortfolio\backend
php artisan migrate:status
# Only after reviewing pending migrations and verifying an independent backup:
php artisan migrate --force
```

The first migration preserves a `platform_runs` table left behind by its known
initial failed DDL attempt. It does not erase it. Do not repair migration failures
by wiping a database or rerunning the default seeder over edited content.

Destructive Artisan commands (`db:wipe`, `migrate:fresh`, `migrate:refresh`,
`migrate:reset`, `migrate:rollback`) are now blocked outside the testing
environment and an actual MySQL-protocol database whose name ends in `_test`.
The guard checks both the configured and effective `SELECT DATABASE()` name,
including an explicit `--database` connection. This is a safety barrier, not a
replacement for least-privilege accounts and backups. It cannot stop raw SQL,
database administration tools or custom migration code.

`--env=testing` **does not select a test database** when no `.env.testing` exists.
Use a separate test account with access only to disposable test databases:

```powershell
Set-Location E:\hamidPortfolio\backend
$env:APP_ENV = 'testing'
$env:DB_CONNECTION = 'mysql'
$env:DB_DATABASE = 'hamid_portfolio_test'
$env:DB_URL = ''
php -d extension=gd -d extension=sodium vendor\bin\phpunit
```

Set test credentials through your private environment or `.env.testing`; never
copy production credentials into test commands. The local verification server
is MariaDB 10.4.32 through Laravel's `mysql` driver, not Oracle MySQL 8.
Oracle MySQL verification remains outstanding.

## Official releases and trust

Source is fixed in code:
`https://api.github.com/repos/hamidreza-wq/hamidPortfolio/releases/latest`.
Anonymous API requests cannot discover private releases. Stable `vMAJOR.MINOR.PATCH`
tags only; drafts, prereleases, wrong repository links and downgrade/equal
version verification are rejected. `VERSION` describes this checkout's
development version, not a claim that a signed release has been published.

A published release must contain:

1. `portfolio-manifest.json`, at most 64 KiB, signed as **exact raw bytes**.
2. `portfolio-manifest.sig`, the base64-encoded 64-byte detached Ed25519 signature
   over those bytes (optional terminal newline is allowed).
3. The ZIP named by the signed manifest, at most 50 MiB, with GitHub's
   `sha256:<hex>` release-asset digest.

Example manifest shape (replace every example value with reviewed release data):

```json
{
  "repository": "hamidreza-wq/hamidPortfolio",
  "version": "1.0.0",
  "tag": "v1.0.0",
  "notes": "Human-reviewed release notes",
  "released_at": "2026-11-01T00:00:00Z",
  "asset": {
    "name": "portfolio.zip",
    "sha256": "<64 lowercase hexadecimal SHA-256 characters>"
  },
  "minimum_php": "8.3.0",
  "required_extensions": ["pdo_mysql", "fileinfo", "gd", "sodium", "zip"],
  "database_engine": "mysql",
  "mysql_min": "8.0.0",
  "mysql_max_exclusive": "9.0.0",
  "schema_from": 3,
  "schema_to": 4
}
```

`database_engine` must match the actual server family (`mysql` or `mariadb`).
The compatibility bounds apply to that family's reported version. Never reuse
MySQL version bounds as proof of MariaDB compatibility. `schema_from` must equal
the installed code's `platform.schema_version`; bump that constant deliberately
with reviewed releases, not through an editable dashboard field.

Generate and retain the Ed25519 private key in a protected signing environment
outside the application. In trusted PHP signing automation use
`sodium_crypto_sign_detached($exactManifestBytes, $privateKey)` and base64-encode
the resulting signature. Configure **only** the base64-encoded 32-byte public key
as `RELEASE_PUBLIC_KEY` in server secrets. Never download the trust key from the
release being verified. Key rotation invalidates prior review eligibility and
requires reverification. Private keys must not be copied to production or Git.

The dashboard's verify action requires password reauthentication and a throttled
POST. It verifies the signature first, signed metadata/runtime/schema, the
GitHub digest, downloaded SHA-256, private write/readback and free disk space.
It then inspects ZIP entries without extracting/executing them: path traversal,
absolute/Windows paths, reserved names, case-insensitive duplicates, symlinks,
special files, encryption and expansion limits are rejected; file streams are
checked against declared size and CRC. Limits: 10,000 entries, 50 MiB per file,
200 MiB expanded total and at least 256 MiB private-storage free space.

Verification downloads use official asset IDs and accept a single HTTPS redirect
only to GitHub's exact allowlisted asset hosts. Responses have size/time limits.
Artifacts are randomly named on Laravel's private `local` disk under
`platform/releases`. Failed inspections can leave quarantined private artifacts;
there is no automatic deletion/retention job.

Potentially destructive migration patterns are flagged heuristically. This is
**not a PHP sandbox or a proof migrations are safe**. Review every migration,
including `up`/`down`, data effects, runtime requirements and backward
compatibility. Approval rechecks stored artifact bytes and trust-key identity.
Review approval alone records `manual_action_required`, never deployment success.
Neither verification nor review approval creates a database/media backup.
The separate **Update** action rechecks verified artifact bytes and the trust
key, requires current-password confirmation, and dispatches the protected
workflow. It does not report success merely because GitHub accepted the request.

If GitHub has no release, or a key/signature/digest is unavailable, the UI records
a clear setup/failure state. Do not loosen verification to make an update appear.

## GitHub discovery and editorial review

Public-only anonymous discovery is intentionally selected. No OAuth, GitHub App,
classic PAT or fine-grained PAT is accepted/stored, and no credentials are sent.
Private repositories are **disabled**. If private access is implemented later,
use a separately reviewed GitHub App with selected repositories and read-only
metadata permissions, explicit owner authorization, encrypted credentials,
rotation/revocation and verified account identity. Do not paste broad `repo`
tokens into configuration or invent an OAuth success state.

The fixed account endpoint verifies `hamidreza-wq` and obtains its numeric owner
ID; every owned repository must have the same login and ID. Discovery requests
only `/users/hamidreza-wq` and its owned public `/repos` pages; 100 per page,
10 pages maximum. A malformed page, API failure, rate limit or time budget stops
the scan before any database import. No README, code, homepage, archive or remote
script is fetched/executed.

Repository ID, not name, is the primary key. Forks/archives default to excluded.
Existing projects with a matching GitHub URL are linked, not duplicated.
New eligible repositories create **actual unpublished Project rows**. Imported
descriptions are bounded plain text; language/licence metadata is a review aid,
not inferred authorship or invented product features.

Later scans update review metadata and a previous snapshot only. Renames,
archives and unavailable/deleted/now-private sources are surfaced for review.
They never change manually edited project copy, slug, media, visibility or
attribution and never automatically delete projects. Rejected candidates are
retained to avoid rediscovery duplicates.

Approve/reject in GitHub Projects, then use the existing project editor to
complete content, media, SEO and publication. Approval itself does not publish.
Unreviewed/rejected imports cannot be published through that editor. Deleting a
project leaves the source review record with a null project reference; no
automatic reimport occurs. Reimport of deleted/rejected sources requires manual
operator review; there is no one-click destructive reset.

Account identity is not proof of personal identity or copyright. Project author
and copyright claims require an explicit rights attestation. Default imports
have no author/copyright claim. Confirmed fields appear on the real detail page
and in JSON-LD; unconfirmed claims are omitted. Preserve licence text, contributor
rights, attribution and notices. A licence identifier does not replace the
repository's complete licence. The CMS does not rewrite licences or claim every
repository belongs exclusively to the portfolio owner.

## SEO monitoring

Set `SEO_MONITOR_ORIGIN=https://your-public-domain` on the server, without
credentials, path, query, fragment or port. It must equal the canonical origin
configured in the site editor. Administrators cannot turn arbitrary form input
into scanner URLs.

The scanner resolves public IPv4 addresses, rejects non-global/private/loopback/
metadata/shared ranges, pins the selected address with cURL while keeping TLS
hostname verification, disables proxy routing and never follows redirects.
Direct outbound HTTPS, PHP cURL and DOM are required. IPv6-only sites are not
supported. It never requests admin, preview, arbitrary links or external sites.

It requests only `/`, up to 50 current published `/projects/{slug}` pages,
`/robots.txt` and `/sitemap.xml`, with 1 MiB per-response and a total time budget.
More than 50 published projects fails explicitly instead of claiming full
coverage. It checks observed status, title/description presence and exact
duplicates, canonical URL, noindex conflicts, basic OG, image-alt hints,
one h1, basic JSON-LD, viewport, known project-link consistency, thin-content
review hints, response time, sitemap membership/draft exclusion and robots.
It records recommendations/history, resolves issues after a completed clean
rescan, and never rewrites or publishes content. Failed/incomplete scans do not
falsely resolve earlier findings.

Limits: not a full crawler or Google validator; it does not fetch external
links/images, detect all broken assets, validate full schema semantics, render
JavaScript for scans, measure Core Web Vitals, certify accessibility/mobile
layouts, analyse keywords, guarantee indexing or promise rankings. Content
length is a Unicode-aware review hint, not a ranking target.

First-party analytics records successful requests for the public home and
published project pages. It stores the page path, external referrer hostname
and timestamp only: no cookies, visitor identifiers, IP addresses, user-agent
strings, or full referrer URLs. It reports pageviews, not unique visitors.
Records are pruned weekly after `ANALYTICS_RETENTION_DAYS` (default 90); the
Laravel scheduler must be running for retention cleanup.

Google Search Console is an optional, separate read-only source. Configure
`GOOGLE_SEARCH_CONSOLE_CLIENT_ID`, `GOOGLE_SEARCH_CONSOLE_CLIENT_SECRET`, and
`GOOGLE_SEARCH_CONSOLE_REDIRECT_URI` using the exact HTTPS callback URL
`https://your-domain/admin/analytics/google/callback` registered in the Google
OAuth client. The callback must also be authenticated to the studio. The admin
then selects a property, authorizes only the `webmasters.readonly` scope, and
the application confirms property access before saving the encrypted refresh
token. Only actual Search Console API responses are shown; temporary failures
are reported separately and do not replace first-party counts. Disconnecting
deletes the locally stored token; revoke the grant in Google Account
permissions as well if access should be withdrawn immediately.

## Notifications and email

The private centre has fingerprint-deduplicated updates/GitHub/SEO notices,
priority, history, editor links, read, dismiss and 24-hour snooze. Repeated
identical issues preserve read/dismissal; changed or resolved-then-recurring
issues reopen. A fresh issue's severity does not imply a proven exploit.

Read/dismiss/snooze are shared in this single-owner studio. Each administrator
can select categories for their badge and optional daily/weekly summaries.
Email is disabled by default. Configure a real SMTP mailer, sender and scheduler.
`log`/`array` mailers do not deliver email; submitted history is not proof of inbox
receipt. Digests go only to the opted-in administrator account address, contain
at most 50 current unread, unsnoozed notices, and exclude private contact messages
and credentials. Transport errors are recorded without secrets or a false sent
timestamp. Verify a real mailbox separately before relying on mail.

## Scheduler, queues and dependency checks

Example private environment:

```dotenv
QUEUE_CONNECTION=database
DB_QUEUE_RETRY_AFTER=240
RELEASE_CHECK_CRON="0 */6 * * *"
GITHUB_SCAN_CRON="15 */6 * * *"
SEO_SCAN_CRON="30 3 * * *"
```

Run Laravel `schedule:run` each minute through the host's scheduler, from
`backend`, as a restricted maintenance account; use Task Scheduler on Windows
or cron on Linux. Start a supervised queue worker:

```powershell
php artisan queue:work --queue=default --timeout=150 --tries=1
```

Use a shared database/Redis cache for multi-server locks and keep queue
`retry_after` greater than the worker timeout (defaults are now 180 seconds versus
the monitor's 150-second limit). If overriding `DB_QUEUE_RETRY_AFTER`,
`REDIS_QUEUE_RETRY_AFTER` or `BEANSTALKD_QUEUE_RETRY_AFTER`, preserve that ordering.
Jobs have uniqueness and runtime
locks; scans do not automatically retry rate-limit responses. `sync` runs manual
checks in the request and is intended only for local use. Without a real
scheduler/worker, an enabled schedule is not evidence that checks are executing.
Abrupt process termination can leave `running` history; investigate the worker
logs and expired locks before retrying.

Monitor `php artisan queue:failed` and failed platform runs; alert before a
controlled `queue:retry` rather than repeatedly retrying third-party failures.
Supervise/restart workers after releases and verify one real scheduled task on
the intended host. No host scheduler or supervised worker was installed by the
local verification. Production logs need rotation, for example `LOG_STACK=daily`,
`LOG_LEVEL=warning`, `LOG_DAILY_DAYS=14`, or an equivalent external policy.
The default single-file channel has no automatic rotation. Agree separate
retention for failed jobs, platform history and coordinated off-host backups;
registration in `schedule:list` proves none of these operational requirements.

Read-only checks, with real failure exit status:

```powershell
php artisan platform:check releases
php artisan platform:check github
php artisan platform:check seo
php artisan platform:audit
php artisan platform:digest
php artisan schedule:list
```

Audit runs only in trusted CLI/scheduler, not via a web action. It invokes fixed
argument lists for Composer validation/audit/direct outdated and npm audit/
outdated, with time limits; it does not install/update packages, run migrations,
rotate keys or make application source writable. Results, exit codes and
dependency/advisory relationships appear in Updates history. Composer package
errors and npm `ENOLOCK` are failures/unknown status, not clean audits. Direct
version reports do not prove compatibility or assess every breaking change.
Review lockfile diffs and major upgrades in an isolated branch; never blanket
`composer update` in production.

## Protected Actions activation

Before enabling any deploy action:

1. Decide the hosting OS, SSH/self-hosted runner boundary, exact release layout,
   web identity and independently protected deployment identity.
2. Create a protected `production` environment requiring reviewers, prevent
   self-review and disable administrator bypass. The dashboard checks these
   protections before dispatch. Install a dedicated GitHub App on only
   `hamidPortfolio`, with Actions read/write, Contents read-only and mandatory
   Metadata read-only. Its short-lived installation token is restricted to that
   repository and permissions. Keep signing keys separate from App/deploy keys.
3. Independently reverify official release, trusted signature and digest in
   CI/worker. Never trust a dashboard boolean as authorization to execute bytes.
4. Build/test in isolation against a dedicated Oracle MySQL test server; resolve
   npm lock/build/audit failures and verify compatible runtime versions.
5. Create verified database, media and configuration backups with private
   credentials files; restore to an isolated staging database and verify assets.
6. Stage a new immutable release. This runner does not run migrations: both
   signed schema versions must equal the installed schema version.
7. Activate atomically where supported; verify HTTPS, public/admin/project/
   preview/contact/asset health before marking success.
8. If health fails, switch the application release back only when schema
   compatible. Never restore an old database over newer user writes.
9. Test recovery/failure/concurrency scenarios on the chosen host before
   enabling approval-to-deploy integration. Keep old releases/backups until a
   reviewed retention policy explicitly removes them.

### Configuration and release preparation

Server-only environment variables: `UPDATE_DEPLOYMENT_ENABLED`,
`UPDATE_GITHUB_APP_ID`, `UPDATE_GITHUB_INSTALLATION_ID` and
`UPDATE_GITHUB_PRIVATE_KEY_PATH`. The PEM path must be outside the public root;
never put the key itself in Git or the environment file. Keep the existing
`RELEASE_PUBLIC_KEY` pinned independently of release assets.

The `production` GitHub environment needs `HOSTINGER_DEPLOY_KEY` as a secret,
and `HOSTINGER_KNOWN_HOSTS` and `RELEASE_PUBLIC_KEY` as variables. The workflow
is fixed to `main` and this repository/site; no dashboard-supplied command,
workflow ref, host, path or repository is accepted. Deployment uses a host-pinned
SSH connection, a host-side lock and the expected installed version.
Restrict the environment's deployment branch policy to `main`. The workflow
tests its verification/extraction helpers and isolated backup, activation,
health-failure, exit-trap rollback and concurrency paths before deployment.

For a reviewed stable `VERSION`, package only tracked application files:

```powershell
python scripts\package-release.py C:\private\release mysql 8.0.0 9.0.0
php scripts\sign-release.php C:\private\release C:\private\release-signing.private
```

Select actual database-family bounds, not the illustrative MySQL bounds above.
The signing key file contains a base64 Ed25519 secret key and stays outside the
application and production host. Publish the ZIP, exact manifest and signature
on the matching stable GitHub tag only after CI and human review.

The runner independently verifies signature/digests, rejects private/runtime
archive paths, installs lockfile dependencies with no scripts/plugins and audits
them, checks host runtime/schema compatibility, creates private SHA256-verified
database/uploads/environment/source backups and atomically switches releases.
Public homepage/privacy/health checks and the exact health version header gate
success. Health failure switches back the old release without restoring a stale
database over new writes. Previous releases and backups are retained. Review
Actions for failures before retrying an ambiguous dispatch. A known terminal
unsuccessful workflow can be reverified after inspecting Actions and the current
site. An unresolved/ambiguous request remains blocked against duplicate dispatch.
