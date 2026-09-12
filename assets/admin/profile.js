(() => {
    const root = document.querySelector('.fba-profile');
    if (!root) return;
    let next = root.querySelectorAll('.fba-type').length;
    root.addEventListener('click', event => {
        if (event.target.matches('.fba-add-type')) {
            if (root.querySelectorAll('.fba-type').length >= 20) return;
            const template = document.querySelector('#fba-type-template');
            const fragment = document.createElement('template');
            fragment.innerHTML = template.innerHTML.replaceAll('__INDEX__', String(next++));
            root.querySelector('.fba-types').append(fragment.content.cloneNode(true));
        }
        if (event.target.matches('.fba-remove-type') && root.querySelectorAll('.fba-type').length > 1) {
            event.target.closest('.fba-type').remove();
        }
    });
})();
