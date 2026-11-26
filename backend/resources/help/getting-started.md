## What the dashboard is for

This dashboard runs your public portfolio. Everything you change here (text, projects, images, menus, colours) is stored in the site's database and shown to visitors, so you never need to edit code for day-to-day changes.

The sidebar is grouped the same way as this help:

- **Your site**: the live editor, projects, sections, menus, themes and content history.
- **Library**: images and fonts.
- **People**: the contact inbox, testimonials and social links.
- **Insight**: analytics and SEO monitoring.
- **Running the site**: health, backups, software updates, GitHub discovery and notifications.
- **Protection**: security and privacy.

## First things to set up

1. **Turn on two-factor sign-in** on the [Security](/admin/security) page. Until you do, your password is the only thing protecting the dashboard.
2. **Check the [Health](/admin/health) page.** If the *Scheduler* line is red, ask whoever manages the server to add a cron job that runs `php artisan schedule:run` every minute. Automatic backups, update checks and the SEO scan all depend on it.
3. **Make a backup** on the [Backups](/admin/backups) page, then download it and keep it somewhere other than the server.
4. **Choose retention periods** on the [Privacy](/admin/privacy) page, so old contact messages don't pile up forever.

## Finding your way

- Press **Ctrl K** (**⌘ K** on a Mac) on any page to search the whole dashboard.
- The **Help** button in the top bar opens the article for the page you're on.
- **View live site** opens the public portfolio in a new tab.

## How changes reach visitors

Most pages save straight to the live site: projects, sections, menus, social links, testimonials, themes and media changes all show the next time a visitor loads the page. The **live site editor** is different: it works on a draft with a preview, and nothing changes until you press **Publish changes**.

> Almost every change can be undone. Edits to site content and projects are kept in [Content history](/admin/revisions), and applying a theme saves the previous look there too.

## The overview page

The overview shows counts (projects, published sections, unread messages, storage used), your most recent changes, quick links, and a short search-visibility check. **Full activity log** opens the complete record on the Security page.
