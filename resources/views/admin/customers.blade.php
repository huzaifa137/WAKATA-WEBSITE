@extends('layouts.admin')

@section('title', 'Customers')
@section('page', 'customers')
@section('sales', '1')

@section('content')
<div class="pagehead"><div><h1>Customers</h1><p>Schools, teachers, parents and bookshops who have ordered from WAKATA.</p></div><div class="ph-actions"><button class="btn btn-primary btn-sm" type="button" data-open="addCust">@icon('plus')Add customer</button></div></div>
<div class="kpis" id="custStats"></div><div class="g12" style="margin-bottom:1.1rem"><div class="c8"><section class="card "><div class="card-h"><div><h2>Top customers</h2><span class="sub">By lifetime sales</span></div></div><div class="card-b"><div id="chTopCust"></div></div></section></div><div class="c4"><section class="card "><div class="card-h"><div><h2>Who buys</h2><span class="sub">Share of lifetime sales</span></div></div><div class="card-b"><div id="chCustType"></div></div></section></div></div><div id="tbl"></div>
@endsection

@push('modals')
<div class="mback" id="addCust" role="dialog" aria-modal="true" aria-labelledby="addCustT"><div class="modal "><div class="modal-h"><h2 id="addCustT">Add a customer</h2><button class="btn btn-ic" type="button" data-close aria-label="Close">@icon('x')</button></div><div class="modal-b"><div class="row2"><div class="field "><label for="cn">Name or school <span class="req">*</span></label><input id="cn" name="cn" type="text" value="" placeholder="" required ><span class="err">This field is required.</span></div><div class="field "><label for="ct">Type</label><select id="ct" name="ct" ><option value="School">School</option><option value="Teacher">Teacher</option><option value="Parent">Parent</option><option value="Bookshop">Bookshop</option></select></div></div><div class="row2"><div class="field "><label for="cp">Phone</label><input id="cp" name="cp" type="tel" value="" placeholder="" ></div><div class="field "><label for="ce">Email <span class="req">*</span></label><input id="ce" name="ce" type="email" value="" placeholder="" required maxlength="190"><span class="err">Enter a valid email address.</span></div></div><div class="row2"><div class="field "><label for="cc">Contact person</label><input id="cc" name="cc" type="text" value="" placeholder="e.g. Bursar" ></div><div class="field "><label for="cd">District</label><input id="cd" name="cd" type="text" value="" placeholder="" ></div></div></div><div class="modal-f"><button class="btn btn-line" type="button" data-close>Cancel</button><button class="btn btn-primary" type="button" id="custSave">Save</button></div></div></div>
@endpush
