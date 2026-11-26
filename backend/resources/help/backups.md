## What it does

A backup is a single file holding all your content (projects, sections, settings, messages, testimonials, accounts and history) and every uploaded image and font. Each backup is checked by reading it back after it's written.

## Making and checking backups

- Backups run **automatically every day** once the scheduler is installed. The newest 14 are kept.
- Press **Back up now** before a big change.
- **Verify again** re-checks a backup at any time.

## Downloading

Open **Download or delete** on a backup and enter your password. Keep a recent download somewhere other than the server, so losing the hosting account doesn't lose your backups too.

> A backup file contains password hashes and private messages. Store it somewhere private and, ideally, encrypted.

## Restoring

Restoring replaces everything on the live site, so it's done from the server's command line, not a button:

```
php artisan portfolio:backup:restore backup-YYYYMMDD-HHMMSS-xxxxxx.zip
```

The command first saves a **safety backup** of the current site, switches on maintenance mode, restores the database in one step that is undone completely if anything fails, then restores the files. The Backups page shows the exact command for the newest backup.

## Good to know

- A backup can only be restored into the same database version it was made from. A backup from an older release needs that release deployed first.
- The server's `.env` settings file isn't included. Keep its `APP_KEY` safe: two-factor sign-in and the Google connection depend on it.
- The newest verified backup can't be deleted.
