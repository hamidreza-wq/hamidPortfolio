@extends('admin.layout')
@section('title', 'Help')
@section('breadcrumb', 'Help')
@section('content')
<div class="page-head">
  <div><p class="eyebrow">STUDIO / HELP CENTRE</p><h1>Help</h1><p>How each part of the dashboard works, step by step, with the things worth knowing before you press a button. Every page also has a Help link in the top bar that opens its article.</p></div>
</div>

<section class="panel" style="margin-bottom:14px">
  <form method="get" action="{{ route('admin.help.index') }}" class="form-inline" style="align-items:end" role="search">
    <div class="field" style="flex:1"><label for="help-search">Search the help</label><input id="help-search" name="q" type="search" value="{{ $query }}" maxlength="100" placeholder="For example: restore, two-factor, alt text"></div>
    <button class="button button-primary" type="submit">Search</button>
    @if($query !== '')<a class="button" href="{{ route('admin.help.index') }}">Clear</a>@endif
  </form>
</section>

@if($results !== null)
  <section class="panel" style="margin-bottom:14px" aria-labelledby="help-results">
    <div class="panel-head"><h2 id="help-results">{{ count($results) }} {{ count($results) === 1 ? 'article' : 'articles' }} mention "{{ $query }}"</h2></div>
    @forelse($results as $result)
      <a class="activity-row" href="{{ route('admin.help.show', $result['slug']) }}"><span class="activity-dot"></span><div class="activity-copy"><strong>{{ $result['title'] }}</strong><p>{{ $result['excerpt'] }}</p></div><span class="activity-time">Read →</span></a>
    @empty
      <p class="card-help" style="margin:0">Nothing found. Try a shorter or different word.</p>
    @endforelse
  </section>
@endif

<div class="grid" style="grid-template-columns:repeat(auto-fit,minmax(min(100%,320px),1fr));gap:14px">
  @foreach($help->groups() as $group => $slugs)
    <section class="panel" aria-labelledby="help-group-{{ \Illuminate\Support\Str::slug($group) }}">
      <div class="panel-head"><h2 id="help-group-{{ \Illuminate\Support\Str::slug($group) }}">{{ $group }}</h2></div>
      <div class="activity-list">
        @foreach($slugs as $slug)
          <a class="activity-row" href="{{ route('admin.help.show', $slug) }}"><div class="activity-copy"><strong>{{ $help->title($slug) }}</strong><p>{{ $help->summary($slug) }}</p></div></a>
        @endforeach
      </div>
    </section>
  @endforeach
</div>
@endsection
