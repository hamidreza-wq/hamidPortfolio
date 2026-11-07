@extends('admin.layout')
@section('title', 'Notifications')
@section('breadcrumb', 'Notifications')
@section('content')
<div class="page-head"><div><p class="eyebrow">STUDIO / ATTENTION</p><h1>Notification centre</h1><p>Updates, discovery and SEO in one deduplicated history.</p></div></div>
<section class="panel platform-card">
  <h2>Delivery preferences</h2>
  <p class="card-help">Read and dismissal state is shared by studio administrators. Snooze hides the badge for 24 hours. Identical warnings do not create duplicates or reset dismissal; changed or recurring resolved issues reopen. Preferences affect your unread badge and email digest, not the retained history below.</p>
  <form method="post" action="{{ route('admin.platform.preferences') }}" class="field-grid">
    @csrf @method('put')
    <div class="field"><span class="field-label">Categories</span>@foreach(['updates' => 'Security / software / dependency updates', 'github' => 'GitHub review', 'seo' => 'SEO monitoring'] as $value => $label)<label class="checkbox-row"><input type="checkbox" name="categories[]" value="{{ $value }}" @checked(in_array($value, $preferences->categories, true))>{{ $label }}</label>@endforeach</div>
    <div class="field"><label for="digest-frequency">Email summaries to your administrator account address</label><select id="digest-frequency" name="digest_frequency">@foreach(['never'=>'Disabled', 'daily'=>'Daily (at most once per 24 hours)', 'weekly'=>'Weekly (at most once per 7 days)'] as $value=>$label)<option value="{{ $value }}" @selected($preferences->digest_frequency === $value)>{{ $label }}</option>@endforeach</select><button class="button" type="submit">Save preferences</button></div>
  </form>
  <p class="card-help">Mail transport: {{ config('mail.default') }}. Configure SMTP and a scheduler before enabling email; log/array transports do not deliver to an inbox. Digests contain at most 50 current unread, unsnoozed notices, not private inbox messages or credentials. Delivery history reports submission, not confirmed receipt.</p>
</section>
@forelse($notices as $notice)
<section class="panel platform-card">
  <div class="panel-head"><h2>{{ $notice->title }}</h2><span class="status-pill {{ !$notice->resolved_at ? 'off' : '' }}">{{ $notice->category }} / {{ $notice->priority }}</span></div>
  <p>{{ $notice->message }}</p>
  <p class="card-help">{{ $notice->resolved_at ? 'Resolved' : ($notice->dismissed_at ? 'Dismissed' : ($notice->snoozed_until && $notice->snoozed_until->isFuture() ? 'Snoozed' : ($notice->read_at ? 'Read' : 'Unread'))) }} / Last seen {{ $notice->last_seen_at->format('Y-m-d H:i') }} UTC</p>
  <form method="post" action="{{ route('admin.platform.notice', $notice) }}" class="form-inline">
    @csrf @method('patch')
    <a class="button" href="{{ route($notice->route_name) }}">Open related page</a>
    <button class="button" name="action" value="read" type="submit">Mark read</button>
    <button class="button" name="action" value="snooze" type="submit">Snooze 24 hours</button>
    <button class="button" name="action" value="dismiss" type="submit">Dismiss</button>
  </form>
</section>
@empty
<section class="panel platform-card"><p>No notifications recorded. Monitoring needs a running scheduler and valid server configuration.</p></section>
@endforelse
{{ $notices->links() }}
@include('admin.platform.history')
@endsection
