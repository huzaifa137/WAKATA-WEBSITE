@extends('layouts.site')

@section('title')About | WAKATA Stationers@endsection
@section('page')about@endsection

@section('content')
<section class="page-head"><div class="wrap"><div class="crumbs"><a href="{{ route('site.home') }}">Home</a><span>/</span>About</div><h1>About WAKATA</h1><p>The Wakiso Kampala Teachers' Association brings teachers together to make good assessment affordable for every learner.</p></div></section>
<section class="sec"><div class="wrap split">
  <div><h2>Written by teachers, for teachers</h2>
  <p>WAKATA is an association of teachers from Wakiso and Kampala. Every paper, workbook and revision guide we publish is written and checked by people who teach the syllabus every day.</p>
  <p>We believe a school should not need a large budget to give its candidates a proper mock examination. That is why our papers are priced for schools of every size, and why we sell them as printed copies, PDFs or whole sets.</p>
  <a class="btn btn-primary" href="{{ route('site.books') }}">Browse the shop</a></div>
  <div class="panel" style="text-align:center"><img src="{{ asset('assets/img/logo.png') }}" alt="WAKATA logo" style="width:min(320px,100%);margin:0 auto" width="420" height="404"></div>
</div></section>
<section class="sec sec-white"><div class="wrap">
  <div class="sec-head"><h2>What we stand for</h2></div>
  <div class="values">
    <div class="value"><h3>Affordable</h3><p>Fair prices, with discounts on full sets and holiday packages.</p></div>
    <div class="value"><h3>Quality</h3><p>Papers follow the national format, with marking guides teachers can trust.</p></div>
    <div class="value"><h3>Practical</h3><p>Real science means practical. Our workbooks put experiments in learners' hands.</p></div>
  </div>
</div></section>
<section class="sec"><div class="wrap">
  <div class="sec-head"><h2>What we offer</h2></div>
  <div class="cards3">
    <div class="info"><svg class="ico" viewBox="0 0 24 24" aria-hidden="true"><polyline points="20 6 9 17 4 12"/></svg><h3>Mock examinations</h3><p>PLE, UCE and UACE papers for schools across the region.</p></div>
    <div class="info"><svg class="ico" viewBox="0 0 24 24" aria-hidden="true"><polyline points="20 6 9 17 4 12"/></svg><h3>Textbooks and workbooks</h3><p>Biology, Chemistry, Physics, Agriculture and more.</p></div>
    <div class="info"><svg class="ico" viewBox="0 0 24 24" aria-hidden="true"><polyline points="20 6 9 17 4 12"/></svg><h3>Past papers and sets</h3><p>Buy one paper, or a whole series at a lower price.</p></div>
  </div>
</div></section>
@endsection
