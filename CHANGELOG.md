# Changelog

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
