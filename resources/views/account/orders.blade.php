@extends('layouts.account')

@section('title', 'My orders')
@section('page', 'orders')

@section('content')
<div class="pagehead"><div><h1>My orders</h1><p>Follow, review and reorder everything you have bought.</p></div><div class="ph-actions"><button class="btn btn-line btn-sm" type="button" data-open="claimModal">@icon('search')Find an order</button><a class="btn btn-primary btn-sm" href="{{ route('site.home') }}">@icon('shop')Continue shopping</a></div></div>
<div id="tbl"></div>
@endsection

@push('modals')
<div class="mback" id="claimModal" role="dialog" aria-modal="true" aria-labelledby="claimT"><div class="modal"><div class="modal-h"><h2 id="claimT">Find an order</h2><button class="btn btn-ic" type="button" data-close aria-label="Close">@icon('x')</button></div><div class="modal-b">
<p class="sm muted" style="margin-top:0">Placed an order before you had an account? Enter its number and we email a 6-digit code to the address used at checkout. Enter the code to add the order (and any others placed with that email) to this account.</p>
<div class="field" id="claimStep1"><label for="claimNo">Order number</label><input id="claimNo" type="text" placeholder="WK-10471" autocomplete="off"></div>
<div class="field" id="claimStep2" hidden><label for="claimCode">6-digit code</label><input id="claimCode" type="text" inputmode="numeric" maxlength="6" placeholder="123456" autocomplete="one-time-code"></div>
<p id="claimMsg" class="sm muted" aria-live="polite"></p></div>
<div class="modal-f"><button class="btn btn-line" type="button" data-close>Close</button><button class="btn btn-line" type="button" id="claimSend">Email me a code</button><button class="btn btn-primary" type="button" id="claimVerify">Add order</button></div></div></div>
@endpush
