/* Thin adapter for FluentBooking 2.4's native text-field binding. No network interception. */
(() => {
    const t = text => window.wp.i18n.__(text, 'waaskit-fluent-booking');
    const mounted = new WeakSet();
    const node = (tag, text, props = {}) => Object.assign(document.createElement(tag), {textContent: text, ...props});
    function mount(input) {
        if (mounted.has(input)) return;
        const id = input.id.replace('fcalInputIDfba_party_', '');
        const config = window.fbaProfiles?.[id];
        if (!config) return;
        mounted.add(input);
        const {profile, currency} = config;
        const root = node('div', '', {className: 'fba-party'});
        const list = node('div', '', {className: 'fba-party-list'});
        const total = node('p', '', {className: 'fba-party-total'});
        total.setAttribute('aria-live', 'polite');
        const add = node('button', t('Ajouter un participant'), {type: 'button'});
        root.append(node('p', t('Ajoutez toutes les personnes présentes, y compris vous-même si vous participez.')), list, add, total);
        const wrapper = input.closest('.fcal_input_wrap');
        if (!wrapper) return;
        wrapper.before(root);
        wrapper.hidden = true;
        let serial = 0;
        function field(parent, label, value = '', type = 'text') {
            const control = node('input', '', {type, value});
            const lab = node('label', label);
            lab.append(control); parent.append(lab); return control;
        }
        function sync() {
            const rows = [...list.children].map(row => row.read());
            input.value = JSON.stringify(rows);
            input.dispatchEvent(new Event('input', {bubbles: true}));
            add.disabled = rows.length >= profile.max;
            for (const row of list.children) row.querySelector('.fba-party-remove').disabled = rows.length <= profile.min;
            const counts = new Map();
            for (const row of rows) counts.set(row.type, (counts.get(row.type) || 0) + 1);
            if (profile.pricing) {
                const money = new Intl.NumberFormat(document.documentElement.lang || 'fr', {style: 'currency', currency});
                let subtotal = 0;
                const parts = profile.types.filter(t => counts.has(t.id)).map(t => {
                    const quantity = counts.get(t.id); subtotal += quantity * t.price;
                    return `${quantity} × ${t.label} (${money.format(t.price / 100)})`;
                });
                total.textContent = parts.join(' + ') + ` — Total : ${money.format(subtotal / 100)}. Vérifié à la réservation. Coupons non disponibles.`;
                root.closest('.fcal_booking_form')?.querySelectorAll('.fcal_payment_items').forEach(el => {el.hidden = true;});
            } else total.textContent = `${rows.length} participant(s).`;
        }
        function append(saved = {}) {
            const row = node('fieldset', '', {className: 'fba-party-person'});
            row.append(node('legend', `Participant ${++serial}`));
            const typeLabel = node('label', t('Type de participant'));
            const select = node('select', '');
            for (const type of profile.types) select.append(node('option', type.label, {value: type.id}));
            if (profile.types.some(t => t.id === saved.type)) select.value = saved.type;
            typeLabel.append(select); row.append(typeLabel);
            const name = profile.names ? field(row, t('Nom et prénom'), saved.name || '') : null;
            if (name) { name.required = true; name.maxLength = 160; name.autocomplete = 'off'; }
            const email = profile.email !== 'hidden' ? field(row, 'E-mail' + (profile.email === 'optional' ? ' (facultatif)' : ''), saved.email || '', 'email') : null;
            if (email) { email.required = profile.email === 'required'; email.maxLength = 254; }
            const extra = node('div', '', {className: 'fba-party-extra'}); row.append(extra);
            let birth = null; let fields = {};
            function rebuild() {
                const type = profile.types.find(t => t.id === select.value);
                extra.replaceChildren(); fields = {}; birth = null;
                if (type.min_age > 0 || type.max_age < 120) {
                    birth = field(extra, `Date de naissance (${type.min_age}–${type.max_age} ans à la séance)`, saved.birth_date || '', 'date'); birth.required = true;
                }
                for (const f of type.fields) { fields[f.id] = field(extra, f.label, saved.fields?.[f.id] || ''); fields[f.id].required = f.required; fields[f.id].maxLength = 500; }
            }
            rebuild();
            const remove = node('button', t('Retirer ce participant'), {type: 'button', className: 'fba-party-remove'});
            row.append(remove);
            row.read = () => ({type: select.value, name: name?.value || '', email: email?.value || '', birth_date: birth?.value || '', fields: Object.fromEntries(Object.entries(fields).map(([key,el])=>[key,el.value]))});
            select.addEventListener('change', () => { saved = {}; rebuild(); sync(); });
            row.addEventListener('input', sync);
            remove.addEventListener('click', () => { if (list.children.length > profile.min) { row.remove(); sync(); add.focus(); } });
            list.append(row); sync();
        }
        let saved;
        try { saved = JSON.parse(input.value); } catch (_) { saved = []; }
        if (Array.isArray(saved) && saved.length >= profile.min && saved.length <= profile.max) saved.forEach(append);
        else for (let i = 0; i < profile.min; i++) append();
        add.addEventListener('click', () => { if (list.children.length < profile.max) { append(); list.lastElementChild.querySelector('select').focus(); } });
    }
    let queued = false;
    function scan() { queued = false; document.querySelectorAll('input[id^="fcalInputIDfba_party_"]').forEach(mount); }
    const observer = new MutationObserver(() => { if (!queued) { queued = true; queueMicrotask(scan); } });
    observer.observe(document.body, {childList: true, subtree: true}); scan();
})();
