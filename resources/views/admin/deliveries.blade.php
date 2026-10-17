@extends('layouts.admin')

@section('title', 'Deliveries')
@section('page', 'deliveries')
@section('sales', '1')

@section('content')
<div class="pagehead"><div><h1>Deliveries</h1><p>Prepare, dispatch and confirm physical orders. PDF orders are emailed automatically.</p></div><div class="ph-actions"><button class="btn btn-line btn-sm" type="button" data-print>@icon('print')Print delivery list</button></div></div>
<div class="tabs" data-tabs role="tablist"><button class="tab on" type="button" role="tab" data-pane="t-board">Delivery board</button><button class="tab" type="button" role="tab" data-pane="t-pdf">PDF emails</button><button class="tab" type="button" role="tab" data-pane="t-zones">Options and fees</button></div><div class="tabpane mt" id="t-board"><div class="kanban" id="board"></div></div><div class="tabpane mt" id="t-pdf" hidden><div id="pdfTbl"></div></div><div class="tabpane mt" id="t-zones" hidden><div class="card"><div class="card-h"><div><h2>Delivery options</h2><span class="sub">Exactly what customers see at checkout</span></div></div><div class="card-b flush"><div class="tbl-wrap"><table class="tbl"><thead><tr><th>Option</th><th>Time shown</th><th class="num">Fee (UGX)</th><th>Available</th></tr></thead><tbody id="dlvRows">
@foreach (collect(config('wakata.delivery_all'))->only(\App\Support\ShopSettings::EDITABLE_DELIVERY) as $key => $d)
<tr data-key="{{ $key }}"><td><input class="inline-edit" data-f="label" value="{{ $d['label'] }}" maxlength="80" aria-label="Name"></td><td><input class="inline-edit" data-f="eta" value="{{ $d['eta'] }}" maxlength="80" aria-label="Time"></td><td class="num"><input class="price-in" data-f="fee" type="number" min="0" step="500" value="{{ $d['fee'] }}" aria-label="Fee"></td><td><label class="switch sm"><input type="checkbox" data-f="enabled" name="d-{{ $key }}" {{ $d['enabled'] ? 'checked' : '' }}><span class="trk"></span><span class="t"><b></b></span></label></td></tr>
@endforeach
</tbody></table></div></div><div class="card-f"><span class="sec-note">PDF-only orders skip delivery and are emailed.</span><button class="btn btn-primary btn-sm" type="button" id="dlvSave">Save changes</button></div></div></div>
@endsection
