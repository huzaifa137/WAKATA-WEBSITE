<section class="news"><div class="wrap">
  <div><h2>@c('news.title')</h2><p>@c('news.text')</p></div>
  <form id="newsletter" data-post="{{ route('newsletter.subscribe') }}" data-source="{{ request()->routeIs('site.home') ? 'Home page' : (request()->routeIs('site.contact') ? 'Contact page' : (request()->routeIs('site.cart') ? 'Checkout' : 'Footer')) }}" novalidate><label class="sr" for="nl">Email address</label><input id="nl" name="email" type="email" placeholder="Your email address" required><input name="website" tabindex="-1" autocomplete="off" aria-hidden="true" style="position:absolute;left:-9999px"><button class="btn btn-primary" type="submit">Subscribe</button></form>
</div></section>
