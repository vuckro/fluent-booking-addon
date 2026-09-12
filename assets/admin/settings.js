/* Progressive enhancement only: inherited values remain server-owned. */
document.querySelectorAll('.fba-dashboard [data-fba-mode]').forEach(function (mode) {
    var value = document.getElementById(mode.dataset.fbaMode);
    if (!value) return;
    function refresh() {
        value.disabled = mode.value === 'inherit';
    }
    mode.addEventListener('change', refresh);
    refresh();
});
