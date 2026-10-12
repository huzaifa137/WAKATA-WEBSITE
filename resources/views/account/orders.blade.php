@extends('layouts.account')

@section('title', 'My orders')
@section('page', 'orders')

@section('content')
<div class="pagehead"><div><h1>My orders</h1><p>Follow, review and reorder everything you have bought.</p></div><div class="ph-actions"><a class="btn btn-primary btn-sm" href="{{ route('site.home') }}">@icon('shop')Continue shopping</a></div></div>
<div id="tbl"></div>
@endsection
