# Changelog

## Unreleased

- Added a Testimonials manager (`/admin/testimonials`): a review queue
  (waiting, approved, rejected) with approve, reject, edit, feature, order and
  delete, plus adding testimonials received elsewhere. Only approved
  testimonials are published. They appear automatically, featured first, in
  any visible section of type Testimonials, before that section's hand-written
  items. The sidebar shows how many are waiting, and search finds them.
- Added a shareable client form at `/testimonials/new`, closed by default
  and opened from the Testimonials page. It asks for consent and keeps the
  email private (stored only to confirm the testimonial is genuine). It is
  protected like the contact form: a timed single-use token, a honeypot, a
  limit of 3 posts per 10 minutes per IP and 2 per day per email. The page is
  `noindex`. Rejected submissions are deleted after 90 days, and the privacy
  notice now covers testimonials.
- **Database schema 4 → 5** (additive: a `testimonials` table and a
  `portfolio_settings.testimonial_submissions_open` flag defaulting to off).
  Deploy it as a reviewed migration, like schema 4.

- Added dashboard-wide search. Press Ctrl K (⌘ K) on any admin page, or use
  the new search button, for instant results grouped by type: dashboard pages
  (matched on everyday words such as "2fa" or "colours"), projects, sections,
  site settings, skills, media, fonts and contact messages. Each result opens
  the place where that item is edited. `/admin/search` shows full results with
  a type filter. Wildcards are matched literally, results are rendered as text
  only, and search has its own rate limit (120 a minute) so typing never uses
  up the limit shared by other admin actions.

- Added a Backups page (`/admin/backups`). Each backup is one ZIP in private
  storage with every database table's rows (except sessions, cache and jobs)
  and every uploaded image and font, plus a manifest of row counts and SHA-256
  checksums. Every backup is verified by reading it back, and can be checked
  again later. Backups run daily (`BACKUP_SCHEDULE`, default 03:15 UTC) and on
  demand. The newest `BACKUP_KEEP` (default 14) are kept, and the newest
  verified backup is never pruned or deleted. Downloading or deleting needs the
  password. Failures raise a notification and are written to the activity log.
- Restore runs from the server shell only:
  `php artisan portfolio:backup:restore <file>`. It writes and verifies a
  safety backup first, enables maintenance mode, restores the database in one
  transaction that rolls back on error, then swaps in the uploaded files. It
  refuses a backup from a different schema version or one with unsafe paths.
  `php artisan portfolio:backup:create` creates one from the shell.
  Backups do not include `.env`. Keep `APP_KEY`, which encrypted columns need.

- Added a Security page (`/admin/security`): read-only protection checks
  (debug mode, environment, HTTPS, session cookie flags, lifetime, throttling,
  headers, two-factor status, admin count), failed sign-in counts and sources,
  your signed-in sessions with password-confirmed "sign out other sessions",
  and a filterable, paginated activity log.
- Added optional two-factor sign-in (TOTP, works with any authenticator app):
  QR or manual-key setup confirmed with a code, 8 single-use recovery codes,
  replay protection, a 5-minute challenge window, throttled attempts, and
  disabling requires the password plus a current code. The secret and recovery
  codes are encrypted at rest. Lockout recovery from the server shell:
  `php artisan portfolio:two-factor:reset <email>`.
- Overview: "Review SEO" now opens SEO monitoring, and repeated actions in
  Recent changes are grouped (for example "Media Updated × 8").
- New dependency: `bacon/bacon-qr-code` (server-side QR rendering, so the
  secret is never sent to a third party).
- **Database schema 3 → 4** (additive, nullable `users` columns). The one-click
  updater refuses schema-changing releases by design, so this release must be
  deployed as a reviewed migration: back up the database, deploy the code, then
  `php artisan migrate:status` and `php artisan migrate --force`. Package it
  with `python scripts/package-release.py <dir> mysql <min> <max> 3`.
- Release scripts no longer hard-code the schema: `package-release.py` and
  `verify-release.mjs` read `platform.schema_version` from
  `backend/config/platform.php`, and packaging takes an optional previous schema
  for migration releases.

## 0.3.2 - 2026-11-23

- Project cards on the home page now link straight to each project page, with
  a "View project" button and a secondary "Quick look" dialog; card numbers past
  nine read 10, 11 instead of 010.
- Search Console token refresh failures now report Google's reason (expired or
  revoked authorization, or a rejected OAuth client), log the OAuth error code
  without token material, and offer a one-click Reconnect for the stored property.
- Database schema is unchanged.

## 0.2.0-dev (unreleased)

- Added explicit public-source/verified-demo publication controls and private
  source labels across cards, dialogs, detail pages and editor preview.
- Added reusable effective test-database verification, fresh/two-baseline
  upgrade preservation tests and reverified isolated SQL/upload restoration.
- Extended axe and public Chromium/Firefox/WebKit coverage; corrected visible
  accessible names and Firefox 200%-scaled body overflow.
- Corrected actual CI failures: PHP 8.3-compatible Composer resolution and
  strict GitHub metadata comparison independent of MySQL JSON object order.
- Corrected a cross-platform WebKit Tab-traversal test assumption while retaining
  first-focus-target and skip-link keyboard activation assertions.
- Increased queue visibility defaults above monitor runtime limits; added
  operational timing and optional OAuth failure/disconnect regressions.
- Final local source verification: 150 PHPUnit tests/934 assertions, one
  GD-absence-only skip; 11 browser cases passed; dependency audits/build pass.
  Actual matrix and remaining owner gates:
  [final release readiness](docs/qa/FINAL-RELEASE-READINESS.md).

### Earlier development-stage evidence

- Integrated CMS hardening, managed fonts/media, project SEO and live preview.
- Trusted release review, public GitHub draft discovery and technical SEO monitoring.
- Added an isolated MySQL CI workflow and regression checks for first-party analytics versus optional Search Console data.
- Fresh local verification passed 136 PHPUnit tests (798 assertions; one GD-absence-only skip), six Edge browser tests, Composer/npm audits, the production build and PHP formatting.

This development version is not a signed production release. Deployment remains
disabled pending required branch protections, hosting, production integrations
and intended-host restore/rollback procedures. Corrected-source CI has genuinely
passed PHP 8.3/8.5 on MySQL 8.4.13 and all 11 browser cases; this supports staging
preparation, not merge or production deployment.
