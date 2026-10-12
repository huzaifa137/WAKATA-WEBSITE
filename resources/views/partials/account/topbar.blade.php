<header class="topbar">
  <button class="iconbtn tb-toggle" id="sbToggle" type="button" aria-label="Toggle menu">@icon('menu')</button>
  <div class="tb-search"><label class="sr" for="gsearch">Search</label>@icon('search')<input id="gsearch" type="search" placeholder="Search your orders, downloads, the shop…" readonly data-open-palette><kbd>Ctrl K</kbd></div>
  <div class="tb-actions">
    <a class="btn btn-line btn-sm" href="{{ route('site.home') }}">@icon('shop')<span class="hide-sm">Back to shop</span></a>
    <a class="iconbtn cart-pill" href="{{ route('site.cart') }}" aria-label="Open cart">@icon('cart')<span class="n" data-cart-count hidden></span></a>
    <button class="iconbtn" id="themeBtn" type="button" aria-label="Switch light or dark theme">@icon('moon')</button>
    <div class="dd" id="notifDd"><button class="iconbtn" type="button" aria-label="Notifications" aria-haspopup="true" data-dd>@icon('bell')<span class="dot"></span></button>
      <div class="dd-menu notif" id="notifList"><div class="dd-h">Notifications</div></div></div>
    <div class="dd"><button class="profile-btn" type="button" aria-haspopup="true" data-dd><span class="avatar sm" data-me-initials>JS</span><div><b data-me-name>Customer</b><small>Customer</small></div></button>
      <div class="dd-menu"><a href="{{ route('account.profile') }}">@icon('user')Profile & settings</a><a href="{{ route('account.orders') }}">@icon('box')My orders</a><a href="{{ route('account.library') }}">@icon('download')My downloads</a><hr><a href="{{ route('site.home') }}">@icon('shop')Back to the shop</a><button type="button" data-logout>@icon('logout')Sign out</button></div></div>
  </div>
</header>
