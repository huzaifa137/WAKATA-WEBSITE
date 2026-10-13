/* ==========================================================
   WAKATA customer account: page logic
   ========================================================== */
(function () {
  'use strict';
  if (!window.ADM) return;
  const { $, $$, ic, esc, toast, AD, WK, ADC } = ADM, M = WK.money, C = WKC, P = {};
  const page = document.body.dataset.page, OS = AD.ORDER_STATUS, PS = AD.PAY_STATUS, user = AD.user || {};
  const sBadge = s => `<span class="badge ${OS[s].cls}">${OS[s].label}</span>`, pBadge = s => `<span class="badge ${PS[s].cls}">${PS[s].label}</span>`;
  const fmtTag = f => f === 'pdf' ? '<span class="fmt-tag pdf">PDF</span>' : '<span class="fmt-tag print">Printed</span>';
  const first = (user.name || 'there').split(/\s+/)[0];
  const R = AD.R, P0 = AD.P, U = AD.url;
  const cartBadge = () => { const n = (WK.cart || {}).count || 0; $$('[data-cart-count]').forEach(b => { b.textContent = n || ''; b.hidden = !n; }); };
  /* One place for every call to Laravel: JSON in, JSON out, friendly messages for the usual failures. */
  const errMsg = (r, dflt) => { const d = r.data || {}; if (r.status === 419) return 'Your session expired. Please refresh the page and try again.'; if (r.status === 401) return 'Please sign in again to continue.'; if (d.errors) { const k = Object.keys(d.errors)[0]; if (k) return d.errors[k][0]; } return d.message || dflt || 'Something went wrong. Please try again.'; };
  const api = async (url, method, body) => {
    try {
      const res = await fetch(url, { method: method || 'POST', credentials: 'same-origin', headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': WK.csrf }, body: body === undefined ? undefined : JSON.stringify(body) });
      const data = await res.json().catch(() => ({}));
      if (res.status === 401) { setTimeout(() => location.reload(), 1200); }
      return { ok: res.ok, status: res.status, data };
    } catch (e) { return { ok: false, status: 0, data: { message: 'No connection. Check your internet and try again.' } }; }
  };
  const cartAdd = async (items, msg) => {
    const r = await api(WK.r.cartAdd, 'POST', { items });
    if (r.ok) { WK.cart = { count: r.data.count }; cartBadge(); toast((msg || 'Added to your cart.') + ' <a href="' + esc(R.cart) + '" style="color:var(--hl);font-weight:700;margin-left:.4rem">View cart</a>'); }
    else toast(esc(errMsg(r, 'Could not add to your cart.')), 'err');
  };
  const row = (type, id, fmt, qty) => ({ type, id, fmt: fmt || null, qty });
  const itemToCart = (o, i) => cartAdd([row(i.type, i.id, i.fmt, i.qty > 20 ? 1 : i.qty)]);
  const kpi = (icn, tone, label, val, sub) => `<div class="card kpi"><div class="kpi-top"><span class="kpi-ic ${tone}">${ic(icn)}</span></div><div class="kpi-l">${label}</div><div class="kpi-v">${val}</div><div class="kpi-f"><span class="xs muted">${sub || ''}</span></div></div>`;
  const emptyState = (icn, title, text, cta) => `<div class="empty-state"><div class="big">${ic(icn)}</div><h3>${title}</h3><p class="muted" style="max-width:42ch;margin:0 auto 1rem">${text}</p>${cta || ''}</div>`;
  const LVL = { primary: 'PLE', o: 'UCE', a: 'UACE' };
  const swap = (arr, next) => { arr.length = 0; next.forEach(x => arr.push(x)); };

  /* ---- PDF downloads: the server checks the order, counts the download (limit per file) and sends the file ---- */
  async function download(f, after) {
    if (f.used >= f.max) { toast('You have used all ' + f.max + ' downloads for this file. Ask us for more from Help and support.', 'err'); return false; }
    let res;
    try { res = await fetch(U.file(R.download, f.id), { method: 'POST', credentials: 'same-origin', headers: { 'Accept': 'application/pdf,application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': WK.csrf } }); }
    catch (e) { toast('No connection. Check your internet and try again.', 'err'); return false; }
    if (!res.ok) { const d = await res.json().catch(() => ({})); toast(esc(res.status === 419 ? 'Your session expired. Please refresh the page and try again.' : (d.message || 'Could not download this file.')), 'err'); if (d.limit) { f.used = f.max; after && after(); } return false; }
    f.used = +res.headers.get('X-Downloads-Used') || f.used + 1; f.max = +res.headers.get('X-Downloads-Max') || f.max;
    const a = document.createElement('a'); a.href = URL.createObjectURL(await res.blob()); a.download = f.name; document.body.appendChild(a); a.click(); a.remove(); setTimeout(() => URL.revokeObjectURL(a.href), 4000);
    toast('Download started: ' + esc(f.name)); after && after(); return true;
  }
  const meter = f => `<div class="dlmeter"><span class="xs muted">${f.used} of ${f.max} used</span><div class="progress"><i style="width:${f.used / f.max * 100}%;background:${f.used >= f.max ? 'var(--pen)' : f.used >= f.max - 1 ? 'var(--warn)' : 'var(--blue)'}"></i></div></div>`;
  const sizeTxt = f => f.size ? f.size + ' MB' : 'PDF';
  const fileIc = f => `<span class="fi ${f.kind === 'guide' ? 'g' : ''}">${f.kind === 'guide' ? 'GUIDE' : 'PDF'}</span>`;
  const steps = o => { const phys = o.items.some(i => i.fmt !== 'pdf'); const s = phys ? ['Placed', 'Confirmed', 'Being prepared', o.delivery === 'pickup' ? 'Ready for pickup' : 'Out for delivery', 'Delivered'] : ['Placed', 'Paid', 'PDF ready']; const idx = phys ? { pending: 0, confirmed: 1, processing: 2, ready: 3, shipped: 3, completed: 4 }[o.status] : (o.status === 'completed' ? 2 : o.pay === 'paid' ? 1 : 0); return s.map((l, i) => `<div class="st ${i < idx || (i === idx && o.status === 'completed') ? 'done' : i === idx ? 'cur' : ''}"><i>${i < idx || (i === idx && o.status === 'completed') ? '✓' : i + 1}</i>${l}</div>`).join(''); };

  /* ================= DASHBOARD ================= */
  P.dashboard = function () {
    const os = AD.orders, paid = os.filter(AD.counted), spent = AD.sum(paid, o => o.total), active = os.filter(o => ['pending', 'confirmed', 'processing', 'ready', 'shipped'].includes(o.status));
    const ready = AD.files.filter(f => f.used < f.max).length;
    $('#heroName').textContent = first; $('#heroLine').textContent = AD.fresh ? 'Your account is ready. Browse the shop, and everything you buy will appear here for download and tracking.' : (ready ? ready + ' file' + (ready > 1 ? 's are' : ' is') + ' ready to download' : 'No new downloads') + (active.length ? ', and ' + active.length + ' order' + (active.length > 1 ? 's are' : ' is') + ' on the way.' : '.');
    $('#kpis').innerHTML = kpi('box', '', 'Orders placed', os.length, paid.length + ' paid') + kpi('wallet', 'g', 'Total spent', M(spent), 'Across paid orders') + kpi('download', 'r', 'PDFs in your library', AD.files.filter(f => f.kind === 'paper').length, ready + ' ready to download') + kpi('truck', 'p', 'Orders in progress', active.length, active.length ? 'Track below' : 'Nothing pending');
    const t = active[0] || os.find(o => o.status === 'completed');
    $('#tracker').innerHTML = t ? `<div class="flex-b wrapf" style="margin-bottom:1rem"><div><a class="link-row" style="font-size:1.05rem" href="${esc(AD.url.order(t.id))}">Order ${esc(t.id)}</a><div class="sm muted">${AD.fmtDate(t.ts)} · ${t.items.reduce((a, i) => a + i.qty, 0)} items · ${M(t.total)}</div></div>${sBadge(t.status)}</div><div class="stepper">${steps(t)}</div><div class="flex-b wrapf" style="margin-top:1rem"><span class="sm muted">${t.delivery === 'email' ? 'Files are emailed and appear in your library once payment is confirmed.' : (t.status === 'completed' ? 'Delivered. Thank you for shopping with WAKATA.' : 'Expected: ' + esc((AD.DELIVERY[t.delivery] || {}).eta || ''))}</span><a class="btn btn-line btn-sm" href="${esc(AD.url.order(t.id))}">View order</a></div>` : emptyState('box', 'No orders yet', 'When you place an order you can follow it here, step by step.', `<a class="btn btn-primary" href="${esc(R.papers)}">Find a past paper</a>`);
    const fl = AD.files.slice(0, 4); $('#quick').innerHTML = fl.length ? fl.map(f => `<div class="file-row">${fileIc(f)}<div class="t"><b>${esc(f.title)}</b><small>${sizeTxt(f)} · ${AD.rel(f.ts)}</small></div><button class="btn btn-primary btn-sm" type="button" data-dl="${esc(f.id)}" aria-label="Download ${esc(f.title)}">${ic('download')}</button></div>`).join('') : '<p class="muted">PDF downloads you buy will appear here, once your payment is confirmed.</p>';
    $('#quick').addEventListener('click', e => { const b = e.target.closest('[data-dl]'); if (b) download(AD.files.find(f => f.id === b.dataset.dl)); });
    const mo = AD.monthly();
    if (paid.length) { C.bar('#chSpend', { labels: mo.map(m => m.label), series: [{ name: 'Printed and delivery', data: mo.map(m => m.print), color: 'var(--c1)' }, { name: 'PDF downloads', data: mo.map(m => m.pdf), color: 'var(--c3)' }], stacked: true, tipFmt: M, height: 250 }); const cat = AD.byCategory(); C.donut('#chCat', { items: [{ name: 'Books', value: cat.book, color: 'var(--c1)' }, { name: 'Past papers', value: cat.paper, color: 'var(--c3)' }, { name: 'Exam sets', value: cat.set, color: 'var(--c2)' }, { name: 'Holiday packages', value: cat.pack, color: 'var(--c4)' }], centerLabel: 'Total spent', tipFmt: M, size: 170 }); }
    else { $('#chSpend').innerHTML = emptyState('chart', 'Nothing to chart yet', 'Your spending over time will show here after your first paid order.'); $('#chCat').innerHTML = '<p class="muted tc">No purchases yet.</p>'; }
    $('#recent tbody').innerHTML = os.slice(0, 5).map(o => `<tr class="rowlink" style="cursor:pointer" onclick="location.href='${esc(AD.url.order(o.id))}'"><td><a class="link-row" href="${esc(AD.url.order(o.id))}">${esc(o.id)}</a><div class="xs muted">${AD.fmtDate(o.ts)}</div></td><td>${[...new Set(o.items.map(i => i.fmt === 'pdf' ? 'pdf' : 'print'))].map(fmtTag).join(' ')}<div class="xs muted">${o.items.length} line${o.items.length > 1 ? 's' : ''}</div></td><td class="num b">${M(o.total)}</td><td>${sBadge(o.status)}</td></tr>`).join('') || '<tr><td colspan="4"><div class="empty">No orders yet.</div></td></tr>';
    const owned = new Set(os.flatMap(o => o.items.map(i => i.id))); const rec = [...WK.books.filter(b => b.level === 'o' && !owned.has(b.id)).slice(0, 2).map(b => ({ type: 'book', r: b, t: b.title, p: b.price, fmt: null })), ...WK.papers.filter(p => p.level === 'uce' && p.year === 2026 && !owned.has(p.id)).slice(2, 4).map(p => ({ type: 'paper', r: p, t: p.title + ' ' + p.year, p: p.pdf, fmt: 'pdf' }))];
    $('#recs').innerHTML = rec.map(x => `<div class="rec">${AD.thumbOf(x.type, x.r)}<div class="t"><b>${esc(x.t)}</b><small>${x.type === 'book' ? 'Book' : 'PDF paper'} · ${M(x.p)}</small></div><button class="btn btn-line btn-sm" type="button" data-add="${x.type}:${esc(x.r.id)}:${x.fmt || ''}">${ic('cart')}Add</button></div>`).join('');
    $('#recs').addEventListener('click', e => { const b = e.target.closest('[data-add]'); if (!b) return; const [ty, id, fm] = b.dataset.add.split(':'); cartAdd([row(ty, id, fm, 1)]); });
  };

  /* ================= LIBRARY ================= */
  P.library = function () {
    const pdfs = AD.files, printed = AD.orders.filter(AD.counted).flatMap(o => o.items.filter(i => i.fmt !== 'pdf' && (i.type === 'book' || i.type === 'paper')).map(i => ({ o, i, id: o.id + ':' + i.iid }))), bundles = AD.orders.filter(AD.counted).flatMap(o => o.items.filter(i => i.type === 'set' || i.type === 'pack').map(i => ({ o, i })));
    $('#libStats').innerHTML = kpi('download', 'r', 'PDF files', pdfs.filter(f => f.kind === 'paper').length, 'Yours to download') + kpi('file', 'g', 'Marking guides and extras', pdfs.filter(f => f.kind === 'guide').length, 'Included free') + kpi('book', '', 'Printed items', AD.sum(printed, x => x.i.qty), 'Books and papers') + kpi('layers', 'y', 'Sets and packages', bundles.length, 'Bundles bought');
    $('#cPdf').textContent = pdfs.length; $('#cPrint').textContent = printed.length; $('#cBun').textContent = bundles.length;
    const years = [...new Set(pdfs.map(f => f.year))].sort((a, b) => b - a);
    const S = ADM.table('#pdfTbl', {
      rows: () => pdfs, id: f => f.id, size: 8, csvName: 'my-downloads', placeholder: 'Search your files…', empty: 'No files yet. PDFs appear here once your payment is confirmed.', sort: 'date', dir: -1, search: f => [f.title, f.subject, f.name, f.order, f.year].join(' '),
      filters: [{ k: 'l', label: 'Level', all: 'All levels', options: [['PLE', 'PLE'], ['UCE', 'UCE'], ['UACE', 'UACE']], fn: (f, v) => f.level === v }, { k: 'y', label: 'Year', all: 'Any year', options: years.map(y => [y, y]), fn: (f, v) => String(f.year) === v }, { k: 'k', label: 'Type', all: 'All files', options: [['paper', 'Past papers'], ['guide', 'Marking guides and extras']], fn: (f, v) => f.kind === v }],
      cols: [{ k: 'f', label: 'File', sort: f => f.title, html: f => `<div class="file-row" style="padding:0;border:0">${fileIc(f)}<div class="t"><b>${esc(f.title)}</b><small>${esc(f.name)} · ${sizeTxt(f)}</small></div></div>`, csv: f => f.name }, { k: 'l', label: 'Level', html: f => `<span class="badge nodot b-info">${esc(f.level)}</span>${f.year ? ' <span class="muted sm">' + f.year + '</span>' : ''}`, csv: f => f.level }, { k: 'date', label: 'Bought', sort: f => f.ts, html: f => `${AD.fmtDate(f.ts)}<div class="xs"><a href="${esc(AD.url.order(f.order))}">${esc(f.order)}</a></div>`, csv: f => AD.fmtDate(f.ts) }, { k: 'd', label: 'Downloads', sort: f => f.used, html: f => meter(f), csv: f => f.used + '/' + f.max }],
      actions: f => `<div class="row-actions"><button class="btn btn-primary btn-sm" type="button" data-act="download">${ic('download')}Download</button><button class="btn btn-ic" type="button" data-act="email" title="Email me this file" aria-label="Email me this file">${ic('mail')}</button></div>`,
      onAct: (a, f) => {
        if (a === 'download') { download(f, () => S.render()); return true; }
        if (a === 'email') { if (f.used >= f.max) { toast('You have used all ' + f.max + ' downloads for this file. Ask us for more from Help and support.', 'err'); return true; } api(U.file(R.email, f.id), 'POST').then(r => { if (r.ok) { f.used = r.data.used; f.max = r.data.max; toast(esc(r.data.message)); S.render(); } else toast(esc(errMsg(r)), 'err'); }); return true; }
        return false;
      }
    });
    $('#dlAll').addEventListener('click', async () => { const l = S.rows().filter(f => f.used < f.max); if (!l.length) { toast('Nothing to download.', 'err'); return; } for (const f of l) { const ok = await download(f); if (!ok) break; await new Promise(r => setTimeout(r, 400)); } S.render(); });
    ADM.table('#prTbl', { rows: () => printed, id: x => x.id, size: 8, csvName: 'my-printed-items', placeholder: 'Search printed items…', search: x => x.i.title + x.o.id, cols: [{ k: 'i', label: 'Item', html: x => `<div class="cell-prod">${AD.thumbById(x.i.type, x.i.id)}<div><b>${esc(x.i.title)}</b><small>${AD.TYPE_LABEL[x.i.type]} · ${esc(x.i.level)}</small></div></div>`, csv: x => x.i.title }, { k: 'q', label: 'Qty', num: true, html: x => x.i.qty }, { k: 'o', label: 'Order', html: x => `<a href="${esc(AD.url.order(x.o.id))}">${esc(x.o.id)}</a><div class="xs muted">${AD.fmtDate(x.o.ts)}</div>`, csv: x => x.o.id }, { k: 's', label: 'Status', html: x => sBadge(x.o.status), csv: x => OS[x.o.status].label }], actions: x => `<div class="row-actions"><button class="btn btn-line btn-sm" type="button" data-act="again">${ic('refresh')}Buy again</button></div>`, onAct: (a, x) => { if (a === 'again') { itemToCart(x.o, x.i); return true; } return false; } });
    $('#bundles').innerHTML = bundles.length ? bundles.map(({ o, i }) => { const r = AD.recOf(i.type, i.id), gf = AD.files.find(f => f.order === o.id && f.kind === 'guide' && f.key.indexOf('i' + i.iid + '-') === 0); return `<article class="card"><div class="card-b"><div class="flex" style="align-items:flex-start;gap:1rem">${AD.thumbById(i.type, i.id)}<div style="flex:1;min-width:0"><span class="badge nodot b-info">${i.type === 'set' ? 'Exam set' : 'Holiday package'}</span><h3 style="margin:.4rem 0 .2rem;font-size:1rem">${esc(i.title)}</h3><div class="sm muted">${AD.fmtDate(o.ts)} · <a href="${esc(AD.url.order(o.id))}">${esc(o.id)}</a> · ${sBadge(o.status)}</div>${r ? (i.type === 'pack' ? `<ul class="sm" style="margin:.7rem 0 0;display:grid;gap:.25rem">${(r.includes || []).map(x => `<li>✓ ${esc(x)}</li>`).join('')}</ul>` : `<p class="sm muted" style="margin:.6rem 0 0">${r.papers} papers across ${(r.subjects || []).length} subjects.</p>`) : ''}</div></div></div><div class="card-f"><span class="sm muted">${gf ? 'Marking guides included' : 'Printed bundle'}</span><div class="flex">${gf ? `<button class="btn btn-primary btn-sm" type="button" data-gf="${esc(gf.id)}">${ic('download')}${i.type === 'set' ? 'Marking guides' : 'Timetable'}</button>` : ''}<button class="btn btn-line btn-sm" type="button" data-again="${esc(o.id)}:${i.iid}">${ic('refresh')}Buy again</button></div></div></article>`; }).join('') : emptyState('layers', 'No sets or packages yet', 'Exam sets and holiday packages you buy will be listed here.', `<a class="btn btn-primary" href="${esc(R.sets)}">See exam sets</a>`);
    $('#bundles').addEventListener('click', e => { const g = e.target.closest('[data-gf]'); if (g) { download(AD.files.find(f => f.id === g.dataset.gf), () => S.render()); return; } const a = e.target.closest('[data-again]'); if (a) { const [oid, iid] = a.dataset.again.split(':'), o = AD.orders.find(x => x.id === oid); itemToCart(o, o.items.find(i => String(i.iid) === iid)); } });
  };

  /* ================= ORDERS ================= */
  P.orders = function () {
    const prog = o => ['pending', 'confirmed', 'processing', 'ready', 'shipped'].includes(o.status);
    ADM.table('#tbl', {
      rows: () => AD.orders, id: o => o.id, size: 8, csvName: 'my-orders', placeholder: 'Search by order number or item…', empty: 'No orders yet', sort: 'date', dir: -1,
      tabs: { items: [{ k: 'all', label: 'All orders' }, { k: 'prog', label: 'In progress', fn: prog }, { k: 'done', label: 'Completed', fn: o => o.status === 'completed' }, { k: 'can', label: 'Cancelled', fn: o => o.status === 'cancelled' || o.status === 'refunded' }] },
      search: o => o.id + ' ' + o.items.map(i => i.title).join(' '),
      filters: [{ k: 'f', label: 'Format', all: 'All formats', options: [['pdf', 'Includes PDF'], ['print', 'Includes printed']], fn: (o, v) => o.items.some(i => v === 'pdf' ? i.fmt === 'pdf' : i.fmt !== 'pdf') }, { k: 'd', label: 'Delivery', all: 'Any delivery', options: Object.entries(AD.DELIVERY).map(([k, v]) => [k, v.label]), fn: (o, v) => o.delivery === v }],
      cols: [{ k: 'id', label: 'Order', sort: o => o.id, html: o => `<a class="link-row" href="${esc(AD.url.order(o.id))}">${esc(o.id)}</a><div class="xs muted">${AD.fmtDate(o.ts)}</div>`, csv: o => o.id }, { k: 'date', label: 'Date', sort: o => o.ts, html: o => AD.rel(o.ts), csv: o => AD.fmtDate(o.ts) }, { k: 'items', label: 'Items', html: o => `<div class="flex">${AD.thumbById(o.items[0].type, o.items[0].id)}<div><b class="sm">${esc(o.items[0].title)}</b><div class="xs muted">${o.items.length > 1 ? '+ ' + (o.items.length - 1) + ' more · ' : ''}${o.items.reduce((a, i) => a + i.qty, 0)} units</div><div class="flex" style="gap:.3rem;margin-top:.2rem">${[...new Set(o.items.map(i => i.fmt === 'pdf' ? 'pdf' : 'print'))].map(fmtTag).join('')}</div></div></div>`, csv: o => o.items.map(i => i.qty + 'x ' + i.title).join('; ') }, { k: 'total', label: 'Total', num: true, sort: o => o.total, html: o => `<b>${M(o.total)}</b>`, csv: o => o.total }, { k: 'pay', label: 'Payment', html: o => `<div class="xs muted" style="margin-bottom:.2rem">${esc(AD.PAY_METHODS[o.method] || o.method)}</div>${pBadge(o.pay)}`, csv: o => PS[o.pay].label }, { k: 'st', label: 'Status', sort: o => o.status, html: o => sBadge(o.status), csv: o => OS[o.status].label }],
      actions: o => `<div class="row-actions"><a class="btn btn-ic" href="${esc(AD.url.order(o.id))}" aria-label="View order" title="View order">${ic('eye')}</a>${AD.counted(o) ? `<a class="btn btn-ic" href="${esc(AD.url.invoice(o.id))}" aria-label="Invoice" title="Invoice">${ic('print')}</a>` : ''}<button class="btn btn-ic" type="button" data-act="again" title="Buy again" aria-label="Buy again">${ic('refresh')}</button></div>`,
      onAct: (a, o) => { if (a === 'again') { cartAdd(o.items.map(i => row(i.type, i.id, i.fmt, i.qty > 20 ? 1 : i.qty)), 'All items added to your cart.'); return true; } return false; },
      rowHref: o => AD.url.order(o.id)
    });
    /* Find an order placed without an account: we email a code to the address used at checkout */
    const num = $('#claimNo'), code = $('#claimCode'), s1 = $('#claimStep1'), s2 = $('#claimStep2'), msg = $('#claimMsg');
    if (!num) return;
    const say = (t, bad) => { msg.textContent = t; msg.className = 'sm ' + (bad ? '' : 'muted'); msg.style.color = bad ? 'var(--pen)' : ''; };
    $('#claimSend').addEventListener('click', async () => { const v = num.value.trim(); if (!v) { say('Enter the order number, for example WK-10471.', true); return; } const r = await api(R.claim, 'POST', { number: v }); if (!r.ok) { say(errMsg(r), true); return; } if (r.data.done) { say(r.data.message); return; } s2.hidden = false; say(r.data.message); code.focus(); });
    $('#claimVerify').addEventListener('click', async () => { const r = await api(R.claimVerify, 'POST', { number: num.value.trim(), code: code.value.trim() }); if (!r.ok) { say(errMsg(r), true); return; } say(r.data.message); setTimeout(() => location.reload(), 1200); });
  };

  /* ================= ORDER DETAIL ================= */
  P.order = function () {
    const id = AD.P.focus, o = AD.orders.find(x => x.id === id);
    if (!o) { $('#orderRoot').innerHTML = emptyState('box', 'Order not found', 'We could not find that order.', `<a class="btn btn-primary" href="${esc(R.orders)}">Back to my orders</a>`); return; }
    document.title = o.id + ' | My account'; $$('[data-ref]').forEach(n => n.textContent = o.id);
    $('#oMeta').innerHTML = `Placed ${AD.fmtDate(o.ts)} at ${AD.fmtTime(o.ts)} · ${sBadge(o.status)} ${pBadge(o.pay)}`;
    $('#stepper').innerHTML = (o.status === 'cancelled' || o.status === 'refunded') ? `<div class="callout" style="width:100%">${ic('alert')}<div>This order was <b>${OS[o.status].label.toLowerCase()}</b>. ${o.pay === 'failed' ? 'The payment did not go through, so you were not charged.' : ''}</div></div>` : steps(o);
    const files = AD.files.filter(f => f.order === o.id);
    $('#oItems').innerHTML = o.items.map(i => { const f = files.find(x => x.key === 'i' + i.iid); const act = f ? `<button class="btn btn-primary btn-sm" type="button" data-dl="${esc(f.id)}">${ic('download')}Download</button>` : (i.fmt === 'pdf' && !AD.counted(o) && o.status !== 'cancelled' ? '<span class="xs muted">After payment</span>' : `<button class="btn btn-line btn-sm" type="button" data-again="${i.iid}">Buy again</button>`); return `<tr><td><div class="cell-prod">${AD.thumbById(i.type, i.id)}<div><b>${esc(i.title)}</b><small>${AD.TYPE_LABEL[i.type]} · ${esc(i.level)}</small>${i.fmt ? '<div style="margin-top:.25rem">' + fmtTag(i.fmt) + '</div>' : ''}</div></div></td><td class="num">${M(i.price)}</td><td class="num">${i.qty}</td><td class="num b">${M(i.price * i.qty)}</td><td class="num">${act}</td></tr>`; }).join('');
    $('#oItems').addEventListener('click', e => { const d = e.target.closest('[data-dl]'); if (d) { download(AD.files.find(f => f.id === d.dataset.dl)); return; } const a = e.target.closest('[data-again]'); if (a) itemToCart(o, o.items.find(i => String(i.iid) === a.dataset.again)); });
    $('#oTotals').innerHTML = `<div><span>Subtotal</span><span>${M(o.sub)}</span></div><div><span>Delivery</span><span>${o.fee ? M(o.fee) : 'Free'}</span></div><div class="tot"><span>Total</span><span>${M(o.total)}</span></div>`;
    const extra = files.filter(f => f.kind === 'guide'); if (extra.length) { $('#oExtra').hidden = false; $('#oExtraList').innerHTML = extra.map(f => `<div class="file-row">${fileIc(f)}<div class="t"><b>${esc(f.title)}</b><small>${sizeTxt(f)} · included free</small></div><button class="btn btn-primary btn-sm" type="button" data-dl="${esc(f.id)}">${ic('download')}Download</button></div>`).join(''); $('#oExtraList').addEventListener('click', e => { const d = e.target.closest('[data-dl]'); if (d) download(AD.files.find(f => f.id === d.dataset.dl)); }); }
    const phys = o.items.some(i => i.fmt !== 'pdf'), dl = AD.DELIVERY[o.delivery] || { label: o.delivery, eta: '' };
    $('#oDelivery').innerHTML = phys ? `<dl class="dl"><dt>Method</dt><dd>${esc(dl.label)}</dd><dt>Expected</dt><dd>${esc(dl.eta)}</dd>${o.delivery === 'pickup' ? '<dt>Collect from</dt><dd>WAKATA shop, Bat Valley Primary School</dd>' : `<dt>Deliver to</dt><dd>${esc(o.address || 'Your address')}</dd>`}</dl>` : '<p class="muted mb0">PDF items are emailed to you and added to your library. Nothing to deliver.</p>';
    $('#oPay').innerHTML = `<dl class="dl"><dt>Method</dt><dd>${esc(AD.PAY_METHODS[o.method] || o.method)}</dd><dt>Status</dt><dd>${pBadge(o.pay)}</dd><dt>Amount</dt><dd>${M(o.total)}</dd>${o.txn ? `<dt>Reference</dt><dd>${esc(o.txn)}</dd>` : ''}</dl>${o.pay === 'pending' ? '<p class="xs muted" style="margin:.6rem 0 0">We contact you to complete payment. Your files and order move on once it is confirmed.</p>' : ''}`;
    $('#oActions').innerHTML = (AD.counted(o) ? `<a class="btn btn-line btn-sm" href="${esc(AD.url.invoice(o.id))}">${ic('print')}Invoice</a>` : '') + `<button class="btn btn-line btn-sm" type="button" id="oAgain">${ic('refresh')}Buy again</button><a class="btn btn-primary btn-sm" href="${esc(R.support)}?order=${encodeURIComponent(o.id)}">${ic('help')}Get help</a>`;
    $('#oAgain').addEventListener('click', () => cartAdd(o.items.map(i => row(i.type, i.id, i.fmt, i.qty > 20 ? 1 : i.qty)), 'All items added to your cart.'));
    $('#oTimeline').innerHTML = o.events.slice().reverse().map(t => `<li><b>${esc(t.label)}</b><small>${AD.fmtDate(t.ts)} ${AD.fmtTime(t.ts)}${t.note ? ' · ' + esc(t.note) : ''}</small></li>`).join('');
  };

  /* ================= INVOICES ================= */
  P.invoices = function () {
    const rows = AD.orders.filter(AD.counted);
    $('#invStats').innerHTML = kpi('file', '', 'Invoices', rows.length, 'Available to print') + kpi('wallet', 'g', 'Total invoiced', M(AD.sum(rows, o => o.total)), 'All time') + kpi('calendar', 'y', 'This year', M(AD.sum(rows.filter(o => new Date(o.ts).getFullYear() === new Date().getFullYear()), o => o.total)), 'Calendar year');
    ADM.table('#tbl', { rows: () => rows, id: o => o.id, size: 8, csvName: 'my-invoices', placeholder: 'Search invoices…', empty: 'No invoices yet. An invoice appears once an order is paid.', sort: 'date', dir: -1, search: o => o.id + o.txn, cols: [{ k: 'n', label: 'Invoice', sort: o => o.id, html: o => `<a class="link-row" href="${esc(AD.url.invoice(o.id))}">INV-${esc(o.id.slice(3))}</a>`, csv: o => 'INV-' + o.id.slice(3) }, { k: 'date', label: 'Date', sort: o => o.ts, html: o => AD.fmtDate(o.ts), csv: o => AD.fmtDate(o.ts) }, { k: 'o', label: 'Order', html: o => `<a href="${esc(AD.url.order(o.id))}">${esc(o.id)}</a>` }, { k: 'm', label: 'Paid with', html: o => esc(AD.PAY_METHODS[o.method] || o.method), csv: o => AD.PAY_METHODS[o.method] }, { k: 'a', label: 'Amount', num: true, sort: o => o.total, html: o => `<b>${M(o.total)}</b>`, csv: o => o.total }, { k: 's', label: 'Status', html: () => '<span class="badge b-ok">Paid</span>' }], actions: o => `<div class="row-actions"><a class="btn btn-line btn-sm" href="${esc(AD.url.invoice(o.id))}">${ic('eye')}View</a><a class="btn btn-ic" href="${esc(AD.url.invoice(o.id, true))}" aria-label="Print" title="Print">${ic('print')}</a></div>`, rowHref: o => AD.url.invoice(o.id) });
  };
  P.invoice = function () {
    const o = AD.orders.find(x => x.id === AD.P.focus), b = AD.P.billing || {}; if (!o) { $('#invRoot').innerHTML = emptyState('file', 'No invoice', 'This account has no paid orders yet.'); return; }
    $('#invRoot').innerHTML = `<div class="invoice" id="inv"><div class="ih"><div class="flex"><img src="${esc(WK.u('assets/img/logo.png'))}" alt=""><div><h2 style="margin:0">WAKATA Stationers</h2><div class="sm" style="color:#55648A">Wakiso Kampala Teachers' Association<br>Bat Valley Primary School, Kampala, Uganda<br>info@wakatastationers.com · +256 700 000 000</div></div></div><div style="text-align:right"><h1 style="margin:0">INVOICE</h1><div class="sm">INV-${esc(o.id.slice(3))}<br>Date: ${AD.fmtDate(o.ts)}<br>Order: ${esc(o.id)}</div><div style="margin-top:.6rem"><span class="paidmark">PAID</span></div></div></div>
<div class="row2" style="margin-bottom:1.2rem"><div><h4 style="margin-bottom:.2rem">Billed to</h4><div class="sm"><b>${esc(b.name || b.person)}</b>${b.name && b.person && b.name !== b.person ? '<br>Attn: ' + esc(b.person) : ''}${b.address ? '<br>' + esc(b.address) : ''}<br>${esc(b.email)}${b.phone ? '<br>' + esc(b.phone) : ''}</div></div><div><h4 style="margin-bottom:.2rem">Payment</h4><div class="sm">${esc(AD.PAY_METHODS[o.method] || o.method)}<br>${o.txn ? 'Reference: ' + esc(o.txn) + '<br>' : ''}Delivery: ${esc((AD.DELIVERY[o.delivery] || {}).label || o.delivery)}</div></div></div>
<table><thead><tr><th>Item</th><th class="r">Price</th><th class="r">Qty</th><th class="r">Amount</th></tr></thead><tbody>${o.items.map(i => `<tr><td>${esc(i.title)}<div class="sm" style="color:#55648A">${AD.TYPE_LABEL[i.type]}${i.fmt ? ' · ' + (i.fmt === 'pdf' ? 'PDF download' : 'Printed copy') : ''}</div></td><td class="r">${M(i.price)}</td><td class="r">${i.qty}</td><td class="r">${M(i.price * i.qty)}</td></tr>`).join('')}</tbody></table>
<div class="totals" style="margin:1rem 0 1.4rem"><div><span>Subtotal</span><span>${M(o.sub)}</span></div><div><span>Delivery</span><span>${o.fee ? M(o.fee) : 'Free'}</span></div><div class="tot"><span>Total paid</span><span>${M(o.total)}</span></div></div>
<div class="flex-b" style="align-items:flex-end">${b.stamp ? '<div class="stamp">School stamp</div>' : '<div></div>'}<div class="sm" style="color:#55648A;text-align:right;max-width:34ch">Thank you for supporting teachers.<br>Affordable quality assessment.</div></div></div>`;
    if (ADM.qs('print')) setTimeout(() => window.print(), 400);
  };

  /* ================= SAVED ITEMS ================= */
  P.saved = function () {
    const list = AD.saved;
    function info(x) { const r = AD.recOf(x.type, x.ref); if (!r) return null; if (x.type === 'book') return { t: r.title, sub: 'Book · ' + LVL[r.level], price: r.price }; if (x.type === 'paper') return { t: r.title + ' ' + r.year, sub: 'Past paper · ' + AD.LV[r.level], price: x.fmt === 'pdf' ? r.pdf : r.print }; if (x.type === 'set') return { t: r.title, sub: 'Exam set · ' + r.papers + ' papers', price: r.price }; return { t: r.name, sub: 'Holiday package', price: r.price }; }
    const rows = () => list.map(x => ({ x, d: info(x) })).filter(r => r.d);
    function render() {
      const rs = rows(); $('#savedCount').textContent = rs.length;
      $('#grid').innerHTML = rs.length ? rs.map(({ x, d }) => `<article class="pcard"><div class="pm">${AD.thumbById(x.type, x.ref)}</div><div class="pb"><span class="xs muted">${esc(d.sub)}</span><h3>${esc(d.t)}</h3>${x.type === 'paper' ? `<div class="seg" data-seg-fmt="${x.id}" style="align-self:flex-start"><button type="button" data-v="print" class="${x.fmt !== 'pdf' ? 'on' : ''}">Printed</button><button type="button" data-v="pdf" class="${x.fmt === 'pdf' ? 'on' : ''}">PDF</button></div>` : ''}<div class="pf"><b>${M(d.price)}</b><div class="row-actions"><button class="btn btn-primary btn-sm" type="button" data-add="${x.id}">${ic('cart')}Add</button><button class="btn btn-ic" type="button" data-rm="${x.id}" aria-label="Remove" title="Remove">${ic('trash')}</button></div></div></div></article>`).join('') : `<div style="grid-column:1/-1" class="card">${emptyState('heart', 'Nothing saved yet', 'Tap the heart on any book, paper, set or package in the shop to keep it here for later.', `<a class="btn btn-primary" href="${esc(R.books)}">Browse books</a>`)}</div>`;
    }
    render();
    const find = id => list.find(x => String(x.id) === String(id)), toRow = x => row(x.type, x.ref, x.type === 'paper' ? (x.fmt || 'print') : null, 1);
    $('#grid').addEventListener('click', async e => {
      const a = e.target.closest('[data-add]'), r = e.target.closest('[data-rm]'), s = e.target.closest('[data-seg-fmt] button');
      if (a) { const x = find(a.dataset.add); if (x) cartAdd([toRow(x)]); }
      else if (r) { const res = await api(R.saved + '/' + r.dataset.rm, 'DELETE'); if (res.ok) { swap(list, res.data.saved); render(); toast('Removed from saved items.'); } else toast(esc(errMsg(res)), 'err'); }
      else if (s) { const res = await api(R.saved + '/' + s.parentNode.dataset.segFmt, 'PATCH', { fmt: s.dataset.v }); if (res.ok) { swap(list, res.data.saved); render(); } else toast(esc(errMsg(res)), 'err'); }
    });
    $('#addAll').addEventListener('click', () => { const rs = rows(); if (!rs.length) return; cartAdd(rs.map(r => toRow(r.x)), 'All saved items added to your cart.'); });
  };

  /* ================= ADDRESSES ================= */
  P.addresses = function () {
    const list = AD.addresses; let editing = null;
    const render = () => { $('#addrGrid').innerHTML = list.length ? list.map(a => `<div class="addr ${a.def ? 'def' : ''}"><div class="flex-b"><span class="lab">${esc(a.label)}</span>${a.def ? '<span class="badge b-ok">Default</span>' : ''}</div><b>${esc(a.name)}</b><div class="sm muted" style="margin:.2rem 0 .8rem">${esc(a.line)}<br>${esc(a.district)}${a.district && a.phone ? ' · ' : ''}${esc(a.phone)}</div><div class="flex wrapf"><button class="btn btn-line btn-sm" type="button" data-edit="${a.id}">${ic('edit')}Edit</button>${a.def ? '' : `<button class="btn btn-line btn-sm" type="button" data-def="${a.id}">Make default</button>`}<button class="btn btn-danger-l btn-sm" type="button" data-del="${a.id}" aria-label="Delete address">${ic('trash')}</button></div></div>`).join('') + `<button class="addr" type="button" data-new style="border-style:dashed;display:grid;place-items:center;color:var(--blue);font-weight:700;min-height:150px;cursor:pointer;background:var(--surface-2)"><span>${ic('plus')} Add an address</span></button>` : `<div style="grid-column:1/-1" class="card">${emptyState('map', 'No saved addresses', 'Add an address to check out faster when you want delivery.', `<button class="btn btn-primary" type="button" data-new>${ic('plus')}Add an address</button>`)}</div>`; };
    render();
    const F = { al: 'label', an: 'name', aa: 'line', ad: 'district', ap: 'phone' };
    const openForm = a => { editing = a || null; $('#addrModalT').textContent = a ? 'Edit address' : 'Delivery address'; Object.entries(F).forEach(([id, k]) => { const el = $('#' + id); el.value = a ? a[k] : (k === 'name' ? (user.name || '') : k === 'phone' ? (user.phone || '') : ''); el.closest('.field').classList.remove('bad'); }); $('#adf').checked = a ? a.def : !list.length; ADM.open('addrModal'); };
    $('#addrGrid').addEventListener('click', async e => {
      const n = e.target.closest('[data-new]'), ed = e.target.closest('[data-edit]'), d = e.target.closest('[data-def]'), x = e.target.closest('[data-del]');
      if (n) openForm(null);
      else if (ed) openForm(list.find(a => String(a.id) === ed.dataset.edit));
      else if (d) { const r = await api(R.addresses + '/' + d.dataset.def + '/default', 'POST'); if (r.ok) { swap(list, r.data.addresses); render(); toast(esc(r.data.message)); } else toast(esc(errMsg(r)), 'err'); }
      else if (x) ADM.confirm({ title: 'Delete this address?', text: 'You can add it again later.', onOk: async () => { const r = await api(R.addresses + '/' + x.dataset.del, 'DELETE'); if (r.ok) { swap(list, r.data.addresses); render(); toast(esc(r.data.message)); } else toast(esc(errMsg(r)), 'err'); } });
    });
    $$('[data-open="addrModal"]').forEach(b => b.addEventListener('click', () => { editing = null; $('#addrModalT').textContent = 'Delivery address'; Object.entries(F).forEach(([id, k]) => { $('#' + id).value = k === 'name' ? (user.name || '') : k === 'phone' ? (user.phone || '') : ''; $('#' + id).closest('.field').classList.remove('bad'); }); $('#adf').checked = !list.length; }));
    $('#addrSave').addEventListener('click', async () => {
      let ok = true; ['an', 'aa'].forEach(id => { const el = $('#' + id), bad = !el.value.trim(); el.closest('.field').classList.toggle('bad', bad); if (bad) ok = false; });
      if (!ok) { toast('Please fix the highlighted fields.', 'err'); return; }
      const body = { label: $('#al').value, recipient: $('#an').value, line: $('#aa').value, district: $('#ad').value, phone: $('#ap').value, is_default: $('#adf').checked };
      const r = editing ? await api(R.addresses + '/' + editing.id, 'PUT', body) : await api(R.addresses, 'POST', body);
      if (r.ok) { swap(list, r.data.addresses); render(); ADM.close($('#addrModal')); toast(esc(r.data.message)); } else toast(esc(errMsg(r)), 'err');
    });
    const pd = $('#pd'), pi = $('#pi'), pr = AD.P.prefs || {}; if (pd && pr.delivery_method) pd.value = pr.delivery_method; if (pi) pi.value = pr.delivery_notes || '';
    window.ADM_HOOKS = Object.assign(window.ADM_HOOKS || {}, { savePrefs: async () => { const r = await api(R.addressPrefs, 'PUT', { delivery_method: pd.value, delivery_notes: pi.value }); toast(esc(r.ok ? r.data.message : errMsg(r)), r.ok ? undefined : 'err'); } });
  };

  /* ================= SUPPORT ================= */
  P.support = function () {
    const th = AD.messages; let cur = th[0] || null; const topicCls = { 'Order or delivery': 'b-info', 'Bulk or school quotation': 'b-hl', 'Mock examinations': 'b-pur', 'Something else': 'b-mute' }, stCls = { new: 'b-bad', answered: 'b-ok', open: 'b-warn', closed: 'b-mute' }, stLab = { new: 'New reply', answered: 'Answered', open: 'Waiting for reply', closed: 'Closed' };
    function list() { $('#threads').innerHTML = th.map(t => `<div class="thread ${t.status === 'new' ? 'unread' : ''} ${cur && cur.id === t.id ? 'on' : ''}" data-id="${esc(t.id)}"><div><b>${esc(t.subject)}</b><p>${esc(t.msgs[t.msgs.length - 1].text)}</p><span class="badge nodot ${stCls[t.status]}" style="margin-top:.3rem">${stLab[t.status]}</span> <span class="xs muted">${AD.rel(t.ts)}</span></div></div>`).join('') || `<div class="empty-state">${emptyState('mail', 'No conversations', 'Send us a message and our replies will appear here.')}</div>`; }
    function read(t) {
      cur = t;
      if (t && t.status === 'new') { t.status = 'answered'; api(R.support + '/' + encodeURIComponent(t.id) + '/read', 'POST').then(() => { const b = $$('[data-badge="messages"]')[0]; }); }
      $('#reader').innerHTML = t ? `<div class="flex-b wrapf" style="margin-bottom:1rem"><div><span class="badge nodot ${topicCls[t.topic] || 'b-mute'}">${esc(t.topic)}</span><h2 style="margin:.4rem 0 0">${esc(t.subject)}</h2>${t.order ? `<span class="sm muted">About order <a href="${esc(AD.url.order(t.order))}">${esc(t.order)}</a></span>` : ''}</div></div><div class="convo">${t.msgs.map(m => `<div class="bub ${m.from === 'you' ? 'you' : 'them'}">${esc(m.text)}<small>${m.from === 'you' ? 'You' : 'WAKATA'} · ${AD.fmtDate(m.ts)} ${AD.fmtTime(m.ts)}</small></div>`).join('')}</div><div class="field"><label for="reply">Reply</label><textarea id="reply" placeholder="Write your reply…" style="min-height:100px"></textarea></div><div class="tr"><button class="btn btn-primary" type="button" id="send">${ic('send')}Send reply</button></div>` : '<div class="muted">Select a conversation.</div>'; list();
    }
    const showStart = () => { $('#reader').innerHTML = emptyState('mail', 'Start a conversation', 'Ask about an order, a school quotation or the mock series.', `<button class="btn btn-primary" type="button" data-open="newMsg">${ic('plus')}New message</button>`); };
    list(); if (cur) read(cur); else showStart();
    const apply = (d) => { swap(th, d.messages); cur = th.find(t => t.id === d.focus) || th[0] || null; if (cur) read(cur); else { list(); showStart(); } };
    $('#threads').addEventListener('click', e => { const x = e.target.closest('.thread'); if (x) read(th.find(t => t.id === x.dataset.id)); });
    $('#reader').addEventListener('click', async e => { const b = e.target.closest('#send'); if (!b) return; const v = $('#reply').value.trim(); if (!v) { toast('Write a reply first.', 'err'); return; } b.disabled = true; const r = await api(R.support + '/' + encodeURIComponent(cur.id) + '/reply', 'POST', { body: v }); b.disabled = false; if (r.ok) { toast(esc(r.data.message)); apply(r.data); } else toast(esc(errMsg(r)), 'err'); });
    $('#nmSend').addEventListener('click', async () => {
      const subj = $('#nmSubject'), body = $('#nmBody'); let ok = true; [subj, body].forEach(el => { const bad = !el.value.trim(); el.closest('.field').classList.toggle('bad', bad); if (bad) ok = false; });
      if (!ok) { toast('Please fix the highlighted fields.', 'err'); return; }
      $('#nmSend').disabled = true; const r = await api(R.support, 'POST', { topic: $('#nmTopic').value, order: $('#nmOrder').value, subject: subj.value, body: body.value }); $('#nmSend').disabled = false;
      if (r.ok) { ADM.close($('#newMsg')); subj.value = ''; body.value = ''; $('#nmOrder').value = ''; toast(esc(r.data.message)); apply(r.data); } else toast(esc(errMsg(r)), 'err');
    });
    const o = ADM.qs('order'); if (o) { $('#nmOrder').value = o; $('#nmSubject').value = 'Question about order ' + o; ADM.open('newMsg'); }
    const fq = $('#faqAcc'); if (fq) fq.innerHTML = ADC.faqs.map(f => `<details><summary>${esc(f.q)}</summary><p>${esc(f.a)}</p></details>`).join('');
  };

  /* ================= PROFILE (forms are real Laravel forms) ================= */
  P.profile = function () {
    const email = $('#pe'), box = $('#emailPwBox');
    if (email && box) { const orig = email.value; email.addEventListener('input', () => { box.hidden = email.value.trim().toLowerCase() === orig.toLowerCase(); }); }
    /* switches that need the password open a small dialog */
    const tfa = $('#tfa'); if (tfa) tfa.addEventListener('change', () => { $('#tfaEnabled').value = tfa.checked ? '1' : '0'; $('#tfaTitle').textContent = tfa.checked ? 'Turn on two-step verification?' : 'Turn off two-step verification?'; ADM.open('tfaModal'); });
    const tm = $('#tfaModal'); if (tm) tm.addEventListener('click', e => { if (e.target.closest('[data-close]') || e.target === tm) tfa.checked = $('#tfaEnabled').value !== '1'; });
    /* after a server error, open the tab the form belongs to */
    setTimeout(() => { const bad = document.querySelector('.tabpane .field.bad'); if (bad) { const pane = bad.closest('.tabpane'), t = $('[data-pane="' + pane.id + '"]'); if (t) t.click(); } }, 60);
  };

  if (P[page]) P[page]();

  /* ================= TWO-STEP CODE SCREEN ================= */
  P.verify = function () {
    const ins = $$('.otp input'); if (!ins.length) return; ins[0].focus();
    ins.forEach((i, n) => { i.addEventListener('input', () => { i.value = i.value.replace(/\D/g, '').slice(0, 1); if (i.value && ins[n + 1]) ins[n + 1].focus(); }); i.addEventListener('keydown', e => { if (e.key === 'Backspace' && !i.value && ins[n - 1]) ins[n - 1].focus(); }); i.addEventListener('paste', e => { const d = (e.clipboardData.getData('text') || '').replace(/\D/g, '').slice(0, 6); if (!d) return; e.preventDefault(); d.split('').forEach((c, k) => ins[k] && (ins[k].value = c)); ins[Math.min(d.length, 5)].focus(); }); });
    const form = $('#otpForm');
    form.addEventListener('submit', e => { e.preventDefault(); const code = ins.map(i => i.value).join(''); if (code.length < 6) { toast('Enter the 6-digit code.', 'err'); return; } $('#otpCode').value = code; form.submit(); });
    $('#resend').addEventListener('click', e => { e.preventDefault(); api(e.currentTarget.dataset.url, 'POST').then(r => toast(esc((r.data && r.data.message) || 'Done.'), r.ok ? undefined : 'err')); });
  };
  if (page === 'verify') P.verify();
})();
