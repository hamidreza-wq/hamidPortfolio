## What it does

The Security page shows what protects your dashboard, who has signed in, and a record of every admin action.

## Turning on two-factor sign-in

Two-factor sign-in asks for a 6-digit code from an app on your phone after your password, so a stolen password alone can't open the dashboard.

1. Install an authenticator app, such as Google Authenticator, Microsoft Authenticator, 1Password or Bitwarden.
2. On this page, enter your password and press **Set up two-factor sign-in**.
3. Scan the QR code with the app, or type in the key shown.
4. Enter the 6-digit code the app shows, and press **Turn on**.
5. **Save the 8 recovery codes** in a password manager, or print them. They're shown only once.

If you lose your phone, sign in with a recovery code instead of the app code. Each code works once. Generate new ones when you're running low.

> Lost both your phone and your recovery codes? Someone with access to the server can switch two-factor off with `php artisan portfolio:two-factor:reset your@email`.

## Sessions

**Your signed-in sessions** lists the browsers signed in to your account. If you see one you don't recognise, enter your password and press **Sign out other sessions**, then change your password from the server's command line.

## Protection checks

Each check shows green or amber, with what to do if something is off: debug mode, HTTPS, cookie settings, session encryption, two-factor and more.

## Activity log

Every sign-in, failed sign-in and admin action, with the time, the person and the IP address. Filter it to sign-ins, failed sign-ins or security actions. IP addresses can be cleared after a period you choose on the [Privacy](/admin/privacy) page.
