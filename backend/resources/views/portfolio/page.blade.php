<!doctype html>
<html lang="{{ $seo['language'] }}">
<head>
  <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
  @include('portfolio.seo')
  <link rel="icon" href="/favicon.svg" type="image/svg+xml">
  <link rel="stylesheet" href="/styles.css">
  <style>{!! $fontCss !!}
    .custom-page-body{max-width:760px;color:#c9c5d4;font-size:15px;line-height:1.8}
    .custom-page-body h2{margin:38px 0 10px;font-family:var(--heading);font-size:clamp(22px,3vw,30px);letter-spacing:-.03em;color:var(--ink)}
    .custom-page-body h3{margin:26px 0 8px;color:var(--ink)}
    .custom-page-body a{color:var(--accent);text-decoration:underline}
    .custom-page-body img{max-width:100%;height:auto;border-radius:var(--card-radius)}
    .custom-page-body blockquote{margin:0 0 16px;padding:10px 16px;border-left:3px solid var(--violet)}
    .custom-page-body code{font-family:var(--mono);font-size:.9em}
    .page-preview-banner{position:sticky;top:0;z-index:10;padding:8px 16px;background:#e8cf9b;color:#1a1408;font-size:12px;text-align:center}
  </style>
</head>
<body class="project-detail-page" style="--night:{{ $settings->color_background }};--ink:{{ $settings->color_foreground }};--violet:{{ $settings->color_primary }};--accent:{{ $settings->color_accent }};--cyan:{{ $settings->color_cyan }};--sans:{{ $settings->font_body }},system-ui,sans-serif;--heading:{{ $settings->font_heading }},system-ui,sans-serif;--content-width:{{ $settings->max_width }}px;--card-radius:{{ $settings->border_radius }}px">
  @if($preview)<div class="page-preview-banner" role="status">Preview: {{ $page->stateLabel() }}. Visitors {{ $page->isLive() ? 'see this page' : 'cannot see this page yet' }}.</div>@endif
  <main class="section project-detail">
    <a class="button button-quiet" href="{{ route('portfolio.home') }}">← {{ $settings->name ?: 'Home' }}</a>
    <h1>{{ $page->title }}</h1>
    @if($page->summary)<p class="dialog-description">{{ $page->summary }}</p>@endif
    @if($image)<img class="project-featured-image" src="{{ $image['url'] }}" alt="{{ $image['alt'] }}">@endif
    <div class="custom-page-body">{!! $html !!}</div>
  </main>
  @include('portfolio.analytics-consent', ['measurementId' => config('services.google_analytics.measurement_id')])
</body>
</html>
