<!doctype html>
<html lang="en">
<head>
@include('partials.admin.head')
</head>
<body data-page="@yield('page')">
<div class="app">
@include('partials.admin.sidebar')
<div class="sb-overlay" id="sbOverlay"></div><div class="main">
@include('partials.admin.topbar')
<main class="content" id="main">
@yield('content')
</main>
</div>
</div>
@stack('modals')
@include('partials.admin.scripts')
</body>
</html>
