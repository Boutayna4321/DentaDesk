import 'bootstrap/js/dist/dropdown';
import 'bootstrap/js/dist/collapse';
import Modal from 'bootstrap/js/dist/modal';

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
            document.title = document.title.replace(/^\(\d+\) /, '') ;
            if (total) document.title = `(${total}) ${document.title}`;
            last = p.badges;
        } catch { /* offline: try again next tick */ }
    };
    tick();
    setInterval(tick, 15000);
}

// ── Destructive actions ask first ───────────────────────────────────
document.addEventListener('submit', (e) => {
    const msg = e.target.dataset.confirm;
    if (msg && !window.confirm(msg)) e.preventDefault();
});

// ── Odontogram: click a tooth, pick its state, add a note ──────────
const STATES = [['', 'Saine'], ['to_treat', 'À traiter'], ['treated', 'Soignée'], ['missing', 'Absente']];
document.querySelectorAll('[data-odontogram]').forEach((odo) => {
    const pop = Object.assign(document.createElement('div'), { className: 'tooth-pop' });
    pop.hidden = true;
    odo.append(pop);
    let tooth = null;

    odo.addEventListener('click', (e) => {
        const t = e.target.closest('.tooth');
        if (!t || t.disabled) return;
        tooth = t;
        pop.innerHTML = `<b>Dent ${t.dataset.tooth}</b><div class="states"></div><input class="form-control form-control-sm" placeholder="Note (ex : composite 2024)" maxlength="200"><button class="btn btn-primary btn-sm">Enregistrer</button>`;
        const states = pop.querySelector('.states');
        let chosen = t.dataset.state || '';
        STATES.forEach(([value, label]) => {
            const b = Object.assign(document.createElement('button'), { type: 'button', className: `st ${value === chosen ? 'on' : ''}`, textContent: label });
            b.dataset.value = value;
            b.onclick = () => { chosen = value; states.querySelectorAll('.st').forEach((x) => x.classList.toggle('on', x === b)); };
            states.append(b);
        });
        pop.querySelector('input').value = t.dataset.note || '';
        pop.querySelector('.btn').onclick = async () => {
            const note = pop.querySelector('input').value.trim();
            try {
                await send(odo.dataset.odontogram, 'POST', { tooth: Number(tooth.dataset.tooth), state: chosen || null, note: note || null });
                tooth.dataset.state = chosen;
                tooth.dataset.note = note;
                tooth.title = `${tooth.dataset.tooth}${note ? ' — ' + note : ''}`;
                pop.hidden = true;
            } catch (err) { toast(err.message, 'err'); }
        };
        const box = odo.getBoundingClientRect(), r = t.getBoundingClientRect();
        pop.hidden = false;
        pop.style.left = `${Math.min(Math.max(r.left - box.left - 80, 0), box.width - pop.offsetWidth)}px`;
        pop.style.top = `${r.bottom - box.top + 6}px`;
        pop.querySelector('input').focus();
    });
    document.addEventListener('click', (e) => { if (!odo.contains(e.target)) pop.hidden = true; });
});

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
