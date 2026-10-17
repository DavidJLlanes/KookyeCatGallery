const { chromium } = require('playwright');
const fs = require('fs');
const path = require('path');
const assert = require('node:assert/strict');

// Editor en vivo: un texto que se ha borrado sigue teniendo hueco (con su nombre como pista) en el modo de edición, para poder volver a
// escribirlo; fuera de ese modo no se ve nada.
(async () => {
    const root = path.join(__dirname, '..');
    const css = fs.readFileSync(path.join(root, 'assets/css/inline-editor.css'), 'utf8');
    const html = '<!doctype html><html><body data-inline-edit-mode="false"><p id="lleno"><span class="inline-edit-text" data-inline-edit-key="a" data-inline-edit-label="Texto A">Hola</span></p>'
        + '<p id="vacio"><span id="hueco" class="inline-edit-text" data-inline-edit-key="b" data-inline-edit-label="Región"></span></p></body></html>';
    const browser = await chromium.launch({ headless: true, args: ['--no-sandbox'] });
    try {
        const page = await browser.newPage();
        await page.setContent(html);
        await page.addStyleTag({ content: css });
        const box = () => page.locator('#hueco').evaluate(el => { const r = el.getBoundingClientRect(); return { w: r.width, h: r.height, before: getComputedStyle(el, '::before').content }; });
        let measured = await box();
        assert.equal(measured.w, 0, 'Fuera del modo de edición, un texto vacío no ocupa sitio.');
        await page.evaluate(() => { document.body.dataset.inlineEditMode = 'true'; });
        measured = await box();
        assert.ok(measured.w > 20 && measured.h > 8, `En modo de edición el hueco vacío se puede pulsar (${measured.w}×${measured.h}).`);
        assert.match(measured.before, /Región/, 'El hueco muestra su nombre como pista.');
        await page.evaluate(() => { document.getElementById('hueco').textContent = 'tu región'; });
        measured = await box();
        assert.equal(measured.before, 'none', 'Con texto, la pista desaparece.');
    } finally {
        await browser.close();
    }
    console.log('Inline editor empty-text tests passed.');
})().catch(error => { console.error(error.message || error); process.exit(1); });
