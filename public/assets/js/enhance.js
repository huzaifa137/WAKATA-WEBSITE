/* ==========================================================
   WAKATA front end — v2 polish layer (vanilla JS, no dependencies)
   Scroll progress, condensing header, back to top, WhatsApp button,
   scroll reveal, count-up numbers, hero parallax, fly-to-cart, searchable dropdowns.
   Everything here is optional: the site still works if this file fails.
   ========================================================== */
(function () {
  'use strict';
  const $ = (s, r = document) => r.querySelector(s);
  const $$ = (s, r = document) => [...r.querySelectorAll(s)];
  const calm = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  const canHover = window.matchMedia('(hover: hover)').matches;
  const D = window.WK;

  /* ---------- Floating UI (built here so every page gets it) ---------- */
  const bar = document.createElement('div');
  bar.className = 'progress'; bar.setAttribute('aria-hidden', 'true');
  document.body.appendChild(bar);

  const top = document.createElement('button');
  top.type = 'button'; top.className = 'fab totop'; top.setAttribute('aria-label', 'Back to top');
  top.innerHTML = '<svg class="ico" viewBox="0 0 24 24" aria-hidden="true"><line x1="12" y1="19" x2="12" y2="5"/><polyline points="5 12 12 5 19 12"/></svg>';
  top.addEventListener('click', () => window.scrollTo({ top: 0, behavior: calm ? 'auto' : 'smooth' }));
  document.body.appendChild(top);

  /* Placeholder number: replace with WAKATA's real WhatsApp line */
  const wa = document.createElement('a');
  wa.className = 'fab wa'; wa.href = 'https://wa.me/' + WK.contact.wa + '?text=Hello%20WAKATA%2C%20I%20need%20help%20with%20an%20order.';
  wa.target = '_blank'; wa.rel = 'noopener'; wa.setAttribute('aria-label', 'Chat with WAKATA on WhatsApp');
  wa.innerHTML = '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M21 11.5a8.5 8.5 0 0 1-12.6 7.4L3 20.5l1.7-5.2A8.5 8.5 0 1 1 21 11.5z"/><path d="M9 8.8c.3 2.6 2.6 5 5.4 5.7l1.3-1.3-2-1-1 .7a4.6 4.6 0 0 1-1.9-1.9l.7-1-1-2z"/></svg><span>Chat with us</span>';
  document.body.appendChild(wa);

  /* ---------- Scroll: progress bar, header, back-to-top ---------- */
  const hdr = $('.hdr');
  let ticking = false;
  function onScroll() {
    const y = window.scrollY, max = document.documentElement.scrollHeight - innerHeight;
    bar.style.transform = 'scaleX(' + (max > 0 ? Math.min(1, y / max) : 0) + ')';
    if (hdr) hdr.classList.toggle('scrolled', y > 40);
    top.classList.toggle('show', y > 700);
    ticking = false;
  }
  window.addEventListener('scroll', () => { if (!ticking) { ticking = true; requestAnimationFrame(onScroll); } }, { passive: true });
  onScroll();

  /* ---------- Scroll reveal for static blocks ---------- */
  const targets = [
    ['.sec-head', 0], ['.levels>*', 90], ['.steps>*', 90], ['.cards3>*', 90], ['.values>*', 90],
    ['.quotes>*', 100], ['.split>*', 120], ['.finder', 0], ['.pay', 0], ['.faq details', 50], ['.faq-item', 60], ['.faq-cta', 0],
    ['.panel', 0], ['.specs', 0]
  ];
  if (!calm && 'IntersectionObserver' in window) {
    const io = new IntersectionObserver(entries => {
      entries.forEach(en => {
        if (!en.isIntersecting) return;
        const el = en.target; io.unobserve(el);
        el.classList.add('in');
        /* drop the helper classes afterwards so hover transforms work */
        setTimeout(() => el.classList.remove('rv', 'in'), 1100);
      });
    }, { threshold: 0.12, rootMargin: '0px 0px -40px 0px' });
    targets.forEach(([sel, step]) => {
      $$(sel).forEach((el, i) => {
        el.classList.add('rv'); el.style.setProperty('--d', (step * (i % 4)) + 'ms'); io.observe(el);
      });
    });
  }

  /* ---------- Count-up numbers ---------- */
  const counts = $$('[data-count]');
  function final(el) {
    const k = el.dataset.count;
    if (D && D[k] && D[k].length != null) return D[k].length;
    return parseInt(k, 10) || 0;
  }
  counts.forEach(el => { el.dataset.final = final(el); });
  function run(el) {
    const end = +el.dataset.final;
    if (calm || !end) { el.textContent = end; return; }
    const t0 = performance.now(), dur = 1300;
    (function step(t) {
      const p = Math.min(1, (t - t0) / dur), e = 1 - Math.pow(1 - p, 3);
      el.textContent = Math.round(end * e);
      if (p < 1) requestAnimationFrame(step);
    })(t0);
  }
  if (counts.length && 'IntersectionObserver' in window) {
    const co = new IntersectionObserver(es => es.forEach(en => { if (en.isIntersecting) { co.unobserve(en.target); run(en.target); } }), { threshold: 0.6 });
    counts.forEach(el => { el.textContent = '0'; co.observe(el); });
  }

  /* ---------- Hero parallax ---------- */
  const hero = $('.hero');
  if (hero && canHover && !calm) {
    hero.addEventListener('mousemove', e => {
      const r = hero.getBoundingClientRect();
      hero.style.setProperty('--mx', ((e.clientX - r.left) / r.width * 2 - 1).toFixed(3));
      hero.style.setProperty('--my', ((e.clientY - r.top) / r.height * 2 - 1).toFixed(3));
    });
    hero.addEventListener('mouseleave', () => { hero.style.setProperty('--mx', 0); hero.style.setProperty('--my', 0); });
  }

  /* ---------- Searchable dropdowns ----------
     Every <select> becomes a button + search box + filtered list.
     The real <select> stays in the page (hidden), so forms, validation and
     change listeners keep working exactly as before. */
  const CHEV = '<svg class="chev" viewBox="0 0 24 24" aria-hidden="true"><polyline points="6 9 12 15 18 9"/></svg>';
  const LENS = '<svg class="ico" viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="7"/><line x1="21" y1="21" x2="16.5" y2="16.5"/></svg>';
  let ssN = 0, ssOpen = null;

  function closeSS(focusBtn) {
    if (!ssOpen) return;
    const s = ssOpen; ssOpen = null;
    s.wrap.classList.remove('open', 'up');
    s.panel.hidden = true; s.btn.setAttribute('aria-expanded', 'false');
    if (focusBtn) s.btn.focus();
  }
  document.addEventListener('mousedown', e => { if (ssOpen && !ssOpen.wrap.contains(e.target)) closeSS(false); });
  document.addEventListener('focusin', e => { if (ssOpen && !ssOpen.wrap.contains(e.target)) closeSS(false); });

  function makeSearchable(sel) {
    if (sel.dataset.ss) return;
    sel.dataset.ss = '1';
    const id = sel.id || ('ss-' + (++ssN));
    const wrap = document.createElement('div');
    wrap.className = 'ss' + (sel.closest('.field') ? '' : ' ss-pill');
    sel.parentNode.insertBefore(wrap, sel);
    wrap.appendChild(sel);
    sel.classList.add('ss-native'); sel.tabIndex = -1; sel.setAttribute('aria-hidden', 'true');

    const lab = document.querySelector('label[for="' + id + '"]');
    if (lab && !lab.id) lab.id = id + '-lab';

    const btn = document.createElement('button');
    btn.type = 'button'; btn.className = 'ss-btn';
    btn.setAttribute('role', 'combobox'); btn.setAttribute('aria-haspopup', 'listbox');
    btn.setAttribute('aria-expanded', 'false'); btn.setAttribute('aria-controls', id + '-list');
    if (lab) btn.setAttribute('aria-labelledby', lab.id);
    btn.innerHTML = '<span class="ss-val"></span>' + CHEV;
    wrap.appendChild(btn);

    const panel = document.createElement('div');
    panel.className = 'ss-panel'; panel.hidden = true;
    panel.innerHTML = '<div class="ss-search">' + LENS + '<input type="text" autocomplete="off" spellcheck="false" placeholder="Type to search" aria-label="Search options"></div><ul class="ss-list" id="' + id + '-list" role="listbox"></ul><div class="ss-empty" hidden>No matches found</div>';
    wrap.appendChild(panel);

    const val = $('.ss-val', btn), input = $('input', panel), list = $('.ss-list', panel), empty = $('.ss-empty', panel);
    const state = { wrap, btn, panel };
    let items = [], active = -1;

    const label = () => { const o = sel.options[sel.selectedIndex]; val.textContent = o ? o.text : ''; };
    label();

    function build() {
      list.innerHTML = '';
      items = [...sel.options].map((o, i) => {
        const li = document.createElement('li');
        li.setAttribute('role', 'option'); li.dataset.i = i; li.dataset.t = o.text;
        li.setAttribute('aria-selected', i === sel.selectedIndex ? 'true' : 'false');
        li.appendChild(document.createElement('span')).textContent = o.text;
        list.appendChild(li);
        return li;
      });
    }
    function setActive(n, scroll) {
      const vis = items.filter(li => !li.hidden);
      items.forEach(li => li.classList.remove('act'));
      if (!vis.length) { active = -1; return; }
      n = Math.max(0, Math.min(vis.length - 1, n));
      active = n; vis[n].classList.add('act');
      if (scroll !== false) vis[n].scrollIntoView({ block: 'nearest' });
    }
    function filter() {
      const q = input.value.trim().toLowerCase();
      let any = false;
      items.forEach(li => {
        const t = li.dataset.t, k = q ? t.toLowerCase().indexOf(q) : 0;
        li.hidden = k < 0;
        if (k >= 0) {
          any = true;
          const sp = li.firstChild; sp.textContent = '';
          if (q) {
            sp.append(t.slice(0, k));
            const m = document.createElement('mark'); m.textContent = t.slice(k, k + q.length); sp.append(m);
            sp.append(t.slice(k + q.length));
          } else sp.textContent = t;
        }
      });
      empty.hidden = any;
      const cur = items.findIndex(li => !li.hidden && li.getAttribute('aria-selected') === 'true');
      const vis = items.filter(li => !li.hidden);
      setActive(q ? 0 : Math.max(0, vis.indexOf(items[cur])));
    }
    function open() {
      if (ssOpen === state) return;
      closeSS(false);
      build(); input.value = ''; empty.hidden = true;
      const r = btn.getBoundingClientRect();
      wrap.classList.toggle('up', innerHeight - r.bottom < 330 && r.top > innerHeight - r.bottom);
      wrap.classList.add('open'); panel.hidden = false; btn.setAttribute('aria-expanded', 'true');
      ssOpen = state;
      filter();
      input.focus({ preventScroll: true });
    }
    function choose(li) {
      if (!li) return;
      sel.selectedIndex = +li.dataset.i;
      label();
      closeSS(true);
      sel.dispatchEvent(new Event('change', { bubbles: true }));
    }

    btn.addEventListener('click', () => ssOpen === state ? closeSS(true) : open());
    btn.addEventListener('keydown', e => {
      if (e.key === 'ArrowDown' || e.key === 'ArrowUp') { e.preventDefault(); open(); }
    });
    input.addEventListener('input', filter);
    input.addEventListener('keydown', e => {
      const vis = items.filter(li => !li.hidden);
      if (e.key === 'ArrowDown') { e.preventDefault(); setActive(active + 1); }
      else if (e.key === 'ArrowUp') { e.preventDefault(); setActive(active - 1); }
      else if (e.key === 'Home') { e.preventDefault(); setActive(0); }
      else if (e.key === 'End') { e.preventDefault(); setActive(vis.length - 1); }
      else if (e.key === 'Enter') { e.preventDefault(); choose(vis[active]); }
      else if (e.key === 'Escape') { e.preventDefault(); e.stopPropagation(); closeSS(true); }
      else if (e.key === 'Tab') closeSS(false);
    });
    list.addEventListener('mousemove', e => {
      const li = e.target.closest('li'); if (!li) return;
      const vis = items.filter(x => !x.hidden); setActive(vis.indexOf(li), false);
    });
    list.addEventListener('click', e => choose(e.target.closest('li')));
    /* labels and programmatic focus on the hidden select land on the button */
    sel.addEventListener('focus', () => btn.focus());
    sel.addEventListener('change', label);
    if (sel.form) sel.form.addEventListener('reset', () => setTimeout(label, 0));
  }
  $$('select').forEach(makeSearchable);

  /* ---------- Testimonials: endless right-to-left flow ---------- */
  const mq = $('#testimonials');
  if (mq) {
    const track = $('.mq-track', mq), group = $('.mq-group', mq), tg = $('#mq-toggle');
    if (!calm && track && group) {
      const clone = group.cloneNode(true);       /* second copy makes the loop seamless */
      clone.setAttribute('aria-hidden', 'true');
      track.appendChild(clone);
      const speed = () => {                      /* constant pixel speed on every screen */
        const w = group.getBoundingClientRect().width;
        mq.style.setProperty('--mq-dur', Math.max(40, w / (innerWidth < 561 ? 38 : 46)).toFixed(1) + 's');
      };
      speed();
      addEventListener('resize', speed);
      if (document.fonts && document.fonts.ready) document.fonts.ready.then(speed);
      if (tg) tg.addEventListener('click', () => {
        const paused = mq.classList.toggle('paused');
        tg.setAttribute('aria-pressed', paused);
        $('span', tg).textContent = paused ? 'Play' : 'Pause';
        $('svg', tg).innerHTML = paused ? '<polygon points="7 4 19 12 7 20 7 4"/>' : '<line x1="9" y1="5" x2="9" y2="19"/><line x1="15" y1="5" x2="15" y2="19"/>';
      });
      let hold;                                  /* touching the row pauses it, it resumes shortly after */
      mq.addEventListener('touchstart', () => { clearTimeout(hold); mq.classList.add('touch-hold'); }, { passive: true });
      mq.addEventListener('touchend', () => { hold = setTimeout(() => mq.classList.remove('touch-hold'), 1500); }, { passive: true });
    }
  }

  /* ---------- Mobile app bar (bottom tabs) + More sheet ---------- */
  (function () {
    const file = document.body.dataset.page || '';
    const cur = { home: 'home', books: 'books', detail: 'books', papers: 'papers', cartPage: 'cart' }[file] || 'more';
    const S = p => '<svg viewBox="0 0 24 24" aria-hidden="true">' + p + '</svg>';
    const IC = {
      home: S('<path d="M3 11l9-8 9 8"/><path d="M5 9.5V20h5v-6h4v6h5V9.5"/>'),
      books: S('<path d="M4 4h6a2 2 0 0 1 2 2v14a2 2 0 0 0-2-2H4z"/><path d="M20 4h-6a2 2 0 0 0-2 2v14a2 2 0 0 1 2-2h6z"/>'),
      papers: S('<path d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8z"/><polyline points="14 3 14 8 19 8"/><line x1="9" y1="13" x2="15" y2="13"/><line x1="9" y1="17" x2="15" y2="17"/>'),
      cart: S('<circle cx="9" cy="20" r="1.5"/><circle cx="18" cy="20" r="1.5"/><path d="M2 3h3l2.5 12.5a1 1 0 0 0 1 .8h9.2a1 1 0 0 0 1-.8L21 7H6"/>'),
      more: S('<rect x="4" y="4" width="6.5" height="6.5" rx="1.6"/><rect x="13.5" y="4" width="6.5" height="6.5" rx="1.6"/><rect x="4" y="13.5" width="6.5" height="6.5" rx="1.6"/><rect x="13.5" y="13.5" width="6.5" height="6.5" rx="1.6"/>'),
      sets: S('<rect x="6" y="3" width="13" height="16" rx="2"/><path d="M4 7v12a2 2 0 0 0 2 2h10"/><line x1="10" y1="9" x2="15" y2="9"/><line x1="10" y1="13" x2="15" y2="13"/>'),
      gift: S('<rect x="3" y="8" width="18" height="4" rx="1"/><path d="M12 8v13"/><path d="M19 12v7a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2v-7"/><path d="M7.5 8a2.5 2.5 0 0 1 0-5C11 3 12 8 12 8s1-5 4.5-5a2.5 2.5 0 0 1 0 5"/>'),
      info: S('<circle cx="12" cy="12" r="9"/><line x1="12" y1="11" x2="12" y2="16"/><line x1="12" y1="7.5" x2="12" y2="7.6"/>'),
      mail: S('<rect x="3" y="5" width="18" height="14" rx="2"/><polyline points="3 7 12 13 21 7"/>')
    };
    const tab = (k, href, label) => {
      const on = cur === k;
      const inner = '<span class="bn-ic">' + IC[k] + (k === 'cart' ? '<span class="cart-count" data-n="0"></span>' : '') + '</span><span class="bn-t">' + label + '</span>';
      if (href) return '<a class="bn-i" href="' + href + '"' + (on ? ' aria-current="page"' : '') + '>' + inner + '</a>';
      return '<button class="bn-i' + (on ? ' on' : '') + '" type="button" id="bn-more" aria-haspopup="dialog" aria-expanded="false">' + inner + '</button>';
    };
    const bar = document.createElement('nav');
    bar.className = 'bn'; bar.setAttribute('aria-label', 'Quick navigation');
    bar.innerHTML = tab('home', WK.r.home, 'Home') + tab('books', WK.r.books, 'Books') + tab('papers', WK.r.papers, 'Papers') + tab('cart', WK.r.cart, 'Cart') + tab('more', '', 'More');
    document.body.appendChild(bar);

    /* the cart badge starts in sync with the header one; app.js keeps every .cart-count updated afterwards */
    const hc = $('.cart-btn .cart-count'), bc = $('.cart-count', bar);
    if (hc && bc) { bc.textContent = hc.textContent; bc.dataset.n = hc.dataset.n || 0; }

    const page = h => (cur === 'more' && file === h ? ' aria-current="page"' : '');
    const sheet = document.createElement('div');
    sheet.className = 'mo'; sheet.id = 'more-sheet';
    sheet.innerHTML =
      '<div class="mo-bg" data-close></div>' +
      '<div class="mo-panel" role="dialog" aria-modal="true" aria-label="More from WAKATA"><span class="mo-grab" aria-hidden="true"></span><h2 class="mo-title">More from WAKATA</h2>' +
      '<div class="mo-grid">' +
      '<a class="mo-t" href="' + WK.r.sets + '"' + page('sets') + '><i>' + IC.sets + '</i>Exam sets</a>' +
      '<a class="mo-t" href="' + WK.r.packages + '"' + page('packages') + '><i>' + IC.gift + '</i>Holiday packages</a>' +
      '<a class="mo-t" href="' + WK.r.about + '"' + page('about') + '><i>' + IC.info + '</i>About us</a>' +
      '<a class="mo-t" href="' + WK.r.contact + '"' + page('contact') + '><i>' + IC.mail + '</i>Contact</a>' +
      '</div>' +
      '<div class="mo-acts"><a class="btn mo-wa" href="' + wa.href + '" target="_blank" rel="noopener">WhatsApp</a><a class="btn btn-hl" href="tel:' + WK.contact.tel + '">Call us</a></div>' +
      '<button class="mo-close" type="button" data-close>Close</button></div>';
    document.body.appendChild(sheet);

    const more = $('#bn-more');
    function openSheet() {
      sheet.classList.add('open'); more.setAttribute('aria-expanded', 'true');
      document.documentElement.style.overflow = 'hidden';
      setTimeout(() => { const f = $('.mo-t', sheet); if (f) f.focus({ preventScroll: true }); }, 60);
    }
    function closeSheet(back) {
      if (!sheet.classList.contains('open')) return;
      sheet.classList.remove('open'); more.setAttribute('aria-expanded', 'false');
      document.documentElement.style.overflow = '';
      if (back) more.focus({ preventScroll: true });
    }
    more.addEventListener('click', () => sheet.classList.contains('open') ? closeSheet(true) : openSheet());
    sheet.addEventListener('click', e => { if (e.target.closest('[data-close]')) closeSheet(true); });
    document.addEventListener('keydown', e => { if (e.key === 'Escape') closeSheet(true); });
    /* leaving the phone layout (rotate / resize) closes the sheet */
    matchMedia('(min-width:761px)').addEventListener('change', e => { if (e.matches) closeSheet(false); });
  })();

  /* ---------- Fly-to-cart ---------- */
  document.addEventListener('click', e => {
    const btn = e.target.closest('[data-add]');
    if (!btn) return;
    const cartBtn = $('.cart-btn');
    if (!cartBtn) return;
    cartBtn.classList.remove('pop'); void cartBtn.offsetWidth; cartBtn.classList.add('pop');
    if (calm || !cartBtn.animate) return;
    const card = btn.closest('.bk,.pp,.set');
    const img = card && $('img:not([src*="logo"])', card);
    const from = (img || btn).getBoundingClientRect();
    const to = cartBtn.getBoundingClientRect();
    if (!to.width) return;
    const ghost = document.createElement(img ? 'img' : 'span');
    ghost.className = 'fly';
    if (img) ghost.src = img.currentSrc || img.src;
    const w = img ? Math.min(from.width, 120) : 28, h = img ? Math.min(from.height, 150) : 28;
    Object.assign(ghost.style, { left: from.left + from.width / 2 - w / 2 + 'px', top: from.top + from.height / 2 - h / 2 + 'px', width: w + 'px', height: h + 'px' });
    document.body.appendChild(ghost);
    const dx = to.left + to.width / 2 - (from.left + from.width / 2), dy = to.top + to.height / 2 - (from.top + from.height / 2);
    const a = ghost.animate([
      { transform: 'translate(0,0) scale(1) rotate(0)', opacity: 1 },
      { transform: `translate(${dx * 0.6}px,${dy * 0.6 - 60}px) scale(.55) rotate(-8deg)`, opacity: .95, offset: .55 },
      { transform: `translate(${dx}px,${dy}px) scale(.12) rotate(10deg)`, opacity: .2 }
    ], { duration: 750, easing: 'cubic-bezier(.5,0,.3,1)' });
    a.onfinish = () => ghost.remove();
  });
})();
