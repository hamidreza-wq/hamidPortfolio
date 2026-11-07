<div class="field">
  <label for="{{ $id }}">{{ $label ?? 'Library image' }}</label>
  <select id="{{ $id }}" name="{{ $name }}">
    <option value="">No library image</option>
    @foreach(app(\App\Services\MediaLibrary::class)->options() as $media)
      <option value="{{ $media->id }}" @selected((string) $value === (string) $media->id)>{{ $media->label }} · {{ $media->width }} × {{ $media->height }}</option>
    @endforeach
  </select>
  <span class="field-hint"><a href="{{ route('admin.media.index') }}">Upload or edit library images</a>. Referenced images cannot be deleted.</span>
</div>
