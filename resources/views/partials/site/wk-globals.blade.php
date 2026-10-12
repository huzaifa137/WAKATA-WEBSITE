{{-- Values the front-end JS needs from Laravel (URLs work in a sub-folder too, e.g. XAMPP). --}}
<script>
window.WK = {
  base: @json(rtrim(url('/'), '/')),
  r: {
    home: @json(route('site.home')),
    books: @json(route('site.books')),
    book: @json(route('site.book')),
    papers: @json(route('site.papers')),
    sets: @json(route('site.sets')),
    packages: @json(route('site.packages')),
    about: @json(route('site.about')),
    contact: @json(route('site.contact')),
    cart: @json(route('site.cart'))
  },
  contact: { wa: @json(config('wakata.whatsapp')), tel: @json(config('wakata.phone_tel')) }
};
WK.u = function (p) { p = (p || '').replace(/^\/+/, ''); return WK.base + (p ? '/' + p : ''); };
</script>
