@extends('admin.layout')
@section('title', $query ? 'Search: '.$query : 'Search')
@section('breadcrumb', 'Search')
@section('content')
<div class="page-head">
  <div><p class="eyebrow">STUDIO / FIND</p><h1>Search</h1><p>Find dashboard pages, projects, sections, site settings, skills, media, fonts and contact messages. Press Ctrl K (⌘ K on a Mac) on any page to search without leaving it.</p></div>
</div>
<section class="panel" style="margin-bottom:14px">
  <form method="get" action="{{ route('admin.search') }}" class="form-inline" style="align-items:end" role="search">
    <div class="field" style="flex:1"><label for="search-page-q">Search for</label><input id="search-page-q" name="q" type="search" value="{{ $query }}" maxlength="100" placeholder="Project name, email, alt text, setting…" autofocus></div>
    <div class="field"><label for="search-page-type">In</label><select id="search-page-type" name="type"><option value="">Everything</option>@foreach($types as $value => $label)<option value="{{ $value }}" @selected($type === $value)>{{ $label }}</option>@endforeach</select></div>
    <button class="button button-primary" type="submit">Search</button>
  </form>
</section>

@if(mb_strlen($query) < 2)
  <p class="card-help">Type at least two characters.</p>
@elseif(! $results)
  <section class="panel"><p class="card-help" style="margin:0">Nothing matches "{{ $query }}"@if($type) in {{ $types[$type] }}@endif. Try fewer or different words.</p></section>
@else
  <div class="grid" style="gap:14px">
    @foreach($results as $group => $items)
      <section class="panel" aria-labelledby="results-{{ $group }}">
        <div class="panel-head"><h2 id="results-{{ $group }}">{{ $types[$group] }}</h2>@if(! $type && count($items) >= 8)<a href="{{ route('admin.search', ['q' => $query, 'type' => $group]) }}">Show more →</a>@endif</div>
        <div class="activity-list">
          @foreach($items as $item)
            <a class="activity-row" href="{{ $item['url'] }}" style="text-decoration:none"><span class="activity-dot"></span><div class="activity-copy"><strong>{{ $item['title'] }}</strong><p>{{ $item['detail'] }}</p></div><span class="activity-time">Open →</span></a>
          @endforeach
        </div>
      </section>
    @endforeach
  </div>
@endif
@endsection
