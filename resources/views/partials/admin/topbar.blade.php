<header class="topbar">
  <button class="iconbtn tb-toggle" id="sbToggle" type="button" aria-label="Toggle menu">@icon('menu')</button>
  <div class="tb-search"><label class="sr" for="gsearch">Search the dashboard</label>@icon('search')<input id="gsearch" type="search" placeholder="Search orders, books, customers…" readonly data-open-palette><kbd>Ctrl K</kbd></div>
  <div class="tb-actions">
    <a class="btn btn-line btn-sm" href="{{ route('site.home') }}" target="_blank" rel="noopener">@icon('external')<span class="hide-sm">View website</span></a>
    <button class="iconbtn" id="themeBtn" type="button" aria-label="Switch light or dark theme">@icon('moon')</button>
    <div class="dd" id="notifDd"><button class="iconbtn" type="button" aria-label="Notifications" aria-haspopup="true" data-dd>@icon('bell')<span class="dot"></span></button>
      <div class="dd-menu notif" id="notifList"><div class="dd-h">Notifications</div></div></div>
    <div class="dd"><button class="profile-btn" type="button" aria-haspopup="true" data-dd>@if ($authAdmin->avatar_url ?? null)<img class="avatar sm" src="{{ $authAdmin->avatar_url }}" alt="" style="object-fit:cover">@else<span class="avatar sm" data-me-initials>{{ $authAdmin->initials ?? 'AD' }}</span>@endif<div><b data-me-name>{{ $authAdmin->name ?? 'Admin' }}</b><small>{{ $authAdmin->role_label ?? 'Admin' }}</small></div></button>
      <div class="dd-menu"><a href="{{ route('admin.profile') }}">@icon('user')My profile</a><a href="{{ route('admin.settings') }}">@icon('settings')Settings</a><a href="{{ route('admin.activity') }}">@icon('history')Activity log</a><hr><a href="{{ route('site.home') }}" target="_blank" rel="noopener">@icon('external')View website</a><button type="button" data-lock>@icon('lock')Lock screen</button><button type="button" data-logout>@icon('logout')Sign out</button></div></div>
  </div>
</header>
