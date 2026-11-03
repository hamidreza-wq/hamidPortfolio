<section class="editor-card" style="margin-bottom:16px">
  <h2>Color presets</h2>
  <p class="card-help">Load a saved palette into the editor draft. Only Publish changes updates the public site. Saving a preset records the current draft colors.</p>
  <div class="grid" style="gap:8px;margin-bottom:12px">
    @foreach(\App\Models\ThemePreset::orderBy('name')->get() as $preset)
      <div style="display:flex;gap:8px;flex-wrap:wrap">
        <button class="button theme-preset-load" type="button" data-colors="{{ json_encode($preset->only(['color_background', 'color_foreground', 'color_primary', 'color_accent', 'color_cyan'])) }}">{{ $preset->name }}</button>
        <form method="post" action="{{ route('admin.themes.destroy', $preset) }}" data-confirm="Remove this saved palette?">
          @csrf
          @method('delete')
          <button class="button button-danger" type="submit">Remove preset</button>
        </form>
      </div>
    @endforeach
  </div>
  <form id="theme-preset-form" method="post" action="{{ route('admin.themes.store') }}" class="field-grid">
    @csrf
    <div class="field"><label for="theme-preset-name">New preset name</label><input id="theme-preset-name" name="name" maxlength="120" required></div>
    @foreach(['color_background', 'color_foreground', 'color_primary', 'color_accent', 'color_cyan'] as $color)
      <input type="hidden" name="{{ $color }}" value="{{ $settings->$color }}">
    @endforeach
    <div style="align-self:end"><button class="button" type="submit">Save draft palette as preset</button></div>
  </form>
</section>
@push('scripts')
<script>
document.querySelectorAll('.theme-preset-load').forEach((button) => button.addEventListener('click', () => {
  const colors = JSON.parse(button.dataset.colors);
  for (const [name, value] of Object.entries(colors)) {
    const input = document.getElementById(`setting-${name}`);
    if (input) {
      input.value = value;
      input.dispatchEvent(new Event('input', { bubbles: true }));
    }
  }
}));
document.getElementById('theme-preset-form').addEventListener('submit', (event) => {
  for (const input of event.currentTarget.querySelectorAll('input[name^="color_"]')) {
    input.value = document.getElementById(`setting-${input.name}`).value;
  }
});
</script>
@endpush
