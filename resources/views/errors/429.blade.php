@extends('errors.layout')

@section('code', '429')
@section('heading', 'Too many requests')
@section('message', 'You have made a lot of requests in a short time. Please wait a minute and try again.')
@section('retry', '1')
