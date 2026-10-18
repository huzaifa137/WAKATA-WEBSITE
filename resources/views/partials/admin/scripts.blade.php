<form id="logoutForm" method="POST" action="{{ route('admin.logout') }}" hidden>@csrf</form>
<form id="lockForm" method="POST" action="{{ route('admin.lock') }}" hidden>@csrf</form>
@include('partials.catalogue')
@hasSection('sales')<script>WK.sales = @json(\App\Support\AdminSales::payload(request()->attributes->get('admin')));</script>@endif
@hasSection('eng')<script>WK.eng = @json(\App\Support\AdminEngagement::payload());</script>@endif
@hasSection('adm')<script>WK.adm = @json(\App\Support\AdminCatalogue::payload()); WK.old = @json(session()->getOldInput());</script>@endif
<script src="{{ asset('assets/js/data.js') }}"></script>
<script src="{{ asset('assets/admin/js/admin-data.js') }}"></script>
<script src="{{ asset('assets/admin/js/charts.js') }}"></script>
<script src="{{ asset('assets/admin/js/admin-content.js') }}"></script>
<script src="{{ asset('assets/admin/js/admin.js') }}"></script>
<script src="{{ asset('assets/admin/js/pages.js') }}"></script>
@stack('scripts')
@include('partials.flash')
