@extends('admin.layout')
@section('title', 'Testimonials')
@section('breadcrumb', 'Testimonials')
@section('content')
@php($formUrl = route('testimonials.create'))
<div class="page-head">
  <div><p class="eyebrow">PORTFOLIO / SOCIAL PROOF</p><h1>Testimonials</h1><p>Review what clients say before anyone else sees it. Only approved testimonials appear on the site, inside any visible Testimonials section.</p></div>
</div>

@if(! $hasSection)
  <div class="error-box" role="status">No visible Testimonials section exists, so approved testimonials aren't shown anywhere yet. <a href="{{ route('admin.sections.index') }}" style="color:#f6d0d3;text-decoration:underline">Add a section</a> with the type "Testimonials".</div>
@endif

<div class="grid dashboard-columns" style="margin-bottom:14px">
  <section class="panel" aria-labelledby="form-heading">
    <div class="panel-head"><h2 id="form-heading">Client submission form</h2><span class="status-pill {{ $submissionsOpen ? '' : 'off' }}">{{ $submissionsOpen ? 'Open' : 'Closed' }}</span></div>
    <p class="card-help">Send this link to clients. They give their name, words, an optional rating and consent; their email is kept private and never published. Nothing they send appears until you approve it.</p>
    <div class="form-inline" style="align-items:end;margin-bottom:12px">
      <div class="field" style="flex:1"><label for="share-link">Link to share</label><input id="share-link" value="{{ $formUrl }}" readonly></div>
      <a class="button" href="{{ $formUrl }}" target="_blank" rel="noopener">Open ↗</a>
    </div>
    <form method="post" action="{{ route('admin.testimonials.submissions') }}">
      @csrf
      <input type="hidden" name="open" value="{{ $submissionsOpen ? 0 : 1 }}">
      <button class="button {{ $submissionsOpen ? 'button-danger' : 'button-primary' }}" type="submit">{{ $submissionsOpen ? 'Close the form' : 'Open the form' }}</button>
    </form>
  </section>

  <section class="panel" aria-labelledby="add-heading">
    <div class="panel-head"><h2 id="add-heading">Add a testimonial yourself</h2></div>
    <p class="card-help">For praise you received by email, on LinkedIn or in person. Only add words the person actually wrote, and only with their permission to publish them.</p>
    <details>
      <summary class="button" style="list-style:none;display:inline-flex">Add testimonial</summary>
      <form method="post" action="{{ route('admin.testimonials.store') }}" class="project-form" style="margin-top:12px">
        @csrf
        @include('admin.partials.testimonial-fields', ['testimonial' => null, 'prefix' => 'new'])
        <label class="checkbox-row"><input type="checkbox" name="publish" value="1"> I have their permission. Publish it now</label>
        <div><button class="button button-primary" type="submit">Save testimonial</button></div>
      </form>
    </details>
  </section>
</div>

<nav class="form-inline" aria-label="Testimonial status" style="margin-bottom:12px;flex-wrap:wrap">
  @foreach(\App\Models\Testimonial::STATUSES as $value => $label)
    <a class="button {{ $status === $value ? 'button-primary' : '' }}" href="{{ route('admin.testimonials.index', ['status' => $value]) }}" @if($status === $value) aria-current="page" @endif>{{ $label }} · {{ $counts[$value] ?? 0 }}</a>
  @endforeach
</nav>

@if($testimonials->isEmpty())
  <section class="panel"><p class="card-help" style="margin:0">{{ $status === 'pending' ? 'Nothing is waiting for review.' : 'None here yet.' }}</p></section>
@else
  <div class="grid" style="gap:12px">
    @foreach($testimonials as $testimonial)
      <article class="editor-card" id="testimonial-{{ $testimonial->id }}">
        <div class="project-title-row">
          <div>
            <h2>{{ $testimonial->name }}@if($testimonial->is_featured) <span class="status-pill" style="margin-left:6px">Featured</span>@endif</h2>
            <p class="card-help" style="margin:4px 0 0">{{ \App\Models\Testimonial::SOURCES[$testimonial->source] ?? $testimonial->source }} · added {{ $testimonial->created_at->format('j M Y') }}@if($testimonial->reviewed_at) · reviewed {{ $testimonial->reviewed_at->diffForHumans() }} by {{ $testimonial->reviewer?->name ?? 'an administrator' }}@endif</p>
          </div>
          <div class="form-inline" style="flex-wrap:wrap">
            @foreach(['approved' => 'Approve and publish', 'rejected' => 'Reject', 'pending' => 'Back to review'] as $value => $label)
              @continue($testimonial->status === $value)
              <form method="post" action="{{ route('admin.testimonials.moderate', $testimonial) }}">@csrf @method('patch')<input type="hidden" name="status" value="{{ $value }}"><button class="button {{ $value === 'approved' ? 'button-primary' : ($value === 'rejected' ? 'button-danger' : '') }}" type="submit">{{ $label }}</button></form>
            @endforeach
          </div>
        </div>
        <blockquote style="margin:0 0 12px;padding:10px 14px;border-left:2px solid #a98cea;color:#dcd8e6;font-size:11px;line-height:1.75;white-space:pre-wrap">{{ $testimonial->quote }}</blockquote>
        @if($testimonial->source === 'website')
          <p class="card-help">Private contact: <a href="mailto:{{ $testimonial->email }}" style="color:#b69bff">{{ $testimonial->email }}</a> · consent given {{ $testimonial->consent_at?->format('j M Y, H:i') ?? 'not recorded' }} UTC. Check with them if anything looks unfamiliar.</p>
        @endif
        <details>
          <summary class="button" style="list-style:none;display:inline-flex">Edit</summary>
          <form method="post" action="{{ route('admin.testimonials.update', $testimonial) }}" class="project-form" style="margin-top:12px">
            @csrf
            @method('put')
            @include('admin.partials.testimonial-fields', ['testimonial' => $testimonial, 'prefix' => 'testimonial-'.$testimonial->id])
            <div><button class="button button-primary" type="submit">Save changes</button></div>
          </form>
          <form method="post" action="{{ route('admin.testimonials.destroy', $testimonial) }}" style="margin-top:10px">
            @csrf
            @method('delete')
            <label class="checkbox-row"><input type="checkbox" required> I understand this permanently deletes the testimonial and its private details</label>
            <button class="button button-danger" type="submit" style="margin-top:6px">Delete</button>
          </form>
        </details>
      </article>
    @endforeach
  </div>
  @if($testimonials->hasPages())<div class="pagination">{{ $testimonials->links() }}</div>@endif
@endif
@endsection
