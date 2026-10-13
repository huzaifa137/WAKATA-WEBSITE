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
    cart: @json(route('site.cart')),
    account: @json(route('account.dashboard')),
    accountLogin: @json(route('account.login')),
    accountRegister: @json(route('account.register')),
    accountLibrary: @json(route('account.library')),
    accountOrders: @json(route('account.orders')),
    accountSaved: @json(route('account.saved')),
    accountLogout: @json(route('account.logout')),
    adminLogin: @json(route('admin.login')),
    cartAdd: @json(route('cart.add')),
    cartUpdate: @json(route('cart.update')),
    cartRemove: @json(route('cart.remove')),
    cartClear: @json(route('cart.clear')),
    checkout: @json(route('checkout.store'))
  },
  // The cart lives on the server (session). The cart page gets the full priced cart; every other page only the count.
  cart: @json($cart ?? ['count' => $wkCartCount ?? 0]),
  shop: {
    delivery: @json(collect(config('wakata.delivery'))->map(fn ($d) => ['label' => $d['label'], 'fee' => $d['fee']])),
    payments: @json(config('wakata.payments'))
  },
  user: @json($wkUser ?? null),
  bookId: @json(isset($book) ? $book->slug : null),
  csrf: @json(csrf_token()),
  contact: { wa: @json(config('wakata.whatsapp')), tel: @json(config('wakata.phone_tel')) }
};
WK.u = function (p) { p = (p || '').replace(/^\/+/, ''); return WK.base + (p ? '/' + p : ''); };
</script>
