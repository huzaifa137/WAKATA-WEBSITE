@extends('layouts.site')

@section('title', 'Unsubscribed | WAKATA Stationers')
@section('page', 'unsubscribed')

@section('content')
<section class="page-head"><div class="wrap"><h1>{{ $found ? 'You are unsubscribed' : 'Link not recognised' }}</h1><p>{{ $found ? 'We will not email you new-paper news again. You can sign up again from the bottom of any page.' : 'This unsubscribe link is not valid. If you still get emails you do not want, reply to one and we will remove you.' }}</p></div></section>
<section class="sec"><div class="wrap"><a class="btn btn-primary" href="{{ route('site.home') }}">Back to the shop</a></div></section>
@endsection
