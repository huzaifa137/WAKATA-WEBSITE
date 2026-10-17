@extends('layouts.admin')

@section('title', 'Exam sets')
@section('page', 'exam-sets')
@section('adm', '1')

@section('content')
<div class="pagehead"><div><h1>Exam sets</h1><p>Complete mock series sold as one bundle, shown on the Exam sets page.</p></div><div class="ph-actions"><a class="btn btn-primary btn-sm" href="{{ route('admin.set-form') }}">@icon('plus')New exam set</a></div></div>
<div class="kpis" id="setStats"></div><div class="flex-b wrapf" style="margin-bottom:1rem"><div class="chips"><button class="chip on" type="button" data-lvl="">All levels</button><button class="chip" type="button" data-lvl="ple">PLE</button><button class="chip" type="button" data-lvl="uce">UCE</button><button class="chip" type="button" data-lvl="uace">UACE</button></div><div class="tsearch" style="max-width:300px"><label class="sr">Search sets</label>@icon('search')<input type="search" data-card-q placeholder="Search exam sets…"></div></div><div class="grid-cards" id="cards" style="grid-template-columns:repeat(auto-fill,minmax(300px,1fr))"></div>
@endsection
