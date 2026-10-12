@extends('layouts.account-auth')

@section('title', 'Create account')
@section('page', 'register')

@section('content')
<div class="auth">
  <div class="auth-art"><svg class="planes" viewBox="0 0 900 900" preserveAspectRatio="xMaxYMid slice" aria-hidden="true"><defs><linearGradient id="ap" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#fff" stop-opacity=".18"/><stop offset="1" stop-color="#fff" stop-opacity=".02"/></linearGradient></defs><polygon points="300,380 480,350 480,500 640,470 640,780 300,830" fill="#fff" fill-opacity=".04"/><polygon points="520,170 730,135 730,330 900,296 900,690 520,750" fill="url(#ap)"/><polyline points="760,118 760,350" fill="none" stroke="#fff" stroke-opacity=".3" stroke-width="2"/></svg><span class="dots"></span>
    <a class="brand" href="{{ route('site.home') }}"><img src="{{ asset('assets/img/logo-white.png') }}" alt="" width="64" height="61"><div><b>WAKATA</b><small>Affordable quality assessment</small></div></a>
    <div><h2>Join thousands of learners and teachers.</h2><p class="lead">One account for every paper, book and holiday package you buy.</p><ul><li>@icon('check')Download your PDFs and marking guides any time</li><li>@icon('check')Track every order from payment to delivery</li><li>@icon('check')Save items and reorder in seconds</li></ul></div>
    <div class="mini-stats"><div><b>129</b><span>Past papers</span></div><div><b>23</b><span>Books</span></div><div><b>6</b><span>Exam sets</span></div></div>
  </div>
  <div class="auth-form"><div class="auth-card"><div class="brand-s"><img src="{{ asset('assets/img/logo.png') }}" alt="" width="54" height="52"><b style="font-family:var(--serif);font-size:1.5rem;color:var(--head)">WAKATA</b></div><h1>Create your account</h1><p class="lead">It takes a minute. Then every purchase appears in your dashboard.</p>
<form method="POST" action="{{ route('account.register.submit') }}" data-validate data-native data-match="password,confirm" novalidate>
@csrf
 <div class="field"><label for="name">Full name <span class="req">*</span></label><input id="name" name="name" required autocomplete="name" value="{{ old('name') }}" placeholder="e.g. Joseph Ssemakula"><span class="err">Enter your name.</span></div>
 <div class="row2"><div class="field"><label for="email">Email <span class="req">*</span></label><input id="email" name="email" type="email" required autocomplete="email" value="{{ old('email') }}" placeholder="you@school.ac.ug"><span class="err">Enter a valid email.</span></div><div class="field"><label for="phone">Phone <span class="req">*</span></label><input id="phone" name="phone" type="tel" required autocomplete="tel" value="{{ old('phone') }}" placeholder="+256 7…"><span class="err">Enter your phone number.</span></div></div>
 <div class="row2"><div class="field "><label for="type">I am a</label><select id="type" name="type" ><option value="Teacher" @selected(old('type') === 'Teacher')>Teacher</option><option value="School" @selected(old('type') === 'School')>School or bursar</option><option value="Parent" @selected(old('type') === 'Parent')>Parent</option><option value="Bookshop" @selected(old('type') === 'Bookshop')>Bookshop</option></select></div><div class="field "><label for="school">School or organisation</label><input id="school" name="school" type="text" value="{{ old('school') }}" placeholder="Optional" ></div></div>
 <div class="field"><label for="password">Password <span class="req">*</span></label><div class="pw" data-pw><input id="password" name="password" type="password" required minlength="8" autocomplete="new-password" data-strength="str" placeholder="At least 8 characters"><button type="button" aria-label="Show password">@icon('eye')</button></div><div class="strength" id="str" data-s="0"><i></i><i></i><i></i><i></i></div><span class="err">Use at least 8 characters.</span></div>
 <div class="field"><label for="confirm">Confirm password</label><div class="pw" data-pw><input id="confirm" name="confirm" type="password" required minlength="8" autocomplete="new-password" placeholder="••••••••" ><button type="button" aria-label="Show password">@icon('eye')</button></div><span class="err">Enter at least 8 characters.</span></div>
 <div class="field"><label class="check"><input type="checkbox" name="terms" required> I agree to the terms of sale and privacy notice</label><span class="err">Please accept to continue.</span></div>
 <button class="btn btn-primary btn-block" type="submit">Create account</button></form>
<p class="foot">Already registered? <a href="{{ route('account.login') }}"><b>Sign in</b></a></p><div class="foot"><a href="{{ route('site.home') }}">&larr; Back to the website</a></div></div></div>
</div>
@endsection
