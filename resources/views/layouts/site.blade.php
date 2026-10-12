<!doctype html>
<html lang="en">
<head>
@include('partials.site.head')
</head>
<body data-page="@yield('page')">
<a class="skip" href="#main">Skip to content</a>
@include('partials.site.topstrip')
@include('partials.site.header')
<main id="main">
@yield('content')
</main>
@include('partials.site.newsletter')
@include('partials.site.footer')
@include('partials.site.scripts')
</body>
</html>
