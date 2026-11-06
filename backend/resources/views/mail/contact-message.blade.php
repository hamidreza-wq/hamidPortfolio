<!doctype html>
<html lang="en">
<head><meta charset="utf-8"><title>New portfolio contact message</title></head>
<body>
  <h1>New portfolio contact message</h1>
  <p><strong>Name:</strong> {{ $contactMessage->name }}</p>
  <p><strong>Email:</strong> {{ $contactMessage->email }}</p>
  <p><strong>Subject:</strong> {{ $contactMessage->subject }}</p>
  <div style="white-space: pre-wrap">{{ $contactMessage->message }}</div>
  <p>Reply to this email to contact the visitor. A copy is also saved in your <a href="{{ url('/admin/inbox') }}">private website inbox</a>.</p>
</body>
</html>
