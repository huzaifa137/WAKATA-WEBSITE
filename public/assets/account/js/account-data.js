/* ==========================================================
   WAKATA customer account: sample data layer (front-end only)
   Exposes window.AD with the same helpers the admin console uses,
   so account.js (a copy of admin.js) works unchanged.
   Orders, downloads, messages are SAMPLES built from the real
   catalogue. A freshly registered user (user.fresh) sees empty states.
   ========================================================== */
window.AD = (function () {
  const WK = window.WK, NOW = Date.now(), DAY = 864e5, money = WK.money;
  const user = WK.user || {}; /* the signed-in customer, set by Laravel */
  const fresh = !!user.fresh;
  const ORDER_STATUS = { pending: { label: 'Pending', cls: 'b-warn' }, confirmed: { label: 'Confirmed', cls: 'b-info' }, processing: { label: 'Being prepared', cls: 'b-pur' }, ready: { label: 'Ready for pickup', cls: 'b-info' }, shipped: { label: 'Out for delivery', cls: 'b-pur' }, completed: { label: 'Completed', cls: 'b-ok' }, cancelled: { label: 'Cancelled', cls: 'b-mute' }, refunded: { label: 'Refunded', cls: 'b-bad' } };
  const PAY_STATUS = { paid: { label: 'Paid', cls: 'b-ok' }, pending: { label: 'Awaiting payment', cls: 'b-warn' }, failed: { label: 'Failed', cls: 'b-bad' }, refunded: { label: 'Refunded', cls: 'b-mute' } };
  const PAY_METHODS = { mtn: 'MTN Mobile Money', airtel: 'Airtel Money', card: 'Visa or Mastercard', cash: 'Cash on pickup' };
  const DELIVERY = { pickup: { label: 'Pick up from our shop', fee: 0, eta: 'Ready within one working day' }, kampala: { label: 'Kampala and Wakiso', fee: 8000, eta: '1 to 2 days' }, upcountry: { label: 'Upcountry courier', fee: 15000, eta: '2 to 4 days' }, email: { label: 'Email delivery (PDF)', fee: 0, eta: 'On payment confirmation' } };
  const LV = { primary: 'PLE', o: 'UCE', a: 'UACE', ple: 'PLE', uce: 'UCE', uace: 'UACE' };
  const TYPE_LABEL = { book: 'Book', paper: 'Past paper', set: 'Exam set', pack: 'Holiday package' };
  const slug = s => s.toLowerCase().replace(/&/g, '').replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '');
  const findPaper = (subject, no, year) => WK.papers.find(x => x.level === 'uce' && x.subject === subject && x.paper === 'Paper ' + no && x.year === year) || WK.papers.find(x => x.level === 'uce' && x.subject === subject && x.paper === 'Paper ' + no) || WK.papers.find(x => x.subject === subject);
  const P = (s, n, y, f, q) => ['paper', findPaper(s, n, y), f, q || 1];
  const SPECS = [
    { id: 10482, d: 0.3, st: 'processing', pay: 'paid', m: 'mtn', dl: 'kampala', it: [['book', 'chem-uce-practical', null, 2], P('Chemistry', 2, 2026, 'print', 12)] },
    { id: 10471, d: 2, st: 'completed', pay: 'paid', m: 'airtel', dl: 'email', it: [P('Biology', 1, 2026, 'pdf'), P('Biology', 2, 2026, 'pdf'), P('Physics', 1, 2026, 'pdf')] },
    { id: 10463, d: 6, st: 'shipped', pay: 'paid', m: 'mtn', dl: 'kampala', it: [['set', 'set-uce-2026', null, 1]] },
    { id: 10431, d: 19, st: 'completed', pay: 'paid', m: 'cash', dl: 'pickup', it: [['pack', 'hp-s4', null, 1]] },
    { id: 10388, d: 41, st: 'completed', pay: 'paid', m: 'mtn', dl: 'email', it: [P('Mathematics', 1, 2025, 'pdf'), P('Mathematics', 2, 2025, 'pdf'), P('Mathematics', 1, 2024, 'pdf')] },
    { id: 10302, d: 75, st: 'completed', pay: 'paid', m: 'card', dl: 'upcountry', it: [['book', 'math-uce-revision', null, 3], ['book', 'phy-uclse-new', null, 3]] },
    { id: 10215, d: 120, st: 'completed', pay: 'paid', m: 'airtel', dl: 'pickup', it: [P('English', 1, 2026, 'print', 30)] },
    { id: 10144, d: 210, st: 'cancelled', pay: 'failed', m: 'mtn', dl: 'kampala', it: [['set', 'set-uce-maths-5y', null, 1]] },
    { id: 10052, d: 300, st: 'completed', pay: 'paid', m: 'mtn', dl: 'email', it: [['book', 'ent-uce', null, 1], P('History', 1, 2025, 'pdf')] }
  ];
  const phone = user.phone || '+256 772 123 456';
  function resolve(spec) {
    const [type, ref, fmt, qty] = spec; let r = typeof ref === 'string' ? WK.byId(type === 'book' ? WK.books : type === 'set' ? WK.sets : WK.packages, ref) : ref;
    if (type === 'book') return { type, id: r.id, fmt: null, qty, price: r.price, title: r.title, level: LV[r.level], subject: r.subject };
    if (type === 'paper') return { type, id: r.id, fmt, qty, price: fmt === 'pdf' ? r.pdf : r.print, title: r.title + ' ' + r.year, level: LV[r.level], subject: r.subject, year: r.year, paperNo: r.paper.replace('Paper ', '') };
    if (type === 'set') return { type, id: r.id, fmt: null, qty, price: r.price, title: r.title, level: LV[r.level], subject: null };
    return { type, id: r.id, fmt: null, qty, price: r.price, title: r.name, level: LV[r.level], subject: null };
  }
  const orders = fresh ? [] : SPECS.map(s => {
    const items = s.it.map(resolve), sub = items.reduce((a, i) => a + i.price * i.qty, 0), fee = DELIVERY[s.dl].fee, ts = NOW - s.d * DAY;
    return { id: 'WK-' + s.id, ts, status: s.st, pay: s.pay, method: s.m, delivery: s.dl, items, sub, fee, total: sub + fee, txn: s.m === 'cash' ? '' : (s.m === 'mtn' ? 'MTN' : s.m === 'airtel' ? 'ATL' : 'CRD') + (30000000 + s.id * 137), cust: { name: user.name || 'Customer' } };
  });
  const counted = o => o.pay === 'paid' && o.status !== 'cancelled' && o.status !== 'refunded';
  /* ---- library derived from paid orders ---- */
  const files = [], hash = s => [...s].reduce((a, c) => a + c.charCodeAt(0), 0);
  orders.filter(counted).forEach(o => o.items.forEach(i => {
    if (i.type === 'paper' && i.fmt === 'pdf') files.push({ id: o.id + ':' + i.id, kind: 'paper', title: i.title, subject: i.subject, level: i.level, year: i.year, name: slug(i.subject) + '-paper' + i.paperNo + '-' + i.year + '.pdf', order: o.id, ts: o.ts, size: (0.8 + hash(i.id) % 12 / 10).toFixed(1), used: hash(i.id) % 4, max: 5 });
    if (i.type === 'set' && (o.status === 'shipped' || o.status === 'completed')) files.push({ id: o.id + ':' + i.id + ':g', kind: 'guide', title: 'Marking guides: ' + i.title, subject: 'All subjects', level: i.level, year: 2026, name: slug(i.title) + '-marking-guides.pdf', order: o.id, ts: o.ts, size: '3.4', used: 0, max: 5 });
    if (i.type === 'pack' && o.status === 'completed') files.push({ id: o.id + ':' + i.id + ':t', kind: 'guide', title: 'Revision timetable: ' + i.title, subject: 'All subjects', level: i.level, year: 2026, name: slug(i.title) + '-timetable.pdf', order: o.id, ts: o.ts, size: '0.4', used: 1, max: 5 });
  }));
  files.sort((a, b) => b.ts - a.ts);
  /* ---- support threads ---- */
  const o0 = orders[0] ? orders[0].id : '', o2 = orders[2] ? orders[2].id : '';
  const messages = fresh ? [] : [
    { id: 'T-301', subject: 'Can the invoice carry our school stamp?', topic: 'Order or delivery', status: 'new', order: o2, ts: NOW - 5 * 36e5, msgs: [{ from: 'you', text: 'Good afternoon. Please can the invoice for ' + o2 + ' carry our school stamp? Our bursar needs it for accounts.', ts: NOW - 30 * 36e5 }, { from: 'wakata', text: 'Hello, yes. Every invoice now has a stamp area. Open Invoices and receipts, choose the order and print it, then stamp it at school. If you want us to stamp it, collect it from the shop.', ts: NOW - 5 * 36e5 }] },
    { id: 'T-288', subject: 'Quotation for 120 practical workbooks', topic: 'Bulk or school quotation', status: 'answered', order: '', ts: NOW - 9 * DAY, msgs: [{ from: 'you', text: 'Please send a quotation for 120 copies of the UCE Chemistry practical workbook, delivered to Mukono.', ts: NOW - 10 * DAY }, { from: 'wakata', text: 'Quotation sent to your email. Prices are valid for 14 days and delivery to Mukono is UGX 15,000.', ts: NOW - 9 * DAY }] },
    { id: 'T-250', subject: 'Delivery to Mukono on Friday', topic: 'Order or delivery', status: 'closed', order: o0, ts: NOW - 40 * DAY, msgs: [{ from: 'you', text: 'Can you deliver to Mukono on Friday?', ts: NOW - 41 * DAY }, { from: 'wakata', text: 'Yes, our courier will bring it on Friday morning.', ts: NOW - 40 * DAY }] }
  ];
  const notifications = fresh ? [{ icon: 'gift', t: 'Welcome to WAKATA', s: 'Browse papers, books and holiday packages', ts: NOW - 6e4 }] : [
    { icon: 'truck', t: 'Order ' + (orders[2] || {}).id + ' is out for delivery', s: 'Expected today or tomorrow', ts: NOW - 3 * 36e5 },
    { icon: 'download', t: 'PDFs ready to download', s: 'Biology Paper 1 and Paper 2 2026', ts: NOW - 2 * DAY },
    { icon: 'mail', t: 'WAKATA replied to your question', s: 'Can the invoice carry our school stamp?', ts: NOW - 5 * 36e5 },
    { icon: 'layers', t: 'New: UCE Mock Series 2026', s: 'Complete set with marking guides', ts: NOW - 4 * DAY }
  ];
  const addresses = fresh ? [] : [
    { id: 1, label: 'School', name: user.name || 'Joseph Ssemakula', line: (user.school || 'Green Hill Secondary School') + ', Mukono Road', district: 'Mukono', phone: phone, def: true },
    { id: 2, label: 'Home', name: user.name || 'Joseph Ssemakula', line: 'Plot 14, Ntinda Estate', district: 'Kampala', phone: phone, def: false }
  ];
  /* ---- helpers ---- */
  const pad = x => String(x).padStart(2, '0');
  const fmtDate = ts => new Date(ts).toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' });
  const fmtTime = ts => { const d = new Date(ts); return pad(d.getHours()) + ':' + pad(d.getMinutes()); };
  const rel = ts => { const s = Math.max(1, (NOW - ts) / 1000); if (s < 60) return 'just now'; if (s < 3600) return Math.floor(s / 60) + ' min ago'; if (s < 86400) return Math.floor(s / 3600) + ' h ago'; if (s < 2592000) return Math.floor(s / 86400) + ' d ago'; return fmtDate(ts); };
  const img = p => p ? '../' + p : '';
  const initials = s => String(s || '?').split(/\s+/).filter(Boolean).slice(0, 2).map(w => w[0]).join('').toUpperCase();
  const esc = s => String(s == null ? '' : s).replace(/[&<>"]/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]));
  const sum = (a, f) => a.reduce((x, y) => x + f(y), 0);
  const genThumb = (subject, cls) => { const c = WK.SUBJECTS[subject] || WK.SUBJECTS.Mathematics; return `<div class="thumb ${cls || ''}"><div class="cv" style="--c1:${c[0]};--c2:${c[1]};--c3:${c[2]}"><span class="sj">${esc(subject)}</span></div></div>`; };
  const recOf = (type, id) => WK.byId(type === 'book' ? WK.books : type === 'paper' ? WK.papers : type === 'set' ? WK.sets : WK.packages, id);
  const thumbOf = (type, r) => { if (type === 'book' || type === 'paper') return r.img ? `<div class="thumb"><img src="${img(r.img)}" alt="" loading="lazy"></div>` : genThumb(r.subject); if (type === 'set') return `<div class="thumb"><img src="${img(WK.PAPER_IMG + r.preview[0])}" alt="" loading="lazy"></div>`; return genThumb('Literacy'); };
  const thumbById = (type, id) => { const r = recOf(type, id); return r ? thumbOf(type, r) : genThumb('Mathematics'); };
  /* ---- aggregates ---- */
  function monthly() { const d0 = new Date(NOW), out = []; for (let i = 11; i >= 0; i--) { const s = new Date(d0.getFullYear(), d0.getMonth() - i, 1), e = new Date(d0.getFullYear(), d0.getMonth() - i + 1, 1), os = orders.filter(o => counted(o) && o.ts >= +s && o.ts < +e); out.push({ label: s.toLocaleString('en', { month: 'short' }), print: sum(os, o => sum(o.items.filter(i => i.fmt !== 'pdf'), i => i.price * i.qty) + o.fee), pdf: sum(os, o => sum(o.items.filter(i => i.fmt === 'pdf'), i => i.price * i.qty)) }); } return out; }
  function byCategory() { const r = { book: 0, paper: 0, set: 0, pack: 0 }; orders.filter(counted).forEach(o => o.items.forEach(i => r[i.type] += i.price * i.qty)); return r; }
  return { WK, user, fresh, NOW, DAY, orders, files, messages, notifications, addresses, customers: [], ORDER_STATUS, PAY_STATUS, PAY_METHODS, DELIVERY, LV, TYPE_LABEL, counted, monthly, byCategory, sum, fmtDate, fmtTime, rel, img, initials, esc, thumbOf, thumbById, genThumb, recOf, money, slug };
})();
