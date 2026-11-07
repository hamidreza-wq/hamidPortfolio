{!! '<'.'?xml version="1.0" encoding="UTF-8"?'.'>' !!}
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
@if($settings->indexing_enabled && !$settings->maintenance_mode)
<url><loc>{{ $baseUrl }}/</loc><lastmod>{{ $settings->updated_at->toAtomString() }}</lastmod></url>
@foreach($projects as $project)
<url><loc>{{ $baseUrl }}/projects/{{ $project->slug }}</loc><lastmod>{{ $project->updated_at->toAtomString() }}</lastmod></url>
@endforeach
@endif
</urlset>
