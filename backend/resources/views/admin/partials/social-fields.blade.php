@php($value = fn (string $field, $default = null) => old($field, $link?->{$field} ?? $default))
<div class="field-grid">
  <div class="field"><label for="{{ $prefix }}-platform">Platform</label><select id="{{ $prefix }}-platform" name="platform">@foreach(\App\Models\SocialLink::PLATFORMS as $key => [$name])<option value="{{ $key }}" @selected($value('platform', 'github') === $key)>{{ $name }}</option>@endforeach</select></div>
  <div class="field"><label for="{{ $prefix }}-url">Profile address</label><input id="{{ $prefix }}-url" name="url" type="url" value="{{ $value('url') }}" maxlength="500" placeholder="https://" required></div>
  <div class="field"><label for="{{ $prefix }}-label">Label (optional)</label><input id="{{ $prefix }}-label" name="label" value="{{ $value('label') }}" maxlength="60" placeholder="Uses the platform name"></div>
  <fieldset class="field" style="border:0;padding:0;margin:0"><legend class="field-label">Show it in</legend>
    <label class="checkbox-row"><input type="checkbox" name="show_in_header" value="1" @checked($value('show_in_header', false))> Header menu</label>
    <label class="checkbox-row"><input type="checkbox" name="show_in_footer" value="1" @checked($value('show_in_footer', true))> Footer</label>
    <label class="checkbox-row"><input type="checkbox" name="show_in_contact" value="1" @checked($value('show_in_contact', true))> Contact section</label>
  </fieldset>
  <label class="checkbox-row field full"><input type="checkbox" name="is_enabled" value="1" @checked($value('is_enabled', true))> Enabled (untick to hide it everywhere without deleting it)</label>
</div>
