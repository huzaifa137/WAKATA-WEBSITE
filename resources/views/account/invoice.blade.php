@extends('layouts.account')

@section('title', 'Invoice')
@section('page', 'invoice')

@section('content')
<div class="pagehead"><div><div class="crumbs"><a href="{{ route('account.dashboard') }}">Dashboard</a><span>/</span><a href="{{ route('account.invoices') }}">Invoices and receipts</a><span>/</span>Invoice</div><h1>Invoice</h1><p>Print this page or save it as a PDF from your browser.</p></div><div class="ph-actions"><button class="btn btn-primary btn-sm" type="button" data-print>@icon('print')Print or save as PDF</button></div></div>
<div id="invRoot"></div>
@endsection
