@extends('admin.layout')
@section('title', 'Menus')
@section('breadcrumb', 'Menus')
@section('content')
<div class="page-head">
  <div><p class="eyebrow">PORTFOLIO / NAVIGATION</p><h1>Menus</h1><p>The links in the site's header and footer. Items that point to a hidden section, an unpublished project or a closed form are left out automatically, so visitors never meet a broken link.</p></div>
</div>

@foreach(\App\Models\MenuItem::MENUS as $menu => $title)
  @php($list = $items->get($menu, collect()))
  <section class="panel" style="margin-bottom:14px" aria-labelledby="menu-{{ $menu }}">
    <div class="panel-head">
      <h2 id="menu-{{ $menu }}">{{ $title }}</h2>
      @if($menu === 'header')<span class="status-pill {{ $headerCustom ? '' : 'warn' }}">{{ $headerCustom ? 'Custom' : 'Automatic' }}</span>@endif
    </div>

    @if($menu === 'header' && ! $headerCustom)
      <p class="card-help">Automatic: built from the sections marked "Show in navigation", in section order. It currently shows: <strong style="color:#dcd8e6">{{ $automatic->map(fn ($section) => $section->label ?: $section->title)->implode(' · ') ?: 'nothing' }}</strong>.</p>
      <p class="card-help">Switch to a custom menu to reorder freely, rename items, add project pages or other websites, or make one item a button. It starts as a copy of the list above.</p>
      <form method="post" action="{{ route('admin.menus.customise') }}">@csrf<button class="button button-primary" type="submit">Customise the header menu</button></form>
    @else
      @if($menu === 'footer')<p class="card-help">Shown in the footer next to the privacy link, which always stays.</p>@endif
      @if($list->isEmpty())
        <p class="card-help">No items yet.</p>
      @else
        <div class="activity-list" style="margin-bottom:12px">
          @foreach($list as $item)
            @php($reason = $item->is_enabled ? $menus->hiddenReason($item) : 'Disabled')
            <div class="activity-row" id="menu-item-{{ $item->id }}" style="flex-wrap:wrap">
              <div class="activity-copy">
                <strong>{{ $item->label ?: '(uses the target’s name)' }}@if($item->is_button) <span class="status-pill" style="margin-left:6px">Button</span>@endif @if($reason)<span class="status-pill off" style="margin-left:6px">{{ $reason }}</span>@endif</strong>
                <p>{{ $menus->describe($item) }}{{ $item->open_in_new_tab ? ' · opens in a new tab' : '' }}</p>
              </div>
              <div class="form-inline">
                <form method="post" action="{{ route('admin.menus.move', $item) }}">@csrf @method('patch')<input type="hidden" name="direction" value="up"><button class="button" type="submit" aria-label="Move up" @disabled($loop->first)>↑</button></form>
                <form method="post" action="{{ route('admin.menus.move', $item) }}">@csrf @method('patch')<input type="hidden" name="direction" value="down"><button class="button" type="submit" aria-label="Move down" @disabled($loop->last)>↓</button></form>
              </div>
              <details style="width:100%">
                <summary class="button" style="list-style:none;display:inline-flex">Edit</summary>
                <form method="post" action="{{ route('admin.menus.update', $item) }}" class="project-form" style="margin-top:10px">
                  @csrf @method('put')
                  @include('admin.partials.menu-item-fields', ['item' => $item, 'prefix' => 'menu-item-'.$item->id, 'menu' => $menu])
                  <div><button class="button button-primary" type="submit">Save</button></div>
                </form>
                <form method="post" action="{{ route('admin.menus.destroy', $item) }}" style="margin-top:8px">@csrf @method('delete')<button class="button button-danger" type="submit">Remove from menu</button></form>
              </details>
            </div>
          @endforeach
        </div>
      @endif
      <details @if($list->isEmpty()) open @endif>
        <summary class="button" style="list-style:none;display:inline-flex">Add an item</summary>
        <form method="post" action="{{ route('admin.menus.store') }}" class="project-form" style="margin-top:10px">
          @csrf
          <input type="hidden" name="menu" value="{{ $menu }}">
          @include('admin.partials.menu-item-fields', ['item' => null, 'prefix' => 'new-'.$menu, 'menu' => $menu])
          <div><button class="button button-primary" type="submit">Add to {{ strtolower($title) }}</button></div>
        </form>
      </details>
      @if($menu === 'header')
        <form method="post" action="{{ route('admin.menus.automatic') }}" style="margin-top:12px">@csrf
          <label class="checkbox-row"><input type="checkbox" required> Delete this custom menu and build it from sections again</label>
          <button class="button button-danger" type="submit" style="margin-top:6px">Go back to automatic</button>
        </form>
      @endif
    @endif
  </section>
@endforeach
<p class="card-help">Dropdowns aren't offered: on a one-page site they make the phone menu harder to use. Social profiles have their own <a href="{{ route('admin.social.index') }}" style="color:#b69bff">Social links</a> page and appear after these items.</p>
<script>
  // Show only the target field that matches the chosen link type.
  document.querySelectorAll('[data-menu-fields]').forEach((fields) => {
    const select = fields.querySelector('[data-menu-type]');
    const sync = () => fields.querySelectorAll('[data-menu-target]').forEach((field) => {
      field.hidden = field.dataset.menuTarget !== select.value;
    });
    select.addEventListener('change', sync);
    sync();
  });
</script>
@endsection
