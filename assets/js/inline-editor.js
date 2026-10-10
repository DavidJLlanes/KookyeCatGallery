(() => {
    'use strict';

    const body = document.body;
    const launch = document.querySelector('[data-inline-editor-toggle]');
    const panel = document.querySelector('[data-inline-editor-panel]');
    if (!body?.dataset.inlineEditorCsrf || !launch || !panel) return;

    const input = panel.querySelector('[data-inline-editor-input]');
    const label = panel.querySelector('[data-inline-editor-label]');
    const status = panel.querySelector('[data-inline-editor-status]');
    const save = panel.querySelector('[data-inline-editor-save]');
    const closeButtons = panel.querySelectorAll('[data-inline-editor-close], [data-inline-editor-cancel]');
    let activeText = null;
    let savedValue = '';

    const setStatus = (message, state = '') => {
        status.textContent = message;
        status.dataset.state = state;
    };
    const dirty = () => Boolean(activeText) && input.value !== savedValue;
    const setEditing = enabled => {
        if (!enabled && dirty() && !window.confirm('Hay cambios sin guardar. ¿Salir y descartarlos?')) return;
        body.dataset.inlineEditMode = enabled ? 'true' : 'false';
        document.querySelectorAll('[data-inline-edit-key]').forEach(element => {
            if (enabled) { element.tabIndex = 0; element.setAttribute('role', 'button'); }
            else { element.removeAttribute('tabindex'); element.removeAttribute('role'); }
        });
        launch.setAttribute('aria-pressed', String(enabled));
        const accessibleLabel = enabled ? 'Salir de edición' : 'Editar página';
        launch.setAttribute('aria-label', accessibleLabel);
        launch.title = accessibleLabel;
        const icon = launch.querySelector('[data-inline-editor-icon]');
        if (icon) {
            icon.innerHTML = enabled
                ? '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 5l14 14M19 5L5 19"/></svg>'
                : '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 20h9"/><path d="M16.5 3.5a2.12 2.12 0 013 3L8 18l-4 1 1-4Z"/></svg>';
        }
        if (!enabled) closePanel();
    };
    const closePanel = () => {
        panel.hidden = true;
        activeText = null;
        savedValue = '';
        setStatus('Los cambios se guardan al pulsar Guardar.');
    };
    const openEditor = element => {
        if (dirty() && !window.confirm('Descartar el texto que estás editando y abrir otro?')) return;
        activeText = element;
        savedValue = element.textContent;
        label.textContent = element.dataset.inlineEditLabel || 'Texto';
        input.value = savedValue;
        panel.hidden = false;
        setStatus('Los cambios se guardan al pulsar Guardar.');
        save.disabled = true;
        requestAnimationFrame(() => {
            input.focus();
            input.setSelectionRange(input.value.length, input.value.length);
        });
    };

    launch.addEventListener('click', () => setEditing(body.dataset.inlineEditMode !== 'true'));
    document.addEventListener('click', event => {
        const target = event.target.closest('[data-inline-edit-key]');
        if (!target || body.dataset.inlineEditMode !== 'true') return;
        event.preventDefault();
        event.stopPropagation();
        openEditor(target);
    }, true);
    document.addEventListener('keydown', event => {
        if (body.dataset.inlineEditMode === 'true' && event.key === 'Escape') {
            if (!panel.hidden) { if (!dirty() || window.confirm('Descartar el texto sin guardar?')) closePanel(); } else setEditing(false);
        }
        const target = event.target.closest?.('[data-inline-edit-key]');
        if (target && body.dataset.inlineEditMode === 'true' && (event.key === 'Enter' || event.key === ' ')) {
            event.preventDefault();
            openEditor(target);
        }
        if ((event.metaKey || event.ctrlKey) && event.key === 'Enter' && !panel.hidden) {
            event.preventDefault();
            save.click();
        }
    });
    input.addEventListener('input', () => {
        save.disabled = !dirty();
        setStatus(dirty() ? 'Cambios pendientes de guardar.' : 'Sin cambios pendientes.', dirty() ? 'pending' : '');
    });
    closeButtons.forEach(button => button.addEventListener('click', () => {
        if (!dirty() || window.confirm('Descartar el texto sin guardar?')) closePanel();
    }));
    const renderText = (element, value) => {
        element.replaceChildren();
        value.split(/\r?\n/).forEach((line, index) => {
            if (index) element.append(document.createElement('br'));
            element.append(document.createTextNode(line));
        });
    };

    const mapNode = document.querySelector('#inline-editor-text-map');
    if (mapNode) {
        try {
            const values = Object.entries(JSON.parse(mapNode.textContent || '{}'))
                .filter(([, entry]) => entry && typeof entry.value === 'string' && entry.value.trim() !== '')
                .sort((a, b) => b[1].value.length - a[1].value.length);
            const normalize = value => value.replace(/\s+/g, ' ').trim();
            const enhanceText = root => {
                if (!root) return;
                const walker = document.createTreeWalker(root, NodeFilter.SHOW_TEXT);
                const nodes = [];
                while (walker.nextNode()) nodes.push(walker.currentNode);
                nodes.forEach(node => {
                    const parent = node.parentElement;
                    if (!parent || parent.closest('[data-inline-edit-key],script,style,textarea,input,select,option,svg,canvas,template')) return;
                    const text = normalize(node.nodeValue || '');
                    if (!text) return;
                    const entry = values.find(([, value]) => normalize(value.value) === text);
                    if (!entry) return;
                    const [key, definition] = entry;
                    const marker = document.createElement('span');
                    marker.className = 'inline-edit-text';
                    marker.dataset.inlineEditKey = key;
                    marker.dataset.inlineEditLabel = definition.label || 'Texto';
                    if (body.dataset.inlineEditMode === 'true') {
                        marker.tabIndex = 0;
                        marker.setAttribute('role', 'button');
                    }
                    node.parentNode.insertBefore(marker, node);
                    marker.append(node);
                });
            };
            enhanceText(body);
            new MutationObserver(records => records.forEach(record => record.addedNodes.forEach(node => {
                if (node.nodeType === Node.ELEMENT_NODE || node.nodeType === Node.TEXT_NODE) enhanceText(node);
            }))).observe(body, {childList: true, subtree: true});
        } catch (error) {
            console.error('No se pudo preparar la edición de textos de plantilla.', error);
        }
    }

    save.addEventListener('click', async () => {
        if (!activeText || !dirty() || save.disabled) return;
        save.disabled = true;
        input.disabled = true;
        setStatus('Guardando…', 'saving');
        try {
            const response = await fetch('/inline-edit.php', {
                method: 'POST',
                credentials: 'same-origin',
                headers: {'Content-Type': 'application/json', 'X-CSRF-Token': body.dataset.inlineEditorCsrf},
                body: JSON.stringify({key: activeText.dataset.inlineEditKey, value: input.value})
            });
            const result = await response.json();
            if (!response.ok || !result.ok) throw new Error(result.error || 'No se pudo guardar el texto.');
            const savedText = typeof result.value === 'string' ? result.value : input.value;
            renderText(activeText, savedText);
            savedValue = savedText;
            const savedElement = activeText;
            savedElement.classList.add('inline-edit-text--saved');
            setStatus('Guardado correctamente.', 'success');
            window.setTimeout(() => savedElement.classList.remove('inline-edit-text--saved'), 1200);
            save.disabled = true;
        } catch (error) {
            save.disabled = false;
            setStatus(error.message || 'Error al guardar. Vuelve a intentarlo.', 'error');
        } finally {
            input.disabled = false;
        }
    });
})();
