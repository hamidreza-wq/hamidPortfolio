@extends('admin.layout')
@section('title', 'Notifications')
@section('breadcrumb', 'Notifications')
@section('content')
@php($priorityPill = ['high' => 'off', 'medium' => 'warn', 'low' => ''])
@php($shortName = fn ($category) => \Illuminate\Support\Str::before($categories[$category] ?? ucfirst($category), ':'))
<div class="page-head">
  <div><p class="eyebrow">STUDIO / ATTENTION</p><h1>Notification centre</h1><p>Everything that needs your attention in one place: security, site health, backups, updates, new messages, SEO and GitHub. The same problem never appears twice; it reopens only if it changes or comes back after being fixed.</p></div>
  <form method="post" action="{{ route('admin.platform.notices.read-all') }}">@csrf @if($filters['category'])<input type="hidden" name="category" value="{{ $filters['category'] }}">@endif<button class="button" type="submit">Mark all{{ $filters['category'] ? ' '.strtolower($shortName($filters['category'])) : '' }} read</button></form>
</div>

<nav class="form-inline" aria-label="Notification filters" style="flex-wrap:wrap;margin-bottom:12px">
  <a class="button {{ ! $filters['category'] ? 'button-primary' : '' }}" href="{{ route('admin.platform.notifications', ['show' => $filters['show']]) }}" @unless($filters['category']) aria-current="page" @endunless>All · {{ $unreadCounts->sum() }}</a>
  @foreach($categories as $key => $label)
    <a class="button {{ $filters['category'] === $key ? 'button-primary' : '' }}" href="{{ route('admin.platform.notifications', ['category' => $key, 'show' => $filters['show']]) }}" @if($filters['category'] === $key) aria-current="page" @endif>{{ $shortName($key) }}@if($unreadCounts[$key] ?? 0) · {{ $unreadCounts[$key] }}@endif</a>
  @endforeach
</nav>
<form method="get" action="{{ route('admin.platform.notifications') }}" class="form-inline" style="align-items:end;margin-bottom:14px">
  @if($filters['category'])<input type="hidden" name="category" value="{{ $filters['category'] }}">@endif
  <div class="field"><label for="notice-show">Show</label><select id="notice-show" name="show">@foreach(['open' => 'Open (not resolved or dismissed)', 'unread' => 'Unread only', 'all' => 'Everything, including resolved'] as $value => $label)<option value="{{ $value }}" @selected($filters['show'] === $value)>{{ $label }}</option>@endforeach</select></div>
  <button class="button" type="submit">Apply</button>
</form>

@forelse($notices as $notice)
  @php($state = $notice->resolved_at ? 'Resolved' : ($notice->dismissed_at ? 'Dismissed' : ($notice->snoozed_until && $notice->snoozed_until->isFuture() ? 'Snoozed' : ($notice->read_at ? 'Read' : 'Unread'))))
  <section class="panel platform-card" @if($state === 'Unread') style="border-color:rgba(194,173,255,.3)" @endif>
    <div class="panel-head"><h2>{{ $notice->title }}</h2><span class="status-pill {{ $notice->resolved_at ? '' : $priorityPill[$notice->priority] ?? '' }}">{{ $shortName($notice->category) }} · {{ $notice->priority }}</span></div>
    <p>{{ $notice->message }}</p>
    <p class="card-help">{{ $state }} · last seen {{ $notice->last_seen_at->diffForHumans() }} ({{ $notice->last_seen_at->format('j M Y, H:i') }} UTC)</p>
    <form method="post" action="{{ route('admin.platform.notice', $notice) }}" class="form-inline" style="flex-wrap:wrap">
      @csrf @method('patch')
      @if(\Illuminate\Support\Facades\Route::has($notice->route_name))<a class="button button-primary" href="{{ route($notice->route_name) }}">Open related page</a>@endif
      @unless($notice->read_at)<button class="button" name="action" value="read" type="submit">Mark read</button>@endunless
      @unless($notice->resolved_at)
        <button class="button" name="action" value="snooze" type="submit">Snooze 24 hours</button>
        <button class="button" name="action" value="dismiss" type="submit">Dismiss</button>
      @endunless
    </form>
  </section>
@empty
  <section class="panel platform-card"><p style="margin:0">{{ $filters['show'] === 'all' ? 'No notifications recorded yet.' : 'No notifications recorded that need your attention.' }} Monitoring needs the server's scheduler to be running.</p></section>
@endforelse
{{ $notices->links() }}

<section class="panel platform-card" aria-labelledby="delivery-heading">
  <h2 id="delivery-heading">What you're told about, and how</h2>
  <p class="card-help">These choices are yours alone. Read and dismissed states are shared by all administrators. Untick a category to leave it out of your unread badge and emails; it still appears on this page.</p>
  <form method="post" action="{{ route('admin.platform.preferences') }}" class="field-grid">
    @csrf @method('put')
    <fieldset class="field" style="border:0;padding:0;margin:0"><legend class="field-label">Categories</legend>@foreach($categories as $value => $label)<label class="checkbox-row"><input type="checkbox" name="categories[]" value="{{ $value }}" @checked(in_array($value, $preferences->categories ?? [], true))>{{ $label }}</label>@endforeach</fieldset>
    <div class="field">
      <label class="checkbox-row"><input type="checkbox" name="instant_alerts" value="1" @checked($preferences->instant_alerts)> Email me straight away about high-priority alerts</label>
      <span class="field-hint">For example a failing backup, a red health check, two-factor being turned off, or a burst of failed sign-ins. Alerts are grouped, with at most one email every {{ \App\Console\Commands\SendPlatformAlerts::BATCH_MINUTES }} minutes.</span>
      <label for="digest-frequency" style="margin-top:10px">Summary email</label><select id="digest-frequency" name="digest_frequency">@foreach(['never' => 'Off', 'daily' => 'Daily (at most once every 24 hours)', 'weekly' => 'Weekly (at most once every 7 days)'] as $value => $label)<option value="{{ $value }}" @selected($preferences->digest_frequency === $value)>{{ $label }}</option>@endforeach</select>
      <button class="button button-primary" type="submit" style="margin-top:8px">Save preferences</button>
    </div>
  </form>
  <p class="card-help" style="margin-bottom:0">Emails go to {{ auth()->user()->email }} through the "{{ config('mail.default') }}" mail transport, and need the server's scheduler. "log" and "array" transports don't reach an inbox. Emails never contain message text, names of people who wrote to you, or passwords: only what happened and a link back here.</p>
</section>
@include('admin.platform.history')
@endsection
