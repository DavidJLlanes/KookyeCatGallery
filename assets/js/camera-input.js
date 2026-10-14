/*
 * «Usar cámara» para cualquier campo de fotografías (admin, microblog, ajustes).
 *
 * Un <input type="file" data-camera> (o data-camera="user" para la cámara frontal) recibe un botón «Usar cámara» solo en
 * dispositivos táctiles. Al pulsarlo se abre la cámara con un campo auxiliar propio (type=file + capture) y la foto hecha
 * se pasa al campo original como si se hubiera elegido allí, con su evento «change»: el resto del código (recortador,
 * editor, vista previa) no necesita saber de dónde viene.
 *
 * Por qué un campo auxiliar y no poner «capture» en el original: en iPhone y en algunos Android el atributo cambiado en
 * caliente se ignora o se queda puesto y el siguiente «Elegir archivo» abre la cámara en vez de la galería.
 */
(() => {
    'use strict';

    const touch = () => (navigator.maxTouchPoints || 0) > 0 || (window.matchMedia && window.matchMedia('(pointer: coarse)').matches);
    const supported = typeof DataTransfer === 'function';

    const attach = (input) => {
        if (input.dataset.cameraReady || input.type !== 'file') return;
        input.dataset.cameraReady = '1';
        if (!supported || !touch()) return;

        // Campo auxiliar: mismo «accept» que el original (así iOS entrega JPEG y no HEIC) y capture según la cámara pedida.
        const camera = document.createElement('input');
        camera.type = 'file';
        camera.accept = input.accept || 'image/*';
        camera.setAttribute('capture', input.dataset.camera === 'user' ? 'user' : 'environment');
        camera.tabIndex = -1;
        camera.setAttribute('aria-hidden', 'true');
        camera.style.cssText = 'position:absolute;width:1px;height:1px;opacity:0;overflow:hidden;pointer-events:none';

        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'upload-logout camera-input__button';
        button.textContent = 'Usar cámara';
        button.addEventListener('click', () => camera.click());

        camera.addEventListener('change', () => {
            const taken = Array.from(camera.files || []);
            camera.value = '';
            if (!taken.length) return;
            const transfer = new DataTransfer();
            let room = 1;
            if (input.multiple) {
                Array.from(input.files || []).forEach((file) => transfer.items.add(file));
                const limit = parseInt(input.dataset.mbMax || '', 10);
                room = Number.isFinite(limit) ? Math.max(0, limit - transfer.items.length) : taken.length;
            }
            taken.slice(0, room).forEach((file) => transfer.items.add(file));
            input.files = transfer.files;
            input.dispatchEvent(new Event('input', { bubbles: true }));
            input.dispatchEvent(new Event('change', { bubbles: true }));
        });

        const actions = document.createElement('div');
        actions.className = 'upload-source-actions camera-input__actions';
        actions.append(button, camera);
        // Detrás del campo; si está dentro de una etiqueta («drop zone»), detrás de la etiqueta para no activar el campo de archivo.
        const anchor = input.closest('label') || input;
        anchor.insertAdjacentElement('afterend', actions);
    };

    const scan = (root = document) => root.querySelectorAll('input[type="file"][data-camera]').forEach(attach);
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', () => scan());
    else scan();
    // Formularios que se montan después (panel flotante del microblog).
    new MutationObserver((records) => records.forEach((record) => record.addedNodes.forEach((node) => {
        if (node.nodeType === 1) scan(node);
    }))).observe(document.documentElement, { childList: true, subtree: true });
})();
