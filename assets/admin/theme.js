/* Share FluentBooking's theme preference without its global notice-removal script. */
(() => {
    // Run in the head so the saved theme is applied before the first paint.
    const root = document.documentElement;
    root.classList.add('fba-page');
    let button;
    const media = window.matchMedia('(prefers-color-scheme: dark)');
    let mode = 'light';
    const read = () => {
        try {
            const stored = localStorage.getItem('fluent_theme_mode') || localStorage.getItem('fcal_color_mode') || 'light';
            const value = stored.split(':')[0];
            return ['light', 'dark', 'system'].includes(value) ? value : 'light';
        } catch { return mode; }
    };
    const apply = value => {
        mode = value;
        const dark = value === 'dark' || (value === 'system' && media.matches);
        root.classList.toggle('fba-dark', dark);
        if (!button) return;
        button.setAttribute('aria-pressed', String(dark));
        button.setAttribute('aria-label', dark ? 'Activer le mode clair' : 'Activer le mode sombre');
    };
    let channel;
    try { channel = new BroadcastChannel('fluent_theme_changed:' + location.origin); } catch {}
    if (channel) channel.onmessage = event => {
        if (['light', 'dark', 'system'].includes(event.data?.mode)) apply(event.data.mode);
    };
    const bind = () => {
        button = document.querySelector('.fba-theme-toggle');
        if (!button) return;
        apply(mode);
        button.addEventListener('click', () => {
        const next = root.classList.contains('fba-dark') ? 'light' : 'dark';
        try {
            localStorage.setItem('fluent_theme_mode', next);
            localStorage.setItem('fcal_color_mode', next);
        } catch {}
        apply(next);
        channel?.postMessage({mode: next});
        });
    };
    window.addEventListener('storage', event => {
        if (['fluent_theme_mode', 'fcal_color_mode', null].includes(event.key)) apply(read());
    });
    media.addEventListener('change', () => { if (mode === 'system') apply(mode); });
    apply(read());
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', bind, {once: true});
    else bind();
})();
