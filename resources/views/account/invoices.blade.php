@extends('layouts.account')

@section('title', 'Invoices and receipts')
@section('page', 'invoices')

@section('content')
<div class="pagehead"><div><h1>Invoices and receipts</h1><p>Print or save a receipt for every paid order. Schools can stamp the printed copy.</p></div><div class="ph-actions"></div></div>
<div class="kpis" id="invStats"></div><div id="tbl"></div>
@endsection
