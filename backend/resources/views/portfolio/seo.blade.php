<title>{{ $seo['title'] }}</title>
<meta name="description" content="{{ $seo['description'] }}">
<meta name="robots" content="{{ $seo['robots'] }}">
@if($seo['indexable'])
<meta name="googlebot" content="max-image-preview:large, max-snippet:-1, max-video-preview:-1">
@endif
@if($seo['author'])
<meta name="author" content="{{ $seo['author'] }}">
@endif
<link rel="canonical" href="{{ $seo['canonical'] }}">
<meta property="og:type" content="website">
<meta property="og:site_name" content="{{ $seo['siteName'] }}">
<meta property="og:title" content="{{ $seo['ogTitle'] }}">
<meta property="og:description" content="{{ $seo['ogDescription'] }}">
<meta property="og:url" content="{{ $seo['canonical'] }}">
<meta property="og:locale" content="{{ str_replace('-', '_', $seo['language']) }}">
<meta name="twitter:card" content="{{ $seo['image'] ? 'summary_large_image' : 'summary' }}">
<meta name="twitter:title" content="{{ $seo['ogTitle'] }}">
<meta name="twitter:description" content="{{ $seo['ogDescription'] }}">
@if($seo['image'])
<meta property="og:image" content="{{ $seo['image'] }}">
<meta property="og:image:alt" content="{{ $seo['imageAlt'] }}">
@if($seo['imageWidth'] && $seo['imageHeight'])
<meta property="og:image:width" content="{{ $seo['imageWidth'] }}">
<meta property="og:image:height" content="{{ $seo['imageHeight'] }}">
@endif
<meta name="twitter:image" content="{{ $seo['image'] }}">
<meta name="twitter:image:alt" content="{{ $seo['imageAlt'] }}">
@endif
<script type="application/ld+json">{!! json_encode($seo['structured'], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) !!}</script>
