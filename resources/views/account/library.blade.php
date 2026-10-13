@extends('layouts.account')

@section('title', 'Downloads and library')
@section('page', 'library')

@section('content')
<div class="pagehead"><div><h1>Downloads and library</h1><p>Everything you have bought, ready when you need it.</p></div><div class="ph-actions"><button class="btn btn-primary btn-sm" type="button" id="dlAll">@icon('download')Download all shown</button></div></div>
<div class="kpis" id="libStats"></div><div class="tabs" data-tabs role="tablist"><button class="tab on" type="button" role="tab" data-pane="t-pdf">PDF files <span class="n" id="cPdf">0</span></button><button class="tab" type="button" role="tab" data-pane="t-print">Printed items <span class="n" id="cPrint">0</span></button><button class="tab" type="button" role="tab" data-pane="t-bun">Sets and packages <span class="n" id="cBun">0</span></button></div>
<div class="tabpane mt" id="t-pdf"><div class="callout info" style="margin-bottom:1rem">@icon('info')<div>Every PDF can be downloaded <b>up to {{ config('wakata.download_limit') }} times</b>, and stays in your library for good. PDFs appear here once your payment is confirmed. Need more downloads? <a href="{{ route('account.support') }}">Ask us</a>.</div></div><div id="pdfTbl"></div></div>
<div class="tabpane mt" id="t-print" hidden><div id="prTbl"></div></div>
<div class="tabpane mt" id="t-bun" hidden><div class="cols-2" id="bundles"></div></div>
@endsection
