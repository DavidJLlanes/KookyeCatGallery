// La paginación debe ser siempre circular (o cuadrada, si se elige): nunca ovalada.
const {chromium} = require('playwright');
const fs = require('fs');
const path = require('path');
const assert = require('assert/strict');
const root = path.join(__dirname, '..');
const css = ['style', 'site-design'].map(n => fs.readFileSync(path.join(root, `assets/css/${n}.css`), 'utf8')).join('\n');
const pages = [1, 2, 3, 9, 10, 11, 25, 99].map(n => `<button class="pagination__page${n === 10 ? ' is-current' : ''}">${n}</button>`).join('');
const html = shape => `<body data-pagination-shape="${shape}" data-palette="current" data-grid="adaptive"><nav class="pagination"><div class="pagination__controls">
<button class="pagination__nav pagination__nav--prev"><svg viewBox="0 0 24 24"><path d="M15 6l-6 6 6 6"/></svg><span>Anterior</span></button>
<div class="pagination__pages">${pages}</div>
<button class="pagination__nav pagination__nav--next"><span>Siguiente</span><svg viewBox="0 0 24 24"><path d="M9 6l6 6-6 6"/></svg></button></div></nav></body>`;
(async () => {
  const browser = await chromium.launch({headless: true, executablePath: process.env.TEST_BROWSER || undefined, args: ['--no-sandbox']});
  try {
    for (const shape of ['circle', 'square']) {
      for (const width of [320, 375, 768, 1280]) {
        const tab = await browser.newPage({viewport: {width, height: 700}});
        await tab.route('**/*', route => route.abort());
        await tab.setContent(`<style>${css}</style>${html(shape)}`);
        const items = await tab.evaluate(() => [...document.querySelectorAll('.pagination__page')].map(el => {
          const r = el.getBoundingClientRect();
          return {text: el.textContent, w: r.width, h: r.height, radius: parseFloat(getComputedStyle(el).borderTopLeftRadius), radiusText: getComputedStyle(el).borderTopLeftRadius};
        }));
        for (const item of items) {
          assert(Math.abs(item.w - item.h) < 0.6, `Page ${item.text} is ${item.w}×${item.h} at ${width}px (${shape}): must be square-sized`);
          if (shape === 'circle') assert(item.radiusText === '50%' || item.radius >= item.w / 2 - 0.5, `Page ${item.text} must be a circle at ${width}px`);
          else assert(item.radius <= 8, `Page ${item.text} must have square corners at ${width}px`);
        }
        const overflow = await tab.evaluate(() => document.documentElement.scrollWidth > innerWidth + 1);
        assert(!overflow, `Pagination overflows at ${width}px`);
        await tab.close();
      }
    }
    console.log('Pagination verified: numbers are circles or squares (never ovals) at 4 widths.');
  } finally { await browser.close(); }
})().catch(e => { console.error(e); process.exit(1); });
