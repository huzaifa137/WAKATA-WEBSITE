/* ==========================================================
   WAKATA Admin: sample data layer (front-end only)
   Builds on the website catalogue (../assets/js/data.js -> WK).
   Orders, customers, messages etc. are GENERATED SAMPLES so the
   charts and tables have something to show. Replace with Laravel
   queries / API responses once the backend exists.
   ========================================================== */
window.AD = (function () {
  const WK = window.WK;
  let seed = 20261010;
  const rnd = () => { seed |= 0; seed = seed + 0x6D2B79F5 | 0; let t = Math.imul(seed ^ seed >>> 15, 1 | seed); t = t + Math.imul(t ^ t >>> 7, 61 | t) ^ t; return ((t ^ t >>> 14) >>> 0) / 4294967296; };
  const pick = a => a[Math.floor(rnd() * a.length)];
  const wpick = (items, weights) => { let r = rnd() * weights.reduce((a, b) => a + b, 0); for (let i = 0; i < items.length; i++) { r -= weights[i]; if (r <= 0) return items[i]; } return items[items.length - 1]; };
  const int = (a, b) => a + Math.floor(rnd() * (b - a + 1));
  const DAY = 864e5, NOW = Date.now();
  const money = WK.money;

  /* ---------- lookups ---------- */
  const ORDER_STATUS = {
    pending: { label: 'Pending', cls: 'b-warn' }, confirmed: { label: 'Confirmed', cls: 'b-info' }, processing: { label: 'Processing', cls: 'b-pur' },
    ready: { label: 'Ready for pickup', cls: 'b-info' }, shipped: { label: 'Out for delivery', cls: 'b-pur' }, completed: { label: 'Completed', cls: 'b-ok' },
    cancelled: { label: 'Cancelled', cls: 'b-mute' }, refunded: { label: 'Refunded', cls: 'b-bad' }
  };
  const PAY_STATUS = { paid: { label: 'Paid', cls: 'b-ok' }, pending: { label: 'Awaiting payment', cls: 'b-warn' }, failed: { label: 'Failed', cls: 'b-bad' }, refunded: { label: 'Refunded', cls: 'b-mute' } };
  const PAY_METHODS = { mtn: 'MTN Mobile Money', airtel: 'Airtel Money', card: 'Visa or Mastercard', cash: 'Cash on pickup' };
  const DELIVERY = { pickup: { label: 'Pick up from our shop', fee: 0, eta: 'Ready within one working day' }, kampala: { label: 'Kampala and Wakiso', fee: 8000, eta: '1 to 2 days' }, upcountry: { label: 'Upcountry courier', fee: 15000, eta: '2 to 4 days' }, email: { label: 'Email delivery (PDF)', fee: 0, eta: 'On payment confirmation' } };
  const LVL = { primary: 'ple', o: 'uce', a: 'uace', ple: 'ple', uce: 'uce', uace: 'uace' };
  const LVL_LABEL = { ple: 'PLE', uce: 'UCE', uace: 'UACE' };
  const TYPE_LABEL = { book: 'Book', paper: 'Past paper', set: 'Exam set', pack: 'Holiday package' };

  /* ---------- enrich catalogue (stock, sku, status) ---------- */
  /* Module 7: the catalogue pages get the REAL catalogue from Laravel (WK.adm: drafts included, real SKU, stock, formats, files).
     Other admin pages still use the sample values below until their own module is built. */
  if (WK.adm) {
    ['books', 'papers', 'sets', 'packages'].forEach(k => { WK[k] = WK.adm[k]; });
  } else {
    WK.books.forEach((b, i) => { b.sku = 'BK-' + String(100 + i); b.stock = [64, 12, 34, 58, 140, 220, 86, 9, 310, 47, 18][i % 11] + (i % 3) * 7; b.low = 20; b.status = 'published'; b.featured = i < 4; });
    if (WK.books[7]) WK.books[7].status = 'draft';
    WK.papers.forEach((p, i) => { p.pdfOn = true; p.printOn = true; p.hasFile = (i % 9) !== 4; p.printStock = [0, 40, 120, 260, 75, 18][i % 6] + 10; p.status = (i % 23 === 5) ? 'draft' : 'published'; if (!p.hasFile && i % 18 === 4) p.pdfOn = false; });
    WK.sets.forEach((s, i) => { s.sku = 'SET-' + (200 + i); s.stock = [24, 18, 60, 41, 7, 33][i]; s.status = 'published'; s.printOn = true; s.pdfOn = false; });
    WK.packages.forEach((k, i) => { k.sku = 'PK-' + (300 + i); k.stock = [30, 12, 26, 19, 8, 22][i]; k.status = 'published'; });
  }

  /* ---------- customers ---------- */
  const schools = ['Green Hill Secondary School', 'Lakeview Primary School', 'St. Gabriel Girls\u2019 School', 'Victoria Heights College', 'Nakawa Model Primary', 'Rubaga Hill Secondary', 'Kira Road Junior School', 'Hope Valley Primary School', 'Bukoto Royal College', 'Entebbe Lakeside High', 'Mukono Central Secondary', 'Kasubi Parents Primary', 'Namugongo Heights School', 'Wakiso Town Academy', 'Mengo Brook Secondary', 'Ntinda Grace Primary', 'Kawempe Unity College', 'Busega Light Secondary', 'Makindye Progressive School', 'Nansana Peak Primary', 'Lubowa International School', 'Gayaza Vision College'];
  const teachers = ['Joseph Ssemakula', 'Agnes Nabirye', 'David Okello', 'Rose Namukasa', 'Peter Mugisha', 'Esther Atim', 'Isaac Kiwanuka', 'Florence Nakirya'];
  const parents = ['Moses Lutaaya', 'Juliet Nansubuga', 'Patrick Byaruhanga', 'Sandra Auma'];
  const shops = ['Kampala Book Point', 'Wakiso Stationers Ltd', 'Smart Learners Bookshop'];
  const districts = ['Kampala', 'Wakiso', 'Mukono', 'Entebbe', 'Jinja', 'Mpigi', 'Masaka', 'Gulu'];
  const customers = [];
  const addCust = (name, type, w) => { const i = customers.length, slug = name.toLowerCase().replace(/[^a-z]+/g, '.').replace(/^\.|\.$/g, ''); customers.push({ id: 'C-' + (1000 + i), name, type, w, district: pick(districts), phone: '+2567' + int(10, 99) + String(int(100000, 999999)), email: type === 'School' || type === 'Bookshop' ? 'admin@' + slug.split('.').slice(0, 2).join('') + '.ac.ug' : slug + '@gmail.com', joined: NOW - int(30, 560) * DAY, contact: type === 'School' ? pick(['Bursar', 'Director of Studies', 'Head Teacher', 'Deputy Head']) : '' }); };
  schools.forEach(n => addCust(n, 'School', int(4, 10))); shops.forEach(n => addCust(n, 'Bookshop', 5)); teachers.forEach(n => addCust(n, 'Teacher', 2)); parents.forEach(n => addCust(n, 'Parent', 1));

  /* ---------- orders ---------- */
  const catalog = []; // pool for generation
  WK.books.filter(b => b.status === 'published').forEach(b => catalog.push({ type: 'book', ref: b, w: 3 }));
  WK.papers.forEach(p => catalog.push({ type: 'paper', ref: p, w: 1 }));
  WK.sets.forEach(s => catalog.push({ type: 'set', ref: s, w: 2 }));
  WK.packages.forEach(k => catalog.push({ type: 'pack', ref: k, w: 3 }));
  function itemOf(e, cust) {
    const bulk = cust.type === 'School' || cust.type === 'Bookshop', r = e.ref; let fmt = null, price, title, qty, level, subject = null;
    if (e.type === 'book') { price = r.price; title = r.title; level = LVL[r.level]; subject = r.subject; qty = bulk ? int(5, 40) : int(1, 3); }
    else if (e.type === 'paper') { fmt = rnd() < .46 ? 'pdf' : 'print'; price = fmt === 'pdf' ? r.pdf : r.print; title = r.title + ' ' + r.year; level = r.level; subject = r.subject; qty = fmt === 'pdf' ? (bulk ? int(1, 3) : 1) : (bulk ? int(10, 120) : int(1, 4)); }
    else if (e.type === 'set') { price = r.price; title = r.title; level = r.level; qty = bulk ? int(1, 4) : 1; }
    else { price = r.price; title = r.name; level = r.level; qty = bulk ? int(2, 20) : 1; }
    return { type: e.type, id: r.id, fmt, qty, price, title, level, subject };
  }
  const orders = []; let n = 0;
  const seasonal = d => { const m = new Date(NOW - d * DAY).getMonth(); return [1.5, 1.7, .9, .8, 1.4, 1.1, .8, 1.3, 1.6, 1.4, 1.2, .7][m]; };
  function make(d, fresh) {
    const cust = wpick(customers, customers.map(c => c.w)), k = wpick([1, 2, 3, 4], [4, 4, 2, 1]), items = [];
    for (let j = 0; j < k; j++) { const e = wpick(catalog, catalog.map(c => c.w)); if (!items.some(x => x.id === e.ref.id && x.type === e.type)) items.push(itemOf(e, cust)); }
    const phys = items.some(i => i.fmt !== 'pdf');
    const delivery = !phys ? 'email' : wpick(['pickup', 'kampala', 'upcountry'], [38, 42, 20]);
    const method = wpick(delivery === 'pickup' ? ['mtn', 'airtel', 'card', 'cash'] : ['mtn', 'airtel', 'card'], delivery === 'pickup' ? [42, 24, 10, 24] : [52, 32, 16]);
    const sub = items.reduce((a, i) => a + i.price * i.qty, 0), fee = DELIVERY[delivery].fee, ts = NOW - d * DAY - int(0, 80000) * 1000;
    let status; const age = d;
    if (age > 12) status = wpick(['completed', 'cancelled', 'refunded'], [91, 6, 3]);
    else if (age > 5) status = wpick(['completed', 'shipped', 'ready', 'processing', 'cancelled'], [60, 12, 10, 12, 6]);
    else if (age > 1.5) status = wpick(['processing', 'confirmed', 'ready', 'shipped', 'completed', 'pending'], [22, 20, 14, 16, 18, 10]);
    else status = wpick(['pending', 'confirmed', 'processing', 'ready'], [40, 28, 20, 12]);
    if (!phys && (status === 'ready' || status === 'shipped' || status === 'processing')) status = 'completed';
    let pay = status === 'cancelled' ? wpick(['failed', 'pending'], [60, 40]) : status === 'refunded' ? 'refunded' : (status === 'pending' ? wpick(['pending', 'paid'], [60, 40]) : 'paid');
    if (method === 'cash' && status !== 'completed' && status !== 'cancelled' && status !== 'refunded') pay = 'pending';
    n++; orders.push({ id: 'WK-' + (10240 + n), ts, custId: cust.id, items, delivery, method, status, pay, sub, fee, total: sub + fee, txn: method === 'cash' ? '' : (method === 'mtn' ? 'MTN' : method === 'airtel' ? 'ATL' : 'CRD') + int(10000000, 99999999), note: rnd() < .12 ? pick(['Please deliver before Monday assembly.', 'School stamp required on the invoice.', 'Call the bursar on arrival.', 'Need the marking guides included.']) : '' });
  }
  while (orders.length < 214) { const d = rnd() * 365; if (rnd() > seasonal(d) * (1.25 - d / 520) / 2.2) continue; make(d); }
  for (let i = 0; i < 9; i++) make(2 + rnd() * 3.5);
  for (let i = 0; i < 12; i++) make(rnd() * 1.6);
  orders.sort((a, b) => b.ts - a.ts);
  orders.slice().reverse().forEach((o, i) => { o.id = 'WK-' + (10241 + i); });
  const custById = {}; customers.forEach(c => custById[c.id] = c);
  orders.forEach(o => { o.cust = custById[o.custId]; });
  customers.forEach(c => { const mine = orders.filter(o => o.custId === c.id); if (mine.length) c.joined = Math.min(...mine.map(o => o.ts)) - int(0, 2) * DAY; });
  customers.forEach(c => { const os = orders.filter(o => o.custId === c.id && o.status !== 'cancelled' && o.status !== 'refunded'); c.orders = orders.filter(o => o.custId === c.id).length; c.spent = os.reduce((a, o) => a + o.total, 0); c.last = orders.find(o => o.custId === c.id); c.lastTs = c.last ? c.last.ts : 0; });
  const counted = o => o.status !== 'cancelled' && o.status !== 'refunded' && o.pay === 'paid';

  /* ---------- aggregation ---------- */
  const within = (days, offset) => orders.filter(o => counted(o) && o.ts >= NOW - (days + (offset || 0)) * DAY && o.ts < NOW - (offset || 0) * DAY);
  const sum = (arr, f) => arr.reduce((a, x) => a + f(x), 0);
  const itemRev = (o, pred) => sum(o.items.filter(pred), i => i.price * i.qty);
  function series(days) {
    const mode = days <= 31 ? 'd' : days <= 100 ? 'w' : 'm', buckets = [];
    const start = NOW - days * DAY;
    if (mode === 'm') { const d0 = new Date(NOW); for (let i = 11; i >= 0; i--) { const s = new Date(d0.getFullYear(), d0.getMonth() - i, 1), e = new Date(d0.getFullYear(), d0.getMonth() - i + 1, 1); buckets.push({ s: +s, e: +e, label: s.toLocaleString('en', { month: 'short' }) }); } }
    else { const step = mode === 'd' ? DAY : 7 * DAY, cnt = Math.ceil(days / (step / DAY)); for (let i = 0; i < cnt; i++) { const s = start + i * step, dt = new Date(s + step); buckets.push({ s, e: s + step, label: dt.toLocaleDateString('en', { day: 'numeric', month: 'short' }) }); } }
    buckets.forEach(b => { const os = orders.filter(o => counted(o) && o.ts >= b.s && o.ts < b.e); b.orders = os.length; b.print = sum(os, o => itemRev(o, i => i.fmt !== 'pdf')) + sum(os, o => o.fee); b.pdf = sum(os, o => itemRev(o, i => i.fmt === 'pdf')); b.rev = b.print + b.pdf; });
    return { mode, buckets };
  }
  function byCategory(os) { const r = { book: 0, paper: 0, set: 0, pack: 0 }; os.forEach(o => o.items.forEach(i => r[i.type] += i.price * i.qty)); return r; }
  function byLevel(os) { const r = { ple: 0, uce: 0, uace: 0 }; os.forEach(o => o.items.forEach(i => r[i.level] += i.price * i.qty)); return r; }
  function bySubject(os) { const r = {}; os.forEach(o => o.items.forEach(i => { if (i.subject) r[i.subject] = (r[i.subject] || 0) + i.price * i.qty; })); return Object.entries(r).sort((a, b) => b[1] - a[1]); }
  function topProducts(os, k) { const r = {}; os.forEach(o => o.items.forEach(i => { const key = i.type + ':' + i.id + ':' + (i.fmt || ''); (r[key] = r[key] || { type: i.type, id: i.id, fmt: i.fmt, title: i.title, units: 0, rev: 0 }); r[key].units += i.qty; r[key].rev += i.price * i.qty; })); return Object.values(r).sort((a, b) => b.rev - a.rev).slice(0, k || 6); }
  function byMethod(os) { const r = { mtn: 0, airtel: 0, card: 0, cash: 0 }; os.forEach(o => r[o.method] += o.total); return r; }
  function byStatus() { const r = {}; Object.keys(ORDER_STATUS).forEach(k => r[k] = 0); orders.forEach(o => r[o.status]++); return r; }
  function byDelivery(os) { const r = { pickup: 0, kampala: 0, upcountry: 0, email: 0 }; os.forEach(o => r[o.delivery]++); return r; }
  function byWeekday(os) { const r = [0, 0, 0, 0, 0, 0, 0]; os.forEach(o => r[(new Date(o.ts).getDay() + 6) % 7]++); return r; }

  /* ---------- other sample sets ---------- */
  const mTopics = ['Order or delivery', 'Bulk or school quotation', 'Mock examinations', 'Something else'];
  const msgBodies = {
    'Order or delivery': ['Good afternoon. We ordered on Monday and paid by MTN but have not received the confirmation email. Please confirm if the order is in.', 'Can you deliver to our school in Mukono on Friday? We need the Senior 4 practical workbooks before the mock week.', 'Our PDF download link has expired. Please resend it to the bursar\u2019s email.'],
    'Bulk or school quotation': ['Please send a quotation for 120 copies of UCE Biology 553/2 practical workbook and 60 UCE Chemistry workbooks. We would like to pay after delivery.', 'We are a group of five schools in Wakiso. What is the price for 5 full UCE Mock sets 2026 with marking guides?', 'Kindly quote 300 printed copies of PLE Mathematics Paper 1, delivered to Entebbe.'],
    'Mock examinations': ['When will the UACE Mock Examinations 2026 series be released? We want to book early for 80 candidates.', 'Do the PLE mock papers follow the new national format? Please advise before we order.'],
    'Something else': ['We would like to become a WAKATA member school. Whom should we speak to?', 'Do you sell the books wholesale to bookshops outside Kampala?']
  };
  const messages = []; Object.keys(msgBodies).forEach(t => msgBodies[t].forEach(b => { const c = pick(customers); messages.push({ id: 'M-' + (500 + messages.length), topic: t, body: b, name: c.type === 'School' ? pick(['Bursar', 'Director of Studies', 'Deputy Head']) + ', ' + c.name : c.name, email: c.email, phone: c.phone, ts: NOW - int(1, 2600) * 36e3 * 4, status: 'new', cust: c }); }));
  messages.sort((a, b) => b.ts - a.ts); messages.forEach((m, i) => { m.status = i < 4 ? 'new' : i < 8 ? 'replied' : (i < 10 ? 'quoted' : 'archived'); });
  const subscribers = []; for (let i = 0; i < 64; i++) { const nm = pick(['info', 'admin', 'bursar', 'office', 'head', 'dos', 'teacher', 'library']), sc = pick(schools).toLowerCase().replace(/[^a-z]+/g, '').slice(0, 14); subscribers.push({ id: i + 1, email: nm + (i % 5 ? '.' + sc : '') + (i % 7 === 0 ? '@gmail.com' : '@' + sc + '.ac.ug'), ts: NOW - Math.pow(rnd(), 1.6) * 330 * DAY, status: rnd() < .93 ? 'active' : 'unsubscribed', source: pick(['Home page', 'Footer', 'Checkout', 'Contact page']) }); }
  subscribers.sort((a, b) => b.ts - a.ts);
  const team = [
    { id: 1, name: 'Grace Namutebi', email: 'grace@wakatastationers.com', role: 'Super admin', status: 'active', last: NOW - 36e5 * 0.4, color: 'av-1' },
    { id: 2, name: 'Ronald Ssekandi', email: 'ronald@wakatastationers.com', role: 'Manager', status: 'active', last: NOW - 36e5 * 5, color: 'av-2' },
    { id: 3, name: 'Brenda Achieng', email: 'brenda@wakatastationers.com', role: 'Orders clerk', status: 'active', last: NOW - 36e5 * 26, color: 'av-3' },
    { id: 4, name: 'Moses Kato', email: 'moses@wakatastationers.com', role: 'Content editor', status: 'active', last: NOW - 36e5 * 70, color: 'av-4' },
    { id: 5, name: 'Sarah Nakato', email: 'sarah@wakatastationers.com', role: 'Accountant', status: 'invited', last: 0, color: 'av-5' }
  ];
  const actTpl = [['Updated price of', 'book', 'edit'], ['Confirmed payment for order', 'order', 'check'], ['Uploaded PDF file for', 'paper', 'upload'], ['Published exam set', 'set', 'plus'], ['Replied to message from', 'msg', 'mail'], ['Marked order delivered', 'order', 'truck'], ['Changed delivery fee for', 'setting', 'settings'], ['Added testimonial from', 'content', 'quote'], ['Adjusted stock of', 'book', 'box'], ['Signed in from Kampala', 'auth', 'user']];
  const activity = []; for (let i = 0; i < 60; i++) { const t = pick(actTpl), u = pick(team.slice(0, 4)); let obj = t[1] === 'book' ? pick(WK.books).title : t[1] === 'order' ? pick(orders).id : t[1] === 'paper' ? (p => p.title + ' ' + p.year)(pick(WK.papers)) : t[1] === 'set' ? pick(WK.sets).title : t[1] === 'msg' ? pick(customers).name : t[1] === 'setting' ? 'Upcountry courier' : t[1] === 'content' ? 'Head of Science, Wakiso' : ''; activity.push({ id: i, user: u, verb: t[0], obj, icon: t[2], ts: NOW - i * int(40, 220) * 6e4, ip: '41.210.' + int(100, 190) + '.' + int(2, 250), module: t[1] }); }
  const notifications = [
    { icon: 'cart', t: 'New order ' + orders[0].id, s: money(orders[0].total) + ' from ' + orders[0].cust.name, ts: orders[0].ts },
    { icon: 'card', t: 'Payment awaiting confirmation', s: '3 mobile money payments to verify', ts: NOW - 36e5 * 2 },
    { icon: 'box', t: 'Low stock warning', s: 'UCE Chemistry 545/3 workbook is running low', ts: NOW - 36e5 * 6 },
    { icon: 'mail', t: 'New bulk quotation request', s: messages[0].name, ts: messages[0].ts }
  ];

  /* ---------- helpers ---------- */
  const pad = x => String(x).padStart(2, '0');
  const fmtDate = ts => { const d = new Date(ts); return d.toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' }); };
  const fmtTime = ts => { const d = new Date(ts); return pad(d.getHours()) + ':' + pad(d.getMinutes()); };
  const rel = ts => { const s = Math.max(1, (NOW - ts) / 1000); if (s < 60) return 'just now'; if (s < 3600) return Math.floor(s / 60) + ' min ago'; if (s < 86400) return Math.floor(s / 3600) + ' h ago'; if (s < 2592000) return Math.floor(s / 86400) + ' d ago'; return fmtDate(ts); };
  const img = p => !p ? '' : (/^(https?:)?\/\//.test(p) || p[0] === '/') ? p : '../' + p;   /* the catalogue now sends full URLs */
  const initials = s => s.split(/\s+/).filter(Boolean).slice(0, 2).map(w => w[0]).join('').toUpperCase();
  const esc = s => String(s == null ? '' : s).replace(/[&<>"]/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]));
  const genThumb = (subject, cls) => { const c = WK.SUBJECTS[subject] || WK.SUBJECTS.Mathematics; return `<div class="thumb ${cls || ''}"><div class="cv" style="--c1:${c[0]};--c2:${c[1]};--c3:${c[2]}"><span class="sj">${esc(subject)}</span></div></div>`; };
  const thumbOf = (type, rec) => {
    if (type === 'book') return rec.img ? `<div class="thumb"><img src="${img(rec.img)}" alt="" loading="lazy"></div>` : genThumb(rec.subject);
    if (type === 'paper') return rec.img ? `<div class="thumb"><img src="${img(rec.img)}" alt="" loading="lazy"></div>` : genThumb(rec.subject);
    if (type === 'set') return rec.preview && rec.preview[0] ? `<div class="thumb"><img src="${img(WK.PAPER_IMG + rec.preview[0])}" alt="" loading="lazy"></div>` : genThumb('Literacy');
    return genThumb('Literacy');
  };
  const recOf = (type, id) => WK.byId(type === 'book' ? WK.books : type === 'paper' ? WK.papers : type === 'set' ? WK.sets : WK.packages, id);
  const thumbById = (type, id) => { const r = recOf(type, id); return r ? thumbOf(type, r) : genThumb('Mathematics'); };

  /* ---------- Module 8: the sales pages get the REAL orders and customers from Laravel (WK.sales) ----------
     Orders, customers, delivery options and payment methods replace the generated samples, so every table, chart and KPI
     on those pages is drawn from the database. Pages that did not ask for WK.sales (dashboard, reports ...) keep the samples
     until their own module is built. After a change the server sends back the fresh order or customer: upsertOrder() and
     upsertCustomer() fold it in, and the totals per customer are worked out again from the orders. */
  const sales = (function () {
    const S = WK.sales; if (!S) return null;
    Object.keys(DELIVERY).forEach(k => delete DELIVERY[k]);
    Object.entries(S.delivery).forEach(([k, d]) => { DELIVERY[k] = { label: d.label, fee: d.fee, eta: d.eta, enabled: d.enabled }; });
    Object.keys(PAY_METHODS).forEach(k => delete PAY_METHODS[k]);
    Object.entries(S.payments).forEach(([k, m]) => { PAY_METHODS[k] = m.label; });
    const ghost = o => ({ id: o.custId || ('G-' + o.id), dbId: null, name: o.name, type: 'Guest', phone: o.phone, email: o.email, district: '', contact: '', note: '', joined: o.ts });
    function link() {
      orders.sort((a, b) => b.ts - a.ts);
      Object.keys(custById).forEach(k => delete custById[k]);
      customers.forEach(c => { custById[c.id] = c; });
      orders.forEach(o => { if (!custById[o.custId]) { const g = ghost(o); customers.push(g); custById[g.id] = g; } o.cust = custById[o.custId]; });
      customers.forEach(c => {
        const mine = orders.filter(o => o.custId === c.id);
        c.orders = mine.length; c.spent = sum(mine.filter(counted), o => o.total); c.last = mine[0] || null; c.lastTs = c.last ? c.last.ts : 0;
      });
    }
    orders.length = 0; customers.length = 0;
    S.orders.forEach(o => orders.push(o)); S.customers.forEach(c => customers.push(c));
    link();
    return {
      data: S, can: S.can, routes: S.routes, items: S.items,
      upsertOrder(o) { const cur = orders.find(x => x.id === o.id); if (cur) Object.assign(cur, o); else orders.unshift(o); link(); return orders.find(x => x.id === o.id); },
      upsertCustomer(c) { const cur = customers.find(x => x.id === c.id); if (cur) Object.assign(cur, c); else customers.push(c); link(); return custById[c.id]; },
      setDelivery(d) { Object.entries(d).forEach(([k, v]) => { DELIVERY[k] = { label: v.label, fee: v.fee, eta: v.eta, enabled: v.enabled }; }); }
    };
  })();

  /* ---------- Module 9: the engagement pages get the REAL messages and subscribers from Laravel (WK.eng) ---------- */
  if (WK.eng) {
    messages.length = 0; WK.eng.messages.forEach(m => messages.push(m));
    subscribers.length = 0; WK.eng.subscribers.forEach(x => subscribers.push(x));
  }

  /* ---------- Module 10: the shell, the team and the activity log come from Laravel ----------
     WK.shell (every signed-in page): the notification bell. WK.ins on the Team page: the real members. WK.ins on the Activity log
     page: the real log lines. The dashboard reads its own part of WK.ins (attention list, goal, latest activity) in pages.js. */
  if (WK.shell) { notifications.length = 0; WK.shell.notifications.forEach(n => notifications.push(n)); }
  if (WK.ins && WK.ins.members) { team.length = 0; WK.ins.members.forEach(m => team.push(m)); }
  if (WK.ins && WK.ins.rows) { activity.length = 0; WK.ins.rows.forEach(a => activity.push(a)); }

  return { WK, rnd, NOW, DAY, orders, customers, custById, messages, subscribers, team, activity, notifications, ORDER_STATUS, PAY_STATUS, PAY_METHODS, DELIVERY, LVL, LVL_LABEL, TYPE_LABEL,
    counted, within, series, byCategory, byLevel, bySubject, topProducts, byMethod, byStatus, byDelivery, byWeekday, sum, itemRev,
    fmtDate, fmtTime, rel, img, initials, esc, thumbOf, thumbById, genThumb, recOf, money, mTopics, sales };
})();
