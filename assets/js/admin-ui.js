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

    // The server exports the same capability contract used when saving settings.
    const contractNode = document.querySelector('[data-gallery-capabilities]');
    if (contractNode) {
        const contract = JSON.parse(contractNode.textContent);
        const control = key => document.getElementById('setting-' + key);
        const value = key => control(key)?.value;
        const applyGalleryOptions = () => {
            const premium = value('gallery_premium') || 'none';
            const supports = (device, option) => Boolean(contract.standard[value('gallery_' + device)]?.[option]);
            const enabled = key => {
                if (premium !== 'none') return (contract.premium[premium] || []).includes(key);
                if (key.startsWith('gallery_')) return true;
                if (key === 'grid') return supports('mobile', 'grid') || supports('desktop', 'grid');
                if (key === 'pagination_shape') return ['mobile', 'desktop'].some(device =>
                    supports(device, 'pagination') && Number(value('photos_' + device)) !== 0);
                const [option, device] = key.split('_');
                return supports(device, option);
            };
            document.querySelectorAll('[data-gallery-control]').forEach(field => {
                const key = field.dataset.galleryControl;
                field.disabled = !enabled(key);
                field.closest('.upload-field')?.classList.toggle('is-disabled', field.disabled);
                const help = document.querySelector('[data-gallery-help="' + key + '"]');
                if (help) {
                    help.textContent = field.disabled
                        ? 'Este ajuste no se aplica al diseño seleccionado. Su valor se conserva.'
                        : key.startsWith('photos_') && ['bubbles', 'squares'].includes(premium)
                            ? 'Máximo por página: si no caben sin solaparse, se muestran menos y el resto pasa a la siguiente página. «Todas» usa la capacidad de la pantalla.' : '';
                    help.hidden = !help.textContent;
                }
            });
            const note = document.querySelector('[data-premium-note]');
            if (note) note.hidden = premium === 'none';
            const gridHelp = document.querySelector('[data-grid-help]');
            if (gridHelp) gridHelp.hidden = enabled('grid');
        };
        ['gallery_premium', 'gallery_mobile', 'gallery_desktop', 'photos_mobile', 'photos_desktop']
            .forEach(key => control(key)?.addEventListener('change', applyGalleryOptions));
        applyGalleryOptions();
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
