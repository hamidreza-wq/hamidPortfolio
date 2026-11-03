# Hamid Reza Bayat — portfolio

A responsive cosmic portfolio with an integrated Laravel 13 administration studio. The public website remains the canonical frontend in the repository root (`index.html`, `styles.css`, and the ES modules). Laravel serves those same files, injects published content from MySQL, and provides a protected dashboard with a live preview of the actual portfolio.

## Static preview

For a frontend-only preview, run a static file server from the repository root:

```powershell
python -m http.server 4173
```

Open `http://localhost:4173`. This mode uses the verified fallback data in `portfolio-data.mjs`; CMS changes and the contact inbox are available only through Laravel.

## Laravel setup

Requirements: PHP 8.3+, Composer, and MySQL 8.0+ (or a compatible MySQL server).

1. Create a dedicated MySQL database and application account. Grant the account access only to the portfolio database.
2. From `backend`, copy `.env.example` to `.env`. Set `APP_URL`, `DB_HOST`, `DB_DATABASE`, `DB_USERNAME`, and `DB_PASSWORD` for the local environment, then generate the application key:

   ```powershell
   Copy-Item .env.example .env
   php artisan key:generate
   ```

3. Install dependencies, migrate and seed the verified portfolio content, and link public media storage:

   ```powershell
   composer install
   php artisan migrate --seed
   php artisan storage:link
   ```

4. Create the single administrator interactively and start the Laravel server:

   ```powershell
   php artisan portfolio:admin:create
   php artisan serve
   ```

   Open `http://127.0.0.1:8000/admin/login`. The create command prompts for the administrator details; no default admin credentials are seeded.

The Laravel document root must be `backend/public`. Keep the root portfolio files next to `backend/`; the public controller loads the root `index.html` and serves only the allowlisted frontend assets. Do not copy the frontend into a separate Blade theme.

## Dashboard and publishing

- **Live site editor:** edits profile links, SEO metadata, theme colors/fonts/layout/motion, section copy/order/visibility, and skill groups. The embedded preview loads the public portfolio and receives same-origin draft updates; publishing submits validated values to MySQL.
- **Projects:** manage verified project details, GitHub links, visibility, SEO fields, and display order. Drafts are excluded from the public page.
- **Media library:** upload JPG, PNG, or WebP images up to 5 MB. Configure the public disk and ensure `storage:link` is available in production.
- **Contact inbox:** the public contact form validates and rate-limits submissions; messages are stored in MySQL with a keyed hash of the submitter IP rather than the raw IP address.
- **Audit trail:** publishing and administrative content actions record the authenticated administrator and action details.

## Tests

Tests use MySQL, not SQLite. Create a separate test database and least-privilege test account, then copy `backend/.env.testing.example` to `backend/.env.testing` and set its database credentials. PHPUnit targets `hamid_portfolio_test` by default.

```powershell
cd backend
Copy-Item .env.testing.example .env.testing
php artisan test
```

The test suite uses Laravel's database refresh behavior. Never point `DB_DATABASE` in `.env.testing` at a database containing data you need to keep.

## Production and operations

- Set `APP_ENV=production`, `APP_DEBUG=false`, a strong `APP_KEY`, and production MySQL credentials in the deployment environment. Do not commit `.env` files.
- Point the web server at `backend/public`; ensure the parent repository files remain readable by the PHP application.
- Configure a durable, private backup for MySQL and uploaded media. Test database restoration before relying on backups.
- Configure outbound mail if contact messages should also generate notifications; the inbox itself works without an email provider.
- Use HTTPS, and set the canonical URL in the admin editor only after the final public domain is configured.
- The dashboard is intended for a single owner. Create accounts only through the protected server-side admin command and remove access promptly when it is no longer needed.

## Portfolio accessibility and motion

The frontend includes a keyboard skip link, visible focus states, semantic sections, a keyboard-operable project dialog, reduced-motion support, responsive navigation, and a decorative canvas particle field. Theme controls can disable interface animation and particles.

## Verified project content

Project descriptions are based on the public READMEs for `Bible`, `officeConverters`, `myAccountingHome`, `interpurting-laravel`, and `meisaPortfolio`. OnePrayer is labeled as an early build because native device testing and mobile product screens are incomplete. No live demo URLs or screenshots are claimed without verification.
