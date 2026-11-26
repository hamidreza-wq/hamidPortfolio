@extends('admin.layout')
@section('title', 'Backups')
@section('breadcrumb', 'Backups')
@section('content')
@php($size = fn ($bytes) => $bytes >= 1048576 ? number_format($bytes / 1048576, 1).' MB' : number_format(max(1, $bytes / 1024)).' KB')
@php($latestAt = $latest ? \App\Services\Backups\BackupManager::createdAt($latest) : null)
<div class="page-head">
  <div><p class="eyebrow">STUDIO / RECOVERY</p><h1>Backups</h1><p>Every project, section, setting, message, account and uploaded file, saved to private storage on this server and checked by reading it back.</p></div>
  <form method="post" action="{{ route('admin.backups.store') }}">@csrf<button class="button button-primary" type="submit">Back up now</button></form>
</div>

<div class="grid stats">
  <article class="stat-card"><div class="stat-top"><span>Last verified backup</span><span class="stat-mark">✓</span></div><div class="stat-number" style="font-size:20px">{{ $latestAt ? $latestAt->diffForHumans() : 'None yet' }}</div><div class="stat-caption">{{ $latestAt ? $latestAt->format('j M Y, H:i').' UTC' : 'Press "Back up now" to create the first one' }}</div></article>
  <article class="stat-card"><div class="stat-top"><span>Backups kept</span><span class="stat-mark">▤</span></div><div class="stat-number">{{ count($backups) }}<span style="color:#858294;font-size:13px;font-weight:400"> / {{ $keep }}</span></div><div class="stat-caption">Oldest removed after each new backup</div></article>
  <article class="stat-card"><div class="stat-top"><span>Space used</span><span class="stat-mark">◫</span></div><div class="stat-number" style="font-size:20px">{{ $size($totalBytes) }}</div><div class="stat-caption">{{ $freeBytes ? $size($freeBytes).' free on this disk' : 'Free space unknown' }}</div></article>
  <article class="stat-card"><div class="stat-top"><span>Automatic backups</span><span class="stat-mark">◷</span></div><div class="stat-number" style="font-size:20px">Daily</div><div class="stat-caption">Cron {{ $schedule }} (UTC), once the scheduler is installed</div></article>
</div>

@if($latestAt && $latestAt->lt(now()->subHours(36)))
  <div class="error-box" role="alert">The newest verified backup is more than 36 hours old. Check that the scheduler (php artisan schedule:run every minute) is installed on the server.</div>
@endif

<section class="panel" style="margin-bottom:14px" aria-labelledby="list-heading">
  <div class="panel-head"><h2 id="list-heading">Saved backups</h2></div>
  @if(! $backups)
    <p class="card-help" style="margin:0">No backups yet.</p>
  @else
    <div class="table-wrap"><table class="data-table"><thead><tr><th scope="col">CREATED (UTC)</th><th scope="col">STATUS</th><th scope="col">CONTENTS</th><th scope="col">SIZE</th><th scope="col">ACTIONS</th></tr></thead><tbody>
      @foreach($backups as $backup)
        @php($at = \App\Services\Backups\BackupManager::createdAt($backup))
        <tr>
          <td>{{ $at?->format('j M Y, H:i') ?? '—' }}<small>{{ ucfirst(str_replace('-', ' ', $backup['trigger'] ?? 'unknown')) }} · v{{ $backup['app_version'] ?? '?' }} · schema {{ $backup['schema_version'] ?? '?' }}</small><small style="font-family:var(--mono)">{{ $backup['name'] }}</small></td>
          <td>
            <span class="status-pill {{ ($backup['verified'] ?? false) ? '' : 'off' }}">{{ ($backup['verified'] ?? false) ? 'Verified' : 'Failed' }}</span>
            @if(! ($backup['verified'] ?? false) && ! empty($backup['error']))<small>{{ $backup['error'] }}</small>@endif
            @if(! empty($backup['verified_at']))<small>Checked {{ \Illuminate\Support\Carbon::parse($backup['verified_at'])->diffForHumans() }}</small>@endif
          </td>
          <td>{{ $backup['tables'] ?? '?' }} tables · {{ number_format($backup['rows'] ?? 0) }} rows<small>{{ $backup['files'] ?? '?' }} uploaded files</small></td>
          <td>{{ $size($backup['bytes']) }}</td>
          <td>
            <div class="form-inline" style="flex-wrap:wrap;align-items:end">
              <form method="post" action="{{ route('admin.backups.verify', $backup['name']) }}">@csrf<button class="button" type="submit">Verify again</button></form>
              <details>
                <summary class="button" style="list-style:none">Download or delete</summary>
                <div style="display:grid;gap:8px;margin-top:8px;min-width:240px">
                  <p class="field-hint" style="margin:0">The file holds password hashes and private messages. Keep any copy somewhere private.</p>
                  <form method="post" action="{{ route('admin.backups.download', $backup['name']) }}" class="form-inline" style="align-items:end">
                    @csrf
                    <div class="field" style="flex:1"><label for="dl-{{ $loop->index }}">Password</label><input id="dl-{{ $loop->index }}" name="password" type="password" autocomplete="current-password" required></div>
                    <button class="button" type="submit">Download</button>
                  </form>
                  <form method="post" action="{{ route('admin.backups.destroy', $backup['name']) }}" class="form-inline" style="align-items:end">
                    @csrf
                    @method('delete')
                    <div class="field" style="flex:1"><label for="rm-{{ $loop->index }}">Password</label><input id="rm-{{ $loop->index }}" name="password" type="password" autocomplete="current-password" required></div>
                    <button class="button button-danger" type="submit">Delete</button>
                  </form>
                </div>
              </details>
            </div>
          </td>
        </tr>
      @endforeach
    </tbody></table></div>
  @endif
</section>

<div class="grid dashboard-columns">
  <section class="panel" aria-labelledby="restore-heading">
    <div class="panel-head"><h2 id="restore-heading">Restoring a backup</h2></div>
    <p class="card-help">Restoring replaces everything on the live site, so it runs from the server's command line rather than a button here. The command first writes and verifies a safety backup of the current site, switches on maintenance mode, restores the database in a single transaction that rolls back on any error, then swaps in the uploaded files.</p>
    <p class="card-help" style="margin-bottom:6px">Over SSH, from the application's <code>backend</code> folder:</p>
    <pre style="margin:0 0 12px;padding:10px;border:1px solid var(--line);border-radius:6px;background:#0d0e18;color:#e5e1ed;font-size:10px;white-space:pre-wrap;word-break:break-all">php artisan portfolio:backup:restore {{ $latest['name'] ?? 'backup-YYYYMMDD-HHMMSS-xxxxxx.zip' }}</pre>
    <p class="card-help" style="margin:0">A backup only restores into the same database schema version it was made from. A backup from an older release needs that release deployed first.</p>
  </section>
  <section class="panel" aria-labelledby="scope-heading">
    <div class="panel-head"><h2 id="scope-heading">What's in a backup</h2></div>
    <div class="activity-list">
      <div class="activity-row"><span class="activity-dot"></span><div class="activity-copy"><strong>Included</strong><p>All database content (projects, sections, settings, revisions, media and font records, messages, accounts, analytics, audit log) and every uploaded image and font.</p></div></div>
      <div class="activity-row"><span class="activity-dot"></span><div class="activity-copy"><strong>Not included</strong><p>Sign-in sessions, cache and queued jobs, the application code (that comes from the signed release), and the server's .env file.</p></div></div>
      <div class="activity-row"><span class="activity-dot"></span><div class="activity-copy"><strong>Keep your APP_KEY</strong><p>Two-factor secrets and Google connection tokens are encrypted with APP_KEY. A restore on a server with a different key needs two-factor set up again and Google reconnected.</p></div></div>
      <div class="activity-row"><span class="activity-dot"></span><div class="activity-copy"><strong>Same-server copies</strong><p>Backups live on this server. Download one now and then and keep it somewhere else, so a lost hosting account doesn't take the backups with it.</p></div></div>
    </div>
  </section>
</div>
@endsection
