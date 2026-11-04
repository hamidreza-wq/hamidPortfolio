# Link audit

## Current link disposition: 2026-11-04

Branch `copilot/final-security-deployment-hardening`, application source
`95ef441f2f524f5796ce3aabfa873bbf06246385`.
**Fixed and verified:** the owner confirmed Bible, myAccountingHome,
interpurting-laravel and meisaPortfolio are private. Their earlier anonymous
404s are preserved below as historical observations, not current broken public
links. Cards, dialogs and detail pages now say **Private source code** and
omit public repository URLs; no fallback GitHub URL is synthesized.

Office Converters/officeConverters was anonymously verified public.
No live demo was verified, so none was fabricated or exposed.
Explicit public-source and verified-demo editor controls default false.
PHP regression covers all four private projects and demo visibility; the public
browser regression covers private card/dialog/detail behavior in four engines.
All 11 combined browser cases passed locally.

**Known limitation:** private repository authentication is not implemented.
**Owner configuration required:** independently verify any future public demo
before enabling its publication flag. The historical finite crawl below was
not rerun as an unrestricted external crawl. Current release/CI disposition is
in [final release readiness](FINAL-RELEASE-READINESS.md).
The corrected-source MySQL matrix and all 11 CI browser cases passed; production
domain/hosting/link availability still requires staging verification.

## Historical finite crawl (2026-11-03)

Date: 2026-11-03. Isolated QA port 8147. Finite allowlisted pages only; no external forms or crawling. Public HTML is parsed, not executed, for this table. Browser separately exercised dynamically generated cards/dialogs and their link roles. External HEAD falls back once to bounded GET; failures are not proof that a repository is deleted.

Protected `/admin` and `/preview` are expected to redirect guests to login; private-file probes are expected to return 404 (shown as expected denials below). No account/token data is recorded.

| Source | URL | Type | Request | HTTP | Final URL | Result |
|---|---|---|---|---:|---|---|
| / | #main | anchor | - | - | - | Passed: target exists |
| / | #home | anchor | - | - | - | Passed: target exists |
| / | #home | anchor | - | - | - | Passed: target exists |
| / | #about | anchor | - | - | - | Passed: target exists |
| / | #work | anchor | - | - | - | Passed: target exists |
| / | #craft | anchor | - | - | - | Passed: target exists |
| / | #journey | anchor | - | - | - | Passed: target exists |
| / | #contact | anchor | - | - | - | Passed: target exists |
| / | #section-01m6gedb01zkgh7952rk53a4jz | anchor | - | - | - | Passed: target exists |
| / | #work | anchor | - | - | - | Passed: target exists |
| / | #contact | anchor | - | - | - | Passed: target exists |
| / | https://github.com/hamidreza-wq | external | HEAD | 200 | https://github.com/hamidreza-wq | Passed |
| / | #about | anchor | - | - | - | Passed: target exists |
| / | #journey | anchor | - | - | - | Passed: target exists |
| / | http://127.0.0.1:8147/projects/oneprayer | internal | GET | 200 | http://127.0.0.1:8147/projects/oneprayer | Passed |
| / | http://127.0.0.1:8147/projects/office-converters | internal | GET | 200 | http://127.0.0.1:8147/projects/office-converters | Passed |
| / | http://127.0.0.1:8147/projects/home-ledger | internal | GET | 200 | http://127.0.0.1:8147/projects/home-ledger | Passed |
| / | http://127.0.0.1:8147/projects/interpreters-hub | internal | GET | 200 | http://127.0.0.1:8147/projects/interpreters-hub | Passed |
| / | http://127.0.0.1:8147/projects/betweenwords | internal | GET | 200 | http://127.0.0.1:8147/projects/betweenwords | Passed |
| / | https://github.com/hamidreza-wq?tab=repositories | external | HEAD | 200 | https://github.com/hamidreza-wq?tab=repositories | Passed |
| / | #home | anchor | - | - | - | Passed: target exists |
| / | #home | anchor | - | - | - | Passed: target exists |
| /projects/oneprayer | http://127.0.0.1:8147#work | anchor | - | - | - | Passed: target exists |
| /projects/oneprayer | https://github.com/hamidreza-wq/Bible | external | HEAD, then GET | 404 | https://github.com/hamidreza-wq/Bible | Failed / unavailable; do not infer private vs deleted |
| /projects/office-converters | http://127.0.0.1:8147#work | anchor | - | - | - | Passed: target exists |
| /projects/office-converters | https://github.com/hamidreza-wq/officeConverters | external | HEAD | 200 | https://github.com/hamidreza-wq/officeConverters | Passed |
| /projects/home-ledger | http://127.0.0.1:8147#work | anchor | - | - | - | Passed: target exists |
| /projects/home-ledger | https://github.com/hamidreza-wq/myAccountingHome | external | HEAD, then GET | 404 | https://github.com/hamidreza-wq/myAccountingHome | Failed / unavailable; do not infer private vs deleted |
| /projects/interpreters-hub | http://127.0.0.1:8147#work | anchor | - | - | - | Passed: target exists |
| /projects/interpreters-hub | https://github.com/hamidreza-wq/interpurting-laravel | external | HEAD, then GET | 404 | https://github.com/hamidreza-wq/interpurting-laravel | Failed / unavailable; do not infer private vs deleted |
| /projects/betweenwords | http://127.0.0.1:8147#work | anchor | - | - | - | Passed: target exists |
| /projects/betweenwords | https://github.com/hamidreza-wq/meisaPortfolio | external | HEAD, then GET | 404 | https://github.com/hamidreza-wq/meisaPortfolio | Failed / unavailable; do not infer private vs deleted |
| /admin/login | http://127.0.0.1:8147/ | internal | GET | 200 | http://127.0.0.1:8147/ | Passed |
| route/asset probe | http://127.0.0.1:8147/styles.css | internal | GET | 200 | http://127.0.0.1:8147/styles.css | Passed |
| route/asset probe | http://127.0.0.1:8147/app.mjs | internal | GET | 200 | http://127.0.0.1:8147/app.mjs | Passed |
| route/asset probe | http://127.0.0.1:8147/portfolio-data.mjs | internal | GET | 200 | http://127.0.0.1:8147/portfolio-data.mjs | Passed |
| route/asset probe | http://127.0.0.1:8147/favicon.svg | internal | GET | 200 | http://127.0.0.1:8147/favicon.svg | Passed |
| route/asset probe | http://127.0.0.1:8147/sitemap.xml | internal | GET | 200 | http://127.0.0.1:8147/sitemap.xml | Passed |
| route/asset probe | http://127.0.0.1:8147/robots.txt | internal | GET | 200 | http://127.0.0.1:8147/robots.txt | Passed |
| route/asset probe | http://127.0.0.1:8147/media/1 | internal | GET | 200 | http://127.0.0.1:8147/media/1 | Passed |
| route/asset probe | http://127.0.0.1:8147/fonts/1 | internal | GET | 200 | http://127.0.0.1:8147/fonts/1 | Passed |
| route/asset probe | http://127.0.0.1:8147/preview | internal | GET | 200 | http://127.0.0.1:8147/admin/login | Passed |
| route/asset probe | http://127.0.0.1:8147/admin | internal | GET | 200 | http://127.0.0.1:8147/admin/login | Passed |
| route/asset probe | http://127.0.0.1:8147/.env | internal | GET | 404 | http://127.0.0.1:8147/.env | Passed: private file denied |
| route/asset probe | http://127.0.0.1:8147/storage/logs/laravel.log | internal | GET | 403 | http://127.0.0.1:8147/storage/logs/laravel.log | Passed: private file denied |

## Coverage limits

Authenticated dashboard route rendering and navigation links were visited separately in the visible browser. Mutation form actions are inventoried in FUNCTIONALITY-AUDIT.md and tested through forms/PHPUnit, not GET-probed. README/license source and the actual GitHub API were checked read-only; remote README product claims were not independently certified. No uptime guarantee is implied by a single response.
