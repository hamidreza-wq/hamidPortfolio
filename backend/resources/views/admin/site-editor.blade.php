@extends('admin.layout')
@section('title', 'Live site editor')
@section('breadcrumb', 'Live site editor')
@section('content')
<div class="page-head">
  <div><p class="eyebrow">EDIT THE REAL PORTFOLIO / CHANGES ARE A DRAFT UNTIL SAVED</p><h1>Live site editor</h1><p>Change your copy, section order, colors, and typography. Preview updates instantly; publish saves these settings to the site visitors see.</p></div>
</div>
@include('admin.partials.theme-presets')
<div class="editor-layout">
  <form class="editor-form" id="site-editor-form" method="post" action="{{ route('admin.site-editor.update') }}" data-unsaved>
    @csrf
    @method('put')
    <section class="editor-card">
      <h2>Profile & links</h2>
      <p class="card-help">These fields are applied to the existing public portfolio and its search metadata.</p>
      <div class="field-grid">
        <div class="field"><label for="setting-name">Display name</label><input id="setting-name" name="settings[name]" value="{{ old('settings.name', $settings->name) }}" maxlength="120" required></div>
        <div class="field"><label for="setting-title">Professional title</label><input id="setting-title" name="settings[professional_title]" value="{{ old('settings.professional_title', $settings->professional_title) }}" maxlength="120" required></div>
        <div class="field"><label for="setting-github">GitHub profile URL</label><input id="setting-github" name="settings[github_url]" type="url" value="{{ old('settings.github_url', $settings->github_url) }}" required><span class="field-hint">Used for project repository links. Where GitHub shows as a profile link is set on the Social links page.</span></div>
        <div class="field"><span class="field-label">LinkedIn and other profiles</span><a class="button" href="{{ route('admin.social.index') }}">Manage social links →</a><span class="field-hint">Where each profile appears is set on the Social links page.</span></div>
        <div class="field"><label for="setting-email">Contact email</label><input id="setting-email" name="settings[email]" type="email" value="{{ old('settings.email', $settings->email) }}" placeholder="hello@example.com"></div>
        <div class="field"><label for="setting-footer">Footer note</label><input id="setting-footer" name="settings[footer_text]" value="{{ old('settings.footer_text', $settings->footer_text) }}" maxlength="255"></div>
      </div>
    </section>
    <section class="editor-card">
      <h2>Search & sharing</h2>
      <p class="card-help">Public page title and description are applied to search, Open Graph, and social metadata. Use a specific, truthful description of your work; tags alone cannot guarantee rankings.</p>
      <div class="field-grid">
        <div class="field full"><label for="setting-site-title">Page title</label><input id="setting-site-title" name="settings[site_title]" value="{{ old('settings.site_title', $settings->site_title) }}" maxlength="255" required></div>
        <div class="field full"><label for="setting-description">Page description</label><textarea id="setting-description" name="settings[site_description]" maxlength="320" required>{{ old('settings.site_description', $settings->site_description) }}</textarea><span class="field-hint">Aim for a useful summary around 140-160 characters. Search engines may rewrite or truncate it; up to 320 characters are supported.</span></div>
        <div class="field full"><label for="setting-canonical">Canonical base URL</label><input id="setting-canonical" name="settings[canonical_url]" type="url" value="{{ old('settings.canonical_url', $settings->canonical_url) }}" placeholder="https://your-domain.example/"><span class="field-hint">Public origin/base path, without query strings or fragments. Project canonical URLs and sitemap use this base.</span></div>
        <div class="field"><label for="setting-language">Website language</label><input id="setting-language" name="settings[language]" value="{{ old('settings.language', $settings->language) }}" maxlength="35" required placeholder="en"></div>
        <div class="field"><label for="setting-og-title">Open Graph title override</label><input id="setting-og-title" name="settings[og_title]" value="{{ old('settings.og_title', $settings->og_title) }}" maxlength="255"></div>
        <div class="field full"><label for="setting-og-description">Open Graph description override</label><textarea id="setting-og-description" name="settings[og_description]" maxlength="320">{{ old('settings.og_description', $settings->og_description) }}</textarea></div>
        @include('admin.partials.media-select', ['id' => 'setting-og-image-media', 'name' => 'settings[og_image_media_id]', 'value' => old('settings.og_image_media_id', $settings->og_image_media_id), 'label' => 'Default sharing image'])
        <div class="field full"><label for="setting-og-image">Fallback sharing image URL</label><input id="setting-og-image" name="settings[og_image]" type="url" value="{{ old('settings.og_image', $settings->og_image) }}" maxlength="255"><span class="field-hint">Use a public HTTPS image, ideally 1200 x 630 pixels. A selected library image takes priority and supplies dimensions and descriptive alt text.</span></div>
        <div class="field full">
          <input type="hidden" name="settings[indexing_enabled]" value="0"><label class="checkbox-row"><input type="checkbox" name="settings[indexing_enabled]" value="1" @checked(old('settings.indexing_enabled', $settings->indexing_enabled))> Allow search-engine indexing</label>
          <input type="hidden" name="settings[robots_follow]" value="0"><label class="checkbox-row"><input type="checkbox" name="settings[robots_follow]" value="1" @checked(old('settings.robots_follow', $settings->robots_follow))> Allow search engines to follow links</label>
        </div>
      </div>
      <h3>SEO health</h3>
      <ul>
        @if(!$settings->canonical_url)<li>Canonical base is not configured; APP_URL is currently used.</li>@endif
        @if(!$settings->og_image_media_id && !$settings->og_image)<li>Default sharing image is missing.</li>@endif
        @if($settings->og_image_media_id && empty(app(\App\Services\MediaLibrary::class)->publicPayload($settings->og_image_media_id)['alt']))<li>Default sharing image needs alt text or the file is missing in the media library.</li>@endif
        @if(!$settings->indexing_enabled)<li>Indexing is disabled. Sitemap is empty and pages send noindex.</li>@endif
        <li><a href="{{ route('portfolio.sitemap') }}" target="_blank" rel="noreferrer">Inspect sitemap</a> · <a href="{{ route('portfolio.robots') }}" target="_blank" rel="noreferrer">Inspect robots.txt</a></li>
      </ul>
    </section>
    <section class="editor-card">
      <h2>Visual system</h2>
      <p class="card-help">The preview uses the same CSS and JavaScript as the public site. Color inputs update the real page frame as you work.</p>
      <div class="field-grid">
        @foreach([
          'color_background' => ['Background', $settings->color_background],
          'color_foreground' => ['Text', $settings->color_foreground],
          'color_primary' => ['Primary', $settings->color_primary],
          'color_accent' => ['Accent', $settings->color_accent],
          'color_cyan' => ['Secondary accent', $settings->color_cyan],
        ] as $key => [$label, $value])
          <div class="field"><label for="setting-{{ $key }}">{{ $label }}</label><input id="setting-{{ $key }}" name="settings[{{ $key }}]" type="color" value="{{ old('settings.'.$key, $value) }}" required></div>
        @endforeach
        @include('admin.partials.font-select', ['id' => 'setting-font-body', 'name' => 'settings[font_body]', 'value' => old('settings.font_body', $settings->font_body), 'label' => 'Body font'])
        @include('admin.partials.font-select', ['id' => 'setting-font-heading', 'name' => 'settings[font_heading]', 'value' => old('settings.font_heading', $settings->font_heading), 'label' => 'Heading font'])
        <div class="field"><label for="setting-max-width">Content width (px)</label><input id="setting-max-width" name="settings[max_width]" type="number" min="720" max="1600" value="{{ old('settings.max_width', $settings->max_width) }}" required></div>
        <div class="field"><label for="setting-section-spacing">Section spacing (px)</label><input id="setting-section-spacing" name="settings[section_spacing]" type="number" min="48" max="240" value="{{ old('settings.section_spacing', $settings->section_spacing) }}" required></div>
        <div class="field"><label for="setting-radius">Card corner radius (px)</label><input id="setting-radius" name="settings[border_radius]" type="number" min="0" max="32" value="{{ old('settings.border_radius', $settings->border_radius) }}" required></div>
        <div class="field full">
          <span class="field-label">Motion preferences</span>
          <label class="checkbox-row"><input type="hidden" name="settings[animations_enabled]" value="0"><input type="checkbox" name="settings[animations_enabled]" value="1" @checked(old('settings.animations_enabled', $settings->animations_enabled))> Enable interface motion</label>
          <label class="checkbox-row"><input type="hidden" name="settings[particles_enabled]" value="0"><input type="checkbox" name="settings[particles_enabled]" value="1" @checked(old('settings.particles_enabled', $settings->particles_enabled))> Show decorative particle field</label>
        </div>
      </div>
    </section>
    <section class="editor-card">
      <h2>Portfolio sections</h2>
      <p class="card-help">Edit the text on the current portfolio sections, reorder them, or hide a section from public view. Use plain text in titles and body copy.</p>
      <p><a href="{{ route('admin.sections.index') }}">Add or manage structured sections</a> · <a href="{{ route('admin.revisions.index') }}">Restore content history</a></p>
      @foreach($sections as $section)
        <div class="section-editor" data-section-key="{{ $section->key }}">
          <div class="section-editor-head">
            <div style="display:flex;align-items:center;gap:8px"><span class="drag-handle" draggable="true" title="Drag to reorder" aria-hidden="true">⠿</span><strong>{{ $section->label }}</strong><small>{{ $section->key }}</small></div>
            <div class="form-inline"><button class="button section-move" type="button" data-move="up" aria-label="Move {{ $section->label }} up">↑</button><button class="button section-move" type="button" data-move="down" aria-label="Move {{ $section->label }} down">↓</button></div>
          </div>
          <div class="field-grid">
            <div class="field"><label for="section-{{ $section->key }}-label">Navigation label</label><input id="section-{{ $section->key }}-label" name="sections[{{ $section->key }}][label]" value="{{ old('sections.'.$section->key.'.label', $section->label) }}" maxlength="60" required></div>
            <div class="field"><input type="hidden" name="sections[{{ $section->key }}][show_in_navigation]" value="0"><label class="checkbox-row"><input type="checkbox" name="sections[{{ $section->key }}][show_in_navigation]" value="1" @checked(old('sections.'.$section->key.'.show_in_navigation', $section->show_in_navigation))> Show in navigation</label><span class="field-hint">While the header menu is automatic. <a href="{{ route('admin.menus.index') }}">Menus</a></span></div>
            <div class="field full"><label for="section-{{ $section->key }}-title">Title</label><input id="section-{{ $section->key }}-title" name="sections[{{ $section->key }}][title]" value="{{ old('sections.'.$section->key.'.title', $section->title) }}" maxlength="180" required></div>
            <div class="field full"><label for="section-{{ $section->key }}-subtitle">Eyebrow / subtitle</label><input id="section-{{ $section->key }}-subtitle" name="sections[{{ $section->key }}][subtitle]" value="{{ old('sections.'.$section->key.'.subtitle', $section->subtitle) }}" maxlength="180"></div>
            <div class="field full"><label for="section-{{ $section->key }}-body">Supporting copy</label><textarea id="section-{{ $section->key }}-body" name="sections[{{ $section->key }}][body]" maxlength="3000">{{ old('sections.'.$section->key.'.body', $section->body) }}</textarea></div>
            <div class="field"><label for="section-{{ $section->key }}-order">Display order</label><input id="section-{{ $section->key }}-order" name="sections[{{ $section->key }}][sort_order]" type="number" min="0" max="100" value="{{ old('sections.'.$section->key.'.sort_order', $section->sort_order) }}" required></div>
            <div class="field"><label for="section-{{ $section->key }}-effect">Background decoration</label><select id="section-{{ $section->key }}-effect" name="sections[{{ $section->key }}][background_effect]">@foreach(['cosmic', 'none'] as $effect)<option value="{{ $effect }}" @selected(old('sections.'.$section->key.'.background_effect', $section->background_effect) === $effect)>{{ ucfirst($effect) }}</option>@endforeach</select></div>
            @foreach(['spacing_top' => 'Top spacing', 'spacing_bottom' => 'Bottom spacing'] as $field => $label)
            <div class="field"><label for="section-{{ $section->key }}-{{ $field }}">{{ $label }} (px)</label><input id="section-{{ $section->key }}-{{ $field }}" name="sections[{{ $section->key }}][{{ $field }}]" type="number" min="0" max="240" value="{{ old('sections.'.$section->key.'.'.$field, $section->$field) }}" required></div>
            @endforeach
            @include('admin.partials.media-select', ['id' => 'section-'.$section->key.'-image', 'name' => 'sections['.$section->key.'][image_media_id]', 'value' => old('sections.'.$section->key.'.image_media_id', $section->image_media_id), 'label' => 'Section image'])
            @include('admin.partials.media-select', ['id' => 'section-'.$section->key.'-background', 'name' => 'sections['.$section->key.'][background_media_id]', 'value' => old('sections.'.$section->key.'.background_media_id', $section->background_media_id), 'label' => 'Section background'])
            <div class="field" style="align-content:end"><input type="hidden" name="sections[{{ $section->key }}][is_visible]" value="0"><label class="checkbox-row"><input type="checkbox" name="sections[{{ $section->key }}][is_visible]" value="1" @checked(old('sections.'.$section->key.'.is_visible', $section->is_visible))> Visible on public site</label></div>
          </div>
        </div>
      @endforeach
    </section>
    <section class="editor-card">
      <h2>Skills & categories</h2>
      <p class="card-help">Edit the groups and skills shown on the existing portfolio. Put one skill on each line.</p>
      @foreach($skillCategories as $category)
        <div class="section-editor skill-editor" data-skill-category="{{ $category->id }}">
          <div class="section-editor-head"><strong>{{ $category->title }}</strong><small>GROUP {{ str_pad((string) ($loop->index + 1), 2, '0', STR_PAD_LEFT) }}</small></div>
          <div class="field-grid">
            <div class="field full"><label for="skill-category-{{ $category->id }}-title">Category title</label><input id="skill-category-{{ $category->id }}-title" name="skill_categories[{{ $category->id }}][title]" value="{{ old('skill_categories.'.$category->id.'.title', $category->title) }}" maxlength="120" required></div>
            <div class="field"><label for="skill-category-{{ $category->id }}-order">Display order</label><input id="skill-category-{{ $category->id }}-order" name="skill_categories[{{ $category->id }}][sort_order]" type="number" min="0" max="100" value="{{ old('skill_categories.'.$category->id.'.sort_order', $category->sort_order) }}" required></div>
            <div class="field" style="align-content:end"><input type="hidden" name="skill_categories[{{ $category->id }}][is_visible]" value="0"><label class="checkbox-row"><input type="checkbox" name="skill_categories[{{ $category->id }}][is_visible]" value="1" @checked(old('skill_categories.'.$category->id.'.is_visible', $category->is_visible))> Visible on public site</label></div>
            <div class="field full"><label for="skill-category-{{ $category->id }}-skills">Skills</label><textarea id="skill-category-{{ $category->id }}-skills" name="skill_categories[{{ $category->id }}][skills]" maxlength="2000" required>{{ old('skill_categories.'.$category->id.'.skills', $category->skills->pluck('name')->implode("\n")) }}</textarea></div>
          </div>
        </div>
      @endforeach
    </section>
    <section class="editor-card">
      <h2>Publishing</h2>
      <p class="card-help">Maintenance mode temporarily replaces the public portfolio with a brief update notice. Your preview remains available.</p>
      <input type="hidden" name="settings[maintenance_mode]" value="0">
      <label class="checkbox-row"><input type="checkbox" name="settings[maintenance_mode]" value="1" @checked(old('settings.maintenance_mode', $settings->maintenance_mode))> Put the public site in maintenance mode when these changes are published</label>
    </section>
    <div class="editor-actions"><span class="draft-status" id="draft-status" role="status" aria-live="polite">No unpublished changes</span><button class="button" type="button" id="draft-undo" disabled title="Undo (Ctrl+Z outside a text box)">↶ Undo</button><button class="button" type="button" id="draft-redo" disabled title="Redo (Ctrl+Shift+Z)">↷ Redo</button><a class="button" href="{{ route('portfolio.home') }}" target="_blank" rel="noreferrer">Open published site ↗</a><button class="button button-primary" type="submit">Publish changes <span aria-hidden="true">↗</span></button></div>
  </form>
  <aside class="panel preview-card" aria-label="Live portfolio preview">
    <div class="preview-head"><strong>Live website preview</strong><span>ACTUAL PORTFOLIO / DRAFT</span></div>
    <div class="preview-toolbar" role="toolbar" aria-label="Preview options">
      <div class="form-inline" role="group" aria-label="Preview size">
        @foreach(['desktop' => 'Desktop', 'tablet' => 'Tablet', 'phone' => 'Phone'] as $device => $label)<button class="button preview-device" type="button" data-device="{{ $device }}" aria-pressed="{{ $device === 'desktop' ? 'true' : 'false' }}">{{ $label }}</button>@endforeach
      </div>
      <label class="checkbox-row"><input type="checkbox" id="click-to-edit" checked> Click text to edit it</label>
    </div>
    <div class="preview-stage"><iframe class="preview-frame" id="portfolio-preview" src="{{ route('portfolio.preview') }}" title="Live preview of the portfolio website"></iframe></div>
  </aside>
</div>
@endsection
@push('scripts')
<style>
  .preview-toolbar{display:flex;flex-wrap:wrap;align-items:center;justify-content:space-between;gap:8px;padding:8px 14px;border-bottom:1px solid var(--line)}
  .preview-device[aria-pressed="true"]{border-color:rgba(194,173,255,.5);background:rgba(180,153,255,.14)}
  .preview-stage{display:flex;justify-content:center;overflow:auto;background:#05060c}
  .preview-stage .preview-frame{flex:0 0 auto;transition:width .2s ease}
  .drag-handle{cursor:grab;color:#858294;font-size:14px;user-select:none}
  .section-editor.dragging{opacity:.45}
  .section-editor.drop-before{box-shadow:0 -3px 0 #b69bff}
  .section-editor.drop-after{box-shadow:0 3px 0 #b69bff}
  .edit-flash{animation:edit-flash 1.6s ease}
  @keyframes edit-flash{0%,40%{box-shadow:0 0 0 3px rgba(196,177,255,.7)}100%{box-shadow:none}}
  .draft-status{margin-right:auto;align-self:center;color:#858294;font-size:9px}
  .draft-status.dirty{color:#e8cf9b}
</style>
<script>
  const editorForm = document.querySelector('#site-editor-form');
  const previewFrame = document.querySelector('#portfolio-preview');
  const basePayload = @js($previewPayload);
  const readField = (name) => {
    const fields = [...editorForm.querySelectorAll(`[name="${CSS.escape(name)}"]`)];
    const checkbox = fields.find((field) => field.type === 'checkbox');
    return checkbox ? checkbox.checked : (fields.at(-1)?.value ?? '');
  };
  const makePreviewPayload = () => {
    const payload = structuredClone(basePayload);
    payload.profile = {
      ...payload.profile,
      name: readField('settings[name]'),
      title: readField('settings[professional_title]'),
      github: readField('settings[github_url]'),
      email: readField('settings[email]'),
      linkedin: readField('settings[linkedin_url]'),
    };
    payload.settings = {
      ...payload.settings,
      footer_text: readField('settings[footer_text]'),
      site_title: readField('settings[site_title]'),
      site_description: readField('settings[site_description]'),
      canonical_url: readField('settings[canonical_url]'),
      language: readField('settings[language]'),
    };
    payload.theme = {
      ...payload.theme,
      background: readField('settings[color_background]'),
      foreground: readField('settings[color_foreground]'),
      primary: readField('settings[color_primary]'),
      accent: readField('settings[color_accent]'),
      cyan: readField('settings[color_cyan]'),
      font_body: readField('settings[font_body]'),
      font_heading: readField('settings[font_heading]'),
      max_width: Number(readField('settings[max_width]')),
      section_spacing: Number(readField('settings[section_spacing]')),
      border_radius: Number(readField('settings[border_radius]')),
      animations_enabled: readField('settings[animations_enabled]'),
      particles_enabled: readField('settings[particles_enabled]'),
    };
    payload.sections = [...editorForm.querySelectorAll('.section-editor[data-section-key]')].map((element) => {
      const key = element.dataset.sectionKey;
      return {
        ...basePayload.sections.find((section) => section.key === key),
        label: readField(`sections[${key}][label]`),
        show_in_navigation: readField(`sections[${key}][show_in_navigation]`),
        spacing_top: Number(readField(`sections[${key}][spacing_top]`)),
        spacing_bottom: Number(readField(`sections[${key}][spacing_bottom]`)),
        background_effect: readField(`sections[${key}][background_effect]`),
        image: basePayload.media.find((media) => media?.id === Number(readField(`sections[${key}][image_media_id]`))) ?? null,
        background: basePayload.media.find((media) => media?.id === Number(readField(`sections[${key}][background_media_id]`))) ?? null,
        title: readField(`sections[${key}][title]`),
        subtitle: readField(`sections[${key}][subtitle]`),
        body: readField(`sections[${key}][body]`),
        sort_order: Number(readField(`sections[${key}][sort_order]`)),
        is_visible: readField(`sections[${key}][is_visible]`),
      };
    });
    payload.craft = [...editorForm.querySelectorAll('.skill-editor')].map((element) => {
      const id = element.dataset.skillCategory;
      return {
        title: readField(`skill_categories[${id}][title]`),
        items: readField(`skill_categories[${id}][skills]`).split(/\r?\n/).map((value) => value.trim()).filter(Boolean),
        sort_order: Number(readField(`skill_categories[${id}][sort_order]`)),
        is_visible: readField(`skill_categories[${id}][is_visible]`),
      };
    }).filter((category) => category.is_visible).sort((a, b) => a.sort_order - b.sort_order);
    return payload;
  };
  const sendPreview = () => {
    previewFrame.contentWindow?.postMessage({ type: 'portfolio:preview', payload: makePreviewPayload() }, window.location.origin);
  };
  previewFrame.addEventListener('load', sendPreview);
  editorForm.addEventListener('input', sendPreview);
  editorForm.addEventListener('change', sendPreview);

  // ---- Live editor: click-to-edit, device sizes, section order, undo/redo ----
  (() => {
    const fieldFor = (key) => {
      const ids = { 'profile.name': 'setting-name', 'profile.title': 'setting-title', 'profile.footer': 'setting-footer' };
      if (ids[key]) return document.getElementById(ids[key]);
      let match = key.match(/^section\.([a-z0-9-]+)\.(title|subtitle|body)$/);
      if (match) return document.getElementById(`section-${match[1]}-${match[2]}`);
      match = key.match(/^section:([a-z0-9-]+)$/);
      if (match) return document.querySelector(`.section-editor[data-section-key="${CSS.escape(match[1])}"]`)?.querySelector('input, textarea');
      return null;
    };
    window.addEventListener('message', (event) => {
      if (event.source !== previewFrame.contentWindow || event.origin !== window.location.origin || event.data?.type !== 'portfolio:edit') return;
      const field = fieldFor(String(event.data.key ?? ''));
      if (!field) return;
      field.closest('details')?.setAttribute('open', '');
      const block = field.closest('.section-editor, .editor-card') ?? field;
      block.scrollIntoView({ behavior: 'smooth', block: 'center' });
      field.focus({ preventScroll: true });
      block.classList.remove('edit-flash');
      void block.offsetWidth;
      block.classList.add('edit-flash');
    });
    const clickToEdit = document.getElementById('click-to-edit');
    const sendMode = () => previewFrame.contentWindow?.postMessage({ type: 'portfolio:edit-mode', on: clickToEdit.checked }, window.location.origin);
    clickToEdit.addEventListener('change', sendMode);
    previewFrame.addEventListener('load', sendMode);

    const widths = { desktop: '100%', tablet: '768px', phone: '390px' };
    document.querySelectorAll('.preview-device').forEach((button) => button.addEventListener('click', () => {
      document.querySelectorAll('.preview-device').forEach((other) => other.setAttribute('aria-pressed', String(other === button)));
      previewFrame.style.width = widths[button.dataset.device];
    }));

    // Section order follows the order of the blocks; display order numbers are kept in step.
    const sectionBlocks = () => [...editorForm.querySelectorAll('.section-editor[data-section-key]')];
    const renumber = () => {
      sectionBlocks().forEach((block, index) => {
        const order = document.getElementById(`section-${block.dataset.sectionKey}-order`);
        if (order) order.value = index;
      });
      editorForm.dispatchEvent(new Event('change', { bubbles: true }));
    };
    const sortBlocksByOrder = () => {
      const blocks = sectionBlocks();
      const parent = blocks[0]?.parentNode;
      blocks.sort((a, b) => Number(document.getElementById(`section-${a.dataset.sectionKey}-order`)?.value ?? 0)
        - Number(document.getElementById(`section-${b.dataset.sectionKey}-order`)?.value ?? 0))
        .forEach((block) => parent.appendChild(block));
    };
    sortBlocksByOrder();
    editorForm.querySelectorAll('.section-move').forEach((button) => button.addEventListener('click', () => {
      const block = button.closest('.section-editor');
      const sibling = button.dataset.move === 'up' ? block.previousElementSibling : block.nextElementSibling;
      if (!sibling?.matches('.section-editor[data-section-key]')) return;
      button.dataset.move === 'up' ? sibling.before(block) : sibling.after(block);
      renumber();
      button.focus();
    }));
    let dragged = null;
    editorForm.querySelectorAll('.drag-handle').forEach((handle) => {
      handle.addEventListener('dragstart', (event) => {
        dragged = handle.closest('.section-editor');
        dragged.classList.add('dragging');
        event.dataTransfer.effectAllowed = 'move';
        event.dataTransfer.setData('text/plain', dragged.dataset.sectionKey);
        event.dataTransfer.setDragImage(dragged, 20, 20);
      });
      handle.addEventListener('dragend', () => {
        dragged?.classList.remove('dragging');
        sectionBlocks().forEach((block) => block.classList.remove('drop-before', 'drop-after'));
        dragged = null;
      });
    });
    sectionBlocks().forEach((block) => {
      block.addEventListener('dragover', (event) => {
        if (!dragged || block === dragged) return;
        event.preventDefault();
        const after = event.clientY > block.getBoundingClientRect().top + block.offsetHeight / 2;
        block.classList.toggle('drop-after', after);
        block.classList.toggle('drop-before', !after);
      });
      block.addEventListener('dragleave', () => block.classList.remove('drop-before', 'drop-after'));
      block.addEventListener('drop', (event) => {
        if (!dragged || block === dragged) return;
        event.preventDefault();
        block.classList.contains('drop-after') ? block.after(dragged) : block.before(dragged);
        block.classList.remove('drop-before', 'drop-after');
        renumber();
      });
    });

    // Undo and redo cover the whole draft. Inside a text box, Ctrl+Z still undoes typing as usual.
    const undoButton = document.getElementById('draft-undo');
    const redoButton = document.getElementById('draft-redo');
    const status = document.getElementById('draft-status');
    const capture = () => JSON.stringify([...editorForm.elements].filter((field) => field.name && field.type !== 'hidden' && field.name !== '_token')
      .map((field) => [field.id || field.name, field.type === 'checkbox' ? field.checked : field.value]));
    const initial = capture();
    const history = [initial];
    let position = 0;
    let restoring = false;
    const refresh = () => {
      undoButton.disabled = position === 0;
      redoButton.disabled = position === history.length - 1;
      const dirty = history[position] !== initial;
      status.textContent = dirty ? 'Unpublished changes' : 'No unpublished changes';
      status.classList.toggle('dirty', dirty);
    };
    const record = () => {
      if (restoring) return;
      const snapshot = capture();
      if (snapshot === history[position]) return;
      history.splice(position + 1);
      history.push(snapshot);
      if (history.length > 100) history.shift();
      position = history.length - 1;
      refresh();
    };
    const restore = (snapshot) => {
      restoring = true;
      const values = new Map(JSON.parse(snapshot));
      [...editorForm.elements].forEach((field) => {
        const key = field.id || field.name;
        if (!values.has(key) || field.type === 'hidden') return;
        field.type === 'checkbox' ? (field.checked = values.get(key)) : (field.value = values.get(key));
      });
      sortBlocksByOrder();
      sendPreview();
      restoring = false;
      refresh();
    };
    let typingTimer = null;
    editorForm.addEventListener('input', () => { clearTimeout(typingTimer); typingTimer = setTimeout(record, 500); });
    editorForm.addEventListener('change', () => { clearTimeout(typingTimer); record(); });
    undoButton.addEventListener('click', () => { if (position > 0) restore(history[--position]); });
    redoButton.addEventListener('click', () => { if (position < history.length - 1) restore(history[++position]); });
    document.addEventListener('keydown', (event) => {
      if (!(event.ctrlKey || event.metaKey) || event.key.toLowerCase() !== 'z' && event.key.toLowerCase() !== 'y') return;
      if (event.target.closest?.('input[type="text"], input:not([type]), input[type="url"], input[type="email"], textarea')) return;
      event.preventDefault();
      (event.shiftKey || event.key.toLowerCase() === 'y') ? redoButton.click() : undoButton.click();
    });

    // Warn before leaving with unpublished changes.
    let submitting = false;
    editorForm.addEventListener('submit', () => { submitting = true; });
    window.addEventListener('beforeunload', (event) => {
      if (submitting || history[position] === initial) return;
      event.preventDefault();
      event.returnValue = '';
    });
    refresh();
  })();
</script>
@endpush
