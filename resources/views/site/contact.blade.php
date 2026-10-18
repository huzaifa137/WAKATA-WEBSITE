@extends('layouts.site')

@section('title', \App\Support\SiteContent::seo('contact', 'title'))
@section('description', \App\Support\SiteContent::seo('contact', 'desc'))
@section('page', 'contact')

@section('content')
<section class="page-head"><div class="wrap"><div class="crumbs"><a href="{{ route('site.home') }}">Home</a><span>/</span>Contact</div><h1>@c('contact.title')</h1><p>@c('contact.intro')</p></div></section>
<section class="sec"><div class="wrap">
  <div class="cards3">
    <div class="info"><svg class="ico" viewBox="0 0 24 24" aria-hidden="true"><path d="M5 4h4l2 5-2.5 1.5a11 11 0 0 0 5 5L15 13l5 2v4a2 2 0 0 1-2 2A16 16 0 0 1 3 6a2 2 0 0 1 2-2z"/></svg><h3>Call or WhatsApp</h3><p><a href="tel:{{ config('wakata.phone_tel') }}">@c('contact.phone')</a></p></div>
    <div class="info"><svg class="ico" viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="5" width="18" height="14" rx="2"/><polyline points="3 7 12 13 21 7"/></svg><h3>Email</h3><p><a href="mailto:{{ config('wakata.email') }}">@c('contact.email')</a></p></div>
    <div class="info"><svg class="ico" viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"/><polyline points="12 7 12 12 15 14"/></svg><h3>Opening hours</h3><p>@c('contact.hours')</p></div>
  </div>
  <div class="split split-contact" style="margin-top:2.4rem">
    <form class="panel" data-post="{{ route('contact.store') }}" data-done="@c('contact.thanks')" novalidate>
      <h2>Send a message</h2>
      <div class="field"><label for="c-name">Your name</label><input id="c-name" name="name" maxlength="120" required><span class="err">Enter your name.</span></div>
      <div class="row2"><div class="field"><label for="c-phone">Phone</label><input id="c-phone" name="phone" type="tel" maxlength="30" required><span class="err">Enter a valid phone number.</span></div>
      <div class="field"><label for="c-email">Email</label><input id="c-email" name="email" type="email" maxlength="190" required><span class="err">Enter a valid email address.</span></div></div>
      <div class="field"><label for="c-topic">Topic</label><select id="c-topic" name="topic"><option>Order or delivery</option><option>Bulk or school quotation</option><option>Mock examinations</option><option>Something else</option></select></div>
      <div class="field"><label for="c-msg">Message</label><textarea id="c-msg" name="message" maxlength="5000" required></textarea><span class="err">Write a short message.</span></div>
      <div style="position:absolute;left:-9999px" aria-hidden="true"><label>Leave this empty<input name="website" tabindex="-1" autocomplete="off"></label></div>
      <button class="btn btn-primary" type="submit">Send message</button>
    </form>
    <div class="map-card">
      <div class="map-info">
        <span class="pin"><svg class="ico" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 21s7-6.2 7-12a7 7 0 0 0-14 0c0 5.8 7 12 7 12z"/><circle cx="12" cy="9" r="2.5"/></svg></span>
        <div><h3>Visit our shop</h3><p>@c('contact.address')</p></div>
        <a class="btn btn-primary btn-sm" href="https://www.google.com/maps/dir/?api=1&destination=Bat+Valley+Primary+School%2C+Kampala" target="_blank" rel="noopener">Get directions</a>
      </div>
      <iframe class="map-frame" title="Map showing Bat Valley Primary School, Kampala" src="https://www.google.com/maps?q={{ str_replace(' ', '', \App\Support\SiteContent::get('contact.map')) }}+(Bat+Valley+Primary+School)&z=16&hl=en&output=embed" loading="lazy" referrerpolicy="no-referrer-when-downgrade" allowfullscreen></iframe>
    </div>
  </div>
</div></section>
<section class="sec sec-white" id="faq"><div class="wrap">
  <div class="sec-head"><div><h2>Common questions</h2><p>Quick answers about payment, delivery and school orders.</p></div></div>
  <div class="faq-grid">
    @foreach ($faqs as $f)
    <details class="faq-item"><summary><span class="q-ico">{!! $f->svg() !!}</span><span class="q-txt">{{ $f->question }}</span><span class="q-tog" aria-hidden="true"></span></summary><p>{{ $f->answer }}</p></details>
    @endforeach
  
  </div>
  <div class="faq-cta">
    <div><h3>Still have a question?</h3><p>Call, write or send us a message. We reply within one working day.</p></div>
    <div class="acts"><a class="btn btn-hl" href="tel:{{ config('wakata.phone_tel') }}">Call us</a><a class="btn btn-ghost-w" href="mailto:{{ config('wakata.email') }}">Email us</a></div>
  </div>
</div></section>
@endsection
