@extends('admin.layout')
@section('title', 'Media library')
@section('breadcrumb', 'Media library')
@section('content')
<div class="page-head">
  <div><p class="eyebrow">PORTFOLIO ASSETS / PRIVATE LIBRARY</p><h1>Media library</h1><p>Upload optimized project imagery and keep its alt text and public URL together.</p></div>
</div>
<section class="editor-card" style="margin-bottom:17px">
  <h2>Upload an image</h2>
  <p class="card-help">JPG, PNG, or WebP up to 5 MB. Images are stored on the configured public disk.</p>
  <form class="upload-form" method="post" action="{{ route('admin.media.store') }}" enctype="multipart/form-data">
    @csrf
    <div class="field"><label for="media-file">Image file</label><input id="media-file" name="file" type="file" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp" required></div>
    <div class="field"><label for="media-label">Library label</label><input id="media-label" name="label" maxlength="120" required></div>
    <div class="field"><label for="media-alt">Alt text</label><input id="media-alt" name="alt_text" maxlength="255"></div>
    <button class="button button-primary" type="submit">Upload image</button>
  </form>
</section>
<div class="panel" style="margin-bottom:14px"><div class="panel-head" style="margin:0"><h2>{{ $assets->total() }} assets</h2><span style="color:#858294;font-size:9px">{{ number_format($storageBytes / 1048576, 2) }} MB total</span></div></div>
@if($assets->isEmpty())
  <section class="panel"><p style="margin:0;color:#9793a8;font-size:10px">No media uploaded yet. Images added here will be served from the configured public storage disk.</p></section>
@else
  <div class="grid media-grid">
    @foreach($assets as $asset)
      <article class="media-card">
        <a href="{{ Storage::disk($asset->disk)->url($asset->path) }}" target="_blank" rel="noreferrer" aria-label="Open {{ $asset->label }}"><img src="{{ Storage::disk($asset->disk)->url($asset->path) }}" alt="{{ $asset->alt_text ?? '' }}" loading="lazy"></a>
        <div class="media-meta">
          <strong title="{{ $asset->label }}">{{ $asset->label }}</strong>
          <small>{{ $asset->width }} × {{ $asset->height }} px · {{ number_format($asset->size_bytes / 1024) }} KB</small>
          <div class="field" style="margin-top:9px"><label for="media-url-{{ $asset->id }}">Public URL</label><input id="media-url-{{ $asset->id }}" readonly value="{{ Storage::disk($asset->disk)->url($asset->path) }}" onclick="this.select()"></div>
          @if($asset->alt_text)<small>{{ $asset->alt_text }}</small>@endif
          <form method="post" action="{{ route('admin.media.destroy', $asset) }}" style="margin-top:10px" data-confirm="Delete {{ $asset->label }}? Any page using this file will no longer be able to load it.">
            @csrf
            @method('delete')
            <button class="button button-danger" type="submit">Delete image</button>
          </form>
        </div>
      </article>
    @endforeach
  </div>
  @if($assets->hasPages())<div class="pagination">{{ $assets->links() }}</div>@endif
@endif
@endsection
