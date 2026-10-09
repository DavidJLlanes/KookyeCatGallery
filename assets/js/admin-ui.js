/* =============================================================================
   PANEL DE ADMINISTRACIÓN · comportamiento de la pantalla «Diseño»
   -----------------------------------------------------------------------------
   Índice
     1. Galería premium: desactiva los ajustes de la galería estándar que ignora.
     2. Textos de la plantilla activa.
     3. Orden de los bloques de la portada (flechas ↑ ↓).
   ============================================================================= */
(() => {
    'use strict';

    /* -------------------------------------------------------------------------
       1. Galería premium
       Al elegir una galería premium se desactivan los campos marcados con
       data-premium-off="<galerías que lo ignoran>" (los genera site_settings_form()). Un campo
       desactivado no se envía; el servidor conserva entonces los valores guardados (admin.php).
       Los campos que ignora cada galería premium están en site_premium_ignored_settings().
       ------------------------------------------------------------------------- */
    const premiumSelect = document.getElementById('setting-gallery_premium');
    if (premiumSelect) {
        const fields = [...document.querySelectorAll('[data-premium-off]')];
        const note = document.querySelector('[data-premium-note]');
        const apply = () => {
            const gallery = premiumSelect.value;
            fields.forEach(field => {
                const off = gallery !== 'none' && (field.dataset.premiumOff || '').split(',').includes(gallery);
                field.disabled = off;
                field.closest('.upload-field')?.classList.toggle('is-disabled', off);
            });
            if (note) note.hidden = gallery === 'none';
        };
        premiumSelect.addEventListener('change', apply);
        apply();
    }

    /* -------------------------------------------------------------------------
       2. Textos editables del diseño seleccionado. Los campos ocultos permanecen
       habilitados para que un cambio de plantilla no borre sus textos guardados.
       ------------------------------------------------------------------------- */
    const templateGroups = [...document.querySelectorAll('[data-template-text-group]')];
    if (templateGroups.length) {
        const updateTemplateGroups = () => {
            templateGroups.forEach(group => {
                const selected = document.getElementById('setting-' + group.dataset.templateSetting)?.value;
                const options = (group.dataset.templateOptions || '').split(',');
                group.hidden = !options.includes('*') && !options.includes(selected);
            });
        };
        [...new Set(templateGroups.map(group => group.dataset.templateSetting))].forEach(setting => {
            document.getElementById('setting-' + setting)?.addEventListener('change', updateTemplateGroups);
        });
        updateTemplateGroups();
    }

    /* -------------------------------------------------------------------------
       3. Orden de los bloques de la portada
       El orden del DOM es el orden enviado (cada fila lleva su input oculto section_order[]).
       ------------------------------------------------------------------------- */
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
