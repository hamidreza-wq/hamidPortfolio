@extends('admin.layout')
@section('title', $page->exists ? 'Edit '.$page->title : 'New page')
@section('breadcrumb', 'Pages / '.($page->exists ? $page->title : 'New page'))
@section('content')
@php($value = fn (string $field, $default = null) => old($field, $page->{$field} ?? $default))
<div class="page-head">
  <div><p class="eyebrow"><a href="{{ route('admin.pages.index') }}" style="color:inherit">PAGES</a> / {{ $page->exists ? 'EDIT' : 'NEW' }}</p><h1>{{ $page->exists ? $page->title : 'New page' }}</h1>@if($page->exists)<p><span class="status-pill {{ $page->isLive() ? '' : 'warn' }}">{{ $page->stateLabel() }}</span> @if($page->isLive())<a href="{{ $page->url() }}" target="_blank" rel="noopener" style="color:#b69bff">{{ $page->url() }} ↗</a>@endif</p>@endif</div>
  @if($page->exists)<a class="button" href="{{ route('admin.pages.preview', $page) }}" target="_blank" rel="noopener">Preview ↗</a>@endif
</div>

<form method="post" action="{{ $page->exists ? route('admin.pages.update', $page) : route('admin.pages.store') }}" class="editor-layout" style="grid-template-columns:minmax(0,1fr) minmax(280px,.45fr)">
  @csrf
  @if($page->exists) @method('put') @endif
  <input type="hidden" name="menus_present" value="1">
  <div class="editor-form">
    <section class="editor-card">
      <div class="field-grid">
        <div class="field full"><label for="page-title">Title</label><input id="page-title" name="title" value="{{ $value('title') }}" maxlength="120" required></div>
        <div class="field full"><label for="page-slug">Address</label><div class="form-inline" style="align-items:center"><span class="field-hint" style="white-space:nowrap">{{ rtrim(url('/'), '/') }}/</span><input id="page-slug" name="slug" value="{{ $value('slug') }}" maxlength="80" pattern="[a-z0-9]+(-[a-z0-9]+)*" placeholder="made from the title"></div><span class="field-hint">Lowercase letters, numbers and hyphens. Changing it after publishing breaks links people already have.</span></div>
        <div class="field full"><label for="page-summary">Summary (optional)</label><textarea id="page-summary" name="summary" rows="2" maxlength="320">{{ $value('summary') }}</textarea><span class="field-hint">Shown under the title, and used for search results unless you set a description below.</span></div>
        <div class="field full"><label for="page-body">Content</label><textarea id="page-body" name="body" rows="22" required style="font-family:var(--mono);font-size:11px;line-height:1.7">{{ $value('body') }}</textarea>
          <span class="field-hint">Write in Markdown: <code>## Heading</code>, <code>**bold**</code>, <code>- list item</code>, <code>[link text](https://…)</code>. Images: copy an image's URL from the <a href="{{ route('admin.media.index') }}">media library</a> and write <code>![what it shows](that URL)</code>. HTML and images from other sites aren't allowed.</span></div>
      </div>
    </section>
  </div>

  <aside class="editor-form">
    <section class="editor-card">
      <h2>Publishing</h2>
      <div class="field"><label for="page-status">Status</label><select id="page-status" name="status">@foreach(\App\Models\Page::STATUSES as $key => $label)<option value="{{ $key }}" @selected($value('status', 'draft') === $key)>{{ $label }}</option>@endforeach</select></div>
      <div class="field" style="margin-top:10px"><label for="page-publish-at">Publish at (optional, UTC)</label><input id="page-publish-at" name="publish_at" type="datetime-local" value="{{ old('publish_at', $page->publish_at?->format('Y-m-d\TH:i')) }}"><span class="field-hint">With "Published", the page appears at this time. Leave empty to publish now.</span></div>
      <div style="margin-top:12px"><button class="button button-primary" type="submit">{{ $page->exists ? 'Save page' : 'Create page' }}</button></div>
    </section>

    <section class="editor-card">
      <h2>Menus</h2>
      <label class="checkbox-row"><input type="checkbox" name="menu_footer" value="1" @checked(old('menu_footer', in_array('footer', $menuState, true)))> Show in the footer menu</label>
      @if($headerCustom)
        <label class="checkbox-row"><input type="checkbox" name="menu_header" value="1" @checked(old('menu_header', in_array('header', $menuState, true)))> Show in the header menu</label>
      @else
        <p class="card-help" style="margin:6px 0 0">The header menu is automatic, built from sections. To add this page to it, <a href="{{ route('admin.menus.index') }}">customise the header menu</a> first.</p>
      @endif
      <p class="card-help" style="margin:6px 0 0">Menu links only show while the page is live.</p>
    </section>

    <section class="editor-card">
      <h2>Search &amp; sharing</h2>
      <div class="field"><label for="page-seo-title">Search title (optional)</label><input id="page-seo-title" name="seo_title" value="{{ $value('seo_title') }}" maxlength="120"></div>
      <div class="field" style="margin-top:8px"><label for="page-seo-description">Search description (optional)</label><textarea id="page-seo-description" name="seo_description" rows="3" maxlength="320">{{ $value('seo_description') }}</textarea></div>
      <div style="margin-top:8px">@include('admin.partials.media-select', ['id' => 'page-image', 'name' => 'media_asset_id', 'value' => $value('media_asset_id'), 'label' => 'Featured and sharing image'])</div>
      <label class="checkbox-row" style="margin-top:8px"><input type="checkbox" name="noindex" value="1" @checked($value('noindex', false))> Ask search engines not to list this page</label>
    </section>
  </aside>
</form>

@if($page->exists)
  <section class="panel" style="margin-top:14px">
    <div class="form-inline" style="flex-wrap:wrap;align-items:center">
      <form method="post" action="{{ route('admin.pages.duplicate', $page) }}">@csrf<button class="button" type="submit">Duplicate</button></form>
      <a class="button" href="{{ route('admin.revisions.index') }}">Earlier versions</a>
      <form method="post" action="{{ route('admin.pages.destroy', $page) }}" data-confirm="Move &quot;{{ $page->title }}&quot; to the trash? It stops showing straight away and can be restored.">@csrf @method('delete')<button class="button button-danger" type="submit">Move to trash</button></form>
    </div>
  </section>
@endif
@endsection
