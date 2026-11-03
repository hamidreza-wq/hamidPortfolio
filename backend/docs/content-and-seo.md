# Content, revisions and SEO

Changes in the site editor remain browser-only drafts until **Publish changes**.
Theme preset selection also changes only the unsaved draft. Project and additional
section forms have explicit publication/visibility checkboxes; new records start
unpublished. Preview is administrator-only, private/no-store and noindex/nofollow.
Scheduling and persistent saved drafts are not implemented.

## Sections

The six existing sections preserve the cosmic frontend. Their titles, copy,
navigation labels, visibility, order, images and background settings can be edited.
They can be hidden, but not deleted or converted to another type.

Additional sections support experience, education, services, testimonials and
custom plain-text blocks. Items are a JSON array with only `title`, `subtitle`,
and `body`. At most 20 items and 30 additional sections are allowed. Markup is
escaped, never executed. Enter only verified information; no personal history or
testimonials are generated. Navigation follows visible sections and their order.
Section top/bottom spacing scales with the global spacing control (140 is the
neutral global setting). A home-section image can be used for a profile
illustration; there is no separate profile-avatar field.

## Projects and media

Published projects have server-rendered `/projects/{slug}` pages as well as the
existing cards/modal. Title changes preserve the slug. Explicit slug changes
create 301 redirects to the current slug; redirects become unavailable when the
project is unpublished or deleted. Draft project content is excluded from public
HTML, structured data, sitemap and detail routes. Project images, gallery images,
featured state, status and live links are rendered.

Choose images from the managed media library; alt text is edited there. Media
deletion guards include revision references, and restoration validates both
database references and file availability. Removing a project never deletes
its images. Replacing an image updates the shared stable media URL, including
historical revisions: revisions preserve references, not historical image bytes.

## Revisions

Site/section edits record the previous site settings, sections and skills.
Project creation, updates and deletion record full project snapshots. **Restore**
requires explicit confirmation and publishes the snapshot in a database
transaction. Existing replaced content is preserved as another revision.
Missing images, missing/inactive/unlicensed selected fonts and conflicting
project slugs cause restoration to fail without partial changes.

History is retained indefinitely and can contain unpublished copy. It is
administrator-only. Include `content_revisions` and managed media/font storage
in coordinated backups. Deleted projects may be restored with their original
ID; redirects removed by deletion are not restored.

## SEO

Configure the canonical base URL to the public HTTPS origin (or deployment base
path), without query strings/fragments, and set `APP_URL` consistently. Homepage
and project pages emit title, description, canonical, Open Graph, Twitter card,
language, robots directives and JSON-LD. Project SEO falls back to its name and
description; its featured image falls back to the global sharing image.

`/sitemap.xml` lists only published project canonical URLs plus the homepage.
Disabling indexing or enabling maintenance makes the sitemap empty and
`robots.txt` disallow crawling. Public maintenance pages return 503; missing or
unpublished projects return 404. Preview always sends noindex/nofollow.
Robots directives are crawler guidance, not access control or a ranking promise.
SEO health warnings are available in the site editor, project editor and
dashboard. Uploaded font rendering requires valid licensed files; see
[managed-assets.md](managed-assets.md) for runtime requirements.
