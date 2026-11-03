@extends('admin.layout')
@section('title', 'SEO monitoring')
@section('breadcrumb', 'SEO monitoring')
@section('content')
<div class="page-head"><div><p class="eyebrow">SEARCH / REAL CONTENT</p><h1>SEO monitoring</h1><p>Actionable technical checks on the actual public website, not ranking promises.</p></div></div>
<section class="panel platform-card">
  @include('admin.platform.check', ['kind' => 'seo', 'label' => 'Scan public website'])
  <p class="card-help">Origin: {{ config('platform.seo_origin') ?: 'Not configured: set SEO_MONITOR_ORIGIN on the server' }}. Schedule: {{ config('platform.schedule.seo') }} (UTC). Only the home page, up to 50 published project pages, robots and sitemap are requested. External links, redirects, admin and arbitrary URLs are never crawled.</p>
  <a class="button" href="{{ route('admin.site-editor.edit') }}">Global metadata / indexing / sharing</a>
  <a class="button" href="{{ route('admin.projects.index') }}">Project content and SEO</a>
  <h2>Search Console: disconnected</h2>
  <p class="card-help">No Google credentials are configured and no impressions, clicks, rankings or indexing results are being reported. Technical scan success is not proof of Google indexing. Search Console OAuth/statistics are not implemented in this phase.</p>
</section>
@forelse($findings as $finding)
<section class="panel platform-card">
  <div class="panel-head"><h2>{{ $finding->check }} / {{ $finding->priority }}</h2><span class="status-pill {{ !$finding->resolved_at ? 'off' : '' }}">{{ $finding->resolved_at ? 'resolved' : 'open' }}</span></div>
  <p><code>{{ $finding->path }}</code></p><p>{{ $finding->message }}</p>
  <p class="card-help">Last detected {{ $finding->last_seen_at->format('Y-m-d H:i') }} UTC. Resolve by editing the content, then rescan. No automatic rewrites are published.</p>
  <a class="button" href="{{ $finding->path === '/' ? route('admin.site-editor.edit') : route('admin.projects.index') }}">Open editor</a>
</section>
@empty
<section class="panel platform-card"><p>No findings have been recorded. Check scan history: an unconfigured or failed scan is not a clean bill of health.</p></section>
@endforelse
{{ $findings->links() }}
@include('admin.platform.history')
@endsection
