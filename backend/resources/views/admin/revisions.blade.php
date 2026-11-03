@extends('admin.layout')
@section('title', 'Content history')
@section('breadcrumb', 'Content history')
@section('content')
<div class="page-head"><div><p class="eyebrow">REVISION HISTORY / EXPLICIT RESTORATION</p><h1>Content history</h1><p>Site revisions include profile, SEO, theme, sections and skills. Project revisions include the full project. Restoring publishes that snapshot immediately, and preserves the replaced content as a new revision. Media files are referenced, never copied or deleted.</p></div></div>
@forelse($revisions as $revision)
<section class="editor-card">
  <h2>{{ $revision->label }}</h2><p>{{ $revision->created_at->format('Y-m-d H:i:s') }} · {{ ucfirst($revision->scope) }} · Revision #{{ $revision->id }}</p>
  <form method="post" action="{{ route('admin.revisions.restore', $revision) }}" data-confirm="Restore and publish this {{ $revision->scope }} snapshot?">
    @csrf
    <label class="checkbox-row"><input type="checkbox" name="confirm" value="1" required> I understand this replaces and publishes the {{ $revision->scope }} content.</label>
    <button class="button button-primary" type="submit">Restore revision</button>
  </form>
</section>
@empty
<section class="editor-card"><p>No revisions yet. Saved content changes create history automatically.</p></section>
@endforelse
{{ $revisions->links() }}
@endsection
