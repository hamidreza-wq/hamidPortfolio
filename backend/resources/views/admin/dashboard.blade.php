@extends('admin.layout')
@section('title', 'Overview')
@section('breadcrumb', 'Overview')
@section('content')
<div class="page-head">
  <div><p class="eyebrow">YOUR DIGITAL UNIVERSE / OVERVIEW</p><h1>Good to see you, {{ auth()->user()->name }}.</h1><p>A clear snapshot of your portfolio, publishing status, and recent activity.</p></div>
  <a class="button button-primary" href="{{ route('admin.site-editor.edit') }}">Open live editor <span aria-hidden="true">↗</span></a>
</div>
<div class="grid stats">
  <article class="stat-card"><div class="stat-top"><span>All projects</span><span class="stat-mark">▧</span></div><div class="stat-number">{{ $projectCount }}</div><div class="stat-caption">{{ $publishedCount }} published · {{ $draftCount }} draft</div></article>
  <article class="stat-card"><div class="stat-top"><span>Published sections</span><span class="stat-mark">◉</span></div><div class="stat-number">{{ $sectionCount }}<span style="color:#858294;font-size:13px;font-weight:400"> / {{ $totalSections }}</span></div><div class="stat-caption">Visibility managed in the live editor</div></article>
  <article class="stat-card"><div class="stat-top"><span>Unread messages</span><span class="stat-mark">✉</span></div><div class="stat-number">{{ $unreadMessages }}</div><div class="stat-caption">Contact inbox submissions</div></article>
  <article class="stat-card"><div class="stat-top"><span>Media storage</span><span class="stat-mark">▤</span></div><div class="stat-number">{{ number_format($mediaBytes / 1048576, 1) }}<span style="color:#858294;font-size:13px;font-weight:400"> MB</span></div><div class="stat-caption">Uploaded image library</div></article>
</div>
<div class="grid dashboard-columns">
  <section class="panel">
    <div class="panel-head"><h2>Recent changes</h2><a href="{{ route('admin.site-editor.edit') }}">Open editor →</a></div>
    @if($activity->isEmpty())
      <div class="activity-row"><span class="activity-dot"></span><div class="activity-copy"><strong>Your studio is ready</strong><p>Save your first content update from the live site editor to start the audit trail.</p></div></div>
    @else
      <div class="activity-list">
        @foreach($activity as $item)
          <div class="activity-row"><span class="activity-dot"></span><div class="activity-copy"><strong>{{ str($item->action)->replace('.', ' ')->headline() }}</strong><p>{{ $item->user?->name ?? 'System' }}@if($item->details && isset($item->details['name'])) · {{ $item->details['name'] }}@endif</p></div><time class="activity-time">{{ $item->created_at->diffForHumans() }}</time></div>
        @endforeach
      </div>
    @endif
  </section>
  <section class="panel">
    <div class="panel-head"><h2>Quick actions</h2><span class="status-pill {{ $settings->maintenance_mode ? 'off' : '' }}">{{ $settings->maintenance_mode ? 'Maintenance' : 'Live site' }}</span></div>
    <div class="quick-actions">
      <a class="quick-action" href="{{ route('admin.site-editor.edit') }}">Edit site appearance<span>Copy, layout, color and typography</span></a>
      <a class="quick-action" href="{{ route('admin.projects.index') }}">Manage projects<span>Featured work and repository details</span></a>
      <a class="quick-action" href="{{ route('admin.media.index') }}">Upload an image<span>Manage approved portfolio media</span></a>
      <a class="quick-action" href="{{ route('admin.inbox.index') }}">Check your inbox<span>{{ $unreadMessages }} unread messages</span></a>
    </div>
    <div style="margin-top:17px;padding-top:14px;border-top:1px solid rgba(255,255,255,.06)">
      <div class="panel-head" style="margin-bottom:7px"><h2>Search visibility</h2><a href="{{ route('admin.site-editor.edit') }}">Review SEO →</a></div>
      <p style="margin:0;color:#9290a2;font-size:9px;line-height:1.75">Analytics are not configured. This dashboard does not invent visitor or traffic data.</p>
    </div>
  </section>
</div>
@endsection
