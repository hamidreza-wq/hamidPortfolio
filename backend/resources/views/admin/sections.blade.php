@extends('admin.layout')
@section('title', 'Structured sections')
@section('breadcrumb', 'Structured sections')
@section('content')
<div class="page-head"><div><p class="eyebrow">SAFE CONTENT / NO CUSTOM HTML</p><h1>Structured sections</h1><p>Add verified experience, education, services, testimonials or plain-text content. New sections are hidden until you explicitly publish them.</p></div></div>
<p><a href="{{ route('admin.site-editor.edit') }}">Edit built-in sections and navigation</a> · <a href="{{ route('admin.revisions.index') }}">Content history</a></p>
<div class="editor-layout">
<div>
  <section class="editor-card"><h2>Add a section</h2>
    <form class="section-form" method="post" action="{{ route('admin.sections.store') }}" data-key="section-draft" data-unsaved>
      @csrf
      @include('admin.partials.section-fields', ['section' => null, 'prefix' => 'new'])
      <button class="button button-primary" type="submit">Save section</button>
    </form>
  </section>
  @foreach($sections as $section)
  @if(!in_array($section->key, \App\Models\PortfolioSection::BUILT_IN_KEYS, true))
  <section class="editor-card">
    <h2>{{ $section->label }} <small>{{ $section->is_visible ? 'Published' : 'Hidden' }}</small></h2>
    <form class="section-form" method="post" action="{{ route('admin.sections.update', $section) }}" data-key="{{ $section->key }}" data-unsaved>
      @csrf @method('put')
      @include('admin.partials.section-fields', ['section' => $section, 'prefix' => 'section-'.$section->id])
      <button class="button button-primary" type="submit">Save section</button>
    </form>
    <form method="post" action="{{ route('admin.sections.destroy', $section) }}" data-confirm="Delete this section? Content is retained in revision history.">
      @csrf @method('delete')<button class="button button-danger" type="submit">Delete section</button>
    </form>
  </section>
  @endif
  @endforeach
</div>
<aside class="panel preview-card"><div class="preview-head"><strong>Live section preview</strong><span>UNSAVED / PRIVATE</span></div><iframe id="section-preview" class="preview-frame" src="{{ route('portfolio.preview') }}" title="Structured section preview"></iframe></aside>
</div>
@endsection
@push('scripts')
<script>
const sectionBaseline = @js($previewPayload);
const sectionFrame = document.querySelector('#section-preview');
const sendSectionPreview = () => {
  const payload = structuredClone(sectionBaseline);
  const current = new Map(payload.sections.map((section) => [section.key, section]));
  document.querySelectorAll('.section-form').forEach((form) => {
    const value = (name) => form.querySelector(`[name="${CSS.escape(name)}"]`)?.value ?? '';
    const checked = (name) => form.querySelector(`input[type="checkbox"][name="${CSS.escape(name)}"]`)?.checked ?? false;
    let items = [];
    try { const parsed = JSON.parse(value('items_json')); if (Array.isArray(parsed)) items = parsed.filter((item) => item && typeof item === 'object').slice(0,20); } catch {}
    if (!value('title')) return;
    current.set(form.dataset.key, {
      key: form.dataset.key, type: value('type'), label: value('label'), title: value('title'),
      subtitle: value('subtitle'), body: value('body'), items,
      sort_order: Number(value('sort_order')), is_visible: checked('is_visible'),
      show_in_navigation: checked('show_in_navigation'),
      spacing_top: Number(value('spacing_top')), spacing_bottom: Number(value('spacing_bottom')),
      background_effect: value('background_effect'),
      image: payload.media.find((media) => media?.id === Number(value('image_media_id'))) ?? null,
      background: payload.media.find((media) => media?.id === Number(value('background_media_id'))) ?? null,
    });
  });
  payload.sections = [...current.values()];
  sectionFrame.contentWindow?.postMessage({ type: 'portfolio:preview', payload }, window.location.origin);
};
sectionFrame.addEventListener('load', sendSectionPreview);
document.querySelectorAll('.section-form').forEach((form) => {
  form.addEventListener('input', sendSectionPreview);
  form.addEventListener('change', sendSectionPreview);
});
</script>
@endpush
