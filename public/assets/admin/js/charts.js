/* ==========================================================
   WAKATA Admin: tiny dependency-free SVG chart library
   WKC.line / WKC.bar / WKC.donut / WKC.spark / WKC.ring
   Colours use the CSS variables --c1..--c7 so dark mode works.
   ========================================================== */
window.WKC = (function () {
  const NS = 'http://www.w3.org/2000/svg';
  const PAL = ['var(--c1)', 'var(--c2)', 'var(--c3)', 'var(--c4)', 'var(--c5)', 'var(--c6)', 'var(--c7)'];
  let uid = 0;
  const esc = s => String(s).replace(/[&<>"]/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]));
  const compact = v => { const a = Math.abs(v); if (a >= 1e9) return +(v / 1e9).toFixed(1) + 'B'; if (a >= 1e6) return +(v / 1e6).toFixed(1) + 'M'; if (a >= 1e3) return +(v / 1e3).toFixed(a >= 1e4 ? 0 : 1) + 'k'; return String(Math.round(v)); };
  const nice = max => { if (max <= 0) return 1; const p = Math.pow(10, Math.floor(Math.log10(max))); const n = max / p; return (n <= 1 ? 1 : n <= 2 ? 2 : n <= 2.5 ? 2.5 : n <= 5 ? 5 : 10) * p; };

  /* shared tooltip */
  let tt;
  function tip(html, x, y) {
    if (!tt) { tt = document.createElement('div'); tt.className = 'tt'; document.body.appendChild(tt); }
    if (html === null) { tt.classList.remove('show'); return; }
    tt.innerHTML = html; tt.classList.add('show');
    const w = tt.offsetWidth, h = tt.offsetHeight;
    let L = x + 14, T = y - h - 12; if (L + w > innerWidth - 8) L = x - w - 14; if (T < 8) T = y + 18;
    tt.style.left = L + 'px'; tt.style.top = T + 'px';
  }
  const row = (c, n, v) => `<div><i style="background:${c}"></i>${esc(n)}: <b style="display:inline">${v}</b></div>`;

  function mount(el, draw) {
    el = typeof el === 'string' ? document.querySelector(el) : el; if (!el) return;
    el.classList.add('chart');
    el._draw = draw;
    const run = () => { const w = el.clientWidth; if (!w) return; if (el._w === w && el._done) return; el._w = w; el._done = true; el._draw(el, w); };
    el._done = false; run();
    if (!el._ro && window.ResizeObserver) { el._ro = new ResizeObserver(() => { el._done = false; run(); }); el._ro.observe(el); }
    el._redraw = () => { el._done = false; run(); };
    return el;
  }
  const svgEl = (w, h) => { const s = document.createElementNS(NS, 'svg'); s.setAttribute('width', w); s.setAttribute('height', h); s.setAttribute('viewBox', `0 0 ${w} ${h}`); s.setAttribute('role', 'img'); return s; };
  function legend(el, items, col) {
    let lg = el.parentNode.querySelector(':scope>.legend[data-for="' + (el.id || '') + '"]');
    if (lg) lg.remove();
    if (!items) return;
    lg = document.createElement('div'); lg.className = 'legend' + (col ? ' col' : ''); lg.dataset.for = el.id || '';
    lg.innerHTML = items.map(i => `<span><i style="background:${i.color}"></i>${esc(i.name)}${i.val !== undefined ? `<b>${i.val}</b>` : ''}</span>`).join('');
    el.after(lg);
  }

  /* smooth path (monotone-ish via Catmull-Rom) */
  function smooth(pts) {
    if (pts.length < 3) return pts.map((p, i) => (i ? 'L' : 'M') + p[0] + ',' + p[1]).join('');
    let d = 'M' + pts[0][0] + ',' + pts[0][1];
    for (let i = 0; i < pts.length - 1; i++) {
      const p0 = pts[i - 1] || pts[i], p1 = pts[i], p2 = pts[i + 1], p3 = pts[i + 2] || p2, t = .18;
      const c1x = p1[0] + (p2[0] - p0[0]) * t, c1y = p1[1] + (p2[1] - p0[1]) * t, c2x = p2[0] - (p3[0] - p1[0]) * t, c2y = p2[1] - (p3[1] - p1[1]) * t;
      d += `C${c1x.toFixed(1)},${c1y.toFixed(1)} ${c2x.toFixed(1)},${c2y.toFixed(1)} ${p2[0]},${p2[1]}`;
    }
    return d;
  }

  /* ---------- LINE / AREA ---------- */
  function line(el, o) {
    return mount(el, (host, W) => {
      const H = o.height || 270, m = { l: 46, r: 14, t: 14, b: 28 }, iw = W - m.l - m.r, ih = H - m.t - m.b;
      const S = o.series.map((s, i) => Object.assign({ color: PAL[i % PAL.length] }, s)), n = o.labels.length, fmt = o.fmt || compact;
      const max = nice(Math.max(1, ...S.flatMap(s => s.data))), id = 'g' + (++uid);
      const X = i => m.l + (n === 1 ? iw / 2 : iw * i / (n - 1)), Y = v => m.t + ih - ih * v / max;
      const svg = svgEl(W, H); let h = '<defs>' + S.map((s, i) => `<linearGradient id="${id}${i}" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="${s.color}" stop-opacity=".28"/><stop offset="1" stop-color="${s.color}" stop-opacity="0"/></linearGradient>`).join('') + '</defs>';
      for (let k = 0; k <= 4; k++) { const v = max * k / 4, y = Y(v); h += `<line class="grid" x1="${m.l}" x2="${W - m.r}" y1="${y}" y2="${y}"/><text x="${m.l - 8}" y="${y + 4}" text-anchor="end">${fmt(v)}</text>`; }
      const step = Math.ceil(n / Math.max(2, Math.floor(iw / 62)));
      o.labels.forEach((l, i) => { if (i % step === 0 || i === n - 1 && (n - 1) % step > step / 2) h += `<text x="${X(i)}" y="${H - 8}" text-anchor="middle">${esc(l)}</text>`; });
      S.forEach((s, si) => {
        const pts = s.data.map((v, i) => [+X(i).toFixed(1), +Y(v).toFixed(1)]), d = smooth(pts);
        if (o.area !== false) h += `<path d="${d}L${pts[pts.length - 1][0]},${m.t + ih}L${pts[0][0]},${m.t + ih}Z" fill="url(#${id}${si})"/>`;
        h += `<path d="${d}" fill="none" style="stroke:${s.color}" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"${s.dash ? ' stroke-dasharray="5 5"' : ''}/>`;
        if (n <= 14) pts.forEach(p => h += `<circle cx="${p[0]}" cy="${p[1]}" r="3.4" style="fill:var(--surface);stroke:${s.color}" stroke-width="2"/>`);
      });
      h += `<line id="${id}v" y1="${m.t}" y2="${m.t + ih}" class="axis" style="display:none"/>` + S.map((s, i) => `<circle id="${id}c${i}" r="5" style="display:none;fill:${s.color};stroke:var(--surface)" stroke-width="2"/>`).join('');
      h += `<rect x="${m.l}" y="${m.t}" width="${iw}" height="${ih}" fill="transparent" id="${id}o"/>`;
      svg.innerHTML = h; host.innerHTML = ''; host.appendChild(svg);
      const ov = svg.querySelector('#' + id + 'o'), vl = svg.querySelector('#' + id + 'v');
      ov.addEventListener('mousemove', e => {
        const r = svg.getBoundingClientRect(), x = e.clientX - r.left; let i = Math.round((x - m.l) / iw * (n - 1)); i = Math.max(0, Math.min(n - 1, i));
        vl.style.display = ''; vl.setAttribute('x1', X(i)); vl.setAttribute('x2', X(i));
        S.forEach((s, k) => { const c = svg.querySelector('#' + id + 'c' + k); c.style.display = ''; c.setAttribute('cx', X(i)); c.setAttribute('cy', Y(s.data[i])); });
        tip(`<b>${esc(o.labels[i])}</b>` + S.map(s => row(s.color, s.name, (o.tipFmt || fmt)(s.data[i]))).join(''), e.clientX, e.clientY);
      });
      ov.addEventListener('mouseleave', () => { vl.style.display = 'none'; S.forEach((s, k) => svg.querySelector('#' + id + 'c' + k).style.display = 'none'); tip(null); });
      if (o.legend !== false && S.length > 1) legend(host, S.map(s => ({ name: s.name, color: s.color }))); else legend(host, null);
    });
  }

  /* ---------- BAR (grouped / stacked / horizontal) ---------- */
  function bar(el, o) {
    return mount(el, (host, W) => {
      const S = o.series.map((s, i) => Object.assign({ color: PAL[i % PAL.length] }, s)), n = o.labels.length, fmt = o.fmt || compact;
      const horiz = !!o.horizontal, H = o.height || (horiz ? Math.max(180, n * 38 + 30) : 260);
      const lw = horiz ? Math.min(150, Math.max(70, Math.max(...o.labels.map(l => String(l).length)) * 6.4)) : 0;
      const m = horiz ? { l: lw + 8, r: 46, t: 6, b: 22 } : { l: 46, r: 10, t: 12, b: 28 }, iw = W - m.l - m.r, ih = H - m.t - m.b;
      const tot = i => S.reduce((a, s) => a + s.data[i], 0);
      const max = nice(Math.max(1, ...(o.stacked ? o.labels.map((_, i) => tot(i)) : S.flatMap(s => s.data))));
      const svg = svgEl(W, H); let h = '';
      for (let k = 0; k <= 4; k++) {
        const v = max * k / 4;
        if (horiz) { const x = m.l + iw * k / 4; h += `<line class="grid" x1="${x}" x2="${x}" y1="${m.t}" y2="${m.t + ih}"/><text x="${x}" y="${H - 6}" text-anchor="middle">${fmt(v)}</text>`; }
        else { const y = m.t + ih - ih * k / 4; h += `<line class="grid" x1="${m.l}" x2="${W - m.r}" y1="${y}" y2="${y}"/><text x="${m.l - 8}" y="${y + 4}" text-anchor="end">${fmt(v)}</text>`; }
      }
      const band = (horiz ? ih : iw) / n, gap = band * .24, bw = o.stacked ? band - gap : (band - gap) / S.length, step = horiz ? 1 : Math.ceil(n / Math.max(2, Math.floor(iw / 54)));
      o.labels.forEach((l, i) => {
        let acc = 0;
        if (horiz) h += `<text x="${m.l - 8}" y="${m.t + band * i + band / 2 + 4}" text-anchor="end">${esc(String(l).length > 22 ? String(l).slice(0, 21) + '…' : l)}</text>`;
        else if (i % step === 0) h += `<text x="${m.l + band * i + band / 2}" y="${H - 8}" text-anchor="middle">${esc(l)}</text>`;
        S.forEach((s, k) => {
          const v = s.data[i], len = (horiz ? iw : ih) * v / max, last = o.stacked ? k === S.length - 1 : true;
          let x, y, w, hh;
          if (horiz) { w = len; hh = bw; x = m.l + (o.stacked ? (acc / max) * iw : 0); y = m.t + band * i + gap / 2 + (o.stacked ? 0 : bw * k); }
          else { w = bw; hh = len; x = m.l + band * i + gap / 2 + (o.stacked ? 0 : bw * k); y = m.t + ih - (o.stacked ? ((acc / max) * ih + len) : len); }
          if (o.stacked) acc += v;
          h += `<rect class="bar" data-i="${i}" x="${x.toFixed(1)}" y="${y.toFixed(1)}" width="${Math.max(0, w).toFixed(1)}" height="${Math.max(0, hh).toFixed(1)}" rx="${Math.min(4, bw / 3)}" style="fill:${s.color}"/>`;
        });
        if (horiz && !o.stacked && S.length === 1) h += `<text x="${m.l + iw * S[0].data[i] / max + 6}" y="${m.t + band * i + band / 2 + 4}" style="fill:var(--head);font-weight:700">${fmt(S[0].data[i])}</text>`;
      });
      svg.innerHTML = h; host.innerHTML = ''; host.appendChild(svg);
      svg.querySelectorAll('.bar').forEach(r => {
        const i = +r.dataset.i;
        r.addEventListener('mousemove', e => tip(`<b>${esc(o.labels[i])}</b>` + S.map(s => row(s.color, s.name, (o.tipFmt || fmt)(s.data[i]))).join(''), e.clientX, e.clientY));
        r.addEventListener('mouseleave', () => tip(null));
      });
      if (o.legend !== false && S.length > 1) legend(host, S.map(s => ({ name: s.name, color: s.color }))); else legend(host, null);
    });
  }

  /* ---------- DONUT ---------- */
  function donut(el, o) {
    return mount(el, (host, W) => {
      const size = Math.min(W, o.size || 190), r = size / 2 - 14, sw = o.thick || 26, C = 2 * Math.PI * r, items = o.items.map((it, i) => Object.assign({ color: PAL[i % PAL.length] }, it));
      const total = items.reduce((a, i) => a + i.value, 0) || 1, fmt = o.fmt || compact;
      const svg = svgEl(size, size); let off = 0, h = `<circle cx="${size / 2}" cy="${size / 2}" r="${r}" fill="none" style="stroke:var(--line-2)" stroke-width="${sw}"/>`;
      items.forEach((it, i) => { const len = C * it.value / total; h += `<circle class="bar" data-i="${i}" cx="${size / 2}" cy="${size / 2}" r="${r}" fill="none" style="stroke:${it.color}" stroke-width="${sw}" stroke-dasharray="${Math.max(0, len - (items.length > 1 ? 2 : 0)).toFixed(2)} ${(C - len + (items.length > 1 ? 2 : 0)).toFixed(2)}" stroke-dashoffset="${(-off).toFixed(2)}" transform="rotate(-90 ${size / 2} ${size / 2})"/>`; off += len; });
      h += `<text class="ctr-v" x="${size / 2}" y="${size / 2 + 2}" text-anchor="middle">${esc(o.centerValue !== undefined ? o.centerValue : fmt(total))}</text><text class="ctr-l" x="${size / 2}" y="${size / 2 + 18}" text-anchor="middle">${esc(o.centerLabel || 'Total')}</text>`;
      svg.innerHTML = h; host.innerHTML = ''; host.style.textAlign = 'center'; host.appendChild(svg);
      svg.querySelectorAll('.bar').forEach(c => {
        const it = items[+c.dataset.i];
        c.addEventListener('mousemove', e => tip(`<b>${esc(it.name || it.label)}</b>${(o.tipFmt || fmt)(it.value)} (${Math.round(it.value / total * 100)}%)`, e.clientX, e.clientY));
        c.addEventListener('mouseleave', () => tip(null));
      });
      legend(host, o.legend === false ? null : items.map(i => ({ name: i.name || i.label, color: i.color, val: Math.round(i.value / total * 100) + '%' })), true);
    });
  }

  /* ---------- SPARKLINE ---------- */
  function spark(el, data, color) {
    el = typeof el === 'string' ? document.querySelector(el) : el; if (!el) return;
    const W = 96, H = 34, max = Math.max(...data), min = Math.min(...data), rng = (max - min) || 1, id = 'sp' + (++uid), c = color || 'var(--c1)';
    const pts = data.map((v, i) => [+(i * (W - 4) / (data.length - 1) + 2).toFixed(1), +(H - 4 - (v - min) / rng * (H - 8)).toFixed(1)]), d = smooth(pts);
    el.innerHTML = `<svg width="${W}" height="${H}" viewBox="0 0 ${W} ${H}" aria-hidden="true"><defs><linearGradient id="${id}" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="${c}" stop-opacity=".3"/><stop offset="1" stop-color="${c}" stop-opacity="0"/></linearGradient></defs><path d="${d}L${W - 2},${H}L2,${H}Z" fill="url(#${id})"/><path d="${d}" fill="none" style="stroke:${c}" stroke-width="2" stroke-linecap="round"/></svg>`;
  }

  /* ---------- RING (progress / goal) ---------- */
  function ring(el, o) {
    return mount(el, host => {
      const size = o.size || 170, sw = 16, r = size / 2 - sw, C = 2 * Math.PI * r, p = Math.max(0, Math.min(1, o.value / o.max));
      host.style.textAlign = 'center';
      host.innerHTML = `<svg width="${size}" height="${size}" viewBox="0 0 ${size} ${size}"><circle cx="${size / 2}" cy="${size / 2}" r="${r}" fill="none" style="stroke:var(--line-2)" stroke-width="${sw}"/><circle cx="${size / 2}" cy="${size / 2}" r="${r}" fill="none" style="stroke:${o.color || 'var(--c1)'}" stroke-width="${sw}" stroke-linecap="round" stroke-dasharray="${(C * p).toFixed(1)} ${C.toFixed(1)}" transform="rotate(-90 ${size / 2} ${size / 2})"/><text class="ctr-v" x="${size / 2}" y="${size / 2 + 6}" text-anchor="middle">${Math.round(p * 100)}%</text><text class="ctr-l" x="${size / 2}" y="${size / 2 + 24}" text-anchor="middle">${esc(o.label || '')}</text></svg>`;
    });
  }

  return { line, bar, donut, spark, ring, compact, PAL };
})();
