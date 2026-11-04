# Security audit

## Current evidence: 2026-11-04 final verification

Branch `copilot/final-security-deployment-hardening`; application source
`95ef441f2f524f5796ce3aabfa873bbf06246385`.
See [final release readiness](FINAL-RELEASE-READINESS.md) for current CI,
backup assurance levels and classified open gates.

The final bounded read-only review found no high-confidence exploitable
vulnerabilities in its reviewed changes. This is static review, not live-host
or OAuth certification. Earlier publication-boundary findings below remain
fixed and covered by current PHPUnit/browser regressions.

| # | Severity | File | Lines | Vulnerability | Confidence |
|---|----------|------|-------|---------------|------------|
| - | - | - | - | No new high-confidence vulnerability reported in the bounded review | - |

Fresh local regression: **150 tests/934 assertions**, one GD-absence-only skip;
**11 browser cases passed**. Audits report no dependency advisories.
Private repository URLs are no longer public links/payload URLs; unknown demos
remain withheld. OAuth failure/disconnect tests preserve first-party analytics.
Actual MySQL CI failure revealed JSON-order sensitivity and was corrected;
no passing MariaDB result is substituted for Oracle MySQL acceptance.

SQL and uploaded assets were restored to a fresh isolated schema/directory and
checked, not to the owner database. Owner data remains untouched in this phase;
the original data-loss incident remains unrecovered.
Repository API inspection: secret scanning/push protection enabled;
`main` unprotected/no rulesets; Dependabot security updates disabled.
Protection/dependency-alert configuration is **Owner configuration required**.
Both corrected-source CI jobs passed on Oracle MySQL 8.4.13, with all 11 browser
cases passing; the final report links the exact run/jobs.
Production TLS/proxy/permissions/SMTP/scheduler/live OAuth checks are also
**Owner configuration required**, not passed security controls.

## Historical audit and prior evidence (superseded where updated above)

Original audit: 2026-11-03, baseline `ce7787c`; regression evidence refreshed 2026-11-04 on `copilot/final-security-deployment-hardening`. This is a local audit, not a penetration-test certification or deployment approval.

## Confirmed finding

| # | Severity | File | Lines | Vulnerability | Confidence |
|---|----------|------|-------|---------------|------------|
| 1 | 🟡 MEDIUM | [PublicPortfolioController.php](../../backend/app/Http/Controllers/PublicPortfolioController.php#L168) | 168-189 | Unpublished section text, structured items and media references were serialized into anonymous homepage HTML despite being visually hidden. **Fixed.** | 9/10 |

An independent read-only security review found this publication-boundary issue. The user selected “Fix all issues.” Public section queries now include only visible sections; authenticated previews retain drafts. The renderer removes omitted built-in markup and unavailable section links instead of leaving private text or static fallback copies. Frontend initialization tolerates missing work, skills, contact and hero elements.

Regression evidence:

- PHPUnit checks private built-in/custom text and structured items are absent from public HTML and JSON, while authenticated preview retains them.
- PHPUnit checks all built-ins can be unpublished without fallback hero content or dangling home anchors.
- Real Edge test publishes hidden sections in the QA database, opens the public page, verifies zero sections and no JavaScript errors, then restores original visibility in `finally`.
- The first Edge run exposed a missing-hero animation null reference introduced by the removal; it was fixed and retested, not ignored.

## Controls exercised

| Control | Evidence | Assessment |
|---|---|---|
| Authentication/admin authorization | Full PHPUnit suite; real fixture login; anonymous `/admin` and `/preview` follow to login | Passed locally; non-admin denial is automated PHP evidence, not a separate visible non-admin login |
| Rate limiting | Auth/contact tests; repeated browser-suite login really returned HTTP 429 | Passed locally; tests do not disable/reset rate limits to bypass them |
| CSRF/request forgery | Raw HTTP POST `/contact` and PUT `/admin/site-editor` without cookies, token or origin headers return 419 | Passed locally |
| Same-origin requests | Browser fetch differs from raw forgery probes because modern framework origin/fetch-metadata checks apply; following redirects can end at 200 | Do not mistake final 200 or an incorrect-method 405 for a CSRF result |
| Session protection/logout | Authentication tests and static review cover regeneration, invalidation and auth-session middleware | Automated/source evidence; no production cookie/TLS certification |
| Draft projects | Browser-created draft detail 404; explicit publish gives 200, metadata and sitemap entry; deleting restores 404 | Passed |
| URL/CSS/structured-content validation | Existing PHP tests for unsafe schemes, invalid GitHub origins, CSS and structured HTML injection | Passed; not arbitrary unbounded fuzzing |
| XSS | Static review of DOM/Blade sinks; escaped text regression coverage | No other high-confidence exploitable issue reported in reviewed code; not proof of absence |
| Images/fonts | Real GD upload/re-encode, replacement, stable public URL, font loading; invalid-signature/oversize/reference-protection tests | Passed locally with required extensions |
| Private files | Anonymous `/.env` 404 and `/storage/logs/laravel.log` 403; asset allowlist tests | Passed on this server |
| Updates/trust | Signed manifest, digest, traversal/ZIP/runtime fixtures in PHP tests; real no-release failure in UI | Verification exercised; activation remains disabled |
| SSRF/SEO | Bounded DNS-pinned monitor fixtures/static review; actual unconfigured-origin failure | Public production scan not completed; loopback was not allowlisted to bypass policy |
| GitHub/attribution | Real anonymous public scan, manual review, draft-only creation; preserved manual content tests | Passed for public connection; private auth not implemented |
| Analytics separation | Fresh PHPUnit and Edge regressions for first-party pageviews/admin reporting versus optional Search Console metrics | Passed locally; production traffic history and Search Console OAuth remain unconfigured |
| Dependencies | Composer/npm audits completed with no advisories at test time | Time-specific dependency result, not a security guarantee |

## Data handling

- Fresh browser operations use `hamid_portfolio_final_qa_test` on port 8147 and session-private storage; PHPUnit uses `hamid_portfolio_final_test`. Both are isolated `_test` schemas.
- Fresh PHPUnit run: 136 tests, 798 assertions, 135 passed and one conditional skip for a branch requiring GD to be absent. Fresh real Edge run: six tests passed.
- QA uses its own administrator and session cookie. No fixture password/token is printed in these reports, stored in the repository or captured in screenshots. Trace/video/automatic failure screenshots are disabled.
- Owner server port 8000 was not stopped, reset or used for test mutations. Final read-only inspection found no QA administrator in the owner schema and zero owner revisions/media/fonts; QA had its own administrator, revisions and assets.
- Final owner counts were 1 user, 8 projects, 6 sections, 1 settings row and 0 inbox rows. There is no same-run row-level baseline/hash comparison; this report does **not** claim all owner counts equal an earlier phase's five-project baseline.
- Earlier data-loss incident and its limitations remain documented in [PLATFORM-REPORT.md](../../PLATFORM-REPORT.md); this QA does not recover that earlier data.

## Outstanding deployment gates

Oracle MySQL validation, production HTTPS/cookies/proxy/web-server configuration, least-privilege DB credentials, real shared cache/session concurrency, SMTP delivery, scheduler/worker installation, hosting, trusted release key/assets, verified restore/rollback and Search Console are not certified here. No production update, deployment, backup restore or private GitHub connection was performed.
