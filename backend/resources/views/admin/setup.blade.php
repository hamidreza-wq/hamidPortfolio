@extends('admin.layout')
@section('title', 'Set your password')
@section('content')
<main class="login-page">
  <section class="login-card" aria-labelledby="setup-title">
    <a class="brand" href="{{ route('portfolio.home') }}" style="padding:0 0 24px">
      <span class="brand-mark">H<span>.</span></span>
      <span class="brand-copy"><strong>Hamid Portfolio</strong><small>PRIVATE ADMINISTRATION</small></span>
    </a>
    @if($user)
      <h1 id="setup-title">Welcome, {{ $user->name }}.</h1>
      <p>Choose a password for {{ $user->email }}. Use at least 12 characters; a password manager can make one for you. This link works once.</p>
      @if($errors->any())<div class="error-box" role="alert">{{ $errors->first() }}</div>@endif
      <form method="post" action="{{ route('admin.setup.store', $token) }}">
        @csrf
        <input type="email" name="email" value="{{ $user->email }}" autocomplete="username" hidden readonly>
        <div class="field"><label for="password">New password</label><input id="password" name="password" type="password" autocomplete="new-password" minlength="12" required autofocus></div>
        <div class="field"><label for="password_confirmation">Type it again</label><input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" minlength="12" required></div>
        <button class="button button-primary" type="submit">Set password</button>
      </form>
      <p class="card-help" style="margin-top:16px">After signing in, turn on two-factor sign-in on the Security page.</p>
    @else
      <h1 id="setup-title">This link has expired.</h1>
      <p>Set-password links work once and expire after {{ \App\Http\Controllers\Admin\UserController::LINK_HOURS }} hours. Ask the site owner for a new one.</p>
      <a class="button button-primary" href="{{ route('login') }}">Go to sign in</a>
    @endif
  </section>
</main>
@endsection
