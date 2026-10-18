@extends('layouts.site')

@section('title', \App\Support\SiteContent::seo('packages', 'title'))
@section('description', \App\Support\SiteContent::seo('packages', 'desc'))
@section('page', 'packages')

@section('content')
<section class="page-head"><div class="wrap"><div class="crumbs"><a href="{{ route('site.home') }}">Home</a><span>/</span>Holiday packages</div><h1>Holiday packages</h1><p>Workbooks, revision tests and mock papers in one bundle, so learning carries on while school is out.</p></div></section><div class="wrap" style="padding-block:2.4rem 3rem"><div class="tabs" id="tabs" role="group" aria-label="Filter by level"></div><div class="grid grid-pk" id="grid" style="padding-top:.8rem"></div></div><section class="sec sec-white" id="faq"><div class="wrap">
  <div class="sec-head"><div><h2>Questions about packages</h2><p>Quick answers about ordering, swaps and what is inside.</p></div></div>
  <div class="faq-grid">
    @foreach ($faqs as $f)
    <details class="faq-item"><summary><span class="q-ico">{!! $f->svg() !!}</span><span class="q-txt">{{ $f->question }}</span><span class="q-tog" aria-hidden="true"></span></summary><p>{{ $f->answer }}</p></details>
    @endforeach
  
  </div>
  <div class="faq-cta">
    <div><h3>Need help choosing a package?</h3><p>Tell us the level and subjects and we will suggest the right one.</p></div>
    <div class="acts"><a class="btn btn-hl" href="tel:{{ config('wakata.phone_tel') }}">Call us</a><a class="btn btn-ghost-w" href="{{ route('site.contact') }}">Send a message</a></div>
  </div>
</div></section>
@endsection
