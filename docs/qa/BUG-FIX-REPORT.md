# Bug fix report

Date: 2026-11-03. Changes remain local and uncommitted.

| ID | Priority | Root cause | Fix | Verification |
|---|---|---|---|---|
| QA-01 | Security: medium | Hidden section content still serialized into anonymous homepage payload; hidden built-in DOM retained private/default copy | Filter public section query; remove omitted built-ins/intro strip; preserve authenticated draft preview; remove/fallback unavailable anchors | New PHP privacy/empty-section assertions; real Edge hide/publish/restore regression |
| QA-02 | Functional: medium | Frontend assumed work/craft/contact containers and hero scene always exist | Guard optional render/event targets and hero-scene pointer styles | Edge all-hidden section regression, including captured JavaScript errors |
| QA-03 | SEO: medium | Existing `public/robots.txt` was served directly by real web servers, shadowing Laravel's dynamic robots route | Remove static file; add assertion it cannot shadow runtime policy | Real HTTP before: bare allow-all; after: admin/preview exclusions+sitemap; maintenance/indexing-off: `Disallow: /` and empty sitemap; PHP indexing test |
| QA-04 | Accessibility: medium | Mobile sidebar moved offscreen with CSS but descendants remained keyboard-focusable | Synchronize `inert`, `aria-hidden`, labels/expanded state; Escape closes/returns focus; desktop resets | Native Edge keyboard test; final 120 admin geometry checks |
| QA-05 | Performance/functionality: low | Decorative canvas kept scheduling frames even when animations or particles disabled | Central scheduling guard and cancel/resume on preference/visibility events; static draw retained when appropriate | Instrumented Edge callback counts stop for both disabled settings |
| QA-06 | Verification: medium | No npm lockfile/dependencies; audit/build could not run | Ordinary dependency restoration succeeded; commit-ready lockfile, required Playwright dependency/test script and ignored runtime outputs added | npm audit zero vulnerabilities; Vite build succeeds; browser regressions executed |

## Important implementation details

- Canonical root frontend and cosmic design remain in place; no disconnected admin template or replacement frontend was created.
- Fully hidden pages remain valid documents with usable main/home fallback links, not JavaScript exceptions.
- Public draft protection is enforced before serialization, not through CSS or client-side hiding alone.
- Existing upload/history reference protections are preserved: a font/image may remain undeletable even after unlinking current settings because a restorable revision still references it. Separate unused fixtures verified activation/deactivation/deletion and image deletion.
- Dynamic robots changes apply only when the server routes requests through this application. Deployments must also avoid CDN/web-server static overrides.
- Test retries did not remove rate limits; browser evidence capture was corrected after the VS Code screenshot adapter reset emulated sizes.

## Unresolved or intentionally unimplemented

- Anonymous GitHub checks returned 404 for `Bible`, `myAccountingHome`, `interpurting-laravel`, `meisaPortfolio`. They may be private, renamed or unavailable. No replacement URL, authorship, features or ownership was fabricated. Owner editorial records were not deleted or rewritten to hide the failure.
- Public SEO scan requires a configured public HTTPS origin. Loopback was not allowed through the SSRF protections to simulate a successful production scan.
- No official signed release assets/key and no hosting/staging/backup-restore/rollback/deployment runner. Activation remains disabled.
- Search Console/private GitHub authentication are not implemented. SMTP delivery and scheduler/queue installation are not verified.
- Local database is MariaDB, not Oracle MySQL.
- Screen-reader/contrast certification, every field combination, large inbox pagination, every revision type in the visible UI and production performance remain incomplete; see the functionality matrix.

No automatic commit, push, deployment, owner password reset, storage symlink replacement or owner-database reset was performed in this QA phase.
