<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="index,follow">
  <title>Privacy notice · Hamid Reza Bayat</title>
  <link rel="icon" href="/favicon.svg" type="image/svg+xml">
  <link rel="stylesheet" href="/styles.css">
</head>
<body>
  <main class="privacy-page">
    <a class="button button-quiet" href="/">← Back to portfolio</a>
    <p class="eyebrow">PRIVACY / ANALYTICS</p>
    <h1>Privacy notice</h1>
    <p>This notice explains the website analytics used on hamidbayat.co.uk.</p>

    <h2>Google Analytics</h2>
    <p>Google Analytics is optional and is not loaded until you choose “Allow analytics”. If allowed, it measures page visits and standard interaction signals using Google technologies, which may store cookies and process information such as your device, browser, approximate location and pages visited. Advertising and personalisation signals are disabled.</p>
    <p>The analytics choice is saved for up to six months so the site can remember it. Choose “Privacy settings” on any page to change or withdraw your choice. When you withdraw consent, Google Analytics cookies are removed where possible and tracking stops after the page reloads.</p>
    <p>The contact form's typed fields are not sent to Google Analytics. Do not include personal information in page addresses or other public content.</p>
    <p>Google's processing is described in the <a href="https://policies.google.com/privacy" target="_blank" rel="noopener noreferrer">Google Privacy Policy</a>.</p>

    <h2>First-party pageview counts</h2>
    <p>The site separately counts successful public page loads to operate its own analytics dashboard. These counts include the page path and, when available, the external referrer hostname. Raw visitor IP addresses, user-agent strings and full referrer URLs are not stored in these pageview records.</p>

    <h2>Contact messages</h2>
    <p>If you submit the contact form, the information you provide is stored in the site's private inbox and emailed to the site owner through the site's email provider to respond to your message. It is not sent to Google Analytics.</p>
  </main>
  @include('portfolio.analytics-consent', ['measurementId' => config('services.google_analytics.measurement_id')])
</body>
</html>
