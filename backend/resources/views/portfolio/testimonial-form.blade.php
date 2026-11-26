<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex,nofollow">
  <title>Leave a testimonial · {{ $ownerName }}</title>
  <link rel="icon" href="/favicon.svg" type="image/svg+xml">
  <link rel="stylesheet" href="/styles.css">
</head>
<body>
  <main class="privacy-page">
    <a class="button button-quiet" href="/">← Back to portfolio</a>
    <p class="eyebrow">TESTIMONIAL / YOUR WORDS</p>
    <h1>Leave a testimonial</h1>
    @if(session('testimonial_sent'))
      <p role="status">Thank you. Your testimonial has been received. {{ $ownerName }} will read it before it appears on the site, and may email you if anything needs checking.</p>
    @elseif(! $open)
      <p>{{ $ownerName }} isn't collecting testimonials through this page right now. If you'd like to share feedback, use the contact form on the <a href="/#contact">portfolio</a>.</p>
    @else
      <p>If you've worked with {{ $ownerName }}, a few honest sentences about the experience help other people decide. Nothing is published until it has been read and approved.</p>
      @if($errors->any())<p role="alert" style="color:#f0b5b8">{{ $errors->first() }}</p>@endif
      <form class="portfolio-contact-form" method="post" action="{{ route('testimonials.store') }}" style="margin-top:28px">
        @csrf
        <input type="hidden" name="submission_token" value="{{ $token }}">
        <label class="contact-honeypot" aria-hidden="true">Leave this field empty<input name="website" tabindex="-1" autocomplete="off"></label>
        <div class="contact-form-grid">
          <label>Your name (published)<input name="name" value="{{ old('name') }}" autocomplete="name" maxlength="120" required></label>
          <label>Email address (private)<input name="email" type="email" value="{{ old('email') }}" autocomplete="email" maxlength="255" required></label>
          <label>Your role (optional, published)<input name="role" value="{{ old('role') }}" autocomplete="organization-title" maxlength="120"></label>
          <label>Company (optional, published)<input name="company" value="{{ old('company') }}" autocomplete="organization" maxlength="120"></label>
          <label class="contact-form-wide">Your testimonial<textarea name="quote" rows="6" minlength="20" maxlength="1500" required>{{ old('quote') }}</textarea></label>
          <label>Rating (optional)<select name="rating"><option value="">Prefer not to rate</option>@foreach([5, 4, 3, 2, 1] as $stars)<option value="{{ $stars }}" @selected((string) old('rating') === (string) $stars)>{{ $stars }} of 5</option>@endforeach</select></label>
        </div>
        <label style="display:flex;gap:10px;align-items:flex-start;margin:18px 0;line-height:1.6"><input type="checkbox" name="consent" value="1" required style="margin-top:4px"> <span>I agree that {{ $ownerName }} may publish my testimonial on this website with my name and, if given, my role, company and rating. My email address stays private. I can ask for it to be removed at any time.</span></label>
        <div class="contact-form-submit"><button class="button button-primary" type="submit">Send testimonial <span aria-hidden="true">↗</span></button></div>
      </form>
      <h2>How your details are used</h2>
      <p>Your name, words and any role, company and rating you give are shown on this website once approved. Your email address is stored privately so {{ $ownerName }} can confirm the testimonial is genuine. It is never published or shared. To have your testimonial changed or removed, use the contact form on the portfolio. See the <a href="{{ route('portfolio.privacy') }}">privacy notice</a> for more.</p>
    @endif
  </main>
</body>
</html>
