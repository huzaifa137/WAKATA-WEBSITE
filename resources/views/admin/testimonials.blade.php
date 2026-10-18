@extends('layouts.admin')

@section('title', 'Testimonials')
@section('page', 'testimonials')
@section('eng', '1')

@section('content')
<div class="pagehead"><div><h1>Testimonials</h1><p><span id="tCount">10</span> quotes in the “What teachers say” row on the home page.</p></div><div class="ph-actions"><button class="btn btn-primary btn-sm" type="button" data-open="tModal">@icon('plus')Add testimonial</button></div></div>
<div class="callout" style="margin-bottom:1.1rem">@icon('alert')<div><b>Only quotes marked visible appear in the scrolling row on the home page.</b> The ten quotes that came with the site are sample text: replace them with real quotes, with permission, before launch.</div></div><div class="cols-3" id="tCards"></div>
@endsection

@push('modals')
<div class="mback" id="tModal" role="dialog" aria-modal="true" aria-labelledby="tModalT"><div class="modal wide"><div class="modal-h"><h2 id="tModalT">Testimonial</h2><button class="btn btn-ic" type="button" data-close aria-label="Close">@icon('x')</button></div><div class="modal-b"><div class="row2"><div class="field "><label for="tr">Rating</label><select id="tr" name="tr" ><option value="5">5 stars</option><option value="4">4 stars</option><option value="3">3 stars</option><option value="2">2 stars</option><option value="1">1 star</option></select></div><div class="field "><label for="ti">Initials</label><input id="ti" name="ti" type="text" value="" placeholder="Shown in the circle, for example HS" maxlength="4" ></div></div><div class="field "><label for="tq">Quote <span class="req">*</span></label><textarea id="tq" name="tq" placeholder="What did they say?" required ></textarea><span class="err">This field is required.</span></div><div class="row2"><div class="field "><label for="trl">Role</label><input id="trl" name="trl" type="text" value="" placeholder="e.g. Head of Science" ></div><div class="field "><label for="tp">School and place</label><input id="tp" name="tp" type="text" value="" placeholder="Secondary school, Wakiso" ></div></div><label class="check"><input type="checkbox" id="tperm"> I have permission to publish this quote</label></div><div class="modal-f"><button class="btn btn-line" type="button" data-close>Cancel</button><button class="btn btn-primary" type="button" id="tSave">Save</button></div></div></div>
@endpush
