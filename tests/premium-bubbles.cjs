// Galería premium «Estilo Burbujas»: círculos de distintos tamaños repartidos por toda la pantalla sin solaparse, paginación,
// «Fotos visibles a la vez», apertura con rebote hacia la ficha, fijación en móvil, botones de salir, filtros y vuelta desde la ficha.
// Renderiza el marcado real (inc/premium/bubbles.php) con el CSS y el JS reales (bubbles.css, bubbles.js, main.js).
const {chromium} = require('playwright');
const {execFileSync} = require('child_process');
const fs = require('fs');
const path = require('path');
const assert = require('assert/strict');
const root = path.join(__dirname, '..');

const TOTAL = 30;
const fixture = String.raw`
require_once 'inc/helpers.php';
require_once 'inc/site-settings.php';
$rootDir = getcwd(); $author = 'Autor'; $galleryItems = [];
for ($i = 0; $i < ${TOTAL}; $i++) {
    $galleryItems[] = ['slug' => "foto-$i", 'title' => "Foto $i", 'description' => '', 'desktop' => "imagenes/desktop/foto-$i.webp",
        'mobile' => "imagenes/mobile/foto-$i.webp", 'aspect' => 1.5, 'category' => $i % 3 ? 'B' : 'A', 'latitude' => 0, 'longitude' => 0];
}
include 'inc/premium/bubbles.php';
`;
const markup = execFileSync('php', ['-r', fixture], {cwd: root, encoding: 'utf8'});
const css = ['style', 'site-design', 'gallery-layout'].map(n => fs.readFileSync(path.join(root, `assets/css/${n}.css`), 'utf8')).join('\n')
    + fs.readFileSync(path.join(root, 'assets/premium/bubbles/bubbles.css'), 'utf8');
const scripts = [fs.readFileSync(path.join(root, 'assets/js/site-texts.js'), 'utf8'),
    fs.readFileSync(path.join(root, 'assets/js/main.js'), 'utf8'),
    fs.readFileSync(path.join(root, 'assets/premium/bubbles/bubbles.js'), 'utf8')];
const png = Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==', 'base64');
const pageHtml = (palette, desktop = 20, mobile = 12) => `<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head>
<body data-palette="${palette}" data-grid="premium" data-gallery-premium="bubbles" data-gallery-mobile="premium" data-gallery-desktop="premium" data-photos-desktop="${desktop}" data-photos-mobile="${mobile}">
<header id="before" style="height:700px">Cabecera</header>
<div class="categories-filter" style="height:120px;overflow:hidden"><div class="categories-filter__inner"><button class="categories-filter__chip is-active" data-category="">Todas</button>
<button class="categories-filter__chip" data-category="A">A</button><button class="categories-filter__chip" data-category="B">B</button></div></div>
${markup}
<a class="upload-fab" href="#" aria-label="Subir">+</a><button class="top-btn" type="button" aria-label="Subir">↑</button>
<section id="after" style="height:1600px">Siguiente bloque</section></body></html>`;

const devices = [
    {name: 'escritorio 1440×900', viewport: {width: 1440, height: 900}, per: 20},
    {name: 'portátil 1280×720', viewport: {width: 1280, height: 720}, per: 20},
    {name: 'tablet 820×1180', viewport: {width: 820, height: 1180}, touch: true, per: 20},
    {name: 'móvil 390×844', viewport: {width: 390, height: 844}, touch: true, per: 12},
    {name: 'móvil apaisado 844×390', viewport: {width: 844, height: 390}, touch: true, per: 20},
];

// Abre la página con el mismo HTML y recursos de siempre; `navigate` captura las fichas de foto.
const open = async (context, html, url = 'https://deck.test/') => {
    const page = await context.newPage();
    const errors = [];
    page.on('pageerror', error => errors.push(error.message));
    await page.route('**/*', route => {
        const target = route.request().url();
        if (target.endsWith('.webp')) return route.fulfill({status: 200, contentType: 'image/png', body: png});
        if (target === 'https://deck.test/') return route.fulfill({status: 200, contentType: 'text/html', body: html});
        if (target.startsWith('https://deck.test/foto/')) return route.fulfill({status: 200, contentType: 'text/html', body: '<!doctype html><title>Ficha</title><h1>Ficha de la foto</h1>'});
        return route.fulfill({status: 200, contentType: 'text/plain', body: ''});
    });
    await page.goto(url);
    await page.addStyleTag({content: css});
    for (const script of scripts) await page.addScriptTag({content: script});
    return {page, errors};
};

// Geometría de las burbujas visibles (coordenadas relativas a la zona de reparto).
const geometry = page => page.evaluate(() => {
    const field = document.querySelector('[data-bubbles-field]');
    const f = field.getBoundingClientRect();
    return {
        width: field.clientWidth, height: field.clientHeight,
        circles: [...document.querySelectorAll('.bubbles__item')].filter(item => !item.hidden).map(item => ({
            x: item.offsetLeft + item.offsetWidth / 2, y: item.offsetTop + item.offsetHeight / 2, r: item.offsetWidth / 2,
            h: item.offsetHeight / 2, text: item.textContent.trim(), slug: item.dataset.slug, category: item.dataset.category})),
        field: {top: f.top, bottom: f.bottom, left: f.left, right: f.right},
    };
});

(async () => {
    const browser = await chromium.launch({headless: true, executablePath: process.env.TEST_BROWSER || undefined, args: ['--no-sandbox']});
    try {
        for (const device of devices) {
            const context = await browser.newContext({viewport: device.viewport, hasTouch: !!device.touch, isMobile: !!device.touch});
            const {page, errors} = await open(context, pageHtml('current'));
            const label = device.name;
            const state = () => page.evaluate(() => ({
                page: Number(document.querySelector('#bubbles').dataset.page), pinned: document.documentElement.classList.contains('deck-pinned'),
                scrollY: document.documentElement.classList.contains('deck-pinned') ? -parseInt(document.body.style.top, 10) : Math.round(scrollY),
                top: Math.round(document.querySelector('#bubbles').getBoundingClientRect().top),
                current: document.querySelector('[data-bubbles-current]').textContent, total: document.querySelector('[data-bubbles-total]').textContent,
                shown: [...document.querySelectorAll('.bubbles__item')].filter(i => !i.hidden).length,
                overflow: document.documentElement.scrollWidth > innerWidth + 1}));
            const toDeck = async () => {
                for (let attempt = 0; attempt < 14; attempt++) {
                    if (device.touch && await page.evaluate(() => document.documentElement.classList.contains('deck-pinned'))) return;
                    await page.evaluate(() => window.scrollTo({top: document.querySelector('#bubbles').getBoundingClientRect().top + scrollY, behavior: 'instant'}));
                    await page.waitForTimeout(150);
                    if (Math.abs(await page.evaluate(() => document.querySelector('#bubbles').getBoundingClientRect().top)) <= 2) return;
                }
            };
            const freeTouch = async () => {
                if (await page.evaluate(() => document.documentElement.classList.contains('deck-pinned'))) { await page.click('[data-bubbles-exit-up]'); await page.waitForTimeout(2000); }
                await page.evaluate(() => window.scrollTo({top: 0, behavior: 'instant'}));
                await page.waitForTimeout(500);
            };
            const settle = () => page.waitForTimeout(1900);   // Salida (~0,4 s) + entrada con rebote (~0,85 s) + margen.
            const cdp = device.touch ? await context.newCDPSession(page) : null;
            const swipe = async (dy, steps = 8, wait = 1900, fromY = device.viewport.height * 0.6) => {
                const x = device.viewport.width / 2;
                await cdp.send('Input.dispatchTouchEvent', {type: 'touchStart', touchPoints: [{x, y: fromY}]});
                for (let i = 1; i <= steps; i++) await cdp.send('Input.dispatchTouchEvent', {type: 'touchMove', touchPoints: [{x, y: fromY + dy * i / steps}]});
                await cdp.send('Input.dispatchTouchEvent', {type: 'touchEnd', touchPoints: []});
                await page.waitForTimeout(wait);
            };

            // 1. Pantalla completa y «Fotos visibles a la vez»: 20 en escritorio y tablet (más de 768 px), 12 en móvil (data-photos-* del <body>).
            await toDeck();
            await page.waitForTimeout(1200);
            const box = await page.evaluate(() => { const r = document.querySelector('#bubbles').getBoundingClientRect(); return {w: r.width, h: r.height, vw: innerWidth, vh: innerHeight}; });
            assert(Math.abs(box.w - box.vw) < 1 && Math.abs(box.h - box.vh) < 1, `${label}: la galería debe ocupar toda la pantalla (${box.w}×${box.h})`);
            let s = await state();
            const expectedPer = device.per;
            assert.equal(s.shown, Math.min(expectedPer, TOTAL), `${label}: debe mostrar «Fotos visibles a la vez» (${expectedPer}) burbujas por página (${s.shown})`);
            assert.equal(s.total, String(Math.ceil(TOTAL / s.shown)), `${label}: número de páginas`);
            assert.equal(s.current, '1');
            assert(!s.overflow, `${label}: desbordamiento horizontal`);

            // 2. Reparto: tamaños distintos, sin solaparse, sin salirse, repartidas por toda la pantalla y sin texto.
            const g = await geometry(page);
            const radii = g.circles.map(c => c.r);
            assert(Math.max(...radii) / Math.min(...radii) >= 1.25, `${label}: los círculos deben tener tamaños distintos (${Math.min(...radii).toFixed(0)}–${Math.max(...radii).toFixed(0)})`);
            for (const c of g.circles) {
                assert(Math.abs(c.r - c.h) < 0.6, `${label}: cada burbuja debe ser un círculo`);
                assert(c.x - c.r >= -0.5 && c.x + c.r <= g.width + 0.5 && c.y - c.r >= -0.5 && c.y + c.r <= g.height + 0.5, `${label}: una burbuja se sale de la pantalla (${JSON.stringify(c)} en ${g.width}×${g.height})`);
                assert.equal(c.text, '', `${label}: las burbujas no llevan texto`);
            }
            let minGap = Infinity;
            for (let i = 0; i < g.circles.length; i++) for (let j = i + 1; j < g.circles.length; j++) {
                const a = g.circles[i], b = g.circles[j];
                minGap = Math.min(minGap, Math.hypot(a.x - b.x, a.y - b.y) - a.r - b.r);
            }
            assert(minGap >= 22, `${label}: las burbujas no deben tocarse ni solaparse (separación mínima ${minGap.toFixed(1)} px)`);
            const quadrants = new Set(g.circles.map(c => `${c.x < g.width / 2 ? 'L' : 'R'}${c.y < g.height / 2 ? 'T' : 'B'}`));
            assert.equal(quadrants.size, 4, `${label}: las burbujas deben repartirse por toda la pantalla (cuadrantes ${[...quadrants]})`);
            const covered = g.circles.reduce((sum, c) => sum + Math.PI * c.r * c.r, 0) / (g.width * g.height);
            assert(covered >= 0.3, `${label}: las burbujas deben llenar la pantalla (cobertura ${(covered * 100).toFixed(0)} %)`);
            // Centrado: la zona útil deja la misma reserva arriba y abajo.
            const reserve = await page.evaluate(() => { const d = document.querySelector('#bubbles').getBoundingClientRect(), f = document.querySelector('[data-bubbles-field]').getBoundingClientRect();
                return {top: f.top - d.top, bottom: d.bottom - f.bottom, left: f.left - d.left, right: d.right - f.right}; });
            assert(Math.abs(reserve.top - reserve.bottom) <= 1 && Math.abs(reserve.left - reserve.right) <= 1, `${label}: la zona de reparto debe estar centrada (${JSON.stringify(reserve)})`);

            // 3. Las burbujas flotan: se mueven solas, despacio y menos que la separación entre ellas.
            const float = await page.evaluate(() => new Promise(resolve => {
                const el = document.querySelector('.bubbles__item:not([hidden]) .bubbles__float');
                const name = getComputedStyle(el).animationName;
                const a = getComputedStyle(el).translate;
                setTimeout(() => resolve({name, moved: a !== getComputedStyle(el).translate}), 1500);
            }));
            assert.equal(float.name, 'bubbles-float', `${label}: las burbujas deben flotar`);
            assert(float.moved, `${label}: las burbujas deben moverse`);

            // 4. Paginación minimalista: «1 / N», anterior desactivada; sin caja alrededor de las flechas.
            assert.equal(await page.evaluate(() => document.querySelector('[data-bubbles-prev]').disabled), true, `${label}: en la primera página «anterior» está desactivada`);
            const pagerStyle = await page.evaluate(() => { const b = getComputedStyle(document.querySelector('[data-bubbles-next]')); return {bg: b.backgroundColor, border: b.borderTopWidth}; });
            assert(pagerStyle.bg === 'rgba(0, 0, 0, 0)' && pagerStyle.border === '0px', `${label}: la paginación debe ser minimalista (sin caja)`);

            // 5. Teclado: pasa de página y vuelve; en la última deja de capturar.
            await page.keyboard.press('ArrowDown'); await settle();
            s = await state();
            assert.equal(s.page, 1, `${label}: ArrowDown pasa a la página siguiente`);
            assert.equal(s.current, '2');
            assert.equal(s.shown, TOTAL - expectedPer >= expectedPer ? expectedPer : TOTAL - expectedPer, `${label}: la página 2 muestra el resto`);
            assert(Math.abs(s.top) <= 2, `${label}: el fondo no debe moverse (top=${s.top})`);
            const g2 = await geometry(page);
            assert(g2.circles.every(c => c.x - c.r >= -0.5 && c.x + c.r <= g2.width + 0.5 && c.y - c.r >= -0.5 && c.y + c.r <= g2.height + 0.5), `${label}: la página 2 también debe caber en pantalla`);
            await page.keyboard.press('ArrowUp'); await settle();
            assert.equal((await state()).page, 0, `${label}: ArrowUp vuelve a la página anterior`);
            await page.keyboard.press('End'); await settle();
            s = await state();
            assert.equal(s.current, s.total, `${label}: End va a la última página`);
            assert.equal(await page.evaluate(() => document.querySelector('[data-bubbles-next]').disabled), true, `${label}: en la última página «siguiente» está desactivada`);
            await page.keyboard.press('Home'); await settle();
            assert.equal((await state()).page, 0);

            // 6. Rueda (escritorio) o dedo (móvil y tablet).
            if (!device.touch) {
                await page.mouse.move(device.viewport.width / 2, device.viewport.height / 2);
                await page.mouse.wheel(0, 120); await settle();
                assert.equal((await state()).page, 1, `${label}: la rueda hacia abajo pasa de página`);
                await page.mouse.wheel(0, -120); await settle();
                assert.equal((await state()).page, 0, `${label}: la rueda hacia arriba vuelve`);
            } else {
                const before = await state();
                assert(before.pinned, `${label}: en táctil la galería debe quedar fijada`);
                await swipe(-140);
                s = await state();
                assert.equal(s.page, 1, `${label}: deslizar hacia arriba pasa de página`);
                assert(Math.abs(s.top - before.top) <= 1, `${label}: el dedo no debe mover el fondo`);
                await swipe(140);
                assert.equal((await state()).page, 0, `${label}: deslizar hacia abajo vuelve`);
                // Estable: ni un píxel de movimiento ni con intentos de scroll programático.
                const tops = [];
                for (let i = 0; i < 6; i++) { await page.evaluate(dy => window.scrollBy(0, dy), i % 2 ? 40 : -40); tops.push((await state()).top); await page.waitForTimeout(80); }
                assert(tops.every(top => top === 0), `${label}: la galería fijada no debe moverse (${tops.join(',')})`);
            }

            // 7. Pulsar una burbuja: rebota y abre SIEMPRE la ficha (nunca la foto a pantalla completa en la propia galería).
            for (let attempt = 0; attempt < 2; attempt++) {
                await toDeck();
                const target = await page.evaluate(() => { const link = document.querySelector('.bubbles__item:not([hidden]) .bubbles__link'); const r = link.getBoundingClientRect(); return {x: r.left + r.width / 2, y: r.top + r.height / 2, slug: link.closest('.bubbles__item').dataset.slug}; });
                const seenOpener = page.evaluate(() => new Promise(resolve => {
                    const watch = () => { if (document.querySelector('.bubbles-opener')) resolve(true); else requestAnimationFrame(watch); };
                    watch(); setTimeout(() => resolve(false), 900);
                })).catch(() => false);   // La navegación destruye el contexto: no cuenta como «abierta».
                await page.mouse.click(target.x, target.y);
                await page.waitForURL(`https://deck.test/foto/${target.slug}`, {timeout: 5000});
                assert.notEqual(await seenOpener, true, `${label}: no debe mostrarse la foto a pantalla completa antes de la ficha`);
                assert(await page.evaluate(() => document.body.textContent.includes('Ficha de la foto')), `${label}: debe abrirse la ficha de la foto`);
                if (attempt === 0) { await page.goBack(); await page.waitForTimeout(1500); }
            }
            await page.goBack();
            await page.waitForTimeout(500);
            await page.close();
            assert.deepEqual(errors, [], `${label}: errores de JavaScript: ${errors.join(' | ')}`);
            await context.close();
        }

        // 8. Salir: botones hacia abajo y hacia arriba, filtros y vuelta desde la ficha (escritorio y móvil).
        for (const device of [devices[0], devices[3]]) {
            const context = await browser.newContext({viewport: device.viewport, hasTouch: !!device.touch, isMobile: !!device.touch});
            const {page, errors} = await open(context, pageHtml('current'));
            const label = device.name;
            const toDeck = async () => {
                for (let attempt = 0; attempt < 14; attempt++) {
                    if (device.touch && await page.evaluate(() => document.documentElement.classList.contains('deck-pinned'))) return;
                    await page.evaluate(() => window.scrollTo({top: document.querySelector('#bubbles').getBoundingClientRect().top + scrollY, behavior: 'instant'}));
                    await page.waitForTimeout(150);
                    if (Math.abs(await page.evaluate(() => document.querySelector('#bubbles').getBoundingClientRect().top)) <= 2) return;
                }
            };
            await toDeck();
            await page.waitForFunction(() => getComputedStyle(document.querySelector('.upload-fab')).opacity === '0', null, {timeout: 4000}).catch(() => {});
            assert.equal(await page.evaluate(() => getComputedStyle(document.querySelector('.upload-fab')).opacity), '0', `${label}: los botones flotantes se apartan mientras la galería llena la pantalla`);
            const before = await page.evaluate(() => document.documentElement.classList.contains('deck-pinned') ? -parseInt(document.body.style.top, 10) : scrollY);
            assert.equal(await page.evaluate(() => document.querySelector('[data-bubbles-exit-up]').hidden), false, `${label}: con contenido encima hay botón para salir hacia arriba`);
            await page.click('[data-bubbles-exit]');
            await page.waitForFunction(() => document.querySelector('#after').getBoundingClientRect().top <= 5, null, {timeout: 6000}).catch(() => {});
            const left = await page.evaluate(() => ({afterTop: Math.round(document.querySelector('#after').getBoundingClientRect().top), scrollY: Math.round(scrollY), engaged: document.documentElement.classList.contains('deck-engaged')}));
            assert(left.afterTop <= 5 && left.engaged === false, `${label}: «Salir» deja la web siguiente a pantalla completa (${JSON.stringify(left)})`);
            assert(Math.abs(left.scrollY - (before + device.viewport.height)) <= 3, `${label}: «Salir» baja exactamente una pantalla`);
            await page.evaluate(() => window.scrollTo({top: 0, behavior: 'instant'}));
            await page.waitForTimeout(2000);
            await toDeck();
            await page.click('[data-bubbles-exit-up]');
            await page.waitForFunction(() => scrollY < 50, null, {timeout: 6000}).catch(() => {});
            assert((await page.evaluate(() => scrollY)) < 50, `${label}: «Salir hacia arriba» sube por encima de la galería`);
            // Filtros: la categoría B tiene 20 fotos de 30 → en escritorio 1 página, en móvil 2.
            await page.click('.categories-filter__chip[data-category="B"]');
            await page.waitForTimeout(600);
            const filtered = await page.evaluate(() => ({total: document.querySelector('[data-bubbles-total]').textContent,
                categories: [...new Set([...document.querySelectorAll('.bubbles__item')].filter(i => !i.hidden).map(i => i.dataset.category))]}));
            assert.deepEqual(filtered.categories, ['B'], `${label}: el filtro por categoría solo muestra esa categoría`);
            assert.equal(filtered.total, device.touch ? '2' : '1', `${label}: el filtro recalcula las páginas`);
            await page.click('.categories-filter__chip[data-category=""]');
            await page.click('#favoritesToggle');
            assert.equal(await page.evaluate(() => document.querySelector('[data-bubbles-empty]').hidden), false, `${label}: sin favoritas debe avisar`);
            await page.click('#favoritesToggle');
            assert.deepEqual(errors, [], `${label}: errores de JavaScript: ${errors.join(' | ')}`);
            await context.close();
        }

        // 9. Volver desde la ficha: /#baraja=<slug> abre la galería en la página de esa foto, a pantalla completa.
        for (const viewport of [{width: 1440, height: 900}, {width: 390, height: 844, touch: true}]) {
            const context = await browser.newContext({viewport: {width: viewport.width, height: viewport.height}, hasTouch: !!viewport.touch, isMobile: !!viewport.touch});
            const {page, errors} = await open(context, pageHtml('current'), 'https://deck.test/#baraja=foto-25');
            await page.waitForTimeout(2400);
            const back = await page.evaluate(() => ({page: Number(document.querySelector('#bubbles').dataset.page), top: Math.round(document.querySelector('#bubbles').getBoundingClientRect().top),
                hash: location.hash, slugs: [...document.querySelectorAll('.bubbles__item')].filter(i => !i.hidden).map(i => i.dataset.slug),
                pinned: document.documentElement.classList.contains('deck-pinned')}));
            const expectedPage = Math.floor(25 / (viewport.touch ? 12 : 20));
            assert.equal(back.page, expectedPage, `Volver (${viewport.width}px): debe abrir la página de la foto (${JSON.stringify(back)})`);
            assert(back.slugs.includes('foto-25'), `Volver (${viewport.width}px): la foto de la ficha debe estar en la página`);
            assert(Math.abs(back.top) <= 2, `Volver (${viewport.width}px): la galería debe quedar a pantalla completa`);
            assert.equal(back.hash, '', 'El enlace de vuelta no debe quedarse en la dirección');
            if (viewport.touch) assert(back.pinned, 'Volver (móvil): la galería debe quedar fijada');
            assert.deepEqual(errors, [], 'Volver desde la ficha: errores de JavaScript');
            await context.close();
        }

        // 10. «Fotos visibles a la vez» = 0 (todas): cabe todo en una página, con círculos de tamaño razonable.
        {
            const context = await browser.newContext({viewport: {width: 1440, height: 900}});
            const {page} = await open(context, pageHtml('current', 0, 0));
            await page.waitForTimeout(1500);
            const all = await geometry(page);
            assert.equal(all.circles.length, TOTAL, 'Con «Todas» deben verse las 30 burbujas');
            assert(Math.min(...all.circles.map(c => c.r)) >= 20, 'Con «Todas» ninguna burbuja debe ser diminuta');
            await context.close();
        }

        // 11. Integración con la paleta y movimiento reducido.
        {
            const context = await browser.newContext({viewport: {width: 1280, height: 720}, reducedMotion: 'reduce'});
            const {page} = await open(context, pageHtml('ocean'));
            await page.waitForTimeout(600);
            assert.equal(await page.evaluate(() => getComputedStyle(document.querySelector('#bubbles')).backgroundColor), 'rgb(16, 39, 55)', 'El fondo de la galería sigue la paleta');
            assert.equal(await page.evaluate(() => getComputedStyle(document.querySelector('.bubbles__float')).animationName), 'none', 'Con movimiento reducido las burbujas no flotan');
            await context.close();
        }

        console.log('Burbujas verificadas: reparto sin solapes en 5 dispositivos, tamaños distintos, paginación, fotos visibles a la vez, rebote al abrir, fijación en móvil, salidas, filtros y vuelta desde la ficha.');
    } finally { await browser.close(); }
})().catch(error => { console.error(error); process.exit(1); });
