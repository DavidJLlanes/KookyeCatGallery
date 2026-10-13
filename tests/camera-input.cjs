const { chromium } = require('playwright');
const fs = require('fs');
const path = require('path');
const assert = require('node:assert/strict');

// «Usar cámara» (assets/js/camera-input.js): solo en dispositivos táctiles, con campo auxiliar propio (capture) que pasa la
// foto al campo original con su evento «change», sin dejar «capture» puesto en el campo de archivos normal.
(async () => {
    const root = path.join(__dirname, '..');
    const script = fs.readFileSync(path.join(root, 'assets/js/camera-input.js'), 'utf8');
    const html = '<!doctype html><html><body><label><input id="single" type="file" accept="image/jpeg,image/png" data-camera></label>' +
        '<input id="multi" type="file" accept="image/jpeg,image/png" multiple data-mb-max="3" data-camera>' +
        '<input id="selfie" type="file" data-camera="user"><input id="plain" type="file"></body></html>';
    const browser = await chromium.launch({ headless: true, args: ['--no-sandbox'] });
    try {
        // Escritorio (sin pantalla táctil): no se añade ningún botón.
        const desktop = await browser.newPage();
        await desktop.setContent(html);
        await desktop.addScriptTag({ content: script });
        assert.equal(await desktop.locator('.camera-input__button').count(), 0, 'Sin pantalla táctil no debe haber botón de cámara.');
        await desktop.close();

        // Móvil.
        const context = await browser.newContext({ hasTouch: true, isMobile: true, viewport: { width: 390, height: 800 } });
        const page = await context.newPage();
        const errors = [];
        page.on('pageerror', error => errors.push(error.message));
        await page.setContent(html);
        await page.addScriptTag({ content: script });
        assert.equal(await page.locator('.camera-input__button').count(), 3, 'Un botón por cada campo con data-camera.');
        assert.equal(await page.locator('#plain + .camera-input__actions').count(), 0, 'Un campo sin data-camera no recibe botón.');
        assert.equal(await page.locator('#single').getAttribute('capture'), null, 'El campo de archivos normal nunca lleva «capture».');
        assert.equal(await page.locator('#selfie + .camera-input__actions input').first().getAttribute('capture'), 'user', 'data-camera="user" usa la cámara frontal.');
        assert.equal(await page.locator('#single').evaluate(el => el.closest('label').nextElementSibling.classList.contains('camera-input__actions')), true, 'El botón va detrás de la etiqueta que envuelve el campo.');
        const proxy = page.locator('#multi + .camera-input__actions input');
        assert.equal(await proxy.getAttribute('capture'), 'environment');
        assert.equal(await proxy.getAttribute('accept'), 'image/jpeg,image/png', 'El campo de cámara copia el «accept» del original.');

        const png = Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==', 'base64');
        const take = async (id, count, name) => {
            await page.evaluate(selector => { window.__events = []; const input = document.querySelector(selector); input.addEventListener('change', () => window.__events.push(input.files.length)); }, id);
            const [chooser] = await Promise.all([page.waitForEvent('filechooser'), page.locator(`${id} + .camera-input__actions button`).click()]);
            await chooser.setFiles(Array.from({ length: count }, (_, i) => ({ name: `${name}${i}.png`, mimeType: 'image/png', buffer: png })));
            await page.waitForFunction(() => window.__events.length > 0);
            return page.evaluate(selector => Array.from(document.querySelector(selector).files).map(f => f.name), id);
        };

        // Un archivo: la foto llega al campo original y se dispara «change».
        await page.evaluate(() => { window.__events = []; const input = document.getElementById('single'); input.addEventListener('change', () => window.__events.push(input.files.length)); });
        const [chooserSingle] = await Promise.all([page.waitForEvent('filechooser'), page.locator('#single').evaluate(el => el.closest('label').nextElementSibling.querySelector('button').click())]);
        await chooserSingle.setFiles([{ name: 'camara.png', mimeType: 'image/png', buffer: png }]);
        await page.waitForFunction(() => window.__events.length > 0);
        assert.deepEqual(await page.evaluate(() => Array.from(document.getElementById('single').files).map(f => f.name)), ['camara.png']);

        // Varios: cada foto de cámara se añade a las que ya había y se respeta el máximo (data-mb-max).
        assert.deepEqual(await take('#multi', 1, 'a'), ['a0.png']);
        assert.deepEqual(await take('#multi', 1, 'b'), ['a0.png', 'b0.png']);
        assert.deepEqual(await take('#multi', 1, 'c'), ['a0.png', 'b0.png', 'c0.png']);
        assert.deepEqual(await take('#multi', 1, 'd'), ['a0.png', 'b0.png', 'c0.png'], 'No debe pasar del máximo de imágenes del campo.');
        assert.deepEqual(errors, [], 'Sin errores de JavaScript.');
        await context.close();
    } finally {
        await browser.close();
    }
    console.log('Camera input tests passed.');
})().catch(error => { console.error(error); process.exit(1); });
