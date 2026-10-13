/* ==========================================================
   WAKATA Admin core: shell, overlays, tables, forms
   Front-end only. Every "save" shows a toast; wire the
   handlers to Laravel routes/controllers when the backend lands.
   ========================================================== */
(function () {
  'use strict';
  const $ = (s, r) => (r || document).querySelector(s), $$ = (s, r) => [...(r || document).querySelectorAll(s)];
  const AD = window.AD, ADC = window.ADC, WK = window.WK, page = document.body.dataset.page;
  const AUTH_PAGES = ['login', 'register', 'forgot', 'reset', '404'];
  const ic = n => `<svg class="ico" viewBox="0 0 24 24" aria-hidden="true">${ADC.icons[n] || ''}</svg>`;
  const esc = AD.esc, qs = k => new URLSearchParams(location.search).get(k);
  const me = (window.WK && WK.user) || {}; /* set by Laravel (partials/account/auth-state) */

  /* ---------- toast ---------- */
  function toast(msg, type) {
    let w = $('.toasts'); if (!w) { w = document.createElement('div'); w.className = 'toasts'; w.setAttribute('aria-live', 'polite'); document.body.appendChild(w); }
    const t = document.createElement('div'); t.className = 'toast' + (type === 'err' ? ' err' : ''); t.innerHTML = ic(type === 'err' ? 'alert' : 'check') + '<span>' + msg + '</span>'; w.appendChild(t);
    setTimeout(() => { t.style.transition = 'opacity .3s'; t.style.opacity = 0; setTimeout(() => t.remove(), 320); }, 3600);
  }

  /* ---------- modal / drawer / confirm ---------- */
  const open = id => { const m = typeof id === 'string' ? document.getElementById(id) : id; if (!m) return; m.classList.add('open'); document.body.style.overflow = 'hidden'; const f = $('input:not([type=hidden]),select,textarea', m); if (f && !m.classList.contains('pal')) setTimeout(() => f.focus(), 60); };
  const close = m => { m = m || $('.mback.open'); if (!m) return; m.classList.remove('open'); if (!$('.mback.open') && !$('.drawer.open')) document.body.style.overflow = ''; };
  function drawer(title, body, foot) {
    let b = $('#drawerBack'), d = $('#drawer');
    if (!d) { b = document.createElement('div'); b.id = 'drawerBack'; b.className = 'drawer-back'; d = document.createElement('aside'); d.id = 'drawer'; d.className = 'drawer'; d.setAttribute('aria-modal', 'true'); document.body.append(b, d); b.addEventListener('click', closeDrawer); }
    d.innerHTML = `<div class="modal-h"><h2>${title}</h2><button class="btn btn-ic" type="button" data-close-drawer aria-label="Close">${ic('x')}</button></div><div class="modal-b">${body}</div>${foot ? `<div class="modal-f">${foot}</div>` : ''}`;
    requestAnimationFrame(() => { b.classList.add('open'); d.classList.add('open'); }); document.body.style.overflow = 'hidden';
  }
  function closeDrawer() { const b = $('#drawerBack'), d = $('#drawer'); if (!d) return; b.classList.remove('open'); d.classList.remove('open'); if (!$('.mback.open')) document.body.style.overflow = ''; }
  function confirmBox(o) {
    let m = $('#confirmModal'); if (!m) { m = document.createElement('div'); m.id = 'confirmModal'; m.className = 'mback'; m.innerHTML = '<div class="modal sm" role="alertdialog" aria-modal="true"><div class="modal-b" style="text-align:center;padding:1.8rem 1.4rem 1rem"><div class="auth-ico" style="margin:0 auto 1rem;background:#FDE3E7;color:var(--pen)" id="cfIco"></div><h2 id="cfT"></h2><p class="muted" id="cfP" style="margin:0"></p></div><div class="modal-f" style="justify-content:center"><button class="btn btn-line" type="button" data-close>Cancel</button><button class="btn btn-danger" type="button" id="cfOk"></button></div></div>'; document.body.appendChild(m); }
    $('#cfIco', m).innerHTML = ic(o.icon || 'trash'); $('#cfT', m).textContent = o.title || 'Are you sure?'; $('#cfP', m).textContent = o.text || ''; const ok = $('#cfOk', m); ok.textContent = o.ok || 'Delete'; ok.className = 'btn ' + (o.danger === false ? 'btn-primary' : 'btn-danger');
    ok.onclick = () => { close(m); o.onOk && o.onOk(); }; open(m);
  }

  /* ---------- csv ---------- */
  function csv(name, head, rows) {
    const q = v => '"' + String(v == null ? '' : v).replace(/"/g, '""') + '"';
    const txt = [head.map(q).join(','), ...rows.map(r => r.map(q).join(','))].join('\r\n');
    const a = document.createElement('a'); a.href = URL.createObjectURL(new Blob(['\ufeff' + txt], { type: 'text/csv' })); a.download = name + '.csv'; document.body.appendChild(a); a.click(); a.remove();
    toast('Exported ' + rows.length + ' rows to ' + name + '.csv');
  }

  /* ---------- data table ---------- */
  function table(el, cfg) {
    el = typeof el === 'string' ? $(el) : el; if (!el) return;
    const S = { q: '', tab: cfg.tabs ? (cfg.tabs.default || cfg.tabs.items[0].k) : null, f: {}, sort: cfg.sort || null, dir: cfg.dir || 1, page: 1, size: cfg.size || 10, sel: new Set() };
    const all = () => typeof cfg.rows === 'function' ? cfg.rows() : cfg.rows, idOf = cfg.id || (r => r.id), strip = h => String(h).replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ').trim();
    el.classList.add('card');
    const tabsH = cfg.tabs ? '<div class="tabs" style="padding:0 .8rem" role="tablist"></div>' : '';
    const filtersH = (cfg.filters || []).map(f => `<select class="sel" data-f="${f.k}" aria-label="${f.label}"><option value="">${f.all || 'All'}</option>${f.options.map(o => `<option value="${o[0]}">${o[1]}</option>`).join('')}</select>`).join('');
    el.innerHTML = `${tabsH}<div class="toolbar"><div class="tsearch"><label class="sr">Search</label>${ic('search')}<input type="search" data-q placeholder="${cfg.placeholder || 'Search…'}"></div>${filtersH}<div style="margin-left:auto;display:flex;gap:.5rem;align-items:center">${cfg.toolbar || ''}<button class="btn btn-line btn-sm" type="button" data-export>${ic('download')}Export CSV</button></div></div><div class="bulkbar" hidden></div><div class="tbl-wrap"><table class="tbl"><thead></thead><tbody></tbody></table></div><div class="tbl-foot"></div>`;
    if (cfg.q0) { S.q = cfg.q0; $('[data-q]', el).value = cfg.q0; }
    function rows() {
      let r = all();
      if (cfg.tabs) { const t = cfg.tabs.items.find(x => x.k === S.tab); if (t && t.fn) r = r.filter(t.fn); }
      (cfg.filters || []).forEach(f => { const v = S.f[f.k]; if (v) r = r.filter(x => f.fn(x, v)); });
      if (S.q) { const q = S.q.toLowerCase(); r = r.filter(x => cfg.search(x).toLowerCase().includes(q)); }
      if (S.sort) { const c = cfg.cols.find(c => c.k === S.sort); if (c && c.sort) r = r.slice().sort((a, b) => { const x = c.sort(a), y = c.sort(b); return (x > y ? 1 : x < y ? -1 : 0) * S.dir; }); }
      return r;
    }
    function render() {
      const r = rows(), pages = Math.max(1, Math.ceil(r.length / S.size)); if (S.page > pages) S.page = pages;
      const slice = r.slice((S.page - 1) * S.size, S.page * S.size);
      if (cfg.tabs) { const a = all(); $('.tabs', el).innerHTML = cfg.tabs.items.map(t => `<button class="tab${t.k === S.tab ? ' on' : ''}" type="button" role="tab" data-tab="${t.k}">${t.label}<span class="n">${t.fn ? a.filter(t.fn).length : a.length}</span></button>`).join(''); }
      $('thead', el).innerHTML = '<tr>' + (cfg.select ? `<th style="width:40px"><input type="checkbox" data-all aria-label="Select all"${slice.length && slice.every(x => S.sel.has(idOf(x))) ? ' checked' : ''}></th>` : '') + cfg.cols.map(c => `<th class="${c.sort ? 'sortable ' : ''}${c.num ? 'num ' : ''}${S.sort === c.k ? (S.dir > 0 ? 'asc' : 'desc') : ''}" ${c.sort ? `data-sort="${c.k}"` : ''}>${c.label}${c.sort ? `<span class="si">${S.sort === c.k ? (S.dir > 0 ? '▲' : '▼') : '↕'}</span>` : ''}</th>`).join('') + (cfg.actions ? '<th></th>' : '') + '</tr>';
      $('tbody', el).innerHTML = slice.length ? slice.map(x => { const id = idOf(x); return `<tr data-id="${esc(id)}" class="${S.sel.has(id) ? 'sel-row' : ''}${cfg.rowHref ? ' rowlink' : ''}">` + (cfg.select ? `<td><input type="checkbox" data-sel aria-label="Select row"${S.sel.has(id) ? ' checked' : ''}></td>` : '') + cfg.cols.map(c => `<td class="${c.num ? 'num' : ''} ${c.cls || ''}">${c.html(x)}</td>`).join('') + (cfg.actions ? `<td class="num">${cfg.actions(x)}</td>` : '') + '</tr>'; }).join('') : `<tr><td colspan="${cfg.cols.length + (cfg.select ? 1 : 0) + (cfg.actions ? 1 : 0)}"><div class="empty"><h3>${cfg.empty || 'Nothing found'}</h3><p>Try a different search or clear the filters.</p></div></td></tr>`;
      const from = r.length ? (S.page - 1) * S.size + 1 : 0, to = Math.min(r.length, S.page * S.size);
      let pg = ''; if (pages > 1) { const lo = Math.max(1, Math.min(S.page - 2, pages - 4)), hi = Math.min(pages, lo + 4); pg = `<div class="pager"><button type="button" data-pg="${S.page - 1}" ${S.page === 1 ? 'disabled' : ''} aria-label="Previous">‹</button>${Array.from({ length: hi - lo + 1 }, (_, i) => lo + i).map(p => `<button type="button" data-pg="${p}" class="${p === S.page ? 'on' : ''}">${p}</button>`).join('')}<button type="button" data-pg="${S.page + 1}" ${S.page === pages ? 'disabled' : ''} aria-label="Next">›</button></div>`; }
      $('.tbl-foot', el).innerHTML = `<span>Showing ${from} to ${to} of ${r.length}</span>${pg}`;
      const bb = $('.bulkbar', el); if (cfg.select && S.sel.size) { bb.hidden = false; bb.innerHTML = `<span>${S.sel.size} selected</span>${(cfg.bulk || []).map((b, i) => `<button class="btn" type="button" data-bulk="${i}">${b.label}</button>`).join('')}<button class="btn" type="button" data-bulk-clear style="margin-left:auto">Clear</button>`; } else bb.hidden = true;
      cfg.after && cfg.after(r, el);
    }
    let tmr; el.addEventListener('input', e => { if (e.target.matches('[data-q]')) { clearTimeout(tmr); tmr = setTimeout(() => { S.q = e.target.value.trim(); S.page = 1; render(); }, 120); } });
    el.addEventListener('change', e => {
      const t = e.target;
      if (t.matches('[data-f]')) { S.f[t.dataset.f] = t.value; S.page = 1; render(); }
      else if (t.matches('[data-all]')) { const r = rows().slice((S.page - 1) * S.size, S.page * S.size); r.forEach(x => t.checked ? S.sel.add(idOf(x)) : S.sel.delete(idOf(x))); render(); }
      else if (t.matches('[data-sel]')) { const id = t.closest('tr').dataset.id; t.checked ? S.sel.add(id) : S.sel.delete(id); render(); }
    });
    el.addEventListener('click', e => {
      const t = e.target, g = s => t.closest(s);
      let n;
      if ((n = g('[data-tab]'))) { S.tab = n.dataset.tab; S.page = 1; render(); }
      else if ((n = g('[data-sort]'))) { const k = n.dataset.sort; if (S.sort === k) S.dir = -S.dir; else { S.sort = k; S.dir = 1; } render(); }
      else if ((n = g('[data-pg]'))) { S.page = +n.dataset.pg; render(); el.scrollIntoView({ block: 'nearest' }); }
      else if (g('[data-export]')) { const r = rows(); csv(cfg.csvName || 'export', cfg.cols.map(c => c.label), r.map(x => cfg.cols.map(c => c.csv ? c.csv(x) : strip(c.html(x))))); }
      else if ((n = g('[data-bulk]'))) { const b = cfg.bulk[+n.dataset.bulk]; const ids = [...S.sel]; if (b.act) b.act(ids, S); else toast(b.label + ': ' + ids.length + ' item(s). Design preview, nothing changed yet.'); }
      else if (g('[data-bulk-clear]')) { S.sel.clear(); render(); }
      else if ((n = g('[data-act]'))) { if (n.tagName === 'A') e.preventDefault(); const tr = g('tr'), id = tr.dataset.id, row = all().find(x => String(idOf(x)) === id); if (cfg.onAct && cfg.onAct(n.dataset.act, row, id, S) !== false) return; if (n.dataset.act === 'delete') confirmBox({ title: 'Delete this item?', text: 'It will be removed from the website. This is a design preview, so nothing is deleted yet.', onOk: () => toast('Deleted (preview only).') }); else if (n.tagName !== 'A') toast(n.dataset.act.charAt(0).toUpperCase() + n.dataset.act.slice(1) + ': design preview, no data changed.'); }
      else if (cfg.rowHref && (n = g('tr.rowlink')) && !g('a,button,input,select,label')) { const row = all().find(x => String(idOf(x)) === n.dataset.id); location.href = cfg.rowHref(row); }
    });
    S.render = render; S.rows = rows; render(); return S;
  }

  /* ---------- tabs ---------- */
  function initTabs() {
    $$('[data-tabs]').forEach(w => {
      const tabs = $$('.tab', w), panes = tabs.map(t => document.getElementById(t.dataset.pane));
      const show = id => { tabs.forEach((t, i) => { const on = t.dataset.pane === id; t.classList.toggle('on', on); t.setAttribute('aria-selected', on); if (panes[i]) panes[i].hidden = !on; }); };
      tabs.forEach(t => t.addEventListener('click', () => { show(t.dataset.pane); history.replaceState(null, '', '#' + t.dataset.pane); window.dispatchEvent(new Event('resize')); }));
      const h = location.hash.slice(1); show(tabs.some(t => t.dataset.pane === h) ? h : (tabs.find(t => t.classList.contains('on')) || tabs[0]).dataset.pane);
    });
    $$('.seg[data-seg]').forEach(s => s.addEventListener('click', e => { const b = e.target.closest('button'); if (!b) return; $$('button', s).forEach(x => x.classList.toggle('on', x === b)); s.dispatchEvent(new CustomEvent('seg', { detail: b.dataset.v })); }));
  }

  /* ---------- forms ---------- */
  const repRow = (v, ph, name) => `<div class="rrow" draggable="false"><span class="grip" title="Drag to reorder">${ic('drag')}</span><input name="${name || 'items[]'}" value="${esc(v || '')}" placeholder="${esc(ph || '')}"><button class="btn btn-ic" type="button" data-rm-row aria-label="Remove">${ic('x')}</button></div>`;
  function fillForm(f) {
    const key = f.dataset.load, id = qs('id'); const list = WK[key]; if (!list) return null; const rec = id ? list.find(x => x.id === id) : null; if (!rec) return null; if (rec.preview) rec.preview.forEach((p, i) => rec['preview' + (i + 1)] = WK.PAPER_IMG + p);
    $$('[name]', f).forEach(i => { const n = i.name; if (n.endsWith('[]') || rec[n] === undefined) return; if (i.type === 'checkbox') i.checked = !!rec[n]; else if (i.type !== 'file') i.value = rec[n]; });
    $$('[data-fill-list]', f).forEach(c => { const arr = rec[c.dataset.fillList] || []; $('.rlist', c).innerHTML = arr.map(v => repRow(v, c.dataset.ph, c.dataset.fillList + '[]')).join(''); });
    $$('[data-fill-tags]', f).forEach(c => { (rec[c.dataset.fillTags] || []).forEach(v => addTag(c, v)); });
    $$('[data-bind-text]').forEach(n => { n.textContent = rec[n.dataset.bindText] != null ? rec[n.dataset.bindText] : n.textContent; });
    $$('[data-live-img]', f.closest('main') || document).forEach(n => { const v = rec[n.dataset.liveImg]; if (v) { n.src = AD.img(v); n.hidden = false; const p = n.closest('[data-img-wrap]'); if (p) p.classList.add('has'); } });
    $$('.drop[data-preview]', f).forEach(d => { const nm = $('input[type=file]', d).name, v = rec[nm]; if (v && !$('.pv', d)) { const im = document.createElement('img'); im.className = 'pv'; im.src = AD.img(v); im.alt = ''; d.prepend(im); } });
    document.title = (rec.title || rec.name || document.title) + ' | WAKATA Admin';
    f.dataset.mode = 'edit'; $$('[data-new-only]').forEach(n => n.hidden = true); $$('[data-edit-only]').forEach(n => n.hidden = false);
    return rec;
  }
  function live(f) {
    const run = () => $$('[data-live]').forEach(n => { const i = f.elements[n.dataset.live]; if (!i) return; let v = i.type === 'checkbox' ? i.checked : i.value; if (n.dataset.fmt === 'money') v = v === '' ? '' : WK.money(Number(v)); if (n.dataset.fmt === 'disc') { const o = +f.elements.old.value, p = +f.elements.price.value; v = o > p && p > 0 ? '-' + Math.round((o - p) / o * 100) + '%' : ''; } if (n.dataset.fmt === 'saving') { const w = +f.elements.worth.value, p = +f.elements.price.value; v = w > p && p > 0 ? 'Save ' + Math.round((w - p) / w * 100) + '% (' + WK.money(w - p) + ')' : ''; } if (n.dataset.show) { n.hidden = !v; if (v && typeof v === 'string') n.textContent = v; return; } n.textContent = v || n.dataset.empty || ''; });
    f.addEventListener('input', run); f.addEventListener('change', run); run();
  }
  function addTag(box, v) { v = (v || '').trim().replace(/,$/, ''); if (!v) return; const c = document.createElement('span'); c.className = 'chip on'; c.innerHTML = esc(v) + '<span class="x" role="button" aria-label="Remove">×</span><input type="hidden" name="' + (box.dataset.name || 'tags[]') + '" value="' + esc(v) + '">'; $('input:not([type=hidden])', box).before(c); }
  window.addEventListener('pageshow', () => $$('form[data-native] [type=submit]').forEach(b => { b.disabled = false; })); /* re-enable after Back */
  function initForms() {
    $$('form[data-validate]').forEach(f => {
      f.setAttribute('novalidate', '');
      f.addEventListener('submit', e => {
        e.preventDefault(); let ok = true;
        $$('[required]', f).forEach(i => { if (i.disabled || i.closest('[hidden]')) return; const bad = i.type === 'checkbox' ? !i.checked : (!i.value.trim() || !i.checkValidity()); const fl = i.closest('.field'); if (fl) fl.classList.toggle('bad', bad); if (bad && ok) { ok = false; i.focus(); } });
        if (f.dataset.match) { const [a, b] = f.dataset.match.split(','); if (f.elements[a].value !== f.elements[b].value) { f.elements[b].closest('.field').classList.add('bad'); $('.err', f.elements[b].closest('.field')).textContent = 'The passwords do not match.'; ok = false; } }
        if (!ok) { toast('Please fix the highlighted fields.', 'err'); return; }
        if (f.hasAttribute('data-native')) { const sb = f.querySelector('[type=submit]'); if (sb) sb.disabled = true; f.submit(); return; } /* real server-side form (Laravel) */
        if (f.dataset.onsubmit && window.ADM_HOOKS && ADM_HOOKS[f.dataset.onsubmit]) { ADM_HOOKS[f.dataset.onsubmit](f); return; }
        toast(f.dataset.ok || 'Saved. This is a design preview, so nothing is stored yet.'); if (f.dataset.next) setTimeout(() => location.href = f.dataset.next, 900);
      });
      f.addEventListener('input', e => { const fl = e.target.closest('.field'); if (fl) fl.classList.remove('bad'); });
    });
    $$('form[data-load]').forEach(f => { fillForm(f); });
    $$('form[data-live-form]').forEach(live);
    $$('.drop[data-preview]').forEach(d => {
      const inp = $('input[type=file]', d); ['dragenter', 'dragover'].forEach(ev => d.addEventListener(ev, () => d.classList.add('over'))); ['dragleave', 'drop'].forEach(ev => d.addEventListener(ev, () => d.classList.remove('over')));
      inp.addEventListener('change', () => { const f = inp.files[0]; if (!f) return; const dz = $('.dz small', d); if (f.type.startsWith('image/')) { const url = URL.createObjectURL(f); let im = $('.pv', d); if (!im) { im = document.createElement('img'); im.className = 'pv'; im.alt = ''; d.prepend(im); } im.src = url; $$('[data-live-img="' + inp.name + '"]').forEach(n => { n.src = url; n.hidden = false; const p = n.closest('[data-img-wrap]'); if (p) p.classList.add('has'); }); if (dz) dz.textContent = f.name + ' (' + Math.round(f.size / 1024) + ' KB)'; } else if (dz) { dz.textContent = f.name + ' (' + (f.size > 1048576 ? (f.size / 1048576).toFixed(1) + ' MB' : Math.round(f.size / 1024) + ' KB') + ')'; d.classList.add('has-file'); } toast('File selected: ' + f.name); });
    });
    $$('[data-pw]').forEach(w => { const b = $('button', w), i = $('input', w); b.addEventListener('click', () => { const s = i.type === 'password'; i.type = s ? 'text' : 'password'; b.innerHTML = ic(s ? 'eye' : 'eye'); b.setAttribute('aria-label', s ? 'Hide password' : 'Show password'); b.style.opacity = s ? .6 : 1; }); });
    $$('[data-strength]').forEach(i => { const m = document.getElementById(i.dataset.strength); i.addEventListener('input', () => { const v = i.value; let s = 0; if (v.length >= 8) s++; if (/[A-Z]/.test(v) && /[a-z]/.test(v)) s++; if (/\d/.test(v)) s++; if (/[^A-Za-z0-9]/.test(v) || v.length >= 14) s++; m.dataset.s = v ? s : 0; }); });
    $$('input[data-enables]').forEach(c => { const area = $(c.dataset.enables); const ap = () => { if (!area) return; area.classList.toggle('off', !c.checked); $$('input,select,textarea', area).forEach(x => { if (x !== c) x.disabled = !c.checked; }); }; c.addEventListener('change', ap); ap(); });
    document.addEventListener('click', e => {
      let n;
      if ((n = e.target.closest('[data-add-row]'))) { const c = n.closest('.repeater'); $('.rlist', c).insertAdjacentHTML('beforeend', repRow('', c.dataset.ph, c.dataset.name)); $('.rlist .rrow:last-child input', c).focus(); }
      else if ((n = e.target.closest('[data-rm-row]'))) n.closest('.rrow').remove();
      else if ((n = e.target.closest('.tagbox .x'))) n.closest('.chip').remove();
    });
    document.addEventListener('keydown', e => { const t = e.target; if (t.matches && t.matches('.tagbox input:not([type=hidden])') && (e.key === 'Enter' || e.key === ',')) { e.preventDefault(); addTag(t.closest('.tagbox'), t.value); t.value = ''; } });
    document.addEventListener('mousedown', e => { const g = e.target.closest('.grip'); if (g) g.closest('.rrow').draggable = true; });
    let dragEl = null;
    document.addEventListener('dragstart', e => { const r = e.target.closest && e.target.closest('.rrow'); if (r) { dragEl = r; r.style.opacity = .4; } });
    document.addEventListener('dragover', e => { const r = e.target.closest && e.target.closest('.rrow'); if (dragEl && r && r !== dragEl && r.parentNode === dragEl.parentNode) { e.preventDefault(); const b = r.getBoundingClientRect(); r.parentNode.insertBefore(dragEl, e.clientY > b.top + b.height / 2 ? r.nextSibling : r); } });
    document.addEventListener('dragend', () => { if (dragEl) { dragEl.style.opacity = ''; dragEl.draggable = false; dragEl = null; } });
  }

  /* ---------- command palette ---------- */
  function palette() {
    const m = document.createElement('div'); m.className = 'mback pal'; m.id = 'pal'; m.innerHTML = '<div class="box" role="dialog" aria-modal="true" aria-label="Search"><input type="search" placeholder="Search your orders, downloads and the shop…" aria-label="Search" autocomplete="off"><ul></ul></div>'; document.body.appendChild(m);
    const inp = $('input', m), ul = $('ul', m); let items = [], hi = 0;
    const pages = ADC.nav.flatMap(g => g.items.map(i => ({ g: 'Pages', t: i.label, s: g.group, href: i.href, icon: i.icon })));
    [['Browse past papers', '../past-papers.html', 'file'], ['Browse books', '../books.html', 'book'], ['Exam sets', '../exam-sets.html', 'layers'], ['Holiday packages', '../holiday-packages.html', 'gift'], ['Open my cart', '../cart.html', 'cart']].forEach(a => pages.push({ g: 'Quick actions', t: a[0], s: '', href: a[1], icon: a[2] }));
    const data = () => [...pages, ...WK.books.map(b => ({ g: 'Books', t: b.title, s: b.subject, href: '../book.html?id=' + b.id, icon: 'book' })), ...AD.orders.slice(0, 80).map(o => ({ g: 'Orders', t: o.id, s: AD.fmtDate(o.ts) + ' · ' + WK.money(o.total), href: 'order.html?id=' + o.id, icon: 'cart' }))];
    const all = data();
    function render() { const q = inp.value.trim().toLowerCase(); items = (q ? all.filter(x => (x.t + ' ' + x.s + ' ' + x.g).toLowerCase().includes(q)) : all.filter(x => x.g !== 'Orders' && x.g !== 'Books')).slice(0, 30); hi = 0; let last = ''; ul.innerHTML = items.length ? items.map((x, i) => { const h = x.g !== last ? `<li class="gh">${x.g}</li>` : ''; last = x.g; return h + `<li><a href="${x.href}" class="${i === 0 ? 'hi' : ''}" data-i="${i}">${ic(x.icon)}<span>${esc(x.t)}</span><small>${esc(x.s)}</small></a></li>`; }).join('') : '<li class="empty">No matches</li>'; }
    inp.addEventListener('input', render);
    inp.addEventListener('keydown', e => { const as = $$('a', ul); if (e.key === 'ArrowDown' || e.key === 'ArrowUp') { e.preventDefault(); if (!as.length) return; as[hi].classList.remove('hi'); hi = (hi + (e.key === 'ArrowDown' ? 1 : -1) + as.length) % as.length; as[hi].classList.add('hi'); as[hi].scrollIntoView({ block: 'nearest' }); } else if (e.key === 'Enter' && as[hi]) { location.href = as[hi].getAttribute('href'); } });
    const show = () => { inp.value = ''; render(); open(m); setTimeout(() => inp.focus(), 40); };
    document.addEventListener('keydown', e => { if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'k') { e.preventDefault(); show(); } else if (e.key === '/' && !/INPUT|TEXTAREA|SELECT/.test(document.activeElement.tagName)) { e.preventDefault(); show(); } });
    $$('[data-open-palette]').forEach(n => n.addEventListener('click', show));
  }

  /* ---------- shell ---------- */
  function shell() {
    if (me.name) { $$('[data-me-name]').forEach(n => n.textContent = me.name); $$('[data-me-initials]').forEach(n => n.textContent = AD.initials(me.name)); }
    const nl = $('#notifList'); if (nl) nl.insertAdjacentHTML('beforeend', AD.notifications.map(n => `<div class="it"><span class="ni">${ic(n.icon)}</span><div><b>${esc(n.t)}</b><small>${esc(n.s)} · ${AD.rel(n.ts)}</small></div></div>`).join('') + '<a href="activity.html" style="display:block;text-align:center;padding:.7rem;font-weight:700;font-size:.85rem">View all activity</a>');
    const bc = { orders: AD.orders.filter(o => o.status === 'pending').length, messages: AD.messages.filter(m => m.status === 'new').length };
    $$('[data-badge]').forEach(b => { const n = bc[b.dataset.badge]; if (n) b.textContent = n; else b.remove(); });
    const tb = $('#themeBtn'), setIcon = () => { if (tb) tb.innerHTML = ic(document.documentElement.getAttribute('data-theme') === 'dark' ? 'sun' : 'moon'); };
    setIcon(); tb && tb.addEventListener('click', () => { const d = document.documentElement.getAttribute('data-theme') === 'dark'; if (d) document.documentElement.removeAttribute('data-theme'); else document.documentElement.setAttribute('data-theme', 'dark'); try { d ? localStorage.removeItem('wk_theme') : localStorage.setItem('wk_theme', 'dark'); } catch (e) { } setIcon(); });
    const st = $('#sbToggle'); st && st.addEventListener('click', () => { if (innerWidth <= 1024) document.documentElement.classList.toggle('sb-open'); else { const m = document.documentElement.getAttribute('data-sb') === 'mini'; m ? document.documentElement.removeAttribute('data-sb') : document.documentElement.setAttribute('data-sb', 'mini'); try { m ? localStorage.removeItem('wk_sb') : localStorage.setItem('wk_sb', 'mini'); } catch (e) { } setTimeout(() => window.dispatchEvent(new Event('resize')), 260); } });
    const so = $('#sbOverlay'); so && so.addEventListener('click', () => document.documentElement.classList.remove('sb-open'));
    document.addEventListener('click', e => {
      const dd = e.target.closest('[data-dd]'); $$('.dd.open').forEach(d => { if (!dd || d !== dd.parentNode) d.classList.remove('open'); }); if (dd) dd.parentNode.classList.toggle('open');
      let n;
      if ((n = e.target.closest('[data-open]'))) { e.preventDefault(); open(n.dataset.open); }
      else if (e.target.closest('[data-close]')) { close(e.target.closest('.mback')); }
      else if (e.target.classList && e.target.classList.contains('mback')) close(e.target);
      else if (e.target.closest('[data-close-drawer]')) closeDrawer();
      else if ((n = e.target.closest('[data-toast]'))) { if (n.tagName === 'A') e.preventDefault(); toast(n.dataset.toast); }
      else if ((n = e.target.closest('[data-confirm]'))) { e.preventDefault(); confirmBox({ title: n.dataset.confirm, text: n.dataset.text || 'This is a design preview, so nothing is changed yet.', ok: n.dataset.ok || 'Delete', danger: n.dataset.danger !== 'false', icon: n.dataset.icon, onOk: () => { toast(n.dataset.done || 'Done (preview only).'); if (n.dataset.then) setTimeout(() => location.href = n.dataset.then, 800); } }); }
      else if (e.target.closest('[data-logout]')) { e.preventDefault(); const lf = $('#logoutForm'); if (lf) lf.submit(); }
      else if (e.target.closest('[data-lock]')) { location.href = 'locked.html'; }
      else if (e.target.closest('[data-print]')) { window.print(); }
      else if ((n = e.target.closest('[data-copy]'))) { try { navigator.clipboard.writeText(n.dataset.copy); } catch (er) { } toast('Copied to clipboard'); }
    });
    document.addEventListener('keydown', e => { if (e.key === 'Escape') { $$('.dd.open').forEach(d => d.classList.remove('open')); closeDrawer(); const m = $$('.mback.open').pop(); if (m) close(m); document.documentElement.classList.remove('sb-open'); } });
    if (!AUTH_PAGES.includes(page)) palette();
  }
  shell(); initTabs(); initForms();
  (function cartBadge() { const n = ((window.WK || {}).cart || {}).count || 0; $$('[data-cart-count]').forEach(b => { b.textContent = n || ''; b.hidden = !n; }); })();

  window.ADM = { $, $$, ic, esc, toast, open, close, drawer, closeDrawer, confirm: confirmBox, csv, table, qs, me, repRow, addTag, fillForm, AD, WK, ADC };
})();
