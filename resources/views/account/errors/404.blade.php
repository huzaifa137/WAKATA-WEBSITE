@extends('layouts.account-auth')

@section('title', 'Not found')
@section('page', '404')

@section('content')
<div class="auth">
  <div class="auth-art"><svg class="planes" viewBox="0 0 900 900" preserveAspectRatio="xMaxYMid slice" aria-hidden="true"><defs><linearGradient id="ap" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#fff" stop-opacity=".18"/><stop offset="1" stop-color="#fff" stop-opacity=".02"/></linearGradient></defs><polygon points="300,380 480,350 480,500 640,470 640,780 300,830" fill="#fff" fill-opacity=".04"/><polygon points="520,170 730,135 730,330 900,296 900,690 520,750" fill="url(#ap)"/><polyline points="760,118 760,350" fill="none" stroke="#fff" stroke-opacity=".3" stroke-width="2"/></svg><span class="dots"></span>
    <a class="brand" href="{{ route('site.home') }}"><img src="{{ asset('assets/img/logo-white.png') }}" alt="" width="64" height="61"><div><b>WAKATA</b><small>Affordable quality assessment</small></div></a>
    <div><h2>Your papers and orders, always within reach.</h2><p class="lead">Sign in to download the PDFs you bought, follow your orders, print invoices and reorder in one tap.</p><ul><li>@icon('check')Download your PDFs and marking guides any time</li><li>@icon('check')Track every order from payment to delivery</li><li>@icon('check')Save items and reorder in seconds</li></ul></div>
    <div class="mini-stats"><div><b>129</b><span>Past papers</span></div><div><b>23</b><span>Books</span></div><div><b>6</b><span>Exam sets</span></div></div>
  </div>
  <div class="auth-form"><div class="auth-card"><div class="brand-s"><img src="{{ asset('assets/img/logo.png') }}" alt="" width="54" height="52"><b style="font-family:var(--serif);font-size:1.5rem;color:var(--head)">WAKATA</b></div><div class="auth-ico">@icon('search')</div><h1>Page not found</h1><p class="lead">That page does not exist in your account.</p><a class="btn btn-primary btn-block" href="{{ route('account.dashboard') }}">Go to my dashboard</a></div></div>
</div>
@endsection
