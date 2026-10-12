/* =============================================================================
   PANEL · pestaña Microblog
   -----------------------------------------------------------------------------
     1. Contador de caracteres del texto.
     1b. Botón «Enlace» (Ctrl+K): inserta [texto](https://…) en el punto del cursor, o alrededor de lo seleccionado.
     2. «Añadir otro enlace»: muestra las filas de enlace ocultas.
     3. Imágenes: reduce cada foto en el navegador antes de enviarla (el servidor limita la petición a unos
        20 MB en total), muestra miniaturas y pide un texto alternativo para cada una.
   ============================================================================= */
(() => {
    'use strict';

    const MAX_SIDE = 1500; // igual que el servidor: lado mayor máximo
    const MAX_TOTAL_BYTES = 18 * 1024 * 1024;

    document.querySelectorAll('[data-mb-admin-form]').forEach(form => {
        // 1. Contador
        const text = form.querySelector('[data-mb-text]');
        const count = form.querySelector('[data-mb-count]');
        if (text && count) {
            const update = () => { count.textContent = Array.from(text.value).length; };
            text.addEventListener('input', update);
            update();
        }

        // 1b. Enlace dentro del texto
        const tool = form.querySelector('[data-mb-link-tool]');
        const box = form.querySelector('[data-mb-linkbox]');
        if (tool && box && text) {
            const labelInput = box.querySelector('[data-mb-link-label]');
            const hrefInput = box.querySelector('[data-mb-link-href]');
            const errorNote = box.querySelector('[data-mb-link-error]');
            let range = [0, 0];
            const closeBox = (refocus = true) => {
                box.hidden = true;
                tool.setAttribute('aria-expanded', 'false');
                if (refocus) text.focus();
            };
            const openBox = () => {
                range = [text.selectionStart ?? text.value.length, text.selectionEnd ?? text.value.length];
                labelInput.value = text.value.slice(range[0], range[1]).replace(/[\[\]\n]+/g, ' ').trim();
                hrefInput.value = '';
                errorNote.hidden = true;
                box.hidden = false;
                tool.setAttribute('aria-expanded', 'true');
                (labelInput.value ? hrefInput : labelInput).focus();
            };
            const applyLink = () => {
                let href = hrefInput.value.trim();
                if (href && !/^https?:\/\//i.test(href)) href = 'https://' + href;
                if (!/^https?:\/\/[^\s]+\.[^\s]+$/i.test(href)) {
                    errorNote.textContent = 'Escribe una dirección válida, por ejemplo https://ejemplo.com';
                    errorNote.hidden = false;
                    hrefInput.focus();
                    return;
                }
                href = href.replace(/\(/g, '%28').replace(/\)/g, '%29');
                const label = labelInput.value.replace(/[\[\]\n]+/g, ' ').trim();
                text.setRangeText(label ? '[' + label + '](' + href + ')' : href, range[0], range[1], 'end');
                text.dispatchEvent(new Event('input', { bubbles: true }));
                closeBox();
            };
            tool.addEventListener('click', () => (box.hidden ? openBox() : closeBox()));
            box.querySelector('[data-mb-link-apply]')?.addEventListener('click', applyLink);
            box.querySelector('[data-mb-link-cancel]')?.addEventListener('click', () => closeBox());
            box.addEventListener('keydown', event => {
                if (event.key === 'Enter') { event.preventDefault(); applyLink(); }
                if (event.key === 'Escape') { event.preventDefault(); event.stopPropagation(); closeBox(); }
            });
            text.addEventListener('keydown', event => {
                if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 'k') { event.preventDefault(); openBox(); }
            });
        }

        // 2. Enlaces
        const addLink = form.querySelector('[data-mb-add-link]');
        const syncAddLink = () => { addLink.hidden = !form.querySelector('[data-mb-link-row][hidden]'); };
        if (addLink) {
            addLink.addEventListener('click', () => {
                const next = form.querySelector('[data-mb-link-row][hidden]');
                if (next) { next.hidden = false; next.querySelector('input')?.focus(); }
                syncAddLink();
            });
            syncAddLink();
        }

        // 3. Imágenes
        const input = form.querySelector('[data-mb-images]');
        const previews = form.querySelector('[data-mb-previews]');
        if (!input || !previews) return;
        const limit = Number(input.dataset.mbMax || 4);
        let busy = false;

        const shrink = async file => {
            if (!/^image\/(jpeg|png|webp)$/.test(file.type) || !window.createImageBitmap) return file;
            try {
                const bitmap = await createImageBitmap(file, { imageOrientation: 'from-image' });
                const scale = Math.min(1, MAX_SIDE / Math.max(bitmap.width, bitmap.height));
                if (scale === 1 && file.size < 1.5 * 1024 * 1024) { bitmap.close?.(); return file; }
                const canvas = document.createElement('canvas');
                canvas.width = Math.max(1, Math.round(bitmap.width * scale));
                canvas.height = Math.max(1, Math.round(bitmap.height * scale));
                canvas.getContext('2d').drawImage(bitmap, 0, 0, canvas.width, canvas.height);
                bitmap.close?.();
                const blob = await new Promise(resolve => canvas.toBlob(resolve, 'image/jpeg', 0.92));
                if (!blob || blob.size >= file.size) return file;
                return new File([blob], file.name.replace(/\.[^.]+$/, '') + '.jpg', { type: 'image/jpeg', lastModified: Date.now() });
            } catch (_) {
                return file;
            }
        };
        const note = message => {
            const el = document.createElement('p');
            el.className = 'mb-previews__note';
            el.textContent = message;
            previews.append(el);
        };

        input.addEventListener('change', async () => {
            if (busy) return;
            busy = true;
            previews.replaceChildren();
            let files = Array.from(input.files || []);
            if (files.length > limit) {
                files = files.slice(0, limit);
                note('Solo caben ' + limit + ' imágenes en este post; se han tomado las primeras.');
            }
            note('Preparando imágenes…');
            const prepared = [];
            for (const file of files) prepared.push(await shrink(file));
            previews.replaceChildren();
            const total = prepared.reduce((sum, file) => sum + file.size, 0);
            if (total > MAX_TOTAL_BYTES) {
                input.value = '';
                note('Las imágenes pesan demasiado juntas (máx. ' + Math.round(MAX_TOTAL_BYTES / 1048576) + ' MB). Elige menos o más pequeñas.');
                busy = false;
                return;
            }
            try {
                const transfer = new DataTransfer();
                prepared.forEach(file => transfer.items.add(file));
                input.files = transfer.files;
            } catch (_) { /* navegador sin DataTransfer: se envían los originales */ }
            prepared.forEach(file => {
                const card = document.createElement('label');
                card.className = 'mb-preview';
                const img = document.createElement('img');
                img.alt = '';
                img.src = URL.createObjectURL(file);
                img.addEventListener('load', () => URL.revokeObjectURL(img.src), { once: true });
                const alt = document.createElement('input');
                alt.type = 'text';
                alt.name = 'image_alt[]';
                alt.maxLength = 200;
                alt.placeholder = 'Texto alternativo (opcional)';
                alt.setAttribute('aria-label', 'Texto alternativo de ' + file.name);
                card.append(img, alt);
                previews.append(card);
            });
            busy = false;
        });

        form.addEventListener('submit', event => { if (busy) event.preventDefault(); });
    });
})();
