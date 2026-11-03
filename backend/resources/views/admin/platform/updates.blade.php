@extends('admin.layout')
@section('title', 'Software updates')
@section('breadcrumb', 'Software updates')
@section('content')
<div class="page-head"><div><p class="eyebrow">SYSTEM / RELEASE TRUST</p><h1>Software update centre</h1><p>Official releases only. Verify before review. No web-shell access.</p></div></div>
<div class="grid dashboard-columns platform-stack">
  <section class="panel platform-card">
    <h2>Installed {{ $version }}</h2>
    <p>PHP {{ PHP_VERSION }} / Laravel {{ app()->version() }} / MySQL required</p>
    <p class="card-help">Release source: hamidreza-wq/hamidPortfolio. Stable semantic version tags only; never the main branch ZIP.</p>
    @include('admin.platform.check', ['kind' => 'releases', 'label' => 'Check official releases'])
    <p class="card-help">Automatic checks: {{ config('platform.schedule.releases') }} (UTC). A configured scheduler is required.</p>
  </section>
  <section class="panel platform-card">
    <h2>Activation disabled / manual action required</h2>
    <p>No hosting target, staging, verified database/media/config backups, health check, or rollback runner has been configured. No update has been applied by this centre.</p>
    <p class="card-help">Your protected GitHub Actions production environment must require human approval. This dashboard cannot dispatch deployments or restore databases.</p>
    <h2>Dependencies and runtime</h2>
    <p class="card-help">Run <code>php artisan platform:audit</code> from trusted CLI / CI. Results appear in history. This does not install or update packages. Host runtime upgrades remain manual.</p>
    <p class="card-help">Last successful activation: none recorded. Backup status: not configured / not verified.</p>
  </section>
</div>
@foreach($releases as $release)
<section class="panel platform-card">
  <div class="panel-head"><h2>{{ $release->tag }}</h2><span class="status-pill {{ $release->status === 'failed' ? 'off' : '' }}">{{ $release->status }}</span></div>
  <p class="card-help">Published {{ $release->released_at->format('Y-m-d H:i') }} UTC. Release notes are untrusted plain text.</p>
  <pre>{{ $release->notes }}</pre>
  <form method="post" action="{{ route('admin.platform.prepare', $release) }}" class="field-grid" data-platform-submit>
    @csrf
    <div class="field"><label for="verify-password-{{ $release->id }}">Your administrator password</label><input id="verify-password-{{ $release->id }}" type="password" name="password" autocomplete="current-password" required></div>
    <div class="field"><label class="checkbox-row"><input type="checkbox" name="confirm" value="1" required>I request signature, checksum and archive inspection only.</label><button class="button" type="submit">Verify release artifact</button><span class="field-hint" role="status" data-platform-status>No code will execute.</span></div>
  </form>
  @if($release->manifest)
    <details><summary>Signed manifest and runtime compatibility</summary><pre>{{ json_encode($release->manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre></details>
  @endif
  @if($release->inspection)
    <details><summary>Archive / migration review</summary><pre>{{ json_encode($release->inspection, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre></details>
  @endif
  @if($release->status === 'awaiting_approval')
    <form method="post" action="{{ route('admin.platform.approve', $release) }}" class="field-grid">
      @csrf
      <div class="field"><label for="approve-password-{{ $release->id }}">Re-enter your password to record review</label><input id="approve-password-{{ $release->id }}" type="password" name="password" autocomplete="current-password" required></div>
      <div class="field"><label class="checkbox-row"><input type="checkbox" name="confirm" value="1" required>I reviewed the signed manifest and every migration. This does not deploy.</label><button class="button" type="submit">Record review approval</button></div>
    </form>
  @endif
</section>
@endforeach
{{ $releases->links() }}
@include('admin.platform.history')
@endsection
