/*
 * Recortador de imágenes para los campos de subida del panel (Ajustes → Contenido).
 *
 * Un <input type="file" data-crop-ratio="1"> abre el recortador al elegir una imagen cuya proporción no coincide con
 * la pedida (ancho/alto, p. ej. 1 o 1.91). Se arrastra para encuadrar y se acerca con el deslizador o la rueda. Al
 * aceptar, el archivo recortado sustituye al elegido en el propio campo y se envía con el formulario como siempre.
 * data-crop-label="4:5" se muestra en el recortador. data-crop-min="512" fija el lado menor que debe tener el resultado (si hace falta, se amplía).
 */
(() => {
    'use strict';

    const TOLERANCE = 0.02;
    const MAX_SIDE = 2400;
    const VIEW = 320;

    const el = (tag, className, text) => {
        const node = document.createElement(tag);
        if (className) node.className = className;
        if (text) node.textContent = text;
        return node;
    };

    const open = (file, image, ratio, minSide, label) => new Promise((resolve) => {
        const viewW = ratio >= 1 ? VIEW : Math.round(VIEW * ratio);
        const viewH = ratio >= 1 ? Math.round(VIEW / ratio) : VIEW;
        const overlay = el('div', 'cropper');
        overlay.setAttribute('role', 'dialog');
        overlay.setAttribute('aria-modal', 'true');
        overlay.setAttribute('aria-label', 'Recortar imagen');
        const box = el('div', 'cropper__box');
        const hint = el('p', 'cropper__hint', (label ? 'Proporción ' + label + '. ' : '') + 'Arrastra para encuadrar y usa el deslizador para acercar.');
        const stage = el('div', 'cropper__stage');
        stage.style.width = viewW + 'px';
        stage.style.height = viewH + 'px';
        const canvas = el('canvas');
        canvas.width = viewW;
        canvas.height = viewH;
        stage.append(canvas);
        const zoom = el('input', 'cropper__zoom');
        zoom.type = 'range';
        zoom.min = '1';
        zoom.max = '4';
        zoom.step = '0.01';
        zoom.value = '1';
        zoom.setAttribute('aria-label', 'Zoom');
        const note = el('p', 'cropper__note');
        const actions = el('div', 'cropper__actions');
        const cancel = el('button', 'upload-logout', 'Cancelar');
        cancel.type = 'button';
        const accept = el('button', 'upload-submit', 'Recortar y usar');
        accept.type = 'button';
        actions.append(cancel, accept);
        box.append(hint, stage, zoom, note, actions);
        overlay.append(box);
        document.body.append(overlay);

        // Escala mínima: la imagen cubre el marco entero. offset = esquina superior izquierda de la imagen dentro del marco.
        const cover = Math.max(viewW / image.naturalWidth, viewH / image.naturalHeight);
        let scale = cover;
        let x = (viewW - image.naturalWidth * scale) / 2;
        let y = (viewH - image.naturalHeight * scale) / 2;

        const clamp = () => {
            x = Math.min(0, Math.max(viewW - image.naturalWidth * scale, x));
            y = Math.min(0, Math.max(viewH - image.naturalHeight * scale, y));
        };
        const draw = () => {
            clamp();
            const context = canvas.getContext('2d');
            context.clearRect(0, 0, viewW, viewH);
            context.drawImage(image, x, y, image.naturalWidth * scale, image.naturalHeight * scale);
            const cropW = Math.round(viewW / scale);
            const cropH = Math.round(viewH / scale);
            note.textContent = 'Recorte: ' + cropW + ' × ' + cropH + ' px' + (minSide && Math.min(cropW, cropH) < minSide ? ' (se ampliará a ' + minSide + ' px de lado menor)' : '');
        };
        const setZoom = (value, cx = viewW / 2, cy = viewH / 2) => {
            const next = cover * Math.min(4, Math.max(1, value));
            const px = (cx - x) / scale;
            const py = (cy - y) / scale;
            scale = next;
            x = cx - px * scale;
            y = cy - py * scale;
            zoom.value = String(scale / cover);
            draw();
        };

        let drag = null;
        canvas.addEventListener('pointerdown', (event) => {
            drag = { id: event.pointerId, x: event.clientX, y: event.clientY, ox: x, oy: y };
            canvas.setPointerCapture(event.pointerId);
        });
        canvas.addEventListener('pointermove', (event) => {
            if (!drag || drag.id !== event.pointerId) return;
            x = drag.ox + event.clientX - drag.x;
            y = drag.oy + event.clientY - drag.y;
            draw();
        });
        const end = (event) => { if (drag && drag.id === event.pointerId) drag = null; };
        canvas.addEventListener('pointerup', end);
        canvas.addEventListener('pointercancel', end);
        canvas.addEventListener('wheel', (event) => {
            event.preventDefault();
            const rect = canvas.getBoundingClientRect();
            setZoom(scale / cover * (event.deltaY < 0 ? 1.08 : 0.92), event.clientX - rect.left, event.clientY - rect.top);
        }, { passive: false });
        zoom.addEventListener('input', () => setZoom(parseFloat(zoom.value)));

        const close = (result) => {
            document.removeEventListener('keydown', onKey);
            overlay.remove();
            resolve(result);
        };
        const onKey = (event) => { if (event.key === 'Escape') close(null); };
        document.addEventListener('keydown', onKey);
        cancel.addEventListener('click', () => close(null));
        accept.addEventListener('click', () => {
            const sx = -x / scale;
            const sy = -y / scale;
            const sw = viewW / scale;
            const sh = viewH / scale;
            let outW = Math.round(sw);
            let outH = Math.round(sh);
            const limit = Math.min(1, MAX_SIDE / Math.max(outW, outH));
            outW = Math.round(outW * limit);
            outH = Math.round(outH * limit);
            if (minSide && Math.min(outW, outH) < minSide) {
                const up = minSide / Math.min(outW, outH);
                outW = Math.round(outW * up);
                outH = Math.round(outH * up);
            }
            const out = document.createElement('canvas');
            out.width = outW;
            out.height = outH;
            const context = out.getContext('2d');
            context.imageSmoothingQuality = 'high';
            context.drawImage(image, sx, sy, sw, sh, 0, 0, outW, outH);
            const type = file.type === 'image/png' || file.type === 'image/webp' ? file.type : 'image/jpeg';
            out.toBlob((blob) => {
                if (!blob) { close(null); return; }
                const extension = type === 'image/png' ? 'png' : type === 'image/webp' ? 'webp' : 'jpg';
                const base = (file.name || 'imagen').replace(/\.[^.]+$/, '');
                close(new File([blob], base + '-recortada.' + extension, { type }));
            }, type, 0.92);
        });

        setZoom(1);
        accept.focus();
    });

    const handle = async (input) => {
        const file = input.files && input.files[0];
        if (!file || !/^image\/(jpeg|png|webp)$/.test(file.type)) return;
        const ratio = parseFloat(input.dataset.cropRatio || '1') || 1;
        const minSide = parseInt(input.dataset.cropMin || '0', 10) || 0;
        const url = URL.createObjectURL(file);
        try {
            const image = new Image();
            image.decoding = 'async';
            image.src = url;
            await image.decode();
            const current = image.naturalWidth / image.naturalHeight;
            if (Math.abs(current - ratio) / ratio <= TOLERANCE && (!minSide || Math.min(image.naturalWidth, image.naturalHeight) >= minSide)) return;
            const cropped = await open(file, image, ratio, minSide, input.dataset.cropLabel || '');
            if (cropped) {
                const transfer = new DataTransfer();
                transfer.items.add(cropped);
                input.files = transfer.files;
                input.dispatchEvent(new CustomEvent('cropper:done', { bubbles: true, detail: { file: cropped } }));
            } else {
                input.value = '';
            }
        } catch (error) {
            // Si el navegador no puede decodificar la imagen, se envía tal cual y el servidor decide.
        } finally {
            URL.revokeObjectURL(url);
        }
    };

    document.addEventListener('change', (event) => {
        const input = event.target;
        if (input instanceof HTMLInputElement && input.type === 'file' && input.dataset.cropRatio) handle(input);
    });
})();
