@foreach($projects as $project)
<article class="project-card">
  <div class="project-card-body">
    <h3><a href="{{ $project['detailUrl'] }}">{{ $project['name'] }}</a></h3>
    <p>{{ $project['description'] }}</p>
  </div>
</article>
@endforeach
