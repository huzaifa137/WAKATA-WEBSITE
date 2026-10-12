<meta name="csrf-token" content="{{ csrf_token() }}">
<script>window.WK = window.WK || {}; WK.me = @json(isset($authAdmin) ? ['name' => $authAdmin->name, 'email' => $authAdmin->email, 'role' => $authAdmin->role_label] : null) || {};</script>
