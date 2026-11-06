# Hamid Reza Bayat — portfolio

A responsive cosmic portfolio with an integrated Laravel 13 administration studio. The public website remains the canonical frontend in the repository root (`index.html`, `styles.css`, and the ES modules). Laravel serves those same files, injects published content from MySQL, and provides a protected dashboard with a live preview of the actual portfolio.

## Static preview

For a frontend-only preview, run a static file server from the repository root:

```powershell
python -m http.server 4173
```

Open `http://localhost:4173`. This mode uses the verified fallback data in `portfolio-data.mjs`; CMS changes and the contact inbox are available only through Laravel.

## Laravel setup

Requirements: PHP 8.3+, Composer, and MySQL 8.0+ (or a compatible MySQL server). PHP must provide PDO MySQL and Fileinfo. Image uploads additionally require GD; unsupported uploads fail rather than storing unprocessed files.

1. Create a dedicated MySQL database and application account. Grant the account access only to the portfolio database.
2. From `backend`, copy `.env.example` to `.env`. Set `APP_URL`, `DB_HOST`, `DB_DATABASE`, `DB_USERNAME`, and `DB_PASSWORD` for the local environment:

   ```powershell
   Copy-Item .env.example .env
   ```

3. For a **new empty installation only**, install dependencies, migrate and seed the verified portfolio content, and link public media storage:

   ```powershell
   composer install
   php artisan key:generate
   php artisan migrate --seed
   php artisan storage:link
   ```

   Generate the key only on a new installation with an empty `APP_KEY`. Preserve the existing key on upgrades. Composer setup does not rotate keys or run database migrations.

   For an existing installation, first back up and verify an isolated SQL/assets
   restore, review `php artisan migrate:status`, then apply `php artisan migrate`
   as an explicitly approved operation. Do not run `--seed`, `migrate:fresh`,
   reset/wipe or key generation over existing data.

4. Create the single administrator interactively and start the Laravel server:

   ```powershell
   php artisan portfolio:admin:create
   php artisan serve
   ```

   Open `http://127.0.0.1:8000/admin/login`. The create command prompts for the administrator details; no default admin credentials are seeded. It refuses to promote an existing ordinary user, and resetting an existing administrator requires confirmation. There is no public registration or emailed password-reset route.

The Laravel document root must be `backend/public`. Keep the root portfolio files next to `backend/`; the public controller loads the root `index.html` and serves only the allowlisted frontend assets. Do not copy the frontend into a separate Blade theme.

## Dashboard and publishing

- **Live site editor:** edits profile links, SEO metadata, theme colors/fonts/layout/motion, section copy/order/visibility, and skill groups. The embedded preview loads the public portfolio and receives same-origin draft updates; publishing submits validated values to MySQL.
- **Projects:** manage verified project details, GitHub links, visibility, SEO fields, and display order. Drafts are excluded from the public page. Repository links appear only when marked public; otherwise public surfaces say "Private source code". Demo links require explicit verification. Unknown links default withheld.
- **Media library:** upload JPG, PNG, or WebP images up to 5 MB. Configure the public disk and ensure `storage:link` is available in production.
- **Fonts:** upload licensed WOFF2, WOFF, TTF or OTF files, record family/weight/style and source/license information, then select an active font in the live editor. The actual portfolio receives generated `@font-face` rules; built-in system stacks remain available.
- **Sections:** manage controlled plain-text content blocks, ordering, visibility and media without injecting arbitrary HTML. Existing canonical portfolio sections remain protected from deletion.
- **Content history:** review supported site/project snapshots and explicitly restore an earlier version. Revisions are not backups for uploaded assets or the database.
- **Color presets:** save a draft palette without publishing it, or load a saved palette into the preview before explicitly publishing.
- **Contact inbox:** the public contact form validates and rate-limits submissions; messages are stored in MySQL with a keyed hash of the submitter IP rather than the raw IP address.
- **Analytics:** first-party counts of successful public page loads, top pages and external referrer hosts. It does not store visitor IPs, user agents or full referrer URLs. Optional Google Analytics 4 tracking stays off until a visitor opts in; the choice can be changed through Privacy settings. Search Console read-only metrics remain separate.
- **Audit trail:** publishing and administrative content actions record the authenticated administrator and action details.
- **SEO:** unique titles/descriptions, canonical URLs, robots policy and Open Graph/Twitter cards are rendered in HTML. Homepage JSON-LD connects the `ProfilePage`, `Person` and `WebSite` using stable canonical identifiers and the configured public profile links. Published project pages include `CreativeWork` and canonical breadcrumbs; authorship remains gated by confirmed rights. Library sharing images supply dimensions and alt text. Draft projects are not public pages or sitemap entries.
- **Software updates:** discover official GitHub Releases and verify trusted Ed25519 manifests, GitHub/SHA-256 digests and ZIP contents before password-confirmed review. Deployment remains disabled until hosting and protected GitHub Actions are configured.
- **GitHub projects:** automatic public-only read-only discovery creates real draft projects with a review queue. Existing edits and third-party rights are preserved; private repository authentication is not implemented.
- **SEO monitoring:** bounded, DNS-pinned scans of the configured public site produce actionable findings and notifications. Search Console data is shown only when its optional read-only OAuth integration is connected.

Search metadata is managed in **Live site editor > Search & sharing**; project
overrides remain in the project editor. Describe actual work and use unique,
useful summaries rather than keyword stuffing. A public HTTPS sharing image
around 1200 x 630 pixels is recommended; upload your own image to the library,
provide descriptive alt text and select it as the default sharing image.
Only indexable public pages advertise expanded Google snippet/image previews.
Preview and maintenance/indexing controls retain their noindex protections.
No fake ratings, locations, credentials, obsolete meta-keywords, or ranking
guarantees are added. Google may rewrite snippets and takes time to crawl changes;
submit the canonical `/sitemap.xml` in a verified Search Console property.
Search Console verification/OAuth requires the owner's real Google account and
credentials and is not accomplished by adding meta tags.
- **Notifications:** deduplicated in-app history, priority/read/dismiss/snooze and category preferences, with opt-in daily/weekly email digests through a configured mail transport.

## Request protection

Admin routes require authentication and administrator authorization. Login regenerates the session, logout invalidates it, and failed attempts are rate limited. Administrator privilege cannot be set through ordinary user mass assignment.

Contact forms use CSRF protection, a honeypot, a signed form-age token, one-use tokens and IP/email rate limits. Send after at least `CONTACT_MIN_SECONDS` (default 2); reload forms older than `CONTACT_MAX_SECONDS` (default 7200). Set a shared cache/session store for multi-server deployments so limits and session protection remain consistent.

HTML responses are private and not shared-cached because they contain contact/session tokens. CSP uses script/style nonces and same-origin framing, preserving the real portfolio preview. Inline style attributes remain allowed for the existing theme/layout implementation, but inline event handlers are not allowed.

## Tests

Tests use MySQL, not SQLite. Create a separate test database and least-privilege test account, then copy `backend/.env.testing.example` to `backend/.env.testing` and set its database credentials. PHPUnit targets `hamid_portfolio_test` by default.

```powershell
cd backend
Copy-Item .env.testing.example .env.testing
$env:APP_ENV = 'testing'
$env:DB_CONNECTION = 'mysql'
$env:DB_DATABASE = 'hamid_portfolio_test'
$env:DB_URL = ''
php artisan portfolio:test-database:verify
php artisan test
```

The test suite uses Laravel's database refresh behavior. Never point `DB_DATABASE` in `.env.testing` at a database containing data you need to keep. A runtime guard requires MySQL and an effective database name ending in `_test` before refresh operations.

`--env=testing` alone does not select a test database when `.env.testing` is absent. Destructive Artisan reset/wipe/rollback commands are now blocked outside a verified `*_test` connection and the testing environment. Use a test-only database account. See the [database incident disclosure and current verification report](PLATFORM-REPORT.md).

If GD is installed but not enabled in the local PHP configuration, you can run the PHP tests without changing the shared configuration:

```powershell
php -d extension=gd -d extension=sodium vendor\bin\phpunit
```

## Production and operations

Start with the [final release evidence and safe owner checklist](docs/qa/FINAL-RELEASE-READINESS.md).
The [functionality audit](docs/qa/FUNCTIONALITY-AUDIT.md), [test results](docs/qa/TEST-RESULTS.md), [security audit](docs/qa/SECURITY-AUDIT.md), [link audit](docs/qa/LINK-AUDIT.md), [responsive evidence](docs/qa/RESPONSIVE-AUDIT.md) and [fix report](docs/qa/BUG-FIX-REPORT.md) preserve dated historical stages separately from current results. Local database verification used MariaDB; Oracle MySQL acceptance is a separate GitHub CI gate.

The npm lockfile is included. From `backend`, use `npm ci`, `npm audit` and `npm run build` for reproducible scaffold validation. `npm run test:browser` uses installed Edge plus public Chromium/Firefox/WebKit coverage and requires the explicitly isolated QA server/database and private credential-file environment variables documented in the test report. It must not target an owner's installation. Repeated runs may hit the real login rate limit; wait for its window rather than disabling protection.

Follow the [deployment, backup and rollback checklist](DEPLOYMENT.md) and review the [hardening verification report](HARDENING-REPORT.md) before deployment.
The [platform operations guide](backend/docs/platform-operations.md) documents signed manifests, schema changes, scheduler/queue/mail setup, public GitHub review, monitoring limits and the deliberately disabled deployment boundary.

- Set `APP_ENV=production`, `APP_DEBUG=false`, a strong `APP_KEY`, and production MySQL credentials in the deployment environment. Do not commit `.env` files.
- Point the web server at `backend/public`; ensure the parent repository files remain readable by the PHP application.
- Configure a durable, private backup for MySQL and uploaded media. Test database restoration before relying on backups.
- To email new contact messages, set `CONTACT_RECIPIENT` to the owner's mailbox
  and configure `MAIL_HOST`, `MAIL_PORT`, `MAIL_SCHEME`, `MAIL_USERNAME`,
  `MAIL_PASSWORD`, and `MAIL_FROM_ADDRESS` for authenticated SMTP. Use the
  authenticated mailbox as From; the visitor is Reply-To, never From.
  Notifications use SMTP directly (no log/failover fallback or queue worker);
  `MAIL_TIMEOUT` defaults to 10 seconds. Leave `CONTACT_RECIPIENT` empty for
  inbox-only operation. Messages are saved before sending; SMTP failures log
  only the message ID and show explicit saved-but-not-emailed feedback, without
  losing the message or asking visitors to resubmit. Delivery to the receiving
  mailbox must be checked separately from SMTP acceptance.
- Use HTTPS, and set the canonical URL in the admin editor only after the final public domain is configured.
- The dashboard is intended for a single owner. Create accounts only through the protected server-side admin command and remove access promptly when it is no longer needed.

## Troubleshooting

- **Could not open input file: artisan:** run PHP commands from `E:\hamidPortfolio\backend`, or use `php backend\artisan ...` from the root.
- **SQLite/no such table errors:** this project uses MySQL. Check `.env`, run `php artisan config:clear`, and restart any old Laravel processes. Do not migrate SQLite to hide an environment mismatch.
- **Images rejected because GD is unavailable:** enable PHP GD for the web-server PHP process; enabling it only in a test command does not enable the running application.
- **GitHub Repository not found:** verify that `origin` points to an existing repository and that Git authentication can access it.
- **npm audit has no lockfile or installation is blocked:** do not report an audit/build as successful. Restore dependencies and create a reviewed lockfile in an environment where package downloads are allowed; then run `npm audit` and `npm run build`.

## Portfolio accessibility and motion

The frontend includes a keyboard skip link, visible focus states, semantic sections, a keyboard-operable project dialog, reduced-motion support, responsive navigation, and a decorative canvas particle field. Theme controls can disable interface animation and particles.

## Verified project content

Project descriptions are based on the public READMEs for `Bible`, `officeConverters`, `myAccountingHome`, `interpurting-laravel`, and `meisaPortfolio`. OnePrayer is labeled as an early build because native device testing and mobile product screens are incomplete. No live demo URLs or screenshots are claimed without verification.

That describes the original content research, not guaranteed current repository availability. Four sources were subsequently owner-confirmed private and are now labelled "Private source code" rather than linked publicly. Office Converters remains public; no live demo is published without verification. See the [current link disposition](docs/qa/LINK-AUDIT.md). No replacement repository, ownership claim or feature description was fabricated.
