@extends('admin.layout')
@section('title', 'Font library')
@section('breadcrumb', 'Font library')
@section('content')
<style>{!! $fontCss !!}
  .font-sample{margin:10px 0;color:#ece7f6;font-size:22px;line-height:1.3;overflow-wrap:anywhere}
  .google-results{display:grid;grid-template-columns:repeat(auto-fill,minmax(min(100%,300px),1fr));gap:10px;margin-top:12px}
  .google-result{padding:12px;border:1px solid rgba(195,183,240,.12);border-radius:8px;background:rgba(255,255,255,.02)}
  .weight-picks{display:flex;flex-wrap:wrap;gap:4px 12px;margin:8px 0}
</style>
<div class="page-head"><div><p class="eyebrow">PORTFOLIO ASSETS / TYPOGRAPHY</p><h1>Font library</h1><p>Add fonts from Google Fonts or upload your own, then choose them for headings or body text in the <a href="{{ route('admin.site-editor.edit') }}" style="color:#b69bff">live editor</a> or a <a href="{{ route('admin.themes.index') }}" style="color:#b69bff">theme</a>.</p></div></div>

<section class="editor-card" style="margin-bottom:17px" aria-labelledby="google-heading">
  <h2 id="google-heading">Add from Google Fonts</h2>
  <p class="card-help">The font file is copied onto this site, so visitors' browsers never contact Google. That's the privacy-friendly way to use Google Fonts. Every Google font has an open licence that allows use on websites.
    @unless($google->hasApiKey()) Showing popular families. To search all of Google Fonts, add a free <code>GOOGLE_FONTS_API_KEY</code> on the server, or type any family name exactly as Google Fonts shows it.@endunless</p>
  <form method="get" action="{{ route('admin.fonts.index') }}" class="form-inline" style="align-items:end" role="search">
    <div class="field" style="flex:1"><label for="google-search">Find a font</label><input id="google-search" name="google" type="search" value="{{ $googleQuery }}" maxlength="60" placeholder="For example: Playfair, Mono, Sans"></div>
    <button class="button" type="submit">Search</button>
  </form>

  @php($exactMatch = collect($googleResults)->contains(fn ($font) => strcasecmp($font['family'], $googleQuery) === 0))
  <div class="google-results">
    @if($googleQuery !== '' && ! $exactMatch)
      @include('admin.partials.google-font-import', ['family' => $googleQuery, 'category' => 'Import by exact name'])
    @endif
    @foreach($googleResults as $font)
      @include('admin.partials.google-font-import', ['family' => $font['family'], 'category' => $font['category']])
    @endforeach
  </div>
  @if(! $googleResults && $googleQuery === '')<p class="card-help">No fonts to show.</p>@endif
</section>

<details class="editor-card" style="margin-bottom:17px">
  <summary style="cursor:pointer"><strong>Upload a font file instead</strong></summary>
  <p class="card-help" style="margin-top:10px">WOFF2, WOFF, TTF, or OTF, up to 2 MB. Use web-licensed files from a trusted source. Uploading confirms your right to publish and embed the font.</p>
  <form method="post" action="{{ route('admin.fonts.store') }}" enctype="multipart/form-data">
    @csrf
    <div class="field"><label for="font-file">Font file</label><input id="font-file" type="file" name="file" accept=".woff2,.woff,.ttf,.otf" required></div>
    <div class="field"><label for="font-family">Family label</label><input id="font-family" name="family" maxlength="120" value="{{ old('family') }}" required></div>
    <div class="field"><label for="font-weight">Weight</label><select id="font-weight" name="weight">@foreach(range(100,900,100) as $weight)<option value="{{ $weight }}" @selected((int)old('weight',400) === $weight)>{{ $weight }}</option>@endforeach</select></div>
    <div class="field"><label for="font-style">Style</label><select id="font-style" name="style">@foreach(['normal','italic','oblique'] as $style)<option value="{{ $style }}" @selected(old('style') === $style)>{{ $style }}</option>@endforeach</select></div>
    <div class="field"><label for="font-source">Source URL</label><input id="font-source" name="source_url" type="url" maxlength="2048" value="{{ old('source_url') }}" required></div>
    <div class="field"><label for="font-license">License / permission details</label><input id="font-license" name="license_note" maxlength="255" value="{{ old('license_note') }}" required></div>
    <label><input type="checkbox" name="license_confirmed" value="1" required> I have the rights to upload, redistribute and web-embed this font.</label>
    <p><button class="button button-primary">Upload and activate</button></p>
  </form>
</details>

<h2 style="font-size:13px;margin:0 0 10px">Your fonts</h2>
<div class="grid">
@forelse($assets as $asset)
  <article class="editor-card">
    <h2>{{ $asset->family }} <span style="color:#858294;font-weight:400">· {{ $asset->weight }} {{ $asset->style }}</span></h2>
    @if($asset->is_active && $asset->license_confirmed)
      <p class="font-sample" style="font-family:'{{ $asset->cssFamily() }}',system-ui;font-weight:{{ (int) $asset->weight }};font-style:{{ $asset->style === 'italic' ? 'italic' : 'normal' }}">The quick brown fox jumps over the lazy dog · 0123456789</p>
    @endif
    <p>{{ strtoupper($asset->format) }} · {{ number_format($asset->size_bytes / 1024) }} KB · {{ $asset->is_active ? 'Active' : 'Inactive' }}</p>
    <p>{{ $asset->license_note }}</p>
    @if($asset->source_url)<a href="{{ $asset->source_url }}" target="_blank" rel="noopener noreferrer">License source</a>@endif
    <p>Usage: {{ implode(', ', $usage[$asset->id]) ?: 'Unused' }}</p>
    <form method="post" action="{{ route('admin.fonts.update', $asset) }}">
      @csrf @method('put')
      <input type="hidden" name="is_active" value="{{ $asset->is_active ? 0 : 1 }}">
      <button class="button" @disabled($asset->is_active && count($usage[$asset->id]))>{{ $asset->is_active ? 'Deactivate' : 'Activate' }}</button>
    </form>
    <form method="post" action="{{ route('admin.fonts.destroy', $asset) }}" data-confirm="Remove this unused font permanently?">
      @csrf @method('delete')
      <button class="button button-danger" @disabled(count($usage[$asset->id]))>Remove unused font</button>
    </form>
  </article>
@empty
  <section class="panel"><p>No fonts yet. Add one from Google Fonts above.</p></section>
@endforelse
</div>
@if($assets->hasPages())<div class="pagination">{{ $assets->links() }}</div>@endif
@endsection
