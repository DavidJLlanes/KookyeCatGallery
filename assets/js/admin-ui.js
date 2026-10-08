(() => {
    'use strict';

    // Orden de los bloques de la portada: el orden del DOM es el orden enviado
    // (cada fila lleva su input oculto section_order[]).
    const list = document.querySelector('[data-section-order]');
    if (!list) return;

    const live = document.createElement('p');
    live.className = 'sr-only';
    live.setAttribute('aria-live', 'polite');
    live.style.cssText = 'position:absolute;width:1px;height:1px;overflow:hidden;clip:rect(0 0 0 0);white-space:nowrap';
    list.after(live);

    const refresh = () => {
        const items = [...list.children];
        items.forEach((item, index) => {
            const up = item.querySelector('[data-move="up"]');
            const down = item.querySelector('[data-move="down"]');
            if (up) up.disabled = index === 0;
            if (down) down.disabled = index === items.length - 1;
        });
    };

    list.addEventListener('click', event => {
        const button = event.target.closest('[data-move]');
        if (!button || button.disabled) return;
        const item = button.closest('.section-order__item');
        const direction = button.dataset.move;
        const sibling = direction === 'up' ? item.previousElementSibling : item.nextElementSibling;
        if (!sibling) return;
        if (direction === 'up') sibling.before(item); else sibling.after(item);
        item.classList.add('is-moved');
        refresh();
        const target = button.disabled ? item.querySelector(`[data-move="${direction === 'up' ? 'down' : 'up'}"]`) : button;
        target?.focus();
        const name = item.querySelector('strong')?.textContent || '';
        live.textContent = `${name}: posición ${[...list.children].indexOf(item) + 1} de ${list.children.length}`;
    });

    refresh();
})();
