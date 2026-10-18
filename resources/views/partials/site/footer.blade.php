<footer class="ftr"><div class="wrap">
  <div class="cols">
    <div><a class="brand" href="{{ route('site.home') }}"><img src="{{ asset('assets/img/logo-white.png') }}" alt="" width="70" height="67"><span><b>WAKATA</b><small>Wakiso Kampala Teachers' Association</small></span></a>
      <p>@c('footer.about')</p><div class="pay"><span>MTN MoMo</span><span>Airtel Money</span><span>Visa</span><span>Mastercard</span></div></div>
    <div><h4>Shop</h4><ul><li><a href="{{ route('site.books') }}">Books</a></li><li><a href="{{ route('site.papers') }}">Past papers</a></li><li><a href="{{ route('site.sets') }}">Exam sets</a></li><li><a href="{{ route('site.packages') }}">Holiday packages</a></li><li><a href="{{ route('site.cart') }}">Your cart</a></li></ul></div>
    <div><h4>WAKATA</h4><ul><li><a href="{{ route('site.about') }}">About us</a></li><li><a href="{{ route('site.contact') }}">Contact</a></li><li><a href="{{ route('site.contact') }}#faq">Questions and answers</a></li></ul></div>
    <div><h4>Contact</h4><ul><li><a href="tel:{{ config('wakata.phone_tel') }}">{{ config('wakata.phone') }}</a></li><li><a href="mailto:{{ config('wakata.email') }}">{{ config('wakata.email') }}</a></li><li>{{ config('wakata.address') }}</li><li>{{ config('wakata.hours') }}</li></ul></div>
  </div>
  <div class="bottom"><span>{{ \App\Support\SiteContent::copyright() }}</span><span>@c('footer.tagline')</span></div>
</div></footer>
