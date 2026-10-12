/* =============================================================================
   Menú del botón «+» de la portada: se despliega con dos accesos (subir foto y nuevo post).
   Copia el color del botón a los iconos para respetar la paleta y los diseños activos.
   ============================================================================= */
(() => {
    'use strict';
    const menu = document.querySelector('[data-fab-menu]');
    const toggle = menu?.querySelector('[data-fab-toggle]');
    if (!menu || !toggle) return;

    const syncColors = () => {
        const style = getComputedStyle(toggle);
        menu.style.setProperty('--fab-bg', style.backgroundImage !== 'none' ? style.backgroundImage : style.backgroundColor);
        menu.style.setProperty('--fab-fg', style.color);
        menu.style.setProperty('--fab-border', style.borderTopColor);
    };
    const setOpen = open => {
        if (open) syncColors();
        menu.classList.toggle('is-open', open);
        toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
        menu.querySelectorAll('.fab-menu__item').forEach(item => { item.tabIndex = open ? 0 : -1; });
    };
    setOpen(false);

    toggle.addEventListener('click', () => setOpen(!menu.classList.contains('is-open')));
    // «Nuevo post» con sesión: no navega, abre el panel flotante de la propia web (microblog-composer.js).
    menu.querySelector('[data-mbc-open]')?.addEventListener('click', () => {
        setOpen(false);
        document.dispatchEvent(new CustomEvent('mbc:open'));
    });
    menu.querySelector('.fab-menu__scrim')?.addEventListener('click', () => setOpen(false));
    document.addEventListener('keydown', event => {
        if (event.key === 'Escape' && menu.classList.contains('is-open')) { setOpen(false); toggle.focus(); }
    });
    document.addEventListener('click', event => {
        if (menu.classList.contains('is-open') && !menu.contains(event.target)) setOpen(false);
    });
})();
