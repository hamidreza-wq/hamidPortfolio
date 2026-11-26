@extends('admin.layout')
@section('title', 'Users')
@section('breadcrumb', 'Users')
@section('content')
<div class="page-head">
  <div><p class="eyebrow">STUDIO / ACCESS</p><h1>Users</h1><p>Who can sign in to this dashboard, and what each person can do. Only owners see this page.</p></div>
</div>

@if(session('setupLink'))
  <div class="editor-card" style="margin-bottom:14px;border-color:rgba(240,206,140,.35)" role="status">
    <h2>Set-password link for {{ session('setupLink')['name'] }}</h2>
    <p class="card-help">Send this privately, for example in a direct message. It works once, expires in {{ \App\Http\Controllers\Admin\UserController::LINK_HOURS }} hours, and isn't shown again.</p>
    <div class="form-inline" style="align-items:end"><div class="field" style="flex:1"><label for="setup-link">Link</label><input id="setup-link" value="{{ session('setupLink')['url'] }}" readonly></div><button class="button" type="button" data-copy="#setup-link">Copy</button></div>
  </div>
@endif

<section class="panel" style="margin-bottom:14px" aria-labelledby="people-heading">
  <div class="panel-head"><h2 id="people-heading">People with access</h2></div>
  <div class="table-wrap"><table class="data-table">
    <thead><tr><th scope="col">PERSON</th><th scope="col">ROLE</th><th scope="col">SIGN-IN</th><th scope="col">ACTIONS</th></tr></thead>
    <tbody>
      @foreach($users as $person)
        @php($isMe = $person->is(auth()->user()))
        <tr id="user-{{ $person->id }}">
          <td><strong style="color:#dcd8e6">{{ $person->name }}</strong>@if($isMe) <span class="status-pill" style="margin-left:4px">You</span>@endif<small>{{ $person->email }}</small></td>
          <td>{{ \App\Support\Roles::label($person->adminRole()) }}<small>{{ $roles[$person->adminRole()][1] ?? '' }}</small></td>
          <td>
            @if($person->setup_expires_at && $person->setup_expires_at->isFuture())
              <span class="status-pill warn">Invited</span><small>Link expires {{ $person->setup_expires_at->diffForHumans() }}</small>
            @elseif(isset($lastSignIns[$person->id]))
              Last signed in {{ \Illuminate\Support\Carbon::parse($lastSignIns[$person->id])->diffForHumans() }}
            @else
              Never signed in
            @endif
            <small>Two-factor {{ $person->hasTwoFactorEnabled() ? 'on' : 'off' }}</small>
          </td>
          <td>
            @if($isMe)
              <small>Ask another owner to change your access.</small>
            @else
              <details>
                <summary class="button" style="list-style:none;display:inline-flex">Manage</summary>
                <div style="display:grid;gap:10px;margin-top:8px;min-width:260px">
                  <form method="post" action="{{ route('admin.users.role', $person) }}" class="form-inline" style="align-items:end;flex-wrap:wrap">
                    @csrf @method('put')
                    <div class="field"><label for="role-{{ $person->id }}">Role</label><select id="role-{{ $person->id }}" name="role">@foreach($roles as $key => [$label])<option value="{{ $key }}" @selected($person->adminRole() === $key)>{{ $label }}</option>@endforeach</select></div>
                    <div class="field"><label for="role-pw-{{ $person->id }}">Your password</label><input id="role-pw-{{ $person->id }}" name="password" type="password" autocomplete="current-password" required></div>
                    <button class="button" type="submit">Change role</button>
                  </form>
                  <form method="post" action="{{ route('admin.users.link', $person) }}" class="form-inline" style="align-items:end;flex-wrap:wrap">
                    @csrf
                    <div class="field"><label for="link-pw-{{ $person->id }}">Your password</label><input id="link-pw-{{ $person->id }}" name="password" type="password" autocomplete="current-password" required></div>
                    <button class="button" type="submit">New set-password link</button>
                  </form>
                  <form method="post" action="{{ route('admin.users.revoke', $person) }}" style="display:grid;gap:6px">
                    @csrf @method('delete')
                    <div class="field"><label for="revoke-pw-{{ $person->id }}">Your password</label><input id="revoke-pw-{{ $person->id }}" name="password" type="password" autocomplete="current-password" required></div>
                    <label class="checkbox-row"><input type="checkbox" name="confirm" value="1" required> Remove {{ $person->name }}'s access and sign them out everywhere</label>
                    <div><button class="button button-danger" type="submit">Remove access</button></div>
                  </form>
                </div>
              </details>
            @endif
          </td>
        </tr>
      @endforeach
    </tbody>
  </table></div>
</section>

<div class="grid dashboard-columns">
  <section class="panel" aria-labelledby="invite-heading">
    <div class="panel-head"><h2 id="invite-heading">Add someone</h2></div>
    <p class="card-help">They get a one-time link to choose their own password. No password is sent to them or seen by you.</p>
    <form method="post" action="{{ route('admin.users.store') }}" class="project-form">
      @csrf
      <div class="field-grid">
        <div class="field"><label for="invite-name">Name</label><input id="invite-name" name="name" value="{{ old('name') }}" maxlength="120" required></div>
        <div class="field"><label for="invite-email">Email address</label><input id="invite-email" name="email" type="email" value="{{ old('email') }}" maxlength="255" required></div>
        <div class="field"><label for="invite-role">Role</label><select id="invite-role" name="role">@foreach($roles as $key => [$label])<option value="{{ $key }}" @selected(old('role', 'editor') === $key)>{{ $label }}</option>@endforeach</select></div>
        <div class="field"><label for="invite-pw">Your password</label><input id="invite-pw" name="password" type="password" autocomplete="current-password" required></div>
      </div>
      <div><button class="button button-primary" type="submit">Add and create link</button></div>
    </form>
  </section>

  <section class="panel" aria-labelledby="roles-heading">
    <div class="panel-head"><h2 id="roles-heading">What each role can do</h2></div>
    <div class="table-wrap"><table class="data-table">
      <thead><tr><th scope="col">PERMISSION</th>@foreach($roles as [$label])<th scope="col">{{ mb_strtoupper($label) }}</th>@endforeach</tr></thead>
      <tbody>
        @foreach($permissions as $permission => $description)
          <tr><td>{{ $description }}</td>@foreach(array_keys($roles) as $role)<td>{{ \App\Support\Roles::allows($role, $permission) ? '✓' : '—' }}</td>@endforeach</tr>
        @endforeach
      </tbody>
    </table></div>
    <p class="card-help" style="margin:10px 0 0">Everyone can use the overview, search, help and notifications, and manage their own two-factor sign-in and sessions.</p>
  </section>
</div>

<script>
  document.querySelectorAll('[data-copy]').forEach((button) => button.addEventListener('click', async () => {
    const input = document.querySelector(button.dataset.copy);
    try { await navigator.clipboard.writeText(input.value); } catch { input.select(); document.execCommand('copy'); }
    button.textContent = 'Copied';
  }));
</script>
@endsection
