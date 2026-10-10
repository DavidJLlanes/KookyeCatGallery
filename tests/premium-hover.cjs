// Real CSS states, without injecting effect overrides or replacing :hover with a test class.
const { chromium } = require('playwright');
const fs = require('fs');
const path = require('path');
const assert = require('assert/strict');
const root = path.join(__dirname, '..');
const base = ['style', 'site-design', 'interface-worlds', 'gallery-layout'].map(n =>
    fs.readFileSync(path.join(root, `assets/css/${n}.css`), 'utf8')).join('\n');
const standards = ['standard', 'grid', 'mosaic', 'asymmetric', 'category-rails', 'scattered', 'exhibition', 'contact-sheet', 'narrative', 'triptych'];
const premiums = ['deck', 'coverflow', 'bubbles', 'squares', 'drum', 'cylinder', 'polaroid', 'swipe'];
const effects = ['soft', 'zoom', 'lift', 'reveal', 'tint', 'frame', 'slide', 'tilt', 'focus', 'shine'];
const png = 'data:image/svg+xml,' + encodeURIComponent('<svg xmlns="http://www.w3.org/2000/svg" width="200" height="200"><rect width="200" height="200" fill="red"/></svg>');

(async () => {
    const browser = await chromium.launch({ headless: true, executablePath: process.env.TEST_BROWSER || undefined, args: ['--no-sandbox'] });
    try {
        for (const design of [...standards, ...premiums]) {
            const premium = premiums.includes(design);
            const kind = ['bubbles', 'squares'].includes(design) ? 'bubbles' : design === 'cylinder' ? 'cyl' : 'deck';
            const prefix = premium ? kind : 'card';
            const rootId = premium ? kind === 'bubbles' ? 'bubbles' : 'deck' : 'masonry';
            const picture = prefix + '__picture';
            const link = prefix + (premium ? '__link' : '__btn');
            const img = prefix + '__img';
            let css = base;
            if (premium) {
                const files = kind === 'bubbles' ? ['bubbles/bubbles.css'] : ['deck/deck.css', ...(design !== 'deck' ? [`${design}/${design}.css`] : [])];
                for (const file of files) css += '\n' + fs.readFileSync(path.join(root, 'assets/premium', file), 'utf8');
            }
            const page = await browser.newPage({ viewport: { width: 1000, height: 800 }, reducedMotion: 'reduce' });
            await page.setContent(`<style>${css}</style><body data-gallery-active="${premium ? '' : design}" data-gallery-premium="${premium ? design : 'none'}"
                data-header-mobile="orbit" data-header-desktop="noir" data-grid="square" data-hover="soft">
                <div id="${rootId}" data-layout="${design}" data-shape="${design === 'squares' ? 'square' : 'circle'}">
                  <article class="${premium ? kind === 'bubbles' ? 'bubbles__item' : kind === 'cyl' ? 'cyl__item' : 'deck__card' : 'card'}" data-pos="0"
                    style="--layout-x:0px;--layout-y:0px;--layout-w:240px;--layout-h:200px;position:relative;width:240px;height:200px">
                    <a class="${link}" href="#"><picture class="${picture}"><img class="${img}" src="${png}" alt="Foto"></picture></a>
                  </article>
                </div></body>`);
            const target = page.locator('.' + link);
            const image = page.locator('.' + img);
            const frame = premium ? target : page.locator('.' + picture);
            for (const effect of effects) {
                await target.evaluate(el => el.blur());
                await page.mouse.move(900, 700);
                await page.locator('body').evaluate((el, effect) => { el.dataset.hover = effect; }, effect);
                const before = await image.evaluate(el => ({ transform: getComputedStyle(el).transform, filter: getComputedStyle(el).filter }));
                const outerBefore = await target.evaluate(el => getComputedStyle(el.closest('article')).transform);
                await target.focus();
                if (effect === 'frame') {
                    assert.equal(await frame.evaluate(el => getComputedStyle(el).outlineStyle), 'solid', design + '/frame');
                } else if (effect === 'shine') {
                    const state = await page.locator('.' + picture).evaluate(el => {
                        const s = getComputedStyle(el, '::after'); return { content: s.content, display: s.display, transform: s.transform };
                    });
                    assert.notEqual(state.content, 'none', design + '/shine missing');
                    assert.notEqual(state.display, 'none', design + '/shine hidden by geometry');
                    assert.notEqual(state.transform, 'none', design + '/shine sweep');
                } else {
                    const after = await image.evaluate(el => ({ transform: getComputedStyle(el).transform, filter: getComputedStyle(el).filter }));
                    assert.notDeepEqual(after, before, design + '/' + effect + ': effect flattened by theme/geometry');
                }
                assert.equal(await target.evaluate(el => getComputedStyle(el.closest('article')).transform), outerBefore,
                    design + '/' + effect + ': hover must not replace the layout transform');
            }
            await page.close();
        }
        console.log('Hover passed: 10 effects × 10 standard layouts + 8 premium layouts, real focus states and preserved geometry.');
    } finally { await browser.close(); }
})().catch(error => { console.error(error); process.exitCode = 1; });
