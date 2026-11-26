@extends('admin.layout')
@section('title', 'Two-factor check')
@section('content')
<main class="login-page">
  <section class="login-card" aria-labelledby="challenge-title">
    <a class="brand" href="{{ route('portfolio.home') }}" style="padding:0 0 24px">
      <span class="brand-mark">H<span>.</span></span>
      <span class="brand-copy"><strong>Hamid Portfolio</strong><small>PRIVATE ADMINISTRATION</small></span>
    </a>
    <h1 id="challenge-title">One more step.</h1>
    <p>Enter the 6-digit code from your authenticator app. This check expires five minutes after your password was accepted.</p>
    @if($errors->any())<div class="error-box" role="alert">{{ $errors->first() }}</div>@endif
    <form method="post" action="{{ route('admin.two-factor.verify') }}">
      @csrf
      <div class="field"><label for="code">Authenticator code</label><input id="code" name="code" inputmode="numeric" pattern="[0-9 ]*" maxlength="7" autocomplete="one-time-code" autofocus></div>
      <button class="button button-primary" type="submit">Verify and sign in <span aria-hidden="true">↗</span></button>
    </form>
    <details style="margin-top:18px;color:#a6a2b3;font-size:10px">
      <summary style="cursor:pointer">Lost your phone? Use a recovery code</summary>
      <form method="post" action="{{ route('admin.two-factor.verify') }}" style="margin-top:12px">
        @csrf
        <div class="field"><label for="recovery_code">Recovery code</label><input id="recovery_code" name="recovery_code" autocomplete="off" spellcheck="false" placeholder="xxxxx-xxxxx"></div>
        <button class="button" type="submit">Use recovery code</button>
      </form>
    </details>
  </section>
</main>
@endsection
