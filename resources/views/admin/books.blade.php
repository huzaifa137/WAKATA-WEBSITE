@extends('layouts.admin')

@section('title', 'Books and workbooks')
@section('page', 'books')
@section('adm', '1')

@section('content')
<div class="pagehead"><div><h1>Books and workbooks</h1><p>Textbooks, practical workbooks and revision guides shown on the Books page.</p></div><div class="ph-actions"><div class="seg" id="viewSeg" data-seg role="group" aria-label="View"><button type="button" data-v="table" class="on">Table</button><button type="button" data-v="grid">Grid</button></div><a class="btn btn-primary btn-sm" href="{{ route('admin.book-form') }}">@icon('plus')Add book</a></div></div>
<div class="kpis" id="bookStats"></div><div id="tbl"></div><div id="gridWrap" hidden><div class="grid-cards" id="gridView"></div></div>
@endsection

@push('modals')
<div class="mback" id="priceModal" role="dialog" aria-modal="true" aria-labelledby="priceModalT"><div class="modal sm"><div class="modal-h"><h2 id="priceModalT">Adjust prices</h2><button class="btn btn-ic" type="button" data-close aria-label="Close">@icon('x')</button></div><div class="modal-b"><p class="muted sm">Applies to the selected books.</p><div class="field "><label for="pm">Change</label><select id="pm" name="pm" ><option value="up">Increase by a percentage</option><option value="down">Decrease by a percentage</option><option value="set">Set a fixed price</option></select></div><div class="field "><label for="pv">Value</label><input id="pv" name="pv" type="number" value="10" placeholder="" ></div><label class="switch"><input type="checkbox" name="keepold" checked><span class="trk"></span><span class="t"><b>Show the old price as a discount</b><small>The previous price is saved as the crossed-out “old” price on the website.</small></span></label></div><div class="modal-f"><button class="btn btn-line" type="button" data-close>Cancel</button><button class="btn btn-primary" type="button" id="priceApply">Apply</button></div></div></div>
<div class="mback" id="badgeModal" role="dialog" aria-modal="true" aria-labelledby="badgeModalT"><div class="modal sm"><div class="modal-h"><h2 id="badgeModalT">Set a badge</h2><button class="btn btn-ic" type="button" data-close aria-label="Close">@icon('x')</button></div><div class="modal-b"><p class="muted sm">Applies to the selected books.</p><div class="field "><label for="bdg">Badge</label><select id="bdg" name="bdg" ><option value="">No badge (remove it)</option>@foreach($badges as $b)<option value="{{ $b }}">{{ $b }}</option>@endforeach</select></div></div><div class="modal-f"><button class="btn btn-line" type="button" data-close>Cancel</button><button class="btn btn-primary" type="button" id="badgeApply">Apply</button></div></div></div>
@endpush
