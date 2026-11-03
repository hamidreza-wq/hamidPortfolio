<h1>Portfolio studio notifications</h1>
<p>These are technical review reminders, not confirmations of deployment, indexing or search rankings.</p>
@foreach($items as $item)
<h2>{{ $item['title'] }} / {{ $item['priority'] }}</h2>
<p>{{ $item['message'] }}</p>
@endforeach
<p><a href="{{ route('admin.platform.notifications') }}">Open the private notification centre</a></p>
