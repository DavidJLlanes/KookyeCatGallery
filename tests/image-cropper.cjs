const { chromium } = require('playwright');
const fs = require('fs');
const path = require('path');
const assert = require('node:assert/strict');

// Recortador de imágenes del panel (assets/js/image-cropper.js): la imagen elegida se copia a memoria (si el navegador la leyera al enviar
// el formulario y el archivo se hubiera movido o estuviera en la nube, saldría ERR_FILE_NOT_FOUND), se reduce si es enorme y se recorta
// cuando no tiene la proporción pedida.
(async () => {
    const root = path.join(__dirname, '..');
    const script = fs.readFileSync(path.join(root, 'assets/js/image-cropper.js'), 'utf8');
    const html = '<!doctype html><html><body><form><input id="square" type="file" data-crop-ratio="1" data-crop-label="1:1"></form></body></html>';
    const browser = await chromium.launch({ headless: true, args: ['--no-sandbox'] });
    try {
        const page = await browser.newPage();
        const errors = [];
        page.on('pageerror', error => errors.push(error.message));
        await page.setContent(html);
        await page.addScriptTag({ content: script });

        // Genera una imagen PNG de w×h en el navegador y la pone en el campo como si la hubiera elegido el usuario.
        const choose = (w, h, name) => page.evaluate(async ({ w, h, name }) => {
            const canvas = document.createElement('canvas');
            canvas.width = w; canvas.height = h;
            const context = canvas.getContext('2d');
            context.fillStyle = '#c87832'; context.fillRect(0, 0, w, h);
            const blob = await new Promise(resolve => canvas.toBlob(resolve, 'image/png'));
            const transfer = new DataTransfer();
            transfer.items.add(new File([blob], name, { type: 'image/png' }));
            const input = document.getElementById('square');
            input.files = transfer.files;
            input.dispatchEvent(new Event('change', { bubbles: true }));
        }, { w, h, name });
        const current = () => page.evaluate(async () => {
            const file = document.getElementById('square').files[0];
            if (!file) return null;
            const bitmap = await createImageBitmap(file);
            return { name: file.name, width: bitmap.width, height: bitmap.height, type: file.type };
        });

        // 1. Proporción correcta y tamaño normal: se queda igual (copiada a memoria).
        await choose(600, 600, 'cuadrada.png');
        await page.waitForFunction(() => document.getElementById('square').files.length === 1);
        assert.deepEqual(await current(), { name: 'cuadrada.png', width: 600, height: 600, type: 'image/png' });
        assert.equal(await page.locator('.cropper').count(), 0, 'Con la proporción correcta no se abre el recortador.');

        // 2. Proporción correcta pero enorme: se reduce a 2400 px.
        await choose(3600, 3600, 'enorme.png');
        for (let attempt = 0; attempt < 40 && (await current())?.width !== 2400; attempt++) await page.waitForTimeout(250);
        assert.deepEqual(await current(), { name: 'enorme.png', width: 2400, height: 2400, type: 'image/png' });

        // 3. Proporción distinta: se abre el recortador y el resultado es cuadrado.
        await choose(1000, 500, 'apaisada.png');
        await page.waitForSelector('.cropper');
        await page.locator('.cropper .upload-submit').click();
        await page.waitForSelector('.cropper', { state: 'detached' });
        const cropped = await current();
        assert.equal(cropped.width, cropped.height, 'El recorte es cuadrado.');
        assert.equal(cropped.name, 'apaisada-recortada.png');

        // 4. Archivo que el navegador ya no puede leer (movido, en la nube sin descargar): avisa y vacía el campo, no falla al enviar.
        const dialogs = [];
        page.on('dialog', dialog => { dialogs.push(dialog.message()); dialog.accept(); });
        await page.evaluate(() => {
            const original = File.prototype.arrayBuffer;
            File.prototype.arrayBuffer = function () { File.prototype.arrayBuffer = original; return Promise.reject(new DOMException('no encontrado', 'NotFoundError')); };
        });
        await choose(300, 300, 'perdida.png');
        await page.waitForFunction(() => document.getElementById('square').files.length === 0);
        assert.equal(dialogs.length, 1, 'Se avisa al usuario.');
        assert.match(dialogs[0], /No se ha podido leer el archivo/);

        assert.deepEqual(errors, [], 'Sin errores de JavaScript.');
    } finally {
        await browser.close();
    }
    console.log('Image cropper tests passed.');
})().catch(error => { console.error(error.message || error); process.exit(1); });
