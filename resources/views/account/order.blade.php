@extends('layouts.account')

@section('title', 'Order')
@section('page', 'order')

@section('content')
<div class="pagehead"><div><div class="crumbs"><a href="{{ route('account.dashboard') }}">Dashboard</a><span>/</span><a href="{{ route('account.orders') }}">My orders</a><span>/</span><span data-ref></span></div><h1>Order <span data-ref></span></h1><p>Everything about this order in one place.</p></div><div class="ph-actions"></div></div>
<div id="orderRoot"><div class="layout-2"><div class="stack">
 <section class="card"><div class="card-h"><div><h2>Order progress</h2><span class="sub" id="oMeta"></span></div></div><div class="card-b"><div class="stepper" id="stepper"></div></div></section>
 <section class="card"><div class="card-h"><h2>Items</h2></div><div class="card-b flush"><div class="tbl-wrap"><table class="tbl"><thead><tr><th>Item</th><th class="num">Price</th><th class="num">Qty</th><th class="num">Total</th><th></th></tr></thead><tbody id="oItems"></tbody></table></div><div style="padding:1rem 1.2rem"><div class="totals" id="oTotals"></div></div></div></section>
 <section class="card" id="oExtra" hidden><div class="card-h"><div><h2>Included downloads</h2><span class="sub">Free extras that come with this order</span></div></div><div class="card-b" id="oExtraList"></div></section>
 <section class="card"><div class="card-h"><h2>History</h2></div><div class="card-b"><ul class="timeline" id="oTimeline"></ul></div></section>
</div><div class="rail stack">
 <section class="card "><div class="card-h"><div><h2>What would you like to do?</h2></div></div><div class="card-b"><div class="flex wrapf" id="oActions"></div></div></section>
 <section class="card "><div class="card-h"><div><h2>Delivery</h2></div></div><div class="card-b"><div id="oDelivery"></div></div></section>
 <section class="card "><div class="card-h"><div><h2>Payment</h2></div></div><div class="card-b"><div id="oPay"></div></div></section>
</div></div></div>
@endsection
