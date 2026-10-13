#!/usr/bin/env node
/* =============================================================================
   Matriz de capturas visuales. Red de seguridad de la reestructuración: detecta cualquier cambio de aspecto
   no intencionado en las combinaciones de personalización que ofrece la web.

   Cómo funciona
     1. Copia el proyecto a una carpeta temporal (nunca toca los datos reales ni la copia de seguridad).
     2. Le crea fotos sintéticas y posts de microblog con fechas fijas (tests/fixtures/make-site-fixtures.php).
     3. Recorre el catálogo REAL de opciones (tests/fixtures/dump-choices.php): paletas, cabeceras de móvil y de
        escritorio, galerías estándar y premium, y el microblog. Una opción nueva entra sola en la matriz.
     4. Compara cada captura con la de referencia (tests/visual/baseline/, fuera de git).

   Uso
     node tests/visual-matrix.cjs                   modo rápido (por defecto, ~3 min): el mínimo de escenarios que usa cada opción al menos una vez
     node tests/visual-matrix.cjs --full            los 218 escenarios (~15 min)
     node tests/visual-matrix.cjs --update          guarda las capturas actuales como referencia (con --full, todas; sin él, solo las rápidas)
     node tests/visual-matrix.cjs --only=hd-museum  solo los escenarios cuyo nombre contenga el texto
     node tests/visual-matrix.cjs --list            muestra los escenarios sin ejecutarlos

   Variables: PHP (ejecutable de PHP con GD), TEST_BROWSER (Chromium), VISUAL_PORT (puerto, por defecto 8765),
   VISUAL_TOLERANCE (porcentaje máximo de píxeles distintos, por defecto 0.6).
   ============================================================================= */
'use strict';

const fs = require('fs');
const os = require('os');
const path = require('path');
const { spawn, execFileSync } = require('child_process');
const { chromium } = require('playwright');

const root = path.join(__dirname, '..');
const php = process.env.PHP || 'php';
const port = Number(process.env.VISUAL_PORT || 8765);
const tolerance = Number(process.env.VISUAL_TOLERANCE || 0.6);
const args = process.argv.slice(2);
const update = args.includes('--update');
const listOnly = args.includes('--list');
const full = args.includes('--full') || process.env.VISUAL_FULL === '1';
const only = (args.find(a => a.startsWith('--only=')) || '').slice(7);
const dirs = { baseline: path.join(__dirname, 'visual/baseline'), current: path.join(__dirname, 'visual/current'), diff: path.join(__dirname, 'visual/diff') };

const tempRoot = path.join(os.tmpdir(), 'djl-visual-matrix');
const siteDir = path.join(tempRoot, 'site');
const configDir = path.join(tempRoot, 'config');

// ---------------------------------------------------------------------------------------------------------------
// 1-2. Copia temporal del sitio con datos de prueba
// ---------------------------------------------------------------------------------------------------------------
function prepareSite() {
    fs.rmSync(tempRoot, { recursive: true, force: true });
    const skip = new Set(['.git', 'node_modules', 'img', 'imagenes', 'data', 'microblog', 'log', 'var', 'visual', 'subirweb']);
    fs.cpSync(root, siteDir, { recursive: true, filter: source => !skip.has(path.basename(source)) || source === root });
    fs.mkdirSync(path.join(siteDir, 'data'), { recursive: true });
    fs.mkdirSync(path.join(siteDir, 'imagenes/desktop'), { recursive: true });
    fs.mkdirSync(path.join(siteDir, 'imagenes/mobile'), { recursive: true });
    fs.mkdirSync(configDir, { recursive: true });
    execFileSync(php, [path.join(siteDir, 'tests/fixtures/make-site-fixtures.php'), siteDir, configDir], { stdio: 'pipe' });
}

function readChoices() {
    const output = execFileSync(php, [path.join(siteDir, 'tests/fixtures/dump-choices.php'), siteDir], { encoding: 'utf8', env: { ...process.env, SITE_CONFIG_DIR: configDir } });
    // Algunos PHP escriben avisos de arranque en la salida estándar: se toma solo el JSON.
    return JSON.parse(output.slice(output.indexOf('{'), output.lastIndexOf('}') + 1));
}

// ---------------------------------------------------------------------------------------------------------------
// 3. Escenarios
// ---------------------------------------------------------------------------------------------------------------
const slug = value => String(value).toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '');

function buildScenarios(choices) {
    const desktop = { width: 1280, height: 800 };
    const mobile = { width: 390, height: 800 };
    const scenarios = [];
    const add = (name, viewport, settings, extra = {}) => scenarios.push({ name: slug(name), viewport, settings, ...extra });

    // Cabeceras × paletas: solo la primera pantalla (es lo que cambia).
    for (const header of choices.header_desktop) for (const palette of choices.palette) {
        add(`hd-${header}-${palette}`, desktop, { header_desktop: header, palette }, { clipHeight: 800 });
    }
    for (const header of choices.header_mobile) for (const palette of choices.palette) {
        add(`hm-${header}-${palette}`, mobile, { header_mobile: header, palette }, { clipHeight: 800 });
    }
    // Página completa por paleta (todas las secciones), en escritorio y móvil.
    for (const palette of choices.palette) {
        add(`page-${palette}-desktop`, desktop, { palette }, { clipHeight: 6000 });
        add(`page-${palette}-mobile`, mobile, { palette }, { clipHeight: 6000 });
    }
    // Galerías estándar (cada una en su dispositivo) sobre la paleta y la cabecera por defecto.
    for (const layout of choices.gallery_desktop) add(`gal-${layout}-desktop`, desktop, { gallery_desktop: layout }, { scrollTo: '#galeria', clipHeight: 1800 });
    for (const layout of choices.gallery_mobile) add(`gal-${layout}-mobile`, mobile, { gallery_mobile: layout }, { scrollTo: '#galeria', clipHeight: 1800 });
    // Galerías premium.
    for (const premium of choices.gallery_premium.filter(key => key !== 'none')) {
        add(`premium-${premium}-desktop`, desktop, { gallery_premium: premium }, { scrollTo: '#galeria', clipHeight: 800 });
        add(`premium-${premium}-mobile`, mobile, { gallery_premium: premium }, { scrollTo: '#galeria', clipHeight: 800 });
    }
    // Efectos hover y forma de la paginación no se ven sin interacción: se comprueban en sus pruebas propias.
    // Tipografías: una captura de la cabecera con cada pareja.
    for (const pair of choices.font_pair) add(`font-${pair}`, desktop, { font_pair: pair }, { clipHeight: 800 });
    // Microblog, en escritorio y móvil, con distintas paletas.
    for (const palette of ['current', 'white', 'ocean']) {
        add(`mb-${palette}-desktop`, desktop, { palette }, { scrollTo: '#microblog', clipHeight: 2600 });
        add(`mb-${palette}-mobile`, mobile, { palette }, { scrollTo: '#microblog', clipHeight: 2600 });
    }
    add('mb-hidden-desktop', desktop, { show_microblog: false }, { clipHeight: 800 });
    // Web mínima: todos los bloques opcionales ocultos (comprueba que no se necesitan sus estilos ni scripts).
    const hidden = { show_map: false, show_project: false, show_microblog: false, show_social: false, show_categories: false };
    add('min-desktop', desktop, hidden, { clipHeight: 2400 });
    add('min-mobile', mobile, hidden, { clipHeight: 2400 });
    // Otras páginas.
    add('foto-detail-desktop', desktop, {}, { url: '/foto/foto-de-prueba-3', clipHeight: 1400 });
    add('foto-detail-mobile', mobile, {}, { url: '/foto/foto-de-prueba-3', clipHeight: 1800 });
    // Página estática: sin JavaScript (pwa.js recarga la página en el entorno de pruebas al no poder registrar el service worker).
    add('legal-desktop', desktop, {}, { url: '/aviso-legal.php', clipHeight: 1200, noScript: true });
    add('legal-mobile', mobile, {}, { url: '/aviso-legal.php', clipHeight: 1400, noScript: true });
    add('privacy-desktop', desktop, { palette: 'white' }, { url: '/politica-privacidad.php', clipHeight: 1200, noScript: true });
    add('cookies-mobile', mobile, { palette: 'ocean' }, { url: '/politica-cookies.php', clipHeight: 1400, noScript: true });
    add('microblog-thread-desktop', desktop, {}, { url: '/microblog.php?id=aaaaaaaa03', clipHeight: 1200 });
    add('microblog-embed', { width: 560, height: 600 }, {}, { url: '/microblog.php?id=aaaaaaaa03&embed=1', clipHeight: 600 });
    return scenarios;
}

/**
 * Modo rápido: recubrimiento mínimo. Cada escenario «cubre» las opciones que usa (cada valor de cada ajuste, el tipo de
 * escenario, el dispositivo y la URL); se eligen, de golpe, los más baratos que cubren todas. Si se añade una opción, entra sola.
 */
function quickSubset(scenarios) {
    const tagsOf = scenario => new Set([
        // Las 30 parejas tipográficas se reparten en 3 grupos fijos (las fuentes se comprueban a fondo en tests/catalogs.php).
        ...Object.entries(scenario.settings || {}).map(([key, value]) => key + ':' + (key === 'font_pair' ? [...String(value)].reduce((sum, c) => sum + c.charCodeAt(0), 0) % 3 : value)),
        'kind:' + scenario.name.split('-')[0], 'width:' + scenario.viewport.width, 'url:' + (scenario.url || '/'),
    ]);
    const remaining = new Set(scenarios.flatMap(s => [...tagsOf(s)]));
    const chosen = [];
    while (remaining.size) {
        let best = null, bestScore = 0;
        for (const scenario of scenarios) {
            if (chosen.includes(scenario)) continue;
            const gain = [...tagsOf(scenario)].filter(tag => remaining.has(tag)).length;
            const score = gain / (scenario.clipHeight || scenario.viewport.height);
            if (gain && score > bestScore) { best = scenario; bestScore = score; }
        }
        if (!best) break;
        chosen.push(best);
        tagsOf(best).forEach(tag => remaining.delete(tag));
    }
    return scenarios.filter(s => chosen.includes(s));
}

// ---------------------------------------------------------------------------------------------------------------
// 4. Servidor PHP, capturas y comparación
// ---------------------------------------------------------------------------------------------------------------
async function startServer(worker) {
    const env = { ...process.env, SITE_CONFIG_DIR: worker.configDir, MICROBLOG_DATA_DIR: path.join(worker.configDir, 'microblog'), GALLERY_PRIVATE_DIR: worker.configDir };
    const server = spawn(php, ['-d', 'display_errors=0', '-S', `127.0.0.1:${worker.port}`, '-t', siteDir], { env, stdio: 'ignore' });
    for (let i = 0; i < 60; i++) {
        try { const response = await fetch(`http://127.0.0.1:${worker.port}/aviso-legal.php`); if (response.ok) return server; } catch (_) { /* aún arrancando */ }
        await new Promise(resolve => setTimeout(resolve, 250));
    }
    server.kill();
    throw new Error('El servidor PHP no arrancó.');
}

/** Espera a que las fuentes y todas las imágenes (también las diferidas) estén cargadas. */
async function waitForAssets(page) {
    await page.evaluate(async () => {
        if (document.fonts && document.fonts.ready) await document.fonts.ready;
        document.querySelectorAll('img[loading="lazy"]').forEach(img => { img.loading = 'eager'; });
        await Promise.all(Array.from(document.images).map(img => img.complete ? null : new Promise(resolve => { img.onload = img.onerror = resolve; setTimeout(resolve, 4000); })));
        // Decodificadas: una imagen «completa» puede tardar aún en pintarse.
        await Promise.all(Array.from(document.images).map(img => img.decode ? img.decode().catch(() => {}) : null));
    });
}

async function settle(page, scenario) {
    // Sin JavaScript no hay evaluate ni addStyleTag (se quedarían esperando); ya hay `reducedMotion: 'reduce'`.
    if (scenario.noScript) { await page.waitForTimeout(500); return; }
    // Deja terminar las animaciones de entrada (botón flotante, apariciones…) para que el estado final sea siempre el mismo.
    await page.waitForTimeout(1300);
    await page.evaluate(() => Promise.all(document.getAnimations().filter(a => a.effect && a.effect.getComputedTiming().endTime !== Infinity).map(a => a.finished.catch(() => {}))));
    await waitForAssets(page);
    await page.addStyleTag({ content: '*,*::before,*::after{animation:none!important;transition:none!important;caret-color:transparent!important}.preloader,.cursor-dot,.cursor-ring,.scroll-progress{display:none!important}' });
    // Recorre la página para que se carguen las imágenes diferidas y se activen las apariciones.
    await page.evaluate(async () => {
        const step = Math.max(400, innerHeight / 2);
        for (let y = 0; y < document.documentElement.scrollHeight; y += step) { scrollTo(0, y); await new Promise(r => setTimeout(r, 60)); }
        document.querySelectorAll('[data-reveal],.card,.mb-thread').forEach(el => el.classList.add('is-revealed', 'is-visible', 'is-in'));
        scrollTo(0, 0);
    });
    // main.js revela cada foto cuando entra en pantalla (IntersectionObserver); las del borde dependen del instante. Se revelan todas.
    await page.evaluate(() => document.querySelectorAll('.card__img').forEach(img => img.classList.add('is-loaded')));
    await waitForAssets(page);
    await page.waitForTimeout(250);
    // El diseño (columnas, alturas) y las imágenes diferidas pueden cambiar tras la carga: se repite hasta que nada cambie.
    let previous = null, stable = 0;
    for (let i = 0; i < 40 && stable < 4; i++) {
        const state = await page.evaluate(selector => {
            const el = selector ? document.querySelector(selector) : null;
            if (el) el.scrollIntoView({ block: 'start' });
            const loaded = Array.from(document.images).filter(img => img.complete && img.naturalWidth > 0).length;
            return [Math.round(scrollY), document.documentElement.scrollHeight, el ? Math.round(el.getBoundingClientRect().height) : 0, document.images.length, loaded].join(':');
        }, scenario.scrollTo || '');
        stable = state === previous ? stable + 1 : 0;
        previous = state;
        await page.waitForTimeout(150);
    }
}

async function capture(worker, scenario) {
    const browser = worker.browser;
    // Sin service worker: pwa.js puede recargar la página al detectar «una versión nueva» y descuadrar la captura.
    const context = await browser.newContext({ viewport: scenario.viewport, deviceScaleFactor: 1, reducedMotion: 'reduce', locale: 'es-ES', timezoneId: 'Europe/Madrid', serviceWorkers: 'block', javaScriptEnabled: !scenario.noScript });
    // Sin red externa (mapas, CDN): el resultado no puede depender de internet.
    await context.route(url => !['127.0.0.1', 'localhost'].includes(new URL(url).hostname), route => route.abort());
    // Math.random determinista: las galerías con posiciones o giros aleatorios (burbujas, polaroids…) salen siempre igual.
    await context.addInitScript(() => {
        let seed = 123456789;
        Math.random = () => { seed |= 0; seed = (seed + 0x6D2B79F5) | 0; let t = Math.imul(seed ^ (seed >>> 15), 1 | seed); t = (t + Math.imul(t ^ (t >>> 7), 61 | t)) ^ t; return ((t ^ (t >>> 14)) >>> 0) / 4294967296; };
    });
    const page = await context.newPage();
    const settings = JSON.stringify({ show_microblog: true, show_map: true, show_categories: true, show_project: true, show_social: true, ...scenario.settings });
    fs.writeFileSync(path.join(worker.configDir, 'site-settings.json'), settings);
    await page.goto(`http://127.0.0.1:${worker.port}${scenario.url || '/'}`, { waitUntil: 'load' });
    await page.waitForTimeout(500);
    await settle(page, scenario);
    // Con `scrollTo` la captura empieza en la sección; si no, arriba del todo.
    // Sin JavaScript tampoco funciona `page.evaluate`: se captura desde arriba con la altura pedida.
    const top = scenario.scrollTo && !scenario.noScript ? await page.evaluate(() => Math.round(scrollY)) : 0;
    const total = scenario.noScript ? top + (scenario.clipHeight || scenario.viewport.height) : await page.evaluate(() => document.documentElement.scrollHeight);
    const height = Math.max(1, Math.min(scenario.clipHeight || scenario.viewport.height, total - top));
    // Se repite la captura hasta que dos seguidas son idénticas: así no se fotografía una animación a medias.
    const shoot = () => page.screenshot({ fullPage: true, clip: { x: 0, y: top, width: scenario.viewport.width, height } });
    let buffer = await shoot();
    for (let attempt = 0; attempt < 12; attempt++) {
        await page.waitForTimeout(300);
        const next = await shoot();
        const same = next.equals(buffer);
        buffer = next;
        if (same) break;
    }
    await context.close();
    return buffer;
}

// Compara dos PNG en el propio navegador (sin dependencias). Devuelve el % de píxeles distintos y una imagen de diferencias.
async function compare(page, expectedBuffer, actualBuffer) {
    return page.evaluate(async ({ expected, actual }) => {
        const load = src => new Promise((resolve, reject) => { const img = new Image(); img.onload = () => resolve(img); img.onerror = reject; img.src = src; });
        const [a, b] = await Promise.all([load(expected), load(actual)]);
        const width = Math.max(a.width, b.width), height = Math.max(a.height, b.height);
        const draw = img => { const c = document.createElement('canvas'); c.width = width; c.height = height; const g = c.getContext('2d'); g.fillStyle = '#ff00ff'; g.fillRect(0, 0, width, height); g.drawImage(img, 0, 0); return g.getImageData(0, 0, width, height); };
        const da = draw(a), db = draw(b);
        const out = new ImageData(width, height);
        let different = 0;
        for (let i = 0; i < da.data.length; i += 4) {
            const delta = Math.max(Math.abs(da.data[i] - db.data[i]), Math.abs(da.data[i + 1] - db.data[i + 1]), Math.abs(da.data[i + 2] - db.data[i + 2]));
            const bad = delta > 12;
            if (bad) different++;
            out.data[i] = bad ? 255 : db.data[i] * 0.35; out.data[i + 1] = bad ? 0 : db.data[i + 1] * 0.35; out.data[i + 2] = bad ? 0 : db.data[i + 2] * 0.35; out.data[i + 3] = 255;
        }
        const c = document.createElement('canvas'); c.width = width; c.height = height; c.getContext('2d').putImageData(out, 0, 0);
        return { percent: different / (width * height) * 100, sizeChanged: a.width !== b.width || a.height !== b.height, diff: c.toDataURL('image/png') };
    }, { expected: 'data:image/png;base64,' + expectedBuffer.toString('base64'), actual: 'data:image/png;base64,' + actualBuffer.toString('base64') });
}

(async () => {
    prepareSite();
    const choices = readChoices();
    const everything = buildScenarios(choices).filter(s => !only || s.name.includes(only));
    // Con --only se ejecuta justo lo pedido; sin él, el modo rápido salvo que se pida --full.
    const scenarios = full || only ? everything : quickSubset(everything);
    if (listOnly) { console.log(scenarios.map(s => s.name).join('\n') + `\n\n${scenarios.length} escenarios`); return; }
    for (const dir of Object.values(dirs)) fs.mkdirSync(dir, { recursive: true });
    if (!update) { fs.rmSync(dirs.diff, { recursive: true, force: true }); fs.mkdirSync(dirs.diff, { recursive: true }); }

    // Trabajadores en paralelo: cada uno con su servidor PHP (puerto y carpeta de configuración propios) y su Chromium.
    const workerCount = Math.max(1, Math.min(scenarios.length, Number(process.env.VISUAL_WORKERS || 3)));
    const launch = () => chromium.launch({ headless: true, executablePath: process.env.TEST_BROWSER || undefined, args: ['--no-sandbox'] });
    const workers = [];
    for (let i = 0; i < workerCount; i++) {
        const workerConfig = i === 0 ? configDir : configDir + '-w' + i;
        if (i > 0) fs.cpSync(configDir, workerConfig, { recursive: true });
        const worker = { id: i, port: port + i, configDir: workerConfig, done: 0 };
        worker.server = await startServer(worker);
        worker.browser = await launch();
        worker.comparer = await worker.browser.newPage();
        // Chromium se reinicia cada cierto número de capturas y si se cae: en pasadas largas acumula memoria y puede cerrarse.
        worker.restart = async () => { await worker.browser.close().catch(() => {}); worker.browser = await launch(); worker.comparer = await worker.browser.newPage(); };
        workers.push(worker);
    }
    const failures = [];
    let missing = 0, done = 0, next = 0;
    const runWorker = async worker => {
        while (next < scenarios.length) {
            const scenario = scenarios[next++];
            const file = scenario.name + '.png';
            let buffer;
            if (!worker.browser.isConnected() || (worker.done > 0 && worker.done % 25 === 0)) await worker.restart();
            // Un reintento (con navegador nuevo si hace falta): una recarga puntual de la página no debe tumbar la matriz.
            try { buffer = await capture(worker, scenario).catch(async () => { if (!worker.browser.isConnected()) await worker.restart(); return capture(worker, scenario); }); } catch (error) { failures.push(scenario.name + ': error al capturar (' + String(error).split('\n')[0].slice(0, 120) + ')'); continue; }
            fs.writeFileSync(path.join(dirs.current, file), buffer);
            worker.done++;
            if (update) { fs.writeFileSync(path.join(dirs.baseline, file), buffer); }
            else if (!fs.existsSync(path.join(dirs.baseline, file))) { missing++; failures.push(scenario.name + ': sin referencia (ejecuta con --update)'); }
            else {
                const expected = fs.readFileSync(path.join(dirs.baseline, file));
                let result = await compare(worker.comparer, expected, buffer);
                // Una diferencia se confirma con una segunda captura: una foto diferida que llega tarde no es un fallo, un cambio real se repite.
                if (result.sizeChanged || result.percent > tolerance) {
                    if (!worker.browser.isConnected()) await worker.restart();
                    const second = await capture(worker, scenario).catch(() => null);
                    if (second) {
                        const again = await compare(worker.comparer, expected, second);
                        if (!again.sizeChanged && again.percent <= tolerance) { result = again; buffer = second; fs.writeFileSync(path.join(dirs.current, file), buffer); }
                    }
                }
                if (result.sizeChanged || result.percent > tolerance) {
                    fs.writeFileSync(path.join(dirs.diff, file), Buffer.from(result.diff.split(',')[1], 'base64'));
                    failures.push(scenario.name + ': ' + (result.sizeChanged ? 'cambia de tamaño y ' : '') + result.percent.toFixed(2) + ' % de píxeles distintos');
                }
            }
            done++;
            if (done % 20 === 0) console.log('  ' + done + '/' + scenarios.length + ' capturas…');
        }
    };
    try {
        await Promise.all(workers.map(runWorker));
    } finally {
        for (const worker of workers) { await worker.browser.close().catch(() => {}); worker.server.kill(); }
    }
    console.log(`${update ? 'Referencia guardada' : 'Comparadas'}: ${done}/${scenarios.length} escenarios.`);
    if (failures.length) { console.log(`\n${failures.length} diferencias:\n` + failures.map(f => '  - ' + f).join('\n') + '\n\nImágenes de diferencias en tests/visual/diff/'); process.exit(1); }
    console.log('Sin diferencias visuales.');
})().catch(error => { console.error(error); process.exit(1); });
