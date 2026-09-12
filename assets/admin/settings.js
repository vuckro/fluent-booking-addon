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
