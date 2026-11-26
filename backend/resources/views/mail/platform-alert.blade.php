<h1>Something on your portfolio needs attention</h1>
<p>You asked to be emailed straight away about high-priority alerts. Similar alerts are grouped, and you get at most one email every {{ \App\Console\Commands\SendPlatformAlerts::BATCH_MINUTES }} minutes.</p>
@foreach($items as $item)
<h2>{{ $item['title'] }}</h2>
<p>{{ $item['message'] }}</p>
@endforeach
<p><a href="{{ route('admin.platform.notifications') }}">Open the notification centre</a>. You can turn these emails off there.</p>
