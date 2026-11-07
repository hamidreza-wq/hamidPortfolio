<section class="panel platform-card">
  <h2>Check / review history</h2>
  <p class="card-help">A successful check is not a successful deployment. Refresh to see queued work complete.</p>
  @forelse($runs as $run)
    <div class="activity-row">
      <div class="activity-copy"><strong>{{ $run->kind }} / {{ $run->status }}</strong><p>{{ $run->message ?: 'Running; if the worker was interrupted, inspect private logs before retrying.' }}</p>
      @if($run->summary)<details><summary>Result details</summary><pre>{{ json_encode($run->summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre></details>@endif
      <p>{{ $run->created_at->format('Y-m-d H:i:s') }} UTC</p></div>
    </div>
  @empty
    <p class="card-help">No checks have been recorded. No status is being assumed.</p>
  @endforelse
</section>
