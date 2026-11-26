@extends('admin.layout')
@section('title', $help->title($topic).' · Help')
@section('breadcrumb', 'Help / '.$help->title($topic))
@section('content')
<div class="page-head">
  <div><p class="eyebrow"><a href="{{ route('admin.help.index') }}" style="color:inherit">HELP CENTRE</a> / {{ mb_strtoupper(\App\Services\HelpCentre::TOPICS[$topic][2]) }}</p><h1>{{ $help->title($topic) }}</h1><p>{{ $help->summary($topic) }}</p></div>
  @if($page)<a class="button button-primary" href="{{ route($page) }}">Open {{ $help->title($topic) }} →</a>@endif
</div>

<div class="help-layout">
  <article class="editor-card help-article">{!! $article['html'] !!}</article>
  <aside class="help-aside">
    @if(count($article['headings']) > 1)
      <nav class="panel" aria-label="On this page">
        <p class="nav-label" style="margin:0 0 8px">ON THIS PAGE</p>
        @foreach($article['headings'] as $heading)<a href="#{{ $heading['id'] }}">{{ $heading['text'] }}</a>@endforeach
      </nav>
    @endif
    <nav class="panel" aria-label="More help">
      <p class="nav-label" style="margin:0 0 8px">MORE HELP</p>
      @if($previous)<a href="{{ route('admin.help.show', $previous) }}">← {{ $help->title($previous) }}</a>@endif
      @if($next)<a href="{{ route('admin.help.show', $next) }}">{{ $help->title($next) }} →</a>@endif
      <a href="{{ route('admin.help.index') }}">All help articles</a>
    </nav>
  </aside>
</div>

<style>
  .help-layout{display:grid;grid-template-columns:minmax(0,1fr) 240px;align-items:start;gap:16px}
  .help-aside{position:sticky;top:82px;display:grid;gap:12px}
  .help-aside nav{display:grid;gap:7px;padding:14px}
  .help-aside a{color:#b69bff;font-size:10px}
  .help-article{padding:22px 26px;color:#c9c5d4;font-size:11px;line-height:1.85;max-width:780px}
  .help-article h2{margin:26px 0 8px;color:#ece7f6;font-size:14px;font-weight:600;scroll-margin-top:80px}
  .help-article h2:first-child{margin-top:0}
  .help-article h3{margin:18px 0 6px;color:#dcd8e6;font-size:12px}
  .help-article p,.help-article ul,.help-article ol{margin:0 0 12px}
  .help-article ul,.help-article ol{padding-left:20px}
  .help-article li{margin:3px 0}
  .help-article a{color:#b69bff;text-decoration:underline}
  .help-article strong{color:#ece7f6}
  .help-article code{padding:1px 5px;border:1px solid var(--line);border-radius:4px;background:#0d0e18;color:#e5e1ed;font-family:var(--mono);font-size:10px}
  .help-article pre{overflow:auto;padding:10px 12px;border:1px solid var(--line);border-radius:6px;background:#0d0e18}
  .help-article pre code{padding:0;border:0}
  .help-article blockquote{margin:0 0 12px;padding:9px 13px;border-left:3px solid #e8cf9b;border-radius:0 6px 6px 0;background:rgba(176,138,62,.08);color:#ecdcb8}
  .help-article blockquote p{margin:0}
  .help-article table{width:100%;margin:0 0 12px;border-collapse:collapse}
  .help-article th,.help-article td{padding:7px 8px;border-bottom:1px solid rgba(255,255,255,.07);text-align:left;vertical-align:top}
  @media(max-width:900px){.help-layout{grid-template-columns:1fr}.help-aside{position:static}}
</style>
@endsection
