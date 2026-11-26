@extends('admin.layout')
@section('title', 'Security')
@section('breadcrumb', 'Security')
@section('content')
@php($pill = ['ok' => ['', 'Healthy'], 'warning' => ['warn', 'Warning'], 'critical' => ['off', 'Critical']])
<div class="page-head">
  <div><p class="eyebrow">STUDIO / PROTECTION</p><h1>Security</h1><p>What protects this dashboard right now, who has signed in, and every recorded admin action. These checks read the running configuration; they change nothing.</p></div>
</div>

<div class="grid stats">
  @php($counts = collect($checks)->countBy('state'))
  <article class="stat-card"><div class="stat-top"><span>Checks passing</span><span class="stat-mark">✓</span></div><div class="stat-number">{{ $counts['ok'] ?? 0 }}<span style="color:#858294;font-size:13px;font-weight:400"> / {{ count($checks) }}</span></div><div class="stat-caption">{{ $counts['warning'] ?? 0 }} warning · {{ $counts['critical'] ?? 0 }} critical</div></article>
  @foreach($failed as $window => $count)
    <article class="stat-card"><div class="stat-top"><span>Failed sign-ins</span><span class="stat-mark">!</span></div><div class="stat-number">{{ $count }}</div><div class="stat-caption">Last {{ $window }}</div></article>
  @endforeach
</div>

<section class="panel" id="two-factor" aria-labelledby="two-factor-heading" style="margin-bottom:14px">
  <div class="panel-head"><h2 id="two-factor-heading">Two-factor sign-in</h2><span class="status-pill {{ $twoFactorEnabled ? '' : 'warn' }}">{{ $twoFactorEnabled ? 'On' : ($pendingSecret ? 'Waiting for confirmation' : 'Off') }}</span></div>
  @if(session('recoveryCodes'))
    <div class="editor-card" style="margin-bottom:14px;padding:14px;border-color:rgba(240,206,140,.3)">
      <h2>Your recovery codes</h2>
      <p class="card-help">Each code signs you in once if you lose your phone. Store them in a password manager or print them. This is the only time they are shown.</p>
      <ul style="display:grid;grid-template-columns:repeat(2,minmax(0,max-content));gap:6px 28px;margin:0;padding:0;list-style:none;font-family:var(--mono);font-size:12px;color:#ece7f6">
        @foreach(session('recoveryCodes') as $code)<li>{{ $code }}</li>@endforeach
      </ul>
    </div>
  @endif
  @if($twoFactorEnabled)
    <p class="card-help">Signing in needs your password and a 6-digit code from your authenticator app. {{ $recoveryRemaining }} of 8 recovery codes left.@if($recoveryRemaining <= 2) Generate new ones soon.@endif</p>
    <div class="field-grid">
      <form method="post" action="{{ route('admin.security.two-factor.recovery') }}" class="form-inline" style="align-items:end">
        @csrf
        <div class="field" style="flex:1"><label for="recovery-password">Password</label><input id="recovery-password" name="password" type="password" autocomplete="current-password" required></div>
        <button class="button" type="submit">New recovery codes</button>
      </form>
      <form method="post" action="{{ route('admin.security.two-factor.disable') }}" class="form-inline" style="align-items:end">
        @csrf
        @method('delete')
        <div class="field" style="flex:1"><label for="disable-password">Password</label><input id="disable-password" name="password" type="password" autocomplete="current-password" required></div>
        <div class="field" style="width:110px"><label for="disable-code">Current code</label><input id="disable-code" name="code" inputmode="numeric" maxlength="7" autocomplete="one-time-code" required></div>
        <button class="button button-danger" type="submit">Turn off</button>
      </form>
    </div>
  @elseif($pendingSecret)
    <div style="display:flex;flex-wrap:wrap;gap:20px;align-items:flex-start">
      <div style="padding:10px;border-radius:8px;background:#fff;line-height:0" role="img" aria-label="QR code for your authenticator app">{!! $qrSvg !!}</div>
      <div style="flex:1;min-width:240px">
        <p class="card-help">1. Open an authenticator app (Google Authenticator, Microsoft Authenticator, 1Password, Bitwarden…) and scan this code. If you can't scan it, enter this key instead:</p>
        <p style="margin:0 0 14px;font-family:var(--mono);font-size:12px;letter-spacing:.08em;color:#ece7f6;word-break:break-all">{{ trim(chunk_split($pendingSecret, 4, ' ')) }}</p>
        <p class="card-help">2. Enter the 6-digit code the app shows to switch two-factor sign-in on.</p>
        <form method="post" action="{{ route('admin.security.two-factor.confirm') }}" class="form-inline" style="align-items:end">
          @csrf
          <div class="field" style="width:140px"><label for="confirm-code">Code from the app</label><input id="confirm-code" name="code" inputmode="numeric" maxlength="7" autocomplete="one-time-code" required autofocus></div>
          <button class="button button-primary" type="submit">Turn on</button>
        </form>
      </div>
    </div>
  @else
    <p class="card-help">Add a second step to sign-in: after your password, a 6-digit code from an app on your phone. A stolen or guessed password alone will no longer open the dashboard.</p>
    <form method="post" action="{{ route('admin.security.two-factor.start') }}" class="form-inline" style="align-items:end">
      @csrf
      <div class="field" style="flex:1;max-width:320px"><label for="start-password">Confirm your password</label><input id="start-password" name="password" type="password" autocomplete="current-password" required></div>
      <button class="button button-primary" type="submit">Set up two-factor sign-in</button>
    </form>
  @endif
</section>

<div class="grid dashboard-columns" style="margin-bottom:14px">
  <section class="panel" aria-labelledby="checks-heading">
    <div class="panel-head"><h2 id="checks-heading">Protection checks</h2></div>
    <div class="activity-list">
      @foreach($checks as $check)
        <div class="activity-row"><div class="activity-copy"><strong>{{ $check['label'] }}</strong><p>{{ $check['detail'] }}</p></div><span class="status-pill {{ $pill[$check['state']][0] }}">{{ $pill[$check['state']][1] }}</span></div>
      @endforeach
    </div>
  </section>

  <div class="grid" style="align-content:start">
    <section class="panel" aria-labelledby="sessions-heading">
      <div class="panel-head"><h2 id="sessions-heading">Your signed-in sessions</h2></div>
      @if($lastLogin)
        <p class="card-help">Previous sign-in: {{ $lastLogin->created_at->format('j M Y, H:i') }} UTC from {{ $lastLogin->ip_address ?? 'an unknown address' }}. If that wasn't you, change your password from the server command line and sign out other sessions.</p>
      @endif
      @if(! $tracksSessions)
        <p class="card-help">Sessions are not stored in the database (SESSION_DRIVER is "{{ config('session.driver') }}"), so they can't be listed here. Signing out other sessions still works.</p>
      @else
        <div class="activity-list">
          @forelse($sessions as $session)
            <div class="activity-row"><span class="activity-dot"></span><div class="activity-copy"><strong>{{ $session['agent'] }}@if($session['current']) <span style="color:#99d9bd;font-weight:400">· this browser</span>@endif</strong><p>{{ $session['ip'] ?? 'Unknown address' }}</p></div><time class="activity-time" datetime="{{ $session['last_active']->toIso8601String() }}">{{ $session['last_active']->diffForHumans() }}</time></div>
          @empty
            <p class="card-help">No stored sessions found.</p>
          @endforelse
        </div>
      @endif
      <form method="post" action="{{ route('admin.security.sessions.destroy') }}" class="form-inline" style="align-items:end;margin-top:12px">
        @csrf
        @method('delete')
        <div class="field" style="flex:1"><label for="security-password">Confirm your password</label><input id="security-password" name="password" type="password" autocomplete="current-password" required></div>
        <button class="button button-danger" type="submit">Sign out other sessions</button>
      </form>
    </section>

    <section class="panel" aria-labelledby="sources-heading">
      <div class="panel-head"><h2 id="sources-heading">Failed sign-in sources · 7 days</h2></div>
      @if($failedSources->isEmpty())
        <p class="card-help" style="margin:0">No failed sign-ins in the last 7 days.</p>
      @else
        <div class="table-wrap"><table class="data-table"><thead><tr><th scope="col">IP ADDRESS</th><th scope="col">ATTEMPTS</th><th scope="col">LAST SEEN</th></tr></thead><tbody>
          @foreach($failedSources as $source)
            <tr><td>{{ $source->ip_address }}</td><td>{{ $source->attempts }}</td><td>{{ \Illuminate\Support\Carbon::parse($source->last_seen)->diffForHumans() }}</td></tr>
          @endforeach
        </tbody></table></div>
        <p class="card-help" style="margin:10px 0 0">Attempted email addresses are not recorded. Repeated attempts are already throttled.</p>
      @endif
    </section>
  </div>
</div>

<section class="panel" aria-labelledby="log-heading">
  <div class="panel-head"><h2 id="log-heading">Activity log</h2></div>
  <form method="get" action="{{ route('admin.security') }}" class="form-inline" style="align-items:end;margin-bottom:12px">
    <div class="field"><label for="log-filter">Show</label><select id="log-filter" name="filter">@foreach($filters as $value => $label)<option value="{{ $value }}" @selected($filter === $value)>{{ $label }}</option>@endforeach</select></div>
    <button class="button" type="submit">Filter</button>
  </form>
  @if($log->isEmpty())
    <p class="card-help">Nothing recorded for this filter.</p>
  @else
    <div class="table-wrap"><table class="data-table"><thead><tr><th scope="col">WHEN (UTC)</th><th scope="col">ACTION</th><th scope="col">BY</th><th scope="col">IP ADDRESS</th></tr></thead><tbody>
      @foreach($log as $entry)
        <tr>
          <td>{{ $entry->created_at->format('j M Y, H:i') }}<small>{{ $entry->created_at->diffForHumans() }}</small></td>
          <td>{{ str($entry->action)->replace('.', ' ')->headline() }}@if(isset($entry->details['name']))<small>{{ $entry->details['name'] }}</small>@endif</td>
          <td>{{ $entry->user?->name ?? ($entry->action === 'authentication.failed' ? 'Unknown visitor' : 'System') }}</td>
          <td>{{ $entry->ip_address ?? '—' }}</td>
        </tr>
      @endforeach
    </tbody></table></div>
    @if($log->hasPages())<div class="pagination">{{ $log->links() }}</div>@endif
  @endif
</section>
@endsection
