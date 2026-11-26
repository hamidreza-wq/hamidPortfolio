@php($value = fn (string $field, $default = null) => old($field, $item?->{$field} ?? $default))
@php($type = $value('type', 'section'))
<div class="field-grid" data-menu-fields>
  <div class="field"><label for="{{ $prefix }}-type">Links to</label><select id="{{ $prefix }}-type" name="type" data-menu-type>@foreach(\App\Models\MenuItem::TYPES as $key => $name)<option value="{{ $key }}" @selected($type === $key)>{{ $name }}</option>@endforeach</select></div>
  <div class="field" data-menu-target="section"><label for="{{ $prefix }}-section">Section</label><select id="{{ $prefix }}-section" name="target_section">@foreach($sections as $section)<option value="{{ $section->key }}" @selected($type === 'section' && $item?->target === $section->key)>{{ $section->title ?: $section->label }}{{ $section->is_visible ? '' : ' (hidden)' }}</option>@endforeach</select></div>
  <div class="field" data-menu-target="project"><label for="{{ $prefix }}-project">Project</label><select id="{{ $prefix }}-project" name="target_project">@foreach($projects as $project)<option value="{{ $project->id }}" @selected($type === 'project' && (string) $item?->target === (string) $project->id)>{{ $project->name }}{{ $project->is_published ? '' : ' (draft)' }}</option>@endforeach</select></div>
  <div class="field" data-menu-target="custom_page"><label for="{{ $prefix }}-custom-page">Page</label><select id="{{ $prefix }}-custom-page" name="target_custom_page">@forelse($customPages as $customPage)<option value="{{ $customPage->id }}" @selected($type === 'custom_page' && (string) $item?->target === (string) $customPage->id)>{{ $customPage->title }}{{ $customPage->isLive() ? '' : ' ('.strtolower($customPage->stateLabel()).')' }}</option>@empty<option value="">No pages yet</option>@endforelse</select></div>
  <div class="field" data-menu-target="page"><label for="{{ $prefix }}-page">Page</label><select id="{{ $prefix }}-page" name="target_page">@foreach(\App\Models\MenuItem::PAGES as $key => $name)<option value="{{ $key }}" @selected($type === 'page' && $item?->target === $key)>{{ $name }}</option>@endforeach</select></div>
  <div class="field" data-menu-target="url"><label for="{{ $prefix }}-url">Address</label><input id="{{ $prefix }}-url" name="target_url" value="{{ $type === 'url' ? $value('target') : old('target_url') }}" maxlength="500" placeholder="https://… or mailto:you@example.com"></div>
  <div class="field"><label for="{{ $prefix }}-label">Label</label><input id="{{ $prefix }}-label" name="label" value="{{ $value('label') }}" maxlength="60" placeholder="Uses the section, project or page name"></div>
  <div class="field">
    <label class="checkbox-row"><input type="checkbox" name="open_in_new_tab" value="1" @checked($value('open_in_new_tab', false))> Open in a new tab</label>
    @if(($item?->menu ?? $menu) === 'header')<label class="checkbox-row"><input type="checkbox" name="is_button" value="1" @checked($value('is_button', false))> Show as a button</label>@endif
    <label class="checkbox-row"><input type="checkbox" name="is_enabled" value="1" @checked($value('is_enabled', true))> Enabled</label>
  </div>
</div>
