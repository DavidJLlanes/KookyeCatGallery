// Comprueba Cover Flow en escritorio y móvil con el marcado, CSS y JS reales.
const { chromium } = require('playwright');
const { execFileSync } = require('child_process');
const fs = require('fs');
const path = require('path');
const assert = require('assert/strict');
const root = path.join(__dirname, '..');
const fixture = String.raw`
require_once 'inc/helpers.php';
require_once 'inc/site-settings.php';
$siteSettings = site_settings_defaults(); $siteSettings['gallery_premium'] = 'coverflow';
$rootDir = getcwd(); $author = 'Autor'; $galleryItems = [];
for ($i = 0; $i < 8; $i++) $galleryItems[] = ['slug' => "foto-$i", 'title' => "Foto $i", 'description' => '',
    'desktop' => "imagenes/desktop/foto-$i.webp", 'mobile' => "imagenes/mobile/foto-$i.webp",
    'aspect' => 1.5, 'category' => '', 'latitude' => 0, 'longitude' => 0];
include 'inc/premium/deck.php';
`;
const markup = execFileSync('php', ['-r', fixture], { cwd: root, encoding: 'utf8' });
assert.match(markup, /data-premium-gallery="coverflow"/);
assert.match(markup, /data-layout="coverflow"/);
const css = ['style', 'site-design', 'gallery-layout'].map(n => fs.readFileSync(path.join(root, `assets/css/${n}.css`), 'utf8')).join('\n')
    + fs.readFileSync(path.join(root, 'assets/premium/deck/deck.css'), 'utf8')
    + fs.readFileSync(path.join(root, 'assets/premium/coverflow/coverflow.css'), 'utf8');
const scripts = ['assets/js/site-texts.js', 'assets/js/main.js', 'assets/premium/deck/deck.js'].map(p => fs.readFileSync(path.join(root, p), 'utf8'));
const png = Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==', 'base64');
(async () => {
    const browser = await chromium.launch({ headless: true, executablePath: process.env.TEST_BROWSER || undefined, args: ['--no-sandbox'] });
    try {
        for (const viewport of [{ width: 1280, height: 800 }, { width: 390, height: 844 }]) {
            const mobile = viewport.width < 700;
            const page = await browser.newPage({ viewport, isMobile: mobile, hasTouch: mobile });
            const errors = [];
            page.on('pageerror', e => errors.push(e.message));
            await page.route('**/*', route => {
                if (route.request().url().endsWith('.webp')) return route.fulfill({ status: 200, contentType: 'image/png', body: png });
                return route.fulfill({ status: 200, contentType: 'text/html', body:
                    `<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head><body><header style="height:500px"></header>${markup}<footer style="height:500px"></footer></body></html>` });
            });
            await page.goto('https://coverflow.test/');
            await page.addStyleTag({ content: css });
            for (const source of scripts) await page.addScriptTag({ content: source });
            await page.evaluate(() => window.scrollTo({ top: document.querySelector('#deck').offsetTop + 500, behavior: 'instant' }));
            await page.waitForTimeout(150);
            await page.click('[data-deck-next]');
            await page.click('[data-deck-next]');
            await page.waitForTimeout(950);
            const s = await page.evaluate(() => {
                const cards = [...document.querySelectorAll('.deck__card')];
                const active = cards.find(c => c.dataset.pos === '0');
                const left = cards.find(c => c.dataset.pos === '-1');
                const right = cards.find(c => c.dataset.pos === '1');
                return {
                    layout: document.querySelector('#deck').dataset.layout,
                    positions: cards.map(c => c.dataset.pos),
                    visible: cards.filter(c => getComputedStyle(c).visibility === 'visible' && !c.hidden).length,
                    active: getComputedStyle(active).transform, left: getComputedStyle(left).transform, right: getComputedStyle(right).transform,
                    reflection: getComputedStyle(document.querySelector('.deck__picture')).getPropertyValue('-webkit-box-reflect'),
                    overflow: document.documentElement.scrollWidth > innerWidth + 1,
                };
            });
            assert.equal(s.layout, 'coverflow');
            assert.deepEqual(s.positions.slice(1, 4), ['-1', '0', '1']);
            assert.notEqual(s.left, s.active, 'la foto izquierda debe inclinarse');
            assert.notEqual(s.right, s.active, 'la foto derecha debe inclinarse');
            assert.notEqual(s.reflection, 'none', 'el reflejo debe mostrarse cuando el motor lo soporta');
            assert.equal(s.visible, mobile ? 3 : 5, 'móvil muestra solo dos laterales');
            assert.equal(s.overflow, false, 'sin desbordamiento horizontal');
            assert.deepEqual(errors, [], 'sin errores de JavaScript');
            await page.close();
        }
    } finally { await browser.close(); }
    console.log('Cover Flow verificado en escritorio y móvil.');
})().catch(e => { console.error(e); process.exitCode = 1; });
