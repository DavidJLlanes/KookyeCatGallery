// Galería premium «Estilo Tinder»: una carta que se desliza a un lado. Hacia el corazón (derecha) da un corazón y pasa a la siguiente;
// hacia la flecha (izquierda) solo pasa. En los dos casos se avanza. Interfaz de la baraja con un corazón y una flecha que lo indican.
// Renderiza el marcado real (inc/premium/swipe.php) con el CSS y el JS reales (deck.css + swipe.css, swipe.js, main.js) y un servidor de
// corazones simulado (/hearts.php) que cuenta las llamadas.
const {chromium} = require('playwright');
const {execFileSync} = require('child_process');
const fs = require('fs');
const path = require('path');
const assert = require('assert/strict');
const root = path.join(__dirname, '..');

const TOTAL = 8;
const fixture = String.raw`
require_once 'inc/helpers.php';
require_once 'inc/site-settings.php';
$rootDir = getcwd(); $author = 'Autor'; $galleryItems = [];
$siteSettings = array_replace(site_settings_defaults(), ['gallery_premium' => 'swipe']);
for ($i = 0; $i < ${TOTAL}; $i++) {
    $galleryItems[] = ['slug' => "foto-$i", 'title' => "Foto $i", 'description' => '', 'desktop' => "imagenes/desktop/foto-$i.webp",
        'mobile' => "imagenes/mobile/foto-$i.webp", 'aspect' => 1.5, 'category' => $i % 2 ? 'B' : 'A', 'latitude' => 0, 'longitude' => 0];
}
include 'inc/premium/swipe.php';
`;
const markup = execFileSync('php', ['-r', fixture], {cwd: root, encoding: 'utf8'});
const css = ['style', 'site-design', 'gallery-layout'].map(n => fs.readFileSync(path.join(root, `assets/css/${n}.css`), 'utf8')).join('\n')
    + fs.readFileSync(path.join(root, 'assets/premium/deck/deck.css'), 'utf8') + fs.readFileSync(path.join(root, 'assets/premium/swipe/swipe.css'), 'utf8');
const scripts = [fs.readFileSync(path.join(root, 'assets/js/site-texts.js'), 'utf8'),
    fs.readFileSync(path.join(root, 'assets/js/main.js'), 'utf8'),
    fs.readFileSync(path.join(root, 'assets/premium/swipe/swipe.js'), 'utf8')];
const png = Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==', 'base64');
const pageHtml = palette => `<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head>
<body data-palette="${palette}" data-grid="premium" data-gallery-premium="swipe" data-gallery-mobile="premium" data-gallery-desktop="premium">
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

// Abre la página con un servidor de corazones simulado: guarda qué fotos tienen corazón y cuenta las llamadas POST.
const open = async (context, html, {liked = [], url = 'https://deck.test/'} = {}) => {
    const page = await context.newPage();
    const errors = [], posts = [], likedSet = new Set(liked);
    page.on('pageerror', error => errors.push(error.message));
    await page.route('**/*', async route => {
        const request = route.request(), target = request.url();
        if (target.endsWith('.webp')) return route.fulfill({status: 200, contentType: 'image/png', body: png});
        if (target === 'https://deck.test/') return route.fulfill({status: 200, contentType: 'text/html', body: html});
        if (target.startsWith('https://deck.test/hearts.php')) {
            if (request.method() === 'POST') {
                const body = new URLSearchParams(request.postData() || '');
                const slug = body.get('slug'), action = body.get('action');
                posts.push({slug, action});
                if (action === 'like') likedSet.add(slug); else likedSet.delete(slug);
                return route.fulfill({status: 200, contentType: 'application/json', body: JSON.stringify({ok: true, liked: likedSet.has(slug), count: likedSet.has(slug) ? 1 : 0})});
            }
            const slugs = (new URL(target).searchParams.get('slugs') || '').split(',').filter(Boolean);
            return route.fulfill({status: 200, contentType: 'application/json', body: JSON.stringify({ok: true,
                counts: Object.fromEntries(slugs.map(slug => [slug, likedSet.has(slug) ? 1 : 0])), liked: Object.fromEntries(slugs.map(slug => [slug, likedSet.has(slug)]))})});
        }
        if (target.startsWith('https://deck.test/foto/')) return route.fulfill({status: 200, contentType: 'text/html', body: '<!doctype html><title>Ficha</title><h1>Ficha de la foto</h1>'});
        return route.fulfill({status: 200, contentType: 'text/plain', body: ''});
    });
    await page.addInitScript(saved => { try { if (saved.length) localStorage.setItem('djl-photo-favorites-v1', JSON.stringify(saved)); } catch (_) {} }, liked);
    await page.goto(url);
    await page.addStyleTag({content: css});
    for (const script of scripts) await page.addScriptTag({content: script});
    return {page, errors, posts};
};

(async () => {
    const browser = await chromium.launch({headless: true, executablePath: process.env.TEST_BROWSER || undefined, args: ['--no-sandbox']});
    try {
        for (const device of devices) {
            const context = await browser.newContext({viewport: device.viewport, hasTouch: !!device.touch, isMobile: !!device.touch});
            const {page, errors, posts} = await open(context, pageHtml('current'));
            const label = device.name;
            const state = () => page.evaluate(() => ({
                index: Number(document.querySelector('#deck').dataset.index), pinned: document.documentElement.classList.contains('deck-pinned'),
                scrollY: document.documentElement.classList.contains('deck-pinned') ? -parseInt(document.body.style.top, 10) : Math.round(scrollY),
                top: Math.round(document.querySelector('#deck').getBoundingClientRect().top),
                positions: [...document.querySelectorAll('.deck__card')].map(card => card.dataset.pos).join(','),
                finished: document.querySelector('#deck').classList.contains('is-finished'),
                current: document.querySelector('[data-deck-current]').textContent, total: document.querySelector('[data-deck-total]').textContent,
                overflow: document.documentElement.scrollWidth > innerWidth + 1}));
            const toDeck = async () => {
                for (let attempt = 0; attempt < 14; attempt++) {
                    if (device.touch && await page.evaluate(() => document.documentElement.classList.contains('deck-pinned'))) return;
                    await page.evaluate(() => window.scrollTo({top: document.querySelector('#deck').getBoundingClientRect().top + scrollY, behavior: 'instant'}));
                    await page.waitForTimeout(150);
                    if (Math.abs(await page.evaluate(() => document.querySelector('#deck').getBoundingClientRect().top)) <= 2) return;
                }
            };
            const settle = () => page.waitForTimeout(900);
            const cdp = device.touch ? await context.newCDPSession(page) : null;
            // Arrastra la carta activa desde su centro: con CDP (dedo) en táctil y con el ratón en escritorio.
            const center = () => page.evaluate(() => { const r = document.querySelector('.deck__card[data-pos="0"]').getBoundingClientRect(); return {x: r.left + r.width / 2, y: r.top + r.height / 2, w: r.width}; });
            const drag = async (dx, dy = 0, {release = true, wait = 900, steps = 8} = {}) => {
                const c = await center();
                if (device.touch) {
                    await cdp.send('Input.dispatchTouchEvent', {type: 'touchStart', touchPoints: [{x: c.x, y: c.y}]});
                    for (let i = 1; i <= steps; i++) await cdp.send('Input.dispatchTouchEvent', {type: 'touchMove', touchPoints: [{x: c.x + dx * i / steps, y: c.y + dy * i / steps}]});
                    if (release) await cdp.send('Input.dispatchTouchEvent', {type: 'touchEnd', touchPoints: []});
                } else {
                    await page.mouse.move(c.x, c.y); await page.mouse.down();
                    await page.mouse.move(c.x + dx, c.y + dy, {steps});
                    if (release) await page.mouse.up();
                }
                if (release) await page.waitForTimeout(wait);
                return c;
            };

            // 1. Pantalla completa, disposición «swipe» y posiciones iniciales (la activa y dos que asoman debajo).
            await toDeck();
            await page.waitForTimeout(900);
            const box = await page.evaluate(() => { const r = document.querySelector('#deck').getBoundingClientRect(); return {w: r.width, h: r.height, vw: innerWidth, vh: innerHeight}; });
            assert(Math.abs(box.w - box.vw) < 1 && Math.abs(box.h - box.vh) < 1, `${label}: la galería debe ocupar toda la pantalla`);
            let s = await state();
            assert.equal(s.positions, '0,1,2,3,3,3,3,3', `${label}: posiciones iniciales`);
            assert(!s.overflow, `${label}: desbordamiento horizontal`);
            if (device.touch) assert(s.pinned, `${label}: en táctil la galería debe quedar fijada`);
            const baseScroll = s.scrollY;

            // 2. La interfaz indica los dos lados: una flecha a la izquierda y un corazón a la derecha (en la barra) y en la pista.
            const ui = await page.evaluate(() => {
                const pass = document.querySelector('[data-swipe-pass]').getBoundingClientRect(), like = document.querySelector('[data-swipe-like]').getBoundingClientRect();
                const counter = document.querySelector('.deck__counter').getBoundingClientRect();
                return {passX: pass.left + pass.width / 2, likeX: like.left + like.width / 2, counterX: counter.left + counter.width / 2,
                    heartPath: !!document.querySelector('[data-swipe-like] svg path[d^="M12 21s"]'), arrowPath: !!document.querySelector('[data-swipe-pass] svg path[d^="M20 12H5"]'),
                    hint: document.querySelector('[data-deck-hint]').textContent, stamps: document.querySelectorAll('.deck__card[data-pos="0"] .swipe__stamp').length};
            });
            assert(ui.passX < ui.counterX && ui.likeX > ui.counterX, `${label}: la flecha va a la izquierda y el corazón a la derecha del contador`);
            assert(ui.heartPath && ui.arrowPath, `${label}: el botón de corazón lleva un corazón y el de pasar una flecha`);
            assert(ui.hint.includes('♥') && ui.hint.includes('→') && ui.hint.includes('←'), `${label}: la pista explica los dos lados (${ui.hint.trim().replace(/\s+/g, ' ')})`);
            assert.equal(ui.stamps, 2, `${label}: la carta lleva un sello de corazón y otro de flecha`);

            // 3. Arrastre corto: la carta vuelve a su sitio y no ocurre nada.
            await drag(22, 0);
            s = await state();
            assert.equal(s.index, 0, `${label}: un arrastre corto no avanza`);
            assert.equal(posts.length, 0, `${label}: un arrastre corto no da corazón`);

            // 3b. Mientras se arrastra hacia el corazón aparece el sello del corazón (y el de la flecha al ir al otro lado).
            const dragged = await drag(Math.round((await center()).w * 0.2), 0, {release: false});
            const stamps = await page.evaluate(() => { const card = document.querySelector('.deck__card[data-pos="0"]'); return {like: parseFloat(getComputedStyle(card.querySelector('.swipe__stamp--like')).opacity), pass: parseFloat(getComputedStyle(card.querySelector('.swipe__stamp--pass')).opacity), transform: card.style.transform}; });
            assert(stamps.like > 0.3 && stamps.pass === 0, `${label}: al arrastrar hacia el corazón se ve el sello del corazón (${JSON.stringify(stamps)})`);
            assert(stamps.transform.includes('rotate'), `${label}: la carta sigue al dedo girando`);
            await page.waitForTimeout(250);                                    // El dedo se detiene un momento: no es un gesto rápido.
            if (device.touch) await cdp.send('Input.dispatchTouchEvent', {type: 'touchEnd', touchPoints: []}); else await page.mouse.up();
            await page.waitForTimeout(900);
            assert.equal((await state()).index, 0, `${label}: soltar antes del umbral deja la carta en su sitio`);

            // 4. Hacia la IZQUIERDA (la flecha): solo avanza a la siguiente foto; no hay corazón.
            await drag(-Math.round((await center()).w * 0.5), 10);
            s = await state();
            assert.equal(s.index, 1, `${label}: deslizar hacia la flecha pasa a la siguiente`);
            assert.equal(posts.length, 0, `${label}: deslizar hacia la flecha NO da corazón`);
            const out0 = await page.evaluate(() => document.querySelector('.deck__card[data-slug="foto-0"]').className);
            assert(!out0.includes('is-out-right'), `${label}: la carta sin corazón sale por la izquierda`);
            assert(Math.abs(s.top) <= 2, `${label}: el fondo no debe moverse`);

            // 5. Hacia la DERECHA (el corazón): da un corazón a la foto y avanza a la siguiente.
            await drag(Math.round((await center()).w * 0.5), -10);
            s = await state();
            assert.equal(s.index, 2, `${label}: deslizar hacia el corazón también pasa a la siguiente`);
            assert.deepEqual(posts, [{slug: 'foto-1', action: 'like'}], `${label}: deslizar hacia el corazón da un corazón a la foto que se ha dejado (${JSON.stringify(posts)})`);
            assert.equal(await page.evaluate(() => document.querySelector('.deck__card[data-slug="foto-1"]').classList.contains('is-out-right')), true, `${label}: la carta con corazón sale por la derecha`);
            assert.equal(await page.evaluate(() => document.querySelector('.deck__card[data-slug="foto-1"] .deck__hearts').textContent.trim().startsWith('♥')), true, `${label}: la foto queda marcada con un corazón`);
            assert.equal(await page.evaluate(() => JSON.parse(localStorage.getItem('djl-photo-favorites-v1') || '[]').includes('foto-1')), true, `${label}: la foto pasa a Favoritas`);

            // 5b. Tinder usa el gesto horizontal para las fotos, pero un gesto vertical debe soltar la galería
            //     y permitir que la página continúe desplazándose sin quedarse bloqueada en body:fixed.
            if (device.touch) {
                const beforeVertical = (await state()).scrollY;
                const x = Math.round(device.viewport.width / 2), y = Math.round(device.viewport.height * 0.58);
                await cdp.send('Input.dispatchTouchEvent', {type: 'touchStart', touchPoints: [{x, y}]});
                for (let i = 1; i <= 8; i++) {
                    await cdp.send('Input.dispatchTouchEvent', {type: 'touchMove', touchPoints: [{x, y: y - 150 * i / 8}]});
                    await page.waitForTimeout(16);
                }
                await cdp.send('Input.dispatchTouchEvent', {type: 'touchEnd', touchPoints: []});
                await page.waitForTimeout(500);
                const afterVertical = await state();
                assert(!afterVertical.pinned && afterVertical.scrollY > beforeVertical + 50,
                    `${label}: el gesto vertical libera Tinder y desplaza la página (${beforeVertical} → ${afterVertical.scrollY})`);
                await toDeck();
                assert((await state()).pinned, `${label}: la galería vuelve a acoplarse al regresar a ella`);
            }

            // 6. Botones de la barra: el corazón da un corazón y avanza; la flecha solo avanza.
            await page.click('[data-swipe-like]'); await settle();
            s = await state();
            assert.equal(s.index, 3, `${label}: el botón del corazón avanza`);
            assert.deepEqual(posts[1], {slug: 'foto-2', action: 'like'}, `${label}: el botón del corazón da un corazón`);
            await page.click('[data-swipe-pass]'); await settle();
            assert.equal((await state()).index, 4, `${label}: el botón de la flecha avanza`);
            assert.equal(posts.length, 2, `${label}: el botón de la flecha no da corazón`);

            // 7. Si la foto ya tenía corazón, deslizar hacia el corazón solo avanza (no lo quita).
            await page.evaluate(() => { const favorites = new Set(JSON.parse(localStorage.getItem('djl-photo-favorites-v1') || '[]')); favorites.add('foto-4'); localStorage.setItem('djl-photo-favorites-v1', JSON.stringify([...favorites])); });
            await drag(Math.round((await center()).w * 0.5), 0);
            assert.equal((await state()).index, 5, `${label}: avanza también si ya tenía corazón`);
            assert.equal(posts.length, 2, `${label}: no se vuelve a llamar al servidor (ni se quita) si ya tenía corazón`);

            // 8. Teclado: → corazón y siguiente, ← siguiente, ↑ anterior; rueda (escritorio): abajo = siguiente sin corazón.
            if (!device.touch) {
                await page.keyboard.press('ArrowRight'); await settle();
                assert.equal((await state()).index, 6, `${label}: → da corazón y avanza`);
                assert.deepEqual(posts[2], {slug: 'foto-5', action: 'like'}, `${label}: → da un corazón`);
                await page.keyboard.press('ArrowLeft'); await settle();
                assert.equal((await state()).index, 7, `${label}: ← avanza`);
                assert.equal(posts.length, 3, `${label}: ← no da corazón`);
                await page.keyboard.press('ArrowUp'); await settle();
                assert.equal((await state()).index, 6, `${label}: ↑ vuelve a la anterior`);
                await page.mouse.move(device.viewport.width / 2, device.viewport.height / 2);
                await page.mouse.wheel(0, 120); await settle();
                assert.equal((await state()).index, 7, `${label}: la rueda hacia abajo pasa sin dar corazón`);
                assert.equal(posts.length, 3);
            } else {
                await drag(-Math.round((await center()).w * 0.5), 0);
                await drag(-Math.round((await center()).w * 0.5), 0);
                assert.equal((await state()).index, 7, `${label}: dos deslizamientos más`);
            }

            // 9. Al ver todas las fotos aparece el aviso con «Volver a empezar».
            await page.keyboard.press('End'); await settle();
            s = await state();
            assert(s.finished && s.index === TOTAL && s.current === String(TOTAL), `${label}: tras la última se muestra el aviso final (${JSON.stringify(s)})`);
            assert.equal(await page.evaluate(() => getComputedStyle(document.querySelector('[data-swipe-end]')).display), 'flex', `${label}: el aviso final se ve`);
            assert.equal(await page.evaluate(() => document.querySelector('[data-swipe-like]').disabled && document.querySelector('[data-swipe-pass]').disabled), true, `${label}: sin fotos, los botones se desactivan`);
            await page.click('[data-swipe-restart]'); await settle();
            s = await state();
            assert(!s.finished && s.index === 0 && s.positions.startsWith('0,1,2,3'), `${label}: «Volver a empezar» vuelve a la primera foto`);

            // 10. Pulsar la carta abre la ficha; arrastrarla no.
            await drag(40, 0, {wait: 400});
            assert.equal(page.url(), 'https://deck.test/', `${label}: arrastrar la carta no abre la ficha`);
            const c = await center();
            if (device.touch) await page.touchscreen.tap(c.x, c.y); else await page.mouse.click(c.x, c.y);
            await page.waitForURL('https://deck.test/foto/foto-0', {timeout: 4000});
            assert(await page.evaluate(() => document.body.textContent.includes('Ficha de la foto')), `${label}: pulsar la carta abre la ficha`);
            await page.goBack();
            await page.waitForTimeout(500);
            await page.addStyleTag({content: css});                     // La página vuelve sin estilos ni scripts (el HTML es de prueba): se añaden otra vez.
            for (const script of scripts) await page.addScriptTag({content: script});
            await page.waitForTimeout(800);

            // 11. Salir de la galería: botones (abajo y arriba) y, en táctil, un gesto vertical en el límite.
            await toDeck();
            const before = (await state()).scrollY;
            await page.click('[data-deck-exit]');
            await page.waitForFunction(() => document.querySelector('#after').getBoundingClientRect().top <= 5, null, {timeout: 6000}).catch(() => {});
            await page.waitForTimeout(900);
            const exited = await page.evaluate(() => ({afterTop: Math.round(document.querySelector('#after').getBoundingClientRect().top), scrollY: Math.round(scrollY)}));
            assert(exited.afterTop <= 5 && Math.abs(exited.scrollY - (before + box.vh)) <= 3, `${label}: «Salir» baja una pantalla (${JSON.stringify(exited)})`);
            await page.evaluate(() => window.scrollTo({top: 0, behavior: 'instant'}));
            await page.waitForTimeout(2000);
            await toDeck();
            await page.click('[data-deck-exit-up]');
            const upTarget = Math.max(0, 820 - box.vh);
            await page.waitForFunction(target => Math.abs(scrollY - target) <= 3, upTarget, {timeout: 6000}).catch(() => {});
            assert(Math.abs((await page.evaluate(() => scrollY)) - upTarget) <= 3, `${label}: «Salir hacia arriba» sube una pantalla`);
            if (device.touch) {
                await page.evaluate(() => window.scrollTo({top: 0, behavior: 'instant'}));
                await page.waitForTimeout(2000);
                await toDeck();
                const pinnedY = (await state()).scrollY;
                await drag(0, 120, {steps: 8, wait: 1800});          // En la primera foto, un gesto hacia abajo sale por arriba.
                const up = await page.evaluate(() => Math.round(scrollY));
                assert(up < pinnedY - 50, `${label}: un gesto vertical en la primera foto sale de la galería (${pinnedY} → ${up})`);
            }

            // 12. Filtros: categoría y favoritas.
            if (device.touch) { await page.evaluate(() => window.scrollTo({top: 0, behavior: 'instant'})); await page.waitForTimeout(1500); }
            await page.click('.categories-filter__chip[data-category="B"]');
            await page.waitForTimeout(300);
            const filtered = await page.evaluate(() => ({total: document.querySelector('[data-deck-total]').textContent, hidden: [...document.querySelectorAll('.deck__card')].filter(card => card.hidden).length}));
            assert.deepEqual(filtered, {total: '4', hidden: 4}, `${label}: el filtro por categoría deja 4 fotos`);
            await page.click('.categories-filter__chip[data-category=""]');
            await page.click('#favoritesToggle');
            assert.equal(await page.evaluate(() => document.querySelector('[data-deck-empty]').hidden), true, `${label}: con fotos con corazón, Favoritas las muestra`);
            await page.click('#favoritesToggle');
            assert.deepEqual(errors, [], `${label}: errores de JavaScript: ${errors.join(' | ')}`);
            await context.close();
        }

        // 13. Centrado: la carta queda centrada en horizontal y, en vertical, justo en el hueco libre entre las herramientas y la barra de abajo
        //     (la misma distancia hasta unas que hasta la otra), en cualquier pantalla.
        for (const [width, height] of [[320, 568], [360, 640], [390, 844], [393, 852], [430, 932], [768, 1024], [820, 1180], [1024, 768], [1280, 720], [1440, 900], [1920, 1080], [2560, 1440], [844, 390], [667, 375]]) {
            const touch = width < 800 || height < 500;
            const context = await browser.newContext({viewport: {width, height}, hasTouch: touch, isMobile: touch});
            const {page} = await open(context, pageHtml('current'));
            await page.waitForTimeout(900);
            const m = await page.evaluate(() => { const deck = document.querySelector('#deck').getBoundingClientRect(), r = document.querySelector('.deck__card[data-pos="0"]').getBoundingClientRect(),
                tools = document.querySelector('.deck__tools').getBoundingClientRect(), nav = document.querySelector('.deck__nav').getBoundingClientRect();
                return {dx: r.left + r.width / 2 - deck.left - deck.width / 2, above: r.top - tools.bottom, below: nav.top - r.bottom, left: r.left - deck.left, right: r.right - deck.left, w: deck.width}; });
            assert(Math.abs(m.dx) <= 2, `Centrado ${width}×${height}: la carta debe estar centrada en horizontal (${m.dx.toFixed(1)})`);
            assert(Math.abs(m.above - m.below) <= 6, `Centrado ${width}×${height}: la carta debe estar centrada en vertical entre las herramientas y la barra (arriba ${m.above.toFixed(1)}, abajo ${m.below.toFixed(1)})`);
            assert(m.above >= 8 && m.below >= 8 && m.left >= 0 && m.right <= m.w, `Centrado ${width}×${height}: la carta no toca los controles ni se sale (${JSON.stringify(m)})`);
            await context.close();
        }

        // 14. Volver desde la ficha: /#baraja=<slug> abre la carta de esa foto; paleta y movimiento reducido.
        for (const viewport of [{width: 1440, height: 900}, {width: 390, height: 844, touch: true}]) {
            const context = await browser.newContext({viewport: {width: viewport.width, height: viewport.height}, hasTouch: !!viewport.touch, isMobile: !!viewport.touch});
            const {page, errors} = await open(context, pageHtml('current'), {url: 'https://deck.test/#baraja=foto-5'});
            await page.waitForTimeout(1900);
            const back = await page.evaluate(() => ({index: Number(document.querySelector('#deck').dataset.index), top: Math.round(document.querySelector('#deck').getBoundingClientRect().top),
                hash: location.hash, active: document.querySelector('.deck__card[data-pos="0"]')?.dataset.slug}));
            assert.equal(back.index, 5, `Volver (${viewport.width}px): debe abrir la foto 6`);
            assert(back.active === 'foto-5' && Math.abs(back.top) <= 2 && back.hash === '', `Volver (${viewport.width}px): carta correcta, a pantalla completa y sin hash`);
            assert.deepEqual(errors, [], 'Volver: errores de JavaScript');
            await context.close();
        }
        {
            const context = await browser.newContext({viewport: {width: 1280, height: 720}, reducedMotion: 'reduce'});
            const {page} = await open(context, pageHtml('ocean'));
            assert.equal(await page.evaluate(() => getComputedStyle(document.querySelector('#deck')).backgroundColor), 'rgb(16, 39, 55)', 'El fondo sigue la paleta');
            await page.evaluate(() => window.scrollTo({top: document.querySelector('#deck').getBoundingClientRect().top + scrollY, behavior: 'instant'}));
            await page.waitForTimeout(500);
            await page.keyboard.press('ArrowLeft');
            await page.waitForFunction(() => document.querySelector('#deck').dataset.index === '1' && getComputedStyle(document.querySelector('.deck__card[data-slug="foto-0"]')).opacity === '0', null, {timeout: 500});
            await context.close();
        }

        console.log('Tinder verificado: pantalla completa, arrastre a la derecha (corazón + siguiente) y a la izquierda (solo siguiente), sellos y botones de corazón y flecha, foto que ya tenía corazón, teclado, rueda, aviso final, salidas, filtros, fijación en móvil, vuelta desde la ficha y centrado en 5 dispositivos.');
    } finally { await browser.close(); }
})().catch(error => { console.error(error); process.exit(1); });
