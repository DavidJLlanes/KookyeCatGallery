// Galería premium «Estilo Cilindro»: varias filas de fotos de distintos tamaños en un cilindro giratorio, con la interfaz de la baraja.
// Renderiza el marcado real (inc/premium/cylinder.php) con el CSS y el JS reales (deck.css + cylinder.css, cylinder.js, main.js).
const {chromium} = require('playwright');
const {execFileSync} = require('child_process');
const fs = require('fs');
const path = require('path');
const assert = require('assert/strict');
const root = path.join(__dirname, '..');
const TOTAL = 40;

const fixture = String.raw`
require_once 'inc/helpers.php';
require_once 'inc/site-settings.php';
$rootDir = getcwd(); $author = 'Autor'; $galleryItems = [];
$siteSettings = array_replace(site_settings_defaults(), ['gallery_premium' => 'cylinder']);
for ($i = 0; $i < ${TOTAL}; $i++) {
    $galleryItems[] = ['slug' => "foto-$i", 'title' => "Foto $i", 'description' => '', 'desktop' => "imagenes/desktop/foto-$i.webp",
        'mobile' => "imagenes/mobile/foto-$i.webp", 'aspect' => 1.5, 'category' => $i % 2 ? 'B' : 'A', 'latitude' => 0, 'longitude' => 0];
}
include 'inc/premium/cylinder.php';
`;
const markup = execFileSync('php', ['-r', fixture], {cwd: root, encoding: 'utf8'});
const css = ['style', 'site-design', 'gallery-layout'].map(n => fs.readFileSync(path.join(root, `assets/css/${n}.css`), 'utf8')).join('\n')
    + fs.readFileSync(path.join(root, 'assets/premium/deck/deck.css'), 'utf8') + fs.readFileSync(path.join(root, 'assets/premium/cylinder/cylinder.css'), 'utf8');
const scripts = [fs.readFileSync(path.join(root, 'assets/js/site-texts.js'), 'utf8'),
    fs.readFileSync(path.join(root, 'assets/js/main.js'), 'utf8'),
    fs.readFileSync(path.join(root, 'assets/premium/cylinder/cylinder.js'), 'utf8')];
const png = Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==', 'base64');
const pageHtml = palette => `<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head>
<body data-palette="${palette}" data-grid="premium" data-gallery-premium="cylinder" data-gallery-mobile="premium" data-gallery-desktop="premium">
<header id="before" style="height:700px">Cabecera</header>
<div class="categories-filter" style="height:120px;overflow:hidden"><div class="categories-filter__inner"><button class="categories-filter__chip is-active" data-category="">Todas</button>
<button class="categories-filter__chip" data-category="A">A</button><button class="categories-filter__chip" data-category="B">B</button></div></div>
${markup}
<a class="upload-fab" href="#" aria-label="Subir">+</a><button class="top-btn" type="button" aria-label="Subir">↑</button>
<section id="after" style="height:1600px">Siguiente bloque</section></body></html>`;

const devices = [
    {name: 'escritorio 1440×900', viewport: {width: 1440, height: 900}},
    {name: 'portátil 1280×720', viewport: {width: 1280, height: 720}},
    {name: 'tablet 820×1180', viewport: {width: 820, height: 1180}, touch: true},
    {name: 'móvil 390×844', viewport: {width: 390, height: 844}, touch: true},
    {name: 'móvil apaisado 844×390', viewport: {width: 844, height: 390}, touch: true},
];

const open = async (context, html, url = 'https://deck.test/') => {
    const page = await context.newPage();
    const errors = [];
    page.on('pageerror', error => errors.push(error.message));
    await page.route('**/*', route => {
        const target = route.request().url();
        if (target.endsWith('.webp')) return route.fulfill({status: 200, contentType: 'image/png', body: png});
        if (target === 'https://deck.test/') return route.fulfill({status: 200, contentType: 'text/html', body: html});
        return route.fulfill({status: 200, contentType: 'text/plain', body: ''});
    });
    await page.goto(url);
    await page.addStyleTag({content: css});
    for (const script of scripts) await page.addScriptTag({content: script});
    return {page, errors};
};

// Geometría de las fotos colocadas (las de la mitad delantera del cilindro), relativa a la galería.
const layout = page => page.evaluate(() => {
    const deck = document.querySelector('#deck').getBoundingClientRect();
    const items = [...document.querySelectorAll('.cyl__item')].filter(item => item.style.display !== 'none').map(item => {
        const r = item.getBoundingClientRect(), style = getComputedStyle(item);
        return {slug: item.dataset.slug, left: r.left - deck.left, right: r.right - deck.left, top: r.top - deck.top, bottom: r.bottom - deck.top,
            x: r.left + r.width / 2 - deck.left, y: r.top + r.height / 2 - deck.top, width: r.width, height: r.height,
            z: new DOMMatrix(style.transform).m43, brightness: parseFloat(/brightness\(([\d.]+)\)/.exec(style.filter)?.[1] || '1')};
    });
    return {w: deck.width, h: deck.height, items};
});
const overlap = (a, b) => Math.max(0, Math.min(a.right, b.right) - Math.max(a.left, b.left)) * Math.max(0, Math.min(a.bottom, b.bottom) - Math.max(a.top, b.top));

(async () => {
    const browser = await chromium.launch({headless: true, executablePath: process.env.TEST_BROWSER || undefined, args: ['--no-sandbox']});
    try {
        for (const device of devices) {
            const context = await browser.newContext({viewport: device.viewport, hasTouch: !!device.touch, isMobile: !!device.touch});
            const {page, errors} = await open(context, pageHtml('current'));
            const label = device.name;
            const state = () => page.evaluate(() => ({
                index: Number(document.querySelector('#deck').dataset.index), pinned: document.documentElement.classList.contains('deck-pinned'),
                scrollY: document.documentElement.classList.contains('deck-pinned') ? -parseInt(document.body.style.top, 10) : Math.round(scrollY),
                top: Math.round(document.querySelector('#deck').getBoundingClientRect().top),
                placed: [...document.querySelectorAll('.cyl__item')].filter(item => item.style.display !== 'none').length,
                overflow: document.documentElement.scrollWidth > innerWidth + 1}));
            const toDeck = async () => {
                for (let attempt = 0; attempt < 14; attempt++) {
                    if (device.touch && await page.evaluate(() => document.documentElement.classList.contains('deck-pinned'))) return;
                    await page.evaluate(() => window.scrollTo({top: document.querySelector('#deck').getBoundingClientRect().top + scrollY, behavior: 'instant'}));
                    await page.waitForTimeout(150);
                    if (Math.abs(await page.evaluate(() => document.querySelector('#deck').getBoundingClientRect().top)) <= 2) return;
                }
            };
            const freeTouch = async () => {
                if (await page.evaluate(() => document.documentElement.classList.contains('deck-pinned'))) { await page.click('[data-deck-exit-up]'); await page.waitForTimeout(2000); }
                await page.evaluate(() => window.scrollTo({top: 0, behavior: 'instant'}));
                await page.waitForTimeout(500);
            };
            const settle = () => page.waitForTimeout(1300);
            const cdp = device.touch ? await context.newCDPSession(page) : null;
            const swipe = async (dy, steps = 8, wait = 1300, fromY = device.viewport.height * 0.6) => {
                const x = device.viewport.width / 2;
                await cdp.send('Input.dispatchTouchEvent', {type: 'touchStart', touchPoints: [{x, y: fromY}]});
                for (let i = 1; i <= steps; i++) await cdp.send('Input.dispatchTouchEvent', {type: 'touchMove', touchPoints: [{x, y: fromY + dy * i / steps}]});
                await cdp.send('Input.dispatchTouchEvent', {type: 'touchEnd', touchPoints: []});
                await page.waitForTimeout(wait);
            };

            // 1. Pantalla completa y disposición «cilindro».
            await toDeck();
            await page.waitForTimeout(1200);
            const box = await page.evaluate(() => { const r = document.querySelector('#deck').getBoundingClientRect(); return {w: r.width, h: r.height, vw: innerWidth, vh: innerHeight}; });
            assert(Math.abs(box.w - box.vw) < 1 && Math.abs(box.h - box.vh) < 1, `${label}: la galería debe ocupar toda la pantalla`);
            assert.equal(await page.evaluate(() => document.querySelector('#deck').dataset.layout), 'cylinder', `${label}: disposición cilindro`);
            let s = await state();
            assert(!s.overflow, `${label}: desbordamiento horizontal`);
            assert(s.placed >= 6, `${label}: debe verse una parte del cilindro con varias fotos (${s.placed})`);
            const baseScroll = s.scrollY;

            // 2. Geometría: el cilindro ocupa todo el ancho (en escritorio, estirado hacia los bordes) y se reparte en filas centradas.
            let L = await layout(page);
            const left = Math.min(...L.items.map(i => i.left)), right = Math.max(...L.items.map(i => i.right));
            assert(left <= L.w * 0.04 && right >= L.w * 0.96, `${label}: el cilindro debe llegar a los bordes de la pantalla (de ${left.toFixed(0)} a ${right.toFixed(0)} de ${L.w})`);
            const top = Math.min(...L.items.map(i => i.top)), bottom = Math.max(...L.items.map(i => i.bottom));
            assert(top >= 0 && bottom <= L.h, `${label}: el cilindro cabe en vertical (${top.toFixed(0)}–${bottom.toFixed(0)} de ${L.h})`);
            assert(Math.abs((top + bottom) / 2 - L.h / 2) <= 12, `${label}: el cilindro debe estar centrado en vertical (centro ${((top + bottom) / 2).toFixed(0)} de ${L.h})`);
            const widths = L.items.filter(i => i.brightness > 0.9).map(i => i.width), heights = L.items.filter(i => i.brightness > 0.9).map(i => i.height);
            assert(Math.max(...widths) / Math.min(...widths) >= 1.15 && Math.max(...heights) / Math.min(...heights) >= 1.15, `${label}: las fotos del frente deben tener tamaños distintos`);
            const rowsSeen = new Set(L.items.map(i => Math.round(i.y / (L.h / 8)))).size;
            assert(rowsSeen >= 2, `${label}: el cilindro debe tener varias filas`);
            assert(L.items.some(i => i.z === 0 || Math.abs(i.z) < 2) && L.items.every(i => i.z <= 0.5), `${label}: la parte delantera está al frente y el resto se curva hacia atrás`);
            assert(Math.min(...L.items.map(i => i.z)) < -20, `${label}: los lados deben curvarse hacia atrás (profundidad 3D)`);
            assert(Math.min(...L.items.map(i => i.brightness)) < 0.75 && Math.max(...L.items.map(i => i.brightness)) > 0.95, `${label}: los laterales se oscurecen`);
            assert(L.items.some(i => i.slug === `foto-${TOTAL - 1}`), `${label}: el cilindro se cierra sobre sí mismo (la última foto aparece junto a la primera)`);
            if (device.viewport.width / device.viewport.height > 1.2) assert(Math.min(...L.items.map(i => i.z)) > -0.45 * L.w, `${label}: en pantallas anchas el cilindro va estirado (poca profundidad)`);
            // Las fotos del frente no se solapan entre sí (los laterales casi de canto pueden rozar).
            const front = L.items.filter(i => i.brightness > 0.85);
            let worst = 0;
            for (let a = 0; a < front.length; a++) for (let b = a + 1; b < front.length; b++) worst = Math.max(worst, overlap(front[a], front[b]) / Math.min(front[a].width * front[a].height, front[b].width * front[b].height));
            assert(worst < 0.1, `${label}: las fotos del frente no deben solaparse (solape máximo ${(worst * 100).toFixed(1)} %)`);

            // 2b. Girar: cada paso mueve el cilindro (la foto central pasa hacia la izquierda) y el contador avanza.
            const centerSlug = L.items.reduce((best, i) => Math.abs(i.x - L.w / 2) < Math.abs(best.x - L.w / 2) ? i : best).slug;
            const xBefore = L.items.find(i => i.slug === centerSlug).x;
            await page.keyboard.press('ArrowDown'); await settle();
            s = await state();
            assert.equal(s.index, 1, `${label}: las flechas giran el cilindro`);
            assert(Math.abs(s.top) <= 2, `${label}: el fondo no debe moverse`);
            L = await layout(page);
            const after = L.items.find(i => i.slug === centerSlug);
            assert(after && after.x < xBefore - 20, `${label}: al avanzar, las fotos pasan hacia la izquierda (${xBefore.toFixed(0)} → ${after?.x.toFixed(0)})`);
            await page.keyboard.press('Home'); await settle();
            assert.equal((await state()).index, 0, `${label}: Inicio vuelve al primer paso`);

            // 3. Interfaz de la baraja: contador, flechas, botones de salir y filtros.
            const counter = await page.evaluate(() => ({current: document.querySelector('[data-deck-current]').textContent, total: Number(document.querySelector('[data-deck-total]').textContent)}));
            assert(counter.current === '1' && counter.total >= 8 && counter.total <= TOTAL / 2, `${label}: contador «1 / pasos» (${JSON.stringify(counter)})`);
            assert.equal(await page.evaluate(() => document.querySelector('[data-deck-prev]').disabled), true, `${label}: «anterior» desactivada en la primera`);
            await page.click('[data-deck-next]'); await settle();
            assert.equal((await state()).index, 1, `${label}: la flecha siguiente gira el tambor`);
            await page.click('[data-deck-prev]'); await settle();

            // 4. Rueda (escritorio) o dedo (móvil y tablet); límites.
            if (!device.touch) {
                await page.mouse.move(device.viewport.width / 2, device.viewport.height / 2);
                await page.mouse.wheel(0, 120); await settle();
                assert.equal((await state()).index, 1, `${label}: la rueda hacia abajo gira el tambor`);
                await page.mouse.wheel(0, -120); await settle();
                assert.equal((await state()).index, 0, `${label}: la rueda hacia arriba gira hacia atrás`);
                await page.keyboard.press('End'); await settle();
                await page.mouse.wheel(0, 400);
                await page.waitForFunction(min => scrollY > min, baseScroll + 50, {timeout: 6000}).catch(() => {});
                assert((await state()).scrollY > baseScroll + 50, `${label}: tras la última foto la página debe seguir bajando`);
                await toDeck();
                await page.keyboard.press('Home'); await settle();
            } else {
                const before = await state();
                assert(before.pinned, `${label}: en táctil la galería debe quedar fijada`);
                await swipe(-140);
                s = await state();
                assert.equal(s.index, 1, `${label}: deslizar hacia arriba gira el tambor`);
                assert(Math.abs(s.top - before.top) <= 1, `${label}: el dedo no debe mover el fondo`);
                await swipe(140);
                assert.equal((await state()).index, 0, `${label}: deslizar hacia abajo gira hacia atrás`);
                await swipe(-200, 8, 1300, device.viewport.height * 0.6);   // Sale de la galería solo en el límite: aquí aún hay fotos.
                await page.keyboard.press('Home'); await settle();
                const tops = [];
                for (let i = 0; i < 6; i++) { await page.evaluate(dy => window.scrollBy(0, dy), i % 2 ? 40 : -40); tops.push((await state()).top); await page.waitForTimeout(80); }
                assert(tops.every(top => top === 0), `${label}: la galería fijada no debe moverse (${tops.join(',')})`);
            }

            // 5. Botones de salir hacia abajo y hacia arriba; botones flotantes apartados mientras la galería llena la pantalla.
            await toDeck();
            await page.waitForFunction(() => getComputedStyle(document.querySelector('.upload-fab')).opacity === '0', null, {timeout: 4000}).catch(() => {});
            assert.equal(await page.evaluate(() => getComputedStyle(document.querySelector('.upload-fab')).opacity), '0', `${label}: los botones flotantes se apartan`);
            const before = (await state()).scrollY;
            await page.click('[data-deck-exit]');
            await page.waitForFunction(() => document.querySelector('#after').getBoundingClientRect().top <= 5, null, {timeout: 6000}).catch(() => {});
            await page.waitForTimeout(900);   // El scroll es animado: se espera a que termine.
            const exited = await page.evaluate(() => ({afterTop: Math.round(document.querySelector('#after').getBoundingClientRect().top), scrollY: Math.round(scrollY)}));
            assert(exited.afterTop <= 5 && Math.abs(exited.scrollY - (before + box.vh)) <= 3, `${label}: «Salir» baja una pantalla (${JSON.stringify(exited)})`);
            await page.evaluate(() => window.scrollTo({top: 0, behavior: 'instant'}));
            await page.waitForTimeout(2000);
            await toDeck();
            await page.click('[data-deck-exit-up]');
            const upTarget = Math.max(0, 820 - box.vh);   // La galería empieza a 820 px (cabecera + filtros): sube una pantalla.
            await page.waitForFunction(target => Math.abs(scrollY - target) <= 3, upTarget, {timeout: 6000}).catch(() => {});
            assert(Math.abs((await page.evaluate(() => scrollY)) - upTarget) <= 3, `${label}: «Salir hacia arriba» sube una pantalla por encima de la galería`);

            // 6. Filtros: categoría y favoritas.
            await page.click('.categories-filter__chip[data-category="B"]');
            await page.waitForTimeout(300);
            const filtered = await page.evaluate(() => ({total: Number(document.querySelector('[data-deck-total]').textContent),
                categories: [...new Set([...document.querySelectorAll('.cyl__item')].filter(item => item.style.display !== 'none').map(item => item.dataset.category))]}));
            assert.deepEqual(filtered.categories, ['B'], `${label}: el filtro por categoría solo muestra esa categoría`);
            assert(filtered.total >= 5 && filtered.total <= TOTAL / 4, `${label}: el filtro recalcula los pasos (${filtered.total})`);
            await page.click('.categories-filter__chip[data-category=""]');
            await page.click('#favoritesToggle');
            assert.equal(await page.evaluate(() => document.querySelector('[data-deck-empty]').hidden), false, `${label}: sin favoritas debe avisar`);
            await page.click('#favoritesToggle');
            assert.deepEqual(errors, [], `${label}: errores de JavaScript: ${errors.join(' | ')}`);
            await context.close();
        }

        // 7. Centrado: la carta activa y el conjunto quedan centrados en horizontal y en vertical en cualquier pantalla.
        const sizes = [[320, 568], [360, 640], [390, 844], [430, 932], [768, 1024], [820, 1180], [1024, 768], [1280, 720], [1440, 900], [1920, 1080], [2560, 1440], [844, 390], [667, 375]];
        for (const [width, height] of sizes) {
            const touch = width < 800 || height < 500;
            const context = await browser.newContext({viewport: {width, height}, hasTouch: touch, isMobile: touch});
            const {page} = await open(context, pageHtml('current'));
            await page.waitForTimeout(600);
            const L = await layout(page);
            const left = Math.min(...L.items.map(i => i.left)), right = Math.max(...L.items.map(i => i.right));
            const top = Math.min(...L.items.map(i => i.top)), bottom = Math.max(...L.items.map(i => i.bottom));
            assert(left <= L.w * 0.05 && right >= L.w * 0.95, `Cilindro ${width}×${height}: debe llegar a los bordes (${left.toFixed(0)}–${right.toFixed(0)} de ${L.w})`);
            assert(Math.abs((left + right) / 2 - L.w / 2) <= L.w * 0.02, `Cilindro ${width}×${height}: centrado en horizontal`);
            assert(top >= 0 && bottom <= L.h && Math.abs((top + bottom) / 2 - L.h / 2) <= 14, `Cilindro ${width}×${height}: centrado y dentro en vertical (${top.toFixed(0)}–${bottom.toFixed(0)} de ${L.h})`);
            await context.close();
        }

        // 8. Volver desde la ficha: /#baraja=<slug> abre el tambor en esa foto, a pantalla completa.
        for (const viewport of [{width: 1440, height: 900}, {width: 390, height: 844, touch: true}]) {
            const context = await browser.newContext({viewport: {width: viewport.width, height: viewport.height}, hasTouch: !!viewport.touch, isMobile: !!viewport.touch});
            const {page, errors} = await open(context, pageHtml('current'), 'https://deck.test/#baraja=foto-5');
            await page.waitForTimeout(1900);
            const back = await page.evaluate(() => { const deck = document.querySelector('#deck').getBoundingClientRect(); const item = document.querySelector('.cyl__item[data-slug="foto-5"]'); const r = item.getBoundingClientRect();
                return {index: Number(document.querySelector('#deck').dataset.index), top: Math.round(deck.top), hash: location.hash, shown: item.style.display !== 'none', offset: Math.abs(r.left + r.width / 2 - deck.left - deck.width / 2) / deck.width}; });
            assert(back.index >= 1 && back.index <= 5 && back.shown && back.offset < 0.25, `Volver (${viewport.width}px): la foto 6 debe quedar al frente (${JSON.stringify(back)})`);
            assert(Math.abs(back.top) <= 2 && back.hash === '', `Volver (${viewport.width}px): pantalla completa y sin hash`);
            assert.deepEqual(errors, [], 'Volver: errores de JavaScript');
            await context.close();
        }

        // 9. Paleta y movimiento reducido.
        {
            const context = await browser.newContext({viewport: {width: 1280, height: 720}, reducedMotion: 'reduce'});
            const page = await context.newPage();
            await page.route('**/*', route => route.request().url() === 'https://deck.test/'
                ? route.fulfill({status: 200, contentType: 'text/html', body: pageHtml('ocean')})
                : route.fulfill({status: 200, contentType: 'image/png', body: png}));
            await page.goto('https://deck.test/');
            await page.addStyleTag({content: css});
            for (const script of scripts) await page.addScriptTag({content: script});
            assert.equal(await page.evaluate(() => getComputedStyle(document.querySelector('#deck')).backgroundColor), 'rgb(16, 39, 55)', 'El fondo sigue la paleta');
            await page.evaluate(() => window.scrollTo({top: document.querySelector('#deck').getBoundingClientRect().top + scrollY, behavior: 'instant'}));
            await page.waitForTimeout(400);
            const before = await page.evaluate(() => document.querySelector('.cyl__item[style*="block"]').style.transform);
            await page.keyboard.press('ArrowDown');
            // Con movimiento reducido el giro es instantáneo: las fotos cambian de sitio casi al momento.
            await page.waitForFunction(first => document.querySelector('#deck').dataset.index === '1' && document.querySelector('.cyl__item[style*="block"]').style.transform !== first, before, {timeout: 400});
            await context.close();
        }

        console.log('Cilindro verificado: pantalla completa, cilindro de varias filas con fotos de distintos tamaños que llega a los bordes y se centra (13 tamaños de pantalla), giro, interfaz de la baraja (rueda, dedo, teclado, flechas, salidas, filtros), fijación en móvil, vuelta desde la ficha y paleta en 5 dispositivos.');
    } finally { await browser.close(); }
})().catch(error => { console.error(error); process.exit(1); });
