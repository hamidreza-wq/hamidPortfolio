@extends('admin.layout')
@section('title', 'Health')
@section('breadcrumb', 'Health')
@section('content')
@php($pill = ['ok' => ['', 'Healthy'], 'warning' => ['warn', 'Warning'], 'critical' => ['off', 'Critical'], 'unknown' => ['warn', 'Unknown']])
@php($all = collect($groups)->flatten(1))
@php($checkedAt = $site['checked_at'] ? \Illuminate\Support\Carbon::parse($site['checked_at']) : null)
<div class="page-head">
  <div><p class="eyebrow">STUDIO / STATUS</p><h1>Health</h1><p>Whether the site, server and background jobs are working. Server checks run each time this page opens. Public-site checks load the live site over HTTPS every hour, or when you press the button.</p></div>
  <form method="post" action="{{ route('admin.health.check') }}">@csrf<button class="button button-primary" type="submit">Check the public site now</button></form>
</div>

<div class="grid stats">
  <article class="stat-card"><div class="stat-top"><span>Overall</span><span class="stat-mark">◉</span></div><div class="stat-number" style="font-size:20px"><span class="status-pill {{ $pill[$overall][0] }}" style="font-size:11px">{{ $pill[$overall][1] }}</span></div><div class="stat-caption">The worst result below</div></article>
  <article class="stat-card"><div class="stat-top"><span>Healthy</span><span class="stat-mark">✓</span></div><div class="stat-number">{{ $all->where('state', 'ok')->count() }}<span style="color:#858294;font-size:13px;font-weight:400"> / {{ $all->count() }}</span></div><div class="stat-caption">Checks passing</div></article>
  <article class="stat-card"><div class="stat-top"><span>Needs attention</span><span class="stat-mark">!</span></div><div class="stat-number">{{ $all->whereIn('state', ['warning', 'critical', 'unknown'])->count() }}</div><div class="stat-caption">{{ $all->where('state', 'critical')->count() }} critical</div></article>
  <article class="stat-card"><div class="stat-top"><span>Public site checked</span><span class="stat-mark">◷</span></div><div class="stat-number" style="font-size:20px">{{ $checkedAt ? $checkedAt->diffForHumans() : 'Not yet' }}</div><div class="stat-caption">{{ $checkedAt ? $checkedAt->format('j M Y, H:i').' UTC' : 'Press the button to run it' }}</div></article>
</div>

@if($site['error'])
  <div class="error-box" role="alert">The last public-site check could not finish: {{ $site['error'] }}</div>
@endif

<div class="grid" style="grid-template-columns:repeat(auto-fit,minmax(min(100%,420px),1fr));gap:14px">
  @foreach($groups as $group => $checks)
    <section class="panel" aria-labelledby="health-{{ \Illuminate\Support\Str::slug($group) }}">
      <div class="panel-head"><h2 id="health-{{ \Illuminate\Support\Str::slug($group) }}">{{ $group }}</h2></div>
      <div class="activity-list">
        @foreach($checks as $check)
          <div class="activity-row"><div class="activity-copy"><strong>{{ $check['label'] }}</strong><p>{{ $check['detail'] }}</p></div><span class="status-pill {{ $pill[$check['state']][0] }}">{{ $pill[$check['state']][1] }}</span></div>
        @endforeach
      </div>
    </section>
  @endforeach
</div>
<p class="card-help" style="margin-top:14px">Critical results also raise a notification, which clears when the check passes again. Sign-in and configuration protections are on the <a href="{{ route('admin.security') }}" style="color:#b69bff">Security</a> page; search problems are on <a href="{{ route('admin.platform.seo') }}" style="color:#b69bff">SEO monitoring</a>.</p>
@endsection
