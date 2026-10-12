@extends('layouts.site')

@section('title')Past papers | WAKATA Stationers@endsection
@section('page')papers@endsection

@section('content')
<section class="page-head"><div class="wrap"><div class="crumbs"><a href="{{ route('site.home') }}">Home</a><span>/</span>Past papers</div><h1>Past papers</h1><p>Real WAKATA mock papers from earlier series. Buy a printed copy for the classroom, or download a PDF today.</p></div></section><div class="wrap shop"><aside class="side" aria-label="Filters"><div id="facets"></div></aside><section aria-label="Results"><div class="tools"><span class="found" id="found" aria-live="polite"></span>
        <div style="display:flex;gap:.6rem;flex-wrap:wrap"><button class="btn btn-line btn-sm filter-toggle" id="filter-toggle" type="button" aria-expanded="false"><svg class="ico" viewBox="0 0 24 24" aria-hidden="true"><polygon points="3 4 21 4 14 12 14 20 10 18 10 12"/></svg>Filters</button>
        <label class="sr" for="q">Search past papers</label><input id="q" type="search" placeholder="Search past papers" style="padding:.55rem 1rem;border:1.5px solid var(--line);border-radius:999px"></div></div>
        <div class="grid grid-papers" id="grid"></div>
        <div class="more" id="more" hidden><p class="muted"><span></span></p><button class="btn btn-line" id="more-btn" type="button">Show more papers</button></div></section></div><section class="sec sec-white" style="padding-block:2.4rem"><div class="wrap cards3"><div class="info"><svg class="ico" viewBox="0 0 24 24" aria-hidden="true"><rect x="1" y="6" width="13" height="10" rx="1"/><path d="M14 9h4l3 3v4h-7"/><circle cx="6" cy="18" r="2"/><circle cx="17" cy="18" r="2"/></svg><h3>Printed copies</h3><p>Collected free from our shop or delivered with your other items.</p></div><div class="info"><svg class="ico" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3v12"/><polyline points="7 11 12 16 17 11"/><path d="M4 20h16"/></svg><h3>PDF downloads</h3><p>Cheaper, and sent to your email as soon as payment is confirmed.</p></div><div class="info"><svg class="ico" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3l8 3v6c0 5-3.5 8-8 9-4.5-1-8-4-8-9V6z"/><polyline points="9 12 11 14 15 10"/></svg><h3>Marking guides</h3><p>Available with every full exam set, so teachers can mark with confidence.</p></div></div></section>
@endsection
