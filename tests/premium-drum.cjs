// Galería premium «Estilo Tambor»: carrusel cilíndrico 3D con la interfaz y el comportamiento de la baraja.
// Renderiza el marcado real (inc/premium/deck.php con la galería `drum`) con el CSS y el JS reales (deck.css + drum.css, deck.js, main.js).
const {chromium} = require('playwright');
const {execFileSync} = require('child_process');
const fs = require('fs');
const path = require('path');
const assert = require('assert/strict');
const root = path.join(__dirname, '..');

const fixture = String.raw`
require_once 'inc/helpers.php';
require_once 'inc/site-settings.php';
$rootDir = getcwd(); $author = 'Autor'; $galleryItems = [];
$siteSettings = array_replace(site_settings_defaults(), ['gallery_premium' => 'drum']);
$aspects = [1.5, 0.6667, 1.0, 1.7778, 0.56, 1.3333, 1.5, 0.75];
foreach ($aspects as $i => $aspect) {
    $galleryItems[] = ['slug' => "foto-$i", 'title' => "Foto $i", 'description' => '', 'desktop' => "imagenes/desktop/foto-$i.webp",
        'mobile' => "imagenes/mobile/foto-$i.webp", 'aspect' => $aspect, 'category' => $i % 2 ? 'B' : 'A', 'latitude' => 0, 'longitude' => 0];
}
include 'inc/premium/deck.php';
`;
const markup = execFileSync('php', ['-r', fixture], {cwd: root, encoding: 'utf8'});
const css = ['style', 'site-design', 'gallery-layout'].map(n => fs.readFileSync(path.join(root, `assets/css/${n}.css`), 'utf8')).join('\n')
    + fs.readFileSync(path.join(root, 'assets/premium/deck/deck.css'), 'utf8') + fs.readFileSync(path.join(root, 'assets/premium/drum/drum.css'), 'utf8');
const scripts = [fs.readFileSync(path.join(root, 'assets/js/site-texts.js'), 'utf8'),
    fs.readFileSync(path.join(root, 'assets/js/main.js'), 'utf8'),
    fs.readFileSync(path.join(root, 'assets/premium/deck/deck.js'), 'utf8')];
const png = Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==', 'base64');
const pageHtml = palette => `<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head>
<body data-palette="${palette}" data-grid="premium" data-gallery-premium="drum" data-gallery-mobile="premium" data-gallery-desktop="premium">
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

// Centros de las cartas (relativos a la galería) por posición: el tambor es simétrico alrededor de la activa.
const layout = page => page.evaluate(() => {
    const deck = document.querySelector('#deck').getBoundingClientRect();
    const out = {w: deck.width, h: deck.height, cards: {}};
    document.querySelectorAll('.deck__card').forEach(card => {
        const r = card.getBoundingClientRect(), style = getComputedStyle(card);
        out.cards[card.dataset.pos] = {x: r.left + r.width / 2 - deck.left, y: r.top + r.height / 2 - deck.top, left: r.left - deck.left, right: r.right - deck.left,
            width: r.width, z: new DOMMatrix(style.transform).m43, filter: style.filter, opacity: style.opacity, visibility: style.visibility, slug: card.dataset.slug};
    });
    return out;
});

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
                positions: [...document.querySelectorAll('.deck__card')].map(card => card.dataset.pos).join(','),
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

            // 1. Pantalla completa, disposición «tambor» y posiciones iniciales (la activa y tres cartas hacia la derecha).
            await toDeck();
            const box = await page.evaluate(() => { const r = document.querySelector('#deck').getBoundingClientRect(); return {w: r.width, h: r.height, vw: innerWidth, vh: innerHeight}; });
            assert(Math.abs(box.w - box.vw) < 1 && Math.abs(box.h - box.vh) < 1, `${label}: la galería debe ocupar toda la pantalla`);
            assert.equal(await page.evaluate(() => document.querySelector('#deck').dataset.layout), 'drum', `${label}: disposición tambor`);
            let s = await state();
            assert.equal(s.positions, '0,1,2,3,3,3,3,3', `${label}: posiciones iniciales del tambor`);
            assert(!s.overflow, `${label}: desbordamiento horizontal`);
            const baseScroll = s.scrollY;

            // 2. Simetría: con la tercera foto activa hay dos cartas a cada lado, y el tambor es simétrico y centrado.
            await page.keyboard.press('ArrowDown'); await settle();
            await page.keyboard.press('ArrowDown'); await settle();
            s = await state();
            assert.equal(s.index, 2, `${label}: las flechas giran el tambor`);
            assert.equal(s.positions, '-2,-1,0,1,2,3,3,3', `${label}: posiciones con dos cartas a cada lado`);
            assert(Math.abs(s.top) <= 2, `${label}: el fondo no debe moverse`);
            const L = await layout(page);
            const c = L.cards;
            assert(Math.abs(c[0].x - L.w / 2) <= 2 && Math.abs(c[0].y - L.h / 2) <= 2, `${label}: la carta activa debe estar centrada (${c[0].x.toFixed(1)},${c[0].y.toFixed(1)} en ${L.w}×${L.h})`);
            assert(Math.abs((c[1].x - c[0].x) + (c[-1].x - c[0].x)) <= 3, `${label}: las cartas ±1 deben ser simétricas (${c[-1].x.toFixed(1)} · ${c[0].x.toFixed(1)} · ${c[1].x.toFixed(1)})`);
            assert(Math.abs((c[2].x - c[0].x) + (c[-2].x - c[0].x)) <= 3, `${label}: las cartas ±2 deben ser simétricas`);
            assert(c[1].x > c[0].x + 20 && c[2].x > c[1].x + 10 && c[-1].x < c[0].x - 20 && c[-2].x < c[-1].x - 10, `${label}: las cartas se alejan del centro a cada lado`);
            assert(c[1].y - c[0].y >= -1 && Math.abs(c[1].y - c[0].y) <= 2 && Math.abs(c[-2].y - c[0].y) <= 2, `${label}: el tambor gira en horizontal (mismo centro vertical)`);
            assert(c[1].z < -20 && c[2].z < c[1].z && c[-1].z < -20 && c[-2].z < c[-1].z, `${label}: las cartas de los lados se curvan hacia atrás (z ${c[-2].z.toFixed(0)},${c[-1].z.toFixed(0)},${c[1].z.toFixed(0)},${c[2].z.toFixed(0)})`);
            assert(c[0].filter === 'none' && c[1].filter !== 'none' && c[2].filter !== 'none', `${label}: las cartas de los lados se oscurecen`);
            assert(c[3].visibility === 'hidden' && c[3].opacity === '0', `${label}: la carta ±3 queda oculta detrás del cilindro`);
            assert(c[0].left >= 0 && c[0].right <= L.w, `${label}: la carta activa cabe entera en la pantalla`);
            if (device.viewport.width > 768) assert(c[1].right <= L.w + 1 && c[-1].left >= -1, `${label}: en pantallas anchas se ven enteras las tres cartas`);
            await page.keyboard.press('Home'); await settle();
            assert.equal((await state()).index, 0, `${label}: Inicio vuelve a la primera foto`);

            // 3. Interfaz de la baraja: contador, flechas, botones de salir y filtros.
            assert.equal(await page.evaluate(() => document.querySelector('[data-deck-current]').textContent + '/' + document.querySelector('[data-deck-total]').textContent), '1/8', `${label}: contador`);
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
            const left = await page.evaluate(() => ({afterTop: Math.round(document.querySelector('#after').getBoundingClientRect().top), scrollY: Math.round(scrollY)}));
            assert(left.afterTop <= 5 && Math.abs(left.scrollY - (before + box.vh)) <= 3, `${label}: «Salir» baja una pantalla (${JSON.stringify(left)})`);
            await page.evaluate(() => window.scrollTo({top: 0, behavior: 'instant'}));
            await page.waitForTimeout(2000);
            await toDeck();
            await page.click('[data-deck-exit-up]');
            const upTarget = Math.max(0, 820 - box.vh);   // La galería empieza a 820 px (cabecera + filtros): sube una pantalla.
            await page.waitForFunction(target => Math.abs(scrollY - target) <= 3, upTarget, {timeout: 6000}).catch(() => {});
            assert(Math.abs((await page.evaluate(() => scrollY)) - upTarget) <= 3, `${label}: «Salir hacia arriba» sube una pantalla por encima de la galería`);

            // 6. Filtros: categoría y favoritas.
            await page.click('.categories-filter__chip[data-category="B"]');
            const filtered = await page.evaluate(() => ({total: document.querySelector('[data-deck-total]').textContent, hidden: [...document.querySelectorAll('.deck__card')].filter(card => card.hidden).length}));
            assert.deepEqual(filtered, {total: '4', hidden: 4}, `${label}: el filtro por categoría deja 4 fotos`);
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
            await page.keyboard.press('End');
            await page.evaluate(() => document.querySelector('[data-deck-next]').click());   // El índice 1 deja cartas a ambos lados.
            await page.evaluate(() => document.querySelector('[data-deck-prev]').click());
            await page.waitForTimeout(1500);
            const L = await layout(page);
            const active = Object.values(L.cards).find(card => card.z === 0 && card.filter === 'none');
            assert(active && Math.abs(active.x - L.w / 2) <= 2 && Math.abs(active.y - L.h / 2) <= 2, `Centrado ${width}×${height}: la carta activa debe estar centrada (${JSON.stringify(active)})`);
            assert(active.left >= 0 && active.right <= L.w, `Centrado ${width}×${height}: la carta activa cabe en la pantalla`);
            await context.close();
        }

        // 8. Volver desde la ficha: /#baraja=<slug> abre el tambor en esa foto, a pantalla completa.
        for (const viewport of [{width: 1440, height: 900}, {width: 390, height: 844, touch: true}]) {
            const context = await browser.newContext({viewport: {width: viewport.width, height: viewport.height}, hasTouch: !!viewport.touch, isMobile: !!viewport.touch});
            const {page, errors} = await open(context, pageHtml('current'), 'https://deck.test/#baraja=foto-5');
            await page.waitForTimeout(1900);
            const back = await page.evaluate(() => ({index: Number(document.querySelector('#deck').dataset.index), top: Math.round(document.querySelector('#deck').getBoundingClientRect().top),
                hash: location.hash, active: document.querySelector('.deck__card[data-pos="0"]')?.dataset.slug}));
            assert.equal(back.index, 5, `Volver (${viewport.width}px): debe abrir la foto 6`);
            assert.equal(back.active, 'foto-5');
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
            await page.keyboard.press('ArrowDown');
            await page.waitForFunction(() => document.querySelector('#deck').dataset.index === '1' && getComputedStyle(document.querySelector('.deck__card[data-pos="-1"]')).visibility === 'visible', null, {timeout: 400});
            await context.close();
        }

        console.log('Tambor verificado: pantalla completa, disposición cilíndrica simétrica y centrada, interfaz de la baraja (rueda, dedo, teclado, flechas, salidas, filtros), fijación en móvil, vuelta desde la ficha y paleta en 5 dispositivos.');
    } finally { await browser.close(); }
})().catch(error => { console.error(error); process.exit(1); });
