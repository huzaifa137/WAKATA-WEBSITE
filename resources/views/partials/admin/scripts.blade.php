<form id="logoutForm" method="POST" action="{{ route('admin.logout') }}" hidden>@csrf</form>
<form id="lockForm" method="POST" action="{{ route('admin.lock') }}" hidden>@csrf</form>
@include('partials.catalogue')
<script src="{{ asset('assets/js/data.js') }}"></script>
<script src="{{ asset('assets/admin/js/admin-data.js') }}"></script>
<script src="{{ asset('assets/admin/js/charts.js') }}"></script>
<script src="{{ asset('assets/admin/js/admin-content.js') }}"></script>
<script src="{{ asset('assets/admin/js/admin.js') }}"></script>
<script src="{{ asset('assets/admin/js/pages.js') }}"></script>
@stack('scripts')
@include('partials.flash')
