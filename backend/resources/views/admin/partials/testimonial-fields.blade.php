@php($old = fn (string $field, $default = null) => old($field, $testimonial?->{$field} ?? $default))
<div class="field-grid">
  <div class="field"><label for="{{ $prefix }}-name">Name</label><input id="{{ $prefix }}-name" name="name" value="{{ $old('name') }}" maxlength="120" required></div>
  <div class="field"><label for="{{ $prefix }}-role">Role (optional)</label><input id="{{ $prefix }}-role" name="role" value="{{ $old('role') }}" maxlength="120" placeholder="Founder"></div>
  <div class="field"><label for="{{ $prefix }}-company">Company (optional)</label><input id="{{ $prefix }}-company" name="company" value="{{ $old('company') }}" maxlength="120"></div>
  <div class="field"><label for="{{ $prefix }}-rating">Rating (optional)</label><select id="{{ $prefix }}-rating" name="rating"><option value="">No rating</option>@foreach([5, 4, 3, 2, 1] as $stars)<option value="{{ $stars }}" @selected((string) $old('rating') === (string) $stars)>{{ $stars }} of 5</option>@endforeach</select></div>
  <div class="field full"><label for="{{ $prefix }}-quote">Their words</label><textarea id="{{ $prefix }}-quote" name="quote" maxlength="1500" required>{{ $old('quote') }}</textarea><span class="field-hint">Plain text, up to 1,500 characters. Fix typos only; don't change what they meant.</span></div>
  @if(($testimonial?->source ?? null) === 'website')
    <input type="hidden" name="source" value="website">
  @else
    <div class="field"><label for="{{ $prefix }}-source">Where it came from</label><select id="{{ $prefix }}-source" name="source">@foreach(\App\Models\Testimonial::SOURCES as $value => $label)@continue($value === 'website')<option value="{{ $value }}" @selected($old('source', 'manual') === $value)>{{ $label }}</option>@endforeach</select></div>
  @endif
  <div class="field"><label for="{{ $prefix }}-source-url">Link to the original (optional)</label><input id="{{ $prefix }}-source-url" name="source_url" type="url" value="{{ $old('source_url') }}" maxlength="500" placeholder="https://"><span class="field-hint">Kept here for your records; not published.</span></div>
  <div class="field"><label for="{{ $prefix }}-given-on">Date given (optional)</label><input id="{{ $prefix }}-given-on" name="given_on" type="date" value="{{ $old('given_on') instanceof \Carbon\CarbonInterface ? $old('given_on')->toDateString() : $old('given_on') }}" max="{{ now()->toDateString() }}"></div>
  <div class="field"><label for="{{ $prefix }}-order">Display order</label><input id="{{ $prefix }}-order" name="sort_order" type="number" min="-1000" max="1000" value="{{ $old('sort_order', 0) }}"></div>
  <div class="field full"><label for="{{ $prefix }}-note">Private note (optional)</label><textarea id="{{ $prefix }}-note" name="internal_note" rows="2" maxlength="2000">{{ $old('internal_note') }}</textarea></div>
  <label class="checkbox-row field full"><input type="checkbox" name="is_featured" value="1" @checked($old('is_featured'))> Feature it (shown first; approved testimonials only)</label>
</div>
