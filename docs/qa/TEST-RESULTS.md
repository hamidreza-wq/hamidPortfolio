# Test results

## Current evidence: 2026-11-04 final verification

Branch `copilot/final-security-deployment-hardening`; application source
`95ef441f2f524f5796ce3aabfa873bbf06246385`.
The [canonical final evidence matrix](FINAL-RELEASE-READINESS.md) records actual
CI conclusions and classifies unresolved production gates. This section
supersedes the historical counts and uncommitted/CI statements below.

- Full direct PHPUnit, sodium enabled: **150 tests, 934 assertions, 149 passed,
  one GD-absence-only skip**, PHP 8.5.11/MariaDB 10.4.32.
- All **11 browser cases passed** in 2.2 minutes: Edge full suite plus public
  Chromium/Firefox/WebKit. Explicit browser schema:
  `hamid_portfolio_browser_20261104_test`; session-private storage/credentials.
- Strict Composer validation, actual platform requirements, Composer/npm audits,
  Pint, root module syntax, Vite build and isolated Blade compile passed.
- Fresh install and two upgrade baselines retain synthetic content. Restored
  snapshot has 29 tables/81 rows, per-table checksums and three matching assets.
- Actual CI exposed PHP 8.3 lockfile incompatibility and MySQL JSON-key ordering;
  both root causes were fixed without suppressing tests. See linked run history
  and final verdict in the canonical report.
- Both backend jobs then passed on Oracle MySQL 8.4.13; Linux WebKit exposed
  an OS-specific Tab-traversal test assumption. The corrected test still
  requires keyboard skip-link activation; four public engines passed locally
  again. Final combined CI conclusions are recorded in the canonical report.
- Corrected source [GitHub CI passed](https://github.com/hamidreza-wq/hamidPortfolio/actions/runs/41874142601):
  both PHP 8.3/8.5 jobs succeeded against Oracle MySQL 8.4.13; 11 browser cases
  passed in 59.7 seconds on the PHP 8.3 job. One GD-absence-only skip is explained
  above. Source is **READY FOR STAGING**, not production approval.

Reproduce local PHP validation from the backend using a pre-created, disposable
schema/account, never the owner's application credentials:

```powershell
$env:APP_ENV = 'testing'
$env:DB_CONNECTION = 'mysql'
$env:DB_DATABASE = 'hamid_portfolio_final_test'
$env:DB_URL = ''
php -d extension=sodium artisan portfolio:test-database:verify
if ($LASTEXITCODE -ne 0) { throw 'Unsafe test database configuration' }
php -d extension=sodium vendor\bin\phpunit --display-skipped
```

Direct PHPUnit retains the local sodium flag; `artisan test` starts another
PHP process. CI enables sodium in its runtime and runs `artisan test`.
Browser command below now selects the new browser schema; it runs Edge plus
the public cross-engine subset, not a full admin suite on every engine.
Native UI zoom/screen-reader/physical-device/performance certification remains
a **Known limitation**. Intended-host smoke tests are **Owner configuration required**.

## Historical verification stage (superseded)

Date: 2026-11-04. Current work is on `copilot/final-security-deployment-hardening`, based on `12f1fa6`; changes remain uncommitted. No push or deployment was performed.

## Environment and isolation

- Windows; PHP 8.5.11; Laravel 13.39.0; Node 26.8.2/npm 12.0.2.
- Database runtime is **MariaDB 10.4.32**, accessed through Laravel's `mysql` driver. **Oracle MySQL was not available/tested.**
- PHPUnit explicitly selects `APP_ENV=testing`, `DB_CONNECTION=mysql`, `DB_DATABASE=hamid_portfolio_final_test`, empty `DB_URL`; the shared test base verifies the effective MySQL database name ends in `_test`.
- Browser uses separate `hamid_portfolio_final_qa_test`, port 8147 and session-private storage/session cookie. No `migrate:fresh` was run on the owner database. QA creation refused an existing schema and used additive migrations only.
- Local PHP 8.5 has GD enabled; sodium was enabled for PHPUnit using `-d extension=sodium`. The browser server used its own isolated QA database. None of these test settings change production configuration.

## Commands and outcomes

Run PHP commands from [backend](../../backend/). PowerShell environment assignments must be made in the same process as the command.

| Validation | Actual outcome |
|---|---|
| `php -d extension=sodium vendor\bin\phpunit --display-skipped` | **136 tests, 798 assertions; 135 passed, 1 conditional skip.** The skipped `missing_gd_fails_closed` case specifically requires GD to be absent; GD is enabled in the current PHP runtime. |
| `composer validate --strict --no-interaction` | Passed; Composer's bundled schema dependency emitted PHP 8.5 deprecation notices |
| `composer audit --no-interaction` | Passed; no security advisories |
| Initial `npm audit --omit=dev` | Failed `ENOLOCK` |
| Initial `npm run build` | Failed: Vite missing |
| Ordinary `npm install --ignore-scripts --no-fund --no-progress` | Succeeded; no policy bypass or alternate package transport used |
| `npm install --save-dev @playwright/test --ignore-scripts --no-fund --no-progress` | Succeeded; adds required reproducible browser tooling and lockfile |
| `npm audit --no-fund` | Passed; zero vulnerabilities |
| `npm run build` | Passed, Vite 7.3.7, 61 modules; 37.42 kB CSS/59.10 kB JS before gzip |
| `vendor\bin\pint --test` | Passed |
| `node --check app.mjs` and `node --check portfolio-data.mjs` | Passed |
| `npm run test:browser` | Fresh run: **six Edge tests passed in 1.4 minutes**, including the analytics separation check and exact PNG width/height assertions |

The Vite build validates the scaffold bundle; the actual public cosmic frontend is served from root HTML/CSS/ES modules. The browser tests exercise that actual frontend, not just compiled scaffold output.

### Browser regression execution

From `backend`, with the explicitly isolated QA server already running:

```powershell
$env:QA_DATABASE = 'hamid_portfolio_final_qa_test'
$env:QA_CREDENTIAL_FILE = 'C:\path\outside\repository\qa-credential.json'
$env:QA_SCREENSHOT_DIR = 'C:\path\outside\repository\screenshots'
npm run test:browser
```

The private JSON file contains a QA-only `email` and `password`; never commit it. The configuration refuses to run without the explicit QA database marker and targets only loopback port 8147, not owner port 8000. It uses installed Microsoft Edge; it neither starts a server nor seeds/migrates a database.

Cases:

1. Public keyboard skip link, search/empty state, category filtering, dialog Escape/focus and ten viewport widths.
2. Publish hidden sections only in QA, assert omitted DOM/payload and no initialization errors, restore visibility in `finally`.
3. Mobile admin inert navigation, opening, Escape/focus return, desktop resize.
4. Analytics administration/reporting remains separate from optional Google Search Console results.
5. Particle-frame instrumentation confirms animation/particle toggles stop animation callbacks.
6. Credential-free exact-size public/admin screenshot capture; inbox is filtered empty.

Intermediate outcomes:

- First hidden-section test timed out waiting for full page `load`, including the live preview. Changed publish navigation synchronization to `domcontentloaded` with explicit DOM assertions.
- The next run exposed an actual null hero scene animation error after hiding all sections; fixed with optional scene access.
- Four functional regressions then passed.
- Adding screenshot evidence produced a real HTTP 429 after repeated login runs within the route's one-minute window. Rate limits were left intact; the clean-window full run below subsequently passed. Do not clear the app cache or relax production protection just to get a green test.

Earlier exploratory runs exposed the frontend's missing-hero null reference, login throttling after repeated attempts and over-wide native full-page screenshots; the implementation/tests were corrected without weakening rate limits. The fresh six-test run passed with the final PNG-header width/height assertions. Screenshots were written outside the repository.

## Browser and HTTP evidence

Actual UI flows and response codes are listed in [FUNCTIONALITY-AUDIT.md](FUNCTIONALITY-AUDIT.md); finite link results in [LINK-AUDIT.md](LINK-AUDIT.md); viewport evidence in [RESPONSIVE-AUDIT.md](RESPONSIVE-AUDIT.md).

Expected negative probes produced 404 draft/deleted-project responses, 403/404 private-file denials, 419 raw forgery denials, 503 intentional maintenance and 429 login throttling. These must not be presented as unexplained console-clean success. No unhandled application exception remained in the final functional Edge regressions.

## Performance observations and limits

- One local visible-browser sample: DOMContentLoaded approximately 1231ms, load 1238ms, 393 DOM elements and four resource requests. Earlier sample DCL 1314ms with canonical CSS/module/data resources. These are warm/local observations, not production Web Vitals.
- Built-in PHP server is single-process; concurrent preview/Edge/visible browser requests affect timings.
- Disabled animation/particle work previously continued scheduling frames; fixed and measured by callback-count regression.
- No invented Lighthouse/PageSpeed score. No Lighthouse, production TTFB, LCP/CLS lab, SQL query budget, load/stress test or cross-browser performance certification was completed.

## Acceptance status

**Partial, not deployment-ready.** Oracle MySQL, four unavailable external repositories, release trust/hosting/restore gates, real public HTTPS SEO monitoring, Search Console, SMTP/scheduler/queue operations and comprehensive accessibility/performance certification remain open. Successful tests/build/audits are separate observations, not proof the application is safe to deploy.
