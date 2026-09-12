(() => {
    const root = document.querySelector('.fba-profile');
    if (!root) return;
    let next = root.querySelectorAll('.fba-type').length;
    const types = () => [...root.querySelectorAll('.fba-types > .fba-type')];
    const control = (row, key) => row.querySelector(`[name$="[${key}]"]`);
    function refresh() {
        const rows = types();
        for (const row of rows) {
            const label = control(row, 'label').value.trim();
            row.querySelector('legend').textContent = label || 'Nouvelle catégorie';
            const select = control(row, 'requires');
            const value = select.value;
            select.replaceChildren(new Option('Aucun accompagnateur requis', ''));
            for (const other of rows) {
                const otherId = control(other, 'id').value;
                if (other !== row && otherId) select.add(new Option(`Au moins un participant « ${control(other, 'label').value || otherId} »`, otherId));
            }
            if (value && ![...select.options].some(option => option.value === value)) {
                select.add(new Option(`Catégorie à remplacer : ${value}`, value));
            }
            select.value = value;
            row.querySelector('.fba-remove-type').disabled = rows.length === 1;
        }
        const pricing = root.querySelector('[name="profile[pricing]"]').checked;
        root.querySelectorAll('.fba-category-price,.fba-pricing-note').forEach(el => { el.hidden = !pricing; });
        root.querySelector('.fba-add-type').disabled = rows.length >= 20;
    }
    root.addEventListener('change', event => {
        const row = event.target.closest('.fba-type');
        if (row && event.target === control(row, 'label') && !control(row, 'id').value) {
            const base = event.target.value.normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '').slice(0, 24);
            if (/^[a-z]/.test(base)) {
                let id = base; let suffix = 2;
                const used = types().filter(other => other !== row).map(other => control(other, 'id').value);
                while (used.includes(id)) id = `${base}-${suffix++}`;
                control(row, 'id').value = id;
            }
        }
        if (event.target.name?.endsWith('[label]') || event.target.name?.endsWith('[id]')) refresh();
    });
    root.addEventListener('change', refresh);
    root.addEventListener('click', event => {
        if (event.target.matches('.fba-add-type') && types().length < 20) {
            const fragment = document.createElement('template');
            fragment.innerHTML = document.querySelector('#fba-type-template').innerHTML.replaceAll('__INDEX__', String(next++));
            root.querySelector('.fba-types').append(fragment.content.cloneNode(true));
            refresh();
            control(types().at(-1), 'label').focus();
        }
        if (event.target.matches('.fba-remove-type') && types().length > 1) {
            event.target.closest('.fba-type').remove();
            refresh();
            root.querySelector('.fba-add-type').focus();
        }
    });
    const mode = document.querySelector('#mode-booking_profile');
    const controls = root.closest('.fba-profile-controls');
    const updateMode = () => { if (controls && mode) controls.disabled = mode.value === 'inherit'; };
    mode?.addEventListener('change', updateMode);
    updateMode();
    refresh();
})();
