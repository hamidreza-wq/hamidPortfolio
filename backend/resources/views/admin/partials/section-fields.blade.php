<div class="field-grid">
  <div class="field"><label for="{{ $prefix }}-type">Section type</label><select id="{{ $prefix }}-type" name="type">@foreach(\App\Models\PortfolioSection::CUSTOM_TYPES as $type)<option value="{{ $type }}" @selected(old('type', $section?->type ?? 'custom') === $type)>{{ ucfirst($type) }}</option>@endforeach</select></div>
  <div class="field"><label for="{{ $prefix }}-label">Navigation label</label><input id="{{ $prefix }}-label" name="label" value="{{ old('label', $section?->label) }}" maxlength="60" required></div>
  <div class="field full"><label for="{{ $prefix }}-title">Title</label><input id="{{ $prefix }}-title" name="title" value="{{ old('title', $section?->title) }}" maxlength="180" required></div>
  <div class="field full"><label for="{{ $prefix }}-subtitle">Subtitle</label><input id="{{ $prefix }}-subtitle" name="subtitle" value="{{ old('subtitle', $section?->subtitle) }}" maxlength="180"></div>
  <div class="field full"><label for="{{ $prefix }}-body">Plain-text description</label><textarea id="{{ $prefix }}-body" name="body" maxlength="3000">{{ old('body', $section?->body) }}</textarea></div>
  <div class="field full"><label for="{{ $prefix }}-items">Structured items (JSON)</label><textarea id="{{ $prefix }}-items" name="items_json" rows="7">{{ old('items_json', json_encode($section?->items ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) }}</textarea><span class="field-hint">Array of at most 20 objects. Only "title" (required), "subtitle", and "body" plain-text fields are supported. Empty array [] is valid. Never invent personal details or testimonials.</span></div>
  <div class="field"><label for="{{ $prefix }}-order">Order</label><input id="{{ $prefix }}-order" type="number" name="sort_order" value="{{ old('sort_order', $section?->sort_order ?? 50) }}" min="0" max="100" required></div>
  <div class="field"><label for="{{ $prefix }}-effect">Background effect</label><select id="{{ $prefix }}-effect" name="background_effect">@foreach(['cosmic','none'] as $effect)<option value="{{ $effect }}" @selected(old('background_effect', $section?->background_effect ?? 'cosmic') === $effect)>{{ ucfirst($effect) }}</option>@endforeach</select></div>
  @foreach(['spacing_top' => 'Top spacing', 'spacing_bottom' => 'Bottom spacing'] as $field => $label)
  <div class="field"><label for="{{ $prefix }}-{{ $field }}">{{ $label }} (px)</label><input id="{{ $prefix }}-{{ $field }}" type="number" name="{{ $field }}" value="{{ old($field, $section?->$field ?? 100) }}" min="0" max="240" required></div>
  @endforeach
  @include('admin.partials.media-select', ['id' => $prefix.'-image', 'name' => 'image_media_id', 'value' => old('image_media_id', $section?->image_media_id), 'label' => 'Section image'])
  @include('admin.partials.media-select', ['id' => $prefix.'-background', 'name' => 'background_media_id', 'value' => old('background_media_id', $section?->background_media_id), 'label' => 'Background image'])
  <div class="field full">
    <input type="hidden" name="is_visible" value="0"><label class="checkbox-row"><input type="checkbox" name="is_visible" value="1" @checked(old('is_visible', $section?->is_visible ?? false))> Publish this section</label>
    <input type="hidden" name="show_in_navigation" value="0"><label class="checkbox-row"><input type="checkbox" name="show_in_navigation" value="1" @checked(old('show_in_navigation', $section?->show_in_navigation ?? true))> Show in navigation when published</label>
  </div>
</div>
