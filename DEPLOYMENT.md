# Production deployment and rollback

## Release gates

- Review `HARDENING-REPORT.md` for actual verification results and unresolved limitations. A successful build alone is not a deployment approval.
- Use PHP 8.3+ with PDO MySQL, Fileinfo and GD. Enable GD before accepting image uploads. Install Composer dependencies with `composer install --no-dev --optimize-autoloader` from `backend`.
- Use a dedicated MySQL application account with access only to the application database. Use a different database and account for tests. Tests require a database name ending in `_test`.
- Run `composer validate`, `composer audit`, `npm audit`, `npm run build` and `php artisan test` in a development/CI environment. Do not run the database-refresh tests on production.
- Verify uploaded fonts are owned/licensed for web use. No third-party fonts are bundled.

## Server configuration

- The only web document root is `backend/public`, never the repository root. Preserve root portfolio assets beside `backend`; Laravel delivers the allowlisted files.
- Deny dotfiles and prohibit PHP/CGI execution in `/storage`, `/media` and `/fonts`. Do not serve private storage, logs, dumps, Composer credentials, Git metadata or test configuration.
- Use HTTPS. Set `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL=https://your-domain`, `SESSION_SECURE_COOKIE=true`, `SESSION_HTTP_ONLY=true`, `SESSION_SAME_SITE=lax` and a suitable `SESSION_LIFETIME`. Generate `APP_KEY` once and preserve it across releases.
- Set database credentials outside Git. Grant write access only to `storage` and `bootstrap/cache`; do not make application source writable by the web server.
- Configure TLS termination and trusted proxies explicitly when using a reverse proxy. Verify Laravel sees requests as HTTPS before relying on HSTS and secure cookies. HSTS is emitted only for HTTPS production requests.
- Check CSP in a browser: scripts/styles receive nonces, external framing is blocked, and the same-origin admin preview is allowed. Inline style attributes remain allowed because existing theme/layout controls rely on them; inline script handlers are not allowed.
- Keep MySQL, PHP, web server and system dependencies patched. Review private contact and authentication logs under an explicit retention policy.

## Controlled deployment

1. Back up MySQL and uploaded media/font files before schema changes. Use database-native tools such as `mysqldump` with a protected credentials option file; never put passwords in command arguments or Git.
2. Restore that backup to a separate staging database and verify that the application and assets can be recovered.
3. Install the reviewed release, retaining private environment configuration and durable storage.
4. Review pending migrations with `php artisan migrate:status`. Run `php artisan migrate --force` only as an explicit deployment step. Composer installation does not migrate the database.
5. Run `php artisan storage:link` where filesystem/web server permissions allow it; uploaded asset routes must remain available.
6. Run `php artisan config:cache` and `php artisan view:cache`. Restart long-lived application processes so their environment/configuration is refreshed.
7. Create the owner account with `php artisan portfolio:admin:create` if absent. Resetting an existing administrator requires explicit confirmation. Existing ordinary users are never promoted by this command.
8. Smoke-test public pages, published project URLs, sitemap/robots, admin login/logout, upload rejection, publish/preview and contact submissions over HTTPS.

## Rollback and restoration

- If smoke tests fail, stop publishing and roll back the release to the previously verified application version.
- Do not blindly run `migrate:rollback`: removing columns can destroy content. Use the reviewed database backup and matching application release if the schema is incompatible.
- Restore MySQL to an isolated database first, then switch application configuration under maintenance control. Restore media/fonts from the matching backup. Preserve `APP_KEY`.
- Clear/rebuild configuration and view caches; restart processes and repeat smoke tests.
- Content revisions are not substitutes for MySQL/asset backups.

## Remaining controls

No public self-registration or emailed password reset is provided. Recovery requires trusted shell access and the administrator command. Optional two-factor authentication and CAPTCHA require a separately reviewed provider/configuration; do not treat them as implemented.
