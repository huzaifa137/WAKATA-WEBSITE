@extends('layouts.site')

@section('title')Holiday packages | WAKATA Stationers@endsection
@section('page')packages@endsection

@section('content')
<section class="page-head"><div class="wrap"><div class="crumbs"><a href="{{ route('site.home') }}">Home</a><span>/</span>Holiday packages</div><h1>Holiday packages</h1><p>Workbooks, revision tests and mock papers in one bundle, so learning carries on while school is out.</p></div></section><div class="wrap" style="padding-block:2.4rem 3rem"><div class="tabs" id="tabs" role="group" aria-label="Filter by level"></div><div class="grid grid-pk" id="grid" style="padding-top:.8rem"></div></div><section class="sec sec-white" id="faq"><div class="wrap">
  <div class="sec-head"><div><h2>Questions about packages</h2><p>Quick answers about ordering, swaps and what is inside.</p></div></div>
  <div class="faq-grid">
    <details class="faq-item"><summary><span class="q-ico"><svg class="ico" viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="5" width="18" height="16" rx="2"/><line x1="3" y1="10" x2="21" y2="10"/><line x1="8" y1="3" x2="8" y2="7"/><line x1="16" y1="3" x2="16" y2="7"/></svg></span><span class="q-txt">When should I order a holiday package?</span><span class="q-tog" aria-hidden="true"></span></summary><p>Order at least one week before school closes so your package is ready for collection or delivery on the first day of the holiday.</p></details>
    <details class="faq-item"><summary><span class="q-ico"><svg class="ico" viewBox="0 0 24 24" aria-hidden="true"><polyline points="17 1 21 5 17 9"/><path d="M3 11V9a4 4 0 0 1 4-4h14"/><polyline points="7 23 3 19 7 15"/><path d="M21 13v2a4 4 0 0 1-4 4H3"/></svg></span><span class="q-txt">Can I swap a book in the package?</span><span class="q-tog" aria-hidden="true"></span></summary><p>Yes. Tell us at checkout or call us, and we will swap a title for another of the same level.</p></details>
    <details class="faq-item"><summary><span class="q-ico"><svg class="ico" viewBox="0 0 24 24" aria-hidden="true"><path d="M9 11l3 3 8-8"/><path d="M20 12v7a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h9"/></svg></span><span class="q-txt">Do packages include answers?</span><span class="q-tog" aria-hidden="true"></span></summary><p>Every test and mock paper in a package comes with a marking guide for the parent or teacher.</p></details>
    <details class="faq-item"><summary><span class="q-ico"><svg class="ico" viewBox="0 0 24 24" aria-hidden="true"><path d="M4 9l1-5h14l1 5"/><path d="M4 9v11h16V9"/><path d="M4 9a3 3 0 0 0 5.3 1.9A3 3 0 0 0 12 12a3 3 0 0 0 2.7-1.1A3 3 0 0 0 20 9"/></svg></span><span class="q-txt">Can I collect my package from the shop?</span><span class="q-tog" aria-hidden="true"></span></summary><p>Yes. Pickup is free. Choose pickup at checkout and collect from us during opening hours.</p></details>
  </div>
  <div class="faq-cta">
    <div><h3>Need help choosing a package?</h3><p>Tell us the level and subjects and we will suggest the right one.</p></div>
    <div class="acts"><a class="btn btn-hl" href="tel:+256700000000">Call us</a><a class="btn btn-ghost-w" href="{{ route('site.contact') }}">Send a message</a></div>
  </div>
</div></section>
@endsection
