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

// ── Odontogram: click cycles healthy → to treat → treated → missing ──
const CYCLE = ['', 'to_treat', 'treated', 'missing'];
document.querySelectorAll('[data-odontogram]').forEach((odo) => {
    odo.addEventListener('click', async (e) => {
        const tooth = e.target.closest('.tooth');
        if (!tooth || tooth.disabled) return;
        const before = tooth.dataset.state || '';
        const next = CYCLE[(CYCLE.indexOf(before) + 1) % CYCLE.length];
        tooth.dataset.state = next; // optimistic
        try {
            await send(odo.dataset.odontogram, 'POST', { tooth: Number(tooth.dataset.tooth), state: next || null });
        } catch {
            tooth.dataset.state = before;
        }
    });
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
    const div = Object.assign(document.createElement('div'), { className: 'alert alert-danger py-2 small fw-semibold position-fixed top-0 end-0 m-3 shadow', textContent: message });
    div.style.zIndex = 2000;
    document.body.append(div);
    setTimeout(() => div.remove(), 4000);
}
