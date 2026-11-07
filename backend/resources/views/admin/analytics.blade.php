@extends('admin.layout')
@section('title', 'Analytics')
@section('breadcrumb', 'Analytics')
@section('content')
<div class="page-head">
  <div><p class="eyebrow">YOUR DIGITAL UNIVERSE / ANALYTICS</p><h1>Traffic, without guesswork.</h1><p>First-party pageviews remain separate from optional Google Analytics 4 and Search Console data.</p></div>
</div>
<section class="panel" style="margin-bottom:14px">
  <div class="panel-head"><h2>Google Analytics 4</h2>
    @if($googleAnalyticsConfigured)<span class="status-pill">Configured</span>@else<span class="status-pill off">Not configured</span>@endif
  </div>
  @if($googleAnalyticsConfigured)
    <p class="card-help">Measurement ID: <code>{{ $googleAnalyticsMeasurementId }}</code>. Tracking runs only after a visitor opts in; detailed reports are viewed in Google Analytics.</p>
    <a class="button button-quiet" href="https://analytics.google.com/analytics/web/" target="_blank" rel="noopener noreferrer">Open Google Analytics ↗</a>
  @else
    <p class="card-help">Google Analytics tracking is available on production after a valid GA4 Measurement ID is configured. Visitors must opt in before the tag loads.</p>
  @endif
</section>
<div class="grid stats">
  <article class="stat-card"><div class="stat-top"><span>Pageviews / 30 days</span><span class="stat-mark">⌁</span></div><div class="stat-number">{{ number_format($pageViews) }}</div><div class="stat-caption">Public home and published project pages</div></article>
  <article class="stat-card"><div class="stat-top"><span>Pageviews / 7 days</span><span class="stat-mark">◷</span></div><div class="stat-number">{{ number_format($recentViews) }}</div><div class="stat-caption">Public page requests only</div></article>
</div>
<section class="panel" style="margin-bottom:14px">
  <div class="panel-head"><h2>First-party pageviews · last 30 days</h2><span class="status-pill">Recording</span></div>
  <p class="card-help">Counts successful public HTML page loads, not unique visitors. These first-party records do not store cookies, IP addresses, full referrer URLs, or user-agent strings. Google Analytics is separate and consent-based.</p>
  <div role="img" aria-label="Daily first-party pageviews during the last 30 days" style="display:grid;grid-template-columns:repeat(30,minmax(0,1fr));align-items:end;gap:4px;min-height:145px;padding:12px 0 0">
    @foreach($dailyViews as $index => $day)
      <div title="{{ $day['label'] }}: {{ $day['views'] }} pageviews" style="display:flex;height:125px;flex-direction:column;justify-content:flex-end;align-items:center;gap:6px">
        <span style="color:#858294;font-size:8px">{{ $day['views'] ?: '' }}</span>
        <span style="width:100%;height:{{ max(4, (int) round(100 * $day['views'] / $chartMaximum)) }}%;border-radius:3px 3px 0 0;background:linear-gradient(180deg,#b69bff,#6e5d9c);opacity:{{ $day['views'] ? '1' : '.22' }}"></span>
        @if($index % 5 === 0 || $index === 29)<span style="color:#777487;font-size:7px;white-space:nowrap">{{ $day['label'] }}</span>@endif
      </div>
    @endforeach
  </div>
</section>
<div class="grid dashboard-columns">
  <section class="panel">
    <div class="panel-head"><h2>Most viewed pages</h2><span style="color:#858294;font-size:9px">30 days</span></div>
    @forelse($topPages as $page)
      <div class="activity-row"><div class="activity-copy"><strong>{{ $page->path === '/' ? 'Home' : $page->path }}</strong></div><span class="activity-time">{{ number_format($page->views) }}</span></div>
    @empty
      <p class="card-help">No public pageviews have been recorded yet.</p>
    @endforelse
  </section>
  <section class="panel">
    <div class="panel-head"><h2>External referrer hosts</h2><span style="color:#858294;font-size:9px">30 days</span></div>
    @forelse($referrers as $referrer)
      <div class="activity-row"><div class="activity-copy"><strong>{{ $referrer->referrer_host }}</strong></div><span class="activity-time">{{ number_format($referrer->views) }}</span></div>
    @empty
      <p class="card-help">No external referrers recorded. Direct visits are not listed as a referrer.</p>
    @endforelse
  </section>
</div>
<section class="panel" style="margin-top:14px">
  <div class="panel-head"><h2>Google Search Console</h2>
    @if($connection)<span class="status-pill">Connected</span>@else<span class="status-pill off">Optional · disconnected</span>@endif
  </div>
  <p class="card-help">Google search clicks, impressions, click-through rate and average position come directly from Search Console. These are not combined with first-party pageviews.</p>
  @if($searchError)<div class="error-box" role="alert">{{ $searchError }}</div>@endif
  @if($connection)
    <p style="color:#c3bfce;font-size:10px">Property: <strong>{{ $connection->site_url }}</strong> · Data range: {{ $searchDateStart }} to {{ $searchDateEnd }}</p>
    @if($searchReport)
      @php($ctr = $searchReport['impressions'] ? 100 * $searchReport['clicks'] / $searchReport['impressions'] : 0)
      <div class="grid stats" style="margin:15px 0">
        <article class="stat-card"><div class="stat-top"><span>Search clicks</span></div><div class="stat-number">{{ number_format($searchReport['clicks']) }}</div></article>
        <article class="stat-card"><div class="stat-top"><span>Impressions</span></div><div class="stat-number">{{ number_format($searchReport['impressions']) }}</div></article>
        <article class="stat-card"><div class="stat-top"><span>Click-through rate</span></div><div class="stat-number">{{ number_format($ctr, 1) }}<span style="color:#858294;font-size:13px;font-weight:400">%</span></div></article>
        <article class="stat-card"><div class="stat-top"><span>Average position</span></div><div class="stat-number">{{ number_format($searchReport['average_position'], 1) }}</div></article>
      </div>
    @endif
    <form method="post" action="{{ route('admin.analytics.google.disconnect') }}" onsubmit="return confirm('Disconnect Google Search Console?')">@csrf @method('DELETE')<button class="button button-danger" type="submit">Disconnect Search Console</button></form>
  @elseif($searchConsoleConfigured)
    <form method="post" action="{{ route('admin.analytics.google.connect') }}" class="field-grid" style="max-width:620px">@csrf
      <div class="field full"><label for="site_url">Verified Search Console property</label><input id="site_url" name="site_url" type="text" required maxlength="255" placeholder="https://example.com/ or sc-domain:example.com" value="{{ old('site_url') }}"><span class="field-hint">The Google account must have access to this exact URL-prefix or domain property. Only read-only Search Console access is requested.</span></div>
      <div><button class="button button-primary" type="submit">Connect Google Search Console</button></div>
    </form>
  @else
    <p style="color:#c3bfce;font-size:10px">To enable this separate source, configure <code>GOOGLE_SEARCH_CONSOLE_CLIENT_ID</code>, <code>GOOGLE_SEARCH_CONSOLE_CLIENT_SECRET</code> and the OAuth callback URI <code>/admin/analytics/google/callback</code>. Google authorization is not configured, so no search performance data is shown.</p>
  @endif
</section>
@endsection
