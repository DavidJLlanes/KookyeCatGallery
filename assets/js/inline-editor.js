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
        launch.querySelector('span').textContent = enabled ? 'Salir de edición' : 'Editar página';
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
            activeText.textContent = input.value;
            savedValue = input.value;
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
