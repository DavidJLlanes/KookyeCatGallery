const { chromium } = require('playwright');
const { execFileSync } = require('child_process');
const fs = require('fs');
const os = require('os');
const path = require('path');
const zlib = require('zlib');
const assert = require('assert/strict');

// Local fixtures and route interception exercise the actual PHP and JS without external services.
function testImage(width = 640, height = 400) {
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
    header.writeUInt32BE(width, 0); header.writeUInt32BE(height, 4); header[8] = 8; header[9] = 2;
    const pixels = Buffer.alloc((width * 3 + 1) * height, 150);
    for (let i = 0; i < height; i++) pixels[i * (width * 3 + 1)] = 0;
    return Buffer.concat([Buffer.from('89504e470d0a1a0a', 'hex'), chunk('IHDR', header), chunk('IDAT', zlib.deflateSync(pixels)), chunk('IEND', Buffer.alloc(0))]);
}

(async () => {
    const root = path.join(__dirname, '..');
    const snapshot = fs.mkdtempSync(path.join(os.tmpdir(), 'photo-navigation-'));
    let browser;
    try {
        fs.cpSync(root, snapshot, { recursive: true, filter: (source) => !/(?:^|\/)(?:\.git|node_modules)(?:\/|$)/.test(source) });
        for (const directory of ['img', 'imagenes/desktop', 'imagenes/mobile', 'data']) fs.mkdirSync(path.join(snapshot, directory), { recursive: true });
        const cache = {};
        for (const [i, slug] of ['first', 'middle', 'last'].entries()) {
            const [width, height] = slug === 'last' ? [400, 640] : [640, 400];
            const image = testImage(width, height);
            const filename = `${slug}.png`;
            const original = path.join(snapshot, 'img', filename);
            fs.writeFileSync(original, image);
            const mtime = Math.floor(Date.now() / 1000) - i * 10;
            fs.utimesSync(original, mtime, mtime);
            for (const size of ['desktop', 'mobile']) fs.writeFileSync(path.join(snapshot, 'imagenes', size, `${slug}.webp`), image);
            fs.writeFileSync(path.join(snapshot, 'img', `${slug}.txt`), `${slug}\n---\nDescription ${slug}\n# Slug: ${slug}\n# Categoría: Test\n`);
            cache[filename] = { mtime, edit_mtime: 0, edit_size: 0, desktop_w: width, desktop_h: height, mobile_w: width, mobile_h: height, aspect: width / height };
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
                    const rects = imgs.map(i => i.getBoundingClientRect());
                    window.__samples.push({max: Math.max(...imgs.map(i => parseFloat(getComputedStyle(i).opacity))), layers: imgs.length,
                        left: Math.min(...rects.map(r => r.left)), right: Math.max(...rects.map(r => r.right)), tops: rects.map(r => Math.round(r.top)),
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
        assert(samples.some(sample => sample.layers === 2), 'La foto nueva debe entrar mientras la anterior sale (dos capas durante el cambio)');
        assert(new Set(samples.map(sample => Math.round(sample.left))).size > 4, 'Las fotos deben deslizar de forma continua (la posición cambia fotograma a fotograma)');
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
        // The old detail fades completely before the new image and text are laid out.
        await desktop.page.locator('a.photo-detail__nav--previous').click();
        await waitSlug(desktop.page, 'first');
        assert.equal(await desktop.page.evaluate(() => window.__marker), 'mismo-documento', 'Las flechas de la ficha tampoco recargan');
        await desktop.page.waitForFunction(() => getComputedStyle(document.querySelector('.photo-detail')).opacity === '1');
        assert.equal(await desktop.page.evaluate(() => getComputedStyle(document.querySelector('.photo-detail__btn')).viewTransitionName), 'none', 'La foto no se separa del texto durante el cambio');
        assert.equal(await desktop.page.locator('#lightbox').getAttribute('aria-hidden'), 'true');
        assert.equal(await desktop.page.locator('.photo-detail__img').getAttribute('src').then(src => /first/.test(src)), true);

        // 6. Recargar con el visor abierto (?viewer=1): lo de detrás no se ve y el visor se abre.
        await desktop.page.goto(`${origin}/foto/middle?viewer=1`);
        await desktop.page.waitForFunction(() => document.querySelector('#lightbox.is-open .lightbox__img.is-shown'));
        assert.equal(await desktop.page.evaluate(() => document.documentElement.classList.contains('viewer-pending')), false, 'El estado pendiente se quita al abrir el visor');

        // 7. Móvil: el visor a pantalla completa sigue al dedo, enseña ya la foto vecina y desliza sin franjas ni saltos.
        const mobile = await makePage({ viewport: { width: 390, height: 844 }, isMobile: true, hasTouch: true });
        const cdp = await mobile.context.newCDPSession(mobile.page);
        await mobile.page.goto(`${origin}/foto/middle?viewer=1`);
        await mobile.page.evaluate(() => { window.__marker = 'mismo-documento'; });
        await mobile.page.waitForFunction(() => document.querySelector('#lightbox.is-open .lightbox__img.is-shown'));
        await mobile.page.waitForTimeout(700);
        const center = async () => { const box = await mobile.page.locator('.lightbox__img').first().boundingBox(); return { x: box.x + box.width / 2, y: box.y + box.height / 2 }; };
        const finger = (type, x, y) => cdp.send('Input.dispatchTouchEvent', { type, touchPoints: type === 'touchEnd' ? [] : [{ x, y }] });
        const first = await mobile.page.locator('.lightbox__img').first().boundingBox();
        // 7a. Mientras se arrastra, la foto sigue al dedo y la vecina asoma por el lado hacia el que se arrastra.
        const c = await center();
        await finger('touchStart', c.x, c.y);
        for (let i = 1; i <= 6; i++) await finger('touchMove', c.x - 15 * i, c.y + 1);
        await mobile.page.waitForTimeout(100);
        const dragging = await mobile.page.evaluate(() => { const imgs = [...document.querySelectorAll('#lightbox .lightbox__img')].map(i => ({left: i.getBoundingClientRect().left, src: i.getAttribute('src'), cls: i.className})); return imgs; });
        assert(dragging.length === 2, `Al arrastrar debe verse la foto vecina (${JSON.stringify(dragging)})`);
        assert.equal(await mobile.page.evaluate(() => document.querySelector('.lightbox__stage').classList.contains('photo-sliding')), true, 'Ambas fotos usan superficies del tamaño del visor');
        assert(Math.abs(dragging[0].left - (first.x - 90)) <= 4, `La foto sigue al dedo (${dragging[0].left} frente a ${first.x - 90})`);
        assert(dragging[1].left > 250 && dragging[1].left < 390 && /last/.test(dragging[1].src), `La foto siguiente asoma por la derecha (${JSON.stringify(dragging[1])})`);
        // 7b. Un gesto que se queda corto (el dedo vuelve atrás antes de soltar): todo vuelve a su sitio, sin cambiar de foto.
        for (let i = 1; i <= 3; i++) await finger('touchMove', c.x - 90 + 20 * i, c.y + 1);
        await finger('touchEnd');
        await mobile.page.waitForTimeout(600);
        assert.equal(await mobile.page.locator('.photo-detail__title').textContent(), 'middle', 'Un gesto corto no cambia de foto');
        assert.equal(await mobile.page.locator('#lightbox .lightbox__img').count(), 1, 'La foto vecina se retira');
        const back = await mobile.page.locator('.lightbox__img').first().boundingBox();
        assert(Math.abs(back.x - first.x) <= 1, 'La foto vuelve a su sitio');
        // 7c. Un gesto largo cambia de foto deslizando: nunca hay hueco entre las dos fotos (cubren todo el ancho en cada fotograma).
        const swipeSamples = await sampleViewer(mobile.page, async () => {
            const origin2 = await center();
            await finger('touchStart', origin2.x, origin2.y);
            for (let i = 1; i <= 4; i++) await finger('touchMove', origin2.x - 30 * i, origin2.y + 1);
            await finger('touchEnd');
        });
        await waitSlug(mobile.page, 'last');
        const during = swipeSamples.filter(sample => sample.layers === 2);
        assert(during.length > 5, 'Durante el deslizamiento hay dos fotos a la vez');
        assert(Math.min(...swipeSamples.map(sample => sample.max)) >= 0.99, 'Móvil: al deslizar nunca debe verse el fondo');
        assert(during.every(sample => sample.left <= 0.5 && sample.right >= 389.5), `Móvil: las dos fotos cubren todo el ancho en cada fotograma, sin franjas (${JSON.stringify(during.filter(sample => sample.left > 0.5 || sample.right < 389.5).slice(0, 3))})`);
        assert.equal(await mobile.page.evaluate(() => window.__marker), 'mismo-documento', 'Móvil: no se recarga la página');
        await mobile.page.waitForFunction(() => document.querySelectorAll('#lightbox .lightbox__img').length === 1);
        // 7d. En la última foto, arrastrar hacia el lado sin foto se resiste (un tercio) y vuelve.
        const edge = await center();
        await finger('touchStart', edge.x, edge.y);
        for (let i = 1; i <= 6; i++) await finger('touchMove', edge.x - 15 * i, edge.y + 1);
        await mobile.page.waitForTimeout(100);
        const resisted = await mobile.page.locator('.lightbox__img').first().boundingBox();
        assert(Math.abs(resisted.x - (first.x - 30)) <= 5 && await mobile.page.locator('#lightbox .lightbox__img').count() === 1, `Sin foto vecina la foto se resiste (${resisted.x})`);
        await finger('touchEnd');
        await mobile.page.waitForTimeout(500);
        assert.equal(await mobile.page.locator('.photo-detail__title').textContent(), 'last');
        // 8. En pantalla completa se apartan la barra de progreso y los botones flotantes.
        assert.equal(await mobile.page.evaluate(() => getComputedStyle(document.querySelector('.scroll-progress')).visibility), 'hidden', 'La barra de progreso no se ve en pantalla completa');
        // 9. Deslizar hacia abajo: un gesto corto vuelve a su sitio; uno largo cierra el visor con la foto saliendo.
        const pullFrom = await center();
        await finger('touchStart', pullFrom.x, pullFrom.y);
        for (let i = 1; i <= 4; i++) await finger('touchMove', pullFrom.x + 1, pullFrom.y + 12 * i);
        await mobile.page.waitForTimeout(80);
        const pulling = await mobile.page.evaluate(() => ({transform: document.querySelector('.lightbox__img').style.transform, bg: document.getElementById('lightbox').style.backgroundColor}));
        assert(/translate3d\(0(px)?, 48px/.test(pulling.transform) && pulling.bg.startsWith('rgba'), `La foto sigue al dedo hacia abajo y el fondo se aclara (${JSON.stringify(pulling)})`);
        await mobile.page.waitForTimeout(200);                      // El dedo se detiene: sin inercia, el gesto corto no cierra.
        await finger('touchEnd');
        await mobile.page.waitForTimeout(500);
        assert.equal(await mobile.page.locator('#lightbox').getAttribute('aria-hidden'), 'false', 'Un gesto corto hacia abajo no cierra el visor');
        assert.equal(await mobile.page.evaluate(() => document.querySelector('.lightbox__img').style.transform), '', 'La foto vuelve a su sitio');
        const pullAgain = await center();
        await finger('touchStart', pullAgain.x, pullAgain.y);
        for (let i = 1; i <= 8; i++) await finger('touchMove', pullAgain.x + 1, pullAgain.y + 22 * i);
        await finger('touchEnd');
        await mobile.page.waitForFunction(() => document.getElementById('lightbox').getAttribute('aria-hidden') === 'true', null, {timeout: 3000});
        assert.equal(new URL(mobile.page.url()).searchParams.has('viewer'), false, 'Al cerrar deslizando se quita ?viewer=1');
        await mobile.page.waitForTimeout(400);
        assert.equal(await mobile.page.evaluate(() => document.getElementById('lightbox').style.backgroundColor), '', 'El visor queda limpio para la próxima vez');
        // 10. En la ficha un gesto corto mantiene imagen y texto alineados y no abre el visor.
        await mobile.page.evaluate(() => window.scrollTo(0, 0));
        const detailBox = await mobile.page.locator('.photo-detail__img').boundingBox();
        const dx0 = detailBox.x + detailBox.width / 2, dy0 = detailBox.y + detailBox.height / 2;
        await finger('touchStart', dx0, dy0);
        for (let i = 1; i <= 4; i++) await finger('touchMove', dx0 + 10 * i, dy0 + 1);
        await mobile.page.waitForTimeout(80);
        assert.equal(await mobile.page.evaluate(() => document.querySelector('.photo-detail__img').style.transform), '', 'La imagen permanece alineada con el texto durante el gesto');
        await finger('touchEnd');
        await mobile.page.waitForTimeout(500);
        assert.equal(await mobile.page.evaluate(() => document.querySelector('.photo-detail__img').style.transform), '', 'La ficha permanece estable si el gesto se queda corto');
        assert.equal(await mobile.page.locator('#lightbox').getAttribute('aria-hidden'), 'true', 'Arrastrar la foto de la ficha no abre el visor');
        assert.deepEqual(desktop.errors, []);
        assert.deepEqual(mobile.errors, []);
        console.log('Photo transition passed: unified detail fade, pull to close, stable detail gesture, in-place sliding without reload, finger following with the neighbour visible, no gaps or strips, resistance at the ends, rapid presses, browser back, closing, detail arrows, ?viewer=1 reload and touch.');
    } finally {
        if (browser) await browser.close();
        fs.rmSync(snapshot, { recursive: true, force: true });
    }
})().catch((error) => { console.error(error); process.exit(1); });
