/* ==========================================================
   WAKATA front end — vanilla JS (no build step, no dependencies)
   Cart is stored in localStorage until the Laravel backend exists.
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
  const ic = n => `<svg class="ico" viewBox="0 0 24 24" aria-hidden="true">${ICON[n]}</svg>`;

  /* ---------------- Cart ---------------- */
  const KEY = 'wakata_cart_v1';
  const cart = {
    get() { try { return JSON.parse(localStorage.getItem(KEY)) || []; } catch (e) { return []; } },
    set(v) { try { localStorage.setItem(KEY, JSON.stringify(v)); } catch (e) { } badge(); },
    add(type, id, fmt, qty) {
      const c = this.get(); const k = [type, id, fmt || ''].join(':');
      const f = c.find(i => i.k === k);
      if (f) f.qty += qty; else c.push({ k, type, id, fmt: fmt || null, qty });
      this.set(c);
    },
    count() { return this.get().reduce((n, i) => n + i.qty, 0); }
  };
  function badge() { const n = cart.count(); $$('.cart-count').forEach(b => { b.textContent = n || ''; b.dataset.n = n; }); }

  function info(i) {
    if (i.type === 'book') { const b = D.byId(D.books, i.id); if (!b) return null; return { t: b.title, sub: D.BOOK_LEVELS[b.level] + ' ' + b.subject, price: b.price, thumb: cover(b), phys: true, href: WK.r.book + '?id=' + b.id }; }
    if (i.type === 'paper') { const p = D.byId(D.papers, i.id); if (!p) return null; return { t: p.title + ' ' + p.year, sub: D.LEVELS[p.level].short + ' past paper, ' + (i.fmt === 'pdf' ? 'PDF download' : 'printed copy'), price: i.fmt === 'pdf' ? p.pdf : p.print, thumb: p.img ? `<img src="${p.img}" alt="">` : genCv(p.subject), phys: i.fmt !== 'pdf', href: WK.r.papers }; }
    if (i.type === 'set') { const s = D.byId(D.sets, i.id); if (!s) return null; return { t: s.title, sub: s.papers + ' papers, printed set', price: s.price, thumb: `<img src="${D.PAPER_IMG}${s.preview[0]}" alt="">`, phys: true, href: WK.r.sets }; }
    if (i.type === 'pack') { const k = D.byId(D.packages, i.id); if (!k) return null; return { t: k.name, sub: 'Holiday package for ' + k.for, price: k.price, thumb: genCv('Holiday'), phys: true, href: WK.r.packages }; }
    return null;
  }
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
      <button class="add" data-add data-type="book" data-id="${b.id}" aria-label="Add ${esc(b.title)} to cart">${ic('cart')}Add</button></div></div></article>`;
  }
  const LV_FULL = { ple: 'Primary Leaving Examination', uce: 'Uganda Certificate of Education', uace: 'Uganda Advanced Certificate of Education' };
  function sheetHTML(p) {
    if (p.img) return `<div class="sheet"><img src="${p.img}" alt="First page of ${esc(p.title)} ${p.year}" loading="lazy"></div>`;
    return `<div class="sheet gen" aria-hidden="true"><img src="${WK.u('assets/img/logo.png')}" alt=""><b>WAKATA MOCK EXAMINATIONS ${p.year}</b><span>${LV_FULL[p.level]}</span><b class="big">${esc(p.subject)}</b><b>${p.level === 'ple' ? 'Primary Seven' : esc(p.paper)}</b><div class="rule"></div><i></i><i style="width:88%"></i><i style="width:94%"></i><i style="width:70%"></i><i></i><i style="width:82%"></i></div>`;
  }
  function paperCard(p) {
    const n = 'fmt-' + p.id;
    return `<article class="pp"><a class="pp-thumb" href="#" tabindex="-1" aria-hidden="true" data-noop><span class="pp-year">${p.year}</span>${sheetHTML(p)}</a>
      <div class="pp-body"><h3 class="pp-title">${esc(p.title)}</h3>
      <span class="pp-meta">${D.LEVELS[p.level].label}, ${esc(p.time)}</span>
      <div class="fmt" role="radiogroup" aria-label="Format for ${esc(p.title)} ${p.year}">
        <label><input type="radio" name="${n}" value="print" checked data-fmt><span>Printed</span></label>
        <label><input type="radio" name="${n}" value="pdf" data-fmt><span>PDF</span></label></div>
      <div class="bk-foot"><span class="price" data-price data-print="${p.print}" data-pdf="${p.pdf}">${D.money(p.print)}</span>
      <button class="add" data-add data-type="paper" data-id="${p.id}" aria-label="Add ${esc(p.title)} ${p.year} to cart">${ic('cart')}Add</button></div></div></article>`;
  }
  function setCard(s) {
    const save = Math.round((1 - s.price / s.worth) * 100);
    return `<article class="set"><div class="stack" aria-hidden="true">${s.preview.map(f => `<div class="sheet"><img src="${D.PAPER_IMG}${f}" alt="" loading="lazy"></div>`).join('')}</div>
      <div><span class="bk-meta">${D.LEVELS[s.level].label}, ${s.year}</span><h3>${esc(s.title)}</h3>
      <p class="inc">${esc(s.blurb)}</p>
      <details><summary>${s.papers} papers included</summary><ul>${s.subjects.map(x => `<li>${esc(x)}</li>`).join('')}</ul></details>
      <div class="bk-foot"><span class="price"><s>${D.money(s.worth)}</s>${D.money(s.price)}<span class="save">Save ${save}%</span></span>
      <button class="add" data-add data-type="set" data-id="${s.id}">${ic('cart')}Add set</button></div></div></article>`;
  }
  function packCard(k) {
    return `<article class="pk${k.best ? ' best' : ''}">${k.best ? '<span class="ribbon">Most chosen</span>' : ''}
      <span class="for">${esc(k.for)}</span><h3>${esc(k.name)}</h3>
      <div class="amt">${D.money(k.price)} <small>per holiday</small></div>
      <ul>${k.includes.map(x => `<li>${ic('check')}<span>${esc(x)}</span></li>`).join('')}</ul>
      <button class="btn ${k.best ? 'btn-primary' : 'btn-line'}" data-add data-type="pack" data-id="${k.id}">Add package to cart</button></article>`;
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
    const feat = ['bio-uce-practical', 'phy-uclse-new', 'bio-uclse-new', 'math-uce-revision', 'phy-practicals-s123', 'phonics-short-vowels', 'chem-uce-practical', 'agric-uce-notes'];
    $('#home-books').innerHTML = feat.map(id => bookCard(D.byId(D.books, id))).join('');
    const pids = ['pp-uce-mathematics-paper-1-2026', 'pp-uace-literature-in-english-paper-2-2024', 'pp-ple-mathematics-paper-1-2025', 'pp-uce-art-design-paper-2-2026'];
    $('#home-papers').innerHTML = pids.map(id => paperCard(D.byId(D.papers, id))).join('');
    $('#home-packs').innerHTML = ['hp-p7', 'hp-s4', 'hp-s6'].map(id => packCard(D.byId(D.packages, id))).join('');
    const bc = countBy(D.books, b => b.level), pc = countBy(D.papers, p => p.level);
    $$('[data-lvl-count]').forEach(el => { const [lv, bl] = el.dataset.lvlCount.split(':'); el.textContent = `${bc[bl]} books and ${pc[lv]} past papers`; });
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
    const b = D.byId(D.books, params.get('id')) || D.books[0];
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
    $('#d-add').onclick = () => { cart.add('book', b.id, null, Math.max(1, +q.value || 1)); toast('Added to your cart. <a href="' + WK.r.cart + '">View cart</a>'); };
    $('#d-buy').onclick = () => { cart.add('book', b.id, null, Math.max(1, +q.value || 1)); location.href = WK.r.cart; };
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
    const list = $('#lines'), sumEl = $('#summary'), form = $('#checkout');
    function draw() {
      const items = cart.get().map(i => ({ i, d: info(i) })).filter(x => x.d);
      $('#cart-wrap').hidden = !items.length; $('#cart-empty').hidden = !!items.length;
      list.innerHTML = items.map(({ i, d }) => `<div class="line"><div class="mini">${d.thumb}</div>
        <div><h3><a href="${d.href}">${esc(d.t)}</a></h3><div class="sub">${esc(d.sub)}</div><button class="rm" data-rm="${i.k}" type="button">Remove</button></div>
        <div class="right"><span class="price">${D.money(d.price * i.qty)}</span><div class="qty" role="group" aria-label="Quantity">
        <button type="button" data-q="-1" data-k="${i.k}" aria-label="Decrease quantity">&minus;</button><input value="${i.qty}" readonly aria-label="Quantity"><button type="button" data-q="1" data-k="${i.k}" aria-label="Increase quantity">+</button></div></div></div>`).join('');
      const sub = items.reduce((n, { i, d }) => n + d.price * i.qty, 0), phys = items.some(x => x.d.phys);
      $('#delivery-box').hidden = !phys;
      const dv = phys ? +($('input[name=delivery]:checked') || { value: 0 }).value : 0;
      $('#addr-field').hidden = !phys || dv === 0;
      sumEl.innerHTML = `<div class="sum"><span>Subtotal</span><span>${D.money(sub)}</span></div><div class="sum"><span>Delivery</span><span>${phys ? (dv ? D.money(dv) : 'Free pickup') : 'Not needed (digital)'}</span></div><div class="sum total"><span>Total</span><span>${D.money(sub + dv)}</span></div>`;
      badge();
    }
    list.addEventListener('click', e => {
      const c = cart.get();
      const rm = e.target.closest('[data-rm]'), q = e.target.closest('[data-q]');
      if (rm) cart.set(c.filter(i => i.k !== rm.dataset.rm));
      if (q) { const it = c.find(i => i.k === q.dataset.k); it.qty = Math.max(1, Math.min(99, it.qty + +q.dataset.q)); cart.set(c); }
      draw();
    });
    form.addEventListener('change', draw);
    $('#clear').addEventListener('click', () => { cart.set([]); draw(); });
    form.addEventListener('submit', e => {
      e.preventDefault();
      if (!validate(form)) { const b = $('.field.bad', form); if (b) b.scrollIntoView({ block: 'center', behavior: 'smooth' }); return; }
      $('#ref').textContent = 'WK-' + Math.random().toString(36).slice(2, 8).toUpperCase();
      $('#pay-name').textContent = $('input[name=pay]:checked').dataset.name;
      $('#modal').classList.add('open'); $('#modal .btn').focus(); cart.set([]);
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
    if (a) {
      const type = a.dataset.type, id = a.dataset.id; let fmt = null;
      if (type === 'paper') { const r = a.closest('.pp').querySelector('input[data-fmt]:checked'); fmt = r ? r.value : 'print'; }
      cart.add(type, id, fmt, 1);
      toast('Added to your cart. <a href="' + WK.r.cart + '">View cart</a>');
      if (a.classList.contains('add')) { const o = a.innerHTML; a.classList.add('done'); a.innerHTML = ic('check') + 'Added'; setTimeout(() => { a.classList.remove('done'); a.innerHTML = o; }, 1400); }
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
