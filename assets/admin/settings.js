/* Progressive enhancement only: the server ignores a number in inheritance mode. */
(() => {
    const mode = document.getElementById('fba-limit-mode');
    const maximum = document.getElementById('fba-maximum');
    if (!mode || !maximum) return;
    const update = () => { maximum.disabled = mode.value === 'inherit'; };
    mode.addEventListener('change', update);
    update();
})();
