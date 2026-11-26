@extends('admin.layout')
@section('title', 'Social links')
@section('breadcrumb', 'Social links')
@section('content')
<div class="page-head">
  <div><p class="eyebrow">PORTFOLIO / PROFILES</p><h1>Social links</h1><p>Your profiles elsewhere, and where each one appears on the site: the header menu, the footer, or the contact section. Enabled links are also listed for search engines as your other profiles.</p></div>
</div>

<section class="panel" style="margin-bottom:14px" aria-labelledby="add-social">
  <div class="panel-head"><h2 id="add-social">Add a profile</h2><span class="status-pill">{{ $links->count() }} / {{ $max }}</span></div>
  <p class="card-help">Only public HTTPS profile addresses. No passwords or sign-in details are ever needed or stored here.</p>
  <form method="post" action="{{ route('admin.social.store') }}" class="project-form">
    @csrf
    @include('admin.partials.social-fields', ['link' => null, 'prefix' => 'new'])
    <div><button class="button button-primary" type="submit">Add link</button></div>
  </form>
</section>

@if($links->isEmpty())
  <section class="panel"><p class="card-help" style="margin:0">No social links yet. The contact section will only show your email button.</p></section>
@else
  <div class="grid" style="gap:12px">
    @foreach($links as $link)
      <article class="editor-card" id="social-{{ $link->id }}">
        <div class="project-title-row">
          <div>
            <h2>{{ $link->displayLabel() }} @unless($link->is_enabled)<span class="status-pill off" style="margin-left:6px">Hidden</span>@endunless</h2>
            <p class="card-help" style="margin:4px 0 0;word-break:break-all"><a href="{{ $link->url }}" target="_blank" rel="noopener noreferrer" style="color:#b69bff">{{ $link->url }}</a></p>
            <p class="card-help" style="margin:4px 0 0">
              @php($places = array_keys(array_filter(['Header' => $link->show_in_header, 'Footer' => $link->show_in_footer, 'Contact section' => $link->show_in_contact])))
              {{ $link->is_enabled ? ($places ? 'Shown in: '.implode(', ', $places) : 'Enabled, but not placed anywhere (still listed for search engines)') : 'Not shown anywhere' }}
            </p>
          </div>
          <div class="form-inline">
            <form method="post" action="{{ route('admin.social.move', $link) }}">@csrf @method('patch')<input type="hidden" name="direction" value="up"><button class="button" type="submit" aria-label="Move {{ $link->displayLabel() }} up" @disabled($loop->first)>↑</button></form>
            <form method="post" action="{{ route('admin.social.move', $link) }}">@csrf @method('patch')<input type="hidden" name="direction" value="down"><button class="button" type="submit" aria-label="Move {{ $link->displayLabel() }} down" @disabled($loop->last)>↓</button></form>
          </div>
        </div>
        <details>
          <summary class="button" style="list-style:none;display:inline-flex">Edit</summary>
          <form method="post" action="{{ route('admin.social.update', $link) }}" class="project-form" style="margin-top:12px">
            @csrf
            @method('put')
            @include('admin.partials.social-fields', ['link' => $link, 'prefix' => 'social-'.$link->id])
            <div><button class="button button-primary" type="submit">Save</button></div>
          </form>
          <form method="post" action="{{ route('admin.social.destroy', $link) }}" style="margin-top:10px">@csrf @method('delete')<button class="button button-danger" type="submit">Remove {{ $link->displayLabel() }}</button></form>
        </details>
      </article>
    @endforeach
  </div>
@endif
<p class="card-help" style="margin-top:14px">Links show as text labels rather than brand logos. The GitHub profile in the <a href="{{ route('admin.site-editor.edit') }}#setting-github" style="color:#b69bff">live editor</a> is still used for project repository links.</p>
@endsection
