import 'bootstrap/js/dist/collapse';
import { gsap } from 'gsap';
import { ScrollTrigger } from 'gsap/ScrollTrigger';

const csrf = document.querySelector('meta[name="csrf-token"]').content;

async function api(path, { method = 'GET', body } = {}) {
    const res = await fetch(`/api/${path}`, {
        method,
        headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf },
        body: body ? JSON.stringify(body) : undefined,
    });
    const data = await res.json().catch(() => ({}));
    if (!res.ok) throw Object.assign(new Error(data.message || 'Erreur'), { status: res.status, data });
    return data;
}

const el = (tag, attrs = {}, text = '') => {
    const node = Object.assign(document.createElement(tag), attrs);
    if (text) node.textContent = text;
    return node;
};

// ── Booking bar ─────────────────────────────────────────────────────
function booking(root) {
    const $ = (s) => root.querySelector(s);
    const state = { service: $('[data-service]').value, date: null, time: null };
    const daysBox = $('[data-days]');
    const slotsBox = $('[data-slots]');
    const go = $('[data-continue]');
    const form = $('[data-form]');

    const select = (box, btn) => {
        box.querySelectorAll('.chip').forEach((c) => c.classList.remove('on'));
        btn.classList.add('on');
    };

    async function loadDays() {
        state.date = state.time = null;
        go.disabled = true;
        form.hidden = true;
        daysBox.innerHTML = '<span class="skeleton"></span>'.repeat(4);
        slotsBox.innerHTML = '<span class="text-mute small">Choisissez un jour</span>';
        const days = await api(`days?service=${state.service}`);
        daysBox.replaceChildren(...days.map((d, i) => {
            const b = el('button', { type: 'button', className: 'chip' }, d.label);
            b.append(el('small', {}, `${d.free} libre${d.free > 1 ? 's' : ''}`));
            b.onclick = () => { select(daysBox, b); loadSlots(d.date); };
            if (i === 0) queueMicrotask(() => b.click());
            return b;
        }));
        if (!days.length) daysBox.textContent = 'Aucun jour libre en ligne, appelez le cabinet.';
    }

    async function loadSlots(date) {
        state.date = date;
        state.time = null;
        go.disabled = true;
        slotsBox.innerHTML = '<span class="skeleton"></span>'.repeat(5);
        const { slots } = await api(`slots?service=${state.service}&date=${date}`);
        $('[data-free]').textContent = `· ${slots.length} libres`;
        slotsBox.replaceChildren(...slots.slice(0, 12).map((t) => {
            const b = el('button', { type: 'button', className: 'chip free' }, t);
            b.onclick = () => {
                select(slotsBox, b);
                state.time = t;
                go.disabled = false;
            };
            return b;
        }));
        gsap.from(slotsBox.children, { y: 6, opacity: 0, stagger: 0.025, duration: 0.25 });
    }

    go.onclick = () => {
        form.hidden = false;
        const label = root.querySelector('[data-days] .on')?.firstChild.textContent;
        $('[data-summary]').textContent = `· ${label} ${state.time}`;
        form.querySelector('input').focus();
    };

    form.onsubmit = async (e) => {
        e.preventDefault();
        const fd = new FormData(form);
        const phone = form.querySelector('[name=phone]');
        phone.classList.remove('is-invalid');
        $('[data-error]').textContent = '';
        const button = form.querySelector('[type=submit]');
        button.disabled = true;
        try {
            const r = await api('appointments', {
                method: 'POST',
                body: { service: state.service, starts_at: `${state.date} ${state.time}`, ...Object.fromEntries(fd) },
            });
            form.hidden = true;
            root.querySelector('.row').hidden = true;
            $('[data-done]').hidden = false;
            $('[data-done-title]').textContent = `C’est réservé : ${r.service}, ${r.label}.`;
            $('[data-whatsapp]').href = r.whatsapp;
            gsap.from($('[data-done]'), { y: 10, opacity: 0, duration: 0.4 });
        } catch (err) {
            if (err.status === 409) {
                $('[data-error]').textContent = err.message;
                loadSlots(state.date);
                form.hidden = true;
            } else if (err.data?.errors?.phone) {
                phone.classList.add('is-invalid');
                $('[data-phone-error]').textContent = err.data.errors.phone[0];
            } else {
                $('[data-error]').textContent = err.message;
            }
        } finally {
            button.disabled = false;
        }
    };

    $('[data-service]').onchange = (e) => { state.service = e.target.value; loadDays(); };
    document.querySelectorAll('[data-book-service]').forEach((b) => b.addEventListener('click', () => {
        $('[data-service]').value = b.dataset.bookService;
        state.service = b.dataset.bookService;
        loadDays();
        root.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }));

    loadDays();
}

// ── Assistant ───────────────────────────────────────────────────────
function assistant(panel) {
    const log = panel.querySelector('[data-log]');
    const form = panel.querySelector('[data-chat-form]');
    const input = form.querySelector('input');
    const fab = document.querySelector('.assistant-fab');
    let token = null;
    try { token = localStorage.getItem('snan.token'); } catch { /* private mode */ }
    let busy = false;

    const scroll = () => log.scrollTo({ top: log.scrollHeight, behavior: 'smooth' });

    function bubble(text, who) {
        const node = el('div', { className: `msg ${who}` });
        // Links in answers (maps) become clickable; everything else stays text.
        text.split(/(https?:\/\/\S+)/).forEach((part) => node.append(
            /^https?:\/\//.test(part) ? el('a', { href: part, target: '_blank', rel: 'noopener' }, 'ouvrir la carte') : part,
        ));
        log.append(node);
        gsap.from(node, { y: 8, opacity: 0, duration: 0.25 });
        scroll();
    }

    function chips(list) {
        if (!list?.length) return;
        const box = el('div', { className: 'quick' });
        list.forEach((c) => {
            const b = el('button', { type: 'button', className: 'chip free' }, c.label);
            b.onclick = () => { box.remove(); send(c.label, c.value); };
            box.append(b);
        });
        log.append(box);
        scroll();
    }

    async function send(message, value = null) {
        if (busy || !message.trim()) return;
        busy = true;
        log.querySelectorAll('.quick').forEach((q) => q.remove());
        bubble(message, 'me');
        const typing = el('div', { className: 'typing', innerHTML: '<i></i><i></i><i></i>' });
        log.append(typing);
        scroll();
        try {
            const r = await api('assistant', { method: 'POST', body: { message, value, token } });
            token = r.token;
            try { localStorage.setItem('snan.token', token); } catch { /* ignore */ }
            await new Promise((ok) => setTimeout(ok, 350));
            typing.remove();
            bubble(r.text, 'bot');
            if (r.booking) log.append(el('div', { className: 'booked' }, `✓ Rendez-vous enregistré · ${r.booking.service}`));
            chips(r.chips);
        } catch {
            typing.remove();
            bubble('Connexion perdue. Réessayez, ou appelez le cabinet.', 'bot');
        } finally {
            busy = false;
            input.focus();
        }
    }

    function open() {
        panel.hidden = false;
        fab.hidden = true;
        gsap.from(panel, { scale: 0.92, opacity: 0, duration: 0.25, ease: 'power2.out' });
        if (!log.children.length) {
            bubble('Salam 👋 Je suis l’assistant du cabinet. Écrivez comme vous parlez : darija, français, عربي or English.', 'bot');
            chips([
                { label: 'Prendre rendez-vous', value: 'intent:prendre_rdv' },
                { label: '3andi wjee3', value: 'intent:urgence' },
                { label: 'Déplacer un rendez-vous', value: 'intent:deplacer' },
                { label: 'Horaires', value: 'intent:horaires' },
            ]);
        }
        input.focus();
    }

    document.querySelectorAll('[data-open-assistant]').forEach((b) => b.addEventListener('click', open));
    panel.querySelector('[data-close-assistant]').onclick = () => { panel.hidden = true; fab.hidden = false; };
    if (location.hash === '#assistant') open(); // deep link, handy for sharing and demos

    form.onsubmit = (e) => {
        e.preventDefault();
        const text = input.value;
        input.value = '';
        send(text);
    };
}

// ── Motion: one entrance, then quiet ────────────────────────────────
function motion() {
    if (matchMedia('(prefers-reduced-motion: reduce)').matches) return;
    gsap.registerPlugin(ScrollTrigger);
    gsap.from('.hero h1, .hero .kick, .hero .lead-sm, .hero .btn, .feat', { y: 18, opacity: 0, stagger: 0.06, duration: 0.6, ease: 'power3.out' });
    gsap.from('.hero .photo', { scale: 1.06, duration: 1.4, ease: 'power2.out' });
    gsap.from('.urgent-card', { y: 20, opacity: 0, delay: 0.6, duration: 0.5 });
    gsap.from('.booking', { y: 30, opacity: 0, delay: 0.3, duration: 0.6, ease: 'power3.out' });
    gsap.utils.toArray('[data-reveal]').forEach((node) => gsap.from(node, {
        y: 24, opacity: 0, duration: 0.6, ease: 'power3.out', scrollTrigger: { trigger: node, start: 'top 88%' },
    }));
}

document.querySelectorAll('[data-booking]').forEach(booking);
document.querySelectorAll('[data-assistant]').forEach(assistant);
motion();
