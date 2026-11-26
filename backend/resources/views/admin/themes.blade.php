@extends('admin.layout')
@section('title', 'Themes')
@section('breadcrumb', 'Themes')
@section('content')
@php($swatches = fn (array $values) => collect(\App\Services\Themes::COLORS)->keys()->map(fn ($color) => '<span class="theme-swatch" style="background:'.e($values[$color]).'" title="'.e(\App\Services\Themes::COLORS[$color].' '.$values[$color]).'"></span>')->implode(''))
@php($summary = fn (array $values) => ($fontOptions[$values['font_heading']] ?? $values['font_heading']).' headings · '.$values['max_width'].' px wide · '.$values['border_radius'].' px corners · '.($values['particles_enabled'] ? 'particles' : 'no particles'))
@php($matches = fn (array $values) => collect(\App\Services\Themes::FIELDS)->every(fn ($field) => (string) ($values[$field] ?? '') === (string) $current[$field] || (is_bool($current[$field]) && (bool) $values[$field] === $current[$field])))
<div class="page-head">
  <div><p class="eyebrow">PORTFOLIO / LOOK AND FEEL</p><h1>Themes</h1><p>Change the whole look at once: colours, fonts, width, spacing, corners and motion. Content is never touched, and the look you replace is saved in <a href="{{ route('admin.revisions.index') }}" style="color:#b69bff">Content history</a>, so any change can be undone.</p></div>
  <a class="button" href="{{ route('admin.site-editor.edit') }}">Fine-tune in the live editor</a>
</div>

<div class="editor-layout">
  <div class="editor-form">
    <section class="editor-card" aria-labelledby="current-heading">
      <h2 id="current-heading">Live now</h2>
      <div class="theme-row"><div class="theme-swatches">{!! $swatches($current) !!}</div><p class="card-help" style="margin:0">{{ $summary($current) }}</p></div>
      <div class="form-inline" style="flex-wrap:wrap;margin-top:12px">
        <button class="button" type="button" data-preview='@json($current)' data-name="Live now">Preview</button>
        <form method="post" action="{{ route('admin.themes.save') }}" class="form-inline" style="align-items:end">@csrf
          <div class="field"><label for="save-current">Save it as</label><input id="save-current" name="name" maxlength="100" required placeholder="My theme"></div>
          <button class="button" type="submit">Save as a theme</button>
        </form>
        <form method="post" action="{{ route('admin.themes.export') }}">@csrf<input type="hidden" name="source" value="current"><button class="button" type="submit">Export</button></form>
      </div>
    </section>

    <section class="editor-card" aria-labelledby="builtin-heading">
      <h2 id="builtin-heading">Ready-made themes</h2>
      <p class="card-help">Dark and light looks. The public site works out every other colour from a theme's five colours and switches to dark text when the background is light, so your own themes stay readable too. "Cosmic" is the original look and works as "restore default".</p>
      <div class="grid" style="gap:10px">
        @foreach($builtIn as $key => $theme)
          <article class="theme-card">
            <div class="theme-row"><div class="theme-swatches">{!! $swatches($theme['values']) !!}</div><div><strong>{{ $theme['name'] }}</strong>@if($matches($theme['values'])) <span class="status-pill">Live</span>@endif<p class="card-help" style="margin:2px 0 0">{{ $theme['description'] }} {{ $summary($theme['values']) }}.</p></div></div>
            <div class="form-inline" style="flex-wrap:wrap">
              <button class="button" type="button" data-preview='@json($theme['values'])' data-name="{{ $theme['name'] }}">Preview</button>
              <form method="post" action="{{ route('admin.themes.apply') }}">@csrf<input type="hidden" name="source" value="built-in:{{ $key }}"><button class="button button-primary" type="submit">{{ $key === \App\Services\Themes::DEFAULT ? 'Restore default' : 'Apply' }}</button></form>
              <form method="post" action="{{ route('admin.themes.save') }}" class="form-inline">@csrf<input type="hidden" name="source" value="built-in:{{ $key }}"><input type="hidden" name="name" value="{{ $theme['name'] }} copy {{ now()->format('His') }}"><button class="button" type="submit">Duplicate to edit</button></form>
            </div>
          </article>
        @endforeach
      </div>
    </section>

    <section class="editor-card" aria-labelledby="saved-heading">
      <h2 id="saved-heading">Your themes</h2>
      <p class="card-help">Themes you saved, duplicated or imported. Older presets that only stored colours keep the current fonts and layout when applied.</p>
      @forelse($saved as $preset)
        @php($values = $themes->valuesOf($preset))
        <article class="theme-card" id="theme-{{ $preset->id }}">
          <div class="theme-row"><div class="theme-swatches">{!! $swatches($values) !!}</div><div><strong>{{ $preset->name }}</strong>@if($matches($values)) <span class="status-pill">Live</span>@endif @unless($preset->settings)<span class="status-pill warn">Colours only</span>@endunless<p class="card-help" style="margin:2px 0 0">{{ $summary($values) }}</p></div></div>
          <div class="form-inline" style="flex-wrap:wrap">
            <button class="button" type="button" data-preview='@json($values)' data-name="{{ $preset->name }}">Preview</button>
            <form method="post" action="{{ route('admin.themes.apply') }}">@csrf<input type="hidden" name="source" value="saved:{{ $preset->id }}"><button class="button button-primary" type="submit">Apply</button></form>
            <form method="post" action="{{ route('admin.themes.save') }}">@csrf<input type="hidden" name="source" value="saved:{{ $preset->id }}"><input type="hidden" name="name" value="{{ mb_substr($preset->name, 0, 80) }} copy {{ now()->format('His') }}"><button class="button" type="submit">Duplicate</button></form>
            <form method="post" action="{{ route('admin.themes.export') }}">@csrf<input type="hidden" name="source" value="saved:{{ $preset->id }}"><button class="button" type="submit">Export</button></form>
            <form method="post" action="{{ route('admin.themes.destroy', $preset) }}" data-confirm="Delete the saved theme &quot;{{ $preset->name }}&quot;? The live look will not change.">@csrf @method('delete')<button class="button button-danger" type="submit">Delete</button></form>
          </div>
          <details>
            <summary class="button" style="list-style:none;display:inline-flex">Edit</summary>
            <form method="post" action="{{ route('admin.themes.update', $preset) }}" class="project-form theme-edit" style="margin-top:10px">
              @csrf @method('put')
              <div class="field-grid">
                <div class="field full"><label for="theme-{{ $preset->id }}-name">Name</label><input id="theme-{{ $preset->id }}-name" name="name" value="{{ $preset->name }}" maxlength="100" required></div>
                @foreach(\App\Services\Themes::COLORS as $color => $label)
                  <div class="field"><label for="theme-{{ $preset->id }}-{{ $color }}">{{ $label }}</label><input id="theme-{{ $preset->id }}-{{ $color }}" type="color" name="theme[{{ $color }}]" value="{{ $values[$color] }}"></div>
                @endforeach
                @foreach(['font_body' => 'Body font', 'font_heading' => 'Heading font'] as $font => $label)
                  <div class="field"><label for="theme-{{ $preset->id }}-{{ $font }}">{{ $label }}</label><select id="theme-{{ $preset->id }}-{{ $font }}" name="theme[{{ $font }}]">@foreach($fontOptions + [$values[$font] => $values[$font]] as $value => $name)<option value="{{ $value }}" @selected($values[$font] === $value)>{{ $name }}</option>@endforeach</select></div>
                @endforeach
                <div class="field"><label for="theme-{{ $preset->id }}-width">Content width (720–1600 px)</label><input id="theme-{{ $preset->id }}-width" type="number" name="theme[max_width]" min="720" max="1600" value="{{ $values['max_width'] }}" required></div>
                <div class="field"><label for="theme-{{ $preset->id }}-spacing">Section spacing (48–240 px)</label><input id="theme-{{ $preset->id }}-spacing" type="number" name="theme[section_spacing]" min="48" max="240" value="{{ $values['section_spacing'] }}" required></div>
                <div class="field"><label for="theme-{{ $preset->id }}-radius">Card corners (0–32 px)</label><input id="theme-{{ $preset->id }}-radius" type="number" name="theme[border_radius]" min="0" max="32" value="{{ $values['border_radius'] }}" required></div>
                <div class="field">
                  <label class="checkbox-row"><input type="checkbox" name="theme[animations_enabled]" value="1" @checked($values['animations_enabled'])> Interface motion</label>
                  <label class="checkbox-row"><input type="checkbox" name="theme[particles_enabled]" value="1" @checked($values['particles_enabled'])> Particle field</label>
                </div>
              </div>
              <div class="form-inline"><button class="button" type="button" data-preview-form>Preview these values</button><button class="button button-primary" type="submit">Save theme</button></div>
            </form>
          </details>
        </article>
      @empty
        <p class="card-help" style="margin:0">None yet. Save the live look or duplicate a ready-made theme to start one.</p>
      @endforelse
      <form method="post" action="{{ route('admin.themes.import') }}" enctype="multipart/form-data" class="form-inline" style="align-items:end;margin-top:14px;flex-wrap:wrap">
        @csrf
        <div class="field"><label for="theme-file">Import a theme file (.json, exported from this dashboard)</label><input id="theme-file" name="theme_file" type="file" accept=".json,application/json" required></div>
        <button class="button" type="submit">Import</button>
      </form>
    </section>
  </div>

  <section class="editor-card preview-card" aria-labelledby="preview-heading">
    <div class="preview-head"><strong id="preview-heading">Preview</strong><span id="theme-preview-name">Live now</span></div>
    <iframe class="preview-frame" id="theme-preview" src="{{ route('portfolio.preview') }}" title="Preview of the portfolio with the selected theme"></iframe>
  </section>
</div>

<style>
  .theme-card{display:grid;gap:10px;padding:12px;border:1px solid rgba(195,183,240,.1);border-radius:8px;background:rgba(255,255,255,.018);margin-top:10px}
  .theme-row{display:flex;align-items:center;gap:12px}
  .theme-row strong{font-size:11px}
  .theme-swatches{display:flex;flex:0 0 auto}
  .theme-swatch{width:20px;height:28px;border:1px solid rgba(255,255,255,.12)}
  .theme-swatch:first-child{border-radius:5px 0 0 5px}.theme-swatch:last-child{border-radius:0 5px 5px 0}
</style>
<script>
  (() => {
    const frame = document.querySelector('#theme-preview');
    const label = document.querySelector('#theme-preview-name');
    const keys = { color_background: 'background', color_foreground: 'foreground', color_primary: 'primary', color_accent: 'accent', color_cyan: 'cyan' };
    // Preview only: the frame shows the theme; nothing is saved or published.
    const preview = (values, name) => {
      const base = frame.contentWindow?.portfolioPayload;
      if (!base) return;
      const payload = structuredClone(base);
      for (const [field, value] of Object.entries(values)) payload.theme[keys[field] ?? field] = value;
      frame.contentWindow.postMessage({ type: 'portfolio:preview', payload }, window.location.origin);
      label.textContent = name + ' (preview, not published)';
      if (window.matchMedia('(max-width: 800px)').matches) frame.scrollIntoView({ behavior: 'smooth', block: 'start' });
    };
    document.querySelectorAll('[data-preview]').forEach((button) => button.addEventListener('click', () => preview(JSON.parse(button.dataset.preview), button.dataset.name)));
    document.querySelectorAll('[data-preview-form]').forEach((button) => button.addEventListener('click', () => {
      const form = button.closest('form');
      const values = {};
      for (const [name, value] of new FormData(form)) {
        const match = name.match(/^theme\[(\w+)\]$/);
        if (match) values[match[1]] = /^(max_width|section_spacing|border_radius)$/.test(match[1]) ? Number(value) : value;
      }
      values.animations_enabled = form.querySelector('[name="theme[animations_enabled]"]').checked;
      values.particles_enabled = form.querySelector('[name="theme[particles_enabled]"]').checked;
      preview(values, form.querySelector('[name="name"]').value);
    }));
  })();
</script>
@endsection
