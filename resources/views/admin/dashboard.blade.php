@extends('layouts.admin')

@section('title', 'Dashboard')
@section('page', 'dashboard')
@section('sales', '1')
@section('ins', '1')

@section('content')
<div class="pagehead"><div><h1>Dashboard</h1><p><span data-greet>Welcome</span>, <span data-me-name>{{ $authAdmin->name ?? 'admin' }}</span>. <span data-today></span></p></div><div class="ph-actions"><div class="seg" id="rangeSeg" data-seg role="group" aria-label="Date range"><button type="button" data-v="7">7 days</button><button type="button" data-v="30" class="on">30 days</button><button type="button" data-v="90">90 days</button><button type="button" data-v="365">12 months</button></div>
@if ($authAdmin->canExportReports())<a class="btn btn-line btn-sm" id="dashExport" href="{{ route('admin.reports.export', ['report' => 'sales', 'format' => 'csv', 'days' => 30]) }}">@icon('download')Export</a>@endif<a class="btn btn-primary btn-sm" href="{{ route('admin.book-form') }}">@icon('plus')Add book</a></div></div>
<div class="kpis k6" id="kpis"></div>
<div class="g12">
 <div class="c12"><section class="card "><div class="card-h"><div><h2>Quick actions</h2></div></div><div class="card-b"><div class="quick q4"><a href="{{ route('admin.book-form') }}">@icon('book')Add a book</a><a href="{{ route('admin.paper-form') }}">@icon('file')Add a past paper</a><a href="{{ route('admin.set-form') }}">@icon('layers')New exam set</a><a href="{{ route('admin.package-form') }}">@icon('gift')New package</a><a href="{{ route('admin.orders') }}">@icon('cart')View orders</a><a href="{{ route('admin.messages') }}">@icon('mail')Reply to messages</a><a href="{{ route('admin.content') }}">@icon('globe')Edit site content</a><a href="{{ route('admin.reports') }}">@icon('chart')See reports</a></div></div></section></div>
 <div class="c8"><section class="card "><div class="card-h"><div><h2>Revenue overview</h2><span class="sub">Printed copies and delivery, against PDF downloads</span></div></div><div class="card-b"><div id="chRevenue"></div></div></section></div>
 <div class="c4"><section class="card "><div class="card-h"><div><h2>Sales by category</h2><span class="sub">Books, papers, sets and packages</span></div></div><div class="card-b"><div id="chCategory"></div></div></section></div>
 <div class="c4"><section class="card "><div class="card-h"><div><h2>Orders by status</h2><span class="sub">All orders in the last 12 months</span></div></div><div class="card-b"><div id="chStatus"></div></div></section></div>
 <div class="c4"><section class="card "><div class="card-h"><div><h2>Sales by level</h2><span class="sub">PLE, UCE and UACE</span></div></div><div class="card-b"><div id="chLevel"></div></div></section></div>
 <div class="c4"><section class="card "><div class="card-h"><div><h2>Payment methods</h2><span class="sub">Value collected</span></div></div><div class="card-b"><div id="chMethod"></div></div></section></div>
 <div class="c6"><section class="card "><div class="card-h"><div><h2>Best sellers</h2><span class="sub">By revenue in the selected period</span></div><div class="flex"><a class="btn btn-ghost btn-sm" href="{{ route('admin.reports') }}">Full report</a></div></div><div class="card-b"><ul class="toplist" id="topList"></ul></div></section></div>
 <div class="c6"><section class="card "><div class="card-h"><div><h2>Needs your attention</h2><span class="sub">Open tasks across the shop</span></div></div><div class="card-b"><ul class="attn" id="attn"></ul></div></section></div>
 <div class="c8"><section class="card "><div class="card-h"><div><h2>Printed vs PDF, last 12 months</h2><span class="sub">How learners and schools prefer to buy</span></div></div><div class="card-b"><div id="chFormat"></div></div></section></div>
 <div class="c4"><section class="card "><div class="card-h"><div><h2>Monthly sales goal</h2><span class="sub">Change the target in Settings</span></div></div><div class="card-b"><div id="chGoal"></div><p class="tc" style="margin:.8rem 0 .1rem" id="goalTxt"></p><p class="tc muted sm mb0" id="goalLeft"></p></div></section></div>
 <div class="c6"><section class="card "><div class="card-h"><div><h2>Top subjects</h2><span class="sub">Books and past papers combined</span></div></div><div class="card-b"><div id="chSubject"></div></div></section></div>
 <div class="c3"><section class="card "><div class="card-h"><div><h2>Delivery methods</h2></div></div><div class="card-b"><div id="chDelivery"></div></div></section></div>
 <div class="c3"><section class="card "><div class="card-h"><div><h2>Orders by weekday</h2><span class="sub">Last 90 days</span></div></div><div class="card-b"><div id="chWeekday"></div></div></section></div>
 <div class="c8"><section class="card "><div class="card-h"><div><h2>Recent orders</h2></div><div class="flex"><a class="btn btn-line btn-sm" href="{{ route('admin.orders') }}">View all orders</a></div></div><div class="card-b flush"><div class="tbl-wrap"><table class="tbl" id="recent"><thead><tr><th>Order</th><th>Customer</th><th class="num">Total</th><th>Payment</th><th>Status</th></tr></thead><tbody></tbody></table></div></div></section></div>
 <div class="c4"><section class="card "><div class="card-h"><div><h2>Latest activity</h2></div><div class="flex"><a class="btn btn-ghost btn-sm" href="{{ route('admin.activity') }}">All activity</a></div></div><div class="card-b"><ul class="feed" id="feed"></ul></div></section></div>
</div>
@endsection
