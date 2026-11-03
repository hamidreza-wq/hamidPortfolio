@extends('admin.layout')
@section('title', 'Media library')
@section('breadcrumb', 'Media library')
@section('content')
<div class="page-head">
  <div><p class="eyebrow">PORTFOLIO ASSETS / PRIVATE LIBRARY</p><h1>Media library</h1><p>Upload optimized project imagery and keep its alt text and public URL together.</p></div>
</div>
<section class="editor-card" style="margin-bottom:17px">
  <h2>Upload an image</h2>
  <p class="card-help">JPG, PNG, or WebP up to 5 MB; 8192 px per side and 20 million pixels maximum. PHP GD is required to decode and re-encode uploads safely.</p>
  <form class="upload-form" method="post" action="{{ route('admin.media.store') }}" enctype="multipart/form-data">
    @csrf
    <div class="field"><label for="media-file">Image file</label><input id="media-file" name="file" type="file" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp" required></div>
    <div class="field"><label for="media-label">Library label</label><input id="media-label" name="label" maxlength="120" required></div>
    <div class="field"><label for="media-alt">Alt text</label><input id="media-alt" name="alt_text" maxlength="255"></div>
    <button class="button button-primary" type="submit">Upload image</button>
  </form>
</section>
<form method="get" action="{{ route('admin.media.index') }}" class="panel" style="margin-bottom:14px"><div class="field"><label for="media-search">Search label, filename or alt text</label><input id="media-search" name="q" maxlength="120" value="{{ $search }}"></div><button class="button">Search</button></form>
<div class="panel" style="margin-bottom:14px"><div class="panel-head" style="margin:0"><h2>{{ $assets->total() }} assets</h2><span style="color:#858294;font-size:9px">{{ number_format($storageBytes / 1048576, 2) }} MB total</span></div></div>
@if($assets->isEmpty())
  <section class="panel"><p style="margin:0;color:#9793a8;font-size:10px">No media uploaded yet. Images added here will be served from the configured public storage disk.</p></section>
@else
  <div class="grid media-grid">
    @foreach($assets as $asset)
      <article class="media-card">
        <a href="{{ $asset->publicUrl() }}" target="_blank" rel="noreferrer" aria-label="Open {{ $asset->label }}"><img src="{{ $asset->publicUrl() }}" alt="{{ $asset->alt_text ?? '' }}" loading="lazy"></a>
        <div class="media-meta">
          <strong title="{{ $asset->label }}">{{ $asset->label }}</strong>
          <small>{{ $asset->width }} × {{ $asset->height }} px · {{ number_format($asset->size_bytes / 1024) }} KB</small>
          <div class="field" style="margin-top:9px"><label for="media-url-{{ $asset->id }}">Stable public URL</label><input id="media-url-{{ $asset->id }}" readonly value="{{ $asset->publicUrl() }}"></div>
          @if($asset->alt_text)<small>{{ $asset->alt_text }}</small>@endif
          <small>{{ $asset->reencoded ? 'Re-encoded' : 'Signature checked; re-encoding unavailable' }}</small>
          <p>Usage: {{ implode(', ', $usage[$asset->id]) ?: 'Unused' }}</p>
          <form method="post" action="{{ route('admin.media.update', $asset) }}" enctype="multipart/form-data">
            @csrf @method('put')
            <div class="field"><label for="edit-label-{{ $asset->id }}">Label</label><input id="edit-label-{{ $asset->id }}" name="label" value="{{ $asset->label }}" maxlength="120" required></div>
            <div class="field"><label for="edit-alt-{{ $asset->id }}">Alt text</label><input id="edit-alt-{{ $asset->id }}" name="alt_text" value="{{ $asset->alt_text }}" maxlength="255"></div>
            <div class="field"><label for="replace-{{ $asset->id }}">Replace image (optional)</label><input id="replace-{{ $asset->id }}" type="file" name="file" accept=".jpg,.jpeg,.png,.webp"></div>
            <button class="button">Save image</button>
          </form>
          <form method="post" action="{{ route('admin.media.destroy', $asset) }}" style="margin-top:10px" data-confirm="Permanently delete this unused image?">
            @csrf
            @method('delete')
            <button class="button button-danger" type="submit" @disabled(count($usage[$asset->id]))>Delete unused image</button>
          </form>
        </div>
      </article>
    @endforeach
  </div>
  @if($assets->hasPages())<div class="pagination">{{ $assets->links() }}</div>@endif
@endif
@endsection
