<!doctype html>
<html lang="en">
<head>
@include('partials.account.head')
</head>
<body data-page="@yield('page')">
<div class="app">
@include('partials.account.sidebar')
<div class="sb-overlay" id="sbOverlay"></div><div class="main">
@include('partials.account.topbar')
<main class="content" id="main">
@yield('content')
</main>
</div>
</div>
@stack('modals')
@include('partials.account.scripts')
</body>
</html>
