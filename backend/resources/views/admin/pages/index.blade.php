@extends('admin.layout')
@section('title', 'Pages')
@section('breadcrumb', 'Pages')
@section('content')
<div class="page-head">
  <div><p class="eyebrow">PORTFOLIO / STANDALONE PAGES</p><h1>Pages</h1><p>Pages of their own, such as "Services", "CV" or "Now", each at its own address (hamidbayat.co.uk/your-page). Your home page and project pages are managed elsewhere.</p></div>
  <a class="button button-primary" href="{{ route('admin.pages.create') }}">New page</a>
</div>

<nav class="form-inline" aria-label="Page status" style="flex-wrap:wrap;margin-bottom:12px">
  @foreach($views as $key => $label)
    <a class="button {{ $view === $key ? 'button-primary' : '' }}" href="{{ route('admin.pages.index', ['view' => $key]) }}" @if($view === $key) aria-current="page" @endif>{{ $label }} · {{ $counts[$key] }}</a>
  @endforeach
</nav>
<form method="get" action="{{ route('admin.pages.index') }}" class="form-inline" style="align-items:end;margin-bottom:14px" role="search">
  <input type="hidden" name="view" value="{{ $view }}">
  <div class="field" style="flex:1"><label for="pages-search">Search titles, addresses and text</label><input id="pages-search" name="q" type="search" value="{{ $query }}" maxlength="100"></div>
  <button class="button" type="submit">Search</button>
</form>

@if($pages->isEmpty())
  <section class="panel"><p class="card-help" style="margin:0">{{ $view === 'trash' ? 'The trash is empty.' : ($query ? 'No pages match.' : 'No pages yet. Press "New page" to write your first one.') }}</p></section>
@else
  <section class="panel">
    <div class="table-wrap"><table class="data-table">
      <thead><tr><th scope="col">PAGE</th><th scope="col">STATUS</th><th scope="col">LAST CHANGED</th><th scope="col">ACTIONS</th></tr></thead>
      <tbody>
        @foreach($pages as $page)
          <tr>
            <td><strong style="color:#dcd8e6">{{ $page->title }}</strong><small>/{{ $page->slug }}</small></td>
            <td><span class="status-pill {{ $page->isLive() ? '' : ($page->trashed() ? 'off' : 'warn') }}">{{ $page->stateLabel() }}</span></td>
            <td>{{ $page->updated_at->diffForHumans() }}<small>{{ $page->editor?->name }}</small></td>
            <td>
              <div class="form-inline" style="flex-wrap:wrap">
                @if($page->trashed())
                  <form method="post" action="{{ route('admin.pages.restore', $page->id) }}">@csrf<button class="button button-primary" type="submit">Restore</button></form>
                  <form method="post" action="{{ route('admin.pages.force-destroy', $page->id) }}" class="form-inline" style="align-items:center">@csrf @method('delete')<label class="checkbox-row"><input type="checkbox" name="confirm" value="1" required> Permanently</label><button class="button button-danger" type="submit">Delete</button></form>
                @else
                  <a class="button" href="{{ route('admin.pages.edit', $page) }}">Edit</a>
                  @if($page->isLive())<a class="button" href="{{ $page->url() }}" target="_blank" rel="noopener">View ↗</a>@else<a class="button" href="{{ route('admin.pages.preview', $page) }}" target="_blank" rel="noopener">Preview ↗</a>@endif
                  <form method="post" action="{{ route('admin.pages.duplicate', $page) }}">@csrf<button class="button" type="submit">Duplicate</button></form>
                @endif
              </div>
            </td>
          </tr>
        @endforeach
      </tbody>
    </table></div>
    @if($pages->hasPages())<div class="pagination">{{ $pages->links() }}</div>@endif
  </section>
@endif
@endsection
