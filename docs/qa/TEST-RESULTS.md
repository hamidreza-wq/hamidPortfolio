# Test results

Date: 2026-11-03. Baseline `ce7787c` plus uncommitted QA fixes. No commit, push or deployment performed.

## Environment and isolation

- Windows; PHP 8.5.11; Laravel 13.39.0; Node 26.8.2/npm 12.0.2.
- Database runtime is **MariaDB 10.4.32**, accessed through Laravel's `mysql` driver. **Oracle MySQL was not available/tested.**
- PHPUnit explicitly selects `APP_ENV=testing`, `DB_CONNECTION=mysql`, `DB_DATABASE=hamid_portfolio_test`, empty `DB_URL`.
- Browser uses separate `hamid_portfolio_qa_test`, port 8147 and session-private storage/session cookie. No `migrate:fresh` was run on the owner database. QA creation refused an existing schema and used additive migrations only.
- Local QA server enables GD/sodium per process. That does not change the owner's shared PHP configuration.

## Commands and outcomes

Run PHP commands from [backend](../../backend/). PowerShell environment assignments must be made in the same process as the command.

| Validation | Actual outcome |
|---|---|
| `php -d extension=gd -d extension=sodium vendor\bin\phpunit --display-skipped` | 133 tests, 787 assertions, 1 conditional skip; 132 passed |
| `php vendor\bin\phpunit tests\Feature\AssetManagementTest.php --filter test_missing_gd_fails_closed` | 1 passed, 3 assertions with GD actually disabled; exercises the conditional branch skipped above |
| Focused content/privacy suite during development | 23 tests, 219 assertions passed before later additional robots/anchor assertions |
| `composer validate --strict --no-interaction` | Passed; Composer's bundled schema dependency emitted PHP 8.5 deprecation notices |
| `composer audit --no-interaction` | No security advisories |
| Initial `npm audit --omit=dev` | Failed `ENOLOCK` |
| Initial `npm run build` | Failed: Vite missing |
| Ordinary `npm install --ignore-scripts --no-fund --no-progress` | Succeeded; no policy bypass or alternate package transport used |
| `npm install --save-dev @playwright/test --ignore-scripts --no-fund --no-progress` | Succeeded; adds required reproducible browser tooling and lockfile |
| `npm audit` | No vulnerabilities in restored dependency tree |
| `npm run build` | Passed, Vite 7.3.7, 61 modules; 33.72 kB CSS/59.10 kB JS before gzip |
| `vendor\bin\pint --test --dirty` | Passed |
| `node --check app.mjs` and `node --check portfolio-data.mjs` | Passed |
| `npm run test:browser` | Five cases passed in a clean-window full run, including real logout and evidence capture; PNG-width validation was subsequently strengthened and rerun as recorded below |

The Vite build validates the scaffold bundle; the actual public cosmic frontend is served from root HTML/CSS/ES modules. The browser tests exercise that actual frontend, not just compiled scaffold output.

### Browser regression execution

From `backend`, with the explicitly isolated QA server already running:

```powershell
$env:QA_DATABASE = 'hamid_portfolio_qa_test'
$env:QA_CREDENTIAL_FILE = 'C:\path\outside\repository\qa-credential.json'
npm run test:browser
```

The private JSON file contains a QA-only `email` and `password`; never commit it. The configuration refuses to run without the explicit QA database marker and targets only loopback port 8147, not owner port 8000. It uses installed Microsoft Edge; it neither starts a server nor seeds/migrates a database.

Cases:

1. Public keyboard skip link, search/empty state, category filtering, dialog Escape/focus and ten viewport widths.
2. Publish hidden sections only in QA, assert omitted DOM/payload and no initialization errors, restore visibility in `finally`.
3. Mobile admin inert navigation, opening, Escape/focus return, desktop resize.
4. Particle-frame instrumentation confirms animation/particle toggles stop animation callbacks.
5. Credential-free exact-size public/admin screenshot capture; inbox is filtered empty.

Intermediate outcomes:

- First hidden-section test timed out waiting for full page `load`, including the live preview. Changed publish navigation synchronization to `domcontentloaded` with explicit DOM assertions.
- The next run exposed an actual null hero scene animation error after hiding all sections; fixed with optional scene access.
- Four functional regressions then passed.
- Adding screenshot evidence produced a real HTTP 429 after repeated login runs within the route's one-minute window. Rate limits were left intact; the clean-window full run below subsequently passed. Do not clear the app cache or relax production protection just to get a green test.

A subsequent full run passed all five cases in 1.2 minutes (8.7s, 16.4s, 2.7s, 3.7s, 37.0s). Logout and the following guest-preview redirect passed. Exact PNG verification subsequently identified that native full-page capture included intentional offscreen decorative bounds; long screenshot clipping and PNG-header assertions were added rather than labelling those expanded images “390px mobile.”

The final focused capture test passed in 42.7s with assertions on **both** PNG width and height: 25 persistent screenshots, desktop 1440x900, mobile 390x844, tablet 768x1024, corresponding correctly clipped full-length captures, desktop admin pages and 390px mobile admin states. Other four regression cases were unchanged from the successful full run. Earlier imprecise captures were overwritten or specifically removed.

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
