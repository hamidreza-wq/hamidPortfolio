@extends('admin.layout')
@section('title', 'Font library')
@section('breadcrumb', 'Font library')
@section('content')
<div class="page-head"><div><p class="eyebrow">PORTFOLIO ASSETS / TYPOGRAPHY</p><h1>Font library</h1><p>Upload licensed fonts, then choose them for headings or body text in the site editor.</p></div></div>
<section class="editor-card" style="margin-bottom:17px">
  <h2>Upload a font</h2>
  <p class="card-help">WOFF2, WOFF, TTF, or OTF, up to 2 MB. Use web-licensed files from a trusted source. Uploading confirms your right to publish and embed the font.</p>
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
</section>
<div class="grid">
@forelse($assets as $asset)
  <article class="editor-card">
    <h2>{{ $asset->family }}</h2>
    <p>{{ strtoupper($asset->format) }} · {{ $asset->weight }} {{ $asset->style }} · {{ number_format($asset->size_bytes / 1024) }} KB · {{ $asset->is_active ? 'Active' : 'Inactive' }}</p>
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
  <section class="panel"><p>No uploaded fonts yet.</p></section>
@endforelse
</div>
@if($assets->hasPages())<div class="pagination">{{ $assets->links() }}</div>@endif
@endsection
