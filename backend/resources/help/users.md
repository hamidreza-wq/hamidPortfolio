## What it does

The Users page decides who can sign in to the dashboard, and what each person can do there. Only **owners** can see it.

## Roles

| Role | Can do |
| --- | --- |
| **Owner** | Everything, including managing users. |
| **Administrator** | Everything except managing users. |
| **Editor** | Site text, projects, sections, pages, menus, social links, themes, fonts and media. |
| **SEO manager** | Site text and SEO fields, plus analytics and SEO monitoring. |
| **Support** | The contact inbox and testimonials. |

Everyone can use the overview, search, help and notifications, and set up their own two-factor sign-in and sessions on the [Security](/admin/security) page. The sidebar and search only show what each person's role allows.

## Adding someone

1. Under **Add someone**, enter their name and email, choose a role, and confirm with your password.
2. Copy the **set-password link** that appears and send it to them privately, for example in a direct message.
3. They open it, choose their own password, and sign in.

The link works once and expires after 48 hours. You never see or send their password. If it expires, use **New set-password link** under **Manage**.

## Changing or removing access

Under **Manage** next to a person:

- **Change role**: takes effect on their next page.
- **New set-password link**: also works when someone forgets their password.
- **Remove access**: signs them out everywhere and stops them signing in. Their name stays in the activity log.

Each action asks for your password.

## Good to know

> There must always be at least one owner, and nobody can change or remove their own access. To change your own role, ask another owner.

- Ask everyone to turn on two-factor sign-in. The Users page shows who has it on.
- If you're the only owner and lose access, the server's command line can reset the owner account (`php artisan portfolio:admin:create`).
