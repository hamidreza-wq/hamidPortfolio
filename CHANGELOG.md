# Changelog

## Unreleased

- Added a Themes page (`/admin/themes`). A theme is the whole look: five
  colours, body and heading fonts, content width, section spacing, card
  corners, interface motion and the particle field.
  - Eight ready-made themes: Cosmic (the original, used as "restore
    default"), Midnight blue, Emerald, Ember, Graphite, Editorial, Terminal
    and Rose.
  - Each theme can be previewed live (nothing is published), applied,
    duplicated to edit, exported as a JSON file, or imported.
  - "Save as a theme" captures the live look.
  - Applying a theme changes only the look, never content, and saves the
    previous look to Content history first, so it can be restored there.
  - Imported files are strict: only known keys are read, every value is
    validated, and uploaded fonts must exist on this site.
  - All ready-made themes are dark on purpose: the public stylesheet
    hard-codes about 300 light-on-dark colours, so a light background would
    make text unreadable until the stylesheet uses colour variables.
  - Existing colour-only presets still work, keeping the current fonts and
    layout when applied.
- **Database schema 7 → 8** (additive: a nullable `theme_presets.settings`
  column).

- Added a Menus page (`/admin/menus`) for the header and footer.
  - The header menu stays automatic (sections marked "Show in navigation")
    until you choose "Customise", which starts from what it currently shows.
    "Go back to automatic" returns to the old behaviour.
  - Items can link to a home-page section, a project page, a site page (top
    of the page, privacy notice, testimonial form) or another website (https
    or mailto only). They can be relabelled, reordered, disabled, opened in a
    new tab, and one header item can show as a button.
  - Items pointing at a hidden section, an unpublished project or a closed
    testimonial form are left out automatically, and the admin page says why.
  - The footer menu sits beside the privacy link, which always stays.
  - Both menus render in the server HTML and in the browser. Dropdowns are
    not offered on purpose.
- **Database schema 6 → 7** (additive: a `menu_items` table). With no rows,
  menus behave exactly as before.

- Media library upgrades:
  - Drag-and-drop or choose up to 10 images at once. The page uploads them
    one per request, so a big batch never exceeds the server's request size
    limit, and shows each file's result. Labels default to the file name.
    Without JavaScript, the same form uploads them together.
  - Filters for usage (used or unused), missing alt text and type, plus
    sorting (newest, oldest, largest, label). Search treats `%` and `_`
    literally.
  - Each card has Copy URL, Download (the stored file under its original
    name), the uploader and date, and a "Missing alt text" marker.
  - Not added on purpose: video, PDF and SVG uploads (nothing on the site
    shows them, and SVG can carry scripts), captions and tags (no public use),
    and thumbnails (images are already re-encoded, typically 20–30 KB).

- Added a Social links page (`/admin/social`) for up to 20 profiles: GitHub,
  LinkedIn, X, Bluesky, Mastodon, Instagram, Facebook, YouTube, TikTok,
  Dribbble, Behance, Stack Overflow, Medium, DEV, a website, or other. Each
  link can be added, edited, relabelled, reordered, enabled or disabled, and
  shown in the header menu, the footer and/or the contact section. Addresses
  must be HTTPS without credentials and must match the platform's own domain
  (for example `linkedin.com`), apart from Mastodon, website and other.
- Links render in the server HTML (header and footer) and in the browser
  (header, footer and contact buttons) with `rel="me noopener noreferrer"`.
  Disabled links are never sent to the browser. Enabled links feed the
  Person `sameAs` list for search engines.
- The live editor's LinkedIn field is replaced by a link to Social links. The
  GitHub profile URL stays there for project repository links.
- **Database schema 5 → 6** (additive: a `social_links` table). The migration
  seeds it from the existing GitHub and LinkedIn URLs, so the site keeps
  showing the same links after the upgrade.

- Added a Health page (`/admin/health`) showing healthy, warning or critical
  for each check.
  - Server checks run on every page view: database connection and speed,
    unapplied migrations, PHP version and extensions, errors logged in the last
    24 hours, writable storage, disk space, cache, scheduler (a once-a-minute
    heartbeat shows whether the cron job is installed), queue backlog and failed
    jobs, and the age of the newest verified backup.
  - Public-site checks run hourly or on demand: HTTP status and response time
    for the home page, privacy notice, sitemap, robots.txt, `/up` and up to five
    project pages; HTTPS certificate validity and days to expiry; security
    headers; and whether the live site serves this installation's version.
    They only request `SEO_MONITOR_ORIGIN`, at a pinned public IP, without
    following redirects.
  - Critical results raise a notification that clears when the check passes.
    `php artisan portfolio:health:check` runs everything from the shell.
- Fixed a search test that wrote real backup files into local storage.

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
