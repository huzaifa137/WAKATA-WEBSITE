@extends('layouts.site')

@section('title', \App\Support\SiteContent::seo('about', 'title'))
@section('description', \App\Support\SiteContent::seo('about', 'desc'))
@section('page', 'about')

@section('content')
<section class="page-head"><div class="wrap"><div class="crumbs"><a href="{{ route('site.home') }}">Home</a><span>/</span>About</div><h1>@c('about.title')</h1><p>@c('about.intro')</p></div></section>
<section class="sec"><div class="wrap split">
  <div><h2>@c('about.heading')</h2>
  <p>@c('about.p1')</p>
  <p>@c('about.p2')</p>
  <a class="btn btn-primary" href="{{ route('site.books') }}">@c('about.button')</a></div>
  <div class="panel" style="text-align:center"><img src="{{ asset('assets/img/logo.png') }}" alt="WAKATA logo" style="width:min(320px,100%);margin:0 auto" width="420" height="404"></div>
</div></section>
<section class="sec sec-white"><div class="wrap">
  <div class="sec-head"><h2>What we stand for</h2></div>
  <div class="values">
    <div class="value"><h3>@c('about.value1_title')</h3><p>@c('about.value1_text')</p></div>
    <div class="value"><h3>@c('about.value2_title')</h3><p>@c('about.value2_text')</p></div>
    <div class="value"><h3>@c('about.value3_title')</h3><p>@c('about.value3_text')</p></div>
  </div>
</div></section>
<section class="sec"><div class="wrap">
  <div class="sec-head"><h2>What we offer</h2></div>
  <div class="cards3">
    <div class="info"><svg class="ico" viewBox="0 0 24 24" aria-hidden="true"><polyline points="20 6 9 17 4 12"/></svg><h3>@c('about.offer1_title')</h3><p>@c('about.offer1_text')</p></div>
    <div class="info"><svg class="ico" viewBox="0 0 24 24" aria-hidden="true"><polyline points="20 6 9 17 4 12"/></svg><h3>@c('about.offer2_title')</h3><p>@c('about.offer2_text')</p></div>
    <div class="info"><svg class="ico" viewBox="0 0 24 24" aria-hidden="true"><polyline points="20 6 9 17 4 12"/></svg><h3>@c('about.offer3_title')</h3><p>@c('about.offer3_text')</p></div>
  </div>
</div></section>
@endsection
