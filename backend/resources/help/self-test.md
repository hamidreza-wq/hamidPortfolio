## What it does

The self-test checks that everything still works. It changes nothing on the site: test uploads are checked in memory and never saved, and the database test is undone straight away. It covers:

- **Public site**: the home page, every published project and page, every link and image on the home page, the privacy notice, the health endpoint, and the stylesheet and script files.
- **Links**: broken pages and images, and `#section` links that point to sections that aren't on the page.
- **SEO**: the sitemap and robots.txt, published pages missing from the sitemap, missing titles and descriptions, and images without alt text.
- **Forms**: the contact form has its security tokens, and the testimonial form loads when it's open.
- **Uploads**: images are checked and re-encoded, fonts are checked, and disguised files (for example a PHP file named `.png`) are refused.
- **Dashboard**: search works, every admin page has a role permission, an owner exists, and who has two-factor sign-in on.
- **Email**: whether mail actually goes anywhere.
- **Server**: everything the [Health](/admin/health) page checks.

## Running it

Press **Run the self-test now**. It takes up to a minute. It also runs on its own every Monday morning, once the scheduler is installed. A report stays on the page until the next run.

## Reading the report

The page opens on **Problems only**. Each row says:

- what failed or needs a look
- where (the page address or dashboard page)
- what happened
- what to do about it

Use the filters to see warnings, passes, or everything.

Failures also raise a [notification](/admin/notifications), which clears once a later run passes.

## Good to know

> JavaScript errors and phone layouts need a real browser, so the self-test can't check them. It lists them as **Not tested**. Before a release, run the browser tests (`npm run test:browser` in the `backend` folder) or check the site on a phone.

- The public-site checks only run once `SEO_MONITOR_ORIGIN` is set on the server.
- At most 60 addresses are checked per run.
- Email delivery isn't tested, because that would send real email. Only the mail settings are checked.
