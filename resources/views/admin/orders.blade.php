@extends('layouts.admin')

@section('title', 'Orders')
@section('page', 'orders')

@section('content')
<div class="pagehead"><div><h1>Orders</h1><p>Everything customers have bought through the cart and checkout.</p></div><div class="ph-actions"><button class="btn btn-primary btn-sm" type="button" data-open="newOrder">@icon('plus')Create order</button></div></div>
<div class="kpis" id="orderStats"></div><div id="tbl"></div>
@endsection

@push('modals')
<div class="mback" id="newOrder" role="dialog" aria-modal="true" aria-labelledby="newOrderT"><div class="modal wide"><div class="modal-h"><h2 id="newOrderT">Create an order manually</h2><button class="btn btn-ic" type="button" data-close aria-label="Close">@icon('x')</button></div><div class="modal-b"><p class="muted sm">For phone and walk-in orders. The customer receives the same confirmation as an online order.</p>
<div class="row2"><div class="field "><label for="nc">Customer name <span class="req">*</span></label><input id="nc" name="nc" type="text" value="" placeholder="e.g. Green Hill Secondary School" required ><span class="err">This field is required.</span></div><div class="field "><label for="np">Phone <span class="req">*</span></label><input id="np" name="np" type="tel" value="" placeholder="+256 7…" required ><span class="err">This field is required.</span></div></div>
<div class="row2"><div class="field "><label for="nt">Item type</label><select id="nt" name="nt" ><option value="book">Book</option><option value="paper">Past paper</option><option value="set">Exam set</option><option value="pack">Holiday package</option></select></div><div class="field "><label for="nf">Format</label><select id="nf" name="nf" ><option value="print">Printed</option><option value="pdf">PDF download</option></select><span class="hint">Only past papers have a PDF format.</span></div></div>
<div class="row2"><div class="field "><label for="ni">Item or search</label><input id="ni" name="ni" type="text" value="" placeholder="Start typing a title…" ></div><div class="field "><label for="nq">Quantity</label><input id="nq" name="nq" type="number" value="1" placeholder="" ></div></div>
<div class="row2"><div class="field "><label for="nd">Delivery</label><select id="nd" name="nd" ><option value="0">Pick up from our shop (free)</option><option value="8000">Kampala and Wakiso (UGX 8,000)</option><option value="15000">Upcountry courier (UGX 15,000)</option></select></div><div class="field "><label for="npm">Payment</label><select id="npm" name="npm" ><option value="mtn">MTN Mobile Money</option><option value="airtel">Airtel Money</option><option value="card">Visa or Mastercard</option><option value="cash">Pay when you collect</option></select></div></div></div><div class="modal-f"><button class="btn btn-line" type="button" data-close>Cancel</button><button class="btn btn-primary" type="button" data-close data-toast="Order created (preview).">Create order</button></div></div></div>
@endpush
