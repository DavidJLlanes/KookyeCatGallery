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
].map(file => fs.readFileSync(path.join(root, file), 'utf8')).join('\\n');

(async () => {
    const browser = await chromium.launch({headless: true, executablePath: process.env.TEST_BROWSER || undefined, args: ['--no-sandbox']});
    try {
        const page = await browser.newPage({viewport: {width: 1000, height: 900}});
        const cases = [
            {premium: 'deck', container: 'deck', link: 'deck__link', picture: 'deck__picture', image: 'deck__img'},
            {premium: 'bubbles', container: 'bubbles', link: 'bubbles__link', picture: 'bubbles__picture', image: 'bubbles__img'},
            {premium: 'cylinder', container: 'deck', link: 'cyl__link', picture: 'cyl__picture', image: 'cyl__img'},
        ];
        for (const item of cases) {
            await page.setContent(`<!doctype html><html><head><meta charset="utf-8"></head>
                <body data-gallery-premium="${item.premium}" data-hover="zoom">
                    <div id="${item.container}"><article class="deck__card" data-pos="0"><a class="${item.link}" href="#"><picture class="${item.picture}"><img class="${item.image}" alt=""></picture></a></article></div>
                </body></html>`);
            await page.addStyleTag({content: 'body{margin:0}#deck,#bubbles{position:relative;width:220px;height:220px;margin:20px}a{display:block;width:100%;height:100%}img{display:block;width:100%;height:100%}'});
            await page.addStyleTag({content: css});
            const link = `#${item.container} .${item.link}`;
            const image = `#${item.container} .${item.image}`;
            await page.locator(link).hover();
            const transform = await page.locator(image).evaluate(node => getComputedStyle(node).transform);
            assert.notEqual(transform, 'none', `El efecto hover debe llegar a ${image} (${transform})`);
            await page.mouse.move(900, 800);
            await page.locator('body').evaluate(node => { node.dataset.hover = 'frame'; });
            await page.locator(link).hover();
            const outline = await page.locator(link).evaluate(node => getComputedStyle(node).outlineStyle);
            assert.equal(outline, 'solid', `El efecto de marco debe alcanzar ${link}`);
            await page.mouse.move(900, 800);
            await page.locator('body').evaluate(node => { node.dataset.hover = 'shine'; });
            await page.locator(link).hover();
            const shine = await page.locator(`#${item.container} .${item.picture}`).evaluate(node => getComputedStyle(node, '::after').content);
            assert.notEqual(shine, 'none', `El destello debe generar una capa sobre ${item.picture}`);
        }
        console.log('OK: efectos hover aplicados a Baraja, Burbujas y Cilindro.');
    } finally {
        await browser.close();
    }
})().catch(error => { console.error(error); process.exitCode = 1; });
