// Dental chart (odontogram), FDI numbering, drawn as the dentist sees the patient:
//   18 … 11 | 21 … 28   upper arch, roots up
//   48 … 41 | 31 … 38   lower arch, roots down
// Each tooth: whole-tooth state (missing, implant, crown, root canal, to extract) and
// findings per surface (M, O, D, V, L: caries or filling). Phone: one arch at a time,
// editing in a bottom sheet.

const STATES = { present: 'Présente', missing: 'Absente', implant: 'Implant', crown: 'Couronne', root_canal: 'Dévitalisée', to_extract: 'À extraire' };
const FINDINGS = { caries: 'Carie', filling: 'Obturation' };
const SURFACE_NAMES = { M: 'mésiale', O: 'occlusale', D: 'distale', V: 'vestibulaire', L: 'linguale / palatine' };
const POSITION = ['', 'incisive centrale', 'incisive latérale', 'canine', '1re prémolaire', '2e prémolaire', '1re molaire', '2e molaire', 'dent de sagesse'];
const QUADRANT = ['', 'supérieure droite', 'supérieure gauche', 'inférieure gauche', 'inférieure droite'];
const UPPER_R = [18, 17, 16, 15, 14, 13, 12, 11];
const UPPER_L = [21, 22, 23, 24, 25, 26, 27, 28];
const LOWER_R = [48, 47, 46, 45, 44, 43, 42, 41];
const LOWER_L = [31, 32, 33, 34, 35, 36, 37, 38];
const SVG = 'http://www.w3.org/2000/svg';

const toothName = (n) => { const s = `${POSITION[n % 10]} ${QUADRANT[Math.floor(n / 10)]}`; return s[0].toUpperCase() + s.slice(1); };
const isUpper = (n) => n < 30;
const roots = (n) => { const p = n % 10; return p >= 6 ? (isUpper(n) ? 3 : 2) : p === 4 && isUpper(n) ? 2 : 1; };

export function describe(t) {
    if (!t) return 'Saine';
    const parts = t.state && t.state !== 'present' ? [STATES[t.state]] : [];
    for (const [k, label] of Object.entries(FINDINGS)) {
        const on = Object.entries(t.surfaces || {}).filter(([, v]) => v === k).map(([s]) => s);
        if (on.length) parts.push(`${label.toLowerCase()} ${on.join(', ')}`);
    }
    return parts.length ? parts.join(' · ').replace(/^./, (c) => c.toUpperCase()) : 'Saine';
}

// Which side of the drawn square is which surface. Vestibular faces outward (top above,
// bottom below); mesial faces the midline (right for the patient's right side).
function sides(n) {
    const q = Math.floor(n / 10);
    const mesialRight = q === 1 || q === 4;
    return isUpper(n)
        ? { top: 'V', bottom: 'L', right: mesialRight ? 'M' : 'D', left: mesialRight ? 'D' : 'M' }
        : { top: 'L', bottom: 'V', right: mesialRight ? 'M' : 'D', left: mesialRight ? 'D' : 'M' };
}

const el = (name, attrs = {}) => { const e = document.createElementNS(SVG, name); for (const [k, v] of Object.entries(attrs)) e.setAttribute(k, v); return e; };

// A square crown seen from above, cut into five surfaces.
function crown(n, t, x, y, s, { big = false } = {}) {
    const g = el('g', { class: 'crown' });
    const i = s * 0.3; // inner square inset
    const side = sides(n);
    const polys = {
        [side.top]: [[x, y], [x + s, y], [x + s - i, y + i], [x + i, y + i]],
        [side.right]: [[x + s, y], [x + s, y + s], [x + s - i, y + s - i], [x + s - i, y + i]],
        [side.bottom]: [[x, y + s], [x + s, y + s], [x + s - i, y + s - i], [x + i, y + s - i]],
        [side.left]: [[x, y], [x, y + s], [x + i, y + s - i], [x + i, y + i]],
        O: [[x + i, y + i], [x + s - i, y + i], [x + s - i, y + s - i], [x + i, y + s - i]],
    };
    for (const [surface, pts] of Object.entries(polys)) {
        const finding = t?.surfaces?.[surface];
        const p = el('polygon', { points: pts.map((pt) => pt.join(',')).join(' '), class: `sf ${finding || ''}`, 'data-surface': surface });
        g.append(p);
        if (big) {
            const cx = pts.reduce((a, pt) => a + pt[0], 0) / 4, cy = pts.reduce((a, pt) => a + pt[1], 0) / 4;
            const label = el('text', { x: cx, y: cy + 4, class: 'sf-label' });
            label.textContent = surface;
            g.append(label);
        }
    }
    if (t?.state === 'crown') g.append(el('rect', { x: x - 2.5, y: y - 2.5, width: s + 5, height: s + 5, rx: 4, class: 'crown-ring' }));
    return g;
}

// Roots: one, two or three tapered strokes; a screw for an implant.
function rootsShape(n, t, cx, y0, dir, len) {
    const g = el('g', { class: 'roots' });
    if (t?.state === 'implant') {
        const w = 9;
        g.append(el('rect', { x: cx - w / 2, y: dir > 0 ? y0 : y0 - len, width: w, height: len, rx: 2, class: 'screw' }));
        for (let k = 1; k < 5; k++) {
            const yy = y0 + dir * (len * k) / 5;
            g.append(el('line', { x1: cx - w / 2 - 2, x2: cx + w / 2 + 2, y1: yy, y2: yy, class: 'thread' }));
        }
        return g;
    }
    const count = roots(n);
    const spread = count === 1 ? [0] : count === 2 ? [-5, 5] : [-7, 0, 7];
    for (const dx of spread) {
        const tip = y0 + dir * len * (count === 3 && dx === 0 ? 0.8 : 1);
        const w = count === 1 ? 6 : 4.5;
        g.append(el('path', { d: `M ${cx + dx - w} ${y0} C ${cx + dx - w} ${(y0 + tip) / 2} ${cx + dx - 1.5} ${tip} ${cx + dx} ${tip} C ${cx + dx + 1.5} ${tip} ${cx + dx + w} ${(y0 + tip) / 2} ${cx + dx + w} ${y0} Z`, class: 'root' }));
        if (t?.state === 'root_canal') g.append(el('line', { x1: cx + dx, x2: cx + dx, y1: y0, y2: tip - dir * 3, class: 'canal' }));
    }
    return g;
}

function toothSvg(n, t, w) {
    const upper = isUpper(n);
    const h = 104, s = 28, cx = w / 2;
    const svg = el('svg', { viewBox: `0 0 ${w} ${h}`, width: w, height: h, class: `tooth-svg ${t?.state || ''}` });
    const crownY = upper ? 52 : 24;
    const rootLen = (n % 10) === 3 ? 40 : 34;
    svg.append(rootsShape(n, t, cx, upper ? crownY : crownY + s, upper ? -1 : 1, rootLen));
    svg.append(crown(n, t, cx - s / 2, crownY, s));
    if (t?.state === 'to_extract') {
        svg.append(el('line', { x1: 6, y1: 8, x2: w - 6, y2: h - 22, class: 'extract' }), el('line', { x1: w - 6, y1: 8, x2: 6, y2: h - 22, class: 'extract' }));
    }
    const num = el('text', { x: cx, y: upper ? h - 6 : 14, class: 'num' });
    num.textContent = n;
    svg.append(num);
    return svg;
}

export function mountOdontogram(root) {
    const data = JSON.parse(root.dataset.teeth || '{}');
    const history = JSON.parse(root.dataset.history || '{}');
    const editable = root.dataset.url !== undefined && root.dataset.url !== '';
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
        <div class="odo-chart" data-chart>
            ${editable ? '<p class="odo-hint">Cliquez une dent pour noter une carie, une obturation, une couronne…</p>' : ''}
            <div class="odo-side-labels"><span>Droite du patient</span><span>Gauche du patient</span></div>
            <div class="odo-arch upper" data-arch-row="upper"><div class="q" data-q="${UPPER_R}"></div><div class="mid"></div><div class="q" data-q="${UPPER_L}"></div></div>
            <div class="odo-arch lower" data-arch-row="lower"><div class="q" data-q="${LOWER_R}"></div><div class="mid"></div><div class="q" data-q="${LOWER_L}"></div></div>
        </div>
        <div class="odo-legend">
            <span><i class="lg caries"></i>Carie</span><span><i class="lg filling"></i>Obturation</span><span><i class="lg ring"></i>Couronne</span>
            <span><i class="lg canal"></i>Dévitalisée</span><span><i class="lg screw"></i>Implant</span><span><i class="lg missing"></i>Absente</span><span><i class="lg extract"></i>À extraire</span>
        </div>
        <div class="odo-sheet-backdrop" data-backdrop hidden></div>
        <aside class="odo-editor" data-editor hidden></aside>`;

    const chart = root.querySelector('[data-chart]');
    const editor = root.querySelector('[data-editor]');
    const backdrop = root.querySelector('[data-backdrop]');

    function drawChart() {
        chart.querySelectorAll('[data-q]').forEach((q) => {
            q.replaceChildren(...q.dataset.q.split(',').map(Number).map((n) => {
                const t = data[n];
                const b = document.createElement('button');
                b.type = 'button';
                b.className = `odo-tooth ${t ? 'has' : ''} ${t?.state === 'missing' ? 'missing' : ''} ${selected === n ? 'on' : ''}`;
                b.dataset.tooth = n;
                b.setAttribute('aria-label', `${n}, ${toothName(n)} : ${describe(t)}`);
                b.title = `${n} · ${describe(t)}${t?.note ? ' — ' + t.note : ''}`;
                b.append(toothSvg(n, t, 44));
                b.onclick = () => select(n);
                return b;
            }));
        });
        const list = Object.entries(data).sort(([a], [b]) => a - b);
        const summary = root.querySelector('[data-summary]');
        const toTreat = list.filter(([, t]) => t.state === 'to_extract' || Object.values(t.surfaces || {}).includes('caries')).length;
        summary.replaceChildren();
        if (!list.length) summary.textContent = 'Aucune observation : toutes les dents sont saines.';
        else {
            const head = document.createElement('b');
            head.textContent = toTreat ? `${toTreat} dent${toTreat > 1 ? 's' : ''} à traiter` : 'Rien à traiter';
            summary.append(head);
            for (const [n, t] of list) {
                const chip = document.createElement('button');
                chip.type = 'button';
                const urgent = t.state === 'to_extract' || Object.values(t.surfaces || {}).includes('caries');
                chip.className = `odo-chip ${urgent ? 'urgent' : ''}`;
                chip.innerHTML = '<span class="n"></span><span></span>';
                chip.firstChild.textContent = n;
                chip.lastChild.textContent = describe(t);
                chip.onclick = () => { arch = isUpper(+n) ? 'upper' : 'lower'; syncArch(); select(+n); };
                summary.append(chip);
            }
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

    function drawEditor() {
        const n = selected;
        const noSurfaces = draft.state === 'missing' || draft.state === 'implant';
        editor.innerHTML = `
            <div class="ed-hd"><div><b>${n}</b><span>${toothName(n)}</span></div><button type="button" class="ed-x" aria-label="Fermer">✕</button></div>
            <div class="ed-label">État de la dent</div>
            <div class="ed-states">${Object.entries(STATES).map(([k, v]) => `<button type="button" data-state="${k}" class="${draft.state === k ? 'on' : ''}" ${editable ? '' : 'disabled'}>${k === 'present' ? 'Présente' : v}</button>`).join('')}</div>
            <div class="ed-label">Faces <span>${noSurfaces ? 'sans objet pour une dent ' + (draft.state === 'missing' ? 'absente' : 'implantée') : 'touchez une face : carie, puis obturation, puis rien'}</span></div>
            <div class="ed-surfaces ${noSurfaces ? 'off' : ''}" data-big></div>
            <div class="ed-surface-list">${['M', 'O', 'D', 'V', 'L'].map((s) => `<span>${s} <small>${SURFACE_NAMES[s]}</small> <b>${draft.surfaces[s] ? FINDINGS[draft.surfaces[s]] : '—'}</b></span>`).join('')}</div>
            <label class="ed-label" for="ed-note">Note</label>
            <input id="ed-note" class="form-control form-control-sm" maxlength="200" placeholder="Ex : composite 2024, sensible au froid" value="" ${editable ? '' : 'disabled'}>
            ${editable ? '<div class="ed-actions"><button type="button" class="btn btn-outline-secondary btn-sm" data-clear>Dent saine</button><button type="button" class="btn btn-primary btn-sm" data-save>Enregistrer</button></div>' : ''}
            <div class="ed-label">Historique</div>
            <ul class="ed-history">${(history[n] || []).map((h) => `<li><span>${h.at}${h.who ? ' · ' + h.who : ''}</span>${h.what.replace(/</g, '&lt;')}</li>`).join('') || '<li class="empty">Aucune modification enregistrée.</li>'}</ul>`;
        editor.querySelector('#ed-note').value = draft.note;

        const big = editor.querySelector('[data-big]');
        const svg = el('svg', { viewBox: '0 0 160 160', width: 160, height: 160 });
        svg.append(crown(n, { surfaces: draft.surfaces, state: draft.state }, 10, 10, 140, { big: true }));
        big.append(svg);
        if (editable && !noSurfaces) {
            svg.querySelectorAll('[data-surface]').forEach((p) => p.addEventListener('click', () => {
                const s = p.dataset.surface;
                draft.surfaces[s] = draft.surfaces[s] === undefined || draft.surfaces[s] === null ? 'caries' : draft.surfaces[s] === 'caries' ? 'filling' : null;
                if (!draft.surfaces[s]) delete draft.surfaces[s];
                draft.note = editor.querySelector('#ed-note').value;
                drawEditor();
            }));
        }
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
