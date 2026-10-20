<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex">
<title>@yield('heading') | WAKATA Stationers</title>
<style>
  :root { color-scheme: light dark; --blue: #043AA0; --ink: #1c2333; --bg: #f5f7fb; --card: #fff; --muted: #5b6478; --line: #e5e8f0; }
  @media (prefers-color-scheme: dark) { :root { --ink: #e8ecf5; --bg: #0f1420; --card: #171e2e; --muted: #9aa4bb; --line: #263049; --blue: #7fa6ff; } }
  * { box-sizing: border-box; }
  body { margin: 0; min-height: 100vh; display: grid; place-items: center; padding: 1.5rem; background: var(--bg); color: var(--ink); font: 16px/1.6 system-ui, -apple-system, "Segoe UI", Roboto, Arial, sans-serif; }
  .box { width: min(520px, 100%); background: var(--card); border: 1px solid var(--line); border-radius: 16px; padding: 2.2rem 1.8rem; text-align: center; box-shadow: 0 8px 30px rgba(10, 30, 80, .08); }
  .code { font-size: 3.4rem; font-weight: 800; color: var(--blue); line-height: 1; margin: 0 0 .4rem; }
  h1 { font-size: 1.35rem; margin: 0 0 .6rem; }
  p { color: var(--muted); margin: 0 0 1.2rem; }
  .btn { display: inline-block; padding: .65rem 1.2rem; border-radius: 10px; background: var(--blue); color: #fff; text-decoration: none; font-weight: 600; margin: .2rem; }
  .btn.line { background: transparent; color: var(--blue); border: 1px solid var(--blue); }
  small { display: block; margin-top: 1.2rem; color: var(--muted); }
</style>
</head>
<body>
<main class="box">
  <p class="code">@yield('code')</p>
  <h1>@yield('heading')</h1>
  <p>@yield('message')</p>
  <a class="btn" href="{{ url('/') }}">Go to the home page</a>
  @hasSection('retry')<a class="btn line" href="javascript:location.reload()">Try again</a>@endif
  <small>Need help? Call {{ config('wakata.phone') }} or email {{ config('wakata.email') }}.</small>
</main>
</body>
</html>
