/* Global email appearance is independent of the selected event. */
(() => {
    const toggle = document.querySelector('[name="email_appearance_enabled"]');
    const options = document.getElementById('fba-email-options');
    if (!toggle || !options) return;
    const refresh = () => {
        options.hidden = !toggle.checked;
        toggle.setAttribute('aria-expanded', String(toggle.checked));
    };
    toggle.addEventListener('change', refresh);
    refresh();
})();

/* Guest options only. Native FluentBooking owns all participant limits. */
(() => {
    const root = document.querySelector('.fba-guest-options');
    if (!root) return;
    const list = root.querySelector('.fba-guest-fields');
    const template = root.querySelector('template');
    let index = list.children.length;
    const refresh = () => {
        const enabled = root.querySelector('[name="guest_options[customize_guests]"]');
        const details = root.querySelector('.fba-guest-details');
        details.hidden = !enabled.checked;
        enabled.setAttribute('aria-expanded', String(enabled.checked));
        const native = root.querySelector('[name="guest_options[native_tariffs]"]')?.value === '1';
        root.querySelector('.fba-legacy-pricing')?.toggleAttribute('hidden', native);
        const nameMode = root.querySelector('[name="guest_options[name_mode]"]')?.value;
        const splitOption = root.querySelector('.fba-split-name-option');
        if (splitOption) splitOption.hidden = nameMode === 'hidden';
        list.querySelectorAll('.fba-extra-field').forEach(row => {
            const type = row.querySelector('select').value;
            row.querySelector('.fba-field-choices').hidden = !['select','radio'].includes(type);
            row.querySelector('.fba-field-bounds')?.toggleAttribute('hidden', type !== 'number');
            const pricing = row.querySelector('.fba-field-pricing');
            pricing.hidden = native || !['select','radio','checkbox'].includes(type);
            if (!['select','radio','checkbox'].includes(type)) pricing.querySelector('select').value = 'none';
            if (native && pricing.querySelector('select').value !== 'none') pricing.hidden = false;
            row.querySelector('.fba-field-prices').hidden = pricing.querySelector('select').value === 'none';
        });
        root.querySelector('.fba-add-field').disabled = list.children.length >= 8;
    };
    root.addEventListener('click', event => {
        if (event.target.closest('.fba-add-field') && list.children.length < 8) {
            const holder = document.createElement('template');
            holder.innerHTML = template.innerHTML.replaceAll('__INDEX__', String(index++));
            const row = holder.content.firstElementChild;
            row.querySelector('[data-field-id]').value = 'field_' + crypto.randomUUID().replaceAll('-', '').slice(0, 20);
            list.append(row);
            row.querySelector('input[type=text]').focus();
        }
        const remove = event.target.closest('.fba-remove-field');
        if (remove) remove.closest('.fba-extra-field').remove();
        refresh();
    });
    root.addEventListener('change', refresh);
    refresh();
})();
