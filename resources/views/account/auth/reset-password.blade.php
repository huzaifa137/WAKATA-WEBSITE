@extends('layouts.account-auth')

@section('title', 'Reset password')
@section('page', 'reset')

@section('content')
<div class="auth">
  <div class="auth-art"><svg class="planes" viewBox="0 0 900 900" preserveAspectRatio="xMaxYMid slice" aria-hidden="true"><defs><linearGradient id="ap" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#fff" stop-opacity=".18"/><stop offset="1" stop-color="#fff" stop-opacity=".02"/></linearGradient></defs><polygon points="300,380 480,350 480,500 640,470 640,780 300,830" fill="#fff" fill-opacity=".04"/><polygon points="520,170 730,135 730,330 900,296 900,690 520,750" fill="url(#ap)"/><polyline points="760,118 760,350" fill="none" stroke="#fff" stroke-opacity=".3" stroke-width="2"/></svg><span class="dots"></span>
    <a class="brand" href="{{ route('site.home') }}"><img src="{{ asset('assets/img/logo-white.png') }}" alt="" width="64" height="61"><div><b>WAKATA</b><small>Affordable quality assessment</small></div></a>
    <div><h2>Your papers and orders, always within reach.</h2><p class="lead">Sign in to download the PDFs you bought, follow your orders, print invoices and reorder in one tap.</p><ul><li>@icon('check')Download your PDFs and marking guides any time</li><li>@icon('check')Track every order from payment to delivery</li><li>@icon('check')Save items and reorder in seconds</li></ul></div>
    <div class="mini-stats"><div><b>129</b><span>Past papers</span></div><div><b>23</b><span>Books</span></div><div><b>6</b><span>Exam sets</span></div></div>
  </div>
  <div class="auth-form"><div class="auth-card"><div class="brand-s"><img src="{{ asset('assets/img/logo.png') }}" alt="" width="54" height="52"><b style="font-family:var(--serif);font-size:1.5rem;color:var(--head)">WAKATA</b></div><div class="auth-ico">@icon('lock')</div><h1>Choose a new password</h1><p class="lead">Use at least 8 characters with letters, numbers and a symbol.</p>
<form method="POST" action="{{ route('account.reset-password.update') }}" data-validate data-native data-match="password,confirm" novalidate>
@csrf<input type="hidden" name="token" value="{{ $token }}"><input type="hidden" name="email" value="{{ $email }}">
 <div class="field"><label for="password">New password</label><div class="pw" data-pw><input id="password" name="password" type="password" required minlength="8" autocomplete="new-password" data-strength="str" placeholder="••••••••"><button type="button" aria-label="Show password">@icon('eye')</button></div><div class="strength" id="str" data-s="0"><i></i><i></i><i></i><i></i></div><span class="err">Use at least 8 characters.</span></div>
 <div class="field"><label for="confirm">Confirm new password</label><div class="pw" data-pw><input id="confirm" name="confirm" type="password" required minlength="8" autocomplete="new-password" placeholder="••••••••" ><button type="button" aria-label="Show password">@icon('eye')</button></div><span class="err">Enter at least 8 characters.</span></div>
 <button class="btn btn-primary btn-block" type="submit">Update password</button></form><div class="foot"><a href="{{ route('account.login') }}">&larr; Back to sign in</a></div></div></div>
</div>
@endsection
