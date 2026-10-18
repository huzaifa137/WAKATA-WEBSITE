@extends('layouts.admin')

@section('title', 'Messages')
@section('page', 'messages')
@section('eng', '1')

@section('content')
<div class="pagehead"><div><h1>Messages</h1><p>Questions and quotation requests sent from the Contact page.</p></div><div class="ph-actions"><a class="btn btn-line btn-sm" href="{{ route('site.contact') }}" target="_blank" rel="noopener">@icon('external')Contact page</a></div></div>
<div class="card" style="overflow:hidden"><div class="chips" style="padding:.8rem 1rem;border-bottom:1px solid var(--line)"><button class="chip on" type="button" data-flt="all">Inbox <span class="n xs" style="opacity:.7"></span></button><button class="chip" type="button" data-flt="new">Unread <span class="n xs" style="opacity:.7"></span></button><button class="chip" type="button" data-flt="quote">Quotations <span class="n xs" style="opacity:.7"></span></button><button class="chip" type="button" data-flt="replied">Answered <span class="n xs" style="opacity:.7"></span></button><button class="chip" type="button" data-flt="archived">Archived <span class="n xs" style="opacity:.7"></span></button></div><div class="inbox"><div class="threads" id="threads"></div><div class="reader" id="reader"></div><aside class="reader-side" id="readerSide"></aside></div></div>
@endsection

@push('modals')
<div class="mback" id="quoteModal" role="dialog" aria-modal="true" aria-labelledby="quoteModalT"><div class="modal wide"><div class="modal-h"><h2 id="quoteModalT">Create quotation</h2><button class="btn btn-ic" type="button" data-close aria-label="Close">@icon('x')</button></div><div class="modal-b"><div class="row2"><div class="field "><label for="qn">Prepared for</label><input id="qn" name="qn" type="text" value="" placeholder="School name" ></div><div class="field "><label for="qv">Valid until</label><input id="qv" name="qv" type="date" value="" placeholder="" ></div></div><table class="mini-table" style="margin:.6rem 0"><thead><tr><th>Item</th><th>Qty</th><th class="num">Unit price</th><th class="num">Total</th><th></th></tr></thead><tbody id="qLines"></tbody></table><datalist id="qItems"></datalist><button class="btn btn-line btn-sm" type="button" id="qAdd">@icon('plus')Add line</button><div class="field" style="margin-top:.8rem"><label for="qDel">Delivery</label><select id="qDel"></select></div><div class="totals mt" id="qTotals"></div><div class="field "><label for="qt">Terms</label><textarea id="qt" name="qt" placeholder="" >Prices are valid for 14 days. Payment by mobile money, card or cash on delivery.</textarea></div></div><div class="modal-f"><button class="btn btn-line" type="button" data-close>Cancel</button><button class="btn btn-primary" type="button" id="qSend">Email quotation</button></div></div></div>
@endpush
