<!doctype html>
<html lang="{{ $seo['language'] }}">
<head>
  <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
  @include('portfolio.seo')
  <link rel="icon" href="/favicon.svg" type="image/svg+xml">
  <link rel="stylesheet" href="/styles.css">
  <style>{!! $fontCss !!}</style>
</head>
<body class="project-detail-page" style="--night:{{ $settings->color_background }};--ink:{{ $settings->color_foreground }};--violet:{{ $settings->color_primary }};--accent:{{ $settings->color_accent }};--cyan:{{ $settings->color_cyan }};--sans:{{ $settings->font_body }},system-ui,sans-serif;--heading:{{ $settings->font_heading }},system-ui,sans-serif;--content-width:{{ $settings->max_width }}px;--card-radius:{{ $settings->border_radius }}px">
  <main class="section project-detail">
    <a class="button button-quiet" href="{{ route('portfolio.home') }}#work">← All projects</a>
    <p class="eyebrow">{{ $project->category_label }} · {{ $project->status }}{{ $project->is_featured ? ' · Featured' : '' }}</p>
    <h1>{{ $project->name }}</h1>
    <p class="dialog-description">{{ $project->description }}</p>
    @if($image)<img class="project-featured-image" src="{{ $image['url'] }}" alt="{{ $image['alt'] }}">@endif
    <div class="project-long-description">{{ $project->details }}</div>
    @if($project->rights_confirmed_at && $project->author_name)<p>Author: {{ $project->author_name }}</p>@endif
    @if($project->maintainer_name)<p>Maintainer: {{ $project->maintainer_name }}</p>@endif
    @if($project->license_name)<p>Repository licence: {{ $project->license_name }}. See the repository for the full licence and notices.</p>@endif
    @if($project->attribution)<p class="project-long-description">{{ $project->attribution }}</p>@endif
    @if($project->rights_confirmed_at && $project->copyright_holder)<p>Copyright holder: {{ $project->copyright_holder }}. Existing licences and contributor rights remain in effect.</p>@endif
    <h2>Built with</h2>
    <ul class="dialog-tags">@foreach($project->technologies ?? [] as $technology)<li>{{ $technology }}</li>@endforeach</ul>
    <h2>Inside the project</h2>
    <ul class="dialog-features">@foreach($project->features ?? [] as $feature)<li>{{ $feature }}</li>@endforeach</ul>
    @if($gallery->isNotEmpty())<div class="project-gallery">@foreach($gallery as $media)<img src="{{ $media['url'] }}" alt="{{ $media['alt'] }}" loading="lazy">@endforeach</div>@endif
    <div class="contact-actions">
      @if($project->repository_is_public)
      <a class="button button-primary" href="{{ $project->repository_url }}" target="_blank" rel="noopener noreferrer">View repository ↗</a>
      @else
      <span class="button button-quiet">Private source code</span>
      @endif
      @if($project->live_url && $project->live_url_verified)<a class="button button-quiet" href="{{ $project->live_url }}" target="_blank" rel="noopener noreferrer">Live demo ↗</a>@endif
    </div>
  </main>
  @include('portfolio.analytics-consent', ['measurementId' => $analyticsMeasurementId])
</body>
</html>
