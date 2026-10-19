@extends('layouts.admin')

@section('title', 'Activity log')
@section('page', 'activity')
@section('ins', '1')

@section('content')
<div class="pagehead"><div><h1>Activity log</h1><p>A record of changes made in the console and of every sign-in, for accountability. The newest 1,000 lines are shown.</p></div><div class="ph-actions"></div></div>
<div id="tbl"></div>
@endsection
