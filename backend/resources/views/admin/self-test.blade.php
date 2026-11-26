@extends('admin.layout')
@section('title', 'Self-test')
@section('breadcrumb', 'Self-test')
@section('content')
@php($pill = ['pass' => ['', 'Passed'], 'warn' => ['warn', 'Warning'], 'fail' => ['off', 'Failed'], 'skip' => ['warn', 'Not tested']])
@php($ranAt = isset($report['ran_at']) ? \Illuminate\Support\Carbon::parse($report['ran_at']) : null)
<div class="page-head">
  <div><p class="eyebrow">STUDIO / DIAGNOSTICS</p><h1>Self-test</h1><p>A full check that everything still works: every public page, link and image, the forms, sitemap, uploads, search, permissions, email settings and the server. It changes nothing on the site. It runs every Monday, or now with the button.</p></div>
  <form method="post" action="{{ route('admin.self-test.run') }}">@csrf<button class="button button-primary" type="submit">Run the self-test now</button></form>
</div>

@if(! $report)
  <section class="panel"><p class="card-help" style="margin:0">No self-test has run yet. Press "Run the self-test now". It takes up to a minute.</p></section>
@else
  <div class="grid stats">
    <article class="stat-card"><div class="stat-top"><span>Failed</span><span class="stat-mark">✕</span></div><div class="stat-number">{{ $report['counts']['fail'] }}</div><div class="stat-caption">Need fixing</div></article>
    <article class="stat-card"><div class="stat-top"><span>Warnings</span><span class="stat-mark">!</span></div><div class="stat-number">{{ $report['counts']['warn'] }}</div><div class="stat-caption">Worth a look</div></article>
    <article class="stat-card"><div class="stat-top"><span>Passed</span><span class="stat-mark">✓</span></div><div class="stat-number">{{ $report['counts']['pass'] }}</div><div class="stat-caption">{{ $report['counts']['skip'] }} not tested</div></article>
    <article class="stat-card"><div class="stat-top"><span>Last run</span><span class="stat-mark">◷</span></div><div class="stat-number" style="font-size:20px">{{ $ranAt->diffForHumans() }}</div><div class="stat-caption">{{ $ranBy ? 'By '.$ranBy : 'Scheduled' }} · {{ $report['seconds'] }} s</div></article>
  </div>

  <nav class="form-inline" aria-label="Filter results" style="flex-wrap:wrap;margin-bottom:12px">
    <a class="button {{ $show === 'problems' ? 'button-primary' : '' }}" href="{{ route('admin.self-test') }}">Problems only</a>
    @foreach($statuses as $key => $label)<a class="button {{ $show === $key ? 'button-primary' : '' }}" href="{{ route('admin.self-test', ['show' => $key]) }}">{{ $label }} · {{ $report['counts'][$key] ?? 0 }}</a>@endforeach
    <a class="button {{ $show === 'all' ? 'button-primary' : '' }}" href="{{ route('admin.self-test', ['show' => 'all']) }}">Everything</a>
  </nav>

  <section class="panel">
    @if($results->isEmpty())
      <p class="card-help" style="margin:0">{{ $show === 'problems' ? 'No problems found. Everything checked passed.' : 'Nothing here.' }}</p>
    @else
      <div class="table-wrap"><table class="data-table">
        <thead><tr><th scope="col">RESULT</th><th scope="col">CHECK</th><th scope="col">WHERE</th><th scope="col">WHAT HAPPENED</th><th scope="col">WHAT TO DO</th></tr></thead>
        <tbody>
          @foreach($results as $result)
            <tr>
              <td><span class="status-pill {{ $pill[$result['status']][0] }}">{{ $pill[$result['status']][1] }}</span><small>{{ ucfirst($result['severity']) }}</small></td>
              <td><strong style="color:#dcd8e6">{{ $result['check'] }}</strong><small>{{ $result['area'] }}</small></td>
              <td>@if($result['location'])<code>{{ $result['location'] }}</code>@else—@endif</td>
              <td>{{ $result['message'] }}</td>
              <td>{{ $result['fix'] ?? '—' }}</td>
            </tr>
          @endforeach
        </tbody>
      </table></div>
    @endif
  </section>
  <p class="card-help" style="margin-top:12px">Failures also raise a notification. For live status between runs, see <a href="{{ route('admin.health') }}" style="color:#b69bff">Health</a>.</p>
@endif
@endsection
