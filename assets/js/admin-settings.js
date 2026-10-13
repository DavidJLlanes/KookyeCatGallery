/*
 * Editor de las páginas de texto del panel (aviso legal, privacidad, cookies y «El proyecto»), sobre Quill (assets/vendor/quill).
 *
 * La barra del formulario (inc/site-settings-form.php, site_page_editor_form) son controles Quill: aplican el formato a la selección,
 * lo quitan al pulsar de nuevo y muestran su estado (.ql-active) también en el móvil. El contenido sale como HTML semántico y se
 * vuelve a sanear en el servidor (site_sanitize_page_html()), que es quien manda sobre qué etiquetas y atributos se guardan.
 */
(() => {
    'use strict';

    const form = document.querySelector('[data-page-editor-form]');
    const surface = form?.querySelector('[data-page-editor]');
    const content = form?.querySelector('[data-page-content]');
    const toolbar = form?.querySelector('[data-editor-toolbar]');
    if (!form || !surface || !content || !toolbar || typeof window.Quill !== 'function') return;

    const Quill = window.Quill;
    const linkPanel = form.querySelector('[data-link-panel]');
    const linkInput = form.querySelector('[data-link-url]');
    const count = form.querySelector('[data-editor-count]');
    const previewButton = form.querySelector('[data-editor-preview]');

    // Alineación como estilo en línea (text-align), que es lo que admite el saneador; sin clases ql-align-*.
    Quill.register(Quill.import('attributors/style/align'), true);
    // Enlaces: http(s), mailto y rutas internas que empiezan por «/» (Quill por defecto descartaría estas últimas).
    const Link = Quill.import('formats/link');
    class SafeLink extends Link {
        static sanitize(url) {
            return /^\/(?!\/)\S*$/.test(url) ? url : super.sanitize(url);
        }
    }
    Quill.register(SafeLink, true);

    const quill = new Quill(surface, {
        modules: {
            toolbar: { container: toolbar, handlers: { link: value => (value ? openLinkPanel() : quill.format('link', false)) } },
            history: { delay: 600, maxStack: 200, userOnly: true },
        },
        formats: ['header', 'bold', 'italic', 'underline', 'strike', 'blockquote', 'list', 'align', 'link'],
    });

    // Contenido inicial: el de la caja oculta, que el servidor ya saneó.
    quill.setContents(quill.clipboard.convert({ html: content.value }), 'silent');
    quill.history.clear();

    const sync = () => {
        // Quill escribe cada espacio como &nbsp;; en una página pública eso impediría partir las líneas: se dejan espacios normales.
        content.value = quill.getSemanticHTML().replace(/&nbsp;/g, ' ');
        if (count) count.textContent = quill.getText().trim().length.toLocaleString('es-ES') + ' caracteres';
    };
    quill.on('text-change', sync);
    form.addEventListener('submit', sync);
    sync();

    // Deshacer / rehacer.
    toolbar.querySelectorAll('[data-editor-history]').forEach(button => {
        button.addEventListener('mousedown', event => event.preventDefault());
        button.addEventListener('click', () => quill.history[button.dataset.editorHistory === 'redo' ? 'redo' : 'undo']());
    });

    // Enlace: panel propio con validación; con texto seleccionado lo enlaza, sin selección inserta la dirección como texto.
    let linkRange = null;
    function openLinkPanel() {
        linkRange = quill.getSelection(true);
        const current = quill.getFormat(linkRange).link;
        linkInput.value = typeof current === 'string' ? current : '';
        linkPanel.hidden = false;
        linkInput.focus();
    }
    form.querySelector('[data-link-apply]')?.addEventListener('click', () => {
        const url = linkInput.value.trim();
        if (!/^(https?:\/\/|mailto:|\/(?!\/))\S*$/i.test(url)) {
            linkInput.setCustomValidity('Usa una URL https://, mailto: o una ruta interna que empiece por /.');
            linkInput.reportValidity();
            return;
        }
        linkInput.setCustomValidity('');
        const range = linkRange || quill.getSelection(true);
        if (range.length > 0) {
            quill.formatText(range.index, range.length, 'link', url, 'user');
        } else {
            quill.insertText(range.index, url, { link: url }, 'user');
            quill.setSelection(range.index + url.length, 0, 'silent');
        }
        linkPanel.hidden = true;
        quill.focus();
    });
    linkInput?.addEventListener('input', () => linkInput.setCustomValidity(''));
    linkInput?.addEventListener('keydown', event => {
        if (event.key === 'Enter') { event.preventDefault(); form.querySelector('[data-link-apply]')?.click(); }
        if (event.key === 'Escape') { linkPanel.hidden = true; quill.focus(); }
    });
    form.querySelector('[data-link-cancel]')?.addEventListener('click', () => {
        linkPanel.hidden = true;
        quill.focus();
    });

    // Vista previa: el mismo contenido sin poder editarlo.
    previewButton?.addEventListener('click', () => {
        const preview = quill.isEnabled();
        quill.enable(!preview);
        surface.dataset.preview = preview ? 'true' : 'false';
        previewButton.setAttribute('aria-pressed', String(preview));
        previewButton.textContent = preview ? 'Volver a editar' : 'Vista previa';
        toolbar.querySelectorAll('button:not([data-editor-preview]), select').forEach(control => { control.disabled = preview; });
        surface.setAttribute('aria-label', preview ? 'Vista previa del contenido' : 'Contenido editable');
    });
})();
