@extends('layouts.account')

@section('title', 'Saved items')
@section('page', 'saved')

@section('content')
<div class="pagehead"><div><h1>Saved items</h1><p><span id="savedCount">0</span> items you saved to buy later.</p></div><div class="ph-actions"><button class="btn btn-primary btn-sm" type="button" id="addAll">@icon('cart')Add all to cart</button></div></div>
<div class="grid-cards" id="grid" style="grid-template-columns:repeat(auto-fill,minmax(260px,1fr))"></div>
@endsection
