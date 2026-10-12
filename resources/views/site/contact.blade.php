@extends('layouts.site')

@section('title', 'Contact | WAKATA Stationers')
@section('page', 'contact')

@section('content')
<section class="page-head"><div class="wrap"><div class="crumbs"><a href="{{ route('site.home') }}">Home</a><span>/</span>Contact</div><h1>Contact us</h1><p>Questions about an order, a bulk quotation or a mock series? Call, write or visit us.</p></div></section>
<section class="sec"><div class="wrap">
  <div class="cards3">
    <div class="info"><svg class="ico" viewBox="0 0 24 24" aria-hidden="true"><path d="M5 4h4l2 5-2.5 1.5a11 11 0 0 0 5 5L15 13l5 2v4a2 2 0 0 1-2 2A16 16 0 0 1 3 6a2 2 0 0 1 2-2z"/></svg><h3>Call or WhatsApp</h3><p><a href="tel:+256700000000">+256 700 000 000</a></p></div>
    <div class="info"><svg class="ico" viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="5" width="18" height="14" rx="2"/><polyline points="3 7 12 13 21 7"/></svg><h3>Email</h3><p><a href="mailto:info@wakatastationers.com">info@wakatastationers.com</a></p></div>
    <div class="info"><svg class="ico" viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"/><polyline points="12 7 12 12 15 14"/></svg><h3>Opening hours</h3><p>Monday to Saturday, 8am to 6pm</p></div>
  </div>
  <div class="split split-contact" style="margin-top:2.4rem">
    <form class="panel" data-demo="Thank you. We have your message and will reply within one working day." novalidate>
      <h2>Send a message</h2>
      <div class="field"><label for="c-name">Your name</label><input id="c-name" required><span class="err">Enter your name.</span></div>
      <div class="row2"><div class="field"><label for="c-phone">Phone</label><input id="c-phone" type="tel" required><span class="err">Enter a valid phone number.</span></div>
      <div class="field"><label for="c-email">Email</label><input id="c-email" type="email" required><span class="err">Enter a valid email address.</span></div></div>
      <div class="field"><label for="c-topic">Topic</label><select id="c-topic"><option>Order or delivery</option><option>Bulk or school quotation</option><option>Mock examinations</option><option>Something else</option></select></div>
      <div class="field"><label for="c-msg">Message</label><textarea id="c-msg" required></textarea><span class="err">Write a short message.</span></div>
      <button class="btn btn-primary" type="submit">Send message</button>
    </form>
    <div class="map-card">
      <div class="map-info">
        <span class="pin"><svg class="ico" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 21s7-6.2 7-12a7 7 0 0 0-14 0c0 5.8 7 12 7 12z"/><circle cx="12" cy="9" r="2.5"/></svg></span>
        <div><h3>Visit our shop</h3><p>Bat Valley Primary School, Kampala, Uganda</p></div>
        <a class="btn btn-primary btn-sm" href="https://www.google.com/maps/dir/?api=1&destination=Bat+Valley+Primary+School%2C+Kampala" target="_blank" rel="noopener">Get directions</a>
      </div>
      <iframe class="map-frame" title="Map showing Bat Valley Primary School, Kampala" src="https://www.google.com/maps?q=0.3225393,32.5736475+(Bat+Valley+Primary+School)&z=16&hl=en&output=embed" loading="lazy" referrerpolicy="no-referrer-when-downgrade" allowfullscreen></iframe>
    </div>
  </div>
</div></section>
<section class="sec sec-white" id="faq"><div class="wrap">
  <div class="sec-head"><div><h2>Common questions</h2><p>Quick answers about payment, delivery and school orders.</p></div></div>
  <div class="faq-grid">
    <details class="faq-item"><summary><span class="q-ico"><svg class="ico" viewBox="0 0 24 24" aria-hidden="true"><rect x="2" y="5" width="20" height="14" rx="2"/><line x1="2" y1="10" x2="22" y2="10"/></svg></span><span class="q-txt">How do I pay?</span><span class="q-tog" aria-hidden="true"></span></summary><p>Pay with MTN Mobile Money, Airtel Money, Visa or Mastercard. You can also pay in cash when you collect from our shop.</p></details>
    <details class="faq-item"><summary><span class="q-ico"><svg class="ico" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3v12"/><polyline points="7 11 12 16 17 11"/><path d="M4 20h16"/></svg></span><span class="q-txt">How fast do PDF downloads arrive?</span><span class="q-tog" aria-hidden="true"></span></summary><p>PDFs are emailed as soon as your payment is confirmed.</p></details>
    <details class="faq-item"><summary><span class="q-ico"><svg class="ico" viewBox="0 0 24 24" aria-hidden="true"><rect x="1" y="6" width="13" height="10" rx="1"/><path d="M14 9h4l3 3v4h-7"/><circle cx="6" cy="18" r="2"/><circle cx="17" cy="18" r="2"/></svg></span><span class="q-txt">Do you deliver outside Kampala?</span><span class="q-tog" aria-hidden="true"></span></summary><p>Yes. Choose upcountry delivery at checkout and we will send your order by courier.</p></details>
    <details class="faq-item"><summary><span class="q-ico"><svg class="ico" viewBox="0 0 24 24" aria-hidden="true"><path d="M3 8l9-5 9 5v11a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1z"/><polyline points="3 8 12 13 21 8"/><line x1="12" y1="13" x2="12" y2="21"/></svg></span><span class="q-txt">Can my school get a bulk price?</span><span class="q-tog" aria-hidden="true"></span></summary><p>Yes. Send us a message with the titles and quantities and we will send a quotation.</p></details>
    <details class="faq-item"><summary><span class="q-ico"><svg class="ico" viewBox="0 0 24 24" aria-hidden="true"><path d="M4 9l1-5h14l1 5"/><path d="M4 9v11h16V9"/><path d="M4 9a3 3 0 0 0 5.3 1.9A3 3 0 0 0 12 12a3 3 0 0 0 2.7-1.1A3 3 0 0 0 20 9"/></svg></span><span class="q-txt">Can I collect my order from the shop?</span><span class="q-tog" aria-hidden="true"></span></summary><p>Yes. Pickup is free. Choose pickup at checkout and collect from us during opening hours.</p></details>
    <details class="faq-item"><summary><span class="q-ico"><svg class="ico" viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"/><polyline points="12 7 12 12 15 14"/></svg></span><span class="q-txt">When are you open?</span><span class="q-tog" aria-hidden="true"></span></summary><p>Monday to Saturday, 8am to 6pm. Messages sent outside these hours are answered the next working day.</p></details>
  </div>
  <div class="faq-cta">
    <div><h3>Still have a question?</h3><p>Call, write or send us a message. We reply within one working day.</p></div>
    <div class="acts"><a class="btn btn-hl" href="tel:+256700000000">Call us</a><a class="btn btn-ghost-w" href="mailto:info@wakatastationers.com">Email us</a></div>
  </div>
</div></section>
@endsection
