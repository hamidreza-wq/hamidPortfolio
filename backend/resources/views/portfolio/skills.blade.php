@foreach($craft as $group)
<section class="craft-group">
  <h3>{{ $group['title'] }}</h3>
  <ul>@foreach($group['items'] as $item)<li>{{ $item }}</li>@endforeach</ul>
</section>
@endforeach
