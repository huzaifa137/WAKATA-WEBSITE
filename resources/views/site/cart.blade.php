@extends('layouts.site')

@section('title', 'Cart and checkout | WAKATA Stationers')
@section('page', 'cartPage')

@section('content')
<section class="page-head"><div class="wrap"><div class="crumbs"><a href="{{ route('site.home') }}">Home</a><span>/</span>Cart</div><h1>Your cart</h1><p>Review your items, choose how you want to receive them and pay.</p></div></section>
<div class="wrap">
  <div id="cart-empty" class="panel cart-empty" style="margin-block:2.4rem 4rem" hidden><svg class="ico" viewBox="0 0 24 24" aria-hidden="true"><circle cx="9" cy="20" r="1.5"/><circle cx="18" cy="20" r="1.5"/><path d="M2 3h3l2.5 12.5a1 1 0 0 0 1 .8h9.2a1 1 0 0 0 1-.8L21 7H6"/></svg><h2>Your cart is empty</h2><p class="muted">Add books, past papers or a holiday package to get started.</p><a class="btn btn-primary" href="{{ route('site.books') }}">Shop books</a> <a class="btn btn-line" href="{{ route('site.papers') }}">Find past papers</a></div>
  <div id="cart-wrap" class="cartgrid">
    <div class="panel"><h2>Items</h2><div id="lines"></div><button class="btn btn-line btn-sm" id="clear" type="button" style="margin-top:1rem">Clear cart</button></div>
    <form class="panel" id="checkout" novalidate>
      <h2>Order summary</h2><div id="summary"></div>
      <div id="delivery-box" style="margin-top:1.2rem"><h2>Delivery</h2><div class="radios">
        <label class="radio"><input type="radio" name="delivery" value="0" checked><span><b>Pick up from our shop</b><small>Ready within one working day</small></span><em>Free</em></label>
        <label class="radio"><input type="radio" name="delivery" value="8000"><span><b>Kampala and Wakiso</b><small>1 to 2 days</small></span><em>UGX 8,000</em></label>
        <label class="radio"><input type="radio" name="delivery" value="15000"><span><b>Upcountry courier</b><small>2 to 4 days</small></span><em>UGX 15,000</em></label></div></div>
      <h2 style="margin-top:1.2rem">Your details</h2>
      <div class="field"><label for="o-name">Full name</label><input id="o-name" required autocomplete="name"><span class="err">Enter your name.</span></div>
      <div class="row2"><div class="field"><label for="o-phone">Phone (mobile money)</label><input id="o-phone" type="tel" required autocomplete="tel"><span class="err">Enter a valid phone number.</span></div>
      <div class="field"><label for="o-email">Email</label><input id="o-email" type="email" required autocomplete="email"><span class="err">Enter a valid email address.</span></div></div>
      <div class="field" id="addr-field" hidden><label for="o-addr">Delivery address or school</label><textarea id="o-addr" style="min-height:80px" required></textarea><span class="err">Enter where we should deliver.</span></div>
      <h2 style="margin-top:.6rem">Payment</h2>
      <div class="radios">
        <label class="radio"><input type="radio" name="pay" value="mtn" data-name="MTN Mobile Money"><span><b>MTN Mobile Money</b><small>Approve the prompt on your phone</small></span></label>
        <label class="radio"><input type="radio" name="pay" value="airtel" data-name="Airtel Money"><span><b>Airtel Money</b><small>Approve the prompt on your phone</small></span></label>
        <label class="radio"><input type="radio" name="pay" value="card" data-name="Visa or Mastercard"><span><b>Visa or Mastercard</b><small>Secure card payment</small></span></label>
        <label class="radio"><input type="radio" name="pay" value="cash" data-name="cash on pickup"><span><b>Pay when you collect</b><small>Cash at our shop</small></span></label></div>
      <button class="btn btn-primary btn-block" type="submit">Place order</button>
      <p class="muted" style="font-size:.82rem;margin:.8rem 0 0">This is a design preview. No payment is taken.</p>
    </form>
  </div>
</div>
<div class="modal" id="modal" role="dialog" aria-modal="true" aria-labelledby="mt"><div class="box"><div class="okc"><svg viewBox="0 0 24 24"><polyline points="4 13 10 19 21 6"/></svg></div>
  <h2 id="mt">Order received</h2><p>Your order reference is <b id="ref"></b>. We will confirm your <span id="pay-name"></span> payment by phone or email.</p><p class="note">Design preview only. No real order was created.</p><button class="btn btn-primary" type="button" style="margin-top:1rem">Back to home</button></div></div>
@endsection
