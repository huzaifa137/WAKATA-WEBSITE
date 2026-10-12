@php
/*
| Admin sidebar. Add or reorder menu items here.
| 'on' lists the data-page values that highlight the link, 'badge' is filled by the dashboard JS.
*/
$page = trim($__env->yieldContent('page'));
$groups = [
    ['Overview', [
        ['label' => 'Dashboard', 'route' => 'admin.dashboard', 'icon' => 'grid', 'on' => ['dashboard', 'profile']],
    ]],
    ['Catalogue', [
        ['label' => 'Books', 'route' => 'admin.books', 'icon' => 'book', 'on' => ['books', 'book-form']],
        ['label' => 'Past papers', 'route' => 'admin.past-papers', 'icon' => 'file', 'on' => ['past-papers', 'paper-form']],
        ['label' => 'Exam sets', 'route' => 'admin.exam-sets', 'icon' => 'layers', 'on' => ['exam-sets', 'set-form']],
        ['label' => 'Holiday packages', 'route' => 'admin.packages', 'icon' => 'gift', 'on' => ['packages', 'package-form']],
        ['label' => 'Subjects & levels', 'route' => 'admin.taxonomy', 'icon' => 'tag', 'on' => ['taxonomy']],
        ['label' => 'Media library', 'route' => 'admin.media', 'icon' => 'image', 'on' => ['media']],
        ['label' => 'Inventory', 'route' => 'admin.inventory', 'icon' => 'box', 'on' => ['inventory']],
    ]],
    ['Sales', [
        ['label' => 'Orders', 'route' => 'admin.orders', 'icon' => 'cart', 'on' => ['orders', 'order'], 'badge' => 'orders'],
        ['label' => 'Payments', 'route' => 'admin.payments', 'icon' => 'card', 'on' => ['payments']],
        ['label' => 'Deliveries', 'route' => 'admin.deliveries', 'icon' => 'truck', 'on' => ['deliveries']],
        ['label' => 'Customers', 'route' => 'admin.customers', 'icon' => 'users', 'on' => ['customers']],
    ]],
    ['Engagement', [
        ['label' => 'Messages', 'route' => 'admin.messages', 'icon' => 'mail', 'on' => ['messages'], 'badge' => 'messages'],
        ['label' => 'Subscribers', 'route' => 'admin.subscribers', 'icon' => 'send', 'on' => ['subscribers']],
        ['label' => 'Testimonials', 'route' => 'admin.testimonials', 'icon' => 'quote', 'on' => ['testimonials']],
        ['label' => 'FAQs', 'route' => 'admin.faqs', 'icon' => 'help', 'on' => ['faqs']],
    ]],
    ['Website', [
        ['label' => 'Site content', 'route' => 'admin.content', 'icon' => 'globe', 'on' => ['content']],
    ]],
    ['Insights', [
        ['label' => 'Reports', 'route' => 'admin.reports', 'icon' => 'chart', 'on' => ['reports']],
    ]],
    ['System', [
        ['label' => 'Team & roles', 'route' => 'admin.team', 'icon' => 'shield', 'on' => ['team']],
        ['label' => 'Activity log', 'route' => 'admin.activity', 'icon' => 'history', 'on' => ['activity']],
        ['label' => 'Settings', 'route' => 'admin.settings', 'icon' => 'settings', 'on' => ['settings']],
    ]],
];
@endphp
<aside class="sb" id="sb" aria-label="Admin navigation"><a class="sb-brand" href="{{ route('admin.dashboard') }}"><img src="{{ asset('assets/img/logo-white.png') }}" alt="" width="42" height="40"><div><b>WAKATA</b><small>Admin</small></div></a><nav class="sb-nav">
@foreach ($groups as [$group, $items])
<div class="sb-grp">{{ $group }}</div>
@foreach ($items as $it)
@php $on = in_array($page, $it['on'] ?? [], true); @endphp
<a class="sb-link{{ $on ? ' on' : '' }}" href="{{ route($it['route']) }}"{!! $on ? ' aria-current="page"' : '' !!}>@icon($it['icon'])<span>{{ $it['label'] }}</span>{!! empty($it['badge']) ? '' : '<span class="sb-badge" data-badge="' . e($it['badge']) . '"></span>' !!}{!! empty($it['ext']) ? '' : \App\Support\Icon::svg('external', 'sb-ext') !!}</a>
@endforeach
@endforeach
</nav><div class="sb-foot"><span class="avatar" data-me-initials>GN</span><div><b data-me-name>Grace Namutebi</b><small>Super admin</small></div></div></aside>
