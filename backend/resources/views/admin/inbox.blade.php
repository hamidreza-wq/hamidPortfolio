@extends('admin.layout')
@section('title', 'Contact inbox')
@section('breadcrumb', 'Contact inbox')
@section('content')
<div class="page-head">
  <div><p class="eyebrow">PORTFOLIO CONTACT / PRIVATE MESSAGES</p><h1>Contact inbox</h1><p>Review and organize messages submitted through the portfolio contact form.</p></div>
</div>
<section class="panel">
  <form method="get" action="{{ route('admin.inbox.index') }}" class="form-inline" style="align-items:end;margin-bottom:16px">
    <div class="field" style="flex:1"><label for="inbox-search">Search messages</label><input id="inbox-search" name="q" value="{{ request('q') }}" placeholder="Name, email, or subject"></div>
    <div class="field"><label for="inbox-status">Status</label><select id="inbox-status" name="status"><option value="">All statuses</option>@foreach(['unread' => 'Unread', 'read' => 'Read', 'archived' => 'Archived'] as $value => $label)<option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>@endforeach</select></div>
    <button class="button" type="submit">Filter</button>
  </form>
  @if($messages->isEmpty())
    <div class="activity-row"><div class="activity-copy"><strong>No messages found</strong><p>Try a different search or status filter. New submissions will appear here.</p></div></div>
  @else
    <div class="grid" style="gap:12px">
      @foreach($messages as $message)
        <article class="editor-card" style="padding:14px">
          <div class="panel-head">
            <div><h2>{{ $message->subject }}</h2><p style="margin:5px 0 0;color:#858294;font-size:9px">{{ $message->name }} · <a href="mailto:{{ $message->email }}" style="color:#b69bff">{{ $message->email }}</a> · {{ $message->created_at->format('M j, Y · g:i a') }}</p></div>
            <span class="status-pill {{ $message->status === 'unread' ? 'off' : '' }}">{{ ucfirst($message->status) }}</span>
          </div>
          <p style="margin:0 0 13px;color:#c3bfce;font-size:10px;line-height:1.75;white-space:pre-wrap">{{ $message->message }}</p>
          <form method="post" action="{{ route('admin.inbox.update', $message) }}" class="form-inline" style="align-items:end">
            @csrf
            @method('patch')
            <div class="field"><label for="message-status-{{ $message->id }}">Status</label><select id="message-status-{{ $message->id }}" name="status">@foreach(['unread' => 'Unread', 'read' => 'Read', 'archived' => 'Archived'] as $value => $label)<option value="{{ $value }}" @selected($message->status === $value)>{{ $label }}</option>@endforeach</select></div>
            <div class="field" style="flex:1"><label for="message-note-{{ $message->id }}">Private note</label><textarea id="message-note-{{ $message->id }}" name="internal_note" rows="2" maxlength="3000" placeholder="Optional note">{{ $message->internal_note }}</textarea></div>
            <button class="button" type="submit">Save</button>
          </form>
          <form method="post" action="{{ route('admin.inbox.destroy', $message) }}" style="margin-top:9px" data-confirm="Permanently delete this contact message?">
            @csrf
            @method('delete')
            <button class="button button-danger" type="submit">Delete message</button>
          </form>
        </article>
      @endforeach
    </div>
    @if($messages->hasPages())<div class="pagination">{{ $messages->links() }}</div>@endif
  @endif
</section>
@endsection
