@extends('layouts.site')

@section('title', 'Book | WAKATA Stationers')
@section('page', 'detail')

@section('content')

<div class="wrap"><div class="crumbs crumbs-l" style="color:var(--muted);padding-top:1.4rem;margin:0"><a href="{{ route('site.home') }}">Home</a><span>/</span><a href="{{ route('site.books') }}">Books</a><span>/</span><span id="d-crumb" style="margin:0"></span></div></div>
<div class="wrap detail">
  <div><div class="stage" id="d-stage"></div><div class="thumbs" id="d-thumbs"></div></div>
  <div>
    <span class="bk-meta" id="d-meta"></span>
    <h1 id="d-title"></h1>
    <span class="price" id="d-price"></span>
    <p id="d-desc" style="max-width:60ch"></p>
    <div class="buy">
      <div class="qty"><button type="button" id="q-minus" aria-label="Decrease quantity">&minus;</button><input id="d-qty" value="1" inputmode="numeric" aria-label="Quantity"><button type="button" id="q-plus" aria-label="Increase quantity">+</button></div>
      <button class="btn btn-primary" id="d-add" type="button"><svg class="ico" viewBox="0 0 24 24" aria-hidden="true"><circle cx="9" cy="20" r="1.5"/><circle cx="18" cy="20" r="1.5"/><path d="M2 3h3l2.5 12.5a1 1 0 0 0 1 .8h9.2a1 1 0 0 0 1-.8L21 7H6"/></svg>Add to cart</button>
      <button class="btn btn-hl" id="d-buy" type="button">Buy now</button>
    </div>
    <table class="specs"><tbody id="d-specs"></tbody></table>
    <div class="assure"><div><svg class="ico" viewBox="0 0 24 24" aria-hidden="true"><rect x="1" y="6" width="13" height="10" rx="1"/><path d="M14 9h4l3 3v4h-7"/><circle cx="6" cy="18" r="2"/><circle cx="17" cy="18" r="2"/></svg>Free pickup or delivery</div><div><svg class="ico" viewBox="0 0 24 24" aria-hidden="true"><path d="M5 4h4l2 5-2.5 1.5a11 11 0 0 0 5 5L15 13l5 2v4a2 2 0 0 1-2 2A16 16 0 0 1 3 6a2 2 0 0 1 2-2z"/></svg>Pay with mobile money</div><div><svg class="ico" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3l8 3v6c0 5-3.5 8-8 9-4.5-1-8-4-8-9V6z"/><polyline points="9 12 11 14 15 10"/></svg>Set by teachers</div></div>
  </div>
</div>
<section class="sec sec-white"><div class="wrap"><div class="sec-head"><h2>More you may need</h2></div><div class="grid grid-books grid-4" id="d-related"></div></div></section>
@endsection
