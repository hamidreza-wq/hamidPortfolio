# Production deployment and rollback

## Current release decision

See [final release readiness](docs/qa/FINAL-RELEASE-READINESS.md) for the
2026-11-04 evidence on `copilot/final-security-deployment-hardening`, application
source `95ef441f2f524f5796ce3aabfa873bbf06246385`, actual CI runs,
isolated restore results and remaining owner-controlled gates.
Production is not approved; keep [PR #1](https://github.com/hamidreza-wq/hamidPortfolio/pull/1)
draft and do not deploy, merge or change DNS/hosting.
Corrected-source CI passed both PHP 8.3/8.5 jobs on Oracle MySQL 8.4.13 and all
11 browser cases. **READY FOR STAGING** is the current evidence-based status;
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

These are owner-run instructions for a separately approved release, not commands
executed against production by this task.

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

See [operational requirements](backend/docs/platform-operations.md) for
supervised workers, minute scheduling, shared locks and digest failure handling.
Queue visibility defaults to 180 seconds, above the 150-second monitor timeout;
preserve this ordering for environment overrides. Confirm actual worker execution,
failed-job alerting and log rotation on the intended host. Mail currently uses
`log` locally; no real SMTP receipt or live GSC/SEO-origin validation is claimed.
Leave digests off, GSC disconnected and release installation disabled until
their documented owner checks pass.

No public self-registration or emailed password reset is provided. Recovery requires trusted shell access and the administrator command. Optional two-factor authentication and CAPTCHA require a separately reviewed provider/configuration; do not treat them as implemented.
