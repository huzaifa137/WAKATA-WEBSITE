/* ==========================================================
   WAKATA navbar sign-in control (shared by every public page)
   Signed out: Sign in / Create account / Admin sign in
   Signed in:  customer menu (dashboard, downloads, orders...)
   The signed-in customer comes from Laravel (WK.user, set in partials/site/wk-globals).
   ========================================================== */
(function () {
  'use strict';
  var tools = document.querySelector('.hdr-tools'); if (!tools || document.getElementById('acct')) return;
  var user = WK.user || null;
  var I = function (p) { return '<svg class="ico" viewBox="0 0 24 24" aria-hidden="true">' + p + '</svg>'; };
  var P = {
    user: '<circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/>',
    shield: '<path d="M12 3l8 3v6c0 4.5-3.2 8.2-8 9-4.8-.8-8-4.5-8-9V6z"/><polyline points="8.5 12 11 14.5 15.5 9.5"/>',
    grid: '<rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/>',
    dl: '<path d="M12 3v12"/><polyline points="7 11 12 16 17 11"/><path d="M4 20h16"/>',
    box: '<path d="M3 8l9-5 9 5v11a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1z"/><polyline points="3 8 12 13 21 8"/><line x1="12" y1="13" x2="12" y2="21"/>',
    heart: '<path d="M12 21s-7-4.6-9.2-9A5.2 5.2 0 0 1 12 6.5 5.2 5.2 0 0 1 21.2 12C19 16.4 12 21 12 21z"/>',
    out: '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/>'
  };
  var esc = function (s) { return String(s || '').replace(/[&<>"]/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]; }); };
  var wrap = document.createElement('div'); wrap.className = 'acct'; wrap.id = 'acct';
  var html;
  if (user && user.email) {
    var name = user.name || user.email.split('@')[0], ini = name.split(/\s+/).filter(Boolean).slice(0, 2).map(function (w) { return w[0]; }).join('').toUpperCase();
    html = '<button class="acct-btn" type="button" aria-haspopup="true" aria-expanded="false" aria-label="My account"><span class="acct-av">' + esc(ini) + '</span><span class="acct-lbl">Hi, ' + esc(name.split(/\s+/)[0]) + '</span></button>' +
      '<div class="acct-menu" role="menu"><div class="acct-h"><span class="acct-av lg">' + esc(ini) + '</span><span><b>' + esc(name) + '</b><span>' + esc(user.email) + '</span></span></div>' +
      '<a role="menuitem" href="' + WK.r.account + '">' + I(P.grid) + 'My dashboard</a><a role="menuitem" href="' + WK.r.accountLibrary + '">' + I(P.dl) + 'My downloads</a><a role="menuitem" href="' + WK.r.accountOrders + '">' + I(P.box) + 'My orders</a><a role="menuitem" href="' + WK.r.accountSaved + '">' + I(P.heart) + 'Saved items</a><hr>' +
      '<button role="menuitem" type="button" data-out>' + I(P.out) + 'Sign out</button></div>';
  } else {
    html = '<button class="acct-btn" type="button" aria-haspopup="true" aria-expanded="false" aria-label="Sign in or create an account">' + I(P.user) + '<span class="acct-lbl">Sign in</span></button>' +
      '<div class="acct-menu" role="menu"><div class="acct-h solo"><span><b>Welcome to WAKATA</b><span>Sign in to download your PDFs and follow your orders.</span></span></div>' +
      '<a class="acct-primary" role="menuitem" href="' + WK.r.accountLogin + '">Sign in</a><a class="acct-line" role="menuitem" href="' + WK.r.accountRegister + '">Create an account</a><hr>' +
      '<a class="acct-admin" role="menuitem" href="' + WK.r.adminLogin + '">' + I(P.shield) + '<span><b>Admin sign in</b><small>For WAKATA staff only</small></span></a></div>';
  }
  wrap.innerHTML = html;
  tools.insertBefore(wrap, tools.querySelector('.cart-btn') || tools.firstChild);
  var btn = wrap.querySelector('.acct-btn');
  var set = function (o) { wrap.classList.toggle('open', o); btn.setAttribute('aria-expanded', o ? 'true' : 'false'); };
  btn.addEventListener('click', function (e) { e.stopPropagation(); set(!wrap.classList.contains('open')); });
  document.addEventListener('click', function (e) { if (!wrap.contains(e.target)) set(false); });
  document.addEventListener('keydown', function (e) { if (e.key === 'Escape') { set(false); } });
  var out = wrap.querySelector('[data-out]'); if (out) out.addEventListener('click', function () {
    var f = document.createElement('form'); f.method = 'POST'; f.action = WK.r.accountLogout;
    f.innerHTML = '<input type="hidden" name="_token" value="' + esc(WK.csrf) + '">';
    document.body.appendChild(f); f.submit();
  });
  /* Signed-in customers: pre-fill the checkout details on the cart page */
  if (user && user.email) {
    var fill = function (id, v) { var el = document.getElementById(id); if (el && v && !el.value) el.value = v; };
    fill('o-name', user.name); fill('o-phone', user.phone); fill('o-email', user.email);
  }
})();
