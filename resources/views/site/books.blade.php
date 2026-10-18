@extends('layouts.site')

@section('title', \App\Support\SiteContent::seo('books', 'title'))
@section('description', \App\Support\SiteContent::seo('books', 'desc'))
@section('page', 'books')

@section('content')
<section class="page-head"><div class="wrap"><div class="crumbs"><a href="{{ route('site.home') }}">Home</a><span>/</span>Books</div><h1>Books and workbooks</h1><p>Practical workbooks, textbooks and revision guides for primary and secondary schools.</p></div></section><div class="wrap shop"><aside class="side" aria-label="Filters"><div id="facets"></div></aside><section aria-label="Results"><div class="tools"><span class="found" id="found" aria-live="polite"></span>
        <div style="display:flex;gap:.6rem;flex-wrap:wrap"><button class="btn btn-line btn-sm filter-toggle" id="filter-toggle" type="button" aria-expanded="false"><svg class="ico" viewBox="0 0 24 24" aria-hidden="true"><polygon points="3 4 21 4 14 12 14 20 10 18 10 12"/></svg>Filters</button>
        <label class="sr" for="q">Search books</label><input id="q" type="search" placeholder="Search books" style="padding:.55rem 1rem;border:1.5px solid var(--line);border-radius:999px">
        <label class="sr" for="sort">Sort books</label><select id="sort" style="padding:.55rem 1rem;border:1.5px solid var(--line);border-radius:999px;background:#fff"><option value="featured">Featured</option><option value="low">Price, low to high</option><option value="high">Price, high to low</option><option value="az">Title, A to Z</option></select></div></div>
        <div class="grid grid-books" id="grid"></div></section></div>
@endsection
