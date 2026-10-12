@extends('layouts.account-auth')

@section('title', 'Forgot password')
@section('page', 'forgot')

@section('content')
<div class="auth">
  <div class="auth-art"><svg class="planes" viewBox="0 0 900 900" preserveAspectRatio="xMaxYMid slice" aria-hidden="true"><defs><linearGradient id="ap" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#fff" stop-opacity=".18"/><stop offset="1" stop-color="#fff" stop-opacity=".02"/></linearGradient></defs><polygon points="300,380 480,350 480,500 640,470 640,780 300,830" fill="#fff" fill-opacity=".04"/><polygon points="520,170 730,135 730,330 900,296 900,690 520,750" fill="url(#ap)"/><polyline points="760,118 760,350" fill="none" stroke="#fff" stroke-opacity=".3" stroke-width="2"/></svg><span class="dots"></span>
    <a class="brand" href="{{ route('site.home') }}"><img src="{{ asset('assets/img/logo-white.png') }}" alt="" width="64" height="61"><div><b>WAKATA</b><small>Affordable quality assessment</small></div></a>
    <div><h2>Your papers and orders, always within reach.</h2><p class="lead">Sign in to download the PDFs you bought, follow your orders, print invoices and reorder in one tap.</p><ul><li>@icon('check')Download your PDFs and marking guides any time</li><li>@icon('check')Track every order from payment to delivery</li><li>@icon('check')Save items and reorder in seconds</li></ul></div>
    <div class="mini-stats"><div><b>129</b><span>Past papers</span></div><div><b>23</b><span>Books</span></div><div><b>6</b><span>Exam sets</span></div></div>
  </div>
  <div class="auth-form"><div class="auth-card"><div class="brand-s"><img src="{{ asset('assets/img/logo.png') }}" alt="" width="54" height="52"><b style="font-family:var(--serif);font-size:1.5rem;color:var(--head)">WAKATA</b></div><div class="auth-ico">@icon('key')</div>@if (session('reset_sent'))
<div id="fgSent"><h1>Check your inbox</h1><p class="lead">If an account exists for <b>{{ session('reset_sent') }}</b>, a reset link is on its way. It expires in 60 minutes.</p></div>
@else
<div id="fgForm"><h1>Forgot your password?</h1><p class="lead">Enter your email and we will send you a link to choose a new one.</p>
<form method="POST" action="{{ route('account.forgot-password.send') }}" data-validate data-native novalidate>
@csrf<div class="field"><label for="email">Email address</label><input id="email" name="email" type="email" required value="{{ old('email') }}" placeholder="you@school.ac.ug"><span class="err">Enter a valid email address.</span></div><button class="btn btn-primary btn-block" type="submit">Send reset link</button></form></div>
@endif
<div class="foot"><a href="{{ route('account.login') }}">&larr; Back to sign in</a></div></div></div>
</div>
@endsection
