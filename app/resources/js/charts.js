// Small hand-made SVG charts for the statistics page. One hue for magnitude (snan blue),
// a validated four-colour set for categories, thin marks, hairline grid, a tooltip on
// every mark. Charts redraw on resize so they stay crisp at any width.

const NS = 'http://www.w3.org/2000/svg';
const el = (name, attrs = {}) => { const e = document.createElementNS(NS, name); for (const [k, v] of Object.entries(attrs)) e.setAttribute(k, v); return e; };
export const CATEGORICAL = ['#1f56d6', '#eb6834', '#1baf7a', '#eda100']; // validated: adjacent CVD ΔE ≥ 9.1
const RAMP = ['#eef3fd', '#cde2fb', '#9ec5f4', '#6da7ec', '#3987e5', '#256abf', '#184f95', '#0d366b'];
const ink = '#0c2350', mute = '#5b6b86', grid = '#eef1f6';

export const fmt = {
    money: (c) => `${Math.round(c / 100).toLocaleString('fr-FR')} DH`,
    moneyShort: (c) => { const v = c / 100; return v >= 1000 ? `${(v / 1000).toLocaleString('fr-FR', { maximumFractionDigits: 1 })} k` : `${Math.round(v)}`; },
    pct: (v) => `${Math.round(v * 100)} %`,
    int: (v) => `${v}`,
};

// One floating tooltip for the whole page.
let tipEl;
function tip(html, x, y) {
    tipEl ??= Object.assign(document.body.appendChild(document.createElement('div')), { className: 'chart-tip2' });
    if (html === null) { tipEl.hidden = true; return; }
    tipEl.innerHTML = html;
    tipEl.hidden = false;
    const r = tipEl.getBoundingClientRect();
    tipEl.style.left = `${Math.min(window.innerWidth - r.width - 8, x + 14)}px`;
    tipEl.style.top = `${Math.max(8, y - r.height - 10)}px`;
}
const esc = (s) => String(s).replace(/[&<>"]/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]));

function niceMax(v) {
    if (v <= 0) return 1;
    const mag = 10 ** Math.floor(Math.log10(v));
    return [1, 1.5, 2, 2.5, 3, 4, 5, 6, 8, 10].map((m) => m * mag).find((x) => x >= v);
}

function responsive(node, draw) {
    const run = () => { node.replaceChildren(); draw(node.clientWidth); };
    new ResizeObserver(() => run()).observe(node);
}

// ── Trend: lines over weeks, crosshair + tooltip with every series ──────────
export function trend(node, { labels, series, format = 'money', note = {} }) {
    responsive(node, (W) => {
        const H = 240, L = 46, R = 56, T = 14, B = 26;
        const svg = el('svg', { width: W, height: H, viewBox: `0 0 ${W} ${H}`, class: 'trend' });
        const max = niceMax(Math.max(...series.flatMap((s) => s.values)));
        const x = (i) => L + (i * (W - L - R)) / (labels.length - 1);
        const y = (v) => T + (1 - v / max) * (H - T - B);
        for (const t of [0, 0.5, 1]) {
            svg.append(el('line', { x1: L, x2: W - R, y1: y(max * t), y2: y(max * t), stroke: grid }));
            const tx = el('text', { x: L - 8, y: y(max * t) + 4, class: 'axis', 'text-anchor': 'end' });
            tx.textContent = fmt[format === 'money' ? 'moneyShort' : format](max * t);
            svg.append(tx);
        }
        labels.forEach((lb, i) => {
            if (i % 2 && labels.length > 8) return;
            const tx = el('text', { x: x(i), y: H - 6, class: 'axis', 'text-anchor': 'middle' });
            tx.textContent = lb;
            svg.append(tx);
        });
        for (const s of [...series].reverse()) {
            const d = s.values.map((v, i) => `${i ? 'L' : 'M'} ${x(i).toFixed(1)} ${y(v).toFixed(1)}`).join(' ');
            if (s.area) svg.append(el('path', { d: `${d} L ${x(s.values.length - 1)} ${y(0)} L ${x(0)} ${y(0)} Z`, fill: s.color, opacity: 0.1 }));
            svg.append(el('path', { d, fill: 'none', stroke: s.color, 'stroke-width': 2, 'stroke-linejoin': 'round', 'stroke-linecap': 'round', 'stroke-dasharray': s.dash ? '5 4' : 'none' }));
            const last = s.values.length - 1;
            svg.append(el('circle', { cx: x(last), cy: y(s.values[last]), r: 4, fill: s.color, stroke: '#fff', 'stroke-width': 2 }));
            if (s.label) {
                const tx = el('text', { x: x(last) + 8, y: y(s.values[last]) + 4, class: 'end-label' });
                tx.textContent = fmt[format === 'money' ? 'moneyShort' : format](s.values[last]);
                svg.append(tx);
            }
        }
        // Crosshair
        const cross = el('line', { y1: T, y2: H - B, stroke: '#9aa7bd', 'stroke-width': 1, visibility: 'hidden' });
        const dots = series.map((s) => el('circle', { r: 5, fill: s.color, stroke: '#fff', 'stroke-width': 2, visibility: 'hidden' }));
        svg.append(cross, ...dots);
        const hit = el('rect', { x: L, y: T, width: W - L - R, height: H - T - B, fill: 'transparent' });
        const show = (i, cx, cy) => {
            cross.setAttribute('x1', x(i)); cross.setAttribute('x2', x(i)); cross.setAttribute('visibility', 'visible');
            dots.forEach((dt, k) => { dt.setAttribute('cx', x(i)); dt.setAttribute('cy', y(series[k].values[i])); dt.setAttribute('visibility', 'visible'); });
            tip(`<b>${esc(labels[i])}${note[i] ? ` <span>${esc(note[i])}</span>` : ''}</b>${series.map((s) => `<div><i style="background:${s.color}"></i>${esc(s.name)}<em>${fmt[format](s.values[i])}</em></div>`).join('')}`, cx, cy);
        };
        hit.addEventListener('mousemove', (e) => {
            const r = svg.getBoundingClientRect();
            const i = Math.max(0, Math.min(labels.length - 1, Math.round(((e.clientX - r.left - L) / (W - L - R)) * (labels.length - 1))));
            show(i, e.clientX, e.clientY);
        });
        hit.addEventListener('mouseleave', () => { cross.setAttribute('visibility', 'hidden'); dots.forEach((d) => d.setAttribute('visibility', 'hidden')); tip(null); });
        svg.append(hit);
        node.append(svg);
    });
}

// ── Sparkline in a KPI tile ─────────────────────────────────────────────────
export function sparkline(node, values, color = CATEGORICAL[0]) {
    responsive(node, (W) => {
        const H = 34;
        const max = Math.max(...values, 1e-9), min = Math.min(...values, 0);
        const x = (i) => 2 + (i * (W - 6)) / (values.length - 1);
        const y = (v) => 3 + (1 - (v - min) / (max - min || 1)) * (H - 8);
        const d = values.map((v, i) => `${i ? 'L' : 'M'} ${x(i).toFixed(1)} ${y(v).toFixed(1)}`).join(' ');
        const svg = el('svg', { width: W, height: H, viewBox: `0 0 ${W} ${H}`, 'aria-hidden': 'true' });
        svg.append(el('path', { d: `${d} L ${x(values.length - 1)} ${H} L ${x(0)} ${H} Z`, fill: color, opacity: 0.1 }),
            el('path', { d, fill: 'none', stroke: color, 'stroke-width': 2, 'stroke-linejoin': 'round', 'stroke-linecap': 'round' }),
            el('circle', { cx: x(values.length - 1), cy: y(values.at(-1)), r: 3.5, fill: color, stroke: '#fff', 'stroke-width': 1.5 }));
        node.append(svg);
    });
}

// ── Heatmap: weekday × hour, how full the chair was ─────────────────────────
export function heatmap(node, { rows, cols, values }) {
    responsive(node, (W) => {
        const L = 40, T = 20, gap = 2;
        const cw = Math.min(56, (W - L) / cols.length - gap), ch = 30;
        const svg = el('svg', { width: L + cols.length * (cw + gap), height: T + rows.length * (ch + gap) + 4, class: 'heat' });
        cols.forEach((c, j) => { const tx = el('text', { x: L + j * (cw + gap) + cw / 2, y: 13, class: 'axis', 'text-anchor': 'middle' }); tx.textContent = `${c}h`; svg.append(tx); });
        rows.forEach((r, i) => {
            const tx = el('text', { x: L - 8, y: T + i * (ch + gap) + ch / 2 + 4, class: 'axis', 'text-anchor': 'end' });
            tx.textContent = r.label;
            svg.append(tx);
            cols.forEach((c, j) => {
                const v = values[r.key]?.[c];
                const closed = v === null || v === undefined;
                const cell = el('rect', {
                    x: L + j * (cw + gap), y: T + i * (ch + gap), width: cw, height: ch, rx: 4,
                    fill: closed ? 'url(#closed)' : RAMP[Math.min(RAMP.length - 1, Math.round(v * (RAMP.length - 1)))], class: 'cell', tabindex: closed ? -1 : 0,
                });
                if (!closed && v >= 0.72) { const t = el('text', { x: L + j * (cw + gap) + cw / 2, y: T + i * (ch + gap) + ch / 2 + 4, class: 'cell-label', 'text-anchor': 'middle' }); t.textContent = Math.round(v * 100); svg.append(cell, t); } else svg.append(cell);
                const msg = closed ? `<b>${esc(r.full)} ${c} h</b><div>Fermé</div>` : `<b>${esc(r.full)} ${c} h – ${c + 1} h</b><div>${Math.round(v * 100)} % du temps réservé</div>`;
                cell.addEventListener('mousemove', (e) => tip(msg, e.clientX, e.clientY));
                cell.addEventListener('mouseleave', () => tip(null));
                cell.addEventListener('focus', () => { const b = cell.getBoundingClientRect(); tip(msg, b.left, b.top); });
                cell.addEventListener('blur', () => tip(null));
            });
        });
        const defs = el('defs');
        const pat = el('pattern', { id: 'closed', width: 6, height: 6, patternUnits: 'userSpaceOnUse', patternTransform: 'rotate(45)' });
        pat.append(el('rect', { width: 6, height: 6, fill: '#f5f7fb' }), el('line', { x1: 0, y1: 0, x2: 0, y2: 6, stroke: '#e3e9f4', 'stroke-width': 2 }));
        defs.append(pat);
        svg.prepend(defs);
        node.append(svg);
    });
}

// ── 100 % stacked bar with a legend that carries the numbers ────────────────
export function share(node, items, format = 'int') {
    const total = items.reduce((a, b) => a + b.value, 0) || 1;
    const bar = Object.assign(document.createElement('div'), { className: 'share-bar' });
    const legend = Object.assign(document.createElement('div'), { className: 'share-legend' });
    items.forEach((it, i) => {
        const color = CATEGORICAL[i % CATEGORICAL.length];
        const pct = it.value / total;
        if (it.value) {
            const seg = Object.assign(document.createElement('span'), { tabIndex: 0 });
            seg.style.flex = `${it.value} 0 0`;
            seg.style.background = color;
            const msg = `<b>${esc(it.label)}</b><div>${fmt[format](it.value)} · ${Math.round(pct * 100)} %</div>`;
            seg.addEventListener('mousemove', (e) => tip(msg, e.clientX, e.clientY));
            seg.addEventListener('mouseleave', () => tip(null));
            bar.append(seg);
        }
        const row = document.createElement('div');
        row.innerHTML = `<i style="background:${color}"></i><span>${esc(it.label)}</span><b>${Math.round(pct * 100)} %</b><em>${fmt[format](it.value)}</em>`;
        legend.append(row);
    });
    node.append(bar, legend);
}

// ── Ranked horizontal bars, value at the tip ────────────────────────────────
export function ranked(node, items, format = 'money', sub = null) {
    const max = Math.max(...items.map((i) => i.value), 1);
    items.forEach((it) => {
        const row = Object.assign(document.createElement('div'), { className: 'rank', tabIndex: 0 });
        row.innerHTML = `<span class="l"></span><span class="track"><b style="width:${Math.max(2, (it.value / max) * 100)}%"></b></span><span class="v"></span>`;
        row.querySelector('.l').textContent = it.label;
        row.querySelector('.v').textContent = fmt[format](it.value);
        const msg = `<b>${esc(it.label)}</b><div>${fmt[format](it.value)}${sub ? ' · ' + esc(sub(it)) : ''}</div>`;
        row.addEventListener('mousemove', (e) => tip(msg, e.clientX, e.clientY));
        row.addEventListener('mouseleave', () => tip(null));
        node.append(row);
    });
}
