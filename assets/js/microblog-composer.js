/* =============================================================================
   MICROBLOG · panel flotante «Nuevo post» (solo propietario)
   -----------------------------------------------------------------------------
     1. Abrir y cerrar (botón «+», Escape, fuera del panel, arrastrar el asa en móvil) con foco atrapado.
     2. Secciones opcionales: cita, enlaces e imágenes.
     3. Borrador del texto guardado en el navegador.
     4. Envío por fetch a /admin.php (respuesta JSON) y actualización del feed sin recargar.
   El contador, el botón «Enlace», las filas de enlaces y las imágenes los gestiona microblog-admin.js.
   ============================================================================= */
(() => {
    'use strict';

    const root = document.querySelector('[data-mbc]');
    if (!root) return;
    const html = document.documentElement;
    const panel = root.querySelector('.mbc__panel');
    const form = root.querySelector('[data-mbc-form]');
    const text = form.querySelector('[data-mb-text]');
    const status = root.querySelector('[data-mbc-status]');
    const submit = root.querySelector('[data-mbc-submit]');
    const DRAFT_KEY = 'djl-mbc-draft';
    const FLASH_KEY = 'djl-mbc-flash';
    let busy = false;
    let lastFocus = null;
    let closeTimer = 0;

    const store = {
        get(key) { try { return localStorage.getItem(key) || ''; } catch (_) { return ''; } },
        set(key, value) { try { value ? localStorage.setItem(key, value) : localStorage.removeItem(key); } catch (_) {} },
        flash(value) { try { value ? sessionStorage.setItem(FLASH_KEY, value) : sessionStorage.removeItem(FLASH_KEY); } catch (_) {} },
        takeFlash() { try { const v = sessionStorage.getItem(FLASH_KEY) || ''; sessionStorage.removeItem(FLASH_KEY); return v; } catch (_) { return ''; } }
    };

    const toast = (() => {
        let node = null, timer = 0;
        return message => {
            if (!node) {
                node = document.createElement('div');
                node.className = 'mb-toast';
                node.setAttribute('role', 'status');
                document.body.append(node);
            }
            node.textContent = message;
            node.classList.add('is-visible');
            clearTimeout(timer);
            timer = setTimeout(() => node.classList.remove('is-visible'), 3200);
        };
    })();

    const setStatus = (message, isError = false) => {
        status.textContent = message || '';
        status.classList.toggle('is-error', isError);
    };

    // 2. Secciones opcionales ------------------------------------------------------------
    const section = key => root.querySelector('[data-mbc-section="' + key + '"]');
    const chip = key => root.querySelector('[data-mbc-toggle="' + key + '"]');
    const setSection = (key, open) => {
        const target = section(key);
        if (!target) return;
        target.hidden = !open;
        chip(key)?.setAttribute('aria-expanded', open ? 'true' : 'false');
        if (open) {
            target.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
            target.querySelector('input:not([type="file"]), textarea')?.focus({ preventScroll: true });
        }
    };
    root.querySelectorAll('[data-mbc-toggle]').forEach(button => {
        button.addEventListener('click', () => setSection(button.dataset.mbcToggle, section(button.dataset.mbcToggle).hidden));
    });

    const resetForm = () => {
        form.reset();
        form.querySelector('[data-mb-previews]')?.replaceChildren();
        form.querySelectorAll('[data-mb-link-row]').forEach((row, i) => { row.hidden = i > 0; });
        const add = form.querySelector('[data-mb-add-link]');
        if (add) add.hidden = false;
        form.querySelector('[data-mb-linkbox]')?.setAttribute('hidden', '');
        ['quote', 'links', 'images'].forEach(key => setSection(key, false));
        text.dispatchEvent(new Event('input', { bubbles: true }));
    };

    // 3. Borrador --------------------------------------------------------------------------
    text.addEventListener('input', () => store.set(DRAFT_KEY, text.value));

    // 1. Abrir y cerrar ----------------------------------------------------------------------
    const focusable = () => Array.from(panel.querySelectorAll('button, [href], input, textarea, select, [tabindex]:not([tabindex="-1"])'))
        .filter(el => !el.disabled && !el.closest('[hidden]') && el.type !== 'hidden' && el.offsetParent !== null);

    const open = () => {
        clearTimeout(closeTimer);
        lastFocus = document.activeElement;
        if (!text.value) text.value = store.get(DRAFT_KEY);
        text.dispatchEvent(new Event('input', { bubbles: true }));
        setStatus('');
        root.hidden = false;
        html.classList.add('mbc-open');
        requestAnimationFrame(() => requestAnimationFrame(() => root.classList.add('is-open')));
        setTimeout(() => text.focus({ preventScroll: true }), 380);
    };
    const close = () => {
        if (busy || root.hidden) return;
        panel.style.transform = '';
        root.classList.remove('is-open', 'is-dragging');
        html.classList.remove('mbc-open');
        closeTimer = setTimeout(() => { root.hidden = true; }, 450);
        if (lastFocus && typeof lastFocus.focus === 'function') lastFocus.focus({ preventScroll: true });
    };
    document.addEventListener('mbc:open', open);
    root.querySelectorAll('[data-mbc-close]').forEach(el => el.addEventListener('click', close));
    document.addEventListener('keydown', event => {
        if (root.hidden) return;
        if (event.key === 'Escape') { event.preventDefault(); close(); return; }
        if (event.key === 'Tab') {
            const items = focusable();
            if (!items.length) return;
            const first = items[0], last = items[items.length - 1];
            if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last.focus(); }
            else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first.focus(); }
        }
        if ((event.ctrlKey || event.metaKey) && event.key === 'Enter') { event.preventDefault(); form.requestSubmit(); }
    });

    // Arrastrar el asa o la cabecera hacia abajo cierra la hoja en móvil.
    const mobile = window.matchMedia('(max-width: 640px)');
    let drag = null;
    const dragTargets = [root.querySelector('[data-mbc-grip]'), root.querySelector('.mbc__head')];
    dragTargets.forEach(target => {
        target?.addEventListener('pointerdown', event => {
            if (!mobile.matches || event.target.closest('button')) return;
            drag = { y: event.clientY, dy: 0, t: performance.now(), id: event.pointerId, el: target };
            target.setPointerCapture(event.pointerId);
            root.classList.add('is-dragging');
        });
        target?.addEventListener('pointermove', event => {
            if (!drag || event.pointerId !== drag.id) return;
            drag.dy = Math.max(0, event.clientY - drag.y);
            panel.style.transform = 'translateY(' + drag.dy + 'px)';
        });
        const end = event => {
            if (!drag || event.pointerId !== drag.id) return;
            const fast = drag.dy / Math.max(1, performance.now() - drag.t) > 0.6;
            const far = drag.dy > 120;
            root.classList.remove('is-dragging');
            drag = null;
            if (far || fast) close(); else panel.style.transform = '';
        };
        target?.addEventListener('pointerup', end);
        target?.addEventListener('pointercancel', end);
    });

    // 4. Envío -----------------------------------------------------------------------------------
    form.addEventListener('submit', async event => {
        event.preventDefault();
        if (busy) return;
        const has = text.value.trim() !== ''
            || form.elements.quote_text.value.trim() !== ''
            || Array.from(form.querySelectorAll('[name="link_url[]"]')).some(input => input.value.trim() !== '')
            || (form.querySelector('[data-mb-images]')?.files.length || 0) > 0;
        if (!has) {
            setStatus('Escribe algo o añade una cita, un enlace o una imagen.', true);
            text.focus();
            return;
        }
        busy = true;
        submit.disabled = true;
        submit.classList.add('is-busy');
        setStatus('Publicando…');
        try {
            const response = await fetch('/admin.php', { method: 'POST', body: new FormData(form), credentials: 'same-origin', headers: { Accept: 'application/json' } });
            let data = null;
            try { data = await response.json(); } catch (_) { /* respuesta que no es JSON */ }
            if (!response.ok || !data?.ok) throw new Error(data?.error || 'No se pudo publicar. Inténtalo de nuevo.');

            store.set(DRAFT_KEY, '');
            resetForm();
            busy = false;
            close();
            const hiddenNote = data.visible === false ? ' (el microblog está oculto en la web: actívalo en Ajustes → Publicación)' : '';
            if (data.scheduled || data.visible === false || !window.djlMicroblog) {
                toast(data.message + hiddenNote);
            } else {
                let refreshed = false;
                try { refreshed = await window.djlMicroblog.refresh(); } catch (_) { refreshed = false; }
                if (refreshed) {
                    toast(data.message);
                    document.querySelector('#microblog')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
                } else {
                    store.flash(data.message);
                    location.hash = 'microblog';
                    location.reload();
                }
            }
        } catch (error) {
            setStatus(error?.message || 'No se pudo publicar. Inténtalo de nuevo.', true);
        } finally {
            busy = false;
            submit.disabled = false;
            submit.classList.remove('is-busy');
        }
    });

    const flash = store.takeFlash();
    if (flash) setTimeout(() => toast(flash), 800);
})();
