@extends('admin.layout')
@section('title', 'Projects')
@section('breadcrumb', 'Projects')
@section('content')
<div class="page-head">
  <div><p class="eyebrow">VERIFIED WORK / PROJECT CATALOG</p><h1>Projects</h1><p>Update projects shown on the existing portfolio. Drafts stay private; published changes appear in the live preview.</p></div>
</div>
<section class="editor-card" style="margin-bottom:16px">
  <h2>Add a project</h2>
  <p class="card-help">Use verified project information. Repository links must point to GitHub. Preview reflects the current form data; save to publish.</p>
  <form class="project-form" id="new-project-form" method="post" action="{{ route('admin.projects.store') }}" data-unsaved>
    @csrf
    @include('admin.partials.project-fields', ['project' => null, 'prefix' => 'new'])
    <div class="checkbox-row">
      <input type="hidden" name="is_featured" value="0">
      <label><input type="checkbox" name="is_featured" value="1"> Featured</label>
      <input type="hidden" name="is_published" value="0">
      <label><input type="checkbox" name="is_published" value="1"> Publish this project on the live site</label>
    </div>
    <div><button class="button button-primary" type="submit">Add project</button></div>
  </form>
</section>
<div class="grid editor-layout">
  <div class="project-list" id="project-list">
    <form class="editor-card" method="get" action="{{ route('admin.projects.index') }}" role="search">
      <div class="field"><label for="project-admin-search">Search projects</label><input id="project-admin-search" name="q" type="search" value="{{ $search }}" maxlength="100" placeholder="Name, slug, category, description or technology"></div>
      <div><button class="button" type="submit">Search</button>@if($search !== '') <a class="button" href="{{ route('admin.projects.index') }}">Clear</a>@endif</div>
      @if($search !== '')<p class="card-help" role="status">{{ $projects->total() }} {{ \Illuminate\Support\Str::plural('project', $projects->total()) }} found for “{{ $search }}”.</p>@endif
    </form>
    @forelse($projects as $project)
      <section class="editor-card project-editor">
        <div class="project-editor-main">
          <div class="project-title-row"><h2>{{ $project->name }}</h2><span class="status-pill {{ $project->is_published ? '' : 'off' }}">{{ $project->is_published ? 'Published' : 'Draft' }}</span></div>
          @if($project->is_published)<p><a href="{{ route('portfolio.projects.show', $project->slug) }}" target="_blank" rel="noreferrer">Open indexable project page ↗</a></p>@endif
          <p class="card-help">SEO health: {{ $project->seo_title ? 'custom title' : 'title fallback' }}, {{ $project->seo_description ? 'custom description' : 'description fallback' }}, {{ $project->media_asset_id ? 'sharing image selected' : 'missing project image' }}. <a href="{{ route('admin.revisions.index') }}">Content history</a></p>
          @if($project->media_asset_id && empty(app(\App\Services\MediaLibrary::class)->publicPayload($project->media_asset_id)['alt']))<p class="card-help">Featured image alt text or file is missing. Update it in the media library.</p>@endif
          @foreach($project->gallery_media_ids ?? [] as $galleryId)@if(empty(app(\App\Services\MediaLibrary::class)->publicPayload($galleryId)['alt']))<p class="card-help">Gallery image #{{ $galleryId }} needs alt text or its file is missing.</p>@endif @endforeach
          <form class="project-form existing-project-form" method="post" action="{{ route('admin.projects.update', $project) }}" data-original-repo="{{ basename(parse_url($project->repository_url, PHP_URL_PATH) ?: '') }}" data-unsaved>
            @csrf
            @method('put')
            @include('admin.partials.project-fields', ['project' => $project, 'prefix' => 'project-'.$project->id])
            <div class="checkbox-row"><input type="hidden" name="is_published" value="0"><label><input type="checkbox" name="is_published" value="1" @checked($project->is_published)> Publish this project on the live site</label><input type="hidden" name="is_featured" value="0"><label><input type="checkbox" name="is_featured" value="1" @checked($project->is_featured)> Featured</label></div>
            <div><button class="button button-primary" type="submit">Save project</button></div>
          </form>
        </div>
        <div class="project-editor-actions">
          <form method="post" action="{{ route('admin.projects.destroy', $project) }}" data-confirm="Remove {{ $project->name }} from the portfolio?">
            @csrf
            @method('delete')
            <button class="button button-danger" type="submit">Delete project</button>
          </form>
        </div>
      </section>
    @empty
      <section class="panel"><p style="color:#9793a8;font-size:10px">{{ $search !== '' ? 'No projects match that search.' : 'No projects yet. Add one above to show it on the portfolio.' }}</p></section>
    @endforelse
    @if($projects->hasPages())<div class="pagination">{{ $projects->links() }}</div>@endif
  </div>
  <aside class="panel preview-card" aria-label="Live portfolio preview">
    <div class="preview-head"><strong>Live website preview</strong><span>PROJECT DRAFT</span></div>
    <iframe class="preview-frame" id="portfolio-preview" src="{{ route('portfolio.preview') }}" title="Live preview of the portfolio website"></iframe>
  </aside>
</div>
@endsection
@push('scripts')
<script>
  const projectPreview = document.querySelector('#portfolio-preview');
  const projectBaseline = @js($previewPayload);
  const list = document.querySelector('#project-list');
  const newForm = document.querySelector('#new-project-form');
  const formValue = (form, name) => {
    const checkbox = form.querySelector(`input[type="checkbox"][name="${CSS.escape(name)}"]`);
    if (checkbox) return checkbox.checked;
    const field = form.elements.namedItem(name);
    if (field instanceof RadioNodeList) return field.value;
    return field?.type === 'checkbox' ? field.checked : (field?.value ?? '');
  };
  const projectFromForm = (form) => {
    const repositoryUrl = formValue(form, 'repository_url');
    const technologies = String(formValue(form, 'technologies')).split(/\r?\n/).map((value) => value.trim()).filter(Boolean);
    const features = String(formValue(form, 'features')).split(/\r?\n/).map((value) => value.trim()).filter(Boolean);
    let repo = '';
    try { repo = new URL(repositoryUrl).pathname.split('/').filter(Boolean).at(-1) ?? ''; } catch {}
    return {
      name: formValue(form, 'name'),
      slug: formValue(form, 'slug'),
      detailUrl: formValue(form, 'slug') ? `/projects/${encodeURIComponent(formValue(form, 'slug'))}` : '',
      featured: formValue(form, 'is_featured'),
      status: formValue(form, 'status'),
      image: projectBaseline.media.find((media) => media?.id === Number(formValue(form, 'media_asset_id'))) ?? null,
      gallery: [...form.querySelector('[name="gallery_media_ids[]"]').selectedOptions].map((option) => projectBaseline.media.find((media) => media?.id === Number(option.value))).filter(Boolean),
      category: formValue(form, 'category'),
      categoryLabel: formValue(form, 'category_label'),
      repo,
      repositoryUrl,
      liveUrl: formValue(form, 'live_url'),
      description: formValue(form, 'description'),
      detail: formValue(form, 'details'),
      technologies,
      features,
      visual: formValue(form, 'visual'),
      visualLabel: formValue(form, 'visual_label'),
      sort_order: Number(formValue(form, 'sort_order')),
      sortOrder: Number(formValue(form, 'sort_order')),
      published: formValue(form, 'is_published'),
    };
  };
  const sendProjectPreview = () => {
    const payload = structuredClone(projectBaseline);
    const currentProjects = new Map(payload.projects.map((project) => [project.repo, project]));
    [...(list?.querySelectorAll('.existing-project-form') ?? [])].forEach((form) => {
      const edited = projectFromForm(form);
      currentProjects.delete(form.dataset.originalRepo);
      if (edited.name && edited.repo && edited.published) currentProjects.set(edited.repo, edited);
    });
    const isNew = projectFromForm(newForm);
    payload.projects = [...currentProjects.values(), ...(isNew.name && isNew.repo ? [isNew] : [])]
      .sort((left, right) => (left.sort_order ?? left.sortOrder ?? 0) - (right.sort_order ?? right.sortOrder ?? 0));
    projectPreview.contentWindow?.postMessage({ type: 'portfolio:preview', payload }, window.location.origin);
  };
  projectPreview.addEventListener('load', sendProjectPreview);
  document.querySelectorAll('.project-form').forEach((form) => {
    form.addEventListener('input', sendProjectPreview);
    form.addEventListener('change', sendProjectPreview);
  });
</script>
@endpush
