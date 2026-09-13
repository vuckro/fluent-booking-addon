/* The server remains authoritative; without JavaScript all choices are usable. */
(() => {
    const form = document.querySelector('.fba-form');
    if (!form) return;
    const maximum = form.querySelector('#fba-maximum');
    const group = form.querySelector('.fba-maximum');
    const update = () => {
        const limited = form.querySelector('input[name="policy"]:checked')?.value === 'limit';
        group.hidden = !limited;
        maximum.disabled = !limited;
        maximum.required = limited;
    };
    form.addEventListener('change', update);
    update();
})();
(() => {
    const root = document.querySelector('.fba-guest-options');
    if (!root) return;
    const list = root.querySelector('.fba-guest-fields');
    const template = root.querySelector('template');
    let index = list.children.length;
    const refresh = () => {
        const enabled = root.querySelector('[name="guest_options[enabled]"]');
        const details = root.querySelector('.fba-guest-details');
        details.hidden = !enabled.checked;
        enabled.setAttribute('aria-expanded', String(enabled.checked));
        list.querySelectorAll('.fba-extra-field').forEach(row => {
            const type = row.querySelector('select').value;
            row.querySelector('.fba-field-choices').hidden = !['select','radio'].includes(type);
            const pricing = row.querySelector('.fba-field-pricing');
            pricing.hidden = !['select','radio','checkbox'].includes(type);
            if (pricing.hidden) pricing.querySelector('select').value = 'none';
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
