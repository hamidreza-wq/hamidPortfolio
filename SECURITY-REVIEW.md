# Security review: admin dashboard stack

**Date:** 26 November 2026
**Scope:** `main` plus the dashboard PR stack #4–#13 (security page and two-factor sign-in, backups, search, testimonials, health, social links, media, menus, themes, privacy), reviewed together on `feat/security-review`.
**Method:** code review of every route, controller, service, view and browser script, plus Composer and npm audits, a scan of the repo and its history for secrets, and read-only checks of the live site's response headers and of the HTTP status codes for sensitive paths.

This is a code-level review by an AI assistant, not a penetration test. Passing tests are not proof of security. The risks still open are listed at the end.

## Fixed in this review

| # | Finding | Severity | Fix |
|---|---|---|---|
| 1 | Three Symfony packages had published advisories: `http-kernel` (forwarded headers changing generated URLs), `routing` (URLs generated for another host) and `mailer` (STARTTLS can be stripped by a man in the middle, which affects the contact-form emails). | Medium | Updated to Symfony 7.4.24 within the existing constraints. `composer audit` now reports none. |
| 2 | The private `local` disk had `serve => true`, which registers unauthenticated `GET` and `PUT /storage/{path}` routes for signed temporary URLs. That disk now holds the backups. Nothing used the routes, but anyone holding `APP_KEY` could have downloaded backups or uploaded files through them. | Low, but high impact | `serve => false`. The routes are gone, and a test pins that. |
| 3 | Session payloads were stored unencrypted. They briefly hold the one-time two-factor recovery codes, plus the CSRF token and sign-in state. | Low | Default is now `SESSION_ENCRYPT=true` (config and `.env.example`), and the Security page reports it. **The live server's `.env` must be changed too** (see Operator actions). |
| 4 | No `Cross-Origin-Opener-Policy` header. | Low | `same-origin` is now sent on every response. |
| 5 | HSTS couldn't cover subdomains. | Informational | Opt in with `HSTS_INCLUDE_SUBDOMAINS=true`, but only once every subdomain serves HTTPS. |

Regression tests: `tests/Feature/SecurityReviewTest.php`, plus the updated `RequestSecurityTest`.

## Area by area

| Area | Result |
|---|---|
| Authentication | Login throttled per email and IP (5 a minute) and per route. A generic error message means accounts can't be enumerated. The session is regenerated on sign-in, and logout is POST with CSRF. Optional TOTP two-factor has replay protection, a 5-minute challenge, single-use bcrypt-hashed recovery codes, and turning it off needs the password plus a code. Admins are created from the CLI only, with a 12-character minimum password. |
| Authorisation | Every `/admin` route sits behind `auth`, `auth.session` and `admin` (the `is_admin` check). Only the expected routes are public: site pages, sitemap, robots, media, fonts, `/up`, the contact and testimonial forms, and login. Route-model binding and fixed-pattern names (backups) prevent references to other records. |
| CSRF | Laravel's CSRF protection is on for all web routes, with no exceptions. Cookies are `SameSite=Lax`. |
| XSS | Blade escapes all output. The only raw outputs are the server-generated QR SVG, the colour swatches (escaped, hex-validated values), JSON-LD (`JSON_HEX_*` flags) and the font CSS (generated names, validated weights and styles). Browser scripts escape every value they insert as HTML, or use `textContent`. URLs pass through `safeURL()` / `menuHref()`. Scripts are nonce-only in the CSP. |
| SQL injection | Every query is bound. Raw fragments are constants, or integers read from the database (the media `FIELD()` sort). `LIKE` searches escape `%` and `_`. |
| Mass assignment | Models use `$fillable`. Requests are validated before writes, and sensitive fields use explicit `forceFill`. `GithubRepository` is `$guarded = []`, but only the GitHub discovery service writes to it, never request input. |
| File uploads | Images: signature, size and pixel limits, then a GD decode and re-encode, which strips metadata. Fonts: format signatures and table checks, served with an exact MIME type and `nosniff`. SVG, PDF and video are refused. Uploads live outside the web root and are served by ID. |
| Path traversal | Files are addressed by database IDs. Backup names must match `backup-YYYYMMDD-HHMMSS-hex.zip`. Backup manifests reject `..`, absolute and Windows paths, and unsafe table names. The release ZIP inspection (existing) rejects traversal. |
| SSRF | Outbound requests go only to fixed hosts (GitHub, Google OAuth and Search Console) or to the server-configured `SEO_MONITOR_ORIGIN`, pinned to a public IP. Redirects aren't followed, and responses are size- and time-limited. No visitor-supplied URL is ever fetched. |
| Session security | Live cookies are `Secure`, `HttpOnly` and `SameSite=Lax`, with a 120-minute lifetime and database storage. Sessions can be listed and other sessions signed out. Encryption is fixed as above. |
| Passwords | Bcrypt (12 rounds), with a 12-character minimum. There's no check against breached passwords, which matters less with two-factor on. |
| API security | There's no public API. The JSON endpoints (search, media upload) are admin-only, same-origin and CSRF-protected. |
| Rate limiting | Login, two-factor, contact, testimonials, backups, privacy actions, themes and health checks are all throttled. Search has its own named limiter. See the open risk on shared counters. |
| Secrets | No secrets are committed in the tree or its history (only `.env.example` files). The release signing key and deploy keys stay outside the app. Two-factor secrets and Google refresh tokens are encrypted with `APP_KEY`. |
| Dependencies | Composer: clean after fix 1. npm: 0 vulnerabilities. `platform:audit` runs daily. |
| Admin access | Single administrator. Two-factor is available, but not enforced. |
| Backups | Stored in private storage (`0600`), outside the web root, verified by checksum on creation. Download and delete need the password. Restore is CLI-only, takes a safety backup first and runs in a transaction. |
| Encryption | Encrypted columns use `APP_KEY` (AES-256-CBC with a MAC). HTTPS everywhere, with HSTS. |
| Privacy / GDPR | Inventory, retention settings (off by default), and data export and erasure tools. The audit log stores a hashed reference, not the email address. See `/admin/privacy`. |
| Security headers | Live: CSP (nonce scripts, `frame-ancestors 'self'`, `object-src 'none'`, `upgrade-insecure-requests`), HSTS for one year, `nosniff`, `X-Frame-Options`, `Referrer-Policy`, `Permissions-Policy`, and now COOP. HTML is `no-store, private`, and the Hostinger CDN treats pages as `DYNAMIC`, not cached. |
| HTTPS | HTTP redirects to HTTPS with a 301. The certificate is checked hourly by the Health page. The web root is `backend/public`, so `.env`, `.git`, app code, storage and logs are not reachable (404 or 403). |

## Open risks

| Risk | Severity | Recommendation |
|---|---|---|
| Two-factor is optional, and there's one admin account. Until it's turned on, a stolen or guessed password is enough to get in. | Medium until enabled | Turn on two-factor in **Security**, and store the recovery codes in a password manager. |
| The live `.env` probably sets `SESSION_ENCRYPT=false` explicitly, which overrides the new default. | Low | Set `SESSION_ENCRYPT=true` on the server. Everyone is signed out once. |
| Backups sit on the same server as the site, as unencrypted ZIPs, protected only by file permissions and the admin password. Losing the hosting account loses the backups, and a downloaded copy holds password hashes and messages. | Medium (operational) | Download a backup regularly and keep it encrypted off-site. Consider encrypting backups with a separate key. |
| `APP_KEY` is a single point of failure. It protects sessions, encrypted columns and signed tokens, so a leak compromises all of them, and rotating it breaks two-factor secrets and the Google connection. | Low (high impact) | Keep it only in the server `.env`. If it leaks, rotate it, then re-enrol two-factor and reconnect Google. |
| Plain `throttle:N,M` routes share one counter per user, or per IP for guests, across all such routes. A burst on one route can temporarily block another. This is mainly a reliability problem, and it doesn't weaken the limits. | Low | Move sensitive routes to named limiters (as search already uses). |
| The CSP allows `style-src-attr 'unsafe-inline'`, needed by inline `style=""` attributes across the site and admin. | Low | Scripts stay nonce-only. Remove it only if inline styles are moved to classes. |
| `/up` reveals the installed version in `X-Portfolio-Version`. | Informational | It's used by deploy health checks, so it's kept. |
| Admin passwords aren't checked against breach lists. | Low | Use a password manager, and enable two-factor. |
| The forms use a honeypot, a timed token and rate limits, but no CAPTCHA. A determined spammer could still post a few messages an hour from each IP. | Low | Testimonials are moderated, and contact messages are private. Add a privacy-friendly challenge if spam appears. |
| The Health page shows the latest error-log message, which could contain data from exceptions. | Low | Only administrators can see it. |
| **Not covered:** a human penetration test; the Playwright browser specs (they need the QA server); the Hostinger server itself (PHP settings, file permissions, other sites on the account); the stacked PRs running in production. | — | Run the browser specs, deploy, then re-run the live header check and the Health page. |

## Operator actions after deploying the stack

1. Set `SESSION_ENCRYPT=true` in the server `.env`, then clear the config cache if one is used.
2. Turn on two-factor sign-in on the Security page.
3. Add the `php artisan schedule:run` cron job, which backups, retention and health checks need.
4. Take a backup, download it, and store it encrypted off-site.
5. Optional: once every subdomain serves HTTPS, set `HSTS_INCLUDE_SUBDOMAINS=true`.
