// El logo se ve siempre redondo (en la cabecera de perfil del móvil, en el hero de escritorio y en la precarga) y, en la cabecera
// móvil, nunca tapa la foto de perfil, en ninguna de las plantillas. Trabaja en una copia exportada del núcleo con ajustes propios.
'use strict';
const fs = require('fs');
const os = require('os');
const path = require('path');
const assert = require('node:assert/strict');
const { spawn, execFileSync } = require('child_process');
const { chromium } = require('playwright');

const root = path.join(__dirname, '..');
const php = process.env.PHP || 'php';
const tmp = fs.mkdtempSync(path.join(os.tmpdir(), 'logo-layout-'));
const site = path.join(tmp, 'site');
const privateDir = path.join(tmp, 'private');
const port = 8600 + Math.floor(Math.random() * 90);
const base = `http://127.0.0.1:${port}`;
let server = null;

const png = (file, w, h, [r, g, b]) => execFileSync(php, ['-r', `$i=imagecreatetruecolor(${w},${h}); imagefill($i,0,0,imagecolorallocate($i,${r},${g},${b})); imagepng($i,'${file.split(path.sep).join('/')}');`], { stdio: 'pipe' });

(async () => {
    const browser = await chromium.launch({ headless: true, args: ['--no-sandbox'] });
    try {
        execFileSync(php, [path.join(root, 'scripts/export-core.php'), site, 'prueba'], { stdio: 'pipe' });
        fs.mkdirSync(privateDir, { recursive: true });
        png(path.join(site, 'profile-photo-custom.png'), 600, 750, [200, 80, 60]);
        // Logo no cuadrado a propósito: aun así debe verse redondo.
        png(path.join(site, 'site-logo-custom.png'), 500, 640, [60, 160, 90]);
        const env = { ...process.env, GALLERY_PRIVATE_DIR: privateDir, SITE_CONFIG_DIR: '', MICROBLOG_DATA_DIR: '', GALLERY_PUBLIC_URL: '' };
        server = spawn(php, ['-d', 'display_errors=0', '-S', `127.0.0.1:${port}`, '-t', site], { env, stdio: 'ignore' });
        for (let i = 0; i < 60; i++) { try { if ((await fetch(base + '/robots.txt')).status) break; } catch (_) { /* arrancando */ } await new Promise(r => setTimeout(r, 250)); }

        const mobileTemplates = fs.readdirSync(path.join(root, 'themes/headers-mobile')).filter(f => f.endsWith('.php')).map(f => f.replace('.php', ''));
        assert.ok(mobileTemplates.length >= 9, 'Se esperaban todas las plantillas móviles.');
        const overlap = (a, b) => a.left < b.right && a.right > b.left && a.top < b.bottom && a.bottom > b.top;
        for (const template of mobileTemplates) {
            fs.writeFileSync(path.join(privateDir, 'site-settings.json'), JSON.stringify({ header_mobile: template, profile_image: '/profile-photo-custom.png', logo_image: '/site-logo-custom.png' }));
            const context = await browser.newContext({ viewport: { width: 390, height: 844 }, isMobile: true, hasTouch: true });
            const page = await context.newPage();
            await page.goto(base + '/', { waitUntil: 'load' });
            await page.addStyleTag({ content: '*{animation:none!important;transition:none!important}' });
            const wrap = page.locator('.mobile-profile__crest-wrap');
            const avatar = page.locator('.mobile-profile__avatar');
            if (await wrap.isVisible()) {
                const w = await wrap.boundingBox();
                const a = await avatar.boundingBox();
                assert.ok(Math.abs(w.width - w.height) < 1, `${template}: el logo debe ser redondo (${w.width}×${w.height}).`);
                assert.equal(await wrap.evaluate(el => getComputedStyle(el).borderRadius), '50%', `${template}: el marco del logo es un círculo.`);
                assert.ok(!overlap(w, a), `${template}: el logo no puede tapar la foto de perfil.`);
                const image = await page.locator('.mobile-profile__crest-wrap img').boundingBox();
                assert.ok(Math.abs(image.width - image.height) < 1, `${template}: la imagen del logo es cuadrada y se recorta redonda.`);
            } else {
                assert.ok(['compact', 'studio'].includes(template), `${template}: el logo debería verse en esta plantilla.`);
            }
            await context.close();
        }

        // Escritorio: el logo del hero y la precarga son círculos.
        fs.writeFileSync(path.join(privateDir, 'site-settings.json'), JSON.stringify({ profile_image: '/profile-photo-custom.png', logo_image: '/site-logo-custom.png' }));
        const desktop = await browser.newContext({ viewport: { width: 1280, height: 900 } });
        const page = await desktop.newPage();
        await page.goto(base + '/', { waitUntil: 'load' });
        await page.addStyleTag({ content: '*{animation:none!important;transition:none!important}' });
        for (const selector of ['.hero__crest img', '.preloader__crest']) {
            const box = await page.locator(selector).first().boundingBox().catch(() => null);
            if (!box) continue; // la precarga ya puede haber terminado
            assert.ok(Math.abs(box.width - box.height) < 1, `${selector} debe ser redondo (${box.width}×${box.height}).`);
            assert.equal(await page.locator(selector).first().evaluate(el => getComputedStyle(el).borderRadius), '50%', `${selector} lleva borde redondo.`);
        }
        await desktop.close();
    } finally {
        await browser.close();
        if (server) server.kill();
        try { fs.rmSync(tmp, { recursive: true, force: true }); } catch (_) { /* el servidor aún suelta archivos */ }
    }
    console.log('Logo layout tests passed.');
})().catch(error => { console.error(error); if (server) server.kill(); process.exit(1); });
