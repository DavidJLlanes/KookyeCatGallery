// Editor de las páginas de texto (Quill): los botones aplican el formato a la selección y lo quitan al pulsar de nuevo (también con
// pantalla táctil), la barra marca el formato activo, los enlaces admiten rutas internas y lo guardado se sanea en el servidor.
// Trabaja en una copia exportada del núcleo con una instalación nueva; no toca los datos reales.
'use strict';
const fs = require('fs');
const os = require('os');
const path = require('path');
const assert = require('node:assert/strict');
const { spawn, execFileSync } = require('child_process');
const { chromium } = require('playwright');

const root = path.join(__dirname, '..');
const php = process.env.PHP || 'php';
const tmp = fs.mkdtempSync(path.join(os.tmpdir(), 'page-editor-'));
const site = path.join(tmp, 'site');
const privateDir = path.join(tmp, 'private');
const port = 8700 + Math.floor(Math.random() * 90);
const base = `http://127.0.0.1:${port}`;
let server = null;

(async () => {
    const browser = await chromium.launch({ headless: true, args: ['--no-sandbox'] });
    try {
        execFileSync(php, [path.join(root, 'scripts/export-core.php'), site, 'prueba'], { stdio: 'pipe' });
        const env = { ...process.env, GALLERY_PRIVATE_DIR: privateDir, SITE_CONFIG_DIR: '', MICROBLOG_DATA_DIR: '', GALLERY_PUBLIC_URL: '' };
        server = spawn(php, ['-d', 'display_errors=0', '-S', `127.0.0.1:${port}`, '-t', site], { env, stdio: 'ignore' });
        for (let i = 0; i < 60; i++) { try { if ((await fetch(base + '/install.php')).status) break; } catch (_) { /* arrancando */ } await new Promise(r => setTimeout(r, 250)); }

        for (const [name, options] of [['escritorio', { viewport: { width: 1200, height: 900 } }], ['móvil táctil', { viewport: { width: 390, height: 844 }, hasTouch: true, isMobile: true }]]) {
            const context = await browser.newContext(options);
            const page = await context.newPage();
            const errors = [];
            page.on('pageerror', error => errors.push(error.message));
            if (name === 'escritorio') {
                await page.goto(base + '/install.php');
                await page.fill('#url', 'https://prueba.example');
                await page.fill('#username', 'admin');
                await page.fill('#password', 'contrasena-de-prueba-1');
                await page.click('button[type=submit]');
                await page.waitForSelector('.message');
            }
            await page.goto(base + '/admin.php');
            await page.fill('input[name=username]', 'admin');
            await page.fill('input[name=password]', 'contrasena-de-prueba-1');
            await Promise.all([page.waitForLoadState('load'), page.click('button[type=submit]')]);
            await page.goto(base + '/admin.php?settings=1&section=page&doc=project');
            await page.waitForSelector('.ql-editor');
            const editor = page.locator('.ql-editor');
            const html = () => page.locator('[data-page-content]').inputValue();
            const tap = selector => (options.hasTouch ? page.tap(selector) : page.click(selector));
            const selectWord = async () => {
                await editor.evaluate(el => { el.focus(); const range = document.createRange(); range.selectNodeContents(el.firstElementChild); const selection = getSelection(); selection.removeAllRanges(); selection.addRange(range); });
            };

            // Texto nuevo; se parte de un contenido limpio.
            await editor.click();
            await page.keyboard.press('Control+A');
            await page.keyboard.type('Hola mundo');
            await selectWord();
            await tap('.ql-clean'); // el texto de partida llevaba cursiva
            assert.equal(await html(), '<p>Hola mundo</p>', `${name}: sin &nbsp; y sin formato de partida.`);
            await selectWord();

            // Negrita con el texto seleccionado, y alternando: la segunda pulsación la quita.
            await tap('.ql-bold');
            assert.match(await html(), /<strong>Hola mundo<\/strong>/, `${name}: la negrita se aplica a la selección.`);
            assert.equal(await page.locator('.ql-bold').evaluate(el => el.classList.contains('ql-active')), true, `${name}: el botón marca el formato activo.`);
            await selectWord();
            await tap('.ql-bold');
            assert.doesNotMatch(await html(), /<strong>/, `${name}: pulsar de nuevo quita la negrita.`);
            await selectWord();
            await tap('.ql-italic');
            await tap('.ql-underline');
            assert.match(await html(), /<em><u>Hola mundo<\/u><\/em>|<u><em>Hola mundo<\/em><\/u>/, `${name}: cursiva y subrayado a la vez.`);
            await selectWord();
            await tap('.ql-italic');
            await tap('.ql-underline');
            assert.doesNotMatch(await html(), /<em>|<u>/, `${name}: se quitan al alternar.`);

            // Estilo de párrafo, listas, alineación.
            await selectWord();
            await page.selectOption('.ql-header', '2');
            assert.match(await html(), /<h2>Hola mundo<\/h2>/, `${name}: título.`);
            await selectWord();
            await page.selectOption('.ql-header', '');
            await selectWord();
            await tap('.ql-list[value=bullet]');
            assert.match(await html(), /<ul><li>Hola mundo<\/li><\/ul>/, `${name}: lista con viñetas como ul/li.`);
            await selectWord();
            await tap('.ql-list[value=bullet]');
            assert.doesNotMatch(await html(), /<ul>/, `${name}: la lista se quita al pulsar de nuevo.`);
            await selectWord();
            await tap('.ql-align[value=center]');
            assert.match(await html(), /<p style="text-align: ?center;?">Hola mundo<\/p>/, `${name}: alineación como estilo en línea.`);

            // Enlace con ruta interna; deshacer solo quita el enlace (Quill agrupa los cambios que van seguidos, de ahí la espera).
            await page.waitForTimeout(800);
            await selectWord();
            await tap('.ql-link');
            await page.fill('[data-link-url]', '/aviso-legal');
            await tap('[data-link-apply]');
            assert.match(await html(), /<a href="\/aviso-legal"[ >]/, `${name}: los enlaces a rutas internas se conservan.`);
            await page.evaluate(() => document.querySelector('[data-editor-history=undo]').click());
            assert.doesNotMatch(await html(), /<a /, `${name}: deshacer quita el enlace.`);

            // Guardar: el servidor sanea y la página vuelve a mostrar lo escrito.
            await Promise.all([page.waitForURL(/saved=1/), page.click('.site-page-editor .upload-submit')]);
            await page.goto(base + '/admin.php?settings=1&section=page&doc=project');
            await page.waitForSelector('.ql-editor');
            assert.match(await page.locator('.ql-editor').innerText(), /Hola mundo/, `${name}: el contenido guardado se carga de nuevo.`);
            assert.match(await page.locator('[data-page-content]').inputValue(), /text-align: ?center/, `${name}: la alineación sobrevive al guardado.`);
            assert.deepEqual(errors, [], `${name}: sin errores de JavaScript.`);
            await context.close();
        }
    } finally {
        await browser.close();
        if (server) server.kill();
        try { fs.rmSync(tmp, { recursive: true, force: true }); } catch (_) { /* el servidor aún suelta archivos */ }
    }
    console.log('Page editor tests passed.');
})().catch(error => { console.error(error); if (server) server.kill(); process.exit(1); });
