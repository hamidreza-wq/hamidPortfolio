<form method="post" action="{{ route('admin.platform.scan', $kind) }}" class="form-inline" data-platform-submit>
  @csrf
  <input type="hidden" name="confirm" value="1">
  <button class="button button-primary" type="submit">{{ $label }}</button>
  <span class="field-hint" role="status" data-platform-status>Read-only check. No publishing or deployment.</span>
</form>
