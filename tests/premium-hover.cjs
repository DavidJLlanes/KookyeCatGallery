// Asegura que los efectos del panel alcanzan las imágenes de los tres tipos de marcado premium.
const {chromium} = require('playwright');
const fs = require('fs');
const path = require('path');
const assert = require('assert/strict');
const root = path.join(__dirname, '..');
const css = [
    'assets/css/gallery-layout.css',
    'assets/premium/deck/deck.css',
    'assets/premium/bubbles/bubbles.css',
    'assets/premium/cylinder/cylinder.css',
].map(file => fs.readFileSync(path.join(root, file), 'utf8')).join('\n');
const html = `<!doctype html><html><head><meta charset="utf-8"></head>
<body data-gallery-premium="deck" data-hover="zoom">
    <div id="deck">
        <a class="deck__link" href="#"><picture class="deck__picture"><img class="deck__img" alt=""></picture></a>
        <a class="cyl__link" href="#"><picture class="cyl__picture"><img class="cyl__img" alt=""></picture></a>
    </div>
    <div id="bubbles"><article class="bubbles__item"><a class="bubbles__link" href="#"><picture class="bubbles__picture"><img class="bubbles__img" alt=""></picture></a></article></div>
</body></html>`;
(async () => {
    const browser = await chromium.launch({headless: true, executablePath: process.env.TEST_BROWSER || undefined, args: ['--no-sandbox']});
    try {
        const page = await browser.newPage({viewport: {width: 1000, height: 900}});
        await page.setContent(html);
        await page.addStyleTag({content: 'body{margin:0}#deck,#bubbles{position:relative;width:220px;height:220px;margin:20px}#deck a,#bubbles a{display:block;width:100%;height:100%}img{display:block;width:100%;height:100%}'});
        await page.addStyleTag({content: css});
        for (const [link, image] of [
            ['#deck .deck__link', '#deck .deck__img'],
            ['#bubbles .bubbles__link', '#bubbles .bubbles__img'],
            ['#deck .cyl__link', '#deck .cyl__img'],
        ]) {
            await page.locator(link).hover();
            const transform = await page.locator(image).evaluate(node => getComputedStyle(node).transform);
            assert.notEqual(transform, 'none', `El efecto hover debe llegar a ${image} (${transform})`);
        }
        await page.locator('body').evaluate(node => { node.dataset.hover = 'frame'; });
        await page.locator('#deck .deck__link').hover();
        const outline = await page.locator('#deck .deck__link').evaluate(node => getComputedStyle(node).outlineStyle);
        assert.equal(outline, 'solid', 'El efecto de marco debe alcanzar la carta premium');
        await page.locator('body').evaluate(node => { node.dataset.hover = 'shine'; });
        const shine = await page.locator('#deck .deck__picture').evaluate(node => getComputedStyle(node, '::after').content);
        assert.notEqual(shine, 'none', 'El efecto destello debe generar su capa sobre la foto premium');
        console.log('OK: efectos hover aplicados a Baraja, Burbujas y Cilindro.');
    } finally {
        await browser.close();
    }
})().catch(error => { console.error(error); process.exitCode = 1; });
