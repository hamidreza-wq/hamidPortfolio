## What it does

Pages are standalone pages of their own, such as "Services", "CV", "Now" or "Speaking", each at its own address: `hamidbayat.co.uk/your-page`. Your home page and project pages are managed elsewhere, in the [live site editor](/admin/site-editor) and [Projects](/admin/projects).

## Writing a page

1. Press **New page** and give it a **title**. The **address** is made from the title, or you can choose your own.
2. Write the **content** in Markdown:
   - `## A heading`
   - `**bold**` and `*italic*`
   - `- a list item`
   - `[link text](https://example.com)`
3. To add an image, copy its URL from the [media library](/admin/media) and write `![what the image shows](that URL)`.
4. Add an optional **summary**. It appears under the title, and in search results.
5. Choose the **status** and press **Create page**.

## Drafts, publishing and scheduling

- **Draft**: only you can see it, using **Preview**.
- **Published**: live straight away.
- **Published with a "publish at" time**: scheduled. It appears on its own at that time (UTC), and the Pages list shows it as *Scheduled*.

**Preview** always works, even for drafts and scheduled pages, and shows a banner saying whether visitors can see the page yet.

## Menus

Tick **Show in the footer menu** or **Show in the header menu** on the page itself, or add it on the [Menus](/admin/menus) page as "One of your pages". The header option appears once the header menu is customised. A menu link only shows while its page is live.

## Undo, duplicate and trash

- Every save keeps the previous version in [Content history](/admin/revisions), where you can restore it.
- **Duplicate** makes a draft copy to start a similar page.
- **Move to trash** takes a page offline straight away. Restore it from the **Trash** tab, or delete it permanently from there.

## Good to know

> HTML, scripts and images from other websites aren't allowed in pages. This keeps the site safe even if someone gets hold of your account.

- Some addresses are reserved because the site already uses them, such as `/admin`, `/projects` and `/privacy`.
- Changing a published page's address breaks links people already have.
- Published pages are added to the sitemap, unless you tick **Ask search engines not to list this page**.
