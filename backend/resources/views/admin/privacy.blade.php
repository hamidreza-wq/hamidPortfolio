@extends('admin.layout')
@section('title', 'Privacy')
@section('breadcrumb', 'Privacy')
@section('content')
<div class="page-head">
  <div><p class="eyebrow">STUDIO / DATA PROTECTION</p><h1>Privacy</h1><p>What personal information the site keeps, for how long, and tools to answer someone who asks for a copy of their data or for it to be deleted.</p></div>
  <a class="button" href="{{ route('portfolio.privacy') }}" target="_blank" rel="noopener">Public privacy notice ↗</a>
</div>

<div class="error-box" role="note" style="color:#e8cf9b;border-color:rgba(240,206,140,.25);background:rgba(176,138,62,.07)">These tools help you look after personal data, but they don't make the site legally compliant on their own. UK GDPR obligations depend on how you use the site; check your privacy notice and retention choices with someone qualified if you're unsure.</div>

<section class="panel" style="margin-bottom:14px" aria-labelledby="inventory-heading">
  <div class="panel-head"><h2 id="inventory-heading">Personal data on this site</h2></div>
  <div class="table-wrap"><table class="data-table">
    <thead><tr><th scope="col">WHAT</th><th scope="col">DETAILS STORED</th><th scope="col">RECORDS</th><th scope="col">HOW LONG</th></tr></thead>
    <tbody>
      @foreach($inventory as [$what, $details, $count, $retention, $oldest])
        <tr><td><strong style="color:#dcd8e6">{{ $what }}</strong></td><td>{{ $details }}</td><td>{{ $count }}@if($oldest)<small>oldest {{ \Illuminate\Support\Carbon::parse($oldest)->format('j M Y') }}</small>@endif</td><td>{{ $retention }}</td></tr>
      @endforeach
    </tbody>
  </table></div>
  <p class="card-help" style="margin:12px 0 0">Outside this database: notification emails sent to your mailbox, and data Google Analytics collects from visitors who allow it ({{ $measurementId ? 'measurement ID '.$measurementId : 'not configured' }}). Analytics only loads after a visitor chooses "Allow analytics" in the cookie banner, and Google's own retention is set in your Google Analytics account.</p>
</section>

<div class="grid dashboard-columns" style="margin-bottom:14px">
  <section class="panel" aria-labelledby="retention-heading">
    <div class="panel-head"><h2 id="retention-heading">Retention</h2></div>
    <p class="card-help">Applied once a day. Keeping personal data only as long as you need it is a core data protection principle. The public privacy notice states whatever you choose here.</p>
    <form method="post" action="{{ route('admin.privacy.retention') }}" class="project-form">
      @csrf @method('put')
      <div class="field"><label for="contact-retention">Delete contact messages after</label><select id="contact-retention" name="contact_retention_days">@foreach($choices as $days => $label)<option value="{{ $days }}" @selected((string) $settings->contact_retention_days === (string) $days)>{{ $label }}</option>@endforeach</select><span class="field-hint">Counted from when each message arrived, whatever its status. Export anything you need to keep first.</span></div>
      <div class="field"><label for="ip-retention">Remove IP addresses from the activity log after</label><select id="ip-retention" name="activity_ip_retention_days">@foreach($choices as $days => $label)<option value="{{ $days }}" @selected((string) $settings->activity_ip_retention_days === (string) $days)>{{ $label }}</option>@endforeach</select><span class="field-hint">The entries stay; only the address is cleared. 90 days still leaves time to investigate failed sign-ins.</span></div>
      <div><button class="button button-primary" type="submit">Save retention</button></div>
    </form>
  </section>

  <section class="panel" aria-labelledby="requests-heading">
    <div class="panel-head"><h2 id="requests-heading">Data requests</h2></div>
    <p class="card-help">When someone asks what you hold about them, or asks you to delete it, look them up by the email address they used. Check the request really comes from that address before sending anything.</p>
    <form method="post" action="{{ route('admin.privacy.lookup') }}" class="form-inline" style="align-items:end" role="search">
      @csrf
      <div class="field" style="flex:1"><label for="request-email">Email address</label><input id="request-email" name="email" type="email" value="{{ $email }}" maxlength="255" required autocomplete="off"></div>
      <button class="button" type="submit">Find</button>
    </form>

    @if($found)
      @php($total = $found['messages']->count() + $found['testimonials']->count())
      <div style="margin-top:14px">
        <p class="card-help" style="margin-bottom:8px"><strong style="color:#dcd8e6">{{ $found['messages']->count() }} contact message(s)</strong> and <strong style="color:#dcd8e6">{{ $found['testimonials']->count() }} testimonial(s)</strong> use this address.</p>
        @if($total)
          <ul class="card-help" style="padding-left:16px;margin-top:0">
            @foreach($found['messages'] as $message)<li>Message "{{ \Illuminate\Support\Str::limit($message->subject, 60) }}" · {{ $message->created_at->format('j M Y') }}</li>@endforeach
            @foreach($found['testimonials'] as $testimonial)<li>Testimonial from {{ $testimonial->name }} · {{ \App\Models\Testimonial::STATUSES[$testimonial->status] ?? $testimonial->status }}{{ $testimonial->status === 'approved' ? ' (shown on the site)' : '' }}</li>@endforeach
          </ul>
          <div class="form-inline" style="flex-wrap:wrap;align-items:end">
            <form method="post" action="{{ route('admin.privacy.export') }}">@csrf<input type="hidden" name="email" value="{{ $email }}"><button class="button" type="submit">Download a copy (JSON)</button></form>
          </div>
          <details style="margin-top:12px">
            <summary class="button button-danger" style="list-style:none;display:inline-flex">Erase this person's data</summary>
            <form method="post" action="{{ route('admin.privacy.erase') }}" class="project-form" style="margin-top:10px">
              @csrf @method('delete')
              <input type="hidden" name="email" value="{{ $email }}">
              <p class="card-help" style="margin:0">Permanently deletes the {{ $total }} record(s) above{{ $found['testimonials']->where('status', 'approved')->count() ? ', including published testimonials, which disappear from the site' : '' }}. It can't be undone. Older backups keep copies until they're replaced.</p>
              <div class="field"><label for="erase-password">Your password</label><input id="erase-password" name="password" type="password" autocomplete="current-password" required></div>
              <label class="checkbox-row"><input type="checkbox" name="confirm" value="1" required> I have checked this request comes from the person, and I want their data erased</label>
              <div><button class="button button-danger" type="submit">Erase permanently</button></div>
            </form>
          </details>
        @else
          <p class="card-help" style="margin:0">Nothing to export or erase. Pageview counts can't be linked to a person, so they never match.</p>
        @endif
      </div>
    @endif
  </section>
</div>

<section class="panel" aria-labelledby="privacy-log">
  <div class="panel-head"><h2 id="privacy-log">Recent privacy actions</h2><a href="{{ route('admin.security', ['filter' => 'all']) }}">Full activity log →</a></div>
  @if($requests->isEmpty())
    <p class="card-help" style="margin:0">None yet.</p>
  @else
    <div class="activity-list">
      @foreach($requests as $entry)
        <div class="activity-row"><span class="activity-dot"></span><div class="activity-copy"><strong>{{ str($entry->action)->replace('.', ' ')->headline() }}</strong><p>{{ $entry->user?->name ?? 'Scheduled' }}@if(isset($entry->details['name'])) · {{ $entry->details['name'] }}@endif @foreach(['messages', 'testimonials', 'activity_ips'] as $key)@if(isset($entry->details[$key])) · {{ $entry->details[$key] }} {{ str_replace('_', ' ', $key) }}@endif @endforeach</p></div><time class="activity-time">{{ $entry->created_at->diffForHumans() }}</time></div>
      @endforeach
    </div>
    <p class="card-help" style="margin:10px 0 0">Requests are logged with a one-way reference, not the email address.</p>
  @endif
</section>
@endsection
