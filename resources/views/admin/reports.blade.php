@extends('layouts.admin')

@section('title', 'Reports')
@section('page', 'reports')
@section('sales', '1')
@section('ins', '1')

@section('content')
<div class="pagehead"><div><h1>Reports</h1><p>Sales, products, customers and payments, ready to review or export.</p></div><div class="ph-actions"><div class="seg" id="repSeg" data-seg><button type="button" data-v="30">30 days</button><button type="button" data-v="90" class="on">90 days</button><button type="button" data-v="365">12 months</button></div>@if ($authAdmin->canExportReports())<button class="btn btn-line btn-sm" type="button" data-open="schedModal">@icon('calendar')Schedule</button>@endif<button class="btn btn-primary btn-sm" type="button" data-print>@icon('print')Print</button></div></div>
<div class="kpis" id="repKpis"></div><div class="g12">
<div class="c8"><section class="card "><div class="card-h"><div><h2>Revenue</h2><span class="sub">Paid orders in the selected period</span></div></div><div class="card-b"><div id="chRev"></div></div></section></div><div class="c4"><section class="card "><div class="card-h"><div><h2>Sales by level</h2></div></div><div class="card-b"><div id="chLv"></div></div></section></div>
<div class="c6"><section class="card "><div class="card-h"><div><h2>Orders</h2></div></div><div class="card-b"><div id="chOrd"></div></div></section></div><div class="c6"><section class="card "><div class="card-h"><div><h2>Sales by category, 12 months</h2><span class="sub">Books, papers, sets and packages</span></div></div><div class="card-b"><div id="chCat"></div></div></section></div>
<div class="c6"><section class="card "><div class="card-h"><div><h2>Top subjects</h2><span class="sub">Books and past papers combined</span></div></div><div class="card-b"><div id="chSubj"></div></div></section></div><div class="c6"><section class="card "><div class="card-h"><div><h2>New vs returning customers</h2><span class="sub">Customers who ordered each month</span></div></div><div class="card-b"><div id="chRet"></div></div></section></div>
<div class="c4"><section class="card "><div class="card-h"><div><h2>Printed vs PDF papers</h2><span class="sub">Units sold</span></div></div><div class="card-b"><div id="chFmt"></div></div></section></div><div class="c8"><section class="card "><div class="card-h"><div><h2>Order funnel</h2><span class="sub">From order placed to delivered</span></div></div><div class="card-b"><div class="funnel" id="funnel"></div><p class="sec-note mb0">Counted from your orders. Visits and product views are not tracked here: use Google Analytics (Settings, Integrations) for those.</p></div></section></div>
<div class="c6"><section class="card "><div class="card-h"><div><h2>Top customers</h2></div></div><div class="card-b"><table class="mini-table"><thead><tr><th>Customer</th><th class="num">Orders</th><th class="num">Spent</th></tr></thead><tbody id="repTop"></tbody></table></div></section></div><div class="c6"><section class="card "><div class="card-h"><div><h2>Ready-made reports</h2><span class="sub">Download any time. They follow the period chosen above.</span></div></div><div class="card-b">
@php
    $ready = [
        ['sales', 'chart', 'Sales summary', 'Revenue by day: orders, products and delivery fees'],
        ['orders', 'truck', 'Orders and fulfilment', 'Status, payment, delivery method and total of every order'],
        ['products', 'book', 'Product performance', 'Units and revenue per title and paper'],
        ['customers', 'users', 'Customers and schools', 'Orders and spend per customer'],
        ['payments', 'card', 'Payments and refunds', 'By method, status and reference'],
        ['stock', 'box', 'Stock position', 'On hand, alert level and low stock (period does not matter)'],
    ];
@endphp
@foreach ($ready as $r)
<div class="flex-b" style="padding:.7rem 0;border-bottom:1px solid var(--line-2)"><div class="flex"><span class="kpi-ic">@icon($r[1])</span><div><b>{{ $r[2] }}</b><div class="xs muted">{{ $r[3] }}</div></div></div><div class="flex">@if ($authAdmin->canExportReports())<a class="btn btn-line btn-sm" data-rep="{{ $r[0] }}" href="{{ route('admin.reports.export', ['report' => $r[0], 'format' => 'csv', 'days' => 90]) }}">CSV</a><a class="btn btn-line btn-sm" data-rep="{{ $r[0] }}" href="{{ route('admin.reports.export', ['report' => $r[0], 'format' => 'pdf', 'days' => 90]) }}">PDF</a>@else<span class="xs muted">Super admin or manager</span>@endif</div></div>
@endforeach
</div></section></div></div>
@endsection

@if ($authAdmin->canExportReports())
@push('modals')
<div class="mback" id="schedModal" role="dialog" aria-modal="true" aria-labelledby="schedModalT"><div class="modal "><form method="POST" action="{{ route('admin.reports.schedule') }}" id="schedForm">@csrf<div class="modal-h"><h2 id="schedModalT">Email me this report</h2><button class="btn btn-ic" type="button" data-close aria-label="Close">@icon('x')</button></div><div class="modal-b"><div class="field "><label for="sf">How often</label><select id="sf" name="frequency"><option value="off" @selected(($ins['schedule']['frequency'] ?? 'off') === 'off')>Do not email it</option><option value="weekly" @selected(($ins['schedule']['frequency'] ?? '') === 'weekly')>Every Monday morning (last 7 days)</option><option value="monthly" @selected(($ins['schedule']['frequency'] ?? '') === 'monthly')>On the 1st of each month (last 30 days)</option></select><span class="hint">A short summary: revenue, paid orders, average order and best sellers.</span></div><div class="field "><label for="sr">Send it to</label><input id="sr" name="recipients" type="text" value="{{ old('recipients', $ins['schedule']['recipients'] ?? '') }}" placeholder="name@wakatastationers.com, another@example.com"><span class="err">Enter a valid email address.</span><span class="hint">Separate several addresses with commas.@if (! empty($ins['schedule']['last_sent'])) Last sent on {{ $ins['schedule']['last_sent'] }}.@endif</span></div><p class="sec-note mb0">The server must run <code>php artisan schedule:run</code> every minute (one cron line) for emails to go out.</p></div><div class="modal-f"><button class="btn btn-line" type="button" data-close>Cancel</button><button class="btn btn-primary" type="submit">Save</button></div></form></div></div>
@endpush
@endif
