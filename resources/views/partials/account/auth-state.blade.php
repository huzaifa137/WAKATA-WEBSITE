<meta name="csrf-token" content="{{ csrf_token() }}">
<script>window.WK = window.WK || {}; WK.user = @json($wkUser ?? null); WK.csrf = @json(csrf_token());</script>
