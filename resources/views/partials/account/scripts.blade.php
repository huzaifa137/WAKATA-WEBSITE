<form id="logoutForm" method="POST" action="{{ route('account.logout') }}" hidden>@csrf</form>
<script src="{{ asset('assets/js/data.js') }}"></script>
<script src="{{ asset('assets/account/js/account-data.js') }}"></script>
<script src="{{ asset('assets/account/js/charts.js') }}"></script>
<script src="{{ asset('assets/account/js/account-content.js') }}"></script>
<script src="{{ asset('assets/account/js/account.js') }}"></script>
<script src="{{ asset('assets/account/js/account-pages.js') }}"></script>
@stack('scripts')
@include('partials.flash')
