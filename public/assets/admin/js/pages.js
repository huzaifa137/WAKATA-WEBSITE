/* ==========================================================
   WAKATA Admin: page logic (tables, charts, inbox, kanban…)
   Each P.<page> runs on the page whose <body data-page="..."> matches.
   ========================================================== */
(function () {
  'use strict';
  if (!window.ADM) return;
  const { $, $$, ic, esc, toast, AD, WK, ADC } = ADM, M = WK.money, C = WKC, P = {};
  const page = document.body.dataset.page;
  const OS = AD.ORDER_STATUS, PS = AD.PAY_STATUS, DAY = AD.DAY, NOW = AD.NOW;
  const sBadge = s => `<span class="badge ${OS[s].cls}">${OS[s].label}</span>`, pBadge = s => `<span class="badge ${PS[s].cls}">${PS[s].label}</span>`;
  const fmtTag = f => f === 'pdf' ? '<span class="fmt-tag pdf">PDF</span>' : '<span class="fmt-tag print">Printed</span>';
  const av = (name, i) => `<span class="avatar sm av-${(i != null ? i : name.length) % 6 + 1}">${AD.initials(name)}</span>`;
  const colors = ['var(--c1)', 'var(--c2)', 'var(--c3)', 'var(--c4)', 'var(--c5)', 'var(--c6)', 'var(--c7)'];
  const LV = Object.assign({ ple: 'PLE', uce: 'UCE', uace: 'UACE', primary: 'PLE', o: 'UCE', a: 'UACE' }, WK.adm ? Object.fromEntries(WK.adm.levels.flatMap(l => [[l.code, l.short], [l.book, l.short]])) : {});
  const R = (WK.adm && WK.adm.routes) || {}, enc = encodeURIComponent;
  const E = WK.eng || null, ER = (E && E.routes) || {};
  const lvBadge = l => `<span class="badge nodot b-info">${LV[l] || l}</span>`;
  const sold = (type, id) => WK.adm ? ((WK.adm.sold[type] || {})[id] || 0) : AD.sum(AD.orders.filter(AD.counted), o => AD.sum(o.items.filter(i => i.type === type && i.id === id), i => i.qty));
  const units = (os, pred) => AD.sum(os, o => AD.sum(o.items.filter(pred), i => i.qty));
  const delta = (cur, prev) => { if (!prev) return '<span class="delta flat">new</span>'; const d = (cur - prev) / prev * 100, up = d >= 0; return `<span class="delta ${Math.abs(d) < .5 ? 'flat' : up ? 'up' : 'down'}">${up ? '▲' : '▼'} ${Math.abs(d).toFixed(1)}%</span>`; };
  const kpi = (icn, tone, label, val, dl, sp, id) => `<div class="card kpi"><div class="kpi-top"><span class="kpi-ic ${tone}">${ic(icn)}</span>${dl}</div><div class="kpi-l">${label}</div><div class="kpi-v">${val}</div><div class="kpi-f"><span class="xs muted">${sp ? 'vs prior period' : ''}</span><span class="spark" id="${id}"></span></div></div>`;
  const stockCls = (s, low) => s <= 0 ? 'out' : s <= (low || 20) ? 'low' : '';
  const stockH = (s, low) => `<span class="stock ${stockCls(s, low)}"><i></i>${s <= 0 ? 'Out of stock' : s}</span>`;
  const stBadgeSimple = s => s === 'published' ? '<span class="badge b-ok">Published</span>' : '<span class="badge b-mute">Draft</span>';
  const pickRows = (arr, n) => arr.slice(0, n);

  /* ================= DASHBOARD ================= */
  P.dashboard = function () {
    let days = 30;
    const hr = new Date().getHours(); $('[data-greet]').textContent = hr < 12 ? 'Good morning' : hr < 17 ? 'Good afternoon' : 'Good evening';
    $('[data-today]').textContent = new Date().toLocaleDateString('en-GB', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' });
    function draw() {
      const cur = AD.within(days), prev = AD.within(days, days), S = AD.series(days), b = S.buckets;
      const rev = AD.sum(cur, o => o.total), prevRev = AD.sum(prev, o => o.total), aov = cur.length ? rev / cur.length : 0, pAov = prev.length ? prevRev / prev.length : 0;
      const newC = AD.customers.filter(c => c.joined >= NOW - days * DAY).length, pNew = AD.customers.filter(c => c.joined >= NOW - 2 * days * DAY && c.joined < NOW - days * DAY).length;
      const pdfU = units(cur, i => i.fmt === 'pdf'), pdfP = units(prev, i => i.fmt === 'pdf'), prU = units(cur, i => i.fmt !== 'pdf' && (i.type === 'book' || i.type === 'paper')), prP = units(prev, i => i.fmt !== 'pdf' && (i.type === 'book' || i.type === 'paper'));
      $('#kpis').innerHTML = kpi('wallet', '', 'Revenue', M(rev), delta(rev, prevRev), 1, 'sp1') + kpi('cart', 'y', 'Orders', cur.length, delta(cur.length, prev.length), 1, 'sp2') + kpi('trend', 'g', 'Average order value', M(Math.round(aov)), delta(aov, pAov), 1, 'sp3') + kpi('users', 'p', 'New customers', newC, delta(newC, pNew), 1, 'sp4') + kpi('book', '', 'Printed copies sold', prU.toLocaleString(), delta(prU, prP), 1, 'sp5') + kpi('download', 'r', 'PDF downloads sold', pdfU.toLocaleString(), delta(pdfU, pdfP), 1, 'sp6');
      C.spark('#sp1', b.map(x => x.rev), 'var(--c1)'); C.spark('#sp2', b.map(x => x.orders), 'var(--c7)'); C.spark('#sp3', b.map(x => x.orders ? x.rev / x.orders : 0), 'var(--c4)'); C.spark('#sp4', b.map(x => AD.customers.filter(c => c.joined >= x.s && c.joined < x.e).length), 'var(--c6)'); C.spark('#sp5', b.map(x => x.print), 'var(--c1)'); C.spark('#sp6', b.map(x => x.pdf), 'var(--c3)');
      C.line('#chRevenue', { labels: b.map(x => x.label), series: [{ name: 'Printed and delivery', data: b.map(x => x.print), color: 'var(--c1)' }, { name: 'PDF downloads', data: b.map(x => x.pdf), color: 'var(--c3)' }], tipFmt: M, height: 290 });
      const cat = AD.byCategory(cur); C.donut('#chCategory', { items: [{ name: 'Books', value: cat.book, color: 'var(--c1)' }, { name: 'Past papers', value: cat.paper, color: 'var(--c3)' }, { name: 'Exam sets', value: cat.set, color: 'var(--c2)' }, { name: 'Holiday packages', value: cat.pack, color: 'var(--c4)' }], centerLabel: 'Product sales', tipFmt: M });
      const st = AD.byStatus(); C.bar('#chStatus', { labels: Object.keys(OS).map(k => OS[k].label), series: [{ name: 'Orders', data: Object.keys(OS).map(k => st[k]), color: 'var(--c1)' }], horizontal: true, height: 270 });
      const lv = AD.byLevel(cur); C.bar('#chLevel', { labels: ['PLE', 'UCE', 'UACE'], series: [{ name: 'Sales', data: [lv.ple, lv.uce, lv.uace], color: 'var(--c1)' }], tipFmt: M, height: 240 });
      const pm = AD.byMethod(cur); C.donut('#chMethod', { items: [{ name: 'MTN Mobile Money', value: pm.mtn, color: 'var(--c2)' }, { name: 'Airtel Money', value: pm.airtel, color: 'var(--c3)' }, { name: 'Visa / Mastercard', value: pm.card, color: 'var(--c1)' }, { name: 'Cash on pickup', value: pm.cash, color: 'var(--c4)' }], centerLabel: 'Collected', tipFmt: M, size: 170 });
      const dv = AD.byDelivery(cur); C.donut('#chDelivery', { items: [{ name: 'Pick up from shop', value: dv.pickup, color: 'var(--c1)' }, { name: 'Kampala and Wakiso', value: dv.kampala, color: 'var(--c5)' }, { name: 'Upcountry courier', value: dv.upcountry, color: 'var(--c7)' }, { name: 'Email (PDF only)', value: dv.email, color: 'var(--c3)' }], centerLabel: 'Orders', fmt: v => v, size: 170 });
      C.bar('#chWeekday', { labels: ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'], series: [{ name: 'Orders', data: AD.byWeekday(AD.within(Math.max(days, 90))), color: 'var(--c5)' }], height: 220, fmt: v => Math.round(v) });
      const bs = AD.bySubject(cur).slice(0, 8); C.bar('#chSubject', { labels: bs.map(x => x[0]), series: [{ name: 'Sales', data: bs.map(x => x[1]), color: 'var(--c6)' }], horizontal: true, tipFmt: M, height: 300 });
      const tp = AD.topProducts(cur, 6), mx = Math.max(1, ...tp.map(x => x.rev));
      $('#topList').innerHTML = tp.map((p, i) => `<li><span class="rk">${i + 1}</span>${AD.thumbById(p.type, p.id)}<div class="nm"><b>${esc(p.title)}</b><small>${AD.TYPE_LABEL[p.type]}${p.fmt ? ' · ' + (p.fmt === 'pdf' ? 'PDF' : 'Printed') : ''} · ${p.units.toLocaleString()} sold</small><div class="progress" style="margin-top:.35rem;height:5px"><i style="width:${p.rev / mx * 100}%"></i></div></div><b class="nowrap sm">${M(p.rev)}</b></li>`).join('');
    }
    draw();
    $('#rangeSeg').addEventListener('seg', e => { days = +e.detail; draw(); const de = $('#dashExport'); if (de) de.href = de.href.replace(/days=\d+/, 'days=' + days); toast('Showing the last ' + (days === 365 ? '12 months' : days + ' days')); });
    /* monthly goal */
    const d0 = new Date(), mStart = +new Date(d0.getFullYear(), d0.getMonth(), 1), goal = (WK.ins && WK.ins.goal) || 40000000, monthRev = AD.sum(AD.orders.filter(o => AD.counted(o) && o.ts >= mStart), o => o.total);
    C.ring('#chGoal', { value: monthRev, max: goal, label: 'of monthly goal', color: 'var(--c4)' }); $('#goalTxt').innerHTML = `<b>${M(monthRev)}</b> of ${M(goal)}`; $('#goalLeft').textContent = M(Math.max(0, goal - monthRev)) + ' to go this month';
    /* printed vs pdf, 12 months */
    const y = AD.series(365).buckets; C.bar('#chFormat', { labels: y.map(b => b.label), series: [{ name: 'Printed and delivery', data: y.map(b => b.print), color: 'var(--c1)' }, { name: 'PDF downloads', data: y.map(b => b.pdf), color: 'var(--c3)' }], stacked: true, tipFmt: M, height: 260 });
    /* attention */
    const A = (WK.ins && WK.ins.attention) || { pending: 0, unpaid: 0, low: 0, messages: 0, noFile: 0 }, RT = (WK.ins && WK.ins.routes) || {};
    const many = (n, one, more) => n + ' ' + (n === 1 ? one : more);
    const items = [['r', 'cart', many(A.pending, 'order is', 'orders are') + ' waiting to be confirmed', 'Confirm or contact the customer', RT.orders, A.pending], ['w', 'card', many(A.unpaid, 'payment is', 'payments are') + ' not yet confirmed', 'Check mobile money statements', RT.payments, A.unpaid], ['w', 'box', many(A.low, 'item is', 'items are') + ' running low on stock', 'Reprint or restock soon', RT.inventory, A.low], ['', 'mail', many(A.messages, 'message is', 'messages are') + ' waiting for an answer', 'Reply within one working day', RT.messages, A.messages], ['r', 'pdf', many(A.noFile, 'past paper has', 'past papers have') + ' no PDF file uploaded', 'PDF buyers cannot download these', RT.papers, A.noFile]].filter(a => a[5] > 0);
    $('#attn').innerHTML = items.length ? items.map(a => `<li class="${a[0]}"><span class="ai">${ic(a[1])}</span><div><b>${a[2]}</b><small>${a[3]}</small></div><a class="btn btn-line btn-sm" href="${a[4]}">Open</a></li>`).join('') : `<li><span class="ai">${ic('check')}</span><div><b>You are all caught up</b><small>Nothing needs your attention right now</small></div></li>`;
    /* recent orders */
    const OURL = AD.sales ? AD.sales.routes.order : 'order.html';
    $('#recent tbody').innerHTML = AD.orders.slice(0, 7).map(o => `<tr class="rowlink" onclick="location.href='${OURL}?id=${o.id}'" style="cursor:pointer"><td><a class="link-row" href="${OURL}?id=${o.id}">${o.id}</a><div class="xs muted">${AD.rel(o.ts)}</div></td><td><div class="flex">${av(o.cust.name)}<span>${esc(o.cust.name)}</span></div></td><td class="num b">${M(o.total)}</td><td>${pBadge(o.pay)}</td><td>${sBadge(o.status)}</td></tr>`).join('');
    const feed = (WK.ins && WK.ins.activity) || [];
    $('#feed').innerHTML = !feed.length ? '<li><div><p class="muted">Nothing has been changed yet.</p></div></li>' : feed.map(a => `<li><span class="fd">${ic(a.icon)}</span><div><p><b>${a.user.name.split(' ')[0]}</b> ${esc(a.verb)} ${a.obj ? '<b>' + esc(a.obj) + '</b>' : ''}</p><small>${AD.rel(a.ts)}</small></div></li>`).join('');
  };

  /* ================= SALES HELPERS (module 8) ================= */
  /* On the sales pages WK.sales holds the real orders and customers (see admin-data.js). Every change goes to Laravel,
     which answers with the fresh order (or customer); AD.sales folds it in and the page draws itself again. */
  const SL = AD.sales, SB = SL ? SL.routes.base : '', CAN = SL ? SL.can : {};
  const orderHref = o => (SL ? SL.routes.order : 'order.html') + '?id=' + encodeURIComponent(o.id);
  const customersHref = c => (SL ? SL.routes.customers : 'customers.html') + '?q=' + encodeURIComponent(c);
  const orderUrl = (o, tail) => SB + '/orders/' + encodeURIComponent(o.id) + tail;
  const send = (url, method, body) => ADM.send(url, method, body).then(d => { if (d.order) AD.sales.upsertOrder(d.order); (d.orders || []).forEach(x => AD.sales.upsertOrder(x)); if (d.customer) AD.sales.upsertCustomer(d.customer); return d; });
  const oops = e => toast(esc(e.message), 'err');
  const lock = (sel, ok, why) => { if (ok) return; $$(sel).forEach(b => { b.disabled = true; b.title = why; }); };
  const NO_SALES = 'Your role can look at sales but not change them.', NO_PAY = 'Only a super admin or manager can handle payments.';
  const itemUnits = o => o.items.reduce((a, i) => a + i.qty, 0);
  const isPhys = o => o.items.some(i => i.fmt !== 'pdf');
  const closed = o => o.status === 'cancelled' || o.status === 'refunded';

  /* ================= ORDERS ================= */
  P.orders = function () {
    const stats = () => {
      const cnt = AD.byStatus(), rev = AD.sum(AD.within(30), o => o.total);
      $('#orderStats').innerHTML = [['Orders (30 days)', AD.within(30).length, 'cart', ''], ['Revenue (30 days)', M(rev), 'wallet', 'g'], ['To confirm', cnt.pending, 'alert', 'y'], ['In progress', cnt.confirmed + cnt.processing + cnt.ready + cnt.shipped, 'truck', 'p'], ['Cancelled / refunded', cnt.cancelled + cnt.refunded, 'x', 'r']].map(s => `<div class="card kpi mini"><div class="flex-b"><div><div class="kpi-l">${s[0]}</div><div class="kpi-v">${s[1]}</div></div><span class="kpi-ic ${s[3]}">${ic(s[2])}</span></div></div>`).join('');
    };
    stats();
    const tabs = [{ k: 'all', label: 'All orders' }, ...Object.keys(OS).map(k => ({ k, label: OS[k].label, fn: o => o.status === k }))];
    const bulk = (ids, action, status) => send(SB + '/orders/bulk', 'POST', { ids, action, status }).then(d => { S.sel.clear(); stats(); S.render(); toast(esc(d.message)); }).catch(oops);
    const S = ADM.table('#tbl', {
      rows: () => AD.orders, id: o => o.id, select: true, size: 10, tabs: { items: tabs, default: 'all' }, placeholder: 'Search order, customer, phone…', csvName: 'wakata-orders', sort: 'date', dir: -1,
      search: o => [o.id, o.cust.name, o.cust.phone, o.cust.email, o.txn].join(' '),
      filters: [{ k: 'm', label: 'Payment method', all: 'All payment methods', options: Object.entries(AD.PAY_METHODS), fn: (o, v) => o.method === v }, { k: 'd', label: 'Delivery', all: 'All delivery options', options: Object.entries(AD.DELIVERY).map(([k, v]) => [k, v.label]), fn: (o, v) => o.delivery === v }, { k: 'p', label: 'Payment status', all: 'Any payment status', options: Object.entries(PS).map(([k, v]) => [k, v.label]), fn: (o, v) => o.pay === v }],
      cols: [
        { k: 'id', label: 'Order', sort: o => o.id, html: o => `<a class="link-row" href="${orderHref(o)}">${o.id}</a>${o.source === 'manual' ? '<div class="xs muted">Phone / walk-in</div>' : ''}`, csv: o => o.id },
        { k: 'date', label: 'Date', sort: o => o.ts, html: o => `${AD.fmtDate(o.ts)}<div class="xs muted">${AD.fmtTime(o.ts)}</div>`, csv: o => AD.fmtDate(o.ts) },
        { k: 'cust', label: 'Customer', sort: o => o.cust.name, html: o => `<div class="flex">${av(o.cust.name)}<div><b>${esc(o.cust.name)}</b><div class="xs muted">${o.cust.type} · ${esc(o.cust.phone)}</div></div></div>`, csv: o => o.cust.name },
        { k: 'items', label: 'Items', sort: o => o.items.length, html: o => `${o.items.length} line${o.items.length > 1 ? 's' : ''}, ${itemUnits(o)} units<div class="flex" style="gap:.3rem;margin-top:.2rem">${[...new Set(o.items.map(i => i.fmt === 'pdf' ? 'pdf' : 'print'))].map(fmtTag).join('')}</div>`, csv: o => o.items.map(i => i.qty + 'x ' + i.title).join('; ') },
        { k: 'total', label: 'Total', num: true, sort: o => o.total, html: o => `<b>${M(o.total)}</b>`, csv: o => o.total },
        { k: 'pay', label: 'Payment', sort: o => o.pay, html: o => `<div class="xs muted" style="margin-bottom:.2rem">${esc(AD.PAY_METHODS[o.method] || o.method)}</div>${pBadge(o.pay)}`, csv: o => (AD.PAY_METHODS[o.method] || o.method) + ' / ' + PS[o.pay].label },
        { k: 'del', label: 'Delivery', html: o => esc((AD.DELIVERY[o.delivery] || {}).label || o.delivery), csv: o => (AD.DELIVERY[o.delivery] || {}).label || o.delivery },
        { k: 'status', label: 'Status', sort: o => o.status, html: o => sBadge(o.status), csv: o => OS[o.status].label }],
      actions: o => `<div class="row-actions"><a class="btn btn-ic" href="${orderHref(o)}" title="View order" aria-label="View order">${ic('eye')}</a><button class="btn btn-ic" type="button" data-act="print" title="Print invoice">${ic('print')}</button></div>`,
      bulk: [{ label: 'Mark as confirmed', act: ids => bulk(ids, 'status', 'confirmed') }, { label: 'Mark as processing', act: ids => bulk(ids, 'status', 'processing') }, { label: 'Print packing slips', act: () => window.print() }, { label: 'Email customers', act: ids => bulk(ids, 'email') }],
      rowHref: orderHref
    });

    /* Create an order manually (phone and walk-in) */
    const items = (SL && SL.items) || {};
    const sync = () => {
      const t = $('#nt').value, list = items[t] || [];
      $('#niList').innerHTML = list.map(x => `<option value="${esc(x.label)}"></option>`).join('');
      const paper = t === 'paper'; $('#nf').disabled = !paper; if (!paper) $('#nf').value = 'print';
      const pdfOnly = paper && $('#nf').value === 'pdf';
      $('#nd').closest('.field').hidden = pdfOnly; $('#naField').hidden = pdfOnly || $('#nd').value === 'pickup';
    };
    ['#nt', '#nf', '#nd'].forEach(s => $(s).addEventListener('change', sync)); $('#nt').addEventListener('change', () => { $('#ni').value = ''; sync(); }); sync();
    lock('#newOrderBtn', CAN.sales, NO_SALES);
    $('#newOrderSave').addEventListener('click', () => {
      const t = $('#nt').value, label = $('#ni').value.trim(), it = (items[t] || []).find(x => x.label === label);
      let ok = true; const bad = (sel, b) => { const f = $(sel).closest('.field'); f.classList.toggle('bad', b); if (b && ok) { ok = false; $(sel).focus(); } };
      bad('#nc', !$('#nc').value.trim()); bad('#np', !$('#np').value.trim()); bad('#ni', !it);
      if (!ok) { toast('Please fix the highlighted fields.', 'err'); return; }
      const pdfOnly = t === 'paper' && $('#nf').value === 'pdf', btn = $('#newOrderSave'); btn.disabled = true;
      send(SB + '/orders', 'POST', { name: $('#nc').value.trim(), phone: $('#np').value.trim(), email: $('#ne').value.trim() || null, type: t, item: it.id, fmt: t === 'paper' ? $('#nf').value : null, qty: Number($('#nq').value || 1), delivery: pdfOnly ? null : $('#nd').value, address: $('#na').value.trim() || null, pay: $('#npm').value })
        .then(d => { ADM.close($('#newOrder')); ['#nc', '#np', '#ne', '#ni', '#na'].forEach(s => { $(s).value = ''; }); $('#nq').value = 1; stats(); S.render(); toast(esc(d.message)); })
        .catch(oops).finally(() => { btn.disabled = false; });
    });
  };

  /* ================= ORDER DETAIL ================= */
  P.order = function () {
    const id = ADM.qs('id'), o = AD.orders.find(x => x.id === id);
    if (!o) { $('main').innerHTML = `<div class="pagehead"><div><h1>Order not found</h1><p>There is no order ${esc(id || '')}.</p></div></div><p><a class="btn btn-line" href="${SL.routes.orders}">Back to orders</a></p>`; return; }
    const act = (tail, body, then) => send(orderUrl(o, tail), 'POST', body).then(d => { draw(); toast(esc(d.message)); if (then) then(d); }).catch(e => { toast(esc(e.message), 'err'); });
    function draw() {
      const c = o.cust, phys = isPhys(o), hasPdf = o.items.some(i => i.fmt === 'pdf'), dl = AD.DELIVERY[o.delivery] || { label: o.delivery, eta: '' };
      document.title = o.id + ' | WAKATA Admin'; $('[data-ref]').textContent = o.id; $('[data-ref-crumb]').textContent = o.id;
      $('#oMeta').innerHTML = `Placed ${AD.fmtDate(o.ts)} at ${AD.fmtTime(o.ts)}${o.source === 'manual' ? ' (phone / walk-in)' : ''} · ${sBadge(o.status)} ${pBadge(o.pay)}`;
      const steps = phys ? ['Placed', 'Confirmed', 'Processing', o.delivery === 'pickup' ? 'Ready for pickup' : 'Out for delivery', 'Completed'] : ['Placed', 'Paid', 'PDF in library'];
      const idx = phys ? { pending: 0, confirmed: 1, processing: 2, ready: 3, shipped: 3, completed: 4 }[o.status] : (o.status === 'completed' ? 2 : o.pay === 'paid' ? 1 : 0);
      $('#stepper').innerHTML = closed(o) ? `<div class="callout" style="width:100%">${ic('alert')}<div>This order was <b>${OS[o.status].label.toLowerCase()}</b>. ${o.status === 'refunded' ? 'The payment has been returned to the customer.' : 'No payment was collected.'}</div></div>` : steps.map((s, i) => `<div class="st ${i < idx || (i === idx && o.status === 'completed') ? 'done' : i === idx ? 'cur' : ''}"><i>${i < idx || (i === idx && o.status === 'completed') ? '✓' : i + 1}</i>${s}</div>`).join('');
      $('#oItems').innerHTML = o.items.map(i => `<tr><td><div class="cell-prod">${AD.thumbById(i.type, i.id)}<div><b>${esc(i.title)}</b><small>${AD.TYPE_LABEL[i.type]} · ${LV[i.level] || String(i.level || '').toUpperCase()}${i.subject ? ' · ' + esc(i.subject) : ''}</small>${i.fmt ? '<div style="margin-top:.25rem">' + fmtTag(i.fmt) + '</div>' : ''}</div></div></td><td class="num">${M(i.price)}</td><td class="num">${i.qty}</td><td class="num b">${M(i.price * i.qty)}</td></tr>`).join('');
      $('#oTotals').innerHTML = `<div><span>Subtotal</span><span>${M(o.sub)}</span></div><div><span>Delivery (${esc(dl.label)})</span><span>${o.fee ? M(o.fee) : 'Free'}</span></div><div class="tot"><span>Total</span><span>${M(o.total)}</span></div>`;
      $('#oStatus').innerHTML = Object.keys(OS).map(k => `<option value="${k}"${k === o.status ? ' selected' : ''}>${OS[k].label}</option>`).join('');
      const phone = String(c.phone || '').replace(/\D/g, '');
      $('#oCust').innerHTML = `<div class="flex" style="margin-bottom:.9rem">${av(c.name, 2)}<div><b>${esc(c.name)}</b><div class="xs muted">${c.type}${c.contact ? ' · ' + esc(c.contact) : ''}</div></div></div><dl class="dl"><dt>Phone</dt><dd><a href="tel:${esc(c.phone)}">${esc(c.phone)}</a></dd><dt>Email</dt><dd>${c.email ? `<a href="mailto:${esc(c.email)}">${esc(c.email)}</a>` : '<span class="muted">None</span>'}</dd>${c.district ? `<dt>District</dt><dd>${esc(c.district)}</dd>` : ''}<dt>Orders</dt><dd>${c.orders} so far · ${M(c.spent)}</dd></dl><div class="flex mt-s wrapf">${phone ? `<a class="btn btn-line btn-sm" href="https://wa.me/${phone}" target="_blank" rel="noopener">${ic('whatsapp')}WhatsApp</a>` : ''}<a class="btn btn-line btn-sm" href="${customersHref(c.name)}">${ic('users')}Customer profile</a></div>${o.note ? `<div class="callout mt-s">${ic('alert')}<div><b>Customer note:</b> ${esc(o.note)}</div></div>` : ''}`;
      $('#oDelivery').innerHTML = phys ? `<dl class="dl"><dt>Method</dt><dd>${esc(dl.label)}</dd><dt>Expected</dt><dd>${esc(dl.eta)}</dd>${o.delivery !== 'pickup' ? `<dt>Deliver to</dt><dd>${o.address ? esc(o.address).replace(/\n/g, '<br>') : esc(c.name)}</dd><dt>Courier / rider</dt><dd><input class="inline-edit" id="oCourier" maxlength="255" value="${esc(o.courier)}" placeholder="Add courier or tracking note" style="border-color:var(--line)"></dd>` : '<dt>Collect from</dt><dd>WAKATA shop, Bat Valley Primary School</dd>'}</dl>${o.delivery !== 'pickup' ? `<button class="btn btn-line btn-sm mt-s" type="button" id="oCourierSave">Save delivery note</button>` : ''}` : `<p class="muted mb0">Only PDF items. The files are in the customer's library once the order is paid, so no delivery is needed.</p>`;
      const pending = o.pay === 'pending' && !closed(o), cash = o.method === 'cash';
      $('#oPay').innerHTML = `<dl class="dl"><dt>Method</dt><dd>${esc(AD.PAY_METHODS[o.method] || o.method)}</dd><dt>Status</dt><dd>${pBadge(o.pay)}</dd><dt>Amount</dt><dd>${M(o.total)}</dd>${o.txn ? `<dt>Transaction</dt><dd>${esc(o.txn)} <button class="btn btn-ic" style="width:26px;height:26px" type="button" data-copy="${esc(o.txn)}" aria-label="Copy">${ic('copy')}</button></dd>` : (cash ? '<dt>Collect cash</dt><dd>On pickup at the shop</dd>' : '')}${o.paidTs ? `<dt>Paid</dt><dd>${AD.fmtDate(o.paidTs)} ${AD.fmtTime(o.paidTs)}</dd>` : ''}</dl>${pending && !cash && !o.txn ? `<div class="field mt-s"><label for="oRef">Transaction reference (optional)</label><input id="oRef" maxlength="60" placeholder="e.g. MTN12345678"></div>` : ''}${pending ? `<button class="btn btn-primary btn-sm mt-s" type="button" id="oPaid">${ic('check')}${cash ? 'Mark cash received' : 'Mark payment received'}</button>` : ''}${o.pay === 'paid' && !closed(o) ? `<button class="btn btn-line btn-sm mt-s" type="button" id="oRefund">${ic('refresh')}Refund payment</button>` : ''}`;
      const pdfs = o.items.filter(i => i.fmt === 'pdf');
      $('#oPdf').hidden = !hasPdf;
      if (hasPdf) $('#oPdfList').innerHTML = pdfs.map(i => `<div class="filechip" style="margin-bottom:.5rem"><span class="fi">PDF</span><div><b>${esc(i.title)}</b><small>${o.pay === 'paid' && !closed(o) ? 'In the customer’s library · ' + o.downloads + ' download(s) used' + (o.emailedTs ? ' · emailed ' + AD.fmtDate(o.emailedTs) : '') : 'Waiting for payment'}</small></div></div>`).join('') + (o.pay === 'paid' && !closed(o) ? `<button class="btn btn-line btn-sm" type="button" id="oResend">${ic('send')}Email the files again</button>` : '');
      $('#oNote').value = o.adminNote || '';
      $('#oTimeline').innerHTML = o.events.slice().reverse().map(t => `<li><b>${esc(t.label)}</b><small>${AD.fmtDate(t.ts)} ${AD.fmtTime(t.ts)}${t.note ? ' · ' + esc(t.note) : ''}</small></li>`).join('');
      const sv = $('#oSave'); sv.disabled = !CAN.sales || closed(o); sv.title = closed(o) ? 'This order is closed.' : (CAN.sales ? '' : NO_SALES);
      $('#oStatus').disabled = sv.disabled; $('#oCancel').disabled = !CAN.sales || closed(o);
      lock('#oEmail,#oNoteSave,#oCourierSave,#oCourier', CAN.sales, NO_SALES); lock('#oPaid,#oRefund', CAN.payments, NO_PAY);
      if (!CAN.sales) $('#oNote').readOnly = true;
      if (!o.cust.email) $('#oEmail').disabled = true;
    }
    draw();
    const body = $('main');
    body.addEventListener('click', e => {
      const t = e.target, g = s => t.closest(s);
      if (g('#oSave')) act('/status', { status: $('#oStatus').value, notify: $('#oNotify').checked });
      else if (g('#oCancel')) {
        if (o.pay === 'paid') ADM.confirm({ title: 'Refund and close this order?', text: 'This order is paid, so it cannot just be cancelled. The payment is recorded as refunded and the order is closed.', ok: 'Refund order', icon: 'refresh', onOk: () => send(orderUrl(o, '/payment/refund'), 'POST', {}).then(d => { draw(); toast(esc(d.message)); }).catch(oops) });
        else ADM.confirm({ title: 'Cancel this order?', text: $('#oNotify').checked && o.cust.email ? 'The customer will be emailed.' : 'The customer will not be emailed.', ok: 'Cancel order', onOk: () => act('/status', { status: 'cancelled', notify: $('#oNotify').checked }) });
      }
      else if (g('#oEmail')) act('/email', {});
      else if (g('#oNoteSave')) act('/note', { admin_note: $('#oNote').value });
      else if (g('#oCourierSave')) act('/courier', { courier_note: $('#oCourier').value });
      else if (g('#oPaid')) send(orderUrl(o, '/payment/verify'), 'POST', { reference: ($('#oRef') || {}).value || null }).then(d => { draw(); toast(esc(d.message)); }).catch(oops);
      else if (g('#oRefund')) ADM.confirm({ title: 'Refund ' + M(o.total) + '?', text: 'The payment is recorded as refunded via ' + (AD.PAY_METHODS[o.method] || o.method) + ' and the order is closed. Send the money back yourself first.', ok: 'Refund', icon: 'refresh', onOk: () => send(orderUrl(o, '/payment/refund'), 'POST', {}).then(d => { draw(); toast(esc(d.message)); }).catch(oops) });
      else if (g('#oResend')) act('/files', {});
    });
  };

  /* ================= BOOKS ================= */
  P.books = function () {
    const base = R.base + '/books', formUrl = id => R.bookForm + '?id=' + enc(id);
    const types = [...new Set(WK.books.map(b => b.type))], subs = [...new Set(WK.books.map(b => b.subject))].sort(), badges = [...new Set(WK.books.filter(b => b.badge).map(b => b.badge))];
    const lvlName = Object.fromEntries(WK.adm.levels.map(l => [l.book, l.bookLabel]));
    const meta = b => [b.sku, b.pages ? b.pages + ' pages' : '', b.edition].filter(Boolean).join(' · ');
    const kp = [['Titles', WK.books.length, 'book', ''], ['Published', WK.books.filter(b => b.status === 'published').length, 'check', 'g'], ['Drafts', WK.books.filter(b => b.status !== 'published').length, 'edit', 'y'], ['Low or out of stock', WK.books.filter(b => b.stock <= b.low).length, 'box', 'r']];
    $('#bookStats').innerHTML = kp.map(s => `<div class="card kpi mini"><div class="flex-b"><div><div class="kpi-l">${s[0]}</div><div class="kpi-v">${s[1]}</div></div><span class="kpi-ic ${s[3]}">${ic(s[2])}</span></div></div>`).join('');
    const bulk = (action, extra) => ids => ADM.post(base + '/bulk', Object.assign({ action, 'ids[]': ids }, extra || {}));
    const S = ADM.table('#tbl', {
      rows: () => WK.books, id: b => b.id, select: true, size: 10, csvName: 'wakata-books', placeholder: 'Search title, subject, SKU…', q0: ADM.qs('q') || '',
      tabs: { items: [{ k: 'all', label: 'All books' }, { k: 'pub', label: 'Published', fn: b => b.status === 'published' }, { k: 'draft', label: 'Drafts', fn: b => b.status !== 'published' }, { k: 'low', label: 'Low stock', fn: b => b.stock <= b.low }, { k: 'feat', label: 'Featured on home', fn: b => b.featured }] },
      search: b => [b.title, b.subject, b.sku, b.type, b.edition].join(' '),
      filters: [{ k: 'l', label: 'Level', all: 'All levels', options: WK.adm.levels.map(l => [l.book, l.bookLabel]), fn: (b, v) => b.level === v }, { k: 's', label: 'Subject', all: 'All subjects', options: subs.map(s => [s, s]), fn: (b, v) => b.subject === v }, { k: 't', label: 'Type', all: 'All types', options: types.map(s => [s, s]), fn: (b, v) => b.type === v }, { k: 'b', label: 'Badge', all: 'Any badge', options: [...badges.map(s => [s, s]), ['none', 'No badge']], fn: (b, v) => v === 'none' ? !b.badge : b.badge === v }],
      cols: [
        { k: 'title', label: 'Book', sort: b => b.title, html: b => `<div class="cell-prod">${AD.thumbOf('book', b)}<div><a class="link-row" href="${formUrl(b.id)}">${esc(b.title)}</a><small>${esc(meta(b))}</small>${b.badge ? `<div style="margin-top:.2rem"><span class="badge nodot b-hl">${esc(b.badge)}</span></div>` : ''}</div></div>`, csv: b => b.title },
        { k: 'subject', label: 'Subject', sort: b => b.subject, html: b => esc(b.subject) },
        { k: 'level', label: 'Level', sort: b => b.level, html: b => lvBadge(b.level), csv: b => lvlName[b.level] || b.level },
        { k: 'type', label: 'Type', html: b => esc(b.type) },
        { k: 'price', label: 'Price', num: true, sort: b => b.price, html: b => `<b>${M(b.price)}</b>${b.old ? `<span class="old">${M(b.old)}</span>` : ''}`, csv: b => b.price },
        { k: 'stock', label: 'Stock', sort: b => b.stock, html: b => stockH(b.stock, b.low), csv: b => b.stock },
        { k: 'sold', label: 'Sold', num: true, sort: b => sold('book', b.id), html: b => sold('book', b.id).toLocaleString(), csv: b => sold('book', b.id) },
        { k: 'status', label: 'Status', sort: b => b.status, html: b => stBadgeSimple(b.status), csv: b => b.status }],
      actions: b => `<div class="row-actions"><a class="btn btn-ic" href="${formUrl(b.id)}" title="Edit" aria-label="Edit">${ic('edit')}</a><a class="btn btn-ic" href="${R.siteBook}?id=${enc(b.id)}" target="_blank" rel="noopener" title="View on website" aria-label="View on website">${ic('external')}</a><button class="btn btn-ic" type="button" data-act="duplicate" title="Duplicate">${ic('copy')}</button><button class="btn btn-ic" type="button" data-act="delete" title="Delete">${ic('trash')}</button></div>`,
      onAct: (a, b) => {
        if (a === 'duplicate') { ADM.post(base + '/' + enc(b.id) + '/duplicate'); return true; }
        if (a === 'delete') { ADM.confirm({ title: 'Delete this book?', text: '“' + b.title + '” will be removed from the website. Orders already placed keep their details.', onOk: () => ADM.post(base + '/' + enc(b.id), {}, 'DELETE') }); return true; }
        return false;
      },
      bulk: [{ label: 'Publish', act: bulk('publish') }, { label: 'Move to draft', act: bulk('draft') }, { label: 'Set a badge…', act: () => ADM.open('badgeModal') }, { label: 'Adjust prices…', act: () => ADM.open('priceModal') },
        { label: 'Delete', act: ids => ADM.confirm({ title: 'Delete ' + ids.length + ' book' + (ids.length === 1 ? '' : 's') + '?', text: 'They will be removed from the website. Orders already placed keep their details.', onOk: () => bulk('delete')(ids) }) }],
      after: r => { $('#gridView').innerHTML = r.map(b => `<article class="pcard"><div class="pm">${b.badge ? `<span class="tag badge nodot b-hl">${esc(b.badge)}</span>` : ''}${AD.thumbOf('book', b)}</div><div class="pb"><span class="xs muted">${esc(b.subject)} · ${LV[b.level] || ''}</span><h3><a href="${formUrl(b.id)}" class="link-row">${esc(b.title)}</a></h3>${stockH(b.stock, b.low)}<div class="pf"><b>${M(b.price)}${b.old ? `<span class="old">${M(b.old)}</span>` : ''}</b><a class="btn btn-ic" href="${formUrl(b.id)}" aria-label="Edit">${ic('edit')}</a></div></div></article>`).join('') || '<div class="empty">No books match.</div>'; }
    });
    $('#viewSeg').addEventListener('seg', e => { $('#tbl').hidden = e.detail === 'grid'; $('#gridWrap').hidden = e.detail !== 'grid'; });
    $('#priceApply').addEventListener('click', () => { const ids = [...S.sel]; ADM.close($('#priceModal')); bulk('price', { mode: $('#pm').value, value: $('#pv').value, keepold: $('#priceModal [name=keepold]').checked ? '1' : '' })(ids); });
    $('#badgeApply').addEventListener('click', () => { const ids = [...S.sel]; ADM.close($('#badgeModal')); bulk('badge', { badge: $('#bdg').value })(ids); });
  };

  /* ================= PAST PAPERS ================= */
  const paperFile = p => p.subject.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '') + '-paper' + (String(p.paper).replace(/\D/g, '') || '1') + '-' + p.year + '.pdf';
  P['past-papers'] = function () {
    const base = R.base + '/papers', formUrl = id => R.paperForm + '?id=' + enc(id), missing = p => p.pdfOn && !p.hasFile;
    const subs = [...new Set(WK.papers.map(p => p.subject))].sort(), years = [...new Set(WK.papers.map(p => p.year))].sort((a, b) => b - a);
    $$('[data-count-level]').forEach(n => n.textContent = WK.papers.filter(p => p.level === n.dataset.countLevel).length);
    const kp = [['Papers listed', WK.papers.length, 'file', ''], ['Printed copies on sale', WK.papers.filter(p => p.printOn).length, 'print', 'y'], ['PDF downloads on sale', WK.papers.filter(p => p.pdfOn).length, 'download', 'r'], ['Missing PDF files', WK.papers.filter(missing).length, 'alert', 'r']];
    $('#paperStats').innerHTML = kp.map(s => `<div class="card kpi mini"><div class="flex-b"><div><div class="kpi-l">${s[0]}</div><div class="kpi-v">${s[1]}</div></div><span class="kpi-ic ${s[3]}">${ic(s[2])}</span></div></div>`).join('');
    const sw = (p, k) => `<label class="switch sm" style="margin-bottom:.35rem" title="On sale"><input type="checkbox" data-fmt-on="${k}" ${p[k + 'On'] ? 'checked' : ''} aria-label="${k === 'print' ? 'Printed' : 'PDF'} available"><span class="trk"></span></label><input class="price-in" type="number" step="500" min="0" value="${p[k]}" data-price="${k}" aria-label="${k} price" ${p[k + 'On'] ? '' : 'disabled'}>`;
    const hash = location.hash.slice(1);
    const bulk = (action, extra) => ids => ADM.post(base + '/bulk', Object.assign({ action, 'ids[]': ids }, extra || {}));
    const S = ADM.table('#tbl', {
      rows: () => WK.papers, id: p => p.id, select: true, size: 10, csvName: 'wakata-past-papers', placeholder: 'Search subject, year…', sort: 'year', dir: -1,
      tabs: { default: hash === 'missing' ? 'nofile' : 'all', items: [{ k: 'all', label: 'All papers' }, { k: 'print', label: 'Printed copies', fn: p => p.printOn }, { k: 'pdf', label: 'PDF downloads', fn: p => p.pdfOn }, { k: 'nofile', label: 'Missing PDF file', fn: missing }, { k: 'draft', label: 'Drafts', fn: p => p.status !== 'published' }] },
      search: p => [p.title, p.subject, p.year, p.paper, LV[p.level]].join(' '),
      filters: [{ k: 'l', label: 'Level', all: 'All levels', options: WK.adm.levels.map(l => [l.code, l.short]), fn: (p, v) => p.level === v }, { k: 's', label: 'Subject', all: 'All subjects', options: subs.map(s => [s, s]), fn: (p, v) => p.subject === v }, { k: 'y', label: 'Year', all: 'Any year', options: years.map(y => [y, y]), fn: (p, v) => String(p.year) === v }, { k: 'pp', label: 'Paper', all: 'Any paper', options: [...new Set(WK.papers.map(p => p.paper))].sort().map(x => [x, x]), fn: (p, v) => p.paper === v }],
      cols: [
        { k: 'paper', label: 'Paper', sort: p => p.subject + p.year, html: p => `<div class="cell-prod">${AD.thumbOf('paper', p)}<div><a class="link-row" href="${formUrl(p.id)}">${esc(p.title)} ${p.year}</a><small>${LV[p.level]} · ${esc(p.time)}</small></div></div>`, csv: p => p.title + ' ' + p.year },
        { k: 'year', label: 'Year', sort: p => p.year, html: p => p.year },
        { k: 'print', label: 'Printed copy', sort: p => p.print, html: p => sw(p, 'print'), csv: p => p.print },
        { k: 'pdf', label: 'PDF download', sort: p => p.pdf, html: p => sw(p, 'pdf'), csv: p => p.pdf },
        { k: 'file', label: 'PDF file', sort: p => p.hasFile ? 1 : 0, html: p => p.hasFile ? `<div class="flex"><span class="badge b-ok">Uploaded</span></div><div class="xs muted">${paperFile(p)} · ${p.fileMb} MB</div>` : `<span class="badge b-bad">Missing</span><div><button class="btn btn-line btn-sm" style="margin-top:.3rem" type="button" data-act="upload">${ic('upload')}Upload</button></div>`, csv: p => p.hasFile ? 'Uploaded' : 'Missing' },
        { k: 'sales', label: 'Sold', num: true, sort: p => sold('paper', p.id), html: p => sold('paper', p.id).toLocaleString() },
        { k: 'status', label: 'Status', sort: p => p.status, html: p => stBadgeSimple(p.status) }],
      actions: p => `<div class="row-actions"><a class="btn btn-ic" href="${formUrl(p.id)}" aria-label="Edit" title="Edit">${ic('edit')}</a><button class="btn btn-ic" type="button" data-act="delete" aria-label="Delete" title="Delete">${ic('trash')}</button></div>`,
      onAct: (a, p) => {
        if (a === 'upload') { ADM.uploadTo(base + '/' + enc(p.id) + '/file', 'pdffile', 'application/pdf'); return true; }
        if (a === 'delete') { ADM.confirm({ title: 'Delete this paper?', text: '“' + p.title + ' ' + p.year + '” and its uploaded files will be removed. Orders already placed keep their details.', onOk: () => ADM.post(base + '/' + enc(p.id), {}, 'DELETE') }); return true; }
        return false;
      },
      bulk: [{ label: 'Enable printed', act: bulk('print_on') }, { label: 'Enable PDF', act: bulk('pdf_on') }, { label: 'Set price…', act: () => ADM.open('bulkPrice') }, { label: 'Publish', act: bulk('publish') }, { label: 'Move to draft', act: bulk('draft') }],
      after: (r, el) => {
        $$('[data-price]', el).forEach(i => i.onchange = () => {
          const p = WK.papers.find(x => x.id === i.closest('tr').dataset.id), k = i.dataset.price;
          ADM.send(base + '/' + enc(p.id) + '/quick', 'PATCH', { [k + '_price']: Number(i.value || 0) }).then(d => { p[k] = d[k]; toast('Price updated to ' + M(d[k]) + '.'); }).catch(e => { toast(e.message, 'err'); i.value = p[k]; });
        });
        $$('[data-fmt-on]', el).forEach(i => i.onchange = () => {
          const p = WK.papers.find(x => x.id === i.closest('tr').dataset.id), k = i.dataset.fmtOn, n = i.closest('td').querySelector('.price-in');
          ADM.send(base + '/' + enc(p.id) + '/quick', 'PATCH', { [k + '_on']: i.checked }).then(d => { p[k + 'On'] = d[k + 'On']; n.disabled = !d[k + 'On']; toast((k === 'print' ? 'Printed copy' : 'PDF download') + (d[k + 'On'] ? ' switched on.' : ' switched off.')); S.render(); }).catch(e => { toast(e.message, 'err'); i.checked = !!p[k + 'On']; });
        });
      }
    });
    $('#bulkPriceApply').addEventListener('click', () => { const ids = [...S.sel]; ADM.close($('#bulkPrice')); bulk('price', { format: $('#bf').value, price: $('#bp').value })(ids); });
    $$('[data-level-apply]').forEach(b => b.addEventListener('click', () => {
      const lv = b.dataset.levelApply, apply = b.dataset.mode === 'apply';
      const go = () => ADM.post(R.base + '/papers/defaults', { level: lv, print: $('#pr-' + lv).value, pdf: $('#pd-' + lv).value, time: $('#tm-' + lv).value, apply: apply ? '1' : '' });
      if (apply) ADM.confirm({ title: 'Apply these prices to every ' + LV[lv] + ' paper?', text: 'This overwrites any paper-specific prices for this level.', ok: 'Apply to all', danger: false, icon: 'tag', onOk: go }); else go();
    }));
  };

  /* ================= EXAM SETS ================= */
  function cardsFilter(root, items, render, extra) {
    const st = { q: '', l: '' }, host = $(root);
    const run = () => { const r = items().filter(x => (!st.l || x.level === st.l) && (!st.q || JSON.stringify(x).toLowerCase().includes(st.q)) && (!extra || extra(x, st))); host.innerHTML = r.map(render).join('') || '<div class="empty card" style="grid-column:1/-1">Nothing found.</div>'; };
    $('[data-card-q]').addEventListener('input', e => { st.q = e.target.value.trim().toLowerCase(); run(); });
    $$('[data-lvl]').forEach(c => c.addEventListener('click', () => { $$('[data-lvl]').forEach(x => x.classList.toggle('on', x === c)); st.l = c.dataset.lvl; run(); }));
    run();
  }
  const delCard = (root, path, title, text) => $(root).addEventListener('click', e => { const b = e.target.closest('[data-del]'); if (!b) return; ADM.confirm({ title, text, onOk: () => ADM.post(R.base + path + '/' + enc(b.dataset.del), {}, 'DELETE') }); });
  P['exam-sets'] = function () {
    $('#setStats').innerHTML = [['Sets', WK.sets.length, 'layers', ''], ['Papers in sets', AD.sum(WK.sets, s => s.papers), 'file', 'y'], ['Total list value', M(AD.sum(WK.sets, s => s.worth)), 'tag', 'g'], ['Sets sold', AD.sum(WK.sets, s => sold('set', s.id)), 'cart', 'p']].map(s => `<div class="card kpi mini"><div class="flex-b"><div><div class="kpi-l">${s[0]}</div><div class="kpi-v">${s[1]}</div></div><span class="kpi-ic ${s[3]}">${ic(s[2])}</span></div></div>`).join('');
    const formUrl = id => R.setForm + '?id=' + enc(id);
    cardsFilter('#cards', () => WK.sets, s => `<article class="card pcard"><div class="pm" style="padding:0"><div class="setstack" style="width:100%;border-radius:0">${(s.preview || []).map(p => `<img src="${AD.img(WK.PAPER_IMG + p)}" alt="" loading="lazy">`).join('')}</div></div><div class="pb"><div class="flex-b"><span class="xs muted">${LV[s.level]} · ${esc(s.year)}</span>${stBadgeSimple(s.status)}</div><h3><a class="link-row" href="${formUrl(s.id)}">${esc(s.title)}</a></h3><p class="sm muted mb0">${s.papers} papers · ${s.subjects.length} subject${s.subjects.length === 1 ? '' : 's'}</p><div class="flex" style="gap:.5rem"><b>${M(s.price)}</b>${s.worth > s.price ? `<span class="old">${M(s.worth)}</span><span class="badge nodot b-ok">Save ${Math.round((s.worth - s.price) / s.worth * 100)}%</span>` : ''}</div><div class="pf"><span class="xs muted">${stockH(s.stock, s.low)}</span><div class="row-actions"><a class="btn btn-ic" href="${formUrl(s.id)}" aria-label="Edit">${ic('edit')}</a><a class="btn btn-ic" href="${R.siteSets}" target="_blank" rel="noopener" aria-label="View on website">${ic('external')}</a><button class="btn btn-ic" type="button" data-del="${esc(s.id)}" aria-label="Delete">${ic('trash')}</button></div></div></div></article>`);
    delCard('#cards', '/exam-sets', 'Delete this exam set?', 'It will be removed from the website. Orders already placed keep their details.');
  };

  /* ================= HOLIDAY PACKAGES ================= */
  P.packages = function () {
    const formUrl = id => R.packageForm + '?id=' + enc(id);
    $('#pkStats').innerHTML = [['Packages', WK.packages.length, 'gift', ''], ['Most chosen', WK.packages.filter(k => k.best).length, 'star', 'y'], ['Sold', AD.sum(WK.packages, k => sold('pack', k.id)), 'cart', 'g'], ['Draft packages', WK.packages.filter(k => k.status !== 'published').length, 'edit', 'p']].map(s => `<div class="card kpi mini"><div class="flex-b"><div><div class="kpi-l">${s[0]}</div><div class="kpi-v">${s[1]}</div></div><span class="kpi-ic ${s[3]}">${ic(s[2])}</span></div></div>`).join('');
    cardsFilter('#cards', () => WK.packages, k => `<article class="pk${k.best ? ' best' : ''}">${k.best ? '<span class="ribbon">Most chosen</span>' : ''}<div class="flex-b"><span class="badge nodot b-info">${LV[k.level]}</span>${stBadgeSimple(k.status)}</div><h3 style="margin:.2rem 0 0">${esc(k.name)}</h3><span class="sm muted">${esc(k.for)}</span><ul>${k.includes.map(i => `<li>${esc(i)}</li>`).join('')}</ul><div class="amt">${M(k.price)} <small class="sm muted" style="font-family:var(--sans);font-weight:500">per holiday</small></div><div class="flex-b" style="margin-top:.4rem"><span class="xs muted">${stockH(k.stock, k.low)} · ${sold('pack', k.id)} sold</span><div class="row-actions"><a class="btn btn-ic" href="${formUrl(k.id)}" aria-label="Edit">${ic('edit')}</a><a class="btn btn-ic" href="${R.sitePackages}" target="_blank" rel="noopener" aria-label="View on website">${ic('external')}</a><button class="btn btn-ic" type="button" data-del="${esc(k.id)}" aria-label="Delete">${ic('trash')}</button></div></div></article>`);
    delCard('#cards', '/packages', 'Delete this package?', 'It will be removed from the website. Orders already placed keep their details.');
  };

  /* ================= SUBJECTS & LEVELS ================= */
  P.taxonomy = function () {
    const base = R.base + '/taxonomy/subjects', sub = WK.adm.subjects;
    const form = $('#subjForm'), method = $('#subjMethod');
    const sync = () => { const pv = $('#subjPrev'); pv.style.setProperty('--c1', $('#sc1').value); pv.style.setProperty('--c2', $('#sc2').value); pv.style.setProperty('--c3', $('#sc3').value); $('#sjText').textContent = $('#sName').value || 'Subject'; };
    ADM.table('#tbl', {
      rows: () => sub, id: s => s.id, size: 20, csvName: 'wakata-subjects', placeholder: 'Search subjects…', search: s => s.name,
      cols: [{ k: 'name', label: 'Subject', sort: s => s.name, html: s => `<div class="flex"><div class="thumb sq"><div class="cv" style="--c1:${s.c1};--c2:${s.c2};--c3:${s.c3}"><span class="sj" style="font-size:.4rem">${esc(s.name.slice(0, 9))}</span></div></div><b>${esc(s.name)}</b></div>`, csv: s => s.name },
        { k: 'cols', label: 'Cover colours', html: s => `<span class="swatch" style="background:${s.c1}" title="${s.c1}"></span> <span class="swatch" style="background:${s.c2}" title="${s.c2}"></span> <span class="swatch" style="background:${s.c3}" title="${s.c3}"></span>`, csv: s => [s.c1, s.c2, s.c3].join(' ') },
        { k: 'books', label: 'Books', num: true, sort: s => s.books, html: s => s.books }, { k: 'papers', label: 'Past papers', num: true, sort: s => s.papers, html: s => s.papers }],
      actions: s => `<div class="row-actions"><button class="btn btn-ic" type="button" data-act="edit" aria-label="Edit">${ic('edit')}</button><button class="btn btn-ic" type="button" data-act="delete" aria-label="Delete">${ic('trash')}</button></div>`,
      onAct: (a, s) => {
        if (a === 'edit') { form.action = base + '/' + s.id; method.value = 'PUT'; $('#sName').value = s.name; ['c1', 'c2', 'c3'].forEach(k => $('#s' + k).value = s[k]); $('#subjTitle').textContent = 'Edit subject'; ADM.open('subjModal'); sync(); return true; }
        if (a === 'delete') { ADM.confirm({ title: 'Delete this subject?', text: s.books || s.papers ? '“' + s.name + '” is used by ' + s.books + ' book(s) and ' + s.papers + ' paper(s), so it cannot be deleted until those are moved.' : '“' + s.name + '” will be removed from the subject lists.', onOk: () => ADM.post(base + '/' + s.id, {}, 'DELETE') }); return true; }
        return false;
      }
    });
    $$('#subjModal input').forEach(i => i.addEventListener('input', sync));
    $('#newSubj').addEventListener('click', () => { form.action = base; method.value = 'POST'; $('#subjTitle').textContent = 'Add a subject'; $('#sName').value = ''; sync(); });
    sync();
  };

  /* ================= MEDIA ================= */
  P.media = function () {
    const files = WK.adm.media, base = R.base;
    let kind = '', q = '';
    const run = () => { const r = files.filter(f => (!kind || f.kind === kind) && (!q || (f.name + f.use).toLowerCase().includes(q))); $('#mediaGrid').innerHTML = r.map(f => `<div class="mi" tabindex="0" role="button" data-p="${esc(f.path)}"><div class="im"><img src="${esc(f.url)}" alt="" loading="lazy" style="${f.kind === 'brand' ? 'object-fit:contain;padding:1rem;background:var(--blue)' : ''}"></div><div class="t"><b>${esc(f.name)}</b><span class="muted">${f.size} KB</span></div></div>`).join('') || '<div class="empty" style="grid-column:1/-1">No files.</div>'; };
    $$('[data-kind]').forEach(c => c.addEventListener('click', () => { $$('[data-kind]').forEach(x => x.classList.toggle('on', x === c)); kind = c.dataset.kind; run(); }));
    $('[data-card-q]').addEventListener('input', e => { q = e.target.value.trim().toLowerCase(); run(); });
    const show = t => {
      $$('.mi').forEach(x => x.classList.toggle('on', x === t)); const f = files.find(x => x.path === t.dataset.p);
      $('#mediaSide').innerHTML = `<div class="coverprev" style="margin-bottom:1rem"><img src="${esc(f.url)}" alt=""></div><h3 style="word-break:break-all">${esc(f.name)}</h3><dl class="dl"><dt>Used for</dt><dd>${esc(f.use)}</dd><dt>Size</dt><dd>${f.size} KB</dd><dt>Folder</dt><dd>${esc(f.path.replace(f.name, '') || 'Site files')}</dd></dl><div class="flex mt wrapf"><button class="btn btn-line btn-sm" type="button" data-copy="${esc(f.path)}">${ic('copy')}Copy path</button>${f.locked ? '' : `<button class="btn btn-line btn-sm" type="button" data-replace>${ic('upload')}Replace</button><button class="btn btn-danger-l btn-sm" type="button" data-del>${ic('trash')}Delete</button>`}</div>`;
      const rep = $('[data-replace]', $('#mediaSide')), del = $('[data-del]', $('#mediaSide'));
      rep && rep.addEventListener('click', () => ADM.uploadTo(base + '/media/replace', 'file', 'image/*', { path: f.path }));
      del && del.addEventListener('click', () => ADM.confirm({ title: 'Delete this file?', text: f.refs ? 'Pages using this image will show a generated cover instead.' : 'Nothing uses this image.', onOk: () => ADM.post(base + '/media/delete', { path: f.path }) }));
    };
    $('#mediaGrid').addEventListener('click', e => { const t = e.target.closest('.mi'); if (t) show(t); });
    run();
    const pdf = p => WK.adm.downloads[p.id] || 0;
    ADM.table('#pdfTbl', { rows: () => WK.papers, id: p => p.id, size: 8, placeholder: 'Search PDF files…', csvName: 'wakata-pdf-files', search: p => p.title + p.year + p.subject,
      cols: [{ k: 'f', label: 'File', sort: p => p.subject + p.year, html: p => `<div class="filechip" style="border:0;padding:0"><span class="fi">PDF</span><div><b>${esc(paperFile(p))}</b><small>${esc(p.title)} ${p.year} · ${LV[p.level]}</small></div></div>` }, { k: 's', label: 'Status', sort: p => p.hasFile ? 1 : 0, html: p => p.hasFile ? '<span class="badge b-ok">Uploaded</span>' : '<span class="badge b-bad">Missing</span>' }, { k: 'sz', label: 'Size', html: p => p.hasFile ? p.fileMb + ' MB' : '—' }, { k: 'd', label: 'Downloads', num: true, sort: p => pdf(p), html: p => pdf(p).toLocaleString() }],
      actions: p => `<div class="row-actions"><button class="btn btn-ic" type="button" data-act="${p.hasFile ? 'replace' : 'upload'}" aria-label="Upload">${ic('upload')}</button>${p.hasFile ? `<a class="btn btn-ic" href="${base}/papers/${enc(p.id)}/file" aria-label="Download">${ic('download')}</a>` : ''}</div>`,
      onAct: (a, p) => { if (a === 'upload' || a === 'replace') { ADM.uploadTo(base + '/papers/' + enc(p.id) + '/file', 'pdffile', 'application/pdf'); return true; } return false; } });
  };

  /* ================= INVENTORY ================= */
  P.inventory = function () {
    const base = R.base + '/inventory';
    const rows = [...WK.books.map(b => ({ id: b.id, type: 'Book', kind: 'book', name: b.title, sku: b.sku, stock: b.stock, low: b.low, price: b.price, rec: b })), ...WK.sets.map(s => ({ id: s.id, type: 'Exam set', kind: 'set', name: s.title, sku: s.sku, stock: s.stock, low: s.low, price: s.price, rec: s })), ...WK.packages.map(k => ({ id: k.id, type: 'Holiday package', kind: 'pack', name: k.name, sku: k.sku, stock: k.stock, low: k.low, price: k.price, rec: k })), ...WK.papers.filter(p => p.printOn).map(p => ({ id: p.id, type: 'Printed paper', kind: 'paper', name: p.title + ' ' + p.year, sku: 'PP-' + p.id.slice(-8).toUpperCase(), stock: p.printStock, low: p.low, price: p.print, rec: p }))];
    const refs = r => r.kind + ':' + r.id;
    const stats = () => { const units = AD.sum(rows, r => r.stock), value = AD.sum(rows, r => r.stock * r.price), low = rows.filter(r => r.stock > 0 && r.stock <= r.low).length, out = rows.filter(r => r.stock <= 0).length; $('#invStats').innerHTML = [['Stock lines', rows.length, 'box', ''], ['Units on hand', units.toLocaleString(), 'layers', 'g'], ['Stock value (at retail)', M(value), 'wallet', 'p'], ['Running low', low, 'alert', 'y'], ['Out of stock', out, 'x', 'r']].map(s => `<div class="card kpi mini"><div class="flex-b"><div><div class="kpi-l">${s[0]}</div><div class="kpi-v">${s[1]}</div></div><span class="kpi-ic ${s[3]}">${ic(s[2])}</span></div></div>`).join(''); };
    const donut = () => { const bt = {}; rows.forEach(r => bt[r.type] = (bt[r.type] || 0) + r.stock); C.donut('#chStock', { items: Object.entries(bt).map(([k, v], i) => ({ name: k, value: v, color: colors[[0, 2, 3, 1][i % 4]] })), centerLabel: 'Units on hand', fmt: v => Math.round(v).toLocaleString(), size: 170 }); };
    stats(); donut();
    const mv = WK.adm.movement; C.line('#chMove', { labels: mv.labels, series: [{ name: 'Received / printed', data: mv.received, color: 'var(--c4)' }, { name: 'Sold', data: mv.sold, color: 'var(--c1)' }], height: 230, fmt: v => Math.round(v) });
    const tabs = { items: [{ k: 'all', label: 'All stock' }, { k: 'low', label: 'Running low', fn: r => r.stock > 0 && r.stock <= r.low }, { k: 'out', label: 'Out of stock', fn: r => r.stock <= 0 }, { k: 'ok', label: 'Healthy', fn: r => r.stock > r.low }] };
    const reprint = list => { const r = list.filter(x => x.stock <= x.low); if (!r.length) { toast('Nothing is running low.'); return; } ADM.csv('wakata-reprint-list', ['Item', 'Type', 'SKU', 'On hand', 'Alert at', 'Suggested reprint'], r.map(x => [x.name, x.type, x.sku, x.stock, x.low, Math.max(x.low * 3 - x.stock, 0)])); };
    let cur = null;
    const S = ADM.table('#tbl', {
      rows: () => rows, id: r => r.kind + ':' + r.id, size: 10, select: true, csvName: 'wakata-inventory', placeholder: 'Search item or SKU…', tabs, search: r => [r.name, r.sku, r.type].join(' '), sort: 'stock', dir: 1,
      filters: [{ k: 't', label: 'Type', all: 'All item types', options: [['Book', 'Books'], ['Printed paper', 'Printed papers'], ['Exam set', 'Exam sets'], ['Holiday package', 'Holiday packages']], fn: (r, v) => r.type === v }],
      cols: [{ k: 'n', label: 'Item', sort: r => r.name, html: r => `<div class="cell-prod">${AD.thumbOf(r.kind, r.rec)}<div><b>${esc(r.name)}</b><small>${esc(r.sku)} · ${r.type}</small></div></div>`, csv: r => r.name }, { k: 'stock', label: 'On hand', sort: r => r.stock, html: r => stockH(r.stock, r.low), csv: r => r.stock }, { k: 'low', label: 'Alert at', num: true, html: r => r.low }, { k: 'cover', label: 'Cover', html: r => { const w = Math.min(100, r.stock / (Math.max(r.low, 1) * 4) * 100); return `<div class="progress" style="width:110px"><i style="width:${w}%;background:${r.stock <= 0 ? 'var(--pen)' : r.stock <= r.low ? 'var(--warn)' : 'var(--ok)'}"></i></div>`; } }, { k: 'val', label: 'Value', num: true, sort: r => r.stock * r.price, html: r => M(r.stock * r.price) }],
      actions: r => `<div class="row-actions"><button class="btn btn-line btn-sm" type="button" data-act="adjust">${ic('sliders')}Adjust</button></div>`,
      onAct: (a, r) => { if (a === 'adjust') { cur = r; $('#adjName').textContent = r.name; $('#adjCur').textContent = r.stock; $('#aq').value = 0; $('#an').value = ''; ADM.open('adjModal'); return true; } return false; },
      bulk: [{ label: 'Set alert level…', act: () => ADM.open('alertModal') }, { label: 'Mark for reprint', act: ids => { const set = new Set(ids); reprint(rows.filter(r => set.has(r.kind + ':' + r.id))); } }]
    });
    $('#adjSave').addEventListener('click', () => {
      if (!cur) return; const r = cur;
      ADM.send(base + '/adjust', 'POST', { kind: r.kind, id: r.id, mode: $('#at').value, qty: Number($('#aq').value || 0), reason: $('#ar').value, note: $('#an').value })
        .then(d => { r.stock = d.stock; if (r.kind === 'paper') r.rec.printStock = d.stock; else r.rec.stock = d.stock; ADM.close($('#adjModal')); stats(); donut(); S.render(); toast('Stock of ' + esc(r.name) + ' is now ' + d.stock + '.'); })
        .catch(e => toast(esc(e.message), 'err'));
    });
    $('#alertSave').addEventListener('click', () => {
      const sel = [...S.sel], lvl = Number($('#alv').value);
      ADM.send(base + '/alert', 'POST', { items: sel, level: lvl }).then(d => { rows.forEach(r => { if (S.sel.has(refs(r))) { r.low = d.level; r.rec.low = d.level; } }); S.sel.clear(); ADM.close($('#alertModal')); stats(); S.render(); toast('Alert level set to ' + d.level + ' for ' + d.updated + ' item(s).'); }).catch(e => toast(esc(e.message), 'err'));
    });
    $('#reprintBtn').addEventListener('click', () => reprint(rows));
  };

  /* ================= CUSTOMERS ================= */
  P.customers = function () {
    const types = ['School', 'Teacher', 'Parent', 'Bookshop'], typeBadge = t => t === 'School' ? 'b-info' : t === 'Bookshop' ? 'b-pur' : 'b-mute';
    const paint = () => {
      const tot = AD.sum(AD.customers, c => c.spent);
      $('#custStats').innerHTML = [['Customers', AD.customers.length, 'users', ''], ['Schools and bookshops', AD.customers.filter(c => c.type === 'School' || c.type === 'Bookshop').length, 'school', 'y'], ['Repeat customers', AD.customers.filter(c => c.orders > 1).length, 'refresh', 'g'], ['Lifetime sales', M(tot), 'wallet', 'p']].map(s => `<div class="card kpi mini"><div class="flex-b"><div><div class="kpi-l">${s[0]}</div><div class="kpi-v">${s[1]}</div></div><span class="kpi-ic ${s[3]}">${ic(s[2])}</span></div></div>`).join('');
      const top = AD.customers.slice().sort((a, b) => b.spent - a.spent).slice(0, 8).filter(c => c.spent > 0);
      if (top.length) C.bar('#chTopCust', { labels: top.map(c => c.name), series: [{ name: 'Lifetime sales', data: top.map(c => c.spent), color: 'var(--c1)' }], horizontal: true, tipFmt: M, height: 300 }); else $('#chTopCust').innerHTML = '<p class="muted">No paid orders yet.</p>';
      const bt = {}; AD.customers.forEach(c => bt[c.type] = (bt[c.type] || 0) + c.spent);
      const groups = [...types, 'Guest'].filter(t => bt[t] > 0);
      if (groups.length) C.donut('#chCustType', { items: groups.map((t, i) => ({ name: t + 's', value: bt[t] || 0, color: colors[[0, 2, 3, 1, 4][types.indexOf(t) < 0 ? 4 : types.indexOf(t)]] })), centerLabel: 'Lifetime sales', tipFmt: M, size: 170 }); else $('#chCustType').innerHTML = '<p class="muted">No paid orders yet.</p>';
    };
    paint();
    const districts = () => [...new Set(AD.customers.map(c => c.district).filter(Boolean))].sort().map(d => [d, d]);
    const S = ADM.table('#tbl', {
      rows: () => AD.customers, id: c => c.id, select: true, size: 10, csvName: 'wakata-customers', placeholder: 'Search name, phone, email…', q0: ADM.qs('q') || '', sort: 'spent', dir: -1,
      tabs: { items: [{ k: 'all', label: 'All customers' }, ...types.map(t => ({ k: t, label: t + 's', fn: c => c.type === t })), { k: 'Guest', label: 'Guests', fn: c => c.type === 'Guest' }, { k: 'rep', label: 'Repeat buyers', fn: c => c.orders > 1 }] },
      search: c => [c.name, c.phone, c.email, c.district, c.id].join(' '),
      filters: [{ k: 'd', label: 'District', all: 'All districts', options: districts(), fn: (c, v) => c.district === v }],
      cols: [{ k: 'n', label: 'Customer', sort: c => c.name, html: c => `<div class="flex">${av(c.name)}<div><a href="#" class="link-row" data-act="view">${esc(c.name)}</a><div class="xs muted">${c.id}${c.contact ? ' · ' + esc(c.contact) : ''}</div></div></div>`, csv: c => c.name }, { k: 't', label: 'Type', sort: c => c.type, html: c => `<span class="badge nodot ${typeBadge(c.type)}">${c.type}</span>` }, { k: 'c', label: 'Contact', html: c => `${esc(c.phone || '—')}<div class="xs muted">${esc(c.email)}</div>`, csv: c => (c.phone || '') + ' ' + c.email }, { k: 'd', label: 'District', sort: c => c.district, html: c => esc(c.district || '—') }, { k: 'o', label: 'Orders', num: true, sort: c => c.orders, html: c => c.orders }, { k: 'spent', label: 'Lifetime spend', num: true, sort: c => c.spent, html: c => `<b>${M(c.spent)}</b>`, csv: c => c.spent }, { k: 'last', label: 'Last order', sort: c => c.lastTs, html: c => c.lastTs ? AD.fmtDate(c.lastTs) : '—' }],
      actions: c => `<div class="row-actions"><button class="btn btn-ic" type="button" data-act="view" aria-label="View customer">${ic('eye')}</button>${c.email ? `<a class="btn btn-ic" href="mailto:${esc(c.email)}" aria-label="Email">${ic('mail')}</a>` : ''}</div>`,
      bulk: [{ label: 'Email selected', act: ids => { const mails = AD.customers.filter(c => ids.includes(c.id) && c.email).map(c => c.email); if (!mails.length) { toast('None of the selected customers has an email.', 'err'); return; } location.href = 'mailto:?bcc=' + encodeURIComponent(mails.join(',')); } }],
      onAct: (a, c) => {
        if (a !== 'view') return false;
        const os = AD.orders.filter(o => o.custId === c.id).slice(0, 5), phone = String(c.phone || '').replace(/\D/g, '');
        ADM.drawer('Customer profile', `<div class="flex" style="margin-bottom:1.1rem"><span class="avatar lg av-${c.name.length % 6 + 1}">${AD.initials(c.name)}</span><div><h3 style="margin:0">${esc(c.name)}</h3><span class="muted">${c.type}${c.contact ? ' · ' + esc(c.contact) : ''}</span><div class="xs muted">Customer since ${AD.fmtDate(c.joined)}</div></div></div><div class="cols-3" style="margin-bottom:1.2rem"><div class="card kpi mini"><div class="kpi-l">Orders</div><div class="kpi-v">${c.orders}</div></div><div class="card kpi mini"><div class="kpi-l">Spent</div><div class="kpi-v" style="font-size:1rem">${M(c.spent)}</div></div><div class="card kpi mini"><div class="kpi-l">Average</div><div class="kpi-v" style="font-size:1rem">${M(c.orders ? Math.round(c.spent / c.orders) : 0)}</div></div></div><dl class="dl" style="margin-bottom:1.2rem"><dt>Phone</dt><dd>${c.phone ? `<a href="tel:${esc(c.phone)}">${esc(c.phone)}</a>` : '—'}</dd><dt>Email</dt><dd>${esc(c.email || '—')}</dd><dt>District</dt><dd>${esc(c.district || '—')}</dd></dl><h4>Recent orders</h4><table class="mini-table"><thead><tr><th>Order</th><th>Date</th><th>Status</th><th class="num">Total</th></tr></thead><tbody>${os.map(o => `<tr><td><a href="${orderHref(o)}">${o.id}</a></td><td>${AD.fmtDate(o.ts)}</td><td>${sBadge(o.status)}</td><td class="num">${M(o.total)}</td></tr>`).join('') || '<tr><td colspan="4" class="muted">No orders yet</td></tr>'}</tbody></table>${c.dbId ? `<div class="field mt"><label for="cnote">Internal note</label><textarea id="cnote" maxlength="2000" placeholder="e.g. Bursar prefers delivery on Fridays"${CAN.sales ? '' : ' readonly'}>${esc(c.note)}</textarea></div>` : '<p class="muted sm mt">This buyer checked out without an account, so there is no profile to add a note to.</p>'}`,
          `${c.email ? `<a class="btn btn-line" href="mailto:${esc(c.email)}">${ic('mail')}Email</a>` : ''}${phone ? `<a class="btn btn-line" href="https://wa.me/${phone}" target="_blank" rel="noopener">${ic('whatsapp')}WhatsApp</a>` : ''}${c.dbId ? '<button class="btn btn-primary" type="button" id="cnoteSave">Save note</button>' : ''}`);
        const sb = $('#cnoteSave'); if (sb) { sb.disabled = !CAN.sales; sb.title = CAN.sales ? '' : NO_SALES; sb.addEventListener('click', () => send(SB + '/customers/' + c.dbId + '/note', 'POST', { admin_note: $('#cnote').value }).then(d => toast(esc(d.message))).catch(oops)); }
        return true;
      }
    });

    /* Add a customer */
    lock('[data-open="addCust"]', CAN.sales, NO_SALES);
    $('#custSave').addEventListener('click', () => {
      let ok = true; const bad = (sel, b) => { const f = $(sel).closest('.field'); f.classList.toggle('bad', b); if (b && ok) { ok = false; $(sel).focus(); } };
      bad('#cn', !$('#cn').value.trim()); bad('#ce', !$('#ce').value.trim() || !$('#ce').checkValidity());
      if (!ok) { toast('Please fix the highlighted fields.', 'err'); return; }
      const btn = $('#custSave'); btn.disabled = true;
      send(SB + '/customers', 'POST', { name: $('#cn').value.trim(), type: $('#ct').value, phone: $('#cp').value.trim() || null, email: $('#ce').value.trim(), contact: $('#cc').value.trim() || null, district: $('#cd').value.trim() || null })
        .then(d => { ADM.close($('#addCust')); ['#cn', '#cp', '#ce', '#cc', '#cd'].forEach(s => { $(s).value = ''; }); paint(); S.render(); toast(esc(d.message)); })
        .catch(oops).finally(() => { btn.disabled = false; });
    });
  };

  /* ================= PAYMENTS ================= */
  P.payments = function () {
    const draw = () => {
      const all = AD.orders, paid = all.filter(AD.counted), m30 = AD.within(30), pend = all.filter(o => o.pay === 'pending' && o.status !== 'cancelled'), fail = all.filter(o => o.pay === 'failed'), ref = all.filter(o => o.pay === 'refunded');
      $('#payStats').innerHTML = [['Collected (30 days)', M(AD.sum(m30, o => o.total)), 'wallet', 'g'], ['Awaiting confirmation', M(AD.sum(pend, o => o.total)) + ' · ' + pend.length, 'clock', 'y'], ['Failed payments', fail.length, 'x', 'r'], ['Refunded', M(AD.sum(ref, o => o.total)), 'refresh', 'p']].map(s => `<div class="card kpi mini"><div class="flex-b"><div><div class="kpi-l">${s[0]}</div><div class="kpi-v" style="font-size:1.15rem">${s[1]}</div></div><span class="kpi-ic ${s[3]}">${ic(s[2])}</span></div></div>`).join('');
      const pm = AD.byMethod(paid); C.donut('#chPay', { items: [{ name: 'MTN Mobile Money', value: pm.mtn, color: 'var(--c2)' }, { name: 'Airtel Money', value: pm.airtel, color: 'var(--c3)' }, { name: 'Visa / Mastercard', value: pm.card, color: 'var(--c1)' }, { name: 'Cash on pickup', value: pm.cash, color: 'var(--c4)' }], centerLabel: 'All time', tipFmt: M, size: 180 });
      const S = AD.series(90).buckets; C.line('#chPayTrend', { labels: S.map(b => b.label), series: [{ name: 'Collected', data: S.map(b => b.rev), color: 'var(--c4)' }], tipFmt: M, height: 230 });
    };
    draw();
    const T = ADM.table('#tbl', {
      rows: () => AD.orders, id: o => o.id, size: 10, csvName: 'wakata-payments', placeholder: 'Search transaction, order, customer…', sort: 'date', dir: -1,
      tabs: { items: [{ k: 'all', label: 'All transactions' }, ...Object.keys(PS).map(k => ({ k, label: PS[k].label, fn: o => o.pay === k }))] },
      search: o => [o.id, o.txn, o.cust.name, o.cust.phone].join(' '),
      filters: [{ k: 'm', label: 'Method', all: 'All methods', options: Object.entries(AD.PAY_METHODS), fn: (o, v) => o.method === v }],
      cols: [{ k: 'txn', label: 'Transaction', sort: o => o.txn, html: o => o.txn ? `<code style="font-size:.82rem">${esc(o.txn)}</code>` : `<span class="muted">${o.method === 'cash' ? 'Cash' : 'No reference yet'}</span>`, csv: o => o.txn }, { k: 'o', label: 'Order', sort: o => o.id, html: o => `<a class="link-row" href="${orderHref(o)}">${o.id}</a>` }, { k: 'c', label: 'Customer', html: o => `${esc(o.cust.name)}<div class="xs muted">${o.method === 'card' ? 'Card payment' : esc(o.cust.phone)}</div>`, csv: o => o.cust.name }, { k: 'm', label: 'Method', html: o => esc(AD.PAY_METHODS[o.method] || o.method), csv: o => AD.PAY_METHODS[o.method] || o.method }, { k: 'date', label: 'Date', sort: o => o.ts, html: o => `${AD.fmtDate(o.ts)}<div class="xs muted">${AD.fmtTime(o.ts)}</div>`, csv: o => AD.fmtDate(o.ts) }, { k: 'a', label: 'Amount', num: true, sort: o => o.total, html: o => `<b>${M(o.total)}</b>`, csv: o => o.total }, { k: 's', label: 'Status', sort: o => o.pay, html: o => pBadge(o.pay), csv: o => PS[o.pay].label }],
      actions: o => `<div class="row-actions">${o.pay === 'pending' && !closed(o) ? `<button class="btn btn-line btn-sm" type="button" data-act="verify">${ic('check')}Verify</button>` : ''}${o.pay === 'paid' && !closed(o) ? `<button class="btn btn-ic" type="button" data-act="refund" title="Refund" aria-label="Refund">${ic('refresh')}</button>` : ''}<a class="btn btn-ic" href="${orderHref(o)}" aria-label="Open order">${ic('eye')}</a></div>`,
      onAct: (a, o) => {
        if (a === 'refund') { ADM.confirm({ title: 'Refund ' + M(o.total) + '?', text: 'The payment is recorded as refunded via ' + (AD.PAY_METHODS[o.method] || o.method) + ' and the order is closed. Send the money back yourself first.', ok: 'Refund', icon: 'refresh', onOk: () => send(orderUrl(o, '/payment/refund'), 'POST', {}).then(d => { draw(); T.render(); toast(esc(d.message)); }).catch(oops) }); return true; }
        if (a === 'verify') { send(orderUrl(o, '/payment/verify'), 'POST', {}).then(d => { draw(); T.render(); toast(esc(d.message)); }).catch(oops); return true; }
        return false;
      }
    });

    /* Payment methods on the website: the four switches */
    const sw = $$('input[data-method]'), flags = () => Object.fromEntries(sw.map(i => [i.dataset.method, i.checked]));
    lock('input[data-method]', CAN.payments, NO_PAY);
    sw.forEach(i => i.addEventListener('change', () => {
      ADM.send(SB + '/payments/methods', 'PUT', flags()).then(d => toast('Payment methods saved.')).catch(e => { i.checked = !i.checked; oops(e); });
    }));
    $('#reconBtn').addEventListener('click', () => {
      if (!CAN.payments) { toast(NO_PAY, 'err'); return; }
      ADM.send(SB + '/payments/reconcile', 'POST').then(d => toast(esc(d.message))).catch(oops);
    });
  };

  /* ================= DELIVERIES ================= */
  P.deliveries = function () {
    const cols = [{ k: 'prep', t: 'To prepare', fn: o => ['pending', 'confirmed', 'processing'].includes(o.status), act: o => o.delivery === 'pickup' ? 'Mark ready' : 'Dispatch', next: o => o.delivery === 'pickup' ? 'ready' : 'shipped' }, { k: 'ready', t: 'Ready for pickup', fn: o => o.status === 'ready' && o.delivery === 'pickup', act: () => 'Collected', next: () => 'completed' }, { k: 'out', t: 'Out for delivery', fn: o => o.status === 'shipped' || (o.status === 'ready' && o.delivery !== 'pickup'), act: () => 'Delivered', next: () => 'completed' }, { k: 'done', t: 'Delivered (14 days)', fn: o => o.status === 'completed' && o.ts > NOW - 14 * DAY }];
    const zone = { pickup: 'b-info', kampala: 'b-ok', upcountry: 'b-pur' };
    function render() {
      const phys = AD.orders.filter(o => o.delivery !== 'email');
      $('#board').innerHTML = cols.map(c => { const os = phys.filter(c.fn).slice(0, c.k === 'done' ? 8 : 20); return `<div class="kcol"><h3>${c.t}<span class="n">${phys.filter(c.fn).length}</span></h3>${os.map(o => `<div class="kcard"><div class="flex-b"><b>${o.id}</b><span class="badge nodot ${zone[o.delivery] || 'b-mute'}">${o.delivery === 'pickup' ? 'Pickup' : o.delivery === 'kampala' ? 'Kampala' : o.delivery === 'upcountry' ? 'Upcountry' : esc(o.delivery)}</span></div><small>${esc(o.cust.name)}</small><small>${itemUnits(o)} units · ${M(o.total)}</small><small>${o.pay === 'paid' ? '<span style="color:var(--ok)">Paid</span>' : '<span style="color:var(--warn)">Payment pending</span>'} · ${AD.rel(o.ts)}</small>${o.courier ? `<small>Courier: ${esc(o.courier)}</small>` : ''}${c.act ? `<div class="flex-b" style="margin-top:.6rem"><a class="btn btn-ic" style="width:30px;height:30px" href="${orderHref(o)}" aria-label="Open order">${ic('eye')}</a><button class="btn btn-primary btn-sm" type="button" data-move="${esc(o.id)}" data-col="${c.k}"${CAN.sales ? '' : ' disabled title="' + NO_SALES + '"'}>${c.act(o)}</button></div>` : ''}</div>`).join('') || '<p class="muted sm">Nothing here.</p>'}</div>`; }).join('');
    }
    render();
    $('#board').addEventListener('click', e => {
      const b = e.target.closest('[data-move]'); if (!b) return;
      const o = AD.orders.find(x => x.id === b.dataset.move), c = cols.find(x => x.k === b.dataset.col); b.disabled = true;
      send(orderUrl(o, '/status'), 'POST', { status: c.next(o), notify: true }).then(d => { render(); toast(esc(o.id) + ' moved to “' + OS[o.status].label + '”.'); }).catch(err => { render(); oops(err); });
    });

    /* PDF emails */
    const pdf = () => AD.orders.filter(o => o.items.some(i => i.fmt === 'pdf'));
    const P2 = ADM.table('#pdfTbl', { rows: pdf, id: o => o.id, size: 8, csvName: 'wakata-pdf-deliveries', placeholder: 'Search order or email…', search: o => o.id + o.cust.email + o.cust.name, cols: [{ k: 'o', label: 'Order', html: o => `<a class="link-row" href="${orderHref(o)}">${o.id}</a>` }, { k: 'c', label: 'Send to', html: o => `${esc(o.cust.name)}<div class="xs muted">${esc(o.cust.email || 'No email')}</div>`, csv: o => o.cust.email }, { k: 'f', label: 'PDF files', num: true, html: o => o.items.filter(i => i.fmt === 'pdf').length }, { k: 's', label: 'Files', html: o => o.pay === 'paid' && !closed(o) ? `<span class="badge b-ok">In library</span>${o.emailedTs ? `<div class="xs muted">Emailed ${AD.fmtDate(o.emailedTs)}</div>` : ''}` : closed(o) ? `<span class="badge b-mute">${OS[o.status].label}</span>` : '<span class="badge b-warn">Waiting for payment</span>', csv: o => o.pay }, { k: 'd', label: 'Downloads', num: true, html: o => o.downloads }], actions: o => `<div class="row-actions"><button class="btn btn-line btn-sm" type="button" data-act="resend"${o.pay === 'paid' && !closed(o) && o.cust.email ? '' : ' disabled'}>${ic('send')}Resend</button></div>`,
      onAct: (a, o) => { if (a !== 'resend') return false; send(orderUrl(o, '/files'), 'POST', {}).then(d => { P2.render(); toast(esc(d.message)); }).catch(oops); return true; } });

    /* Options and fees */
    lock('#dlvSave,#dlvRows input', CAN.payments, NO_PAY);
    $('#dlvSave').addEventListener('click', () => {
      const body = {}; $$('#dlvRows tr').forEach(tr => { const g = f => $('[data-f="' + f + '"]', tr); body[tr.dataset.key] = { label: g('label').value.trim(), eta: g('eta').value.trim(), fee: Number(g('fee').value || 0), enabled: g('enabled').checked }; });
      ADM.send(SB + '/deliveries/options', 'PUT', body).then(d => { AD.sales.setDelivery(d.delivery); render(); toast('Delivery options saved.'); }).catch(oops);
    });
  };

  /* ================= MESSAGES ================= */
  P.messages = function () {
    let filter = 'all', cur = null; const msgs = AD.messages;
    const fl = { all: m => m.status !== 'archived', new: m => m.status === 'new', quote: m => m.topic === 'Bulk or school quotation', replied: m => m.status === 'replied' || m.status === 'quoted', archived: m => m.status === 'archived' };
    const tb = { 'Order or delivery': 'b-info', 'Bulk or school quotation': 'b-hl', 'Mock examinations': 'b-pur', 'Something else': 'b-mute' };
    function list() {
      const r = msgs.filter(fl[filter]); $('#threads').innerHTML = r.map(m => `<div class="thread ${m.status === 'new' ? 'unread' : ''} ${cur === m ? 'on' : ''}" data-id="${m.id}">${av(m.name, m.id.length)}<div><b>${esc(m.name.split(',')[0])}</b><p>${esc(m.body)}</p><span class="badge nodot ${tb[m.topic]}" style="margin-top:.3rem">${m.topic}</span> <span class="xs muted">${AD.rel(m.ts)}</span></div></div>`).join('') || '<div class="empty">No messages</div>';
      $$('[data-flt]').forEach(c => { const k = c.dataset.flt; c.querySelector('.n').textContent = msgs.filter(fl[k]).length; });
    }
    const thread = m => (m.thread || []).map(r => `<div class="card" style="padding:1rem;margin-bottom:.7rem;background:${r.from === 'staff' ? 'var(--surface)' : 'var(--surface-2)'};border-left:3px solid ${r.from === 'staff' ? 'var(--blue)' : 'var(--line)'}"><div class="xs muted" style="margin-bottom:.3rem">${esc(r.who || '')} · ${r.kind === 'quotation' ? 'Quotation sent · ' : ''}${AD.fmtDate(r.ts)} ${AD.fmtTime(r.ts)}</div><p style="margin:0;white-space:pre-line">${esc(r.body)}</p></div>`).join('');
    function read(m) {
      cur = m; if (m.status === 'new') { m.status = 'read'; if (m.kind === 'contact') ADM.send(ER.base + '/messages/' + enc(m.id) + '/read', 'POST').catch(() => { }); } const c = m.cust, os = m.orders || [];
      $('#reader').innerHTML = `<div class="flex-b wrapf" style="margin-bottom:1rem"><div><span class="badge nodot ${tb[m.topic]}">${m.topic}</span><h2 style="margin:.4rem 0 0">${esc(m.name)}</h2><span class="sm muted">${esc(m.email)} · ${m.phone} · ${AD.fmtDate(m.ts)} ${AD.fmtTime(m.ts)}</span></div><div class="flex"><button class="btn btn-ic" type="button" data-mact="new" aria-label="Mark unread" title="Mark as unread">${ic('mail')}</button><button class="btn btn-ic" type="button" data-mact="archived" aria-label="Archive" title="Archive">${ic('archive')}</button><button class="btn btn-ic" type="button" data-mact="delete" aria-label="Delete" title="Delete">${ic('trash')}</button></div></div><div class="body card" style="padding:1.2rem;background:var(--surface-2);margin-bottom:1rem"><p style="margin:0;white-space:pre-line">${esc(m.body)}</p></div>${thread(m)}${m.kind === 'contact' && m.topic === 'Bulk or school quotation' ? `<div class="callout info" style="margin-bottom:1rem">${ic('file')}<div style="flex:1"><b>Bulk quotation request</b><div class="sm muted">Build a priced quotation from your catalogue and email it to the sender.</div></div><button class="btn btn-primary btn-sm" type="button" data-open="quoteModal">Create quotation</button></div>` : ''}<div class="field"><label for="reply">Reply</label><textarea id="reply" placeholder="Write your reply…" style="min-height:130px"></textarea></div><div class="chips" style="margin-bottom:1rem"><span class="chip" data-qr="Thank you for contacting WAKATA. We have received your message and will respond with full details shortly.">Thanks, we will respond</span><span class="chip" data-qr="Your order is confirmed and will be ready within one working day. Please call us if anything changes.">Order confirmed</span><span class="chip" data-qr="Please find the quotation attached. Prices are valid for 14 days. Delivery across Kampala and Wakiso is UGX 8,000 per drop.">Quotation attached</span></div><div class="flex-b"><span class="xs muted">Replies are sent from info@wakatastationers.com</span><button class="btn btn-primary" type="button" data-send>${ic('send')}Send reply</button></div>`;
      $('#readerSide').innerHTML = `<h4>Sender</h4><div class="flex" style="margin-bottom:.8rem">${av(c.name)}<div><b>${esc(c.name)}</b><div class="xs muted">${c.type} · ${c.district}</div></div></div><dl class="dl" style="grid-template-columns:70px 1fr;margin-bottom:1rem"><dt>Phone</dt><dd>${c.phone}</dd><dt>Email</dt><dd style="word-break:break-all">${esc(c.email)}</dd></dl><h4>Their orders</h4>${os.length ? os.map(o => `<div class="flex-b sm" style="padding:.4rem 0;border-bottom:1px solid var(--line-2)"><a href="${ER.order}?id=${o.id}">${o.id}</a><span>${M(o.total)}</span></div>`).join('') : '<p class="muted sm">No orders yet.</p>'}<a class="btn btn-line btn-sm btn-block mt" href="https://wa.me/${c.phone.replace(/\D/g, '')}" target="_blank" rel="noopener">${ic('whatsapp')}Reply on WhatsApp</a>`;
      list();
    }
    list(); $('#threads').addEventListener('click', e => { const t = e.target.closest('.thread'); if (t) read(msgs.find(m => m.id === t.dataset.id)); });
    $$('[data-flt]').forEach(c => c.addEventListener('click', () => { $$('[data-flt]').forEach(x => x.classList.toggle('on', x === c)); filter = c.dataset.flt; list(); }));
    $('#reader').addEventListener('click', e => { const q = e.target.closest('[data-qr]'); if (q) { $('#reply').value = q.dataset.qr; } if (e.target.closest('[data-send]')) { if (!$('#reply').value.trim()) { toast('Write a reply first.', 'err'); return; } ADM.post(ER.base + '/messages/' + enc(cur.id) + '/reply', { body: $('#reply').value.trim() }); }
      const ma = e.target.closest('[data-mact]'); if (ma) { const k = ma.dataset.mact; if (k === 'delete') ADM.confirm({ title: 'Delete this message?', text: cur.kind === 'support' ? 'Customer conversations cannot be deleted. Archive it instead.' : 'It is removed from the inbox for good.', onOk: () => ADM.post(ER.base + '/messages/' + enc(cur.id), {}, 'DELETE') }); else ADM.post(ER.base + '/messages/' + enc(cur.id) + '/status', { status: k }); }
    });
    /* quotation: lines are typed or picked from the catalogue; the server totals them and emails the quotation */
    const qb = $('#qLines');
    if (qb) {
      const money = n => 'UGX ' + Math.round(n).toLocaleString();
      const total = () => { let sub = 0; $$('tr', qb).forEach(tr => { const t = (Number($('.q-qty', tr).value) || 0) * (Number($('.q-unit', tr).value) || 0); $('.q-tot', tr).textContent = money(t); sub += t; }); const fee = Number($('#qDel').value) || 0; $('#qTotals').innerHTML = `<div><span>Subtotal</span><span>${money(sub)}</span></div>${fee ? `<div><span>Delivery</span><span>${money(fee)}</span></div>` : ''}<div class="tot"><span>Quotation total</span><span>${money(sub + fee)}</span></div>`; };
      const addLine = () => { const tr = document.createElement('tr'); tr.innerHTML = `<td><input class="q-label" list="qItems" style="width:100%" placeholder="Choose or type an item" aria-label="Item"></td><td><input type="number" min="1" value="1" style="width:70px" class="price-in q-qty" aria-label="Quantity"></td><td class="num"><input type="number" min="0" value="0" class="price-in q-unit" aria-label="Unit price"></td><td class="num b q-tot">UGX 0</td><td><button class="btn btn-ic q-del" type="button" aria-label="Remove line">${ic('x')}</button></td>`; qb.appendChild(tr); total(); };
      $('#qItems').innerHTML = (E.catalogue || []).map(i => `<option value="${esc(i.label)}"></option>`).join('');
      $('#qDel').innerHTML = '<option value="0">No delivery line</option>' + (E.delivery || []).map(d => `<option value="${d.fee}">${esc(d.label)} (${money(d.fee)})</option>`).join('');
      qb.addEventListener('input', e => { if (e.target.classList.contains('q-label')) { const hit = (E.catalogue || []).find(i => i.label === e.target.value); if (hit) $('.q-unit', e.target.closest('tr')).value = hit.price; } total(); });
      qb.addEventListener('click', e => { if (e.target.closest('.q-del')) { e.target.closest('tr').remove(); total(); } });
      $('#qDel').addEventListener('change', total); $('#qAdd').addEventListener('click', addLine);
      document.addEventListener('click', e => { if (e.target.closest('[data-open="quoteModal"]')) { qb.innerHTML = ''; $('#qn').value = cur ? cur.name : ''; addLine(); } });
      $('#qSend').addEventListener('click', () => {
        const lines = $$('tr', qb).map(tr => ({ label: $('.q-label', tr).value.trim(), qty: $('.q-qty', tr).value, unit: $('.q-unit', tr).value })).filter(l => l.label);
        if (!lines.length) { toast('Add at least one item.', 'err'); return; }
        const f = { prepared: $('#qn').value, valid_until: $('#qv').value, delivery: $('#qDel').value, terms: $('#qt').value };
        lines.forEach((l, i) => { f['lines[' + i + '][label]'] = l.label; f['lines[' + i + '][qty]'] = l.qty; f['lines[' + i + '][unit]'] = l.unit; });
        ADM.close($('#quoteModal')); ADM.post(ER.base + '/messages/' + enc(cur.id) + '/quote', f);
      });
    }
    if (msgs[0]) read(msgs[0]); else { list(); $('#reader').innerHTML = '<div class="empty">No messages yet. Messages sent from the Contact page and questions from customer accounts appear here.</div>'; }
  };

  /* ================= SUBSCRIBERS ================= */
  P.subscribers = function () {
    const act = AD.subscribers.filter(s => s.status === 'active'), m30 = act.filter(s => s.ts > NOW - 30 * DAY).length;
    $('#subStats').innerHTML = [['Active subscribers', act.length, 'send', ''], ['New in 30 days', m30, 'plus', 'g'], ['Unsubscribed', AD.subscribers.length - act.length, 'x', 'r'], ['Newsletters sent', (E.newsletters || []).length, 'mail', 'y']].map(s => `<div class="card kpi mini"><div class="flex-b"><div><div class="kpi-l">${s[0]}</div><div class="kpi-v">${s[1]}</div></div><span class="kpi-ic ${s[3]}">${ic(s[2])}</span></div></div>`).join('');
    const b = AD.series(365).buckets; let run = 0; const cum = b.map(x => (run += AD.subscribers.filter(s => s.ts >= x.s && s.ts < x.e).length)); const base = AD.subscribers.filter(s => s.ts < b[0].s).length; C.line('#chSubs', { labels: b.map(x => x.label), series: [{ name: 'Subscribers', data: cum.map(v => v + base), color: 'var(--c1)' }], fmt: v => Math.round(v), height: 240 });
    const src = {}; act.forEach(s => src[s.source] = (src[s.source] || 0) + 1); C.donut('#chSrc', { items: Object.entries(src).map(([k, v], i) => ({ name: k, value: v, color: colors[[0, 2, 3, 4][i % 4]] })), centerLabel: 'Active', fmt: v => Math.round(v), size: 160 });
    ADM.table('#tbl', { rows: () => AD.subscribers, id: s => s.id, select: true, size: 10, csvName: 'wakata-subscribers', placeholder: 'Search email…', sort: 'date', dir: -1, tabs: { items: [{ k: 'all', label: 'All' }, { k: 'a', label: 'Active', fn: s => s.status === 'active' }, { k: 'u', label: 'Unsubscribed', fn: s => s.status !== 'active' }] }, search: s => s.email + s.source, filters: [{ k: 's', label: 'Source', all: 'All sources', options: ['Home page', 'Footer', 'Checkout', 'Contact page', 'Added by staff'].map(x => [x, x]), fn: (s, v) => s.source === v }], cols: [{ k: 'e', label: 'Email', sort: s => s.email, html: s => `<b>${esc(s.email)}</b>`, csv: s => s.email }, { k: 's', label: 'Signed up from', html: s => s.source }, { k: 'date', label: 'Subscribed', sort: s => s.ts, html: s => AD.fmtDate(s.ts), csv: s => AD.fmtDate(s.ts) }, { k: 'st', label: 'Status', sort: s => s.status, html: s => s.status === 'active' ? '<span class="badge b-ok">Active</span>' : '<span class="badge b-mute">Unsubscribed</span>', csv: s => s.status }], actions: s => `<div class="row-actions"><button class="btn btn-line btn-sm" type="button" data-act="toggle">${s.status === 'active' ? 'Unsubscribe' : 'Subscribe again'}</button><button class="btn btn-ic" type="button" data-act="delete" aria-label="Remove">${ic('trash')}</button></div>`,
      onAct: (a, s) => {
        if (a === 'toggle') { ADM.post(ER.base + '/subscribers/' + s.id + '/status', { status: s.status === 'active' ? 'unsubscribed' : 'active' }); return true; }
        if (a === 'delete') { ADM.confirm({ title: 'Remove this subscriber?', text: s.email + ' will be removed from the list.', onOk: () => ADM.post(ER.base + '/subscribers/' + s.id, {}, 'DELETE') }); return true; }
        return false;
      },
      bulk: [{ label: 'Send newsletter…', act: () => ADM.open('composeModal') }, { label: 'Unsubscribe', act: ids => ADM.post(ER.base + '/subscribers/bulk', { action: 'unsubscribe', 'ids[]': ids }) }, { label: 'Delete', act: ids => ADM.confirm({ title: 'Remove ' + ids.length + ' subscriber' + (ids.length === 1 ? '' : 's') + '?', text: 'They are removed from the list for good.', onOk: () => ADM.post(ER.base + '/subscribers/bulk', { action: 'delete', 'ids[]': ids }) }) }] });
    $('#addSubSave').addEventListener('click', () => { const v = $('#se').value.trim(); if (!v || !/^\S+@\S+\.\S+$/.test(v)) { toast('Enter a valid email address.', 'err'); return; } ADM.close($('#addSub')); ADM.post(ER.base + '/subscribers', { email: v }); });
    const news = test => { if (!$('#ns').value.trim() || !$('#nb').value.trim()) { toast('Write a subject and a message first.', 'err'); return; } const go = () => { ADM.close($('#composeModal')); ADM.post(ER.base + '/subscribers/send', { subject: $('#ns').value, body: $('#nb').value, audience: $('#na').value, test: test ? '1' : '' }); }; if (test) go(); else ADM.confirm({ title: 'Send this newsletter now?', text: 'It goes to everyone in the chosen audience, with an unsubscribe link. This cannot be undone.', ok: 'Send now', danger: false, icon: 'send', onOk: go }); };
    $('#nSendTest').addEventListener('click', () => news(true)); $('#nSendNow').addEventListener('click', () => news(false));
  };

  /* ================= TESTIMONIALS / FAQS (module 9: real) ================= */
  P.testimonials = function () {
    const T = E.testimonials, base = ER.base + '/testimonials', modal = $('#tModal');
    let editing = null;
    $('#tCards').innerHTML = T.map((t, i) => `<article class="card testi" data-id="${t.id}"><div class="flex-b"><div class="stars" aria-label="${t.stars} stars">${'★'.repeat(t.stars)}${'☆'.repeat(5 - t.stars)}</div><label class="switch sm"><input type="checkbox" data-vis ${t.visible ? 'checked' : ''} aria-label="Show on website"><span class="trk"></span><span class="t"><b class="xs">Visible</b></span></label></div><blockquote>${esc(t.quote)}</blockquote><div class="flex-b"><div class="flex"><span class="avatar sm av-${i % 6 + 1}">${esc(t.initials)}</span><div><b class="sm">${esc(t.role)}</b><div class="xs muted">${esc(t.place)}</div></div></div><div class="row-actions"><button class="btn btn-ic" type="button" data-edit aria-label="Edit">${ic('edit')}</button><button class="btn btn-ic" type="button" data-del aria-label="Delete">${ic('trash')}</button></div></div></article>`).join('') || '<div class="empty card" style="grid-column:1/-1">No testimonials yet. The “What teachers say” row stays hidden on the home page until one is visible.</div>';
    $('#tCount').textContent = T.length;
    const fill = t => { $('#tr').value = t ? t.stars : 5; $('#ti').value = t ? t.initials : ''; $('#tq').value = t ? t.quote : ''; $('#trl').value = t ? t.role : ''; $('#tp').value = t ? t.place : ''; $('#tperm').checked = !!(t && t.permission); };
    $$('[data-open="tModal"]').forEach(b => b.addEventListener('click', () => { editing = null; fill(null); }));
    $('#tCards').addEventListener('click', e => {
      const card = e.target.closest('.testi'); if (!card) return; const t = T.find(x => String(x.id) === card.dataset.id);
      if (e.target.closest('[data-edit]')) { editing = t; fill(t); ADM.open('tModal'); }
      if (e.target.closest('[data-del]')) ADM.confirm({ title: 'Delete this testimonial?', text: 'It will disappear from the home page.', onOk: () => ADM.post(base + '/' + t.id, {}, 'DELETE') });
    });
    $('#tCards').addEventListener('change', e => { const sw = e.target.closest('[data-vis]'); if (sw) ADM.post(base + '/' + sw.closest('.testi').dataset.id + '/toggle'); });
    $('#tSave').addEventListener('click', () => {
      if (!$('#tq').value.trim()) { toast('Write the quote first.', 'err'); return; }
      if (!$('#ti').value.trim()) { toast('Enter the initials.', 'err'); return; }
      if (!$('#tperm').checked) { toast('Please confirm the person agreed to be quoted.', 'err'); return; }
      ADM.close(modal);
      ADM.post(editing ? base + '/' + editing.id : base, { stars: $('#tr').value, initials: $('#ti').value, quote: $('#tq').value, role: $('#trl').value, place: $('#tp').value, permission: '1' }, editing ? 'PUT' : 'POST');
    });
  };
  P.faqs = function () {
    const F = E.faqs, base = ER.base + '/faqs', pages = E.faqPages;
    const iconOf = n => ({ store: 'box' }[n] || n);
    let editing = null;
    const groups = Object.keys(pages);
    $('#faqGroups').innerHTML = groups.map(g => { const items = F.filter(f => f.page === g); return `<section class="card" style="margin-bottom:1.1rem" data-page="${g}"><div class="card-h"><div><h2>${esc(pages[g])}</h2><span class="sub">${items.filter(f => f.visible).length} of ${items.length} questions shown on the website. Drag to change the order.</span></div><button class="btn btn-line btn-sm" type="button" data-add="${g}">${ic('plus')}Add question</button></div><div class="card-b flush" data-list>${items.map(f => `<div class="flex-b" draggable="true" data-id="${f.id}" style="padding:1rem 1.2rem;border-top:1px solid var(--line-2);align-items:flex-start;${f.visible ? '' : 'opacity:.55'}"><div class="flex" style="align-items:flex-start;flex:1"><span class="grip" title="Drag to reorder">${ic('drag')}</span><span class="kpi-ic" style="width:34px;height:34px;flex:none">${ic(iconOf(f.icon))}</span><div><b>${esc(f.q)}</b><p class="sm muted mb0">${esc(f.a)}</p></div></div><div class="flex"><label class="switch sm"><input type="checkbox" data-vis ${f.visible ? 'checked' : ''} aria-label="Show on website"><span class="trk"></span></label><button class="btn btn-ic" type="button" data-edit aria-label="Edit">${ic('edit')}</button><button class="btn btn-ic" type="button" data-del aria-label="Delete">${ic('trash')}</button></div></div>`).join('') || '<div class="empty">No questions yet.</div>'}</div></section>`; }).join('');
    const fill = (f, page) => { $('#fp').value = f ? f.page : page; $('#fq').value = f ? f.q : ''; $('#fa').value = f ? f.a : ''; $('#fi').value = f ? f.icon : 'help'; };
    const host = $('#faqGroups');
    host.addEventListener('click', e => {
      const add = e.target.closest('[data-add]'); if (add) { editing = null; fill(null, add.dataset.add); ADM.open('faqModal'); return; }
      const row = e.target.closest('[data-id]'); if (!row) return; const f = F.find(x => String(x.id) === row.dataset.id);
      if (e.target.closest('[data-edit]')) { editing = f; fill(f); ADM.open('faqModal'); }
      if (e.target.closest('[data-del]')) ADM.confirm({ title: 'Delete this question?', text: 'It disappears from the website.', onOk: () => ADM.post(base + '/' + f.id, {}, 'DELETE') });
    });
    host.addEventListener('change', e => { const sw = e.target.closest('[data-vis]'); if (sw) ADM.post(base + '/' + sw.closest('[data-id]').dataset.id + '/toggle'); });
    $('#faqSave').addEventListener('click', () => {
      if (!$('#fq').value.trim() || !$('#fa').value.trim()) { toast('Enter the question and the answer.', 'err'); return; }
      ADM.close($('#faqModal'));
      ADM.post(editing ? base + '/' + editing.id : base, { page: $('#fp').value, question: $('#fq').value, answer: $('#fa').value, icon: $('#fi').value }, editing ? 'PUT' : 'POST');
    });
    /* drag to reorder inside one page's list; the new order is saved straight away */
    let drag = null;
    host.addEventListener('dragstart', e => { drag = e.target.closest('[data-id]'); if (drag && e.dataTransfer) { e.dataTransfer.effectAllowed = 'move'; try { e.dataTransfer.setData('text/plain', drag.dataset.id); } catch (x) { } } });
    host.addEventListener('dragover', e => { const over = e.target.closest('[data-id]'); if (!drag || !over || over === drag || over.closest('[data-page]') !== drag.closest('[data-page]')) return; e.preventDefault(); const r = over.getBoundingClientRect(); over.parentNode.insertBefore(drag, e.clientY < r.top + r.height / 2 ? over : over.nextSibling); });
    host.addEventListener('dragend', () => {
      if (!drag) return; const sec = drag.closest('[data-page]'); const ids = [...sec.querySelectorAll('[data-id]')].map(x => Number(x.dataset.id)); drag = null;
      ADM.send(base + '/reorder', 'POST', { page: sec.dataset.page, ids }).then(() => toast('Order saved.')).catch(err => toast(err.message, 'err'));
    });
  };

  /* ================= REPORTS ================= */
  P.reports = function () {
    let days = 90;
    function draw() {
      const cur = AD.within(days), S = AD.series(days).buckets;
      $('#repKpis').innerHTML = [['Revenue', M(AD.sum(cur, o => o.total)), 'wallet', ''], ['Orders', cur.length, 'cart', 'y'], ['Units sold', units(cur, () => true).toLocaleString(), 'box', 'g'], ['Average order', M(cur.length ? Math.round(AD.sum(cur, o => o.total) / cur.length) : 0), 'trend', 'p']].map(s => `<div class="card kpi mini"><div class="flex-b"><div><div class="kpi-l">${s[0]}</div><div class="kpi-v" style="font-size:1.2rem">${s[1]}</div></div><span class="kpi-ic ${s[3]}">${ic(s[2])}</span></div></div>`).join('');
      C.line('#chRev', { labels: S.map(b => b.label), series: [{ name: 'Revenue', data: S.map(b => b.rev), color: 'var(--c1)' }], tipFmt: M, height: 270 });
      C.bar('#chOrd', { labels: S.map(b => b.label), series: [{ name: 'Orders', data: S.map(b => b.orders), color: 'var(--c7)' }], height: 270, fmt: v => Math.round(v) });
      const lv = AD.byLevel(cur); C.donut('#chLv', { items: [{ name: 'Primary (PLE)', value: lv.ple, color: 'var(--c2)' }, { name: 'O-Level (UCE)', value: lv.uce, color: 'var(--c1)' }, { name: 'A-Level (UACE)', value: lv.uace, color: 'var(--c3)' }], centerLabel: 'By level', tipFmt: M, size: 170 });
      const bs = AD.bySubject(cur).slice(0, 10); C.bar('#chSubj', { labels: bs.map(x => x[0]), series: [{ name: 'Sales', data: bs.map(x => x[1]), color: 'var(--c6)' }], horizontal: true, tipFmt: M, height: 360 });
      const pr = units(cur, i => i.fmt !== 'pdf' && i.type === 'paper'), pd = units(cur, i => i.fmt === 'pdf'); C.donut('#chFmt', { items: [{ name: 'Printed papers', value: pr, color: 'var(--c1)' }, { name: 'PDF papers', value: pd, color: 'var(--c3)' }], centerLabel: 'Past papers sold', fmt: v => Math.round(v).toLocaleString(), size: 170 });
    }
    draw(); $('#repSeg').addEventListener('seg', e => { days = +e.detail; draw(); funnel(days); links(days); });
    const y = AD.series(365).buckets, cats = y.map(b => AD.byCategory(AD.orders.filter(o => AD.counted(o) && o.ts >= b.s && o.ts < b.e)));
    C.bar('#chCat', { labels: y.map(b => b.label), series: [['Books', 'book', 'var(--c1)'], ['Past papers', 'paper', 'var(--c3)'], ['Exam sets', 'set', 'var(--c2)'], ['Holiday packages', 'pack', 'var(--c4)']].map(s => ({ name: s[0], data: cats.map(c => c[s[1]]), color: s[2] })), stacked: true, tipFmt: M, height: 280 });
    const first = {}; AD.orders.slice().reverse().forEach(o => { if (!(o.custId in first)) first[o.custId] = o.ts; });
    const nw = y.map(b => new Set(AD.orders.filter(o => o.ts >= b.s && o.ts < b.e && first[o.custId] === o.ts).map(o => o.custId)).size), rt = y.map((b, i) => new Set(AD.orders.filter(o => o.ts >= b.s && o.ts < b.e).map(o => o.custId)).size - nw[i]);
    C.bar('#chRet', { labels: y.map(b => b.label), series: [{ name: 'New customers', data: nw, color: 'var(--c5)' }, { name: 'Returning customers', data: rt.map(v => Math.max(0, v)), color: 'var(--c1)' }], stacked: true, height: 260, fmt: v => Math.round(v) });
    const funnel = d => {
      const os = AD.orders.filter(o => o.ts >= NOW - d * DAY), fn = [['Orders placed', os.length], ['Accepted by the shop', os.filter(o => o.status !== 'pending' && o.status !== 'cancelled').length], ['Paid', os.filter(o => o.pay === 'paid').length], ['Completed', os.filter(o => o.status === 'completed').length]], top = Math.max(1, fn[0][1]);
      $('#funnel').innerHTML = fn.map(f => `<div class="fr"><span class="sm">${f[0]}</span><div><div class="fb" style="width:${f[1] / top * 100}%;min-width:64px">${f[1].toLocaleString()}</div></div><span class="sm muted tr">${(f[1] / top * 100).toFixed(1)}%</span></div>`).join('');
    };
    const links = d => $$('a[data-rep]').forEach(a => { a.href = a.href.replace(/days=\d+/, 'days=' + d); });
    funnel(days);
    const top = AD.customers.slice().sort((a, b) => b.spent - a.spent).slice(0, 6); $('#repTop').innerHTML = top.map(c => `<tr><td>${esc(c.name)}</td><td class="num">${c.orders}</td><td class="num"><b>${M(c.spent)}</b></td></tr>`).join('');
  };

  /* ================= TEAM / ACTIVITY ================= */
  P.team = function () {
    const T = WK.ins, can = T.can.manage, base = T.routes.base, badge = { super_admin: 'b-bad', manager: 'b-info', editor: 'b-pur', support: 'b-ok' };
    const status = u => u.status === 'active' ? '<span class="badge b-ok">Active</span>' : u.status === 'invited' ? '<span class="badge b-warn">Invite sent</span>' : '<span class="badge b-bad">Suspended</span>';
    ADM.table('#tbl', { rows: () => AD.team, id: u => u.id, size: 10, csvName: 'wakata-team', placeholder: 'Search team…', search: u => u.name + ' ' + u.email + ' ' + u.roleLabel, empty: 'No team members yet',
      cols: [
        { k: 'n', label: 'Team member', sort: u => u.name, html: u => `<div class="flex"><span class="avatar ${u.color}">${AD.initials(u.name)}</span><div><b>${esc(u.name)}</b>${u.self ? ' <span class="badge nodot b-mute">You</span>' : ''}<div class="xs muted">${esc(u.email)}</div></div></div>`, csv: u => u.name },
        { k: 'r', label: 'Role', sort: u => u.roleLabel, html: u => `<span class="badge nodot ${badge[u.role] || 'b-mute'}">${esc(u.roleLabel)}</span>`, csv: u => u.roleLabel },
        { k: 's', label: 'Status', html: status, csv: u => u.status },
        { k: 'l', label: 'Last sign-in', sort: u => u.last, html: u => u.last ? AD.rel(u.last) : 'Never', csv: u => u.last ? AD.fmtDate(u.last) : '' },
        { k: '2', label: 'Two-step', html: u => u.twoFactor ? '<span class="badge b-ok">On</span>' : '<span class="badge b-mute">Off</span>', csv: u => u.twoFactor ? 'on' : 'off' }],
      actions: can ? u => `<div class="row-actions">${u.status === 'invited' ? `<button class="btn btn-ic" type="button" data-act="invite" aria-label="Send the invitation again" title="Send the invitation again">${ic('mail')}</button>` : ''}<button class="btn btn-ic" type="button" data-act="edit" aria-label="Change">${ic('edit')}</button>${u.self ? '' : `<button class="btn btn-ic" type="button" data-act="delete" aria-label="Remove">${ic('trash')}</button>`}</div>` : null,
      onAct: (a, u) => {
        if (!u) return false;
        if (a === 'edit') {
          $('#editForm').action = base + '/' + u.id; $('#en').value = u.name; $('#er').value = u.role; $('#es').value = u.status === 'suspended' ? 'suspended' : 'active';
          $('#editEmail').textContent = u.email;
          $('#editHint').textContent = u.self ? 'You cannot change your own role or suspend yourself. Ask another super admin.' : u.status === 'invited' ? 'They have not chosen a password yet. The account becomes active when they do.' : '';
          ADM.open('editModal'); return true;
        }
        if (a === 'invite') { ADM.post(base + '/' + u.id + '/invite', {}); return true; }
        if (a === 'delete') { ADM.confirm({ title: 'Remove ' + u.name + '?', text: 'They will not be able to sign in any more. What they did stays in the activity log.', ok: 'Remove', icon: 'trash', onOk: () => ADM.post(base + '/' + u.id, {}, 'DELETE') }); return true; }
        return false;
      } });
  };
  P.activity = function () {
    const mods = (WK.ins && WK.ins.areas) || {}, names = [...new Set(AD.activity.map(a => a.user.name))].sort();
    ADM.table('#tbl', { rows: () => AD.activity, id: a => a.id, size: 12, csvName: 'wakata-activity-log', placeholder: 'Search activity…', search: a => a.user.name + ' ' + a.verb + ' ' + a.obj + ' ' + a.ip, empty: 'Nothing has been logged yet',
      filters: [{ k: 'u', label: 'User', all: 'All team members', options: names.map(n => [n, n]), fn: (a, v) => a.user.name === v }, { k: 'm', label: 'Area', all: 'All areas', options: Object.entries(mods), fn: (a, v) => a.module === v }],
      cols: [{ k: 't', label: 'When', sort: a => a.ts, html: a => `${AD.fmtDate(a.ts)}<div class="xs muted">${AD.fmtTime(a.ts)}</div>`, csv: a => AD.fmtDate(a.ts) + ' ' + AD.fmtTime(a.ts) }, { k: 'u', label: 'User', html: a => `<div class="flex"><span class="avatar sm ${a.user.color}">${AD.initials(a.user.name)}</span>${esc(a.user.name)}</div>`, csv: a => a.user.name }, { k: 'a', label: 'Action', html: a => `<span class="fd" style="display:inline-grid;width:26px;height:26px;border-radius:50%;background:var(--tint);color:var(--blue);place-items:center;vertical-align:middle;margin-right:.4rem">${ic(a.icon)}</span>${esc(a.verb)} ${a.obj ? '<b>' + esc(a.obj) + '</b>' : ''}`, csv: a => a.verb + ' ' + a.obj }, { k: 'm', label: 'Area', html: a => `<span class="badge nodot b-mute">${esc(mods[a.module] || a.module)}</span>`, csv: a => mods[a.module] || a.module }, { k: 'ip', label: 'IP address', html: a => `<code class="xs">${esc(a.ip)}</code>` }] });
  };

  /* ================= SETTINGS / PROFILE / AUTH ================= */
  P.settings = function () {
    const t = $('#tfa'); if (!t) return;
    t.addEventListener('change', () => {
      const want = t.checked, token = (document.querySelector('meta[name="csrf-token"]') || {}).content || '';
      fetch(t.dataset.url, { method: 'POST', headers: { 'X-CSRF-TOKEN': token, 'Accept': 'application/json', 'Content-Type': 'application/json' }, body: JSON.stringify({ enabled: want }) })
        .then(r => { if (!r.ok) throw new Error('bad'); toast(want ? 'Two-step verification is on. You will be asked for a code at sign-in.' : 'Two-step verification is off.'); })
        .catch(() => { t.checked = !want; toast('Could not update this setting. Please try again.', 'err'); });
    });
  };
  P.profile = P.settings;
  P.verify = function () {
    const ins = $$('.otp input'); ins[0].focus();
    ins.forEach((i, n) => { i.addEventListener('input', () => { i.value = i.value.replace(/\D/g, '').slice(0, 1); if (i.value && ins[n + 1]) ins[n + 1].focus(); }); i.addEventListener('keydown', e => { if (e.key === 'Backspace' && !i.value && ins[n - 1]) ins[n - 1].focus(); }); i.addEventListener('paste', e => { const d = (e.clipboardData.getData('text') || '').replace(/\D/g, '').slice(0, 6); if (!d) return; e.preventDefault(); d.split('').forEach((c, k) => ins[k] && (ins[k].value = c)); (ins[Math.min(d.length, 5)]).focus(); }); });
    const form = $('#otpForm');
    form.addEventListener('submit', e => { e.preventDefault(); const code = ins.map(i => i.value).join(''); if (code.length < 6) { toast('Enter the 6-digit code.', 'err'); return; } $('#otpCode').value = code; form.submit(); });
    $('#resend').addEventListener('click', e => {
      e.preventDefault(); const token = (document.querySelector('meta[name="csrf-token"]') || {}).content || '';
      fetch(e.currentTarget.dataset.url, { method: 'POST', headers: { 'X-CSRF-TOKEN': token, 'Accept': 'application/json' } })
        .then(r => r.json().then(j => ({ ok: r.ok, j })))
        .then(x => toast(x.j.message || 'Done.', x.ok ? undefined : 'err'))
        .catch(() => toast('Could not send a new code. Please try again.', 'err'));
    });
  };
  if (P[page]) P[page]();
})();
