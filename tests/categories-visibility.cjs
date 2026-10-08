// «Mostrar categorías» debe respetarse tanto en móvil como en escritorio.
const {chromium} = require('playwright');
const fs = require('fs');
const path = require('path');
const assert = require('assert/strict');
const root = path.join(__dirname, '..');
const css = ['style', 'site-design'].map(n => fs.readFileSync(path.join(root, `assets/css/${n}.css`), 'utf8')).join('\n');
const page = show => `<body data-grid="adaptive" data-palette="current" data-header-mobile="current" data-header-desktop="current" data-gallery-mobile="standard" data-gallery-desktop="standard" data-show-categories="${show}" style="--columns-mobile:3;--columns-desktop:3">
<section class="categories-block"><div class="categories-filter" id="categoriesFilter" data-site-section="categories"><div class="categories-filter__inner">
<button class="categories-filter__chip is-active">Todas</button><button class="categories-filter__chip">Naturaleza</button></div></div></section>
<main class="gallery-section" id="galeria"><section class="masonry" id="masonry"></section></main></body>`;
(async () => {
  const browser = await chromium.launch({headless: true, executablePath: process.env.TEST_BROWSER || undefined, args: ['--no-sandbox']});
  try {
    for (const show of ['true', 'false']) {
      for (const width of [375, 768, 769, 1280]) {
        const tab = await browser.newPage({viewport: {width, height: 800}});
        await tab.route('**/*', route => route.abort());
        await tab.setContent(`<style>${css}</style>${page(show)}`);
        const box = await tab.evaluate(() => {
          const el = document.querySelector('#categoriesFilter');
          const r = el.getBoundingClientRect();
          return {display: getComputedStyle(el).display, height: r.height, overflow: document.documentElement.scrollWidth > innerWidth + 1};
        });
        if (show === 'true') assert(box.display !== 'none' && box.height > 20, `Categories must be visible at ${width}px`);
        else assert.equal(box.display, 'none', `Categories must be hidden at ${width}px when switched off`);
        assert(!box.overflow, `Horizontal overflow at ${width}px`);
        await tab.close();
      }
    }
    console.log('Categories visibility verified at 4 widths, on and off.');
  } finally { await browser.close(); }
})().catch(e => { console.error(e); process.exit(1); });
