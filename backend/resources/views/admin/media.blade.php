@extends('admin.layout')
@section('title', 'Media library')
@section('breadcrumb', 'Media library')
@section('content')
@php($active = array_filter($filters))
<div class="page-head">
  <div><p class="eyebrow">PORTFOLIO ASSETS / PRIVATE LIBRARY</p><h1>Media library</h1><p>Upload optimized project imagery and keep its alt text and public URL together.</p></div>
</div>

<section class="editor-card" style="margin-bottom:17px" aria-labelledby="upload-heading">
  <h2 id="upload-heading">Upload images</h2>
  <p class="card-help">JPG, PNG or WebP, up to 5 MB each and 10 at a time; at most 8192 px per side and 20 million pixels. Every image is decoded and re-encoded with PHP GD, which also strips hidden metadata such as camera location.</p>
  <form class="media-dropzone" id="media-dropzone" method="post" action="{{ route('admin.media.store') }}" enctype="multipart/form-data">
    @csrf
    <p class="media-dropzone-title"><strong>Drop images here</strong> or choose them</p>
    <div class="form-inline" style="align-items:end;justify-content:center;flex-wrap:wrap">
      <div class="field"><label for="media-files">Image files</label><input id="media-files" name="files[]" type="file" multiple accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp" required></div>
      <div class="field" style="min-width:220px"><label for="media-alt">Alt text for all (optional)</label><input id="media-alt" name="alt_text" maxlength="255" placeholder="Best added per image afterwards"></div>
      <button class="button button-primary" type="submit">Upload</button>
    </div>
    <p class="field-hint" style="margin:8px 0 0">Labels come from the file names; change them on each card. Images uploaded without alt text show under "Missing alt text".</p>
    <ul class="media-upload-progress" id="media-upload-progress" aria-live="polite"></ul>
  </form>
</section>

<form method="get" action="{{ route('admin.media.index') }}" class="panel media-filters" style="margin-bottom:14px" role="search">
  <div class="field" style="flex:1;min-width:200px"><label for="media-search">Search label, filename or alt text</label><input id="media-search" name="q" type="search" maxlength="120" value="{{ $search }}"></div>
  <div class="field"><label for="media-usage">Usage</label><select id="media-usage" name="usage"><option value="">All images</option><option value="used" @selected(($filters['usage'] ?? '') === 'used')>Used on the site</option><option value="unused" @selected(($filters['usage'] ?? '') === 'unused')>Unused</option></select></div>
  <div class="field"><label for="media-alt-filter">Alt text</label><select id="media-alt-filter" name="alt"><option value="">Any</option><option value="missing" @selected(($filters['alt'] ?? '') === 'missing')>Missing alt text ({{ $missingAlt }})</option></select></div>
  <div class="field"><label for="media-type">Type</label><select id="media-type" name="type"><option value="">Any type</option>@foreach($types as $mime => $name)<option value="{{ $mime }}" @selected(($filters['type'] ?? '') === $mime)>{{ $name }}</option>@endforeach</select></div>
  <div class="field"><label for="media-sort">Sort</label><select id="media-sort" name="sort">@foreach($sorts as $value => $name)<option value="{{ $value }}" @selected(($filters['sort'] ?? 'newest') === $value)>{{ $name }}</option>@endforeach</select></div>
  <button class="button" type="submit">Apply</button>
  @if($active)<a class="button" href="{{ route('admin.media.index') }}">Clear</a>@endif
</form>

<div class="panel" style="margin-bottom:14px"><div class="panel-head" style="margin:0"><h2>{{ $assets->total() }} {{ $active ? 'matching' : '' }} {{ $assets->total() === 1 ? 'image' : 'images' }}@if($active) <span style="color:#858294;font-weight:400">of {{ $totalCount }}</span>@endif</h2><span style="color:#858294;font-size:9px">{{ number_format($storageBytes / 1048576, 2) }} MB total</span></div></div>

@if($assets->isEmpty())
  <section class="panel"><p style="margin:0;color:#9793a8;font-size:10px">{{ $active ? 'No images match these filters.' : 'No media uploaded yet. Images added here will be served from the configured public storage disk.' }}</p></section>
@else
  <div class="grid media-grid">
    @foreach($assets as $asset)
      <article class="media-card" id="media-{{ $asset->id }}">
        <a href="{{ $asset->publicUrl() }}" target="_blank" rel="noreferrer" aria-label="Open {{ $asset->label }} full size"><img src="{{ $asset->publicUrl() }}" alt="{{ $asset->alt_text ?? '' }}" loading="lazy" width="{{ $asset->width }}" height="{{ $asset->height }}"></a>
        <div class="media-meta">
          <strong title="{{ $asset->label }}">{{ $asset->label }}</strong>
          <small>{{ $types[$asset->mime_type] ?? $asset->mime_type }} · {{ $asset->width }} × {{ $asset->height }} px · {{ number_format($asset->size_bytes / 1024) }} KB</small>
          <small>Added {{ $asset->created_at?->format('j M Y') }}@if($asset->uploader) by {{ $asset->uploader->name }}@endif · {{ $asset->reencoded ? 're-encoded' : 'signature checked; re-encoding unavailable' }}</small>
          @if(! $asset->alt_text)<small><span class="status-pill warn">Missing alt text</span></small>@endif
          <div class="field" style="margin-top:9px"><label for="media-url-{{ $asset->id }}">Stable public URL</label><input id="media-url-{{ $asset->id }}" readonly value="{{ $asset->publicUrl() }}"></div>
          <div class="form-inline" style="margin-top:7px;flex-wrap:wrap">
            <button class="button" type="button" data-copy="#media-url-{{ $asset->id }}">Copy URL</button>
            <a class="button" href="{{ route('admin.media.download', $asset) }}">Download</a>
          </div>
          <p>Usage: {{ implode(', ', $usage[$asset->id]) ?: 'Unused' }}</p>
          <form method="post" action="{{ route('admin.media.update', $asset) }}" enctype="multipart/form-data">
            @csrf @method('put')
            <div class="field"><label for="edit-label-{{ $asset->id }}">Label</label><input id="edit-label-{{ $asset->id }}" name="label" value="{{ $asset->label }}" maxlength="120" required></div>
            <div class="field"><label for="edit-alt-{{ $asset->id }}">Alt text</label><input id="edit-alt-{{ $asset->id }}" name="alt_text" value="{{ $asset->alt_text }}" maxlength="255" placeholder="Describe what the image shows"></div>
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

<style>
  .media-dropzone{padding:18px;border:1px dashed rgba(196,180,244,.3);border-radius:8px;text-align:center;transition:border-color .15s ease,background .15s ease}
  .media-dropzone.dragging{border-color:#b69bff;background:rgba(180,153,255,.07)}
  .media-dropzone-title{margin:0 0 12px;color:#c3bfce;font-size:11px}
  .media-upload-progress{display:grid;gap:4px;margin:10px 0 0;padding:0;list-style:none;text-align:left;font-size:9px;color:#c3bfce}
  .media-upload-progress .failed{color:#f0b5b8}
  .media-filters{display:flex;flex-wrap:wrap;align-items:end;gap:9px}
</style>
<script>
  (() => {
    document.querySelectorAll('[data-copy]').forEach((button) => button.addEventListener('click', async () => {
      const input = document.querySelector(button.dataset.copy);
      if (!input) return;
      try {
        await navigator.clipboard.writeText(input.value);
      } catch {
        input.select();
        document.execCommand('copy');
      }
      const label = button.textContent;
      button.textContent = 'Copied';
      setTimeout(() => { button.textContent = label; }, 1500);
    }));

    const zone = document.querySelector('#media-dropzone');
    if (!zone || !window.fetch || !window.FormData) return;
    const input = zone.querySelector('#media-files');
    const progress = zone.querySelector('#media-upload-progress');
    const token = zone.querySelector('input[name="_token"]').value;
    const alt = zone.querySelector('#media-alt');

    // One request per file, so a big batch never hits the server's request size limit.
    const upload = async (files) => {
      const list = [...files].slice(0, 10);
      if (!list.length) return;
      progress.replaceChildren();
      let uploaded = 0;
      for (const file of list) {
        const row = Object.assign(document.createElement('li'), { textContent: file.name + ': uploading…' });
        progress.append(row);
        const body = new FormData();
        body.append('_token', token);
        body.append('files[]', file);
        if (alt.value.trim()) body.append('alt_text', alt.value.trim());
        try {
          const response = await fetch(zone.action, { method: 'POST', body, headers: { Accept: 'application/json' }, credentials: 'same-origin' });
          const data = await response.json().catch(() => ({}));
          if (response.ok && data.uploaded?.length) {
            uploaded++;
            row.textContent = file.name + ': uploaded';
          } else {
            row.className = 'failed';
            row.textContent = file.name + ': ' + (data.failed?.[0]?.error ?? data.message ?? (response.status === 413 ? 'too large for the server' : 'upload failed'));
          }
        } catch {
          row.className = 'failed';
          row.textContent = file.name + ': connection failed';
        }
      }
      if (files.length > 10) progress.append(Object.assign(document.createElement('li'), { className: 'failed', textContent: 'Only the first 10 files were uploaded.' }));
      if (uploaded) {
        const done = Object.assign(document.createElement('li'), { textContent: uploaded + ' uploaded. Reloading the library…' });
        progress.append(done);
        setTimeout(() => window.location.assign(@json(route('admin.media.index'))), progress.querySelector('.failed') ? 3500 : 800);
      }
    };

    zone.addEventListener('submit', (event) => { event.preventDefault(); upload(input.files); });
    ['dragenter', 'dragover'].forEach((type) => zone.addEventListener(type, (event) => { event.preventDefault(); zone.classList.add('dragging'); }));
    ['dragleave', 'drop'].forEach((type) => zone.addEventListener(type, (event) => { event.preventDefault(); zone.classList.remove('dragging'); }));
    zone.addEventListener('drop', (event) => upload(event.dataTransfer?.files ?? []));
  })();
</script>
@endsection
