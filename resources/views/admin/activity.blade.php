@extends('layouts.admin')

@section('title', 'Activity log')
@section('page', 'activity')

@section('content')
<div class="pagehead"><div><h1>Activity log</h1><p>A record of changes made in the console, for accountability.</p></div><div class="ph-actions"></div></div>
<div id="tbl"></div>
@endsection
