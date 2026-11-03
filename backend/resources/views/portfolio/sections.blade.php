@foreach($sections as $section)
@if(!in_array($section['key'], \App\Models\PortfolioSection::BUILT_IN_KEYS, true) && $section['is_visible'])
<section id="{{ $section['key'] }}" class="content-section section-shell managed-custom-section" data-custom-section>
  <div class="section-heading"><p class="eyebrow">{{ $section['subtitle'] }}</p><h2>{{ $section['title'] }}</h2><p>{{ $section['body'] }}</p></div>
  @if($section['image'])<img class="section-image" src="{{ $section['image']['url'] }}" alt="{{ $section['image']['alt'] }}" loading="lazy">@endif
  <div class="content-items">
    @foreach($section['items'] as $item)
    <article class="content-item"><h3>{{ $item['title'] }}</h3>@if(!empty($item['subtitle']))<p class="eyebrow">{{ $item['subtitle'] }}</p>@endif<p>{{ $item['body'] ?? '' }}</p></article>
    @endforeach
  </div>
</section>
@endif
@endforeach
