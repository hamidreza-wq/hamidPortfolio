## What it does

The Health page shows whether everything the site depends on is working. Each check is green (healthy), amber (warning) or red (critical), and the overall status is the worst of them.

## Server checks (every time you open the page)

- **Database**: connected, how fast it answers, and whether any database updates are still waiting to be applied.
- **PHP**: the version, and the extensions the site needs.
- **Server errors**: errors logged in the last 24 hours, with the latest one.
- **File storage, disk space and cache**: whether files can be saved, and how much space is left.
- **Scheduler**: whether the server's scheduled tasks are running. If this is red, see below.
- **Queue**: background jobs waiting or failed.
- **Backups**: how old the newest verified backup is.

## Public-site checks (hourly, or when you press the button)

The site's own public pages are loaded over HTTPS to check:

- whether they answer, and how fast
- the HTTPS certificate, and how many days until it expires
- the security headers
- that visitors get the same version as this installation

## When something is red

- **Scheduler**: the server needs a cron job running `php artisan schedule:run` every minute. Without it, there are no automatic backups, update checks or SEO scans.
- **Database migrations**: a release added database changes that haven't been applied. Take a backup, then run `php artisan migrate --force` on the server.
- **HTTPS certificate**: renew it in the hosting panel. It's usually automatic.

Red results also raise a [notification](/admin/notifications), which clears itself when the check passes again.
