@extends('layouts.account-auth')

@section('title', 'Verify')
@section('page', 'verify')

@section('content')
<div class="auth">
  <div class="auth-art"><svg class="planes" viewBox="0 0 900 900" preserveAspectRatio="xMaxYMid slice" aria-hidden="true"><defs><linearGradient id="ap" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#fff" stop-opacity=".18"/><stop offset="1" stop-color="#fff" stop-opacity=".02"/></linearGradient></defs><polygon points="300,380 480,350 480,500 640,470 640,780 300,830" fill="#fff" fill-opacity=".04"/><polygon points="520,170 730,135 730,330 900,296 900,690 520,750" fill="url(#ap)"/><polyline points="760,118 760,350" fill="none" stroke="#fff" stroke-opacity=".3" stroke-width="2"/></svg><span class="dots"></span>
    <a class="brand" href="{{ route('site.home') }}"><img src="{{ asset('assets/img/logo-white.png') }}" alt="" width="64" height="61"><div><b>WAKATA</b><small>Affordable quality assessment</small></div></a>
    <div><h2>Your papers and orders, always within reach.</h2><p class="lead">Sign in to download the PDFs you bought, follow your orders, print invoices and reorder in one tap.</p><ul><li>@icon('check')Download your PDFs and marking guides any time</li><li>@icon('check')Track every order from payment to delivery</li><li>@icon('check')Save items and reorder in seconds</li></ul></div>
    <div class="mini-stats"><div><b>129</b><span>Past papers</span></div><div><b>23</b><span>Books</span></div><div><b>6</b><span>Exam sets</span></div></div>
  </div>
  <div class="auth-form"><div class="auth-card"><div class="brand-s"><img src="{{ asset('assets/img/logo.png') }}" alt="" width="54" height="52"><b style="font-family:var(--serif);font-size:1.5rem;color:var(--head)">WAKATA</b></div><div class="auth-ico">@icon('shield')</div><h1>Two-step verification</h1><p class="lead">Enter the 6-digit code we sent to your email. It works for 10 minutes.</p>
<form id="otpForm" method="POST" action="{{ route('account.verify.submit') }}" novalidate>
@csrf<input type="hidden" name="code" id="otpCode"><div class="otp"><input inputmode="numeric" maxlength="1" aria-label="Digit 1" autocomplete="one-time-code"><input inputmode="numeric" maxlength="1" aria-label="Digit 2" autocomplete="one-time-code"><input inputmode="numeric" maxlength="1" aria-label="Digit 3" autocomplete="one-time-code"><input inputmode="numeric" maxlength="1" aria-label="Digit 4" autocomplete="one-time-code"><input inputmode="numeric" maxlength="1" aria-label="Digit 5" autocomplete="one-time-code"><input inputmode="numeric" maxlength="1" aria-label="Digit 6" autocomplete="one-time-code"></div><button class="btn btn-primary btn-block" type="submit">Verify and continue</button></form>
<p class="foot" style="margin-top:1rem">Did not get a code? <a href="#" id="resend" data-url="{{ route('account.verify.resend') }}">Resend code</a></p><div class="foot"><a href="{{ route('account.login') }}">&larr; Use a different account</a></div></div></div>
</div>
@endsection
