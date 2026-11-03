@extends('admin.layout')
@section('title', 'GitHub projects')
@section('breadcrumb', 'GitHub projects')
@section('content')
<div class="page-head"><div><p class="eyebrow">PORTFOLIO / DISCOVERY</p><h1>GitHub projects</h1><p>Automatic public discovery, manual editorial review. Nothing is silently published.</p></div></div>
<section class="panel platform-card">
  <h2>hamidreza-wq / public read-only connection</h2>
  <p>Discovery uses GitHub's official anonymous public API. No credentials are requested or stored. Private repositories are disabled.</p>
  <p class="card-help">Account login and immutable owner ID are checked for every repository. This verifies API identity, not your personal name, authorship or copyright. Forks and archived repositories are excluded. README content is not fetched or executed; features are never invented.</p>
  @include('admin.platform.check', ['kind' => 'github', 'label' => 'Scan public repositories'])
  <p class="card-help">Automatic discovery is enabled: {{ config('platform.schedule.github') }} (UTC), once the scheduler is installed. Renames update the review metadata only, never your edited project.</p>
  <form method="get" class="field-grid"><div class="field"><label for="github-search">Search repository names</label><input id="github-search" name="q" maxlength="100" value="{{ $search }}"></div><div><button class="button" type="submit">Search</button></div></form>
</section>
@forelse($repositories as $repo)
<section class="panel platform-card">
  <div class="panel-head"><h2>{{ $repo->full_name }}</h2><span class="status-pill {{ in_array($repo->state, ['excluded', 'rejected', 'unavailable']) ? 'off' : '' }}">{{ $repo->state }}</span></div>
  <p>{{ $repo->metadata['description'] }}</p>
  <p class="card-help">Repository ID {{ $repo->id }} / licence {{ $repo->metadata['license'] ?: 'Not detected: review the repository' }} / {{ $repo->metadata['language'] ?: 'Language not detected' }}. Account ownership does not confer ownership of contributors' work.</p>
  <a class="button" href="{{ $repo->metadata['url'] }}" target="_blank" rel="noopener noreferrer">Inspect source repository</a>
  @if($repo->project)<a class="button" href="{{ route('admin.projects.index', ['q' => $repo->project->slug]) }}">Edit project / attribution / SEO</a>@endif
  <details><summary>Discovered metadata / previous snapshot</summary><pre>{{ json_encode(['current' => $repo->metadata, 'previous' => $repo->previous_metadata], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre></details>
  <form method="post" action="{{ route('admin.platform.review', $repo) }}" class="form-inline">
    @csrf
    <label class="checkbox-row"><input type="checkbox" name="confirm" value="1" required>I reviewed source, licence and attribution; publishing is a separate editor action.</label>
    @if($repo->project_id && !in_array($repo->state, ['unavailable', 'excluded']) && !$repo->metadata['fork'] && !$repo->metadata['archived'])<button class="button" name="decision" value="approve" type="submit">Approve review</button>@endif
    <button class="button button-danger" name="decision" value="reject" type="submit">Reject candidate</button>
  </form>
</section>
@empty
  <section class="panel platform-card"><p>No repositories match. Run a scan and inspect its history for errors.</p></section>
@endforelse
{{ $repositories->links() }}
@include('admin.platform.history')
@endsection
