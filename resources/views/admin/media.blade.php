@extends('layouts.admin')

@section('title', 'Media library')
@section('page', 'media')
@section('adm', '1')

@section('content')
<div class="pagehead"><div><h1>Media library</h1><p>Book covers, paper sheets, logos and the PDF files customers download.</p></div><div class="ph-actions"><button class="btn btn-primary btn-sm" type="button" data-open="upModal">@icon('upload')Upload</button></div></div>
<div class="tabs" data-tabs role="tablist"><button class="tab on" type="button" role="tab" data-pane="t-img">Images</button><button class="tab" type="button" role="tab" data-pane="t-pdf">PDF files</button></div><div class="tabpane mt" id="t-img"><div class="layout-2"><div class="card"><div class="toolbar"><div class="chips"><button class="chip on" type="button" data-kind="">All</button><button class="chip" type="button" data-kind="books">Book covers</button><button class="chip" type="button" data-kind="papers">Paper sheets</button><button class="chip" type="button" data-kind="brand">Logos</button></div><div class="tsearch" style="margin-left:auto">@icon('search')<input type="search" data-card-q placeholder="Search files…"></div></div><div class="card-b"><div class="media-grid" id="mediaGrid"></div></div></div><div class="rail card"><div class="card-h"><h2>Details</h2></div><div class="card-b" id="mediaSide"><p class="muted">Select a file to see where it is used.</p></div></div></div></div><div class="tabpane mt" id="t-pdf" hidden><div id="pdfTbl"></div></div>
@endsection

@push('modals')
<div class="mback" id="upModal" role="dialog" aria-modal="true" aria-labelledby="upModalT"><div class="modal "><div class="modal-h"><h2 id="upModalT">Upload images</h2><button class="btn btn-ic" type="button" data-close aria-label="Close">@icon('x')</button></div><div class="modal-b"><div class="drop dz-lg" data-preview><div class="dz">@icon('upload')<b>Drop images here or click to choose</b><small>JPG, PNG or WebP, up to 2 MB each</small></div><input type="file" name="images[]" form="mediaUpForm" accept="image/*" multiple aria-label="Choose images"></div><div class="field" style="margin-top:1rem"><label for="mkind">Save in</label><select id="mkind" name="kind" form="mediaUpForm"><option value="books">Book covers</option><option value="papers">Paper sheets and exam set previews</option></select></div></div><div class="modal-f"><button class="btn btn-line" type="button" data-close>Cancel</button><button class="btn btn-primary" type="submit" form="mediaUpForm">Upload</button></div></div></div>
<form id="mediaUpForm" method="POST" action="{{ route('admin.media.store') }}" enctype="multipart/form-data" hidden>@csrf</form>
@endpush
