const { chromium } = require('playwright');
const { execFileSync } = require('child_process');
const fs = require('fs');
const os = require('os');
const path = require('path');
const zlib = require('zlib');
const assert = require('assert/strict');

// Local fixtures and route interception exercise the actual PHP and JS without external services.
function testImage() {
    const crc = (bytes) => {
        let value = 0xffffffff;
        for (const byte of bytes) {
            value ^= byte;
            for (let i = 0; i < 8; i++) value = (value >>> 1) ^ ((value & 1) ? 0xedb88320 : 0);
        }
        return (value ^ 0xffffffff) >>> 0;
    };
    const chunk = (type, data) => {
        const bytes = Buffer.concat([Buffer.from(type), data]);
        const size = Buffer.alloc(4); size.writeUInt32BE(data.length);
        const checksum = Buffer.alloc(4); checksum.writeUInt32BE(crc(bytes));
        return Buffer.concat([size, bytes, checksum]);
    };
    const header = Buffer.alloc(13);
    header.writeUInt32BE(640, 0); header.writeUInt32BE(400, 4); header[8] = 8; header[9] = 2;
    const pixels = Buffer.alloc((640 * 3 + 1) * 400, 150);
    for (let i = 0; i < 400; i++) pixels[i * (640 * 3 + 1)] = 0;
    return Buffer.concat([Buffer.from('89504e470d0a1a0a', 'hex'), chunk('IHDR', header), chunk('IDAT', zlib.deflateSync(pixels)), chunk('IEND', Buffer.alloc(0))]);
}

(async () => {
    const root = path.join(__dirname, '..');
    const snapshot = fs.mkdtempSync(path.join(os.tmpdir(), 'photo-navigation-'));
    let browser;
    try {
        fs.cpSync(root, snapshot, { recursive: true, filter: (source) => !/(?:^|\/)(?:\.git|node_modules)(?:\/|$)/.test(source) });
        for (const directory of ['img', 'imagenes/desktop', 'imagenes/mobile', 'data']) fs.mkdirSync(path.join(snapshot, directory), { recursive: true });
        const image = testImage();
        const cache = {};
        for (const [i, slug] of ['first', 'middle', 'last'].entries()) {
            const filename = `${slug}.png`;
            const original = path.join(snapshot, 'img', filename);
            fs.writeFileSync(original, image);
            const mtime = Math.floor(Date.now() / 1000) - i * 10;
            fs.utimesSync(original, mtime, mtime);
            for (const size of ['desktop', 'mobile']) fs.writeFileSync(path.join(snapshot, 'imagenes', size, `${slug}.webp`), image);
            fs.writeFileSync(path.join(snapshot, 'img', `${slug}.txt`), `${slug}\n---\nDescription ${slug}\n# Slug: ${slug}\n# Categoría: Test\n`);
            cache[filename] = { mtime, edit_mtime: 0, edit_size: 0, desktop_w: 640, desktop_h: 400, mobile_w: 640, mobile_h: 400, aspect: 1.6 };
        }
        fs.writeFileSync(path.join(snapshot, 'data/cache.json'), JSON.stringify(cache));
        const render = (url) => execFileSync('php', ['-r', '$_SERVER["REQUEST_URI"]=$argv[1]; require "index.php";', url], { cwd: snapshot, encoding: 'utf8' });
        browser = await chromium.launch({ headless: true });
        const origin = 'http://photo.test';
        const makePage = async (options) => {
            const context = await browser.newContext(options);
            const page = await context.newPage();
            const errors = [];
            page.on('pageerror', (error) => errors.push(error.message));
            await page.route('**/*', async (route) => {
                const url = new URL(route.request().url());
                if (url.origin !== origin) return route.abort();
                if (url.pathname.startsWith('/foto/') || url.pathname === '/') return route.fulfill({ contentType: 'text/html', body: render(url.pathname + url.search) });
                const file = path.join(snapshot, url.pathname);
                if (!fs.existsSync(file) || !fs.statSync(file).isFile()) return route.fulfill({ status: 404, body: '' });
                const contentType = file.endsWith('.css') ? 'text/css' : file.endsWith('.js') ? 'application/javascript' : file.endsWith('.webp') ? 'image/png' : 'application/octet-stream';
                return route.fulfill({ contentType, body: fs.readFileSync(file) });
            });
            return { context, page, errors };
        };
        const waitSlug = async (page, slug) => {
            await page.waitForFunction(expected => document.querySelector('.photo-detail__title')?.textContent === expected, slug);
        };
        // Muestrea cada fotograma mientras dura `action`: devuelve la menor opacidad máxima de las imágenes del visor (si baja de 1 se vería el fondo).
        const sampleViewer = async (page, action) => {
            await page.evaluate(() => {
                window.__samples = []; window.__sampling = true;
                const tick = () => {
                    if (!window.__sampling) return;
                    const imgs = [...document.querySelectorAll('#lightbox .lightbox__img')];
                    const lightbox = document.getElementById('lightbox');
                    window.__samples.push({max: Math.max(...imgs.map(i => parseFloat(getComputedStyle(i).opacity))), layers: imgs.length,
                        open: lightbox.classList.contains('is-open'), bg: getComputedStyle(lightbox).backgroundColor});
                    requestAnimationFrame(tick);
                };
                tick();
            });
            await action();
            await page.waitForTimeout(900);
            return page.evaluate(() => { window.__sampling = false; return window.__samples; });
        };

        // 1. Con movimiento (sin «reducir movimiento»): el visor a pantalla completa cambia de foto sin recargar y sin dejar ver el fondo.
        const desktop = await makePage({ viewport: { width: 1280, height: 900 } });
        await desktop.page.goto(`${origin}/foto/middle`);
        await desktop.page.evaluate(() => { document.fullscreenEnabled = false; document.getElementById('lightbox').requestFullscreen = undefined; window.__marker = 'mismo-documento'; });
        await desktop.page.locator('.photo-detail__zoom').click();
        await desktop.page.waitForFunction(() => document.querySelector('.lightbox__img.is-shown'));
        await desktop.page.waitForTimeout(700);
        const baseBefore = await desktop.page.locator('.lightbox__img').first().getAttribute('src');
        const samples = await sampleViewer(desktop.page, () => desktop.page.locator('.lightbox__nav--next').click());
        assert(samples.length > 20, `Se deben muestrear fotogramas (${samples.length})`);
        const worst = Math.min(...samples.map(sample => sample.max));
        assert(worst >= 0.99, `Al cambiar de foto en pantalla completa siempre debe haber una foto a opacidad completa (peor fotograma: ${worst.toFixed(2)})`);
        assert(samples.every(sample => sample.open), 'El visor no debe cerrarse al cambiar de foto');
        assert(samples.some(sample => sample.layers === 2), 'La foto nueva debe fundirse sobre la anterior (dos capas durante el cambio)');
        await waitSlug(desktop.page, 'last');
        assert.equal(await desktop.page.evaluate(() => window.__marker), 'mismo-documento', 'No debe recargarse la página al cambiar de foto');
        assert.equal(new URL(desktop.page.url()).pathname, '/foto/last');
        assert.equal(new URL(desktop.page.url()).searchParams.get('viewer'), '1');
        await desktop.page.waitForFunction(() => document.querySelectorAll('#lightbox .lightbox__img').length === 1);
        const baseAfter = await desktop.page.locator('.lightbox__img').first().getAttribute('src');
        assert.notEqual(baseAfter, baseBefore, 'La foto base del visor debe ser la nueva');
        assert.match(baseAfter, /last/, 'La foto del visor es la de la nueva ficha');
        assert.equal(await desktop.page.locator('.lightbox__nav--next').isDisabled(), true, 'En la última foto, «siguiente» se desactiva');
        assert.equal(await desktop.page.locator('link[rel="canonical"]').getAttribute('href'), 'https://example.com/foto/last', 'Se actualiza la dirección canónica');
        assert.equal(await desktop.page.title().then(t => t.includes('last')), true, 'Se actualiza el título de la pestaña');

        // 2. Varias pulsaciones seguidas con el teclado: terminan en la foto correcta, sin recargar y sin huecos.
        const rapid = await sampleViewer(desktop.page, async () => {
            await desktop.page.keyboard.press('ArrowLeft');
            await desktop.page.keyboard.press('ArrowLeft');
            await desktop.page.waitForTimeout(100);
            await desktop.page.keyboard.press('ArrowRight');
        });
        // «Izquierda, izquierda, derecha»: la primera se hace, la segunda se sustituye por la última pulsación (derecha) y se vuelve a «last».
        await waitSlug(desktop.page, 'last');
        await desktop.page.waitForTimeout(600);
        assert(Math.min(...rapid.map(sample => sample.max)) >= 0.99, 'Pulsaciones rápidas: nunca debe verse el fondo');
        assert.equal(await desktop.page.evaluate(() => window.__marker), 'mismo-documento');
        assert.equal(await desktop.page.locator('.photo-detail__title').textContent(), 'last');
        assert.equal(await desktop.page.locator('#lightbox .lightbox__img').count(), 1, 'No quedan capas de fundido sueltas');

        // 3. Atrás del navegador: vuelve a la foto anterior, sin recargar, y mantiene el visor abierto.
        await desktop.page.goBack();
        await waitSlug(desktop.page, 'middle');
        await desktop.page.waitForTimeout(800);
        assert.equal(await desktop.page.locator('.lightbox__img').first().getAttribute('src').then(src => /middle/.test(src)), true, 'Atrás muestra la foto anterior en el visor');
        assert.equal(await desktop.page.evaluate(() => window.__marker), 'mismo-documento', 'Atrás no debe recargar la página');
        assert.equal(await desktop.page.locator('#lightbox').getAttribute('aria-hidden'), 'false', 'Atrás mantiene el visor abierto');

        // 4. Cerrar el visor: la ficha de debajo ya es la de la foto actual (título, imagen, flechas y datos).
        await desktop.page.locator('.lightbox__close').click();
        const shown = await desktop.page.evaluate(() => ({title: document.querySelector('.photo-detail__title').textContent, image: document.querySelector('.photo-detail__img').getAttribute('src'),
            slug: document.querySelector('[data-zoom-slug]').dataset.zoomSlug, nextDisabled: document.querySelector('.photo-detail__nav--next').getAttribute('aria-disabled')}));
        assert.equal(shown.title, 'middle');
        assert.match(shown.image, /middle/);
        assert.equal(shown.slug, 'middle');
        assert.equal(shown.nextDisabled, null);

        // 5. Flechas de la ficha (sin visor): también cambian en el sitio y siguen siendo enlaces normales (abrir en otra pestaña).
        assert.equal(await desktop.page.locator('a.photo-detail__nav--previous').getAttribute('href'), '/foto/first');
        await desktop.page.locator('a.photo-detail__nav--previous').click();
        await waitSlug(desktop.page, 'first');
        assert.equal(await desktop.page.evaluate(() => window.__marker), 'mismo-documento', 'Las flechas de la ficha tampoco recargan');
        assert.equal(await desktop.page.locator('#lightbox').getAttribute('aria-hidden'), 'true');
        assert.equal(await desktop.page.locator('.photo-detail__img').getAttribute('src').then(src => /first/.test(src)), true);

        // 6. Recargar con el visor abierto (?viewer=1): lo de detrás no se ve y el visor se abre.
        await desktop.page.goto(`${origin}/foto/middle?viewer=1`);
        await desktop.page.waitForFunction(() => document.querySelector('#lightbox.is-open .lightbox__img.is-shown'));
        assert.equal(await desktop.page.evaluate(() => document.documentElement.classList.contains('viewer-pending')), false, 'El estado pendiente se quita al abrir el visor');

        // 7. Móvil: el gesto de deslizar en pantalla completa cambia de foto con fundido y sin recargar.
        const mobile = await makePage({ viewport: { width: 390, height: 844 }, isMobile: true, hasTouch: true });
        const cdp = await mobile.context.newCDPSession(mobile.page);
        await mobile.page.goto(`${origin}/foto/middle?viewer=1`);
        await mobile.page.evaluate(() => { window.__marker = 'mismo-documento'; });
        await mobile.page.waitForFunction(() => document.querySelector('#lightbox.is-open .lightbox__img.is-shown'));
        await mobile.page.waitForTimeout(700);
        const swipeSamples = await sampleViewer(mobile.page, async () => {
            const box = await mobile.page.locator('.lightbox__img').first().boundingBox();
            const x = box.x + box.width / 2, y = box.y + box.height / 2;
            await cdp.send('Input.dispatchTouchEvent', { type: 'touchStart', touchPoints: [{ x, y }] });
            await cdp.send('Input.dispatchTouchEvent', { type: 'touchMove', touchPoints: [{ x: x - 120, y: y + 2 }] });
            await cdp.send('Input.dispatchTouchEvent', { type: 'touchEnd', touchPoints: [] });
        });
        await waitSlug(mobile.page, 'last');
        assert(Math.min(...swipeSamples.map(sample => sample.max)) >= 0.99, 'Móvil: al deslizar nunca debe verse el fondo');
        assert.equal(await mobile.page.evaluate(() => window.__marker), 'mismo-documento', 'Móvil: no se recarga la página');
        assert.deepEqual(desktop.errors, []);
        assert.deepEqual(mobile.errors, []);
        console.log('Photo transition passed: in-place change without reload, cross-fade over the previous photo (background never visible), rapid presses, browser back, closing, detail arrows, ?viewer=1 reload and touch.');
    } finally {
        if (browser) await browser.close();
        fs.rmSync(snapshot, { recursive: true, force: true });
    }
})().catch((error) => { console.error(error); process.exit(1); });
