/* Extend native guest rows; the native form still owns submission and payment selection. */
(() => {
    const mounted = new WeakSet();
    const observers = [];
    window.addEventListener('pagehide', () => observers.forEach(observer => observer.disconnect()), {once:true});
    const boot = () => document.querySelectorAll('input[id^="fcalInputIDfba_extra_"]').forEach(transport => {
        if (mounted.has(transport)) return;
        const id = transport.id.replace('fcalInputIDfba_extra_', '');
        const config = window.fbaGuestForms?.[id];
        const root = transport.closest('.fcal_booking_form_wrap');
        if (!config || !root) return;
        mounted.add(transport);
        transport.closest('.fcal_form_item').hidden = true;
        root.classList.add('fba-custom-guests');
        const summary = document.createElement('p');
        summary.className = 'fba-guest-summary';
        summary.setAttribute('aria-live', 'polite');
        const guestWrap = root.querySelector('.fcal_input_multi_guests_wrap');
        (guestWrap || transport.closest('.fcal_form_item')).after(summary);
        const rows = () => [...root.querySelectorAll('.fcal_multi_guest_input')];
        const read = row => Object.fromEntries([...row.querySelectorAll('[data-fba-answer]')].map(el => [el.dataset.fbaAnswer, el.value]));
        const update = () => {
            const guests = rows();
            guests.forEach((row, index) => {
                if (row.querySelector('.fba-guest-extra')) return;
                const panel = document.createElement('div'); panel.className = 'fba-guest-extra';
                config.fields.forEach(field => {
                    const label = document.createElement('label');
                    label.textContent = field.label + (field.required ? ' *' : '');
                    const input = document.createElement(field.type === 'select' ? 'select' : 'input');
                    input.dataset.fbaAnswer = field.id;
                    input.required = field.required;
                    if (field.type === 'select') {
                        const placeholder = document.createElement('option'); placeholder.value = ''; placeholder.textContent = 'Choisir…'; input.append(placeholder);
                        field.choices.forEach(value => { const option = document.createElement('option'); option.value = value; option.textContent = value; input.append(option); });
                    } else { input.type = field.type === 'number' ? 'number' : 'text'; if (field.type === 'number') input.step = 'any'; else input.maxLength = 1000; }
                    label.append(input);panel.append(label);
                });
                row.append(panel);
            });
            const payload = guests.map(row => ({email: row.querySelector('input[type=email]')?.value || '', fields: read(row)}));
            const serialized = JSON.stringify(payload);
            if (transport.value !== serialized) { transport.value = serialized; transport.dispatchEvent(new Event('input', {bubbles: true})); }
            const people = guests.length + 1;
            const total = new Intl.NumberFormat(document.documentElement.lang || 'fr', {style:'currency',currency:config.currency}).format(config.unit * (config.price ? people : 1));
            const message = people + (people > 1 ? ' personnes' : ' personne') + ' · ' + (config.seats ? people + ' place(s) utilisées après confirmation' : 'comptage des places FluentBooking') + (config.unit > 0 ? ' · Total : ' + total : '');
            if (summary.textContent !== message) summary.textContent = message;
        };
        root.addEventListener('input', event => { if (event.target !== transport) update(); });
        root.addEventListener('change', update);
        root.addEventListener('click', event => {
            const button = event.target.closest('.fcal_multi_guest_input button');
            if (!button) return;
            const list = rows(); const index = list.indexOf(button.closest('.fcal_multi_guest_input'));
            const values = list.map(read); values.splice(index, 1);
            // Svelte reuses unkeyed rows after removal; move answers with the remaining guests.
            setTimeout(() => { update(); rows().forEach((row, i) => row.querySelectorAll('[data-fba-answer]').forEach(input => { input.value = values[i]?.[input.dataset.fbaAnswer] || ''; })); update(); }, 0);
        }, true);
        const observer = new MutationObserver(update);
        observer.observe(guestWrap || root, {childList: true, subtree: true});
        observers.push(observer);
        update();
    });
    const observer = new MutationObserver(boot);
    observer.observe(document.documentElement, {childList:true,subtree:true});
    observers.push(observer);
    boot();
})();
