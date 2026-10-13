/* ==========================================================
   WAKATA customer account: data layer
   Exposes window.AD with the same helpers the admin console uses, so account.js works unchanged.
   Orders, files, messages, addresses and saved items are the signed-in customer's REAL data: Laravel builds
   them (App\Support\Portal) and hands them over as WK.portal. Nothing here is a sample any more.
   ========================================================== */
window.AD = (function () {
  const WK = window.WK, NOW = Date.now(), DAY = 864e5, money = WK.money;
  const P = WK.portal || {}, user = WK.user || {}, R = P.routes || {};
  const ORDER_STATUS = { pending: { label: 'Pending', cls: 'b-warn' }, confirmed: { label: 'Confirmed', cls: 'b-info' }, processing: { label: 'Being prepared', cls: 'b-pur' }, ready: { label: 'Ready for pickup', cls: 'b-info' }, shipped: { label: 'Out for delivery', cls: 'b-pur' }, completed: { label: 'Completed', cls: 'b-ok' }, cancelled: { label: 'Cancelled', cls: 'b-mute' }, refunded: { label: 'Refunded', cls: 'b-bad' } };
  const PAY_STATUS = { paid: { label: 'Paid', cls: 'b-ok' }, pending: { label: 'Awaiting payment', cls: 'b-warn' }, failed: { label: 'Failed', cls: 'b-bad' }, refunded: { label: 'Refunded', cls: 'b-mute' } };
  const PAY_METHODS = P.payments || { mtn: 'MTN Mobile Money', airtel: 'Airtel Money', card: 'Visa or Mastercard', cash: 'Cash on pickup' };
  const DELIVERY = P.delivery || {};
  const LV = { primary: 'PLE', o: 'UCE', a: 'UACE', ple: 'PLE', uce: 'UCE', uace: 'UACE' };
  const TYPE_LABEL = { book: 'Book', paper: 'Past paper', set: 'Exam set', pack: 'Holiday package' };
  const orders = P.orders || [], files = P.files || [], messages = P.messages || [], addresses = P.addresses || [], saved = P.saved || [], notifications = P.notifications || [];
  const fresh = !orders.length;
  const counted = o => o.pay === 'paid' && o.status !== 'cancelled' && o.status !== 'refunded';
  const url = {
    order: id => (R.order || '').replace('__ID__', encodeURIComponent(id)),
    invoice: (id, print) => (R.invoice || '').replace('__ID__', encodeURIComponent(id)) + (print ? '?print=1' : ''),
    file: (tpl, fid) => { const [o, k] = String(fid).split(':'); return (tpl || '').replace('__ORDER__', encodeURIComponent(o)).replace('__FILE__', encodeURIComponent(k)); }
  };
  const pad = x => String(x).padStart(2, '0');
  const fmtDate = ts => new Date(ts).toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' });
  const fmtTime = ts => { const d = new Date(ts); return pad(d.getHours()) + ':' + pad(d.getMinutes()); };
  const rel = ts => { const s = Math.max(1, (NOW - ts) / 1000); if (s < 60) return 'just now'; if (s < 3600) return Math.floor(s / 60) + ' min ago'; if (s < 86400) return Math.floor(s / 3600) + ' h ago'; if (s < 2592000) return Math.floor(s / 86400) + ' d ago'; return fmtDate(ts); };
  const img = p => p || '';
  const initials = s => String(s || '?').split(/\s+/).filter(Boolean).slice(0, 2).map(w => w[0]).join('').toUpperCase();
  const esc = s => String(s == null ? '' : s).replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
  const sum = (a, f) => a.reduce((x, y) => x + f(y), 0);
  const slug = s => String(s || '').toLowerCase().replace(/&/g, '').replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '');
  const genThumb = (subject, cls) => { const c = WK.SUBJECTS[subject] || WK.SUBJECTS.Mathematics || ['#043AA0', '#0A5BD8', '#FFC20E']; return `<div class="thumb ${cls || ''}"><div class="cv" style="--c1:${c[0]};--c2:${c[1]};--c3:${c[2]}"><span class="sj">${esc(subject)}</span></div></div>`; };
  const recOf = (type, id) => WK.byId(type === 'book' ? WK.books : type === 'paper' ? WK.papers : type === 'set' ? WK.sets : WK.packages, id);
  const thumbOf = (type, r) => { if (type === 'book' || type === 'paper') return r.img ? `<div class="thumb"><img src="${esc(img(r.img))}" alt="" loading="lazy"></div>` : genThumb(r.subject); if (type === 'set') return r.preview && r.preview[0] ? `<div class="thumb"><img src="${esc(img(WK.PAPER_IMG + r.preview[0]))}" alt="" loading="lazy"></div>` : genThumb('Literacy'); return genThumb('Literacy'); };
  const thumbById = (type, id) => { const r = recOf(type, id); return r ? thumbOf(type, r) : genThumb('Mathematics'); };
  function monthly() { const d0 = new Date(NOW), out = []; for (let i = 11; i >= 0; i--) { const s = new Date(d0.getFullYear(), d0.getMonth() - i, 1), e = new Date(d0.getFullYear(), d0.getMonth() - i + 1, 1), os = orders.filter(o => counted(o) && o.ts >= +s && o.ts < +e); out.push({ label: s.toLocaleString('en', { month: 'short' }), print: sum(os, o => sum(o.items.filter(i => i.fmt !== 'pdf'), i => i.price * i.qty) + o.fee), pdf: sum(os, o => sum(o.items.filter(i => i.fmt === 'pdf'), i => i.price * i.qty)) }); } return out; }
  function byCategory() { const r = { book: 0, paper: 0, set: 0, pack: 0 }; orders.filter(counted).forEach(o => o.items.forEach(i => r[i.type] += i.price * i.qty)); return r; }
  return { WK, P, R, url, user, fresh, NOW, DAY, orders, files, messages, notifications, addresses, saved, customers: [], ORDER_STATUS, PAY_STATUS, PAY_METHODS, DELIVERY, LV, TYPE_LABEL, counted, monthly, byCategory, sum, fmtDate, fmtTime, rel, img, initials, esc, thumbOf, thumbById, genThumb, recOf, money, slug };
})();
