@extends('admin.layout')
@section('title', 'Software updates')
@section('breadcrumb', 'Software updates')
@section('content')
<div class="page-head"><div><p class="eyebrow">SYSTEM / RELEASE TRUST</p><h1>Software update centre</h1><p>Official releases only. Verify before review. No web-shell access.</p></div></div>
<div class="grid dashboard-columns platform-stack">
  <section class="panel platform-card">
    <h2>Installed {{ $version }}</h2>
    <p>PHP {{ PHP_VERSION }} / Laravel {{ app()->version() }} / MySQL-compatible database required</p>
    <p class="card-help">Signed release verification: sodium {{ extension_loaded('sodium') ? 'available' : 'missing' }}; ZIP {{ extension_loaded('zip') ? 'available' : 'missing' }}.</p>
    <p class="card-help">Release source: hamidreza-wq/hamidPortfolio. Stable semantic version tags only; never the main branch ZIP.</p>
    @include('admin.platform.check', ['kind' => 'releases', 'label' => 'Check official releases'])
    <p class="card-help">Automatic checks: {{ config('platform.schedule.releases') }} (UTC). A configured scheduler is required.</p>
  </section>
  <section class="panel platform-card">
    <h2>{{ $deploymentConfigured ? 'Protected Update deployment' : 'Activation disabled / setup required' }}</h2>
    <p>Verified, schema-preserving releases can be requested with Update after administrator password confirmation. GitHub Actions requires independent production approval, verifies the signed artifact again, backs up the current site, activates an isolated release and checks health. Failed health checks restore the previous release.</p>
    <p class="card-help">{{ $deploymentConfigured ? 'Deployment App configured. Production approval protections are checked again before each request.' : 'Configure the selected-repository GitHub App, release trust key, protected production environment and hosting secrets before Update is available.' }} Database migrations and hosting runtime upgrades remain separately reviewed operations.</p>
    <h2>Dependencies and runtime</h2>
    <p class="card-help">Run <code>php artisan platform:audit</code> from trusted CLI / CI. Results appear in history. This does not install or update packages. Host runtime upgrades remain manual.</p>
    <p class="card-help">Deployment requests and their actual workflow status appear below. Never interpret verification, review approval or dispatch as deployment success.</p>
    <a class="button" href="https://github.com/hamidreza-wq/hamidPortfolio/actions/workflows/deploy.yml" target="_blank" rel="noopener noreferrer">Open deployment workflows</a>
  </section>
</div>
@foreach($releases as $release)
<section class="panel platform-card">
  <div class="panel-head"><h2>{{ $release->tag }}</h2><span class="status-pill {{ $release->status === 'failed' ? 'off' : '' }}">{{ $release->status }}</span></div>
  <p class="card-help">Published {{ $release->released_at->format('Y-m-d H:i') }} UTC. Release notes are untrusted plain text.</p>
  <pre>{{ $release->notes }}</pre>
  @if(!str_starts_with($release->status, 'deployment_') && $release->status !== 'applied')
  <form method="post" action="{{ route('admin.platform.prepare', $release) }}" class="field-grid" data-platform-submit>
    @csrf
    <div class="field"><label for="verify-password-{{ $release->id }}">Your administrator password</label><input id="verify-password-{{ $release->id }}" type="password" name="password" autocomplete="current-password" required></div>
    <div class="field"><label class="checkbox-row"><input type="checkbox" name="confirm" value="1" required>I request signature, checksum and archive inspection only.</label><button class="button" type="submit">Verify release artifact</button><span class="field-hint" role="status" data-platform-status>No code will execute.</span></div>
  </form>
  @endif
  @if($release->manifest)
    <details><summary>Signed manifest and runtime compatibility</summary><pre>{{ json_encode($release->manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre></details>
  @endif
  @if($deploymentConfigured && in_array($release->status, ['awaiting_approval', 'manual_action_required'], true))
    <form method="post" action="{{ route('admin.platform.deploy', $release) }}" class="field-grid" data-platform-submit>
      @csrf
      <div class="field"><label for="update-password-{{ $release->id }}">Confirm your administrator password</label><input id="update-password-{{ $release->id }}" type="password" name="password" autocomplete="current-password" required></div>
      <div class="field"><label class="checkbox-row"><input type="checkbox" name="confirm" value="1" required>I reviewed this release and request a backed-up, schema-preserving update through protected GitHub Actions.</label><button class="button" type="submit">Update</button><span class="field-hint" role="status" data-platform-status>Independent GitHub production approval is still required.</span></div>
    </form>
  @endif
  @if(isset($release->inspection['deployment_request']))
    @php($deployment = $release->inspection['deployment_request'])
    <p>Workflow status: {{ $deployment['status'] }}{{ isset($deployment['conclusion']) ? ' / '.$deployment['conclusion'] : '' }}</p>
    @if(isset($deployment['url']))
      <a class="button" href="{{ $deployment['url'] }}" target="_blank" rel="noopener noreferrer">View deployment / approve in GitHub</a>
    @endif
    <form method="post" action="{{ route('admin.platform.deployment-status', $release) }}">@csrf<button class="button" type="submit">Refresh deployment status</button></form>
  @elseif($release->status === 'deployment_needs_review')
    <p class="card-help">Review GitHub Actions and deployment history before retrying. The request may have reached GitHub; no success is assumed.</p>
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
