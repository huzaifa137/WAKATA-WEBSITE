<meta name="csrf-token" content="{{ csrf_token() }}">
<script>
window.WK = window.WK || {}; WK.user = @json($wkUser ?? null); WK.csrf = @json(csrf_token());
WK.r = Object.assign(WK.r || {}, { cart: @json(route('site.cart')), cartAdd: @json(route('cart.add')) });
WK.cart = { count: @json($wkCartCount ?? 0) };
</script>
