@php
/*
| Account sidebar. Add or reorder menu items here.
| 'on' lists the data-page values that highlight the link, 'badge' is filled by the dashboard JS.
*/
$page = trim($__env->yieldContent('page'));
$groups = [
    ['Overview', [
        ['label' => 'Dashboard', 'route' => 'account.dashboard', 'icon' => 'grid', 'on' => ['dashboard']],
    ]],
    ['My purchases', [
        ['label' => 'Downloads & library', 'route' => 'account.library', 'icon' => 'download', 'on' => ['library']],
        ['label' => 'My orders', 'route' => 'account.orders', 'icon' => 'box', 'on' => ['orders', 'order'], 'badge' => 'orders'],
        ['label' => 'Invoices & receipts', 'route' => 'account.invoices', 'icon' => 'receipt', 'on' => ['invoices', 'invoice']],
    ]],
    ['Shop', [
        ['label' => 'Saved items', 'route' => 'account.saved', 'icon' => 'heart', 'on' => ['saved']],
        ['label' => 'Past papers', 'route' => 'site.papers', 'icon' => 'file', 'ext' => true],
        ['label' => 'Books', 'route' => 'site.books', 'icon' => 'book', 'ext' => true],
        ['label' => 'Exam sets & packages', 'route' => 'site.sets', 'icon' => 'layers', 'ext' => true],
    ]],
    ['Account', [
        ['label' => 'Addresses', 'route' => 'account.addresses', 'icon' => 'map', 'on' => ['addresses']],
        ['label' => 'Help & support', 'route' => 'account.support', 'icon' => 'help', 'on' => ['support'], 'badge' => 'messages'],
        ['label' => 'Profile & settings', 'route' => 'account.profile', 'icon' => 'settings', 'on' => ['profile']],
    ]],
];
@endphp
<aside class="sb" id="sb" aria-label="Account navigation"><a class="sb-brand" href="{{ route('account.dashboard') }}"><img src="{{ asset('assets/img/logo-white.png') }}" alt="" width="42" height="40"><div><b>WAKATA</b><small>My account</small></div></a><nav class="sb-nav">
@foreach ($groups as [$group, $items])
<div class="sb-grp">{{ $group }}</div>
@foreach ($items as $it)
@php $on = in_array($page, $it['on'] ?? [], true); @endphp
<a class="sb-link{{ $on ? ' on' : '' }}" href="{{ route($it['route']) }}"{!! $on ? ' aria-current="page"' : '' !!}>@icon($it['icon'])<span>{{ $it['label'] }}</span>{!! empty($it['badge']) ? '' : '<span class="sb-badge" data-badge="' . e($it['badge']) . '"></span>' !!}{!! empty($it['ext']) ? '' : \App\Support\Icon::svg('external', 'sb-ext') !!}</a>
@endforeach
@endforeach
</nav><div class="sb-foot"><span class="avatar" data-me-initials>{{ $authCustomer->initials ?? '' }}</span><div><b data-me-name>{{ $authCustomer->name ?? 'Customer' }}</b><small>Customer account</small></div></div></aside>
