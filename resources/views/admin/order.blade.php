@extends('layouts.admin')

@section('title', 'Order')
@section('page', 'order')
@section('sales', '1')

@section('content')
<div class="pagehead"><div><div class="crumbs"><a href="{{ route('admin.dashboard') }}">Dashboard</a><span>/</span><a href="{{ route('admin.orders') }}">Orders</a><span>/</span><span data-ref-crumb></span></div><h1>Order <span data-ref></span></h1><p>Review the order, update its status and contact the customer.</p></div><div class="ph-actions"><button class="btn btn-line btn-sm" type="button" data-print>@icon('print')Print invoice</button><button class="btn btn-line btn-sm" type="button" data-print>@icon('box')Packing slip</button><button class="btn btn-line btn-sm" type="button" id="oEmail">@icon('mail')Email customer</button><button class="btn btn-danger-l btn-sm" type="button" id="oCancel">@icon('x')Cancel order</button></div></div>
<div class="layout-2"><div class="stack">
 <section class="card"><div class="card-h"><div><h2>Progress</h2><span class="sub" id="oMeta"></span></div></div><div class="card-b"><div class="stepper" id="stepper"></div></div></section>
 <section class="card"><div class="card-h"><h2>Items</h2></div><div class="card-b flush"><div class="tbl-wrap"><table class="tbl"><thead><tr><th>Item</th><th class="num">Price</th><th class="num">Qty</th><th class="num">Line total</th></tr></thead><tbody id="oItems"></tbody></table></div><div style="padding:1rem 1.2rem"><div class="totals" id="oTotals"></div></div></div></section>
 <section class="card" id="oPdf" hidden><div class="card-h"><div><h2>PDF delivery</h2><span class="sub">Digital items are emailed once payment is confirmed</span></div></div><div class="card-b" id="oPdfList"></div></section>
 <section class="card"><div class="card-h"><h2>History</h2></div><div class="card-b"><ul class="timeline" id="oTimeline"></ul></div></section>
</div><div class="rail stack">
 <section class="card "><div class="card-h"><div><h2>Update order</h2></div></div><div class="card-b"><div class="field"><label for="oStatus">Order status</label><select id="oStatus"></select></div><div class="field"><label class="check"><input type="checkbox" id="oNotify" checked> Email the customer about this change</label></div><button class="btn btn-primary btn-block" id="oSave" type="button">Save status</button></div></section>
 <section class="card "><div class="card-h"><div><h2>Customer</h2></div></div><div class="card-b"><div id="oCust"></div></div></section>
 <section class="card "><div class="card-h"><div><h2>Delivery</h2></div></div><div class="card-b"><div id="oDelivery"></div></div></section>
 <section class="card "><div class="card-h"><div><h2>Payment</h2></div></div><div class="card-b"><div id="oPay"></div></div></section>
 <section class="card "><div class="card-h"><div><h2>Internal note</h2></div></div><div class="card-b"><div class="field"><textarea id="oNote" placeholder="Only your team can see this."></textarea></div><button class="btn btn-line btn-sm" type="button" id="oNoteSave">Save note</button></div></section>
</div></div>
@endsection
