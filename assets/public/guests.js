/* One guest form; FluentBooking still owns holder details, submission and payment. */
(() => {
    const mounted = new WeakSet();
    const checkoutObservers = new Map();
    const checkoutHandlers = new WeakMap();
    // Capture runs before FluentBooking mounts Stripe Elements.
    window.addEventListener('fluent_booking_payment_next_action_stripe', event => {
        const root = event.detail?.form?.closest('.fcal_booking_form_wrap');
        checkoutHandlers.get(root)?.(event);
    }, true);
    const boot = () => {
        checkoutObservers.forEach((root, item) => {
            if (!root.isConnected) { item.disconnect(); checkoutObservers.delete(item); }
        });
        document.querySelectorAll('input[id^="fcalInputIDfba_extra_"]').forEach(transport => {
        if (mounted.has(transport)) return;
        const id = transport.id.replace('fcalInputIDfba_extra_', '');
        const config = window.fbaGuestForms?.[id];
        const root = transport.closest('.fcal_booking_form_wrap');
        if (!config || !root) return;
        const transportItem = transport.closest('.fcal_form_item');
        if (!transportItem) return;
        mounted.add(transport);
        transportItem.hidden = true;
        root.classList.add('fba-custom-guests');
        root.classList.toggle('fba-custom-pricing', !config.preservePayments);
        if (config.error) {
            const message = document.createElement('p'); message.setAttribute('role','alert'); message.textContent = config.error;
            transportItem.before(message); root.querySelectorAll('[type=submit]').forEach(button => button.disabled = true); return;
        }
        const format = cents => new Intl.NumberFormat(document.documentElement.lang || 'fr', {style:'currency',currency:config.currency}).format(cents / 100);
        const nativeTariffs = Array.isArray(config.tariffs);
        const structuredPayload = nativeTariffs || !!config.structuredPayload || !!config.allowNonparticipating;
        let saved = null;
        try { saved = transport.value ? JSON.parse(transport.value) : null; } catch (_) { /* A fresh form has no payload. */ }
        let restoring = !!saved;
        const tariffControl = (title) => {
            const label = document.createElement('label'); label.className = 'fba-tariff-choice'; label.append(document.createTextNode(title));
            const select = document.createElement('select'); select.dataset.fbaTariff = ''; select.required = true;
            config.tariffs.forEach(tariff => { const option = document.createElement('option'); option.value = tariff.id; option.textContent = tariff.title + ' — ' + format(tariff.cents); select.append(option); });
            label.append(select); return label;
        };
        const holder = nativeTariffs && config.tariffs.length ? tariffControl('Votre tarif') : null;
        const participation = config.allowNonparticipating ? document.createElement('input') : null;
        const participationLabel = document.createElement('label');
        const participationHelp = document.createElement('p'); participationHelp.className = 'fba-participation-help';
        participationHelp.setAttribute('role', 'status');
        if (participation) {
            participation.type = 'checkbox'; participation.checked = saved?.holder_participates !== false;
            participation.className = 'fba-holder-participates';
            participationLabel.className = 'fba-participation-choice';
            participationLabel.append(participation, document.createTextNode('Je participe également'));
        }
        const attends = () => !participation || participation.checked;
        const summary = document.createElement('p');
        summary.className = 'fba-guest-summary';
        summary.setAttribute('aria-live', 'polite');
        const guestWrap = document.createElement('div'); guestWrap.className = 'fcal_input_multi_guests_wrap';
            const add = document.createElement('button'); add.type = 'button'; add.textContent = '+ Ajouter une personne'; add.className = 'fba-add-guest';
            guestWrap.append(add);
            const payment = root.querySelector('.fcal_payment_items');
            const paymentItem = payment?.closest('.fcal_form_item');
            if (paymentItem && !config.preservePayments) {
                paymentItem.classList.add('fba-payment-methods');
                paymentItem.querySelector('.fcal_input_content')?.setAttribute('aria-label','Moyen de paiement');
            }
            (paymentItem || transportItem).before(guestWrap);
            if (holder) guestWrap.before(holder);
            if (participation) { (holder || guestWrap).before(participationLabel, participationHelp); }
            add.addEventListener('click', () => {
                if (checkoutLocked || rows().length + (attends() ? 1 : 0) >= config.limit) return;
                const row = document.createElement('div'); row.className = 'fcal_multi_guest_input fba-attached-guest';
                const heading = document.createElement('strong'); heading.className = 'fba-guest-label'; row.append(heading);
                ['name','email'].forEach(key => {
                    const label = document.createElement('label'); label.textContent = (key === 'name' ? 'Nom du participant' : 'Email du participant') + (config[key+'Mode'] === 'required' ? ' *' : '');
                    const input = document.createElement('input'); input.type = key === 'email' ? 'email' : 'text'; input.maxLength = 200; input.dataset.fbaIdentity = key;
                    input.required = config[key+'Mode'] === 'required'; input.disabled = config[key+'Mode'] === 'hidden'; label.hidden = config[key+'Mode'] === 'hidden'; label.append(input); row.append(label);
                });
                if (nativeTariffs && config.tariffs.length) row.append(tariffControl('Tarif de ce participant'));
                const remove = document.createElement('button'); remove.type = 'button'; remove.textContent = '×'; remove.className = 'fba-remove-guest'; remove.setAttribute('aria-label', 'Supprimer ce participant'); remove.title = 'Supprimer ce participant';
                remove.addEventListener('click', () => { if (!checkoutLocked) { row.remove(); update(); } }); row.append(remove);
                guestWrap.insertBefore(row, add); update(); row.querySelector('label:not([hidden]) input, .fba-guest-extra input, .fba-guest-extra select, button')?.focus();
            });
        const recap = document.createElement('div'); recap.className = 'fba-payment-recap';
        const title = document.createElement('h3'); title.textContent = 'Récapitulatif des paiements';
        const lines = document.createElement('dl'); recap.append(title, lines, summary); guestWrap.after(recap);
        if (!nativeTariffs) title.hidden = true;
        if (config.preservePayments) recap.hidden = true;
        const rows = () => [...guestWrap.querySelectorAll('.fba-attached-guest')];
        let checkoutLocked = false;
        let checkoutReady = false;
        let quotedCents = 0;
        let submittedCents = null;
        const previousDisabled = new Map();
        const form = transport.closest('form');
        const controls = () => [holder, participationLabel, guestWrap].filter(Boolean)
            .flatMap(element => [...element.querySelectorAll('input, select, button')]);
        const freeze = () => {
            if (checkoutLocked) return;
            checkoutLocked = true;
            submittedCents = quotedCents;
            controls().forEach(control => { previousDisabled.set(control, control.disabled); control.disabled = true; });
        };
        const unfreeze = () => {
            if (checkoutReady || !checkoutLocked) return;
            checkoutLocked = false;
            previousDisabled.forEach((disabled, control) => { control.disabled = disabled; });
            previousDisabled.clear();
        };
        form?.addEventListener('submit', event => {
            if (checkoutLocked) { event.preventDefault(); event.stopImmediatePropagation(); return; }
            update();
            if (!form.checkValidity()) { event.preventDefault(); event.stopImmediatePropagation(); form.reportValidity(); return; }
            freeze();
            // Native validation can reject before starting an HTTP request.
            setTimeout(() => { if (!root.querySelector('.fcal_btn_submitting')) unfreeze(); }, 0);
        }, true);
        const settleSubmission = () => {
            if (!root.querySelector('.fcal_btn_submitting')) unfreeze();
        };
        const checkoutObserver = new MutationObserver(settleSubmission);
        checkoutObserver.observe(root, {childList:true, subtree:true, attributes:true, attributeFilter:['class']});
        checkoutObservers.set(checkoutObserver, root);
        checkoutHandlers.set(root, event => {
            if (checkoutReady) { event.stopImmediatePropagation(); return; }
            freeze();
            checkoutReady = true;
            const response = event.detail.response?.data;
            const args = response?.data?.payment_args;
            const intent = response?.intent;
            const currency = String(args?.currency || '').toUpperCase();
            const zeroDecimal = new Intl.NumberFormat('en', {style:'currency', currency:config.currency}).resolvedOptions().maximumFractionDigits === 0;
            const cents = Number(args?.amount) * (zeroDecimal ? 100 : 1);
            const consistent = Number.isSafeInteger(args?.amount) && args.amount > 0
                && intent?.amount === args.amount && String(intent?.currency || '').toUpperCase() === currency
                && currency === config.currency.toUpperCase()
                && (config.preservePayments || submittedCents === cents);
            const processor = root.querySelector('.fluent_booking_payment_processor');
            const notice = document.createElement('p'); notice.className = 'fba-payment-locked-notice';
            notice.setAttribute('role', consistent ? 'status' : 'alert');
            if (!consistent || !processor) {
                event.stopImmediatePropagation();
                notice.textContent = 'Le montant du paiement ne correspond pas à la réservation. Aucun paiement ne peut être effectué sur cet écran. Contactez l’organisateur avant de recommencer.';
                if (processor) processor.style.display = 'none';
            } else {
                root.classList.add('fba-checkout-locked');
                [holder, participationLabel, guestWrap, participationHelp].filter(Boolean).forEach(element => { element.hidden = true; });
                // Keep the frozen recap visible. The native label is calculated
                // from the catalogue, so replace its presentation with the
                // verified server amount without modifying Svelte-owned nodes.
                processor.classList.add('fba-verified-checkout');
                const total = document.createElement('h3'); total.className = 'fba-stripe-total';
                total.textContent = 'Montant à payer : ' + format(cents); processor.prepend(total);
                notice.textContent = 'Les participants et le montant sont confirmés pour ce paiement.';
                const restart = document.createElement('button');
                restart.type = 'button'; restart.className = 'fba-restart-checkout';
                restart.textContent = 'Recommencer';
                restart.title = 'Recharger la page pour une nouvelle réservation';
                const reload = () => window.location.reload();
                restart.addEventListener('click', reload);
                notice.append(document.createTextNode(' '), restart);
                // Leave checkout through a fresh page, never through stale native
                // form state. This does not cancel or alter any Stripe payment.
                const back = root.closest('.fluent_booking_app')?.querySelector('.fcal_back button');
                back?.addEventListener('click', event => {
                    event.preventDefault(); event.stopImmediatePropagation(); reload();
                }, true);

            }
            recap.after(notice);
        });
        const read = row => Object.fromEntries([...row.querySelectorAll('[data-fba-answer]')].filter(el => el.type !== 'radio' || el.checked).map(el => [el.dataset.fbaAnswer, el.type === 'checkbox' ? (el.checked ? '1' : '') : el.value]));
        const update = () => {
            if (checkoutLocked) return;
            const guests = rows();
            if (holder) { holder.hidden = !attends(); holder.querySelector('select').disabled = !attends(); }
            if (participation) {
                const count = guests.length + (attends() ? 1 : 0);
                const error = count < 1 ? 'Ajoutez au moins un participant.' : count > config.limit ? 'Retirez un participant pour participer également : le maximum serait dépassé.' : '';
                participation.setCustomValidity(error);
                participationHelp.textContent = error || (attends() ? 'Vous comptez parmi les participants.' : 'Vous réservez pour les personnes ci-dessous. Vous restez le contact pour le paiement et les messages.');
            }
            guests.forEach((row, index) => {
                const heading = row.querySelector('.fba-guest-label');
                row.querySelector('.fba-remove-guest').disabled = !attends() && guests.length === 1;
                row.querySelector('.fba-remove-guest')?.setAttribute('aria-label', 'Supprimer le participant ' + (index + 1));
                if (heading && heading.textContent !== 'Participant ' + (index + 1)) heading.textContent = 'Participant ' + (index + 1);
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
                        if (!field.required) { const clear = document.createElement('button'); clear.type = 'button'; clear.textContent = 'Effacer ce choix'; clear.addEventListener('click', () => { if (checkoutLocked) return; group.querySelectorAll('input').forEach(input => input.checked = false); update(); }); group.append(clear); }
                        panel.append(group); return;
                    }
                    const input = document.createElement(field.type === 'select' ? 'select' : 'input');
                    input.dataset.fbaAnswer = field.id;
                    input.required = field.required;
                    if (field.type === 'select') {
                        const placeholder = document.createElement('option'); placeholder.value = ''; placeholder.textContent = 'Choisir…'; input.append(placeholder);
                        field.choices.forEach((value, index) => { const option = document.createElement('option'); option.value = value; option.textContent = choiceLabel(value, index); input.append(option); });
                    } else { input.type = ['number','checkbox'].includes(field.type) ? field.type : 'text'; if (field.type === 'number') { input.step = 'any'; if (field.min != null) input.min = field.min; if (field.max != null) input.max = field.max; } else input.maxLength = 1000; }
                    label.append(input);panel.append(label);
                });
                row.append(panel);
            });
            const payload = guests.map(row => ({name: row.querySelector('[data-fba-identity=name]')?.value || '', email: row.querySelector('[data-fba-identity=email]')?.value || '', fields: read(row), ...(nativeTariffs ? {tariff: row.querySelector('[data-fba-tariff]')?.value || ''} : {})}));
            const serialized = JSON.stringify(structuredPayload ? {holder_participates: attends(), holder_tariff: attends() ? holder?.querySelector('select').value || '' : '', guests: payload} : payload);
            if (!restoring && transport.value !== serialized) { transport.value = serialized; transport.dispatchEvent(new Event('input', {bubbles: true})); }
            const people = guests.length + (attends() ? 1 : 0);
            guestWrap.querySelector('.fba-add-guest').disabled = people >= config.limit;
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
            if (nativeTariffs) {
                const selected = [...(attends() ? [holder?.querySelector('select').value] : []), ...payload.map(guest => guest.tariff)];
                const fragment = document.createDocumentFragment(); cents = 0;
                selected.forEach((id, index) => {
                    const tariff = config.tariffs.find(t => t.id === id); if (!tariff) return;
                    cents += tariff.cents;
                    const line = document.createElement('div'); const label = document.createElement('dt'); const value = document.createElement('dd');
                    const guestIndex = index - (attends() ? 1 : 0);
                    label.textContent = (guestIndex < 0 ? 'Vous' : payload[guestIndex].name || 'Participant ' + (guestIndex + 1)) + ' · ' + tariff.title;
                    value.textContent = format(tariff.cents); line.append(label, value); fragment.append(line);
                });
                lines.replaceChildren(fragment);
                recap.hidden = !config.tariffs.length;
            }
            quotedCents = cents;
            const total = new Intl.NumberFormat(document.documentElement.lang || 'fr', {style:'currency',currency:config.currency}).format(cents / 100);
            const message = people + (people > 1 ? ' personnes' : ' personne') + ' · ' + people + ' place(s) utilisées après confirmation' + (cents > 0 || (nativeTariffs && config.tariffs.length) ? ' · Total : ' + total : '');
            // The sidebar must not keep displaying the sum of all available choices.
            if (nativeTariffs) {
                const page = root.closest('.fluent_booking_app');
                if (page) {
                    page.classList.add('fba-priced-event');
                    page.querySelectorAll('.fcal_slot_payment_item').forEach(el => {
                        let value = el.parentElement.querySelector('.fba-sidebar-total');
                        if (!value) { value = document.createElement('div'); value.className = 'fba-sidebar-total'; el.after(value); }
                        if (value.textContent !== total) value.textContent = total;
                    });
                }
            }
            if (summary.textContent !== message) summary.textContent = message;
        };
        root.addEventListener('input', event => { if (event.target !== transport) update(); });
        root.addEventListener('change', update);
        participation?.addEventListener('change', () => {
            if (!attends() && !rows().length) add.click();
            update();
        });
        update();
        if (saved) {
            const savedGuests = structuredPayload ? saved.guests : saved;
            if (holder && typeof saved.holder_tariff === 'string') holder.querySelector('select').value = saved.holder_tariff;
            if (Array.isArray(savedGuests)) savedGuests.slice(0, config.limit - (attends() ? 1 : 0)).forEach(guest => {
                add.click(); const row = rows().at(-1); if (!row) return;
                ['name','email'].forEach(key => { const input = row.querySelector('[data-fba-identity='+key+']'); if (input && typeof guest[key] === 'string') input.value = guest[key]; });
                const tariff = row.querySelector('[data-fba-tariff]'); if (tariff && typeof guest.tariff === 'string') tariff.value = guest.tariff;
                row.querySelectorAll('[data-fba-answer]').forEach(input => {
                    const value = guest.fields?.[input.dataset.fbaAnswer] || '';
                    if (input.type === 'checkbox') input.checked = value === '1';
                    else if (input.type === 'radio') input.checked = input.value === value;
                    else input.value = value;
                });
            });
        }
        restoring = false; update();
        });
    };
    const observer = new MutationObserver(boot);
    observer.observe(document.documentElement, {childList:true,subtree:true});
    window.addEventListener('pagehide', () => { observer.disconnect(); checkoutObservers.forEach((root, item) => item.disconnect()); });
    window.addEventListener('pageshow', () => { observer.observe(document.documentElement, {childList:true,subtree:true}); checkoutObservers.forEach((root, item) => { if (root.isConnected) item.observe(root, {childList:true,subtree:true,attributes:true,attributeFilter:['class']}); else checkoutObservers.delete(item); }); boot(); });
    boot();
})();
