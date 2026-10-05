(() => {
    'use strict';

    const form = document.querySelector('[data-page-editor-form]');
    const editor = form?.querySelector('[data-page-editor]');
    const content = form?.querySelector('[data-page-content]');
    const toolbar = form?.querySelector('[data-editor-toolbar]');
    if (!form || !editor || !content || !toolbar) return;

    let savedRange = null;
    const linkPanel = form.querySelector('[data-link-panel]');
    const linkInput = form.querySelector('[data-link-url]');
    const count = form.querySelector('[data-editor-count]');
    const previewButton = form.querySelector('[data-editor-preview]');

    const saveSelection = () => {
        const selection = window.getSelection();
        if (selection?.rangeCount && editor.contains(selection.anchorNode)) {
            savedRange = selection.getRangeAt(0).cloneRange();
        }
    };
    const restoreSelection = () => {
        editor.focus();
        if (!savedRange) return;
        const selection = window.getSelection();
        selection.removeAllRanges();
        selection.addRange(savedRange);
    };
    const sync = () => {
        content.value = editor.innerHTML;
        if (count) count.textContent = editor.textContent.trim().length.toLocaleString('es-ES') + ' caracteres';
    };
    const run = (command, value = null) => {
        restoreSelection();
        document.execCommand(command, false, value);
        saveSelection();
        sync();
    };

    editor.addEventListener('input', sync);
    editor.addEventListener('keyup', saveSelection);
    editor.addEventListener('mouseup', saveSelection);
    editor.addEventListener('focus', saveSelection);
    form.addEventListener('submit', () => {
        editor.contentEditable = 'true';
        sync();
    });

    toolbar.querySelectorAll('[data-editor-command]').forEach(button => {
        button.addEventListener('mousedown', event => event.preventDefault());
        button.addEventListener('click', () => {
            const [command, value] = button.dataset.editorCommand.split(':');
            if (command === 'createLink') {
                saveSelection();
                linkPanel.hidden = false;
                linkInput.value = '';
                linkInput.focus();
                return;
            }
            run(command, value || null);
        });
    });

    toolbar.querySelector('[data-editor-block]')?.addEventListener('change', event => {
        if (event.target.value) run('formatBlock', event.target.value);
        event.target.value = '';
    });

    form.querySelector('[data-link-apply]')?.addEventListener('click', () => {
        const url = linkInput.value.trim();
        if (!/^(https?:\/\/|mailto:|\/(?!\/))\S*$/i.test(url)) {
            linkInput.setCustomValidity('Usa una URL https://, mailto: o una ruta interna que empiece por /.');
            linkInput.reportValidity();
            return;
        }
        linkInput.setCustomValidity('');
        restoreSelection();
        const selection = window.getSelection();
        if (selection?.rangeCount && !selection.isCollapsed && editor.contains(selection.anchorNode)) {
            document.execCommand('createLink', false, url);
            editor.querySelectorAll('a').forEach(anchor => {
                if (anchor.getAttribute('href') === url) anchor.setAttribute('rel', 'noopener noreferrer');
            });
        } else {
            const anchor = document.createElement('a');
            anchor.href = url;
            anchor.rel = 'noopener noreferrer';
            anchor.textContent = url;
            const range = selection?.rangeCount ? selection.getRangeAt(0) : document.createRange();
            range.insertNode(anchor);
            range.setStartAfter(anchor);
            range.collapse(true);
            selection?.removeAllRanges();
            selection?.addRange(range);
        }
        linkPanel.hidden = true;
        saveSelection();
        sync();
    });
    linkInput?.addEventListener('input', () => linkInput.setCustomValidity(''));
    form.querySelector('[data-link-cancel]')?.addEventListener('click', () => {
        linkPanel.hidden = true;
        editor.focus();
    });

    previewButton?.addEventListener('click', () => {
        const preview = editor.contentEditable === 'true';
        editor.contentEditable = preview ? 'false' : 'true';
        editor.dataset.preview = preview ? 'true' : 'false';
        previewButton.setAttribute('aria-pressed', String(preview));
        previewButton.textContent = preview ? 'Volver a editar' : 'Vista previa';
        editor.setAttribute('aria-label', preview ? 'Vista previa del contenido' : 'Contenido editable');
    });

    sync();
})();