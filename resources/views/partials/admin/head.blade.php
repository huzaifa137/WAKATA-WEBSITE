<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex,nofollow">
@include('partials.admin.auth-state')
<title>@yield('title', 'Dashboard') | WAKATA Admin</title>
<meta name="theme-color" content="#043AA0">
<link rel="icon" type="image/png" href="{{ asset('assets/img/favicon.png') }}">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Public+Sans:wght@400;500;600;700;800&family=Source+Serif+4:opsz,wght@8..60,600;8..60,700;8..60,800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="{{ asset('assets/admin/css/admin.css') }}">
<script>try{var t=localStorage.getItem('wk_theme');if(t)document.documentElement.setAttribute('data-theme',t);if(localStorage.getItem('wk_sb')==='mini'&&innerWidth>1024)document.documentElement.setAttribute('data-sb','mini')}catch(e){}</script>
@stack('head')
