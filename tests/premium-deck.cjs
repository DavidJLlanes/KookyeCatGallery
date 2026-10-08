// Galería premium «Estilo Baraja»: pantalla completa, rueda, dedo, teclado, límites, ajuste de foto, filtros y paletas.
// Renderiza el marcado real (inc/premium/deck.php) con el CSS y el JS reales (deck.css, deck.js, main.js).
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
$aspects = [1.5, 0.6667, 1.0, 1.7778, 0.56, 1.3333];
foreach ($aspects as $i => $aspect) {
    $galleryItems[] = ['slug' => "foto-$i", 'title' => "Foto $i", 'description' => '', 'desktop' => "imagenes/desktop/foto-$i.webp",
        'mobile' => "imagenes/mobile/foto-$i.webp", 'aspect' => $aspect, 'category' => $i % 2 ? 'B' : 'A', 'latitude' => 0, 'longitude' => 0];
}
include 'inc/premium/deck.php';
`;
const markup = execFileSync('php', ['-r', fixture], {cwd: root, encoding: 'utf8'});
const css = ['style', 'site-design', 'gallery-layout'].map(n => fs.readFileSync(path.join(root, `assets/css/${n}.css`), 'utf8')).join('\n')
    + fs.readFileSync(path.join(root, 'assets/premium/deck/deck.css'), 'utf8');
const scripts = [fs.readFileSync(path.join(root, 'assets/js/site-texts.js'), 'utf8'),
    fs.readFileSync(path.join(root, 'assets/js/main.js'), 'utf8'),
    fs.readFileSync(path.join(root, 'assets/premium/deck/deck.js'), 'utf8')];
const png = Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==', 'base64');
const pageHtml = palette => `<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head>
<body data-palette="${palette}" data-grid="premium" data-gallery-premium="deck" data-gallery-mobile="premium" data-gallery-desktop="premium">
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

(async () => {
    const browser = await chromium.launch({headless: true, executablePath: process.env.TEST_BROWSER || undefined, args: ['--no-sandbox']});
    try {
        for (const device of devices) {
            const context = await browser.newContext({viewport: device.viewport, hasTouch: !!device.touch, isMobile: !!device.touch});
            const page = await context.newPage();
            const errors = [];
            page.on('pageerror', error => errors.push(error.message));
            await page.route('**/*', route => {
                const url = route.request().url();
                if (url.endsWith('.webp')) return route.fulfill({status: 200, contentType: 'image/png', body: png});
                if (url === 'https://deck.test/') return route.fulfill({status: 200, contentType: 'text/html', body: pageHtml('current')});
                return route.fulfill({status: 200, contentType: 'text/plain', body: ''});
            });
            await page.goto('https://deck.test/');
            await page.addStyleTag({content: css});
            for (const script of scripts) await page.addScriptTag({content: script});
            const label = device.name;
            const state = () => page.evaluate(() => ({
                index: Number(document.querySelector('#deck').dataset.index), pinned: document.documentElement.classList.contains('deck-pinned'),
                scrollY: document.documentElement.classList.contains('deck-pinned') ? -parseInt(document.body.style.top, 10) : Math.round(scrollY), top: Math.round(document.querySelector('#deck').getBoundingClientRect().top),
                positions: [...document.querySelectorAll('.deck__card')].map(card => card.dataset.pos).join(','),
                overflow: document.documentElement.scrollWidth > innerWidth + 1,
            }));
            // Lleva la baraja al borde superior (reintenta: la página puede recolocarse mientras carga).
            const toDeck = async () => {
                for (let attempt = 0; attempt < 14; attempt++) {
                    // Táctil: la baraja se fija al llegar (body fixed): ya no hay nada que alinear.
                    if (device.touch && await page.evaluate(() => document.documentElement.classList.contains('deck-pinned'))) return;
                    await page.evaluate(() => window.scrollTo({top: document.querySelector('#deck').getBoundingClientRect().top + scrollY, behavior: 'instant'}));
                    await page.waitForTimeout(150);
                    if (Math.abs(await page.evaluate(() => document.querySelector('#deck').getBoundingClientRect().top)) <= 2) return;
                }
            };
            // Táctil: suelta la baraja (si está fijada) y vuelve arriba del todo, esperando a que acabe la salida.
            const freeTouch = async () => {
                if (await page.evaluate(() => document.documentElement.classList.contains('deck-pinned'))) { await page.click('[data-deck-exit-up]'); await page.waitForTimeout(2000); }
                await page.evaluate(() => window.scrollTo({top: 0, behavior: 'instant'}));
                await page.waitForTimeout(500);
            };
            const settle = () => page.waitForTimeout(1300);  // El paso dura 850 ms (margen para dispositivos lentos).
            // Gestos táctiles reales (CDP) para móvil y tablet.
            const cdp = device.touch ? await context.newCDPSession(page) : null;
            const swipe = async (dy, steps = 8, wait = 1300, fromY = device.viewport.height * 0.6) => {
                const x = device.viewport.width / 2;
                await cdp.send('Input.dispatchTouchEvent', {type: 'touchStart', touchPoints: [{x, y: fromY}]});
                for (let i = 1; i <= steps; i++) await cdp.send('Input.dispatchTouchEvent', {type: 'touchMove', touchPoints: [{x, y: fromY + dy * i / steps}]});
                await cdp.send('Input.dispatchTouchEvent', {type: 'touchEnd', touchPoints: []});
                await page.waitForTimeout(wait);
            };

            // 1. A pantalla completa: 100 % de ancho y de alto del dispositivo.
            await toDeck();
            const box = await page.evaluate(() => {
                const r = document.querySelector('#deck').getBoundingClientRect();
                return {w: r.width, h: r.height, top: r.top, vw: innerWidth, vh: innerHeight};
            });
            assert(Math.abs(box.w - box.vw) < 1 && Math.abs(box.h - box.vh) < 1, `${label}: la baraja debe ocupar toda la pantalla (${box.w}×${box.h} en ${box.vw}×${box.vh})`);
            assert(Math.abs(box.top) <= 2, `${label}: la baraja debe quedar alineada arriba (top=${box.top}, scrollY=${await page.evaluate(() => scrollY)})`);
            let s = await state();
            assert.equal(s.positions, '0,1,2,3,4,4', `${label}: posiciones iniciales del mazo`);
            assert(!s.overflow, `${label}: desbordamiento horizontal`);
            const baseScroll = s.scrollY;   // Referencia para comprobar que, fuera de los límites, la página sí se desplaza.

            // 2. Teclado: avanza y retrocede sin mover la página.
            await page.keyboard.press('ArrowDown'); await settle();
            s = await state();
            assert.equal(s.index, 1, `${label}: ArrowDown avanza`);
            assert.equal(s.positions, '-1,0,1,2,3,4', `${label}: la carta pasada sale y el mazo avanza`);
            assert(Math.abs(s.top) <= 2, `${label}: el fondo no debe moverse (la baraja se desplazó a top=${s.top})`);
            await page.keyboard.press('ArrowUp'); await settle();
            assert.equal((await state()).index, 0, `${label}: ArrowUp retrocede`);

            // 2b. Parada al llegar: el scroll de la página se detiene cuando la baraja llena la pantalla, aunque el gesto
            //     traiga inercia (aquí, un salto de scroll que pasa de largo), y las ruedas que sigan llegando no pasan
            //     fotos. Pasada la parada, un gesto nuevo ya mueve las fotos.
            const wheelBurst = (count, gap) => page.evaluate(([count, gap]) => new Promise(resolve => {
                let sent = 0;
                const timer = setInterval(() => {
                    window.dispatchEvent(new WheelEvent('wheel', {deltaY: 120, bubbles: true, cancelable: true}));
                    if (++sent >= count) { clearInterval(timer); resolve(); }
                }, gap);
            }), [count, gap]);
            if (device.touch) {
                // Táctil: al llegar la baraja se FIJA (body fixed): la página no se mueve, ni siquiera con un salto que pasa de largo.
                await freeTouch();
                await page.evaluate(() => window.scrollTo({top: document.querySelector('#deck').getBoundingClientRect().top + scrollY + 280, behavior: 'instant'}));
                await page.waitForTimeout(700);
                const pinned = await state();
                assert(pinned.pinned && Math.abs(pinned.top) <= 1 && pinned.index === 0, `${label}: la baraja debe quedar fijada al llegar (${JSON.stringify(pinned)})`);
                assert.equal(await page.evaluate(() => getComputedStyle(document.body).position), 'fixed', `${label}: el cuerpo de la página debe quedar fijo`);
                // Estable: ni un píxel de movimiento (sin vibración) durante un segundo, ni con intentos de scroll programático.
                const tops = [];
                for (let i = 0; i < 10; i++) {
                    await page.evaluate(dy => window.scrollBy(0, dy), i % 2 ? 40 : -40);
                    tops.push((await state()).top); await page.waitForTimeout(100);
                }
                assert(tops.every(top => top === 0), `${label}: la baraja fijada no debe moverse (${tops.join(',')})`);
                await swipe(-140);
                assert.equal((await state()).index, 1, `${label}: tras fijarse, el dedo mueve las fotos`);
                await page.keyboard.press('ArrowUp'); await settle();
                await toDeck();
            } else {
            await page.evaluate(() => window.scrollTo({top: 0, behavior: 'instant'}));
            await page.waitForTimeout(500);
            await page.evaluate(() => window.scrollTo({top: document.querySelector('#deck').getBoundingClientRect().top + scrollY + 280, behavior: 'instant'}));
            await wheelBurst(12, 30);                       // «Cola» del gesto: llega justo después del salto.
            await page.waitForTimeout(900);
            const arrived = await state();
            assert(Math.abs(arrived.top) <= 2 && arrived.index === 0, `${label}: la página debe detenerse en la baraja sin pasar fotos (${JSON.stringify(arrived)})`);
            await wheelBurst(1, 10); await settle();         // Un gesto nuevo, tras la pausa.
            assert.equal((await state()).index, 1, `${label}: tras la parada, el siguiente gesto mueve las fotos`);
            await page.keyboard.press('ArrowUp'); await settle();
            await toDeck();
            }

            // 3. Animación: la carta pasada acaba fuera (a la izquierda, pequeña y transparente) y la activa a pantalla completa.
            await page.keyboard.press('ArrowDown'); await settle();
            await page.waitForFunction(() => getComputedStyle(document.querySelector('.deck__card')).opacity === '0', null, {timeout: 4000});
            const duration = await page.evaluate(() => parseFloat(getComputedStyle(document.querySelector('#deck')).getPropertyValue('--deck-duration')));
            assert(duration >= 700 && duration <= 1200, `${label}: el movimiento debe ser pausado pero ágil (${duration} ms)`);
            const anim = await page.evaluate(() => {
                const [gone, active] = document.querySelectorAll('.deck__card');
                const leaving = getComputedStyle(gone), current = getComputedStyle(document.querySelectorAll('.deck__card')[1]);
                const m = new DOMMatrix(leaving.transform);
                return {goneOpacity: leaving.opacity, goneVisible: leaving.visibility, goneX: m.m41, goneScale: m.m11,
                    activeOpacity: current.opacity, activeScale: new DOMMatrix(current.transform).m11};
            });
            assert.equal(anim.goneOpacity, '0', `${label}: la carta pasada se desvanece`);
            assert(anim.goneX < 0, `${label}: la carta pasada sale hacia la izquierda`);
            assert(anim.goneScale < 0.7, `${label}: la carta pasada se encoge`);
            assert.equal(anim.activeOpacity, '1');
            assert(Math.abs(anim.activeScale - 1) < 0.001, `${label}: la carta activa está a tamaño completo`);
            // El mazo conserva un aspecto 3D: las cartas de debajo están más al fondo (matrix3d con z negativa).
            const depth = await page.evaluate(() => new DOMMatrix(getComputedStyle(document.querySelectorAll('.deck__card')[3]).transform).m43);
            assert(depth < 0, `${label}: el mazo debe tener profundidad 3D`);
            await page.keyboard.press('ArrowUp'); await settle();

            // 4. Rueda del ratón (solo escritorio/portátil): hacia abajo avanza; el fondo no se mueve.
            if (!device.touch) {
                await page.mouse.move(device.viewport.width / 2, device.viewport.height / 2);
                await page.mouse.wheel(0, 120); await settle();
                s = await state();
                assert.equal(s.index, 1, `${label}: la rueda hacia abajo avanza`);
                assert(Math.abs(s.top) <= 2, `${label}: la rueda no debe mover el fondo (top=${s.top})`);
                await page.mouse.wheel(0, -120); await settle();
                assert.equal((await state()).index, 0, `${label}: la rueda hacia arriba retrocede`);
                // Límite inferior: en la última foto la rueda libera la página.
                await page.keyboard.press('End'); await settle();
                const last = (await state()).index;
                assert.equal(last, 5, `${label}: End va a la última foto`);
                await page.mouse.wheel(0, 400);
                // El scroll de la página es animado: se espera a que llegue (más lento en los ordenadores de CI).
                await page.waitForFunction(min => scrollY > min, baseScroll + 50, {timeout: 6000}).catch(() => {});
                { const after = await state(); assert(after.scrollY > baseScroll + 50, `${label}: tras la última foto la página debe seguir bajando (${JSON.stringify({baseScroll, ...after})})`); }
                await toDeck();
                await page.keyboard.press('Home'); await settle();
                // Límite superior: en la primera foto la rueda hacia arriba libera la página.
                await page.mouse.wheel(0, -400);
                await page.waitForFunction(max => scrollY < max, baseScroll - 50, {timeout: 6000}).catch(() => {});
                const top = await state();
                assert(top.scrollY < baseScroll - 50 && top.index === 0, `${label}: antes de la primera foto la página debe subir (${JSON.stringify({baseScroll, ...top})})`);
                await toDeck();
            }

            // 5. Táctil (móvil y tablet): dedo hacia arriba avanza, hacia abajo retrocede, sin mover el fondo.
            if (device.touch) {
                const before = await state();
                await swipe(-140);
                s = await state();
                assert.equal(s.index, before.index + 1, `${label}: deslizar hacia arriba avanza (antes ${JSON.stringify(before)}, después ${JSON.stringify(s)}, top=${await page.evaluate(() => document.querySelector('#deck').getBoundingClientRect().top)})`);
                assert(Math.abs(s.top - before.top) <= 2, `${label}: el dedo no debe mover el fondo (top ${before.top} → ${s.top})`);
                await swipe(140);
                s = await state();
                assert.equal(s.index, before.index, `${label}: deslizar hacia abajo retrocede`);
                assert(Math.abs(s.top - before.top) <= 2, `${label}: el dedo no debe mover el fondo al retroceder (top ${before.top} → ${s.top})`);
                // Límite: en la última foto, deslizar hacia arriba deja que la página siga.
                await page.keyboard.press('End'); await settle();
                await swipe(-260, 12);
                await page.waitForFunction(min => scrollY > min, before.scrollY + 40, {timeout: 6000}).catch(() => {});
                { const after = await state(); assert(after.scrollY > before.scrollY + 40, `${label}: tras la última foto el dedo debe mover la página (${JSON.stringify({before, after})})`); }
                await toDeck();
                await page.keyboard.press('Home'); await settle();
            }

            // 5b. Pies de foto sin solaparse: mientras una carta sale y otra llega, nunca se ven los dos pies a la vez.
            if (!device.touch) {
                await toDeck();
                const overlap = await page.evaluate(() => new Promise(resolve => {
                    const cards = [...document.querySelectorAll('.deck__card')];
                    let worst = 0; const t0 = performance.now();
                    document.dispatchEvent(new KeyboardEvent('keydown', {key: 'ArrowDown', bubbles: true}));
                    const tick = () => {
                        const opacities = cards.slice(0, 3).map(card => parseFloat(getComputedStyle(card.querySelector('.deck__caption')).opacity));
                        const sorted = opacities.sort((x, y) => y - x);
                        worst = Math.max(worst, sorted[1]);          // El segundo pie más visible: debe ser casi invisible.
                        if (performance.now() - t0 < 1600) requestAnimationFrame(tick); else resolve(worst);
                    };
                    tick();
                }));
                assert(overlap < 0.35, `${label}: los pies de foto se solapan al pasar de carta (${overlap.toFixed(2)})`);
                await settle();
                await page.keyboard.press('Home'); await settle();
            }

            // 5c. Móvil: con la baraja acoplada el navegador NO debe poder desplazar la página con el dedo
            //     (touch-action: none), aunque el gesto empiece despacio; y deslizar más allá de la primera foto sale hacia arriba.
            if (device.touch) {
                await toDeck();
                assert.equal(await page.evaluate(() => getComputedStyle(document.querySelector('#deck')).touchAction), 'none', `${label}: la baraja acoplada debe usar touch-action: none`);
                const slowBefore = await state();
                const x = device.viewport.width / 2; let y = device.viewport.height * 0.62;
                await cdp.send('Input.dispatchTouchEvent', {type: 'touchStart', touchPoints: [{x, y}]});
                for (let i = 1; i <= 70; i++) { await cdp.send('Input.dispatchTouchEvent', {type: 'touchMove', touchPoints: [{x, y: y - 2 * i}]}); }
                await cdp.send('Input.dispatchTouchEvent', {type: 'touchEnd', touchPoints: []});
                await page.waitForTimeout(1500);
                s = await state();
                assert.equal(s.index, slowBefore.index + 1, `${label}: un gesto lento también avanza una foto`);
                assert(Math.abs(s.top - slowBefore.top) <= 2, `${label}: un gesto lento no debe mover la página (top ${slowBefore.top} → ${s.top})`);
                await page.keyboard.press('Home'); await settle();
                await toDeck();
                const upBefore = await state();
                await swipe(260, 12, 1500);
                await page.waitForFunction(max => scrollY < max, upBefore.scrollY - 100, {timeout: 6000}).catch(() => {});
                const up = await state();
                assert(up.scrollY < upBefore.scrollY - 100 && up.index === 0, `${label}: deslizar hacia abajo en la primera foto debe subir la página (${JSON.stringify({upBefore, up})})`);
                await toDeck();
            }

            // 5d. Botón «Salir»: baja la página hasta lo que sigue a la galería y no vuelve a frenarse en ella.
            //     Mientras la baraja llena la pantalla, los botones flotantes de la web se apartan; al salir, vuelven.
            await toDeck();
            // Los botones flotantes se apartan con una transición de 250 ms: se espera a que termine.
            await page.waitForFunction(() => getComputedStyle(document.querySelector('.upload-fab')).opacity === '0', null, {timeout: 4000}).catch(() => {});
            const fabHidden = await page.evaluate(() => getComputedStyle(document.querySelector('.upload-fab')).opacity);
            assert.equal(fabHidden, '0', `${label}: los botones flotantes deben apartarse mientras la baraja llena la pantalla`);
            const exitBefore = await state();
            await page.click('[data-deck-exit]');
            await page.waitForFunction(() => document.querySelector('#after').getBoundingClientRect().top <= 5, null, {timeout: 6000}).catch(() => {});
            await page.waitForTimeout(900);
            await page.waitForFunction(() => getComputedStyle(document.querySelector('.upload-fab')).opacity === '1', null, {timeout: 4000}).catch(() => {});
            const left = await page.evaluate(() => ({
                afterTop: Math.round(document.querySelector('#after').getBoundingClientRect().top), scrollY: Math.round(scrollY),
                deckBottom: Math.round(document.querySelector('#deck').getBoundingClientRect().bottom), fab: getComputedStyle(document.querySelector('.upload-fab')).opacity,
                engaged: document.documentElement.classList.contains('deck-engaged'), index: Number(document.querySelector('#deck').dataset.index)}));
            assert(left.afterTop <= 5 && left.deckBottom <= 5, `${label}: «Salir» debe dejar la web siguiente a pantalla completa (${JSON.stringify(left)})`);
            assert.equal(left.engaged, false, `${label}: al salir la baraja ya no está acoplada`);
            assert.equal(left.fab, '1', `${label}: al salir vuelven los botones flotantes`);
            assert.equal(left.index, exitBefore.index, `${label}: salir no cambia la foto`);
            assert(Math.abs(left.scrollY - (exitBefore.scrollY + box.vh)) <= 3, `${label}: «Salir» baja exactamente una pantalla (${JSON.stringify({left, exitBefore})})`);
            // Subir otra vez hacia la baraja: se detiene en ella (parada) y no se queda pegada abajo.
            await page.evaluate(() => window.scrollTo({top: 0, behavior: 'instant'}));
            await page.waitForTimeout(400);
            await toDeck();

            // 5e. Botón «Salir hacia arriba»: sube hasta lo que hay antes de la baraja (hay una cabecera encima) y no vuelve a frenarse.
            await toDeck();
            assert.equal(await page.evaluate(() => document.querySelector('[data-deck-exit-up]').hidden), false, `${label}: con contenido encima debe haber botón para salir hacia arriba`);
            const upExitBefore = await state();
            await page.click('[data-deck-exit-up]');
            await page.waitForFunction(() => document.querySelector('#deck').getBoundingClientRect().top >= innerHeight - 5, null, {timeout: 6000}).catch(() => {});
            await page.waitForTimeout(600);
            const exitedUp = await page.evaluate(() => ({scrollY: Math.round(scrollY), deckTop: Math.round(document.querySelector('#deck').getBoundingClientRect().top),
                vh: innerHeight, engaged: document.documentElement.classList.contains('deck-engaged'), index: Number(document.querySelector('#deck').dataset.index)}));
            assert(exitedUp.scrollY < upExitBefore.scrollY - 100 && exitedUp.engaged === false, `${label}: «Salir hacia arriba» debe subir por encima de la baraja (${JSON.stringify({upExitBefore, exitedUp})})`);
            assert.equal(exitedUp.index, upExitBefore.index, `${label}: salir hacia arriba no cambia la foto`);
            await page.evaluate(() => window.scrollTo({top: 0, behavior: 'instant'}));
            await page.waitForTimeout(400);
            await toDeck();

            // 6. Marco uniforme y mazo visible, con el fondo de la web (sin fondo desenfocado con los colores de la foto).
            assert.equal(await page.evaluate(() => document.querySelector('.deck__backdrop')), null, `${label}: no debe haber fondo con los colores de la foto`);
            const frames = await page.evaluate(() => [...document.querySelectorAll('.deck__card')].map(card => ({
                w: card.clientWidth, h: card.clientHeight, aspect: Number(card.dataset.aspect), contain: card.classList.contains('is-contain'),
                fit: getComputedStyle(card.querySelector('.deck__img')).objectFit, pos: Number(card.dataset.pos), right: card.getBoundingClientRect().right,
                bottom: card.getBoundingClientRect().bottom, left: card.getBoundingClientRect().left})));
            assert(frames.every(f => f.w === frames[0].w && f.h === frames[0].h), `${label}: todas las cartas deben tener el mismo marco`);
            const frameRatio = frames[0].w / frames[0].h;
            for (const fit of frames) {
                const expectContain = Math.abs(Math.log(fit.aspect / frameRatio)) > 0.3;
                assert.equal(fit.contain, expectContain, `${label}: ajuste de la foto ${fit.aspect} en un marco ${frameRatio.toFixed(2)}`);
                assert.equal(fit.fit, expectContain ? 'contain' : 'cover');
            }
            const [active, p1, p2, p3] = [0, 1, 2, 3].map(pos => frames.find(f => f.pos === pos));
            assert(p1.right > active.right + 6 && p2.right > p1.right + 6 && p3.right > p2.right + 6, `${label}: las cartas del mazo deben asomar por la derecha, una tras otra (${[active, p1, p2, p3].map(f => Math.round(f.right)).join(',')})`);
            assert(p3.right <= box.vw, `${label}: el mazo debe caber en la pantalla (${p3.right} > ${box.vw})`);
            assert(active.left >= 8 && active.bottom <= box.vh - 40, `${label}: la carta activa deja sitio a los controles (${JSON.stringify(active)})`);
            const decks = await page.evaluate(() => [1, 2, 3].map(pos => getComputedStyle(document.querySelector(`.deck__card[data-pos="${pos}"]`)).transform));
            assert.equal(new Set(decks).size, 3, `${label}: cada carta del mazo debe tener su propia posición y giro`);
            assert(await page.evaluate(() => [1, 2, 3].every(pos => Number(getComputedStyle(document.querySelector(`.deck__card[data-pos="${pos}"]`)).opacity) >= 0.85)), `${label}: el mazo debe verse (opacidad)`);

            // 7. Filtros: categoría y favoritas (con main.js real). En táctil se suelta la baraja: los chips están fuera de ella.
            if (device.touch) await freeTouch();
            await page.click('.categories-filter__chip[data-category="B"]');
            s = await page.evaluate(() => ({total: document.querySelector('[data-deck-total]').textContent, hidden: [...document.querySelectorAll('.deck__card')].filter(c => c.hidden).length}));
            assert.deepEqual(s, {total: '3', hidden: 3}, `${label}: el filtro por categoría deja 3 fotos`);
            await page.click('.categories-filter__chip[data-category=""]');
            await page.click('#favoritesToggle');
            assert.equal(await page.evaluate(() => document.querySelector('[data-deck-empty]').hidden), false, `${label}: sin favoritas debe avisar`);
            await page.click('#favoritesToggle');
            assert.equal(await page.evaluate(() => document.querySelector('[data-deck-total]').textContent), '6');

            assert.deepEqual(errors, [], `${label}: errores de JavaScript: ${errors.join(' | ')}`);
            await context.close();
        }

        // 6b. Centrado: el conjunto carta + mazo queda centrado en horizontal y en vertical en cualquier pantalla.
        const sizes = [[320, 568], [360, 640], [375, 667], [390, 844], [412, 915], [430, 932], [768, 1024], [820, 1180], [1024, 768], [1280, 720], [1440, 900], [1920, 1080], [2560, 1440], [844, 390], [667, 375]];
        for (const [width, height] of sizes) {
            const touch = width < 800 || height < 500;
            const centering = await browser.newContext({viewport: {width, height}, hasTouch: touch, isMobile: touch});
            const cpage = await centering.newPage();
            await cpage.route('**/*', route => route.request().url() === 'https://deck.test/'
                ? route.fulfill({status: 200, contentType: 'text/html', body: pageHtml('current')})
                : route.fulfill({status: 200, contentType: 'image/png', body: png}));
            await cpage.goto('https://deck.test/');
            await cpage.addStyleTag({content: css});
            await cpage.waitForTimeout(1200);
            const m = await cpage.evaluate(() => {
                const deck = document.querySelector('#deck').getBoundingClientRect();
                const boxes = [0, 1, 2, 3].map(pos => document.querySelector(`.deck__card[data-pos="${pos}"]`).getBoundingClientRect());
                const left = Math.min(...boxes.map(b => b.left)) - deck.left, right = Math.max(...boxes.map(b => b.right)) - deck.left;
                const top = Math.min(...boxes.map(b => b.top)) - deck.top, bottom = Math.max(...boxes.map(b => b.bottom)) - deck.top;
                const active = boxes[0];
                return {dx: (left + right) / 2 - deck.width / 2, dy: (top + bottom) / 2 - deck.height / 2,
                    activeDx: (active.left + active.right) / 2 - (deck.left + deck.width / 2), activeDy: (active.top + active.bottom) / 2 - (deck.top + deck.height / 2),
                    left, right, top, bottom, w: deck.width, h: deck.height};
            });
            const where = `${width}×${height}`;
            assert(Math.abs(m.dx) <= 7 && Math.abs(m.dy) <= 6, `Centrado ${where}: el conjunto carta + mazo debe estar centrado (dx=${m.dx.toFixed(1)}, dy=${m.dy.toFixed(1)})`);
            assert(Math.abs(m.activeDy) <= 14, `Centrado ${where}: la carta activa debe quedar centrada en vertical (dy=${m.activeDy.toFixed(1)})`);
            assert(m.left >= 4 && m.right <= m.w - 4 && m.top >= 4 && m.bottom <= m.h - 4, `Centrado ${where}: el conjunto debe caber en la pantalla (${JSON.stringify(m)})`);
            await centering.close();
        }

        // 7b. Volver desde la ficha de una foto: /#baraja=<slug> abre la baraja en esa foto, a pantalla completa.
        for (const viewport of [{width: 1440, height: 900}, {width: 390, height: 844, touch: true}]) {
            const backContext = await browser.newContext({viewport: {width: viewport.width, height: viewport.height}, hasTouch: !!viewport.touch, isMobile: !!viewport.touch});
            const back = await backContext.newPage();
            const backErrors = [];
            back.on('pageerror', error => backErrors.push(error.message));
            await back.route('**/*', route => {
                const url = route.request().url();
                if (url.endsWith('.webp')) return route.fulfill({status: 200, contentType: 'image/png', body: png});
                if (url === 'https://deck.test/') return route.fulfill({status: 200, contentType: 'text/html', body: pageHtml('current')});
                return route.fulfill({status: 200, contentType: 'text/plain', body: ''});
            });
            await back.goto('https://deck.test/#baraja=foto-3');
            await back.addStyleTag({content: css});
            for (const script of scripts) await back.addScriptTag({content: script});
            await back.waitForTimeout(1800);
            const returned = await back.evaluate(() => ({
                index: Number(document.querySelector('#deck').dataset.index), top: Math.round(document.querySelector('#deck').getBoundingClientRect().top),
                hash: location.hash, active: document.querySelector('.deck__card[data-pos="0"]')?.dataset.slug,
                vh: innerHeight, deckHeight: Math.round(document.querySelector('#deck').getBoundingClientRect().height)}));
            assert.equal(returned.index, 3, `Volver desde la ficha (${viewport.width}px): debe abrir la foto 4 (${JSON.stringify(returned)})`);
            assert.equal(returned.active, 'foto-3', 'La carta activa debe ser la de la ficha');
            assert(Math.abs(returned.top) <= 2 && returned.deckHeight === returned.vh, `Volver desde la ficha (${viewport.width}px): la baraja debe quedar a pantalla completa (${JSON.stringify(returned)})`);
            assert.equal(returned.hash, '', 'El enlace de vuelta no debe quedarse en la dirección');
            assert.deepEqual(backErrors, [], 'Volver desde la ficha: errores de JavaScript');
            if (viewport.touch) assert.equal(await back.evaluate(() => document.documentElement.classList.contains('deck-pinned')), true, 'Volver desde la ficha (móvil): la baraja debe quedar fijada');
            await backContext.close();
        }

        // 8. Integración con la paleta: el fondo y los controles usan las variables de cada paleta.
        const expected = {current: null, white: 'rgb(255, 255, 255)', ocean: 'rgb(16, 39, 55)', japanese: 'rgb(247, 242, 232)'};
        for (const [palette, background] of Object.entries(expected)) {
            const page = await browser.newPage({viewport: {width: 1280, height: 720}});
            await page.route('**/*', route => route.request().url() === 'https://deck.test/'
                ? route.fulfill({status: 200, contentType: 'text/html', body: pageHtml(palette)})
                : route.fulfill({status: 200, contentType: 'image/png', body: png}));
            await page.goto('https://deck.test/');
            await page.addStyleTag({content: css});
            const colors = await page.evaluate(() => ({
                deck: getComputedStyle(document.querySelector('#deck')).backgroundColor,
                body: getComputedStyle(document.body).getPropertyValue('--bg').trim()}));
            if (background) assert.equal(colors.deck, background, `Paleta ${palette}: el fondo de la baraja debe seguir la paleta`);
            else assert.notEqual(colors.deck, 'rgba(0, 0, 0, 0)', 'Paleta por defecto: la baraja debe tener fondo');
            await page.close();
        }

        // 9. Movimiento reducido: el paso es prácticamente instantáneo.
        const reduced = await browser.newPage({viewport: {width: 1280, height: 720}});
        await reduced.emulateMedia({reducedMotion: 'reduce'});
        await reduced.route('**/*', route => route.request().url() === 'https://deck.test/'
            ? route.fulfill({status: 200, contentType: 'text/html', body: pageHtml('current')})
            : route.fulfill({status: 200, contentType: 'image/png', body: png}));
        await reduced.goto('https://deck.test/');
        await reduced.addStyleTag({content: css});
        for (const script of scripts) await reduced.addScriptTag({content: script});
        await reduced.waitForTimeout(300);
        await reduced.evaluate(() => window.scrollTo({top: document.querySelector('#deck').getBoundingClientRect().top + scrollY, behavior: 'instant'}));
        await reduced.waitForTimeout(300);
        await reduced.keyboard.press('ArrowDown');
        // Con movimiento reducido el paso dura ~1 ms: la carta pasada queda transparente casi al instante.
        await reduced.waitForFunction(() => getComputedStyle(document.querySelector('.deck__card')).opacity === '0', null, {timeout: 400});
        await reduced.close();

        console.log('Baraja verificada: pantalla completa, parada del scroll al llegar, rueda, dedo, teclado, límites, ajuste de foto, filtros, vuelta desde la ficha y paletas en 5 dispositivos.');
    } finally { await browser.close(); }
})().catch(error => { console.error(error); process.exit(1); });
