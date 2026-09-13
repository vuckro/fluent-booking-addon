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
        let guestWrap = root.querySelector('.fcal_input_multi_guests_wrap');
        if (config.attached) {
            guestWrap = document.createElement('div'); guestWrap.className = 'fcal_input_multi_guests_wrap';
            const add = document.createElement('button'); add.type = 'button'; add.textContent = 'Ajouter un invité'; add.className = 'fba-add-guest';
            guestWrap.append(add); transport.closest('.fcal_form_item').before(guestWrap);
            add.addEventListener('click', () => {
                if (rows().length + 1 >= config.limit) return;
                const row = document.createElement('div'); row.className = 'fcal_multi_guest_input fba-attached-guest';
                const heading = document.createElement('strong'); heading.className = 'fba-guest-label'; row.append(heading);
                ['name','email'].forEach(key => {
                    const label = document.createElement('label'); label.textContent = key === 'name' ? 'Nom de l’invité' : 'Courriel de l’invité';
                    const input = document.createElement('input'); input.type = key === 'email' ? 'email' : 'text'; input.maxLength = 200;
                    input.required = config[key+'Mode'] === 'required'; label.hidden = config[key+'Mode'] === 'hidden'; label.append(input); row.append(label);
                });
                const remove = document.createElement('button'); remove.type = 'button'; remove.textContent = 'Supprimer cet invité';
                remove.addEventListener('click', () => { row.remove(); update(); }); row.append(remove);
                guestWrap.insertBefore(row, add); update(); row.querySelector('input:not([type=hidden])')?.focus();
            });
        }
        (guestWrap || transport.closest('.fcal_form_item')).after(summary);
        const rows = () => [...root.querySelectorAll('.fcal_multi_guest_input')];
        const read = row => Object.fromEntries([...row.querySelectorAll('[data-fba-answer]')].filter(el => el.type !== 'radio' || el.checked).map(el => [el.dataset.fbaAnswer, el.type === 'checkbox' ? (el.checked ? '1' : '') : el.value]));
        const update = () => {
            const guests = rows();
            guests.forEach((row, index) => {
                const heading = row.querySelector('.fba-guest-label');
                if (heading && heading.textContent !== 'Invité ' + (index + 1)) heading.textContent = 'Invité ' + (index + 1);
                if (row.querySelector('.fba-guest-extra')) return;
                const panel = document.createElement('div'); panel.className = 'fba-guest-extra';
                config.fields.forEach(field => {
                    const choiceLabel = (value, index) => {
                        if (!field.pricing || field.pricing === 'none') return value;
                        const price = new Intl.NumberFormat(document.documentElement.lang || 'fr', {style:'currency',currency:config.currency}).format((field.prices[index] || 0) / 100);
                        return value + ' (' + (field.pricing === 'add' ? '+' : '') + price + ')';
                    };
                    const label = document.createElement('label');
                    label.textContent = (field.type === 'checkbox' ? choiceLabel(field.label, 0) : field.label) + (field.required ? ' *' : '');
                    if (field.type === 'radio') {
                        const group = document.createElement('fieldset'); const legend = document.createElement('legend'); legend.textContent = label.textContent; group.append(legend);
                        const groupName = 'fba_radio_' + id + '_' + field.id + '_' + crypto.randomUUID();
                        field.choices.forEach((value, index) => { const choice = document.createElement('label'); const radio = document.createElement('input'); radio.type = 'radio'; radio.name = groupName; radio.value = value; radio.dataset.fbaAnswer = field.id; radio.required = field.required; choice.append(radio, document.createTextNode(choiceLabel(value, index))); group.append(choice); });
                        if (!field.required) { const clear = document.createElement('button'); clear.type = 'button'; clear.textContent = 'Effacer ce choix'; clear.addEventListener('click', () => { group.querySelectorAll('input').forEach(input => input.checked = false); update(); }); group.append(clear); }
                        panel.append(group); return;
                    }
                    const input = document.createElement(field.type === 'select' ? 'select' : 'input');
                    input.dataset.fbaAnswer = field.id;
                    input.required = field.required;
                    if (field.type === 'select') {
                        const placeholder = document.createElement('option'); placeholder.value = ''; placeholder.textContent = 'Choisir…'; input.append(placeholder);
                        field.choices.forEach((value, index) => { const option = document.createElement('option'); option.value = value; option.textContent = choiceLabel(value, index); input.append(option); });
                    } else { input.type = ['number','checkbox'].includes(field.type) ? field.type : 'text'; if (field.type === 'number') input.step = 'any'; else input.maxLength = 1000; }
                    label.append(input);panel.append(label);
                });
                row.append(panel);
            });
            const payload = guests.map(row => ({name: row.querySelector('input[type=text]')?.value || '', email: row.querySelector('input[type=email]')?.value || '', fields: read(row)}));
            const serialized = JSON.stringify(payload);
            if (transport.value !== serialized) { transport.value = serialized; transport.dispatchEvent(new Event('input', {bubbles: true})); }
            const people = guests.length + 1;
            if (config.attached) guestWrap.querySelector('.fba-add-guest').disabled = people >= config.limit;
            const base = Math.round(config.unit * 100);
            let cents = base;
            payload.forEach(guest => {
                let amount = config.price ? base : 0, extra = 0;
                config.fields.forEach(field => {
                    const answer = guest.fields[field.id] || '';
                    if (!answer || !field.pricing || field.pricing === 'none') return;
                    const index = field.type === 'checkbox' ? 0 : field.choices.indexOf(answer);
                    if (index < 0) return;
                    const price = field.prices[index] || 0;
                    if (field.pricing === 'replace') amount = price; else extra += price;
                });
                cents += amount + extra;
            });
            const total = new Intl.NumberFormat(document.documentElement.lang || 'fr', {style:'currency',currency:config.currency}).format(cents / 100);
            const message = people + (people > 1 ? ' personnes' : ' personne') + ' · ' + (config.seats ? people + ' place(s) utilisées après confirmation' : 'comptage des places FluentBooking') + (cents > 0 ? ' · Total : ' + total : '');
            if (summary.textContent !== message) summary.textContent = message;
        };
        root.addEventListener('input', event => { if (event.target !== transport) update(); });
        root.addEventListener('change', update);
        root.addEventListener('click', event => {
            if (config.attached) return;
            const button = event.target.closest('.fcal_multi_guest_input button');
            if (!button || button.closest('.fba-guest-extra')) return;
            const list = rows(); const index = list.indexOf(button.closest('.fcal_multi_guest_input'));
            const values = list.map(read); values.splice(index, 1);
            // Svelte reuses unkeyed rows after removal; move answers with the remaining guests.
            setTimeout(() => { update(); rows().forEach((row, i) => row.querySelectorAll('[data-fba-answer]').forEach(input => { const value = values[i]?.[input.dataset.fbaAnswer] || ''; if (input.type === 'checkbox') input.checked = value === '1'; else if (input.type === 'radio') input.checked = value === input.value; else input.value = value; })); update(); }, 0);
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
