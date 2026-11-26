## What it does

The Software updates page checks the official, signed releases of this website and helps you install them safely. It shows the installed version, PHP and Laravel versions, and each release's notes and status.

## How an update works

1. **Check official releases**: the page looks for new signed releases. It also checks every six hours once the scheduler is installed.
2. **Verify**: the release's signature and checksums are checked before anything else happens.
3. **Update**: after you confirm with your password, the deployment workflow on GitHub needs separate approval. It verifies the release again, backs up the current site, switches to the new version and checks the site's health. If the health check fails, it switches back automatically.

## Good to know

> One-click updates are only for releases that don't change the database. A release with database changes says so, and has to be deployed by hand: back up, deploy, then run `php artisan migrate --force`.

- Release notes are shown as plain text.
- A request being approved isn't proof it succeeded. The page shows the workflow's real result.
- Server software (PHP and the database) is upgraded separately, by whoever manages the hosting.
