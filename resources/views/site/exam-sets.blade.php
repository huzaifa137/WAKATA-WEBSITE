@extends('layouts.site')

@section('title', 'Exam sets | WAKATA Stationers')
@section('page', 'sets')

@section('content')
<section class="page-head"><div class="wrap"><div class="crumbs"><a href="{{ route('site.home') }}">Home</a><span>/</span>Exam sets</div><h1>Complete exam sets</h1><p>A whole mock series in one purchase. Cheaper than buying papers one by one, and ready to print for a full class.</p></div></section><div class="wrap" style="padding-block:2.4rem 4rem"><div class="tabs" id="tabs" role="group" aria-label="Filter by level"></div><div class="grid grid-sets" id="grid"></div></div><section class="sec sec-white"><div class="wrap"><div class="sec-head"><div><h2>Why buy a full set?</h2></div></div><div class="cards3"><div class="info"><svg class="ico" viewBox="0 0 24 24" aria-hidden="true"><polyline points="20 6 9 17 4 12"/></svg><h3>Save on every paper</h3><p>Sets cost less than the same papers bought separately.</p></div><div class="info"><svg class="ico" viewBox="0 0 24 24" aria-hidden="true"><polyline points="20 6 9 17 4 12"/></svg><h3>Marking guides included</h3><p>Every set comes with marking schemes for each paper.</p></div><div class="info"><svg class="ico" viewBox="0 0 24 24" aria-hidden="true"><polyline points="20 6 9 17 4 12"/></svg><h3>One order, one delivery</h3><p>Papers are packed together and labelled by subject.</p></div></div></div></section>
@endsection
