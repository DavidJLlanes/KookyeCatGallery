/*
 * Ajusta una foto demasiado grande al límite de subida del servidor, en el propio dispositivo.
 *
 *   const ready = await window.KcgImageFit.fit(file, 15 * 1024 * 1024);
 *
 * Si el archivo cabe, lo devuelve tal cual (con sus metadatos EXIF). Si no, devuelve una copia JPEG con el lado mayor de 4096 px como
 * máximo, reduciendo calidad y tamaño hasta que quepa. La copia se hace en un lienzo, así que pierde el EXIF (fecha y GPS): quien
 * llama puede leer antes lo que necesite. La web después genera sus versiones de 1800 px a partir de la que se envía.
 */
(() => {
    'use strict';

    const LONGEST_SIDE = 4096;

    const fit = async (file, maxBytes) => {
        if (file.size <= maxBytes) return file;
        let bitmap = null;
        let objectUrl = '';
        try {
            if ('createImageBitmap' in window) {
                bitmap = await createImageBitmap(file);
            } else {
                objectUrl = URL.createObjectURL(file);
                bitmap = new Image();
                bitmap.src = objectUrl;
                await bitmap.decode();
            }
            const scale = Math.min(1, LONGEST_SIDE / Math.max(bitmap.width, bitmap.height));
            let width = Math.max(1, Math.round(bitmap.width * scale));
            let height = Math.max(1, Math.round(bitmap.height * scale));
            const canvas = document.createElement('canvas');
            for (let attempt = 0; attempt < 12; attempt++) {
                canvas.width = width;
                canvas.height = height;
                const context = canvas.getContext('2d', { alpha: false });
                context.fillStyle = '#fff';
                context.fillRect(0, 0, width, height);
                context.drawImage(bitmap, 0, 0, width, height);
                const quality = Math.max(0.62, 0.92 - attempt * 0.035);
                const blob = await new Promise((resolve) => canvas.toBlob(resolve, 'image/jpeg', quality));
                if (blob && blob.size <= maxBytes - 128 * 1024) {
                    canvas.width = canvas.height = 1;
                    return new File([blob], file.name.replace(/\.[^.]+$/, '') + '.jpg', { type: 'image/jpeg', lastModified: file.lastModified });
                }
                width = Math.max(1, Math.round(width * 0.88));
                height = Math.max(1, Math.round(height * 0.88));
            }
            throw new Error('No se pudo optimizar la foto por debajo del límite de subida.');
        } finally {
            if (bitmap && typeof bitmap.close === 'function') bitmap.close();
            if (objectUrl) URL.revokeObjectURL(objectUrl);
        }
    };

    window.KcgImageFit = { fit, LONGEST_SIDE };
})();
