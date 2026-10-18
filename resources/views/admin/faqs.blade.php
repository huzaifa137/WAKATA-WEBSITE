@extends('layouts.admin')

@section('title', 'Questions and answers')
@section('page', 'faqs')
@section('eng', '1')

@section('content')
<div class="pagehead"><div><h1>Questions and answers</h1><p>The FAQ lists on the Contact page and the Holiday packages page.</p></div><div class="ph-actions"><button class="btn btn-primary btn-sm" type="button" data-open="faqModal">@icon('plus')Add question</button></div></div>
<div id="faqGroups"></div>
@endsection

@push('modals')
<div class="mback" id="faqModal" role="dialog" aria-modal="true" aria-labelledby="faqModalT"><div class="modal "><div class="modal-h"><h2 id="faqModalT">Question</h2><button class="btn btn-ic" type="button" data-close aria-label="Close">@icon('x')</button></div><div class="modal-b"><div class="field "><label for="fp">Show on</label><select id="fp" name="fp" ><option value="contact">Contact page</option><option value="holiday">Holiday packages page</option></select></div><div class="field "><label for="fq">Question <span class="req">*</span></label><input id="fq" name="fq" type="text" value="" placeholder="" required ><span class="err">This field is required.</span></div><div class="field "><label for="fa">Answer <span class="req">*</span></label><textarea id="fa" name="fa" placeholder="" required ></textarea><span class="err">This field is required.</span></div><div class="field "><label for="fi">Icon</label><select id="fi" name="fi" ><option value="card">Card</option><option value="download">Download</option><option value="truck">Delivery</option><option value="box">Parcel</option><option value="store">Shop</option><option value="clock">Clock</option><option value="calendar">Calendar</option><option value="refresh">Swap</option><option value="check">Tick</option><option value="help">Question</option></select></div></div><div class="modal-f"><button class="btn btn-line" type="button" data-close>Cancel</button><button class="btn btn-primary" type="button" id="faqSave">Save</button></div></div></div>
@endpush
