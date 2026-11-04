# Responsive and accessibility audit

Original audit: 2026-11-03. A fresh six-test Edge run on 2026-11-04 passed, including public viewport checks and exact-size screenshot assertions. The earlier full 120-admin-page geometry sweep was not repeated.

## Viewport matrix

| Viewport | Public document/controls | Public dialog horizontal overflow | 12 authenticated admin pages |
|---|---|---|---|
| 320 x 700 | Passed | None | Passed |
| 375 x 812 | Passed | None | Passed |
| 390 x 844 | Passed | None | Passed |
| 430 x 932 | Passed | None | Passed |
| 768 x 1024 | Passed | None | Passed |
| 1024 x 768 | Passed | None | Passed |
| 1280 x 800 | Passed | None | Passed |
| 1366 x 768 | Passed | None | Passed |
| 1440 x 900 | Passed | None | Passed |
| 1920 x 1080 | Passed | None | Passed |

Checks measured document scroll width against viewport width and actionable form-control bounds, excluding deliberately closed/inert navigation. Native scrollbar gutter can make document width 15px smaller than `innerWidth`; that is not overflow. Dialogs remained inside viewport dimensions and used vertical scrolling when necessary (320px viewport: approximately 267 x 668px dialog).

Admin sweep: overview, editor, projects, sections, revisions, media, fonts, inbox empty-search state, updates, GitHub, SEO and notifications: 120 page/viewport combinations, all HTTP 200. This is geometry/layout evidence, not exhaustive visual inspection of every scrolled state at every size. Inbox long-content protection has PHP/source evidence; large populated inbox pagination was not recreated in the visible browser.

An initial scanner flagged the intentionally translated-offscreen sidebar. Investigation identified a real keyboard issue: its links remained focusable while closed. Closed mobile navigation now uses `inert`/`aria-hidden`; opening reenables it, Escape closes and returns focus, desktop resizing reenables navigation. Real Edge regression covers this.

## Accessibility

| Check | Result |
|---|---|
| Public skip link keyboard focus | Passed in real Edge |
| Project dialog open/close, Escape, backdrop and focus return | Passed; native dialog handles modal behavior |
| Mobile public menu expansion and anchor close | Passed in visible browser |
| Closed mobile admin navigation excluded from focus | Fixed and passed in real Edge |
| Form labels, native email/required validation | Observed on public/admin forms; invalid public email rejected |
| Reduced-motion preference | Applied and verified; deterministic evidence captures use reduced motion |
| Decorative animation work | Regression verifies frame callbacks stop when animation/particles disabled |
| Screen reader announcement quality | Not tested with a screen reader |
| Full contrast/WCAG certification, zoom/forced colors, touch-device lab | Not tested; no compliance claim |
| Firefox/Safari/device-specific rendering | Not tested; Edge/Chromium only |

## Screenshot evidence

See [screenshots](screenshots/):

- [Desktop](screenshots/public-desktop.png), [mobile](screenshots/public-mobile.png), [tablet](screenshots/public-tablet.png); corresponding `-full` files show the entire page.
- [Projects](screenshots/public-projects.png), [dialog](screenshots/project-dialog.png), [project detail](screenshots/public-project-detail.png), [contact](screenshots/public-contact.png).
- [Login](screenshots/admin-login.png), [dashboard](screenshots/admin-dashboard.png), [editor](screenshots/admin-editor-preview.png), [projects](screenshots/admin-projects.png), [sections](screenshots/admin-sections.png), [revisions](screenshots/admin-revisions.png), [media](screenshots/admin-media.png), [fonts](screenshots/admin-fonts.png), [inbox empty search](screenshots/admin-inbox-empty-search.png).
- [Updates](screenshots/admin-updates.png), [GitHub](screenshots/admin-github.png), [SEO](screenshots/admin-seo.png), [notifications](screenshots/admin-notifications.png), [mobile editor](screenshots/admin-editor-mobile.png), [mobile navigation](screenshots/admin-navigation-mobile.png).

The VS Code screenshot adapter was observed to reset the emulated viewport to its panel size. Those preliminary size-labelled captures were not accepted as exact-size evidence. Native full-page capture also included the cosmic artwork's intentionally clipped decorative bounds beyond the layout viewport. The final capture test explicitly clips long captures to the viewport width, asserts PNG header dimensions, and asserts viewport size remains unchanged. It does not remove the intentional cosmic artwork. Inbox screenshots deliberately show no message contents; login screenshots are taken before credentials are filled.
