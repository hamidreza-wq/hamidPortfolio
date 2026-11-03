@php($fonts = app(\App\Services\FontLibrary::class)->activeOptions())
<div class="field">
  <label for="{{ $id }}">{{ $label ?? 'Font family' }}</label>
  <select id="{{ $id }}" name="{{ $name }}">
    @foreach(['Inter', 'system-ui, sans-serif', 'Georgia, serif', 'monospace'] as $stack)
      <option value="{{ $stack }}" @selected($value === $stack)>{{ $stack }}</option>
    @endforeach
    @if($value && !str_starts_with($value, 'PortfolioFont') && !in_array($value, ['Inter', 'system-ui, sans-serif', 'Georgia, serif', 'monospace']))
      <option value="{{ $value }}" selected>{{ $value }}</option>
    @endif
    @foreach($fonts as $font)
      <option value="{{ $font->cssFamily() }}" @selected($value === $font->cssFamily())>{{ $font->family }} · {{ $font->weight }} {{ $font->style }}</option>
    @endforeach
  </select>
  <span class="field-hint">Only active, licensed uploaded fonts are selectable. <a href="{{ route('admin.fonts.index') }}">Manage fonts</a></span>
</div>
