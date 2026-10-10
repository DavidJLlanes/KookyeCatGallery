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

    const applySiteTitle = value => {
        if (typeof value !== 'string' || !value.trim()) return;
        const title = value.trim().slice(0, 80);
        document.querySelectorAll('.admin-brand__text strong').forEach(node => { node.textContent = title; });
        const initial = Array.from(title)[0]?.toLocaleUpperCase() || '';
        document.querySelectorAll('.admin-brand__mark').forEach(node => { node.textContent = initial; });
    };
    const readSiteTitleUpdate = raw => {
        try { applySiteTitle(JSON.parse(raw)?.value); } catch (_) {}
    };
    window.addEventListener('storage', event => {
        if (event.key === 'gallery-admin-site-title' && event.newValue) readSiteTitleUpdate(event.newValue);
    });
    if ('BroadcastChannel' in window) {
        try {
            const titleChannel = new BroadcastChannel('gallery-admin-site-title');
            titleChannel.addEventListener('message', event => applySiteTitle(event.data?.value));
        } catch (_) {}
    }

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
       3. Textos editables del diseño seleccionado. Los campos ocultos permanecen
       habilitados para que un cambio de plantilla no borre sus textos guardados.
       ------------------------------------------------------------------------- */
    const gridSelect = document.getElementById('setting-grid');
    const gridField = gridSelect?.closest('[data-grid-setting]');
    const gridHelp = document.querySelector('[data-grid-help]');
    const galleryLayoutSelects = [
        document.getElementById('setting-gallery_mobile'),
        document.getElementById('setting-gallery_desktop'),
    ].filter(Boolean);
    const premiumForGrid = document.getElementById('setting-gallery_premium');
    if (gridSelect && galleryLayoutSelects.length) {
        const updateGridAvailability = () => {
            const premium = premiumForGrid && premiumForGrid.value !== 'none';
            const used = galleryLayoutSelects.some(select => ['standard', 'grid', 'contact-sheet', 'triptych'].includes(select.value));
            const disabled = Boolean(premium || !used);
            gridSelect.disabled = disabled;
            gridField?.classList.toggle('is-disabled', disabled);
            if (gridHelp) gridHelp.hidden = Boolean(premium || used);
        };
        galleryLayoutSelects.forEach(select => select.addEventListener('change', updateGridAvailability));
        premiumForGrid?.addEventListener('change', updateGridAvailability);
        updateGridAvailability();
    }

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
       4. Editor de Redes Sociales: oculta las filas vacías y permite añadirlas
       progresivamente, sin perder enlaces o campos parciales ya guardados.
       ------------------------------------------------------------------------- */
    const socialEditor = document.querySelector('[data-social-editor]');
    if (socialEditor) {
        const rows = [...socialEditor.querySelectorAll('[data-social-row]')];
        const addSocial = socialEditor.querySelector('[data-social-add]');
        const empty = row => [...row.querySelectorAll('select, input')].every(field => !field.value.trim());
        const syncSocialRows = () => {
            rows.forEach(row => {
                const isEmpty = empty(row);
                row.dataset.socialEmpty = String(isEmpty);
                if (!isEmpty) delete row.dataset.socialOpen;
            });
            const available = rows.some(row => empty(row) && row.dataset.socialOpen !== 'true');
            if (addSocial) addSocial.disabled = !available;
        };
        socialEditor.classList.add('is-enhanced');
        addSocial?.addEventListener('click', () => {
            const row = rows.find(candidate => empty(candidate) && candidate.dataset.socialOpen !== 'true');
            if (!row) return;
            row.dataset.socialOpen = 'true';
            syncSocialRows();
            row.querySelector('select')?.focus();
        });
        rows.forEach(row => {
            row.addEventListener('input', syncSocialRows);
            row.addEventListener('change', syncSocialRows);
            row.querySelector('[data-social-remove]')?.addEventListener('click', () => {
                row.querySelectorAll('select, input').forEach(field => {
                    field.value = '';
                    field.dispatchEvent(new Event('change', {bubbles: true}));
                });
                delete row.dataset.socialOpen;
                syncSocialRows();
            });
        });
        syncSocialRows();
    }

    /* -------------------------------------------------------------------------
       5. Orden de los bloques de la portada
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
