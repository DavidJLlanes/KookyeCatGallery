const { chromium } = require('playwright');
const fs = require('fs');
const path = require('path');
const assert = require('assert/strict');
const root = path.join(__dirname, '..');
const css = ['style', 'site-design', 'interface-worlds', 'gallery-layout'].map(n => fs.readFileSync(path.join(root, `assets/css/${n}.css`), 'utf8')).join('\n');
const scripts = ['gallery-layout', 'main'].map(n => fs.readFileSync(path.join(root, `assets/js/${n}.js`), 'utf8'));
const svg = 'data:image/svg+xml,' + encodeURIComponent('<svg xmlns="http://www.w3.org/2000/svg" width="200" height="200"><rect width="200" height="200" fill="red"/></svg>');
const cards = Array.from({ length: 43 }, (_, i) => `<article class="card" data-slug="foto-${i}" data-title="Foto ${i}" data-full="${svg}" data-lat="${(i % 4).toFixed(4)}" data-lng="1.0000" data-num-global="${i + 1}"
    data-category="${i % 2 ? 'B' : 'A'}" style="--aspect:${i % 2 ? .67 : 1.5}"><button class="card__btn" type="button"><picture class="card__picture"><img class="card__img" src="${svg}" alt="Foto ${i}"></picture></button></article>`).join('');
const lightbox = `<div id="lightbox" class="lightbox" aria-hidden="true"><button class="lightbox__close">Cerrar</button><button class="lightbox__fullscreen">Pantalla completa</button>
    <div class="lightbox__stage"><button class="lightbox__nav lightbox__nav--prev">Anterior</button><img class="lightbox__img" alt=""><button class="lightbox__nav lightbox__nav--next">Siguiente</button></div>
    <h3 class="lightbox__title"></h3><p class="lightbox__description"></p><span class="lightbox__counter"></span></div>`;
const fixture = (mobile, desktop, layoutMobile = 'standard', layoutDesktop = 'standard') => `<!doctype html><html><head><meta name="viewport" content="width=device-width,initial-scale=1"><style>${css}</style></head>
    <body data-gallery-mobile="${layoutMobile}" data-gallery-desktop="${layoutDesktop}" data-photos-mobile="${mobile}" data-photos-desktop="${desktop}" data-grid="square" style="--columns-mobile:3;--columns-desktop:3">
    <div id="categoriesFilter"><button class="categories-filter__chip is-active" data-category="">Todas</button><button class="categories-filter__chip" data-category="A">A</button></div>
    <main id="galeria"><section id="masonry" class="masonry">${cards}</section><nav id="jsPagination"></nav></main>${lightbox}</body></html>`;

(async () => {
    const browser = await chromium.launch({ headless: true, executablePath: process.env.TEST_BROWSER || undefined, args: ['--no-sandbox'] });
    const errors = [];
    const open = async (html, width) => {
        const page = await browser.newPage({ viewport: { width, height: 900 }, reducedMotion: 'reduce' });
        page.on('pageerror', e => errors.push(e.message));
        await page.route('**/*', route => route.fulfill({ status: 200, contentType: 'text/html', body: html }));
        await page.goto('https://gallery.test/');
        await page.evaluate(() => { window.siteText = s => s; window.siteTextHTML = s => s; });
        for (const content of scripts) await page.addScriptTag({ content });
        return page;
    };
    const count = page => page.locator('#masonry .card:not([hidden])').count();
    try {
        for (const width of [390, 1280]) for (const limit of [6, 8, 12, 20, 100, 0]) {
            const page = await open(fixture(limit, limit), width);
            assert.equal(await count(page), limit === 0 ? 43 : Math.min(limit, 43), `Configured count ${limit}/${width}`);
            if (limit > 0 && limit < 43) {
                const last = Math.ceil(43 / limit);
                await page.locator(`[data-page="${last}"]`).click();
                assert.equal(await count(page), 43 - (last - 1) * limit, 'Last page must only contain the remainder.');
            } else assert.equal(await page.locator('#jsPagination').isVisible(), false);
            await page.close();
        }
        const page = await open(fixture(8, 15, 'category-rails', 'contact-sheet'), 1280);
        const visibleSlugs = () => page.locator('#masonry .card:not([hidden])').evaluateAll(nodes => nodes.map(n => n.dataset.slug));
        await page.setViewportSize({ width: 390, height: 900 });
        await page.waitForFunction(() => document.querySelector('#masonry').dataset.layout === 'category-rails');
        assert.equal(await count(page), 43);
        assert.equal(await page.locator('.category-rail').count(), 2);
        await page.locator('[data-category="A"].categories-filter__chip').click();
        assert.equal(await count(page), 22);
        await page.setViewportSize({ width: 1280, height: 900 });
        await page.waitForFunction(() => document.querySelector('#masonry').dataset.layout === 'contact-sheet');
        assert.equal(await count(page), 15);
        assert.equal(await page.locator('.category-rail').count(), 0);
        assert.equal(page.url(), 'https://gallery.test/', 'Changing layout must not reload or navigate.');
        await page.evaluate(() => document.dispatchEvent(new CustomEvent('gallery:filter-location', { detail: { location: '0,1' } })));
        assert.equal(await count(page), 11);
        assert((await visibleSlugs()).every(slug => Number(slug.slice(5)) % 4 === 0), 'Location filtering must update hidden attributes and pagination.');
        await page.locator('[data-category=""].categories-filter__chip').click();
        assert.equal(await count(page), 15);
        await page.evaluate(() => {
            localStorage.setItem('djl-photo-favorites-v1', JSON.stringify(['foto-1', 'foto-19', 'foto-42']));
            document.body.classList.add('favorites-only');
            document.dispatchEvent(new Event('favorites:changed'));
        });
        assert.deepEqual(await visibleSlugs(), ['foto-1', 'foto-19', 'foto-42'], 'Favorites must be selected before pagination.');
        await page.evaluate(() => { document.body.classList.remove('favorites-only'); document.dispatchEvent(new Event('favorites:changed')); });
        await page.locator('.card:not([hidden])').evaluateAll(nodes => nodes.forEach(node => delete node.dataset.slug));
        await page.locator('.card:not([hidden]) .card__btn').first().click();
        await page.waitForFunction(() => document.querySelector('#lightbox').classList.contains('is-open'));
        assert.equal(await page.locator('.lightbox__title').textContent(), 'Foto 0');
        await page.locator('.lightbox__nav--next').click();
        assert.equal(await page.locator('.lightbox__title').textContent(), 'Foto 1');
        await page.locator('.lightbox__close').click();
        await page.evaluate(() => document.dispatchEvent(new CustomEvent('gallery:filter-location', { detail: { location: '90,90' } })));
        assert.equal(await count(page), 0);
        assert.equal(await page.locator('#jsPagination').isVisible(), false);
        await page.close();
        assert.deepEqual(errors, []);
        console.log('Gallery behavior passed: configured limits, all photos, last page, responsive rails, location filtering, favorites and lightbox navigation.');
    } finally { await browser.close(); }
})().catch(error => { console.error(error); process.exitCode = 1; });
