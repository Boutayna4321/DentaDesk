import 'bootstrap/js/dist/dropdown';
import 'bootstrap/js/dist/collapse';
import Modal from 'bootstrap/js/dist/modal';
import Offcanvas from 'bootstrap/js/dist/offcanvas';
import { mountOdontogram } from './odontogram';

const csrf = document.querySelector('meta[name="csrf-token"]')?.content;

async function send(url, method, body) {
    const res = await fetch(url, {
        method,
        headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf },
        body: body ? JSON.stringify(body) : undefined,
    });
    const data = await res.json().catch(() => ({}));
    if (!res.ok) throw Object.assign(new Error(data.message || 'Erreur'), { status: res.status });
    return data;
}

// ── Phone menu: a tap on a link (or on the messages entry) closes the drawer ──
const sidenav = document.getElementById('sidenav');
if (sidenav) {
    sidenav.addEventListener('click', (e) => {
        if (e.target.closest('a.item') && window.matchMedia('(max-width: 991.98px)').matches) Offcanvas.getInstance(sidenav)?.hide();
    });
}

// ── Toasts: flash messages fade out on their own ──────────────────
const toasts = document.querySelector('[data-toasts]');
export function toast(message, kind = 'ok', href = null) {
    const node = Object.assign(document.createElement('div'), { className: `toast-msg ${kind}`, textContent: message });
    if (href) node.onclick = () => { location.href = href; };
    toasts.append(node);
    fade(node);
}
function fade(node) {
    setTimeout(() => { node.classList.add('out'); setTimeout(() => node.remove(), 300); }, node.classList.contains('err') ? 7000 : 4000);
}
toasts?.querySelectorAll('.toast-msg').forEach(fade);

// ── Pulse: new requests (and messages) show up without a reload ─────
if (toasts?.dataset.pulse) {
    const badges = () => Object.fromEntries([...document.querySelectorAll('[data-badge]')].map((b) => [b.dataset.badge, b]));
    let last = null;
    const tick = async () => {
        try {
            const p = await send(toasts.dataset.pulse, 'GET');
            for (const [key, node] of Object.entries(badges())) {
                const n = p.badges[key] ?? 0;
                node.textContent = n;
                node.hidden = !n;
            }
            if (last && p.badges['dash.requests'] > last['dash.requests']) toast('Nouvelle demande de l’assistant', 'info', '/dashboard/demandes');
            if (last && (p.badges['dash.messages'] ?? 0) > (last['dash.messages'] ?? 0)) toast('Nouveau message', 'info');
            const total = Object.values(p.badges).reduce((a, b) => a + b, 0);
            const dot = document.querySelector('[data-burger-dot]');
            if (dot) dot.hidden = !(p.badges['dash.requests'] || p.badges['dash.messages']);
            document.title = document.title.replace(/^\(\d+\) /, '') ;
            if (total) document.title = `(${total}) ${document.title}`;
            last = p.badges;
        } catch { /* offline: try again next tick */ }
    };
    tick();
    setInterval(tick, 15000);
}

// ── Chart tooltips (mouse and keyboard) ────────────────────────────
const tip = document.querySelector('[data-chart-tip]');
if (tip) {
    const show = (el, x, y) => { tip.textContent = el.dataset.tip; tip.hidden = false; tip.style.left = `${x + 12}px`; tip.style.top = `${y - 34}px`; };
    document.querySelectorAll('[data-tip]').forEach((el) => {
        el.addEventListener('mousemove', (e) => show(el, e.clientX, e.clientY));
        el.addEventListener('mouseleave', () => { tip.hidden = true; });
        el.addEventListener('focus', () => { const r = el.getBoundingClientRect(); show(el, r.left, r.top); });
        el.addEventListener('blur', () => { tip.hidden = true; });
    });
}

// ── Internal messages ──────────────────────────────────────────────
const panel = document.querySelector('[data-messages]');
if (panel) {
    const log = panel.querySelector('[data-log]');
    const people = panel.querySelector('[data-people]');
    const form = panel.querySelector('[data-msg-form]');
    const about = panel.querySelector('[data-about]');
    let withId = null;
    let lastId = null;
    let timer = null;
    let patientId = null;

    const render = (data) => {
        withId = data.with;
        people.replaceChildren(...data.people.map((p) => {
            const b = document.createElement('button');
            b.type = 'button';
            b.className = p.id === withId ? 'on' : '';
            b.innerHTML = '<span class="avatar"></span><span class="n"></span>';
            b.querySelector('.avatar').textContent = p.initials;
            b.querySelector('.n').textContent = p.name;
            if (p.unread && p.id !== withId) b.append(Object.assign(document.createElement('em'), { textContent: p.unread }));
            b.onclick = () => load(p.id);
            return b;
        }));
        const newest = data.messages.at(-1)?.id ?? null;
        if (newest === lastId && log.children.length) return;
        lastId = newest;
        if (!data.people.length) { log.innerHTML = '<p class="mp-empty">Personne d’autre dans l’équipe pour l’instant.</p>'; return; }
        if (!data.messages.length) { log.innerHTML = '<p class="mp-empty">Pas encore de message.<br>Un « patient arrivé » vaut mieux qu’un cri dans le couloir.</p>'; return; }
        log.replaceChildren(...data.messages.map((m) => {
            const d = document.createElement('div');
            d.className = `bubble ${m.mine ? 'mine' : ''}`;
            if (m.patient) d.append(Object.assign(document.createElement('a'), { className: 'pt', href: m.patient.url, textContent: `Patient · ${m.patient.name}` }), document.createElement('br'));
            d.append(document.createTextNode(m.body));
            d.append(Object.assign(document.createElement('span'), { className: 'meta', textContent: `${m.at}${m.mine ? (m.read ? ' · lu' : ' · envoyé') : ''}` }));
            return d;
        }));
        log.scrollTop = log.scrollHeight;
    };
    const load = async (id = withId) => render(await send(`${panel.dataset.messages}${id ? `?with=${id}` : ''}`, 'GET'));

    const open = (e) => {
        e?.preventDefault();
        const trigger = e?.currentTarget;
        patientId = trigger?.dataset.patientId ?? null;
        about.hidden = !patientId;
        if (patientId) about.querySelector('[data-about-name]').textContent = trigger.dataset.patientName;
        panel.hidden = false;
        load();
        clearInterval(timer);
        timer = setInterval(() => load(), 5000);
        form.body.focus();
    };
    document.querySelectorAll('[data-open-messages]').forEach((b) => b.addEventListener('click', open));
    panel.querySelector('[data-close-messages]').onclick = () => { panel.hidden = true; clearInterval(timer); };
    panel.querySelectorAll('[data-quick] button').forEach((b) => b.addEventListener('click', () => { form.body.value = b.textContent; form.requestSubmit(); }));
    form.body.addEventListener('keydown', (e) => { if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); form.requestSubmit(); } });
    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        const body = form.body.value.trim();
        if (!body || !withId) return;
        form.body.value = '';
        await send(panel.dataset.send, 'POST', { to: withId, body, patient_id: patientId && form.about.checked ? patientId : null });
        load();
    });
}

// ── Global search: the magnifier (or Ctrl K), a dimmed page, live results ──
const layer = document.querySelector('[data-search-layer]');
if (layer) {
    const input = layer.querySelector('[data-search-input]');
    const results = layer.querySelector('[data-search-results]');
    let timer = null;
    let token = 0;
    const open = () => { layer.hidden = false; input.value = ''; results.innerHTML = '<p class="hint">Tapez au moins 2 lettres ou chiffres.</p>'; setTimeout(() => input.focus(), 30); };
    const close = () => { layer.hidden = true; };
    const initials = (name) => name.split(' ').slice(0, 2).map((w) => w[0]).join('').toUpperCase();
    const run = async () => {
        const q = input.value.trim();
        if (q.length < 2) { results.innerHTML = '<p class="hint">Tapez au moins 2 lettres ou chiffres.</p>'; return; }
        const mine = ++token;
        const r = await send(`${layer.dataset.url}?q=${encodeURIComponent(q)}`, 'GET');
        if (mine !== token) return; // an older request answering late
        results.replaceChildren();
        const section = (title) => results.append(Object.assign(document.createElement('h6'), { textContent: title }));
        const link = (href, left, sub, end) => {
            const a = Object.assign(document.createElement('a'), { href });
            a.innerHTML = '<span class="avatar"></span><span><span class="t"></span><small></small></span><span class="end"></span>';
            a.querySelector('.avatar').textContent = left;
            a.querySelector('.t').textContent = sub[0];
            a.querySelector('small').textContent = sub[1];
            a.querySelector('.end').textContent = end || '';
            results.append(a);
        };
        if (r.patients.length) { section('Patients'); r.patients.forEach((p) => link(p.url, initials(p.name), [p.name, p.phone], p.next ? `Prochain RDV\n${p.next}` : '')); }
        if (r.documents.length) { section('Factures & devis'); r.documents.forEach((d) => link(d.url, d.number.slice(0, 1), [d.number, d.patient], d.total)); }
        if (!r.patients.length && !r.documents.length) results.innerHTML = '<p class="hint">Aucun résultat.</p>';
        results.querySelector('a')?.classList.add('on');
    };
    input.addEventListener('input', () => { clearTimeout(timer); timer = setTimeout(run, 180); });
    input.addEventListener('keydown', (e) => {
        const items = [...results.querySelectorAll('a')];
        const i = items.findIndex((a) => a.classList.contains('on'));
        if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
            e.preventDefault();
            const next = items[(i + (e.key === 'ArrowDown' ? 1 : -1) + items.length) % items.length];
            items.forEach((a) => a.classList.toggle('on', a === next));
            next?.scrollIntoView({ block: 'nearest' });
        }
        if (e.key === 'Enter' && items[i]) location.href = items[i].href;
    });
    document.querySelectorAll('[data-open-search]').forEach((b) => b.addEventListener('click', open));
    layer.querySelector('[data-close-search]').onclick = close;
    layer.addEventListener('click', (e) => { if (e.target === layer) close(); });
    document.addEventListener('keydown', (e) => {
        if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'k') { e.preventDefault(); layer.hidden ? open() : close(); }
        if (e.key === 'Escape' && !layer.hidden) close();
    });
}

// ── Destructive actions ask first ───────────────────────────────────
document.addEventListener('submit', (e) => {
    const msg = e.target.dataset.confirm;
    if (msg && !window.confirm(msg)) e.preventDefault();
});

// ── Odontogram ─────────────────────────────────────────────────────
document.querySelectorAll('[data-odontogram]').forEach(mountOdontogram);

// ── Invoice editor: lines, totals, price list ──────────────────────
const editor = document.querySelector('[data-doc-editor]');
if (editor) {
    const body = editor.querySelector('[data-lines]');
    const tpl = document.querySelector('[data-line-template]');
    const parse = (v) => Number(String(v).replace(/[\s\u202f]/g, '').replace(',', '.')) || 0;
    const fmt = (n) => `${n.toLocaleString('fr-FR', { minimumFractionDigits: 2, maximumFractionDigits: 2 })} DH`;
    let next = body.children.length;
    const recalc = () => {
        let total = 0;
        body.querySelectorAll('[data-line]').forEach((tr) => {
            const amount = parse(tr.querySelector('[data-qty]').value) * parse(tr.querySelector('[data-price]').value);
            tr.querySelector('[data-amount]').textContent = fmt(amount);
            total += amount;
        });
        editor.querySelector('[data-total]').textContent = fmt(total);
    };
    const add = (values = {}) => {
        body.insertAdjacentHTML('beforeend', tpl.innerHTML.replaceAll('__i__', next++));
        const tr = body.lastElementChild;
        for (const [k, v] of Object.entries(values)) tr.querySelector(`[name$="[${k}]"]`).value = v;
        recalc();
        tr.querySelector(values.label ? '[name$="[tooth]"]' : 'input').focus();
    };
    editor.addEventListener('input', recalc);
    editor.addEventListener('click', (e) => {
        if (e.target.closest('[data-remove-line]')) { e.target.closest('tr').remove(); recalc(); }
    });
    document.querySelector('[data-add-line]').onclick = () => add();
    document.querySelector('[data-act-picker]').onchange = (e) => {
        const o = e.target.selectedOptions[0];
        if (o.value) add({ label: o.dataset.label, code: o.dataset.code, unit_price: o.dataset.price, quantity: 1 });
        e.target.value = '';
    };
    editor.querySelector('[data-validate]').addEventListener('click', (e) => {
        if (!window.confirm('Valider ? Le document sera numéroté et ne pourra plus être modifié (seulement annulé).')) e.preventDefault();
    });
    if (!body.children.length) add();
    recalc();
}

// ── Agenda ──────────────────────────────────────────────────────────
const calendarEl = document.querySelector('[data-calendar]');
if (calendarEl) (async () => {
    const [{ Calendar }, { default: timeGrid }, { default: interaction }, { default: fr }] = await Promise.all([
        import('@fullcalendar/core'),
        import('@fullcalendar/timegrid'),
        import('@fullcalendar/interaction'),
        import('@fullcalendar/core/locales/fr'),
    ]);

    const detail = new Modal('#rdv');
    const create = new Modal('#new-rdv');
    let current = null;

    const calendar = new Calendar(calendarEl, {
        plugins: [timeGrid, interaction],
        locale: fr,
        initialView: window.innerWidth < 768 ? 'timeGridDay' : 'timeGridWeek',
        headerToolbar: { left: 'prev,next today', center: 'title', right: 'timeGridDay,timeGridWeek' },
        firstDay: 1,
        hiddenDays: [0],
        slotMinTime: '08:30',
        slotMaxTime: '19:30',
        slotDuration: '00:15:00',
        slotLabelInterval: '01:00',
        allDaySlot: false,
        nowIndicator: true,
        height: 'auto',
        expandRows: true,
        businessHours: JSON.parse(calendarEl.dataset.business),
        events: calendarEl.dataset.events,
        editable: true,
        eventDurationEditable: false,
        selectable: true,
        selectMirror: true,
        eventContent: (arg) => ({
            html: `<div class="fw-bold text-truncate">${arg.event.title}</div><div class="small text-truncate">${arg.timeText} · ${arg.event.extendedProps.service ?? ''}</div>`,
        }),
        eventDrop: async (info) => {
            const day = info.event.start.getDay();
            const hm = (d) => d.toTimeString().slice(0, 5);
            const inside = JSON.parse(calendarEl.dataset.business).some((b) => b.daysOfWeek.includes(day)
                && hm(info.event.start) >= b.startTime && hm(info.event.end) <= b.endTime);
            if (!inside && !window.confirm('Ce créneau est en dehors des horaires d’ouverture. Déplacer quand même ?')) {
                info.revert();
                return;
            }
            try {
                await send(calendarEl.dataset.move.replace(':id', info.event.id), 'PATCH', { starts_at: info.event.startStr });
            } catch (err) {
                info.revert();
                alertBox(err.message);
            }
        },
        eventClick: (info) => {
            if (info.event.display === 'background') return;
            current = info.event;
            const p = info.event.extendedProps;
            const root = document.querySelector('#rdv');
            root.querySelector('[data-title]').textContent = info.event.title;
            root.querySelector('[data-service]').textContent = p.service;
            root.querySelector('[data-time]').textContent = info.event.start.toLocaleString('fr-FR', { weekday: 'long', day: 'numeric', month: 'long', hour: '2-digit', minute: '2-digit' });
            root.querySelector('[data-phone]').textContent = p.phone;
            root.querySelector('[data-source]').textContent = p.source;
            root.querySelector('[data-reason]').textContent = p.reason || '—';
            root.querySelector('[data-patient]').href = p.patientUrl;
            root.querySelectorAll('[data-status]').forEach((b) => { b.style.opacity = b.dataset.status === p.status ? 1 : 0.45; });
            detail.show();
        },
        select: (info) => {
            const form = document.querySelector('[data-new-rdv]');
            form.reset();
            form.starts_at.value = info.startStr;
            form.patient_id.value = '';
            form.querySelector('[data-patient-results]').replaceChildren();
            form.querySelector('[data-new-patient]').hidden = false;
            form.querySelector('[data-when]').textContent = `· ${info.start.toLocaleString('fr-FR', { weekday: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' })}`;
            create.show();
            calendar.unselect();
        },
    });
    calendar.render();

    document.querySelectorAll('#rdv [data-status]').forEach((b) => b.addEventListener('click', async () => {
        await send(`/dashboard/appointments/${current.id}/status`, 'PATCH', { status: b.dataset.status });
        detail.hide();
        calendar.refetchEvents();
    }));

    // New appointment: search an existing patient, or type a new one.
    const form = document.querySelector('[data-new-rdv]');
    const results = form.querySelector('[data-patient-results]');
    let timer;
    form.querySelector('[data-patient-search]').addEventListener('input', (e) => {
        clearTimeout(timer);
        timer = setTimeout(async () => {
            const q = e.target.value.trim();
            if (q.length < 2) return results.replaceChildren();
            const list = await send(`/dashboard/patients/search?q=${encodeURIComponent(q)}`, 'GET');
            results.replaceChildren(...list.map((p) => {
                const a = Object.assign(document.createElement('button'), { type: 'button', className: 'list-group-item list-group-item-action small' });
                a.innerHTML = `<b></b> <span class="mono text-mute"></span>`;
                a.querySelector('b').textContent = p.name;
                a.querySelector('span').textContent = p.phone;
                a.onclick = () => {
                    form.patient_id.value = p.id;
                    e.target.value = p.name;
                    results.replaceChildren();
                    form.querySelector('[data-new-patient]').hidden = true;
                };
                return a;
            }));
        }, 200);
    });
    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        try {
            await send(form.action, 'POST', Object.fromEntries(new FormData(form)));
            create.hide();
            calendar.refetchEvents();
        } catch (err) {
            alertBox(err.message);
        }
    });
})();

function alertBox(message) {
    toast(message, 'err');
}
