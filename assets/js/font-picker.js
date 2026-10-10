(() => {
    const picker = document.querySelector('[data-font-picker]');
    if (!picker) return;
    const cards = [...picker.querySelectorAll('[data-font-category]')];
    const buttons = [...picker.querySelectorAll('[data-font-filter]')];
    const count = picker.querySelector('[data-font-count]');
    const selected = picker.querySelector('[data-font-selected]');

    for (const button of buttons) {
        button.addEventListener('click', () => {
            const group = button.dataset.fontFilter;
            for (const item of buttons) {
                const active = item === button;
                item.classList.toggle('is-active', active);
                item.setAttribute('aria-pressed', String(active));
            }
            for (const card of cards) card.hidden = group !== 'all' && card.dataset.fontCategory !== group;
            const visible = cards.filter(card => !card.hidden).length;
            count.textContent = visible + (visible === 1 ? ' combinación disponible' : ' combinaciones disponibles');
        });
    }

    picker.addEventListener('change', event => {
        if (!event.target.matches('input[name="font_pair"]')) return;
        const label = event.target.closest('.font-pair-card')?.querySelector('.font-pair-card__top strong');
        if (label) selected.textContent = 'Elegida: ' + label.textContent;
    });
})();
