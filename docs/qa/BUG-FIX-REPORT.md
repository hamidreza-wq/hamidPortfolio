# Bug fix report

## Final fixes: 2026-11-04

Branch `copilot/final-security-deployment-hardening`; application source
`95ef441f2f524f5796ce3aabfa873bbf06246385`.
These changes are committed and pushed on that branch, not merged or deployed.
See [final readiness](FINAL-RELEASE-READINESS.md) for actual CI run links,
evidence provenance and classified remaining owner gates.

| ID | Root cause | Fix | Fresh evidence |
|---|---|---|---|
| QA-07 | Known private sources presented as unavailable public links | Explicit source/demo flags, private label, withheld anonymous URLs, no synthesized fallback | Four-private-project PHP checks and public card/dialog/detail browser checks |
| QA-08 | Test isolation depended on caller setup without a reusable CLI proof | Shared effective MySQL/testing/suffix/no-override verifier and guard tests | Full isolated PHP suite; verifier prints selected database/version |
| QA-09 | Fresh tests did not prove existing content survives upgrades | Two historical-schema baselines with synthetic data equality/count checks and visibility-migration replay | Database suite passes locally; actual Oracle MySQL matrix tracked separately |
| QA-10 | Visible accessible labels differed from overriding names | Natural brand/project names and correct scroll label | Axe serious/critical checks pass in exercised states |
| QA-11 | Body minimum width overflowed in Firefox under CSS scaling | Remove unnecessary body minimum width | All four public engines pass 200% scaling/control bounds |
| QA-12 | Worker timeout 150s exceeded queue retry default 90s | Retry defaults 180s; documented overrides and regression | Queue timing test passes |
| QA-13 | Lockfile resolved on PHP 8.5 installed incompatible packages on 8.3 | Resolve with explicit Composer platform 8.3.0; compatible dependencies | Real first CI failure preserved; subsequent jobs install successfully |
| QA-14 | MySQL normalizes JSON object order; strict array comparison treated identical metadata as changed | Recursive key ordering before strict value comparison | Real second CI failure preserved; reordered/repeated scan regression and full PHP suite pass locally |
| QA-15 | WebKit's default link Tab traversal differs between Windows and Linux | Accept only the known skip-link or first-button targets; retain keyboard activation checks | Real third CI failure preserved; public four-engine rerun passes locally |

Current local result: **150 tests/934 assertions**, one GD-absence skip;
**11 browser cases passed**; audits/build/formatting pass.
SQL/assets restore is verified on a separate schema/directory, not the owner DB.
Live SMTP/GSC/SEO-origin checks, intended hosting, protection rules and
production recovery remain classified owner/external gates in the final report.
Corrected-source GitHub CI actually passed both PHP/MySQL jobs and all 11
browser cases. **READY FOR STAGING**, not production approval.

## Historical QA phase (superseded)

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
- At this original audit stage Search Console was not yet implemented; it is now implemented and covered as described above. Private GitHub authentication remains unsupported. Live SMTP delivery and host scheduler/queue installation remain unverified.
- Local database is MariaDB, not Oracle MySQL.
- Screen-reader/contrast certification, every field combination, large inbox pagination, every revision type in the visible UI and production performance remain incomplete; see the functionality matrix.

No automatic commit, push, deployment, owner password reset, storage symlink replacement or owner-database reset was performed in this QA phase.
