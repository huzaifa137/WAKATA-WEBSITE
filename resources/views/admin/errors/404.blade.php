@extends('layouts.admin-auth')

@section('title', 'Not found')
@section('page', '404')

@section('content')
<div class="auth">
  <div class="auth-art"><svg class="planes" viewBox="0 0 900 900" preserveAspectRatio="xMaxYMid slice" aria-hidden="true"><defs><linearGradient id="ap" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#fff" stop-opacity=".18"/><stop offset="1" stop-color="#fff" stop-opacity=".02"/></linearGradient></defs><polygon points="300,380 480,350 480,500 640,470 640,780 300,830" fill="#fff" fill-opacity=".04"/><polygon points="520,170 730,135 730,330 900,296 900,690 520,750" fill="url(#ap)"/><polyline points="760,118 760,350" fill="none" stroke="#fff" stroke-opacity=".3" stroke-width="2"/></svg><span class="dots"></span>
    <a class="brand" href="{{ route('admin.login') }}"><img src="{{ asset('assets/img/logo-white.png') }}" alt="" width="64" height="61"><div><b>WAKATA</b><small>Admin console</small></div></a>
    <div><h2>Run the WAKATA shop from one place.</h2><p class="lead">Manage books, past papers, exam sets and holiday packages, follow every order and payment, and see how sales are going.</p><ul><li>@icon('check')Update the catalogue, prices and PDFs in minutes</li><li>@icon('check')Track orders, payments and deliveries</li><li>@icon('check')Edit what the website says, without touching code</li></ul></div>
    <div class="mini-stats"><div><b>23</b><span>Books</span></div><div><b>129</b><span>Past papers</span></div><div><b>6</b><span>Exam sets</span></div></div>
  </div>
  <div class="auth-form"><div class="auth-card"><div class="brand-s"><img src="{{ asset('assets/img/logo.png') }}" alt="" width="54" height="52"><b style="font-family:var(--serif);font-size:1.5rem;color:var(--head)">WAKATA Admin</b></div><div class="auth-ico">@icon('search')</div><h1>Page not found</h1><p class="lead">That page does not exist in the admin console.</p><a class="btn btn-primary btn-block" href="{{ route('admin.dashboard') }}">Go to the dashboard</a></div></div>
</div>
@endsection
