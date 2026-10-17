/* ==========================================================
   WAKATA front end — vanilla JS (no build step, no dependencies)
   Catalogue data comes from the database (WK.catalogue, see data.js).
   The cart lives on the server (session). Prices and totals are always worked out by Laravel; this file only
   shows them and sends the shopper's choices (see App\Support\Cart and CheckoutController).
   ========================================================== */
(function () {
  'use strict';
  const D = window.WK;
  const $ = (s, r = document) => r.querySelector(s);
  const $$ = (s, r = document) => [...r.querySelectorAll(s)];
  const esc = s => String(s).replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
  const page = document.body.dataset.page;
  const params = new URLSearchParams(location.search);

  const ICON = {
    check: '<polyline points="20 6 9 17 4 12"/>',
    cart: '<circle cx="9" cy="20" r="1.5"/><circle cx="18" cy="20" r="1.5"/><path d="M2 3h3l2.5 12.5a1 1 0 0 0 1 .8h9.2a1 1 0 0 0 1-.8L21 7H6"/>',
    truck: '<rect x="1" y="6" width="13" height="10" rx="1"/><path d="M14 9h4l3 3v4h-7"/><circle cx="6" cy="18" r="2"/><circle cx="17" cy="18" r="2"/>',
    shield: '<path d="M12 3l8 3v6c0 5-3.5 8-8 9-4.5-1-8-4-8-9V6z"/><polyline points="9 12 11 14 15 10"/>',
    phone: '<rect x="7" y="2" width="10" height="20" rx="2"/><line x1="11" y1="18" x2="13" y2="18"/>',
    download: '<path d="M12 3v12"/><polyline points="7 11 12 16 17 11"/><path d="M4 20h16"/>',
    arrow: '<line x1="5" y1="12" x2="19" y2="12"/><polyline points="13 6 19 12 13 18"/>'
  };
  ICON.heart = '<path d="M12 21s-7-4.6-9.2-9A5.2 5.2 0 0 1 12 6.5 5.2 5.2 0 0 1 21.2 12C19 16.4 12 21 12 21z"/>';
  const sv = (t, id) => `<button class="sv" type="button" data-save data-type="${t}" data-id="${id}" aria-label="Save for later" title="Save for later">${ic('heart')}</button>`;
  const ic = n => `<svg class="ico" viewBox="0 0 24 24" aria-hidden="true">${ICON[n]}</svg>`;

  /* ---------------- Cart (server session) ---------------- */
  const cartState = { count: (D.cart && D.cart.count) || 0 };
  function badge() { const n = cartState.count; $$('.cart-count').forEach(b => { b.textContent = n || ''; b.dataset.n = n; }); }

  /* One place that talks to Laravel. Always resolves: { ok, status, data } (data.message is safe to show). */
  async function api(method, url, body) {
    try {
      const res = await fetch(url, {
        method, credentials: 'same-origin',
        headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': D.csrf },
        body: body ? JSON.stringify(body) : undefined
      });
      let data = {}; try { data = await res.json(); } catch (e) { }
      if (res.status === 419) data.message = 'Your session expired. Please refresh the page and try again.';
      else if (res.status === 429) data.message = 'Too many requests. Please wait a moment and try again.';
      else if (!res.ok && !data.message) data.message = 'Something went wrong. Please try again.';
      return { ok: res.ok, status: res.status, data };
    } catch (e) {
      return { ok: false, status: 0, data: { message: 'No connection. Check your internet and try again.' } };
    }
  }
  const cart = {
    async add(items) {
      const r = await api('POST', D.r.cartAdd, { items });
      if (typeof r.data.count === 'number') { cartState.count = r.data.count; badge(); }
      return r;
    }
  };
  const cartLink = 'Added to your cart. <a href="' + WK.r.cart + '">View cart</a>';

  /* Carts saved in this browser before the cart moved to the server are moved over once. */
  (function importOldCart() {
    const KEY = 'wakata_cart_v1'; let old;
    try { old = JSON.parse(localStorage.getItem(KEY)); localStorage.removeItem(KEY); } catch (e) { return; }
    if (!Array.isArray(old) || !old.length) return;
    const items = old.slice(0, 40).map(i => ({ type: i.type, id: i.id, fmt: i.fmt, qty: i.qty }));
    cart.add(items).then(r => { if (!r.ok && r.status === 0) { try { localStorage.setItem(KEY, JSON.stringify(old)); } catch (e) { } } });
  })();

  /* Thumbnail and link for a cart line (the server supplies title, description and price). */
  function lineThumb(l) {
    if (l.type === 'book') { const b = D.byId(D.books, l.id); return b ? cover(b) : genCv('Mathematics'); }
    if (l.type === 'paper') { const p = D.byId(D.papers, l.id); return p && p.img ? `<img src="${p.img}" alt="">` : genCv(p ? p.subject : 'Mathematics'); }
    if (l.type === 'set') { const s = D.byId(D.sets, l.id); return s && s.preview && s.preview[0] ? `<img src="${D.PAPER_IMG}${s.preview[0]}" alt="">` : genCv('Mathematics'); }
    return genCv('Holiday');
  }
  const lineHref = l => l.type === 'book' ? WK.r.book + '?id=' + encodeURIComponent(l.id) : l.type === 'paper' ? WK.r.papers : l.type === 'set' ? WK.r.sets : WK.r.packages;
  const genCv = s => { const c = D.SUBJECTS[s] || D.SUBJECTS.Mathematics; return `<div class="cv" style="--c1:${c[0]};--c2:${c[1]};--c3:${c[2]}"><span class="sj">${esc(s)}</span></div>`; };

  /* ---------------- Toast ---------------- */
  let toastEl, toastT;
  function toast(html) {
    if (!toastEl) { toastEl = document.createElement('div'); toastEl.className = 'toast'; toastEl.setAttribute('role', 'status'); document.body.appendChild(toastEl); }
    toastEl.innerHTML = html; toastEl.classList.add('show');
    clearTimeout(toastT); toastT = setTimeout(() => toastEl.classList.remove('show'), 3200);
  }

  /* ---------------- Component renderers ---------------- */
  function cover(b) {
    if (b.img) return `<img src="${b.img}" alt="Cover of ${esc(b.title)}" loading="lazy" width="300" height="400">`;
    const c = D.SUBJECTS[b.subject] || D.SUBJECTS.Mathematics;
    return `<div class="cv" style="--c1:${c[0]};--c2:${c[1]};--c3:${c[2]}" role="img" aria-label="Cover of ${esc(b.title)}"><span class="lv">${D.BOOK_LEVELS[b.level]}</span><span><span class="sj${b.subject.length > 11 ? ' long' : ''}">${esc(b.subject)}</span><div class="ln"></div></span><span class="sb">${esc(b.type)}</span><img src="${WK.u('assets/img/logo-white.png')}" alt=""></div>`;
  }
  function bookCard(b) {
    return `<article class="bk">${b.badge ? `<span class="bk-badge">${esc(b.badge)}</span>` : ''}
      <a class="bk-media" href="${WK.r.book}?id=${b.id}" aria-label="View ${esc(b.title)}"><div class="book">${cover(b)}</div></a>
      <div class="bk-body"><span class="bk-meta">${D.BOOK_LEVELS[b.level]} ${esc(b.subject)}</span>
      <h3 class="bk-title"><a href="${WK.r.book}?id=${b.id}">${esc(b.title)}</a></h3>
      <div class="bk-foot"><span class="price">${b.old ? `<s>${D.money(b.old)}</s>` : ''}${D.money(b.price)}</span>
      <span class="acts">${sv('book', b.id)}<button class="add" data-add data-type="book" data-id="${b.id}" aria-label="Add ${esc(b.title)} to cart">${ic('cart')}Add</button></span></div></div></article>`;
  }
  const lvFull = k => (D.LEVELS[k] && D.LEVELS[k].full) || '';
  function sheetHTML(p) {
    if (p.img) return `<div class="sheet"><img src="${p.img}" alt="First page of ${esc(p.title)} ${p.year}" loading="lazy"></div>`;
    return `<div class="sheet gen" aria-hidden="true"><img src="${WK.u('assets/img/logo.png')}" alt=""><b>WAKATA MOCK EXAMINATIONS ${p.year}</b><span>${esc(lvFull(p.level))}</span><b class="big">${esc(p.subject)}</b><b>${p.level === 'ple' ? 'Primary Seven' : esc(p.paper)}</b><div class="rule"></div><i></i><i style="width:88%"></i><i style="width:94%"></i><i style="width:70%"></i><i></i><i style="width:82%"></i></div>`;
  }
  function paperCard(p) {
    const n = 'fmt-' + p.id, first = p.noPrint ? 'pdf' : 'print';   /* a format the admin switched off is not offered */
    return `<article class="pp"><a class="pp-thumb" href="#" tabindex="-1" aria-hidden="true" data-noop><span class="pp-year">${p.year}</span>${sheetHTML(p)}</a>
      <div class="pp-body"><h3 class="pp-title">${esc(p.title)}</h3>
      <span class="pp-meta">${D.LEVELS[p.level].label}, ${esc(p.time)}</span>
      <div class="fmt" role="radiogroup" aria-label="Format for ${esc(p.title)} ${p.year}">
        ${p.noPrint ? '' : `<label><input type="radio" name="${n}" value="print" ${first === 'print' ? 'checked' : ''} data-fmt><span>Printed</span></label>`}
        ${p.noPdf ? '' : `<label><input type="radio" name="${n}" value="pdf" ${first === 'pdf' ? 'checked' : ''} data-fmt><span>PDF</span></label>`}</div>
      <div class="bk-foot"><span class="price" data-price data-print="${p.print}" data-pdf="${p.pdf}">${D.money(p[first])}</span>
      <span class="acts">${sv('paper', p.id)}<button class="add" data-add data-type="paper" data-id="${p.id}" aria-label="Add ${esc(p.title)} ${p.year} to cart">${ic('cart')}Add</button></span></div></div></article>`;
  }
  function setCard(s) {
    const save = Math.round((1 - s.price / s.worth) * 100);
    return `<article class="set"><div class="stack" aria-hidden="true">${s.preview.map(f => `<div class="sheet"><img src="${D.PAPER_IMG}${f}" alt="" loading="lazy"></div>`).join('')}</div>
      <div><span class="bk-meta">${D.LEVELS[s.level].label}, ${s.year}</span><h3>${esc(s.title)}</h3>
      <p class="inc">${esc(s.blurb)}</p>
      <details><summary>${s.papers} papers included</summary><ul>${s.subjects.map(x => `<li>${esc(x)}</li>`).join('')}</ul></details>
      <div class="bk-foot"><span class="price"><s>${D.money(s.worth)}</s>${D.money(s.price)}<span class="save">Save ${save}%</span></span>
      <span class="acts">${sv('set', s.id)}<button class="add" data-add data-type="set" data-id="${s.id}">${ic('cart')}Add set</button></span></div></div></article>`;
  }
  function packCard(k) {
    return `<article class="pk${k.best ? ' best' : ''}">${k.best ? '<span class="ribbon">Most chosen</span>' : ''}
      <span class="for">${esc(k.for)}</span><h3>${esc(k.name)}</h3>
      <div class="amt">${D.money(k.price)} <small>per holiday</small></div>
      <ul>${k.includes.map(x => `<li>${ic('check')}<span>${esc(x)}</span></li>`).join('')}</ul>
      <div class="acts wide">${sv('pack', k.id)}<button class="btn ${k.best ? 'btn-primary' : 'btn-line'}" data-add data-type="pack" data-id="${k.id}">Add package to cart</button></div></article>`;
  }
  const emptyMsg = (t, s) => `<div class="empty"><h3>${t}</h3><p class="muted">${s}</p><a class="btn btn-line btn-sm" href="?">Clear all filters</a></div>`;

  /* ---------------- Faceted filter helpers ---------------- */
  function facet(el, title, name, opts, cur) {
    el.insertAdjacentHTML('beforeend', `<fieldset><legend><h3>${title}</h3></legend>${opts.map(o => `<label class="opt"><input type="radio" name="${name}" value="${esc(o.v)}"${String(cur) === String(o.v) ? ' checked' : ''}>${esc(o.l)}<span class="count">${o.c}</span></label>`).join('')}</fieldset>`);
  }
  function syncURL(obj) { const u = new URLSearchParams(); Object.keys(obj).forEach(k => obj[k] && u.set(k, obj[k])); history.replaceState(null, '', u.toString() ? '?' + u : location.pathname); }
  const countBy = (arr, f) => arr.reduce((m, x) => (m[f(x)] = (m[f(x)] || 0) + 1, m), {});

  /* ---------------- Page: Home ---------------- */
  function home() {
    const F = D.featured || {}, pick = (list, ids) => (ids || []).map(id => D.byId(list, id)).filter(Boolean);
    $('#home-books').innerHTML = pick(D.books, F.books).map(bookCard).join('');
    $('#home-papers').innerHTML = pick(D.papers, F.papers).map(paperCard).join('');
    $('#home-packs').innerHTML = pick(D.packages, F.packages).map(packCard).join('');
    const bc = countBy(D.books, b => b.level), pc = countBy(D.papers, p => p.level);
    $$('[data-lvl-count]').forEach(el => { const [lv, bl] = el.dataset.lvlCount.split(':'); el.textContent = `${bc[bl] || 0} books and ${pc[lv] || 0} past papers`; });
    const subs = [...new Set(D.papers.map(p => p.subject))].sort();
    $('#f-subject').insertAdjacentHTML('beforeend', subs.map(s => `<option>${esc(s)}</option>`).join(''));
    const yrs = [...new Set(D.papers.map(p => p.year))].sort((a, b) => b - a);
    $('#f-year').insertAdjacentHTML('beforeend', yrs.map(y => `<option>${y}</option>`).join(''));
    const subjCounts = countBy(D.books, b => b.subject);
    const chipBox = $('#home-chips');
    chipBox.innerHTML = Object.keys(subjCounts).sort((x, y) => subjCounts[y] - subjCounts[x] || x.localeCompare(y)).map(s => `<a class="chip" href="${WK.r.books}?subject=${encodeURIComponent(s)}">${esc(s)}<i>${subjCounts[s]}</i></a>`).join('');
    /* Keep the subject tabs on a single line. Whatever does not fit is hidden;
       "See all books" shows everything. On phones the row scrolls sideways instead. */
    const fitChips = () => {
      const chips = $$('.chip', chipBox);
      chips.forEach(c => { c.hidden = false; });
      if (!chips.length || matchMedia('(max-width:760px)').matches) return;
      const row = chips[0].offsetTop;
      chips.forEach(c => { if (c.offsetTop > row + 4) c.hidden = true; });
    };
    fitChips();
    let chipT; addEventListener('resize', () => { clearTimeout(chipT); chipT = setTimeout(fitChips, 80); });
    if (document.fonts && document.fonts.ready) document.fonts.ready.then(fitChips);
  }

  /* ---------------- Page: Books ---------------- */
  function books() {
    const st = { q: params.get('q') || '', subject: params.get('subject') || '', level: params.get('level') || '', sort: params.get('sort') || 'featured' };
    const side = $('#facets'), grid = $('#grid'), found = $('#found');
    const sc = countBy(D.books, b => b.subject), lc = countBy(D.books, b => b.level);
    facet(side, 'Subject', 'subject', [{ v: '', l: 'All subjects', c: D.books.length }].concat(Object.keys(sc).sort().map(s => ({ v: s, l: s, c: sc[s] }))), st.subject);
    facet(side, 'Level', 'level', [{ v: '', l: 'All levels', c: D.books.length }].concat(Object.keys(D.BOOK_LEVELS).map(k => ({ v: k, l: D.BOOK_LEVELS[k], c: lc[k] || 0 }))), st.level);
    $('#q').value = st.q; $('#sort').value = st.sort;
    function draw() {
      let r = D.books.filter(b => (!st.subject || b.subject === st.subject) && (!st.level || b.level === st.level) && (!st.q || (b.title + ' ' + b.subject + ' ' + b.type).toLowerCase().includes(st.q.toLowerCase())));
      if (st.sort === 'low') r.sort((a, b) => a.price - b.price); else if (st.sort === 'high') r.sort((a, b) => b.price - a.price); else if (st.sort === 'az') r.sort((a, b) => a.title.localeCompare(b.title));
      found.textContent = r.length + (r.length === 1 ? ' book found' : ' books found');
      grid.innerHTML = r.length ? r.map(bookCard).join('') : emptyMsg('No books match those filters', 'Try another subject or level, or search with fewer words.');
      syncURL(st);
    }
    side.addEventListener('change', e => { st[e.target.name] = e.target.value; draw(); });
    $('#q').addEventListener('input', e => { st.q = e.target.value; draw(); });
    $('#sort').addEventListener('change', e => { st.sort = e.target.value; draw(); });
    draw();
  }

  /* ---------------- Page: Book detail ---------------- */
  function detail() {
    const b = D.byId(D.books, WK.bookId || params.get('id'));
    if (!b) { location.replace(WK.r.books); return; }
    document.title = b.title + ' | WAKATA Stationers';
    $('#d-crumb').textContent = b.title;
    $('#d-stage').innerHTML = `<div class="book">${cover(b)}</div>`;
    if (b.full) {
      $('#d-thumbs').innerHTML = `<button type="button" aria-pressed="true" aria-label="Front cover" data-v="front"><img src="${b.img}" alt=""></button><button type="button" aria-pressed="false" aria-label="Front and back cover" data-v="full"><img src="${b.full}" alt=""></button>`;
      $('#d-thumbs').addEventListener('click', e => {
        const t = e.target.closest('button'); if (!t) return;
        $$('#d-thumbs button').forEach(x => x.setAttribute('aria-pressed', x === t));
        $('#d-stage').innerHTML = t.dataset.v === 'full' ? `<img src="${b.full}" alt="Front and back cover of ${esc(b.title)}" style="border-radius:4px;box-shadow:0 18px 30px -14px rgba(6,27,77,.6)">` : `<div class="book">${cover(b)}</div>`;
      });
    }
    $('#d-meta').textContent = D.BOOK_LEVELS[b.level] + ' ' + b.subject;
    $('#d-title').textContent = b.title;
    $('#d-price').innerHTML = (b.old ? `<s>${D.money(b.old)}</s>` : '') + D.money(b.price);
    $('#d-desc').textContent = b.desc;
    $('#d-specs').innerHTML = [['Subject', b.subject], ['Level', D.BOOK_LEVELS[b.level]], ['Format', b.type], ['Pages', b.pages], ['Edition', b.edition], ['Publisher', "Wakiso Kampala Teachers' Association (WAKATA)"]].map(r => `<tr><th>${r[0]}</th><td>${esc(r[1])}</td></tr>`).join('');
    const q = $('#d-qty');
    $('#q-minus').onclick = () => q.value = Math.max(1, +q.value - 1);
    $('#q-plus').onclick = () => q.value = Math.min(99, +q.value + 1);
    const qty = () => Math.max(1, Math.min(99, +q.value || 1));
    $('#d-add').onclick = async () => { const r = await cart.add([{ type: 'book', id: b.id, qty: qty() }]); toast(r.ok ? cartLink : esc(r.data.message)); };
    $('#d-buy').onclick = async () => { const r = await cart.add([{ type: 'book', id: b.id, qty: qty() }]); if (r.ok) location.href = WK.r.cart; else toast(esc(r.data.message)); };
    const rel = D.books.filter(x => x.id !== b.id && (x.subject === b.subject || x.level === b.level)).sort((x, y) => (y.subject === b.subject) - (x.subject === b.subject)).slice(0, 4);
    $('#d-related').innerHTML = rel.map(bookCard).join('');
  }

  /* ---------------- Page: Past papers ---------------- */
  function papers() {
    const st = { q: params.get('q') || '', level: params.get('level') || '', subject: params.get('subject') || '', year: params.get('year') || '', sort: 'new' };
    let shown = 12;
    const side = $('#facets'), grid = $('#grid'), found = $('#found'), more = $('#more');
    const lc = countBy(D.papers, p => p.level), sc = countBy(D.papers, p => p.subject), yc = countBy(D.papers, p => p.year);
    facet(side, 'Level', 'level', [{ v: '', l: 'All levels', c: D.papers.length }].concat(Object.keys(D.LEVELS).map(k => ({ v: k, l: D.LEVELS[k].label, c: lc[k] }))), st.level);
    facet(side, 'Year', 'year', [{ v: '', l: 'Any year', c: D.papers.length }].concat(Object.keys(yc).sort((a, b) => b - a).map(y => ({ v: y, l: y, c: yc[y] }))), st.year);
    facet(side, 'Subject', 'subject', [{ v: '', l: 'All subjects', c: D.papers.length }].concat(Object.keys(sc).sort().map(s => ({ v: s, l: s, c: sc[s] }))), st.subject);
    $('#q').value = st.q;
    function draw() {
      const r = D.papers.filter(p => (!st.level || p.level === st.level) && (!st.subject || p.subject === st.subject) && (!st.year || String(p.year) === st.year) && (!st.q || (p.title + ' ' + p.subject).toLowerCase().includes(st.q.toLowerCase())));
      found.textContent = r.length + (r.length === 1 ? ' past paper found' : ' past papers found');
      grid.innerHTML = r.length ? r.slice(0, shown).map(paperCard).join('') : emptyMsg('No papers match those filters', 'Try a different year or subject. New papers are added after every mock series.');
      more.hidden = r.length <= shown;
      more.querySelector('span').textContent = `Showing ${Math.min(shown, r.length)} of ${r.length}`;
      syncURL({ q: st.q, level: st.level, subject: st.subject, year: st.year });
    }
    side.addEventListener('change', e => { st[e.target.name] = e.target.value; shown = 12; draw(); });
    $('#q').addEventListener('input', e => { st.q = e.target.value; shown = 12; draw(); });
    $('#more-btn').addEventListener('click', () => { shown += 12; draw(); });
    draw();
  }

  /* ---------------- Pages: Sets and Packages (tabbed by level) ---------------- */
  function tabbed(list, render, key) {
    const wrap = $('#tabs'), grid = $('#grid');
    let cur = params.get('level') || '';
    const opts = [['', 'All levels']].concat(Object.keys(D.LEVELS).map(k => [k, D.LEVELS[k].label]));
    wrap.innerHTML = opts.map(o => `<button type="button" class="chip" data-l="${o[0]}" aria-pressed="${cur === o[0]}">${o[1]}</button>`).join('');
    function draw() { $$('.chip', wrap).forEach(c => c.setAttribute('aria-pressed', c.dataset.l === cur)); grid.innerHTML = list.filter(x => !cur || x.level === cur).map(render).join(''); }
    wrap.addEventListener('click', e => { const c = e.target.closest('.chip'); if (c) { cur = c.dataset.l; draw(); } });
    draw();
  }

  /* ---------------- Page: Cart + checkout ---------------- */
  function cartPage() {
    const list = $('#lines'), sumEl = $('#summary'), form = $('#checkout'), submit = $('button[type=submit]', form);
    let state = D.cart && D.cart.lines ? D.cart : { lines: [], count: 0, subtotal: 0, physical: false };
    const deliveryOf = () => { const r = $('input[name=delivery]:checked'); return r ? r.value : 'pickup'; };

    function draw() {
      const lines = state.lines, phys = !!state.physical;
      $('#cart-wrap').hidden = !lines.length; $('#cart-empty').hidden = !!lines.length;
      list.innerHTML = lines.map(l => `<div class="line"><div class="mini">${lineThumb(l)}</div>
        <div><h3><a href="${lineHref(l)}">${esc(l.title)}</a></h3><div class="sub">${esc(l.sub)}</div><button class="rm" data-rm="${esc(l.k)}" type="button">Remove</button></div>
        <div class="right"><span class="price">${D.money(l.line)}</span><div class="qty" role="group" aria-label="Quantity">
        <button type="button" data-q="-1" data-k="${esc(l.k)}" aria-label="Decrease quantity">&minus;</button><input value="${l.qty}" readonly aria-label="Quantity"><button type="button" data-q="1" data-k="${esc(l.k)}" aria-label="Increase quantity">+</button></div></div></div>`).join('');
      $('#delivery-box').hidden = !phys;
      const dv = phys ? ((D.shop.delivery[deliveryOf()] || {}).fee || 0) : 0, pickup = deliveryOf() === 'pickup';
      $('#addr-field').hidden = !phys || pickup;
      /* Cash is only possible when the customer collects from the shop. */
      const cash = $('input[name=pay][value=cash]'), cashOk = phys && pickup;
      if (cash) { cash.closest('.radio').style.display = cashOk ? '' : 'none'; if (!cashOk && cash.checked) cash.checked = false; }
      sumEl.innerHTML = `<div class="sum"><span>Subtotal</span><span>${D.money(state.subtotal)}</span></div><div class="sum"><span>Delivery</span><span>${phys ? (dv ? D.money(dv) : 'Free pickup') : 'Not needed (digital)'}</span></div><div class="sum total"><span>Total</span><span>${D.money(state.subtotal + dv)}</span></div>`;
      cartState.count = state.count; badge();
    }
    function apply(r, quiet) {
      if (r.data && r.data.cart) state = r.data.cart;
      if (r.data && r.data.cart && r.data.cart.removed > 0 && !quiet) toast('Some items are no longer available and were removed from your cart.');
      draw();
    }
    async function change(method, body, url) {
      const r = await api(method, url, body);
      if (r.ok) apply(r); else { toast(esc(r.data.message)); }
    }

    list.addEventListener('click', e => {
      const rm = e.target.closest('[data-rm]'), q = e.target.closest('[data-q]');
      if (rm) change('DELETE', { k: rm.dataset.rm }, D.r.cartRemove);
      if (q) { const l = state.lines.find(x => x.k === q.dataset.k); if (l) change('PATCH', { k: l.k, qty: Math.max(1, Math.min(99, l.qty + +q.dataset.q)) }, D.r.cartUpdate); }
    });
    form.addEventListener('change', draw);
    $('#clear').addEventListener('click', () => change('DELETE', null, D.r.cartClear));

    /* Server-side problems go back onto the matching field, in the same style as the browser checks. */
    function showErrors(errors) {
      const map = { name: 'o-name', phone: 'o-phone', email: 'o-email', address: 'o-addr' }; let first = null, other = null;
      Object.keys(errors).forEach(k => {
        const el = map[k] && document.getElementById(map[k]), f = el && el.closest('.field');
        if (f && !f.hidden) { f.classList.add('bad'); const er = $('.err', f); if (er) { if (!er.dataset.def) er.dataset.def = er.textContent; er.textContent = errors[k][0]; } first = first || f; }
        else other = other || errors[k][0];
      });
      if (first) first.scrollIntoView({ block: 'center', behavior: 'smooth' });
      if (other) toast(esc(other));
    }

    form.addEventListener('submit', async e => {
      e.preventDefault();
      if (submit.disabled) return;
      if (!validate(form)) { const b = $('.field.bad', form); if (b) b.scrollIntoView({ block: 'center', behavior: 'smooth' }); return; }
      const body = { name: $('#o-name').value, phone: $('#o-phone').value, email: $('#o-email').value, pay: $('input[name=pay]:checked').value };
      if (state.physical) { body.delivery = deliveryOf(); body.address = $('#o-addr').value; }
      const label = submit.textContent; submit.disabled = true; submit.textContent = 'Placing your order...';
      const r = await api('POST', D.r.checkout, body);
      submit.disabled = false; submit.textContent = label;
      if (r.ok) {
        $('#ref').textContent = r.data.ref; $('#pay-name').textContent = r.data.payName; $('#ref-email').textContent = body.email;
        state = r.data.cart; draw();
        $('#modal').classList.add('open'); $('#modal .btn').focus();
        return;
      }
      if (r.data.cart) apply(r, true);
      if (r.data.errors) showErrors(r.data.errors); else toast(esc(r.data.message));
    });
    $('#modal .btn').addEventListener('click', () => { location.href = WK.r.home; });
    draw();
  }

  /* ---------------- Generic form validation (demo forms) ---------------- */
  function validate(form) {
    let ok = true;
    $$('.field', form).forEach(f => {
      const i = $('input,select,textarea', f); if (!i || f.hidden || f.closest('[hidden]')) return;
      let bad = i.required && !i.value.trim();
      if (!bad && i.type === 'email' && i.value && !/^\S+@\S+\.\S+$/.test(i.value)) bad = true;
      if (!bad && i.type === 'tel' && i.value && i.value.replace(/\D/g, '').length < 9) bad = true;
      const er = $('.err', f); if (er && er.dataset.def) er.textContent = er.dataset.def;
      f.classList.toggle('bad', bad); if (bad) ok = false;
    });
    const pay = $$('input[name=pay]', form); if (pay.length && !pay.some(r => r.checked)) { ok = false; toast('Choose a payment method to continue.'); }
    return ok;
  }
  function demoForms() {
    $$('form[data-demo]').forEach(f => f.addEventListener('submit', e => {
      e.preventDefault();
      if (!validate(f)) return;
      f.reset(); toast(esc(f.dataset.demo));
      const r = $('[data-result]', f.parentElement); if (r) { r.hidden = false; r.textContent = f.dataset.demo; }
    }));
  }

  /* ---------------- Global events ---------------- */
  document.addEventListener('click', e => {
    const a = e.target.closest('[data-add]');
    if (a && !a.dataset.busy) {
      const type = a.dataset.type, id = a.dataset.id; let fmt = null;
      if (type === 'paper') { const r = a.closest('.pp').querySelector('input[data-fmt]:checked'); fmt = r ? r.value : 'print'; }
      a.dataset.busy = '1';
      cart.add([{ type, id, fmt, qty: 1 }]).then(r => {
        delete a.dataset.busy;
        if (!r.ok) { toast(esc(r.data.message)); return; }
        toast(cartLink);
        if (a.classList.contains('add')) { const o = a.innerHTML; a.classList.add('done'); a.innerHTML = ic('check') + 'Added'; setTimeout(() => { a.classList.remove('done'); a.innerHTML = o; }, 1400); }
      });
    }
    const sb = e.target.closest('[data-save]');
    if (sb && !sb.dataset.busy) {
      if (!WK.user) { toast('Sign in to save items for later. <a href="' + WK.r.accountLogin + '">Sign in</a>'); return; }
      const type = sb.dataset.type; let fmt = null;
      if (type === 'paper') { const r = sb.closest('.pp').querySelector('input[data-fmt]:checked'); fmt = r ? r.value : 'print'; }
      sb.dataset.busy = '1';
      fetch(WK.r.saveItem, { method: 'POST', credentials: 'same-origin', headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': WK.csrf }, body: JSON.stringify({ items: [{ type, id: sb.dataset.id, fmt }] }) })
        .then(r => r.json().catch(() => ({})).then(d => ({ ok: r.ok, d })))
        .then(r => { delete sb.dataset.busy; if (r.ok) { sb.classList.add('on'); toast('Saved for later. <a href="' + WK.r.accountSaved + '">View saved items</a>'); } else toast(esc((r.d && r.d.message) || 'Could not save this item.')); })
        .catch(() => { delete sb.dataset.busy; toast('No connection. Check your internet and try again.'); });
    }
    if (e.target.closest('[data-noop]')) e.preventDefault();
    const bg = e.target.closest('#burger'); if (bg) { const n = $('#nav'); const o = n.classList.toggle('open'); bg.setAttribute('aria-expanded', o); }
    if (e.target.closest('#filter-toggle')) { const s = $('.side'); const o = s.classList.toggle('open'); e.target.closest('#filter-toggle').setAttribute('aria-expanded', o); }
  });
  document.addEventListener('change', e => {
    if (e.target.matches('input[data-fmt]')) { const p = e.target.closest('.pp').querySelector('[data-price]'); p.textContent = D.money(p.dataset[e.target.value]); }
  });
  const nl = $('#newsletter'); if (nl) nl.addEventListener('submit', e => { e.preventDefault(); if (nl.email.value) { nl.reset(); toast('Thank you. You are on the list for new papers and offers.'); } });

  badge(); demoForms();
  ({ home, books, detail, papers, cartPage, sets: () => tabbed(D.sets, setCard), packages: () => tabbed(D.packages, packCard) }[page] || function () { })();
})();
