const { chromium } = require('playwright');
const fs = require('fs');
const path = require('path');
const assert = require('assert/strict');
const root = path.join(__dirname, '..');
const css = ['style', 'site-design', 'interface-worlds', 'gallery-layout'].map(n => fs.readFileSync(path.join(root, `assets/css/${n}.css`), 'utf8')).join('\n');
const scripts = ['gallery-layout', 'main'].map(n => fs.readFileSync(path.join(root, `assets/js/${n}.js`), 'utf8'));
const designs = ['standard', 'grid', 'mosaic', 'asymmetric', 'scattered', 'category-rails', 'exhibition', 'contact-sheet', 'narrative', 'triptych'];
const ratios = [.67, 1.8, 1.33, .8, 2.2, 1];
const cards = Array.from({length: 67}, (_, i) => {
  const svg = `<svg xmlns="http://www.w3.org/2000/svg" width="${600 * ratios[i % 6]}" height="600"><rect width="100%" height="100%" fill="${['#2b779e','#528834','#9c5639'][i%3]}"/><circle cx="30%" cy="35%" r="80" fill="#e0c58c"/><text x="10" y="550" fill="white" font-size="70">Foto ${i+1}</text></svg>`;
  return `<article class="card" data-category="${i%2?'Arquitectura':'Naturaleza'}" data-slug="foto-${i}" style="--aspect:${ratios[i%6]}"><a class="card__btn" href="/foto/foto-${i}"><picture class="card__picture"><img class="card__img" loading="lazy" src="data:image/svg+xml,${encodeURIComponent(svg)}" alt="Foto ${i}"></picture><div class="card__title-base"><h3 class="card__title">Fotografía ${i+1}</h3></div></a></article>`;
}).join('');
(async () => {
  const browser = await chromium.launch({headless:true, executablePath:process.env.TEST_BROWSER || undefined, args:['--no-sandbox']});
  try {
    let page;
    const errors = [];
    const check = async (design, width, expected) => {
      const state = await page.evaluate(() => {
        const g = document.querySelector('#masonry'), rect = g.getBoundingClientRect();
        const visible = [...g.querySelectorAll('.card')].filter(c => getComputedStyle(c).display !== 'none');
        return {height:rect.height, width:rect.width, galleryStyle:g.style.cssText, overflow:document.documentElement.scrollWidth>innerWidth+1,
          cards: visible.map(c => {const r=c.getBoundingClientRect(), p=c.querySelector('picture').getBoundingClientRect();return {x:r.x-rect.x,y:r.y-rect.y,w:r.width,h:r.height,pw:p.width,ph:p.height,ratio:Number(c.style.getPropertyValue('--aspect')),hidden:c.hidden,style:c.style.cssText,transform:getComputedStyle(c).transform,header:document.body.dataset.headerMobile};})};
      });
      assert.equal(state.cards.length, expected, `${design}/${width}: pagination leaked hidden cards`);
      if(state.overflow) console.log(await page.evaluate(()=>[...document.querySelectorAll('body *')].filter(e=>e.getBoundingClientRect().right>innerWidth+1).map(e=>({tag:e.tagName,cls:e.className,right:e.getBoundingClientRect().right,header:document.body.dataset.headerMobile})).slice(0,8)));
      assert(!state.overflow, `${design}/${width}: horizontal page overflow`);
      if (design === 'category-rails') return;
      state.cards.forEach((a,i) => {
        assert(a.w > 5 && a.h > 5 && a.ph > 5, `${design}: collapsed card`);
        assert(a.y + a.h < state.height + 10, `${design}/${width}: gallery does not contain card ${JSON.stringify({a,stateHeight:state.height,galleryStyle:state.galleryStyle})}`);
        if (['mosaic','asymmetric','standard'].includes(design)) assert(Math.abs(a.pw/a.ph-a.ratio)<.03, `${design}: photo cropped ${JSON.stringify(a)}`);
        if (design==='scattered') assert(Math.abs(a.pw/a.ph-1)<.01, 'Album not square');
        if (design==='grid') assert(Math.abs(a.pw/a.ph-1)<.01, 'Square grid not square');
        if (design!=='scattered') for(const b of state.cards.slice(i+1)) assert(!(Math.min(a.x+a.w,b.x+b.w)-Math.max(a.x,b.x)>2 && Math.min(a.y+a.h,b.y+b.h)-Math.max(a.y,b.y)>2), `${design}/${width}: overlap`);
      });
    };
    for (const width of [375,768,1280,1920]) for(const design of designs) {
      page = await browser.newPage({reducedMotion:'reduce'});
      page.on('pageerror', error => errors.push(error.message));
      await page.route('**/*', route => route.abort());
      await page.setViewportSize({width,height:900});
      await page.setContent(`<style>${css}</style><body data-grid="masonry" data-photos-mobile="8" data-photos-desktop="15" data-palette="current" data-header-mobile="scrapbook" data-header-desktop="observatory" data-show-categories="false" data-gallery-mobile="${design}" data-gallery-desktop="${design}" style="--columns-mobile:3;--columns-desktop:3"><main class="gallery-section" id="galeria"><div id="categoriesFilter"><button class="categories-filter__chip" data-category="Naturaleza">Naturaleza</button></div><section class="masonry" id="masonry">${cards}</section><nav id="jsPagination"></nav></main></body>`);
      await page.evaluate(() => {window.siteText = s=>s; window.siteTextHTML=s=>s;});
      for(const content of scripts) await page.addScriptTag({content});
      await page.waitForFunction(() => document.querySelector('#masonry').hasAttribute('data-layout'));
      const count = design==='category-rails'?67:width<=768?8:15;
      await check(design,width,count);
      if(design==='standard') {
        // Reproduce the user's saved combination: square grid + Masonry gallery.
        for(const grid of ['square','landscape','portrait','adaptive','masonry']) {
          await page.evaluate(grid=>{document.body.dataset.grid=grid;window.layoutPhotoGallery(document.querySelector('#masonry'),'standard');},grid);
          await check(design,width,count);
        }
      }
      if(design==='category-rails') {
        assert.equal(await page.locator('.category-rail__title:visible').count(),2,'Category names disappeared with filter disabled');
        const next=page.locator('.category-rail__arrow').nth(1);
        await next.click();
        await page.waitForFunction(()=>document.querySelector('.category-rail__track').scrollLeft>0);
      } else {
        await page.locator('.pagination__nav--next').click();
        await check(design,width,count);
      }
      await page.locator('.categories-filter__chip').click();
      await check(design,width,design==='category-rails'?34:count);
      if(design==='mosaic') {
        await page.locator('#masonry').scrollIntoViewIfNeeded();
        await page.waitForFunction(()=>document.body.dataset.activePhotoTone==='true');
      }
      for(const header of width<=768?['current','reel','atlas','studio','orbit']:['current','museum','newsroom','noir','blueprint']) {
        await page.evaluate(({header,width}) => {document.body.dataset[width<=768?'headerMobile':'headerDesktop']=header;window.dispatchEvent(new Event('resize'));},{header,width});
        await page.evaluate(()=>new Promise(requestAnimationFrame));
        await check(design,width,design==='category-rails'?34:count);
      }
      if(process.env.GALLERY_SCREENSHOTS && [375,1280].includes(width)) {
        fs.mkdirSync(process.env.GALLERY_SCREENSHOTS,{recursive:true});
        if(process.env.GALLERY_REAL_PHOTOS) {
          const dir=path.join(root,'imagenes/desktop');
          const photos=fs.readdirSync(dir).filter(n=>n.endsWith('.webp')).slice(0,20).map(n=>'data:image/webp;base64,'+fs.readFileSync(path.join(dir,n)).toString('base64'));
          await page.evaluate(async photos=>{
            const cards=[...document.querySelectorAll('.card:not(.is-hidden)')];
            await Promise.all(cards.map(async(c,i)=>{const img=c.querySelector('img');img.loading='eager';img.src=photos[i%photos.length];await img.decode();c.style.setProperty('--aspect',img.naturalWidth/img.naturalHeight);img.classList.add('is-loaded');c.classList.add('is-revealed');}));
            window.layoutPhotoGallery(document.querySelector('#masonry'),document.body.dataset.galleryActive);
          },photos);
        }
        await page.waitForFunction(()=>[...document.querySelectorAll('.card:not(.is-hidden)')].every(c=>c.classList.contains('is-revealed')));
        await page.evaluate(()=>new Promise(resolve=>setTimeout(resolve,250)));
        await page.screenshot({path:path.join(process.env.GALLERY_SCREENSHOTS,`${design}-${width}.png`),fullPage:true});
      }
      await page.close();
    }
    assert.deepEqual(errors,[],'Browser errors');
    console.log('Gallery regression passed: real main.js, 67 photos, 10 designs, 4 viewports, themes, pagination, filtering, arrows and color extraction.');
  } finally {await browser.close();}
})().catch(error=>{console.error(error);process.exit(1);});
