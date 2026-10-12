@extends('layouts.admin')

@section('title', 'Holiday packages')
@section('page', 'packages')

@section('content')
<div class="pagehead"><div><h1>Holiday packages</h1><p>Ready-made bundles of workbooks, tests and mock papers.</p></div><div class="ph-actions"><a class="btn btn-primary btn-sm" href="{{ route('admin.package-form') }}">@icon('plus')New package</a></div></div>
<div class="kpis" id="pkStats"></div><div class="flex-b wrapf" style="margin-bottom:1.3rem"><div class="chips"><button class="chip on" type="button" data-lvl="">All levels</button><button class="chip" type="button" data-lvl="ple">PLE</button><button class="chip" type="button" data-lvl="uce">UCE</button><button class="chip" type="button" data-lvl="uace">UACE</button></div><div class="tsearch" style="max-width:300px"><label class="sr">Search packages</label>@icon('search')<input type="search" data-card-q placeholder="Search packages…"></div></div><div class="grid-cards" id="cards" style="grid-template-columns:repeat(auto-fill,minmax(310px,1fr));gap:1.4rem"></div>
@endsection
