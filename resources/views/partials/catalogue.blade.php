{{-- The catalogue from the database (books, papers, sets, packages, levels, subjects) for the page scripts. Loaded before data.js. --}}
<script>
window.WK = window.WK || {};
WK.catalogue = @json(\App\Support\Catalogue::frontend());
</script>
