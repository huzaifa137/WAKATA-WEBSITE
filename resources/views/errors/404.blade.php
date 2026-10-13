@extends('layouts.site')

@section('title', 'Page not found | WAKATA Stationers')
@section('page', '404')

@section('content')
<section class="sec">
  <div class="wrap">
    <div class="empty">
      <h1>We could not find that page</h1>
      <p class="muted">The link may be old, or the item is no longer on sale. Try the shop instead.</p>
      <p><a class="btn btn-primary" href="{{ route('site.books') }}">Shop books</a> <a class="btn btn-line" href="{{ route('site.home') }}">Go to the home page</a></p>
    </div>
  </div>
</section>
@endsection
