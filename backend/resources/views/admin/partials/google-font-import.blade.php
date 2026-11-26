<form method="post" action="{{ route('admin.fonts.google') }}" class="google-result">
  @csrf
  <input type="hidden" name="family" value="{{ $family }}">
  <div class="panel-head" style="margin-bottom:4px"><strong>{{ $family }}</strong><a href="{{ $google->specimenUrl($family) }}" target="_blank" rel="noopener noreferrer">See it on Google Fonts ↗</a></div>
  @if($category)<small style="color:#858294;font-size:8px">{{ ucfirst($category) }}</small>@endif
  <fieldset class="weight-picks" style="border:0;padding:0">
    <legend class="field-label" style="width:100%">Weights to add</legend>
    @foreach(\App\Services\GoogleFonts::WEIGHTS as $weight)
      <label class="checkbox-row"><input type="checkbox" name="weights[]" value="{{ $weight }}" @checked(in_array($weight, [400, 700], true))> {{ $weight }}</label>
    @endforeach
  </fieldset>
  <div class="form-inline" style="align-items:center">
    <select name="style" aria-label="Style for {{ $family }}"><option value="normal">Normal</option><option value="italic">Italic</option></select>
    <button class="button button-primary" type="submit">Add to library</button>
  </div>
</form>
