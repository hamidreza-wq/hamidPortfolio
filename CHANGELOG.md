# Changelog

## Unreleased

- Added a Self-test page (`/admin/self-test`, the operations permission),
  plus `php artisan portfolio:self-test`, run every Monday at 04:30.
  - **Public site crawl**: only `SEO_MONITOR_ORIGIN`, at a pinned IP,
    without following redirects, and at most 60 addresses. It checks the
    home page, every published project and page, links and images on the
    home page, `#section` links, the privacy notice, `/up`, and that
    `styles.css` and `app.mjs` are served with the right types.
  - **SEO**: the sitemap and robots.txt, published items missing from the
    sitemap, missing title, description, canonical or viewport, and images
    without alt text.
  - **Forms**: the contact form's address and tokens, and the open
    testimonial form.
  - **Uploads**: a generated image is re-encoded, a test font is checked,
    and a PHP file named `.png` is refused, all in memory and never saved.
  - **Database**: a test write and read, rolled back.
  - **Dashboard**: search runs, every admin route has a permission, an owner
    exists, and who has two-factor on. Plus the mail transport, and every
    Health check.
  - **Report**: each result shows where, what happened, severity and what to
    do, filterable by status. Failures raise a notification that clears when
    a run passes.
  - **Not tested, and labelled as such**: JavaScript errors and phone
    layouts need a browser (`npm run test:browser`), and email delivery isn't
    tested, to avoid sending real email.

- Admin roles and permissions.
  - Five roles: Owner, Administrator (everything except users), Editor
    (content, design, media), SEO manager (content, analytics, SEO) and
    Support (inbox, testimonials).
  - Permissions are enforced on every admin route through one map in
    `App\Support\Roles`. A route missing from the map is owner-only, and a
    test fails if any admin route is unmapped.
  - The sidebar, top bar, overview links and dashboard search only show what
    each role can open.
  - Everyone keeps the overview, search, help, notifications, and their own
    two-factor and sessions. The Security page's checks, failed sign-ins and
    activity log need the security permission.
  - A Users page (owners only):
    - add someone with a one-time set-password link (stored hashed, expires
      after 48 hours, so no password is ever sent)
    - change roles
    - issue a new link
    - remove access, which signs the person out everywhere and keeps their
      name in the activity log
    - every action needs the owner's password; nobody can change themselves,
      and there's always at least one owner
  - Existing administrators become owners, and `portfolio:admin:create`
    creates owners. A Users help article is added.
- **Database schema 11 → 12** (additive: `users.role`, `setup_token_hash`,
  `setup_expires_at`; existing admins set to owner).

- Google Fonts integration on the Fonts page.
  - Search, see the specimen on Google Fonts, tick weights (400 and 700 by
    default) and normal or italic, then add the font to the library.
  - Each weight becomes a library font, selectable in the live editor and
    themes, with a sample line on the Fonts page.
  - The file is copied onto the site, so visitors never contact Google. This
    keeps their IP addresses away from Google and fits the existing
    `font-src 'self'` CSP.
  - Only `fonts.googleapis.com` and `fonts.gstatic.com` are contacted. The
    family name and weights are validated first, redirects aren't followed,
    files are capped at 2 MB, and every file passes the same checks as an
    uploaded font.
  - The licence note and specimen link are recorded, and imports are logged.
  - Without `GOOGLE_FONTS_API_KEY`, about 40 popular families are offered and
    any family can be added by its exact name. With a key, the whole
    catalogue is searchable (cached for a day).

- Live site editor upgrades:
  - **Click to edit**: clicking text in the preview opens and highlights the
    field that changes it. Clicking a section opens its settings. This can
    be switched off.
  - **Device preview**: Desktop, Tablet (768 px) and Phone (390 px).
  - **Drag and drop** sections by a handle, or use the ↑/↓ buttons, which
    work by keyboard. Display order numbers renumber themselves.
  - **Undo and redo** for the whole draft (buttons, plus Ctrl Z or Ctrl
    Shift Z outside text boxes), an "Unpublished changes" indicator, and a
    warning before leaving with unpublished changes.
  - The preview reports clicks only inside the editor's preview frame,
    only to the same origin, and highlights with inline styles so the CSP
    is unchanged. Publishing works exactly as before.
  - A new test syntax-checks the editor's inline script and `app.mjs` with
    Node.

- Added a Pages manager (`/admin/pages`) for standalone pages at
  `/your-page`, such as Services, CV or Now.
  - Each page has a title, an address, a summary, Markdown content, a search
    title and description, a featured and sharing image, and a "don't list in
    search engines" option.
  - Drafts, publishing, and scheduled publishing (a page appears at its
    "publish at" time with no job needed).
  - Admin preview of any page, with a banner. Duplicate, trash with restore,
    and permanent delete with confirmation.
  - Every save is kept in Content history and can be restored there.
  - Content is safe Markdown: raw HTML is escaped, `javascript:` links are
    dropped, images only from the media library, and links to other sites
    open in a new tab.
  - Addresses already used by the site (`/admin`, `/privacy`, `/up` and so on)
    are reserved. The public route is registered last and excludes them.
  - Pages can be added to the footer menu, or the custom header menu, from
    the page itself. Menus gain a "One of your pages" link type, and links
    disappear while a page isn't live.
  - Published pages are added to the sitemap, and appear in dashboard search
    and the Help centre.
  - Fix: media used in Markdown (`![alt](/media/5)`) now counts as in use, so
    it can't be deleted.
- **Database schema 10 → 11** (additive: a `pages` table).

- Notification centre upgrade:
  - **Seven categories**: security, site health, backups, updates, messages,
    SEO and GitHub. Health and backup notices move out of "updates".
  - **New alerts**:
    - new contact messages and testimonials waiting for review (counts
      only; they clear themselves once read or reviewed)
    - 10 or more failed sign-ins in an hour
    - two-factor sign-in turned off, from the page or the command line
    - a recovery code being used
  - **Immediate email** for high-priority alerts (opt in). The
    every-minute scheduler sends it (`platform:alerts`), so no queue worker
    is needed. Alerts are batched to at most one email every 15 minutes,
    and only new or returning problems email you, not reworded ones.
  - **Page**: category and status filters with unread counts, "Mark all
    read", readable category names, and high priorities first.
  - The Notifications help article is updated.
- **Database schema 9 → 10** (additive): `notification_preferences.instant_alerts`
  and `platform_notices.alerted_at`. The migration adds the new categories to
  existing preferences, keeping existing choices.

- Added a Help centre (`/admin/help`) with 22 articles, one for every
  dashboard page plus "Getting started" and "Searching the dashboard".
  - Each article covers what the page is for, step-by-step instructions,
    warnings and things worth knowing.
  - Articles are Markdown files in `backend/resources/help`, so they ship with
    the release they describe. Raw HTML is escaped and unsafe links dropped.
  - The help index has search. Every page's top bar has a Help button that
    opens its article, and dashboard search (Ctrl K) includes help articles.
  - A test fails if a new admin page has no help article, or if an article
    links to a page that doesn't exist.
  - No screenshots yet: the new pages haven't been deployed to capture them.

- Security review of the dashboard stack, written up in `SECURITY-REVIEW.md`.
  Fixes:
  - Symfony 7.4.23 → 7.4.24 (http-kernel, routing and mailer advisories).
  - Switched off the private disk's unused signed-URL `/storage/{path}` routes.
  - Session data is encrypted by default (`SESSION_ENCRYPT=true`), and the
    Security page reports it.
  - `Cross-Origin-Opener-Policy: same-origin` on every response.
  - Opt-in `HSTS_INCLUDE_SUBDOMAINS`.
  - The report lists the open risks and the steps to take after deploying.

- Added a Privacy page (`/admin/privacy`).
  - **Inventory** of the personal data the site holds (contact messages,
    testimonials, activity-log IP addresses, admin sessions, pageview counts,
    backups), with record counts, the oldest record and how long each is kept.
    It also notes what lives outside the database (owner notification emails,
    Google Analytics).
  - **Retention**: optionally delete contact messages after 90 days, 6
    months, 1 year or 2 years, and clear IP addresses from the activity log
    after a chosen period (entries stay). Applied daily. Both default to
    "keep", so nothing is deleted until you choose.
  - **Data requests**: look up an email address (sent by POST, so it never
    appears in URLs or logs), download everything held for it as JSON, or
    erase it permanently (password and confirmation required). The audit log
    records a one-way reference, not the address.
  - A clear note that the tools don't make the site legally compliant on
    their own.
- The public privacy notice now states the chosen contact-message retention
  and how backups affect it, and adds a "Your rights" section.
- **Database schema 8 → 9** (additive: two nullable retention columns on
  `portfolio_settings`, defaulting to keep).

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
