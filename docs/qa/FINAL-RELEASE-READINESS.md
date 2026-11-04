# Final release readiness

Date: 2026-11-04. Branch: `copilot/final-security-deployment-hardening`.
Source evidence: `95ef441f2f524f5796ce3aabfa873bbf06246385`.
Subsequent documentation commits do not change this tested application source.

**Status: READY FOR STAGING, not production deployment.**
Production deployment is not approved. Draft [PR #1](https://github.com/hamidreza-wq/hamidPortfolio/pull/1)
must not be merged until the checks and owner-controlled release gates pass.
No merge, production deployment, DNS/hosting change, update activation or
owner database reset/reseed/restore was performed.

## Evidence and provenance

This report supersedes earlier readiness conclusions, not their incident/failure
history. Counts in earlier reports describe different snapshots and test stages.
The original owner-database incident remains actual data loss:
**previous custom content, accounts, messages and audit history were not recovered**.
Rebuilt defaults and later post-incident backups are not recovery of that data.

| Verification | Fresh actual result |
|---|---|
| Local runtime | Windows; PHP 8.5.11; Laravel 13.40.0; MariaDB 10.4.32 through PDO MySQL |
| Full PHPUnit | 150 tests, 934 assertions; 149 passed, one conditional skip; no failures |
| Conditional skip | `AssetManagementTest::test_missing_gd_fails_closed` requires GD absent; local GD is enabled |
| Composer | Strict manifest validation, actual platform requirements, audit and Pint passed; no advisories |
| JavaScript | `npm ci` succeeded; audit zero vulnerabilities; root ES-module syntax passed |
| Production scaffold build | Vite 7.3.7, 61 modules; 33.75 kB CSS / 59.10 kB JS before gzip |
| Real browser suite | All 11 cases passed in 2.2 minutes: eight Edge cases, plus public Chromium, Firefox and WebKit |
| Blade | Compilation passed on explicit isolated test configuration; cache cleared afterward |
| Schedules | Six expected tasks registered; registration is not host execution |
| Security review | Bounded read-only static review found no high-confidence exploitable vulnerability; not penetration-test certification |
| GitHub CI | **Passed** at source `95ef441`; PHP 8.3/8.5 on Oracle MySQL 8.4.13; all 11 browser cases passed in 59.7 seconds |

The browser suite passed after the dependency correction in `30d5493`; `d9ad9d7`
then changed only GitHub JSON comparison and its backend regression.
The full backend suite passed again at `d9ad9d7`.
The subsequent `95ef441` changes only the platform-dependent WebKit focus
expectation; all four public engines passed again locally (1.5 minutes).
The scaffold build is distinct from the canonical root cosmic frontend, which
the browser tests actually exercise.

Commands actually executed from the backend, with explicit isolated configuration:

```text
php -d extension=sodium artisan portfolio:test-database:verify
php -d extension=sodium vendor\bin\phpunit --display-skipped
composer validate --strict --no-interaction
composer check-platform-reqs
composer audit --no-interaction
vendor\bin\pint --test
npm ci
npm audit --no-fund
npm run build
node --check ..\app.mjs
node --check ..\portfolio-data.mjs
php -d extension=sodium artisan view:cache
php -d extension=sodium artisan view:clear
php -d extension=sodium artisan schedule:list --no-ansi
npm run test:browser
git diff --check
```

Each passed in its stated stage. Composer's bundled schema dependency emits
PHP 8.5 deprecation notices. Local policy blocked an esbuild postinstall, but
ordinary install/build succeeded without bypassing that policy.
GitHub separately executes PHP syntax checks and the Artisan test runner with
extensions enabled in its runtime. Node 26.8.2/npm 12.0.2 were used locally;
CI builds on Node 22. These are build environments, not intended-host evidence.
An actual local extension probe confirmed PDO MySQL, Fileinfo, GD, cURL, DOM,
mbstring, sodium (with the stated flag) and ZIP. Local storage and bootstrap
cache directories are writable. No corresponding hosting capability or
permission check is claimed.

### Real CI failures, not suppressed

- [Initial PR run](https://github.com/hamidreza-wq/hamidPortfolio/actions/runs/41871282940):
  PHP 8.3 could not install a PHP-8.5-resolved lockfile containing Symfony 8.1
  packages requiring PHP 8.4.1. Added Composer `config.platform.php=8.3.0` and
  resolved dependencies with `--with-all-dependencies --minimal-changes`.
  This also selected Laravel 13.40.0 and compatible Symfony 7.4 dependencies.
  No platform requirement was ignored.
- [Second PR run](https://github.com/hamidreza-wq/hamidPortfolio/actions/runs/41872266692):
  both matrix jobs reached Oracle MySQL tests, exposing metadata comparison
  dependent on JSON object-key order. Canonically sort metadata keys before a
  strict comparison; retain strict value types. A repeated/reordered scan now
  preserves approval and the actual previous metadata. Tests were not skipped.
- Workflow is verification-only, with pinned actions, read-only permissions,
  isolated MySQL 8.4 and separate browser schema; it has no deployment job.
- [Third PR run](https://github.com/hamidreza-wq/hamidPortfolio/actions/runs/41873009926):
  both backend jobs passed (149 passed/one conditional skip, 934 assertions on
  Oracle MySQL 8.4.13), but Linux WebKit tabs to the skip link while Windows
  WebKit skips links by default. Ten browser cases passed; the platform-specific
  assumption failed. The test now accepts only these two known traversal modes
  and still requires successful keyboard skip-link activation in either mode.
  Final corrected run:
  [41874142601](https://github.com/hamidreza-wq/hamidPortfolio/actions/runs/41874142601).

### Completed corrected source matrix

| Job | Actual status | Evidence |
|---|---|---|
| `verify (8.3)` | **success**, completed 2026-11-04T17:09:16Z | [Job 126728859222](https://github.com/hamidreza-wq/hamidPortfolio/actions/runs/41874142601/job/126728859222): MySQL tests and all 11 browser cases passed |
| `verify (8.5)` | **success**, completed 2026-11-04T17:07:00Z | [Job 126728858814](https://github.com/hamidreza-wq/hamidPortfolio/actions/runs/41874142601/job/126728858814): 149 passed, one GD-absence skip, 934 assertions; browser steps intentionally assigned only to PHP 8.3 |

Both jobs ran Composer/npm audits, install/build, formatting/syntax, view
compilation and fresh/upgrade database suites. Overall run **success**, source
SHA `95ef441f2f524f5796ce3aabfa873bbf06246385`. The source's technical CI gate is
**Fixed and verified**; after the documentation-only commit, require its own
current-head check too. There is still no permission to merge or deploy.

## Database safety, upgrades and recovery

**Fixed and verified:** PHPUnit and the verification command require testing
environment, MySQL driver, an explicit `*_test` schema, no `DB_URL` override and
an exact `SELECT DATABASE()` match before database mutations.
Local PHPUnit used `hamid_portfolio_final_test`; browser operations used
`hamid_portfolio_browser_20261104_test` on port 8147 with private storage and
a random test-only administrator. Owner port 8000 was not a fixture target.
The QA server was stopped after validation, its temporary credential file was
removed, and the untouched owner service health endpoint still returned 200.
Private backup/restore evidence and requested screenshots were preserved.

The database suite exercises fresh migrate/seed and upgrades from both the
initial CMS migrations and the previous hardening schema. Synthetic users,
projects, settings, sections and messages retain original values and counts.
The new link-visibility migration safely replays without duplicate columns.
Historical migrations were not rewritten in this final verification phase.
Existing installations must use reviewed additive migrations, never reseeding.

### Four separate backup assurance levels

| Level | Evidence and status |
|---|---|
| Backup creation | **Fixed and verified:** private post-incident SQL snapshot and uploaded-asset backup preserved outside Git |
| File integrity | **Fixed and verified:** SQL 53,802 bytes, modified 2026-11-04T15:40:38Z; current SHA-256 below; three assets/36,189 bytes match their manifest |
| Isolated restore | **Fixed and verified:** fresh separate schema, 29 tables/81 rows; every table count/checksum matches the earlier isolated reference; each copied asset hash matches; additive migrations succeed and restored homepage returns 200 |
| Production disaster recovery | **Owner configuration required:** no intended host, off-host retention, production restore-time objective, switch-over or full rollback rehearsal verified |

SQL SHA-256:
`6aeacddaab93a67a3045cda80c3e22fd8354fd60872ab9bcdc5ca9fe9e8b160f`.
An earlier checkpoint contained a 63-character transcription, not a valid
SHA-256 digest. The fresh digest corrects that typo; restored table/reference
checksums and asset hashes were independently compared. No dump, private
copied content, token or test password is committed.

Restore execution used `php -d extension=sodium
<private-session>\files\readiness-database.php restore` with an explicitly
verified test connection. The private helper refused existing target schemas
and dump-level database-selection statements, invoked native MySQL with
`--defaults-extra-file` and the explicit
`hamid_portfolio_restore_verified_20261104_test` database, removed its temporary
credential file, compared every restored table to the earlier test reference,
copied/hashed each asset, then applied additive migrations and rendered the
homepage. Successful exit was zero. Its evidence JSON is private, not a
production restore tool or permission to rerun against the owner database.

## Public project links

**Fixed and verified:** OnePrayer/Bible, Home Ledger/myAccountingHome,
Interpreters Hub/interpurting-laravel and BetweenWords/meisaPortfolio are
owner-confirmed private. Cards, dialogs and detail pages show **Private source
code**, not public GitHub links. Private full repository URLs are omitted from
anonymous payloads; frontend fallback URL synthesis was removed.

Office Converters/officeConverters was anonymously verified public. No live
demo was independently verified or invented. Repository publication and live
demo verification are explicit editor controls, default false for unknown
sources. The internal URL remains editable; public discovery remains public-only.
**Known limitation:** private GitHub authentication is not implemented.

## Production and integration disposition

| Item | Classification | Evidence / required action |
|---|---|---|
| Intended host, canonical domain, TLS/proxies, secure cookies, web-root permissions | **Owner configuration required** | No hosting/domain supplied or changed; perform staging HTTPS smoke tests |
| Repository protections | **Owner configuration required** | Read-only API: `main` unprotected, no rulesets; require both PHP CI jobs and reviewed PRs; disallow force/direct pushes |
| Repository scanning | **Owner configuration required** | Secret scanning and push protection enabled; Dependabot security updates disabled; vulnerability-alert endpoint returned 404, so alert enablement is not certified |
| Release activation | **Implemented but not verified** | Trust-verification fixtures pass; no genuine signed production release or hosting activation; keep installation disabled |
| SMTP receipt | **External service unavailable** | Local mail transport is `log`; SMTP username/password absent; no real delivery claimed. Keep digests off until legitimate test-mailbox receipt succeeds |
| Digest failure handling | **Fixed and verified** | Mocked transport failure returns failure, records failed run and does not advance sent timestamp; this is not delivery evidence |
| Scheduler/queue installation | **Owner configuration required** | Supervised worker and minute scheduler not installed/verified on intended host |
| Queue timing/locks | **Fixed and verified** | Default visibility retry 180s exceeds monitor timeout 150s; one attempt, unique lock 300s, overlap/single-server locks; regression tests cover timing and duplicate checks |
| SEO public-origin scan | **External service unavailable** | `SEO_MONITOR_ORIGIN` absent; SSRF protections remain intact; no successful public scan invented |
| Search Console OAuth | **Owner configuration required** | Client ID, secret and callback absent. Disconnected reporting works without outbound requests; connect fails explicitly until configured |
| OAuth application behavior | **Fixed and verified** | Mocked read-only scope, encryption, property validation, invalid state, disconnected behavior and disconnect preserving first-party data pass; live provider not certified |
| Accessibility | **Known limitation** | Axe has no serious/critical findings on exercised pages; native screen-reader quality, physical touch devices and full WCAG/contrast certification outstanding |
| Performance | **Known limitation** | Local observations only; no Lighthouse, PageSpeed, Web Vitals, query budget or load certification |
| Original lost data | **Known limitation** | Not recovered; preserved post-incident backups do not reverse the incident |

## Accessibility scope

Public checks run in Edge, Chromium, Firefox and WebKit. Edge additionally
tests admin login/dashboard/editor/projects/analytics with axe, drawer inertness,
Escape/focus return, hidden-section privacy, responsive geometry and motion.
Fixed visible-label/accessibility-name mismatches rather than suppressing axe
rules. Firefox exposed body minimum-width overflow under 200% CSS scaling;
the actual CSS was fixed and all four public engines then passed.

Checks include reduced motion, forced colors, keyboard skip/dialog behavior and
200% CSS scaling/control bounds. CSS scaling is **not native browser UI zoom
certification**. Windows WebKit's default Tab traversal skips links; Linux
WebKit's observed default includes them. Both modes retain strict first-target
and skip-link activation checks. Axe incomplete/moderate/minor
results are not certification; no full screen-reader or physical-device audit.

## Safe owner deployment checklist

1. Keep PR draft/unmerged until its current-head matrix is genuinely green and
   required branch protections are configured. Never infer green from local tests.
2. Select a staging host/domain; verify required PHP extensions, MySQL privileges,
   document root, dotfile/private-file denials, upload execution denial and TLS.
3. Preserve the existing `APP_KEY`; create private coordinated SQL/assets backups,
   test off-host recovery and review pending additive migrations. Do not reseed
   existing content or overwrite newer data with a historical backup.
4. Configure production HTTPS/proxy/session/cache settings and permissions.
   Apply reviewed migrations only as a separately authorized deployment action.
5. Install supervised queue worker and minute scheduler, or leave optional
   monitoring uninstalled and digests explicitly off. Use retry >150s and shared
   locks, inspect failed jobs, alert on failures and verify real task execution.
6. Configure rotating logs (for example `LOG_STACK=daily`, `LOG_LEVEL=warning`,
   `LOG_DAILY_DAYS=14`) or external rotation; default single-file logs do not
   establish retention. Agree backup/log/failed-job retention and alert ownership.
7. Leave GSC disconnected and digests off until legitimate credentials and exact
   callback/property/mailbox checks pass. Set a real public SEO origin only when
   available; private GitHub access remains unsupported.
8. Record staging HTTPS login/logout, publish/preview, upload, contact, project,
   public pages, sitemap and robots checks; rehearse matching-release rollback.
   Only then make a new production-readiness decision. Nothing here authorizes
   merging, DNS changes, release installation or production deployment.
