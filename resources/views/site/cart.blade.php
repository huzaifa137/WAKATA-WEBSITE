@extends('layouts.site')

@section('title', \App\Support\SiteContent::seo('cart', 'title'))
@section('description', \App\Support\SiteContent::seo('cart', 'desc'))
@section('page', 'cartPage')

@section('content')
<section class="page-head"><div class="wrap"><div class="crumbs"><a href="{{ route('site.home') }}">Home</a><span>/</span>Cart</div><h1>@c('cart.title')</h1><p>@c('cart.intro')</p></div></section>
<div class="wrap">
  <div id="cart-empty" class="panel cart-empty" style="margin-block:2.4rem 4rem" hidden><svg class="ico" viewBox="0 0 24 24" aria-hidden="true"><circle cx="9" cy="20" r="1.5"/><circle cx="18" cy="20" r="1.5"/><path d="M2 3h3l2.5 12.5a1 1 0 0 0 1 .8h9.2a1 1 0 0 0 1-.8L21 7H6"/></svg><h2>@c('cart.empty_title')</h2><p class="muted">@c('cart.empty_text')</p><a class="btn btn-primary" href="{{ route('site.books') }}">Shop books</a> <a class="btn btn-line" href="{{ route('site.papers') }}">Find past papers</a></div>
  <div id="cart-wrap" class="cartgrid">
    <div class="panel"><h2>Items</h2><div id="lines"></div><button class="btn btn-line btn-sm" id="clear" type="button" style="margin-top:1rem">Clear cart</button></div>
    <form class="panel" id="checkout" novalidate>
      <h2>Order summary</h2><div id="summary"></div>
      <div id="delivery-box" style="margin-top:1.2rem"><h2>Delivery</h2><div class="radios">
        @foreach (collect(config('wakata.delivery'))->except('email') as $key => $d)
        <label class="radio"><input type="radio" name="delivery" value="{{ $key }}"{{ $loop->first ? ' checked' : '' }}><span><b>{{ $d['label'] }}</b><small>{{ $d['eta'] }}</small></span><em>{{ $d['fee'] ? 'UGX ' . number_format($d['fee']) : 'Free' }}</em></label>
        @endforeach</div></div>
      <h2 style="margin-top:1.2rem">Your details</h2>
      <div class="field"><label for="o-name">Full name</label><input id="o-name" name="name" required autocomplete="name" maxlength="120"><span class="err">Enter your name.</span></div>
      <div class="row2"><div class="field"><label for="o-phone">Phone (mobile money)</label><input id="o-phone" name="phone" type="tel" required autocomplete="tel" maxlength="30"><span class="err">Enter a valid phone number.</span></div>
      <div class="field"><label for="o-email">Email</label><input id="o-email" name="email" type="email" required autocomplete="email" maxlength="190"><span class="err">Enter a valid email address.</span></div></div>
      <div class="field" id="addr-field" hidden><label for="o-addr">Delivery address or school</label><textarea id="o-addr" name="address" style="min-height:80px" required maxlength="500"></textarea><span class="err">Enter where we should deliver.</span></div>
      <h2 style="margin-top:.6rem">Payment</h2>
      <div class="radios">
        @php($payInfo = [
            'mtn'    => ['MTN Mobile Money', 'We contact you to complete payment', 'MTN Mobile Money'],
            'airtel' => ['Airtel Money', 'We contact you to complete payment', 'Airtel Money'],
            'card'   => ['Visa or Mastercard', 'We send you a secure payment link', 'Visa or Mastercard'],
            'cash'   => ['Pay when you collect', 'Cash at our shop', 'cash on pickup'],
        ])
        @foreach (array_keys(config('wakata.payments')) as $method)
        <label class="radio"><input type="radio" name="pay" value="{{ $method }}" data-name="{{ $payInfo[$method][2] }}"><span><b>{{ $payInfo[$method][0] }}</b><small>{{ $payInfo[$method][1] }}</small></span></label>
        @endforeach</div>
      <button class="btn btn-primary btn-block" type="submit">Place order</button>
      <p class="muted" style="font-size:.82rem;margin:.8rem 0 0">Nothing is charged on this page. We confirm your payment by phone or email before your order is prepared.</p>
    </form>
  </div>
</div>
<div class="modal" id="modal" role="dialog" aria-modal="true" aria-labelledby="mt"><div class="box"><div class="okc"><svg viewBox="0 0 24 24"><polyline points="4 13 10 19 21 6"/></svg></div>
  <h2 id="mt">@c('cart.done_title')</h2><p>{!! \App\Support\SiteContent::orderDone() !!}</p><p class="note">We have emailed your order details to <b id="ref-email"></b>.</p><button class="btn btn-primary" type="button" style="margin-top:1rem">Back to home</button></div></div>
@endsection
