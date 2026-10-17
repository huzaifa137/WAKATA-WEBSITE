@extends('layouts.admin')

@section('title', 'Orders')
@section('page', 'orders')
@section('sales', '1')

@section('content')
<div class="pagehead"><div><h1>Orders</h1><p>Everything customers have bought through the cart and checkout.</p></div><div class="ph-actions"><button class="btn btn-primary btn-sm" type="button" data-open="newOrder" id="newOrderBtn">@icon('plus')Create order</button></div></div>
<div class="kpis" id="orderStats"></div><div id="tbl"></div>
@endsection

@push('modals')
<div class="mback" id="newOrder" role="dialog" aria-modal="true" aria-labelledby="newOrderT"><div class="modal wide"><div class="modal-h"><h2 id="newOrderT">Create an order manually</h2><button class="btn btn-ic" type="button" data-close aria-label="Close">@icon('x')</button></div><div class="modal-b"><p class="muted sm">For phone and walk-in orders. The customer receives the same confirmation as an online order.</p>
<div class="row2"><div class="field "><label for="nc">Customer name <span class="req">*</span></label><input id="nc" name="nc" type="text" value="" placeholder="e.g. Green Hill Secondary School" required maxlength="120"><span class="err">This field is required.</span></div><div class="field "><label for="np">Phone <span class="req">*</span></label><input id="np" name="np" type="tel" value="" placeholder="+256 7…" required maxlength="30"><span class="err">This field is required.</span></div></div>
<div class="row2"><div class="field "><label for="ne">Email</label><input id="ne" name="ne" type="email" value="" placeholder="Optional, for the confirmation" maxlength="190"><span class="hint">The confirmation is only sent when there is an email.</span></div><div class="field "><label for="nt">Item type</label><select id="nt" name="nt" ><option value="book">Book</option><option value="paper">Past paper</option><option value="set">Exam set</option><option value="pack">Holiday package</option></select></div></div>
<div class="row2"><div class="field "><label for="ni">Item <span class="req">*</span></label><input id="ni" name="ni" type="text" value="" placeholder="Start typing a title…" list="niList" autocomplete="off" required><datalist id="niList"></datalist><span class="err">Pick an item from the list.</span></div><div class="field "><label for="nf">Format</label><select id="nf" name="nf" ><option value="print">Printed</option><option value="pdf">PDF download</option></select><span class="hint">Only past papers have a PDF format.</span></div></div>
<div class="row2"><div class="field "><label for="nq">Quantity</label><input id="nq" name="nq" type="number" value="1" min="1" max="5000" placeholder="" ></div><div class="field "><label for="nd">Delivery</label><select id="nd" name="nd" >@foreach (collect(config('wakata.delivery_all'))->only(\App\Support\ShopSettings::EDITABLE_DELIVERY) as $key => $d)<option value="{{ $key }}">{{ $d['label'] }} ({{ $d['fee'] ? 'UGX ' . number_format($d['fee']) : 'free' }})</option>@endforeach</select></div></div>
<div class="field" id="naField" hidden><label for="na">Delivery address</label><textarea id="na" name="na" maxlength="500" placeholder="Where should it be delivered?"></textarea></div>
<div class="field "><label for="npm">Payment</label><select id="npm" name="npm" >@foreach (config('wakata.payments_all') as $key => $label)<option value="{{ $key }}">{{ $label }}</option>@endforeach</select><span class="hint">Cash is only for orders collected from the shop.</span></div></div><div class="modal-f"><button class="btn btn-line" type="button" data-close>Cancel</button><button class="btn btn-primary" type="button" id="newOrderSave">Create order</button></div></div></div>
@endpush
