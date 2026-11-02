# Hamid Reza Bayat — portfolio

A lightweight, responsive portfolio built as a static site. The visual system uses a layered CSS planet/orbit scene and a small canvas particle field; it does not depend on a WebGL runtime or third-party animation package.

## Run locally

From this directory, start any static file server. For example:

```powershell
python -m http.server 4173
```

Open `http://localhost:4173`. Python's static server is for local preview only; deploy the files to a static hosting provider for production.

## Update content

Edit [`portfolio-data.mjs`](./portfolio-data.mjs) to update the profile, project details, technologies, or optional social links. Project repository links are built from each project's `repo` value and the GitHub profile in the same file.

- Set `profile.email` to an address to show an `mailto:` contact action.
- Set `profile.linkedin` to a verified LinkedIn URL to show that link.
- Add a project only after verifying its description, technologies, and features in its repository.
- Project artwork is an abstract visual treatment, not a screenshot of the software.

The project descriptions here are based on the public READMEs for `Bible`, `officeConverters`, `myAccountingHome`, `interpurting-laravel`, and `meisaPortfolio`. OnePrayer is explicitly labeled as an early build; its repository notes that native device testing and mobile product screens are incomplete. Live demo URLs and screenshots were not added because none were verified for these entries.

## Motion and accessibility

The site includes a keyboard skip link, visible focus states, semantic sections, a keyboard-operable project dialog, reduced-motion support, and a mobile navigation toggle. The canvas is decorative and does not contain content needed to use the page. It uses a capped particle count, pauses while the tab is hidden, and has no external image or model assets.

## Before deployment

Choose the final hostname and update the page's social metadata, canonical URL, `robots.txt`, and sitemap to match it. No canonical domain or sitemap URL is included yet because the deployment hostname has not been supplied.
