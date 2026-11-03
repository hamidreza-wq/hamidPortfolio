@extends('admin.layout')
@section('title', 'Administrator sign in')
@section('content')
<main class="login-page">
  <section class="login-card" aria-labelledby="login-title">
    <a class="brand" href="{{ route('portfolio.home') }}" style="padding:0 0 24px">
      <span class="brand-mark">H<span>.</span></span>
      <span class="brand-copy"><strong>Hamid Portfolio</strong><small>PRIVATE ADMINISTRATION</small></span>
    </a>
    <h1 id="login-title">Welcome back.</h1>
    <p>Sign in to manage your portfolio. Administrator accounts are created from the server command line.</p>
    @if($errors->any())<div class="error-box" role="alert">{{ $errors->first() }}</div>@endif
    <form method="post" action="{{ route('admin.login') }}">
      @csrf
      <div class="field"><label for="email">Email address</label><input id="email" name="email" type="email" value="{{ old('email') }}" autocomplete="username" required autofocus></div>
      <div class="field"><label for="password">Password</label><input id="password" name="password" type="password" autocomplete="current-password" required></div>
      <button class="button button-primary" type="submit">Sign in securely <span aria-hidden="true">↗</span></button>
    </form>
  </section>
</main>
@endsection
