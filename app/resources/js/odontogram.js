// Dental chart drawn as an open mouth, the way the dentist looks into it:
// the upper arch (∩) on top, the lower arch (U) below, every tooth seen from its biting
// surface with the shape of its kind (incisor, canine, premolar, molar), FDI numbers
// outside the arch. Each tooth carries a whole-tooth state (missing, implant, crown,
// root canal, to extract) and findings on its five surfaces (M, O, D, V, L).
// Clicking a tooth opens it large: biting surface (tap a surface to chart it) and a side
// view with its roots. Phone: one arch at a time and a bottom sheet.

const STATES = { present: 'Présente', missing: 'Absente', implant: 'Implant', crown: 'Couronne', root_canal: 'Dévitalisée', to_extract: 'À extraire' };
const FINDINGS = { caries: 'Carie', filling: 'Obturation' };
const SURFACE_NAMES = { M: 'mésiale', O: 'occlusale', D: 'distale', V: 'vestibulaire', L: 'linguale / palatine' };
const POSITION = ['', 'incisive centrale', 'incisive latérale', 'canine', '1re prémolaire', '2e prémolaire', '1re molaire', '2e molaire', 'dent de sagesse'];
const QUADRANT = ['', 'supérieure droite', 'supérieure gauche', 'inférieure gauche', 'inférieure droite'];
const UPPER = [18, 17, 16, 15, 14, 13, 12, 11, 21, 22, 23, 24, 25, 26, 27, 28];
const LOWER = [48, 47, 46, 45, 44, 43, 42, 41, 31, 32, 33, 34, 35, 36, 37, 38];
const NS = 'http://www.w3.org/2000/svg';

// Relative size seen from above: [mesio-distal width, bucco-lingual depth].
const SIZE = { 1: [0.8, 0.62], 2: [0.64, 0.58], 3: [0.7, 0.74], 4: [0.68, 0.86], 5: [0.66, 0.84], 6: [1, 1.02], 7: [0.94, 0.98], 8: [0.86, 0.92] };
const LOWER_SIZE = { 1: [0.56, 0.56], 2: [0.6, 0.58] }; // lower incisors are narrow

const toothName = (n) => { const s = `${POSITION[n % 10]} ${QUADRANT[Math.floor(n / 10)]}`; return s[0].toUpperCase() + s.slice(1); };
const isUpper = (n) => n < 30;
const kind = (n) => { const p = n % 10; return p <= 2 ? 'incisor' : p === 3 ? 'canine' : p <= 5 ? 'premolar' : 'molar'; };
const rootCount = (n) => { const p = n % 10; return p >= 6 ? (isUpper(n) ? 3 : 2) : p === 4 && isUpper(n) ? 2 : 1; };
const size = (n) => (!isUpper(n) && LOWER_SIZE[n % 10]) || SIZE[n % 10];
const el = (name, attrs = {}) => { const e = document.createElementNS(NS, name); for (const [k, v] of Object.entries(attrs)) e.setAttribute(k, v); return e; };
let uid = 0;

export function describe(t) {
    if (!t) return 'Saine';
    const parts = t.state && t.state !== 'present' ? [STATES[t.state]] : [];
    for (const [k, label] of Object.entries(FINDINGS)) {
        const on = Object.entries(t.surfaces || {}).filter(([, v]) => v === k).map(([s]) => s);
        if (on.length) parts.push(`${label.toLowerCase()} ${on.join(', ')}`);
    }
    return parts.length ? parts.join(' · ').replace(/^./, (c) => c.toUpperCase()) : 'Saine';
}

// ── One tooth seen from its biting surface, centred on (0,0) ────────────────
// Local axes: x runs along the arch, -y points outward (the vestibular side).
function outline(type, w, d) {
    const hw = w / 2, hd = d / 2;
    switch (type) {
        case 'incisor': // wide on the lip side, narrowing to the cingulum
            return `M ${-hw} ${-hd * 0.2} Q ${-hw} ${-hd} 0 ${-hd} Q ${hw} ${-hd} ${hw} ${-hd * 0.2} Q ${hw * 0.85} ${hd * 0.6} 0 ${hd} Q ${-hw * 0.85} ${hd * 0.6} ${-hw} ${-hd * 0.2} Z`;
        case 'canine': // a rounded diamond around the cusp
            return `M ${-hw} 0 Q ${-hw * 0.9} ${-hd} 0 ${-hd} Q ${hw * 0.9} ${-hd} ${hw} 0 Q ${hw * 0.85} ${hd} 0 ${hd} Q ${-hw * 0.85} ${hd} ${-hw} 0 Z`;
        case 'premolar': { // an oval, a little squared
            const r = Math.min(hw, hd) * 0.9;
            return `M ${-hw + r} ${-hd} H ${hw - r} Q ${hw} ${-hd} ${hw} ${-hd + r} V ${hd - r} Q ${hw} ${hd} ${hw - r} ${hd} H ${-hw + r} Q ${-hw} ${hd} ${-hw} ${hd - r} V ${-hd + r} Q ${-hw} ${-hd} ${-hw + r} ${-hd} Z`;
        }
        default: { // molar: a rounded square with a slight bulge on each side
            const r = Math.min(hw, hd) * 0.42, b = w * 0.04;
            return `M ${-hw + r} ${-hd} Q 0 ${-hd - b} ${hw - r} ${-hd} Q ${hw} ${-hd} ${hw} ${-hd + r} Q ${hw + b} 0 ${hw} ${hd - r} Q ${hw} ${hd} ${hw - r} ${hd} Q 0 ${hd + b} ${-hw + r} ${hd} Q ${-hw} ${hd} ${-hw} ${hd - r} Q ${-hw - b} 0 ${-hw} ${-hd + r} Q ${-hw} ${-hd} ${-hw + r} ${-hd} Z`;
        }
    }
}

// Grooves and ridges, drawn faintly so a tooth reads as a tooth.
function anatomy(type, w, d) {
    const hw = w / 2, hd = d / 2;
    switch (type) {
        case 'incisor': return [`M ${-hw * 0.7} ${-hd * 0.55} Q 0 ${-hd * 0.7} ${hw * 0.7} ${-hd * 0.55}`];
        case 'canine': return [`M 0 ${-hd * 0.75} L 0 ${hd * 0.55}`, `M ${-hw * 0.7} 0 L 0 ${-hd * 0.2} L ${hw * 0.7} 0`];
        case 'premolar': return [`M ${-hw * 0.6} 0 Q 0 ${hd * 0.12} ${hw * 0.6} 0`];
        default: return [
            `M ${-hw * 0.65} ${-hd * 0.05} Q 0 ${hd * 0.15} ${hw * 0.65} ${-hd * 0.05}`,
            `M ${-hw * 0.05} ${-hd * 0.7} L 0 ${hd * 0.05} L ${hw * 0.1} ${hd * 0.7}`,
        ];
    }
}

// Five surface areas cut from the tooth's box: V outward, L inward, M toward the midline.
function surfaceAreas(w, d, mesialPlusX) {
    const hw = w / 2, hd = d / 2, ix = w * 0.24, iy = d * 0.24;
    const box = {
        top: [[-hw, -hd], [hw, -hd], [hw - ix, -hd + iy], [-hw + ix, -hd + iy]],
        bottom: [[-hw, hd], [hw, hd], [hw - ix, hd - iy], [-hw + ix, hd - iy]],
        right: [[hw, -hd], [hw, hd], [hw - ix, hd - iy], [hw - ix, -hd + iy]],
        left: [[-hw, -hd], [-hw, hd], [-hw + ix, hd - iy], [-hw + ix, -hd + iy]],
        centre: [[-hw + ix, -hd + iy], [hw - ix, -hd + iy], [hw - ix, hd - iy], [-hw + ix, hd - iy]],
    };
    return { V: box.top, L: box.bottom, O: box.centre, M: mesialPlusX ? box.right : box.left, D: mesialPlusX ? box.left : box.right };
}

/**
 * Draws a tooth into a <g>. `scale` is pixels per unit; `labels` writes M/O/D/V/L on it.
 * Returns the group; surfaces carry data-surface so the big view can be clicked.
 */
function occlusalTooth(n, t, scale, mesialPlusX, { labels = false } = {}) {
    const [sw, sd] = size(n);
    const w = sw * scale, d = sd * scale;
    const type = kind(n);
    const g = el('g', { class: `occ ${t?.state || ''}` });
    const clipId = `clip-${++uid}`;
    const shape = outline(type, w, d);
    const clip = el('clipPath', { id: clipId });
    clip.append(el('path', { d: shape }));
    g.append(clip, el('path', { d: shape, class: 'enamel' }));

    const areas = el('g', { 'clip-path': `url(#${clipId})` });
    for (const [s, pts] of Object.entries(surfaceAreas(w, d, mesialPlusX))) {
        const finding = t?.surfaces?.[s];
        areas.append(el('polygon', { points: pts.map((p) => p.join(',')).join(' '), class: `sf ${finding || ''}`, 'data-surface': s }));
    }
    g.append(areas);
    for (const a of anatomy(type, w, d)) g.append(el('path', { d: a, class: 'groove' }));
    g.append(el('path', { d: shape, class: 'edge' }));

    if (t?.state === 'crown') g.append(el('path', { d: shape, class: 'crown-ring' }));
    if (t?.state === 'implant') {
        const r = Math.min(w, d) * 0.2;
        g.append(el('circle', { r, class: 'implant-head' }), el('path', { d: `M ${-r * 0.55} 0 H ${r * 0.55} M 0 ${-r * 0.55} V ${r * 0.55}`, class: 'implant-cross' }));
    }
    if (t?.state === 'root_canal') g.append(el('circle', { r: Math.min(w, d) * 0.09, class: 'canal-dot' }));
    if (t?.state === 'to_extract') {
        const k = Math.min(w, d) * 0.42;
        g.append(el('path', { d: `M ${-k} ${-k} L ${k} ${k} M ${k} ${-k} L ${-k} ${k}`, class: 'extract' }));
    }
    if (labels) {
        for (const [s, pts] of Object.entries(surfaceAreas(w, d, mesialPlusX))) {
            const cx = pts.reduce((a, p) => a + p[0], 0) / 4, cy = pts.reduce((a, p) => a + p[1], 0) / 4;
            const tx = el('text', { x: cx, y: cy + 5, class: 'sf-label' });
            tx.textContent = s;
            g.append(tx);
        }
    }
    return g;
}

// ── The arch: teeth laid along half an ellipse, sized by kind ───────────────
function archPoints(cx, cy, rx, ry, upper) {
    const pts = [];
    for (let i = 0; i <= 600; i++) {
        const th = Math.PI - (Math.PI * i) / 600; // left end → apex → right end
        // A slightly flattened ellipse: narrow at the front, near-parallel at the back.
        const c = Math.cos(th), s = Math.sin(th);
        const x = cx + rx * Math.sign(c) * Math.abs(c) ** 0.92;
        const y = upper ? cy - ry * s ** 1.15 : cy + ry * s ** 1.15;
        pts.push([x, y]);
    }
    let len = 0;
    return pts.map((p, i) => { if (i) len += Math.hypot(p[0] - pts[i - 1][0], p[1] - pts[i - 1][1]); return { x: p[0], y: p[1], s: len }; });
}

function at(pts, s) {
    let i = pts.findIndex((p) => p.s >= s);
    if (i < 1) i = 1;
    const a = pts[i - 1], b = pts[i];
    const f = (s - a.s) / (b.s - a.s || 1);
    return { x: a.x + (b.x - a.x) * f, y: a.y + (b.y - a.y) * f, tx: b.x - a.x, ty: b.y - a.y };
}

// A real arch is deeper than it is wide. Both screens use the same proportions: the
// phone shows one arch at a time, the desktop stacks them like an open mouth.
const W = 600, H = 420, RX = 238, RY = 318;

function drawArch(svg, teeth, data, selected, upper, onPick) {
    const rx = RX, ry = RY;
    svg.setAttribute('viewBox', `0 0 ${W} ${H}`);
    const cx = W / 2, cy = upper ? H - 40 : 40;
    const pts = archPoints(cx, cy, rx, ry, upper);
    const total = pts.at(-1).s;
    const gap = 0.05;
    const units = teeth.reduce((a, n) => a + size(n)[0], 0) + gap * (teeth.length - 1) + 0.25; // + a little space at the midline
    const scale = (total * 0.97) / units;

    // Palate (upper) or tongue (lower) inside the arch, fading toward the throat so the
    // open back of the arch has no hard edge; then the gum the teeth sit in.
    const gid = `soft-${++uid}`;
    const defs = el('defs');
    const grad = el('radialGradient', { id: gid, cx: '50%', cy: upper ? '8%' : '92%', r: '95%' });
    const tone = upper ? '#efc4c4' : '#e9a9ab';
    grad.append(el('stop', { offset: '0%', 'stop-color': tone, 'stop-opacity': upper ? 0.5 : 0.55 }),
        el('stop', { offset: '70%', 'stop-color': tone, 'stop-opacity': 0.18 }), el('stop', { offset: '100%', 'stop-color': tone, 'stop-opacity': 0 }));
    defs.append(grad);
    const inner = archPoints(cx, cy, rx - scale * 0.7, ry - scale * 0.7, upper);
    const soft = el('path', { d: `M ${inner.map((p) => `${p.x.toFixed(1)} ${p.y.toFixed(1)}`).join(' L ')} Z`, fill: `url(#${gid})` });
    const gum = el('path', { d: `M ${pts.map((p) => `${p.x.toFixed(1)} ${p.y.toFixed(1)}`).join(' L ')}`, class: 'gum', 'stroke-width': scale * 1.4 });
    svg.append(defs, soft, gum);
    if (upper) {
        // The palatal seam and ridges, faint: they make the roof of the mouth read as one.
        const top = inner[300];
        svg.append(el('path', { d: `M ${cx} ${top.y + 18} L ${cx} ${cy - ry * 0.25}`, class: 'raphe' }));
        for (const k of [0.14, 0.24, 0.34]) {
            const y = top.y + ry * k;
            svg.append(el('path', { d: `M ${cx - rx * 0.42} ${y + 14} Q ${cx - rx * 0.2} ${y - 6} ${cx - 8} ${y + 2} M ${cx + 8} ${y + 2} Q ${cx + rx * 0.2} ${y - 6} ${cx + rx * 0.42} ${y + 14}`, class: 'rugae' }));
        }
    } else {
        svg.append(el('path', { d: `M ${cx} ${cy + ry * 0.25} L ${cx} ${inner[300].y - 22}`, class: 'raphe tongue-line' }));
    }

    let s = total * 0.015;
    teeth.forEach((n, i) => {
        const [w] = size(n);
        if (i === 8) s += scale * 0.25;
        const p = at(pts, s + (w * scale) / 2);
        s += w * scale + gap * scale;
        const tl = Math.hypot(p.tx, p.ty) || 1;
        // Outward normal: perpendicular to the arch, away from its inside.
        let nx = p.ty / tl, ny = -p.tx / tl;
        if ((p.x - cx) * nx + (p.y - cy) * ny < 0) { nx = -nx; ny = -ny; }
        const rot = (Math.atan2(nx, -ny) * 180) / Math.PI;
        // Local +x follows the travel on the upper arch and goes against it on the lower one.
        const beforeMidline = i < 8;
        const mesialPlusX = upper ? beforeMidline : !beforeMidline;

        const t = data[n];
        const g = el('g', {
            class: `tooth ${selected === n ? 'on' : ''} ${t ? 'has' : ''}`, tabindex: 0, role: 'button',
            transform: `translate(${p.x.toFixed(1)} ${p.y.toFixed(1)}) rotate(${rot.toFixed(1)})`,
            'aria-label': `${n}, ${toothName(n)} : ${describe(t)}`, 'data-tooth': n,
        });
        const title = el('title');
        title.textContent = `${n} · ${describe(t)}${t?.note ? ' — ' + t.note : ''}`;
        g.append(title, el('circle', { r: scale * 0.62, class: 'hit' }), occlusalTooth(n, t, scale, mesialPlusX));
        g.addEventListener('click', () => onPick(n));
        g.addEventListener('keydown', (e) => { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); onPick(n); } });
        svg.append(g);

        const [, dd] = size(n);
        const lx = p.x + nx * (dd * scale * 0.5 + 15), ly = p.y + ny * (dd * scale * 0.5 + 15);
        const label = el('text', { x: lx.toFixed(1), y: (ly + 4).toFixed(1), class: `num ${selected === n ? 'on' : ''}` });
        label.textContent = n;
        svg.append(label);
    });
    // Side labels once between the two arches on desktop; each arch keeps its own on a phone.
    const side = (x, txt) => { const tx = el('text', { x, y: upper ? H - 8 : 22, class: `side ${upper ? '' : 'phone-only'}` }); tx.textContent = txt; svg.append(tx); };
    side(24, 'DROITE');
    side(W - 24, 'GAUCHE');
}

// ── Side view of the selected tooth: crown and roots of its kind ─────────────
function profileTooth(n, t) {
    const svg = el('svg', { viewBox: '0 0 120 200', class: 'profile' });
    const up = isUpper(n), type = kind(n);
    const g = el('g', { transform: up ? 'translate(60 200) scale(1 -1)' : 'translate(60 0)' }); // roots go up for upper teeth
    const cw = type === 'molar' ? 74 : type === 'premolar' ? 52 : type === 'canine' ? 46 : 44;
    const ch = type === 'incisor' ? 70 : 62;
    const crownD = type === 'molar'
        ? `M ${-cw / 2} 30 Q ${-cw / 2 - 4} 4 ${-cw / 4} 10 Q ${-cw / 8} 0 0 8 Q ${cw / 8} 0 ${cw / 4} 10 Q ${cw / 2 + 4} 4 ${cw / 2} 30 L ${cw / 2 - 4} ${ch} Q 0 ${ch + 6} ${-cw / 2 + 4} ${ch} Z`
        : type === 'incisor'
            ? `M ${-cw / 2} 6 Q 0 2 ${cw / 2} 6 L ${cw / 2 - 6} ${ch} Q 0 ${ch + 6} ${-cw / 2 + 6} ${ch} Z`
            : `M ${-cw / 2} 26 Q ${-cw / 4} 8 0 2 Q ${cw / 4} 8 ${cw / 2} 26 L ${cw / 2 - 5} ${ch} Q 0 ${ch + 6} ${-cw / 2 + 5} ${ch} Z`;
    // Roots
    const rootTop = ch - 2, len = type === 'canine' ? 120 : type === 'incisor' ? 104 : 96;
    if (t?.state === 'implant') {
        const w = 20;
        g.append(el('rect', { x: -w / 2, y: rootTop, width: w, height: len - 10, rx: 5, class: 'screw' }));
        for (let k = 1; k < 7; k++) g.append(el('line', { x1: -w / 2 - 4, x2: w / 2 + 4, y1: rootTop + (k * (len - 10)) / 7, y2: rootTop + (k * (len - 10)) / 7, class: 'thread' }));
    } else if (t?.state !== 'missing') {
        const c = rootCount(n);
        const xs = c === 1 ? [0] : c === 2 ? [-cw / 4, cw / 4] : [-cw / 3, 0, cw / 3];
        xs.forEach((x, i) => {
            const L = c === 3 && i === 1 ? len * 0.85 : len;
            const rw = c === 1 ? cw * 0.32 : cw * 0.17;
            g.append(el('path', { d: `M ${x - rw} ${rootTop} C ${x - rw} ${rootTop + L * 0.6} ${x - 3} ${rootTop + L} ${x} ${rootTop + L} C ${x + 3} ${rootTop + L} ${x + rw} ${rootTop + L * 0.6} ${x + rw} ${rootTop} Z`, class: 'root' }));
            if (t?.state === 'root_canal') g.append(el('path', { d: `M ${x} ${rootTop - 14} L ${x} ${rootTop + L - 8}`, class: 'canal' }));
        });
    }
    g.append(el('path', { d: crownD, class: `crown-side ${t?.state === 'missing' ? 'missing' : ''}` }));
    if (t?.state === 'crown') g.append(el('path', { d: crownD, class: 'crown-cap' }));
    if (t?.state === 'to_extract') g.append(el('path', { d: 'M -40 10 L 40 150 M 40 10 L -40 150', class: 'extract' }));
    svg.append(g);
    return svg;
}

// ── Mount ────────────────────────────────────────────────────────────────────
export function mountOdontogram(root) {
    const data = JSON.parse(root.dataset.teeth || '{}');
    const history = JSON.parse(root.dataset.history || '{}');
    const editable = !!root.dataset.url;
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
    let selected = null;
    let draft = null;
    let arch = 'upper';

    root.innerHTML = `
        <div class="odo-top">
            <div class="odo-summary" data-summary></div>
            <div class="odo-arch-switch" role="tablist" aria-label="Arcade">
                <button type="button" role="tab" data-arch="upper" class="on">Haut</button><button type="button" role="tab" data-arch="lower">Bas</button>
            </div>
        </div>
        <div class="odo-mouth">
            ${editable ? '<p class="odo-hint">Cliquez une dent pour noter une carie, une obturation, une couronne…</p>' : ''}
            <div class="mouth-frame">
                <span class="mouth-side">Droite<br>du patient</span>
                <div class="mouth-arches">
                    <svg class="arch upper" viewBox="0 0 600 420" data-arch-svg="upper" aria-label="Arcade supérieure"></svg>
                    <svg class="arch lower" viewBox="0 0 600 420" data-arch-svg="lower" aria-label="Arcade inférieure"></svg>
                </div>
                <span class="mouth-side">Gauche<br>du patient</span>
            </div>
        </div>
        <div class="odo-legend">
            <span><i class="lg caries"></i>Carie</span><span><i class="lg filling"></i>Obturation</span><span><i class="lg ring"></i>Couronne</span>
            <span><i class="lg canal"></i>Dévitalisée</span><span><i class="lg implant"></i>Implant</span><span><i class="lg missing"></i>Absente</span><span><i class="lg extract"></i>À extraire</span>
        </div>
        <div class="odo-sheet-backdrop" data-backdrop hidden></div>
        <aside class="odo-editor" data-editor hidden></aside>`;

    const editor = root.querySelector('[data-editor]');
    const backdrop = root.querySelector('[data-backdrop]');

    function drawChart() {
        for (const [key, teeth] of [['upper', UPPER], ['lower', LOWER]]) {
            const svg = root.querySelector(`[data-arch-svg="${key}"]`);
            svg.replaceChildren();
            drawArch(svg, teeth, data, selected, key === 'upper', select);
        }
        const list = Object.entries(data).sort(([a], [b]) => a - b);
        const summary = root.querySelector('[data-summary]');
        const urgent = (t) => t.state === 'to_extract' || Object.values(t.surfaces || {}).includes('caries');
        const toTreat = list.filter(([, t]) => urgent(t)).length;
        summary.replaceChildren();
        if (!list.length) { summary.textContent = 'Aucune observation : toutes les dents sont saines.'; return; }
        const head = document.createElement('b');
        head.textContent = toTreat ? `${toTreat} dent${toTreat > 1 ? 's' : ''} à traiter` : 'Rien à traiter';
        summary.append(head);
        for (const [n, t] of list) {
            const chip = document.createElement('button');
            chip.type = 'button';
            chip.className = `odo-chip ${urgent(t) ? 'urgent' : ''}`;
            chip.innerHTML = '<span class="n"></span><span></span>';
            chip.firstChild.textContent = n;
            chip.lastChild.textContent = describe(t);
            chip.onclick = () => { arch = isUpper(+n) ? 'upper' : 'lower'; syncArch(); select(+n); };
            summary.append(chip);
        }
    }

    function syncArch() {
        root.dataset.arch = arch;
        root.querySelectorAll('[data-arch]').forEach((b) => { b.classList.toggle('on', b.dataset.arch === arch); b.setAttribute('aria-selected', b.dataset.arch === arch); });
    }
    root.querySelectorAll('[data-arch]').forEach((b) => b.addEventListener('click', () => { arch = b.dataset.arch; syncArch(); }));

    function select(n) {
        selected = n;
        const t = data[n] || {};
        draft = { state: t.state || 'present', surfaces: { ...(t.surfaces || {}) }, note: t.note || '' };
        drawChart();
        drawEditor();
        editor.hidden = false;
        backdrop.hidden = false;
        root.classList.add('editing');
    }

    function close() {
        selected = null;
        editor.hidden = true;
        backdrop.hidden = true;
        root.classList.remove('editing');
        drawChart();
    }
    backdrop.onclick = close;
    document.addEventListener('keydown', (e) => { if (e.key === 'Escape' && !editor.hidden) close(); });

    function drawEditor() {
        const n = selected;
        const noSurfaces = draft.state === 'missing' || draft.state === 'implant';
        const shown = { state: draft.state, surfaces: draft.surfaces };
        editor.innerHTML = `
            <div class="ed-hd"><div><b>${n}</b><span>${toothName(n)}</span></div><button type="button" class="ed-x" aria-label="Fermer">✕</button></div>
            <div class="ed-views">
                <figure><div data-big></div><figcaption>Vue de dessus · ${noSurfaces ? 'pas de faces' : 'touchez une face'}</figcaption></figure>
                <figure><div data-profile></div><figcaption>Vue de profil</figcaption></figure>
            </div>
            <div class="ed-label">État de la dent</div>
            <div class="ed-states">${Object.entries(STATES).map(([k, v]) => `<button type="button" data-state="${k}" class="${draft.state === k ? 'on' : ''}" ${editable ? '' : 'disabled'}>${v}</button>`).join('')}</div>
            <div class="ed-label">Faces <span>${noSurfaces ? 'sans objet pour une dent ' + (draft.state === 'missing' ? 'absente' : 'implantée') : 'carie, puis obturation, puis rien'}</span></div>
            <div class="ed-surface-list">${['M', 'O', 'D', 'V', 'L'].map((s) => `<button type="button" data-face="${s}" ${editable && !noSurfaces ? '' : 'disabled'} class="${draft.surfaces[s] || ''}"><b>${s}</b> ${SURFACE_NAMES[s]}<em>${draft.surfaces[s] ? FINDINGS[draft.surfaces[s]] : '—'}</em></button>`).join('')}</div>
            <label class="ed-label" for="ed-note">Note</label>
            <input id="ed-note" class="form-control form-control-sm" maxlength="200" placeholder="Ex : composite 2024, sensible au froid" ${editable ? '' : 'disabled'}>
            ${editable ? '<div class="ed-actions"><button type="button" class="btn btn-outline-secondary btn-sm" data-clear>Dent saine</button><button type="button" class="btn btn-primary btn-sm" data-save>Enregistrer</button></div>' : ''}
            <div class="ed-label">Historique</div>
            <ul class="ed-history">${(history[n] || []).map((h) => `<li><span>${h.at}${h.who ? ' · ' + h.who : ''}</span>${h.what.replace(/</g, '&lt;')}</li>`).join('') || '<li class="empty">Aucune modification enregistrée.</li>'}</ul>`;
        editor.querySelector('#ed-note').value = draft.note;

        // Big biting surface, oriented like in the mouth (vestibular up for the upper teeth).
        const big = el('svg', { viewBox: '-95 -95 190 190', class: 'big-occ' });
        const mesialPlusX = UPPER.indexOf(n) > -1 ? UPPER.indexOf(n) < 8 : LOWER.indexOf(n) >= 8;
        const g = occlusalTooth(n, shown, 150, mesialPlusX, { labels: !noSurfaces });
        if (!isUpper(n)) g.setAttribute('transform', 'rotate(180)');
        g.querySelectorAll('.sf-label').forEach((tx) => { if (!isUpper(n)) tx.setAttribute('transform', `rotate(180 ${tx.getAttribute('x')} ${+tx.getAttribute('y') - 5})`); });
        big.append(g);
        editor.querySelector('[data-big]').append(big);
        editor.querySelector('[data-profile]').append(profileTooth(n, shown));

        const cycle = (s) => {
            if (!editable || noSurfaces) return;
            draft.surfaces[s] = !draft.surfaces[s] ? 'caries' : draft.surfaces[s] === 'caries' ? 'filling' : null;
            if (!draft.surfaces[s]) delete draft.surfaces[s];
            draft.note = editor.querySelector('#ed-note').value;
            drawEditor();
        };
        big.querySelectorAll('[data-surface]').forEach((p) => p.addEventListener('click', () => cycle(p.dataset.surface)));
        editor.querySelectorAll('[data-face]').forEach((b) => b.addEventListener('click', () => cycle(b.dataset.face)));
        editor.querySelector('.ed-x').onclick = close;
        editor.querySelectorAll('[data-state]').forEach((b) => b.addEventListener('click', () => {
            draft.state = b.dataset.state;
            draft.note = editor.querySelector('#ed-note').value;
            drawEditor();
        }));
        editor.querySelector('[data-clear]')?.addEventListener('click', () => { draft = { state: 'present', surfaces: {}, note: '' }; drawEditor(); });
        editor.querySelector('[data-save]')?.addEventListener('click', async (e) => {
            e.target.disabled = true;
            draft.note = editor.querySelector('#ed-note').value.trim();
            const res = await fetch(root.dataset.url, {
                method: 'POST',
                headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf },
                body: JSON.stringify({ tooth: n, state: draft.state, surfaces: draft.surfaces, note: draft.note || null }),
            });
            if (!res.ok) { e.target.disabled = false; return; }
            const saved = await res.json();
            const healthy = saved.state === 'present' && !Object.keys(saved.surfaces || {}).length && !saved.note;
            if (healthy) delete data[n]; else data[n] = { state: saved.state, surfaces: saved.surfaces, note: saved.note };
            history[n] = saved.history;
            close();
        });
    }

    syncArch();
    drawChart();
}
