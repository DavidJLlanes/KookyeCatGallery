// La presentación («▶ Presentación») debe abrirse, mostrar la primera foto y avanzar sin errores de JS.
const { chromium } = require('playwright');
const fs = require('fs');
const path = require('path');
const assert = require('node:assert/strict');

(async () => {
    const root = path.join(__dirname, '..');
    const browser = await chromium.launch({ headless: true, executablePath: process.env.TEST_BROWSER || undefined, args: ['--no-sandbox'] });
    try {
        const page = await browser.newPage({ viewport: { width: 1280, height: 900 } });
        const errors = [];
        page.on('pageerror', error => errors.push(error.message));
        const png = Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==', 'base64');
        await page.route('**/*', route => {
            const url = route.request().url();
            if (url.endsWith('.webp') || url.endsWith('.jpg')) return route.fulfill({ status: 200, contentType: 'image/png', body: png });
            if (url === 'https://example.test/') {
                const cards = [1, 2, 3].map(n => `<article class="card is-revealed" data-slug="foto-${n}" data-title="Foto ${n}" data-full="/img/foto-${n}.jpg" data-category="A">` +
                    `<a class="card__btn" href="/foto/foto-${n}"><picture class="card__picture"><img class="card__img" alt="" src="/img/foto-${n}.jpg"></picture></a></article>`).join('');
                return route.fulfill({ status: 200, contentType: 'text/html', body: '<!doctype html><html lang="es"><body data-gallery-mobile="standard" data-gallery-desktop="standard">' +
                    `<main id="galeria"><section id="masonry">${cards}</section><div class="gallery-app-tools"><button id="slideshowStart" type="button">▶ Presentación</button></div></main></body></html>` });
            }
            return route.fulfill({ status: 200, contentType: 'text/plain', body: '' });
        });
        await page.goto('https://example.test/');
        await page.addScriptTag({ content: fs.readFileSync(path.join(root, 'assets/js/site-texts.js'), 'utf8') });
        await page.addScriptTag({ content: fs.readFileSync(path.join(root, 'assets/js/main.js'), 'utf8') });
        await page.locator('#slideshowStart').click();
        await page.waitForSelector('.slideshow-viewer__img');
        assert.match(await page.locator('.slideshow-viewer__img').getAttribute('src'), /foto-1\.jpg$/, 'The slideshow must show the first photo');
        assert.equal(await page.locator('.slideshow-viewer__caption').textContent(), 'Foto 1');
        await page.locator('[data-slide-next]').click();
        assert.match(await page.locator('.slideshow-viewer__img').getAttribute('src'), /foto-2\.jpg$/, 'Next must show the second photo');
        await page.locator('.slideshow-viewer__close').click();
        assert.equal(await page.locator('.slideshow-viewer').count(), 0, 'The slideshow must close');
        assert.deepEqual(errors, [], 'No JavaScript errors: ' + errors.join(' | '));
        console.log('Slideshow passed: opens, shows the first photo, advances and closes without errors.');
    } finally { await browser.close(); }
})().catch(error => { console.error(error); process.exit(1); });
