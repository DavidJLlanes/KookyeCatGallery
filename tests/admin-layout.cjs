const {chromium} = require('playwright');
const {execFileSync} = require('child_process');
const fs = require('fs');
const path = require('path');
const assert = require('assert/strict');
const root = path.join(__dirname,'..');
// Render the real PHP shell and settings form without loading auth, sessions or private config.
const fixture = String.raw`
require 'inc/site-settings.php';
function app_version(): string { return 'layout-test'; }
require 'inc/admin-shell.php';
$_GET['settings'] = '1';
$section = getenv('ADMIN_TEST_SECTION') ?: 'design';
$_GET['section'] = $section;
$settings = site_settings_defaults();
$settings['section_order'] = ['social', 'gallery', 'categories', 'map', 'project'];
uploadPage($section === 'design' ? 'Diseño' : 'Perfil', adminNavigation('test-token') . site_settings_form('test-token', $settings, $section), 200, true);
`;
(async()=>{
  const browser=await chromium.launch({headless:true,executablePath:process.env.TEST_BROWSER||undefined,args:['--no-sandbox']});
  try {
    for(const section of ['design','profile']) {
      const html=execFileSync('php',['-r',fixture],{cwd:root,encoding:'utf8',env:{...process.env,ADMIN_TEST_SECTION:section}});
      for(const width of [320,375,768,959,960,1280,1920]) {
        const page=await browser.newPage({viewport:{width,height:900}});
        await page.route('**/*',route=>route.abort());
        await page.setContent(html);
        for(const name of ['style','photo-editor','photo-manager','admin']) await page.addStyleTag({content:fs.readFileSync(path.join(root,`assets/css/${name}.css`),'utf8')});
        await page.addScriptTag({content:fs.readFileSync(path.join(root,'assets/js/admin-ui.js'),'utf8')});
        for(const installed of [true,false]) {
          const result=await page.evaluate(installed=>{
            const install=document.querySelector('#installAppButton');install.hidden=installed;
            const box=el=>el?el.getBoundingClientRect().toJSON():null;
            const visible=el=>!!el&&getComputedStyle(el).display!=='none';
            const form=document.querySelector('.site-settings');
            const active=[...document.querySelectorAll('.admin-sidebar .admin-nav__link.is-active')].map(a=>a.textContent.trim());
            const navLabels=[...document.querySelectorAll('.admin-sidebar .admin-nav__link')].map(a=>a.textContent.trim());
            const sidebar=document.querySelector('.admin-sidebar'),tabbar=document.querySelector('.admin-tabbar'),topbar=document.querySelector('.admin-topbar');
            return {subnav:!!document.querySelector('.admin-subnav'),active,navLabels,form:box(form),install:box(install),installInTopbar:topbar.contains(install),
              sidebar:visible(sidebar),tabbar:visible(tabbar),tabbarBox:box(tabbar),
              savebar:box(document.querySelector('.admin-savebar')),
              fields:[...document.querySelectorAll('.admin-card select, .admin-card input:not([type=hidden]):not([type=checkbox]), .section-order__move button')].filter(visible).map(box).filter(b=>b.width>0),
              overflow:document.documentElement.scrollWidth>innerWidth+1,
              h1:document.querySelectorAll('h1').length,
              order:[...document.querySelectorAll('input[name="section_order[]"]')].map(i=>i.value)};
          },installed);
          assert(!result.subnav,'Perfil and Diseño are separate menu entries, not sub-tabs');
          assert.deepEqual(result.navLabels,['Subir foto','Gestionar fotos','Categorías','Perfil','Diseño y textos'],'Menu entries');
          assert.deepEqual(result.active,[section==='design'?'Diseño y textos':'Perfil'],'Active menu entry');
          assert(!result.overflow,`Admin overflow ${width}`);
          assert(result.installInTopbar,'Install belongs in the top bar');
          assert.equal(result.h1,1,'One h1 per page');
          assert.equal(result.sidebar,width>=960,`Sidebar only on desktop (${width})`);
          assert.equal(result.tabbar,width<960,`Tab bar only on mobile (${width})`);
          if(width<960) assert(Math.abs(result.tabbarBox.bottom-900)<=1,'Tab bar must be pinned to the bottom');
          assert(result.savebar.height>0,'Save bar missing');
          for(const f of result.fields) assert(f.right<=width+1&&f.left>=-1,`Control clipped at ${width}`);
          if(section==='design') assert.deepEqual(result.order,['social','gallery','categories','map','project'],'Saved order must reach the form');
        }
        if(section==='design') {
          // Galería premium: al elegir una se desactivan los ajustes de la galería estándar que no use (8 con la baraja; 6 con las burbujas, que respetan «Fotos visibles a la vez»); al quitarla se reactivan.
          const premium=await page.evaluate(()=>{
            const select=document.querySelector('#setting-gallery_premium');
            const fields=[...document.querySelectorAll('[data-premium-off]')];
            const change=value=>{select.value=value;select.dispatchEvent(new Event('change',{bubbles:true}));};
            const count=()=>fields.filter(field=>field.disabled).length;
            const result={total:fields.length,initial:count(),titleField:!!document.querySelector('#setting-site_title'),options:[...select.options].map(o=>o.value)};
            change('deck');result.afterDeck=count();result.noteShown=!document.querySelector('[data-premium-note]').hidden;
            result.dimmed=document.querySelectorAll('.upload-field.is-disabled').length;
            change('bubbles');result.afterBubbles=count();result.photosEnabled=!document.querySelector('#setting-photos_desktop').disabled&&!document.querySelector('#setting-photos_mobile').disabled;
            change('none');result.afterNone=count();result.noteHidden=document.querySelector('[data-premium-note]').hidden;
            return result;
          });
          assert.deepEqual(premium,{total:8,initial:0,titleField:true,options:['none','deck','bubbles','squares','drum'],afterDeck:8,noteShown:true,dimmed:8,afterBubbles:6,photosEnabled:true,afterNone:0,noteHidden:true},'Premium gallery must toggle the standard gallery fields');
        }
        if(section==='design') {
          // Reordering moves the DOM rows (which is what gets submitted) and disables the edge buttons.
          await page.click('.section-order__item[data-section-key="gallery"] [data-move="up"]');
          const order=await page.evaluate(()=>[...document.querySelectorAll('input[name="section_order[]"]')].map(i=>i.value));
          assert.deepEqual(order,['gallery','social','categories','map','project'],'Move up failed');
          await page.click('.section-order__item[data-section-key="gallery"] [data-move="down"]');
          await page.click('.section-order__item[data-section-key="project"] [data-move="up"]');
          const after=await page.evaluate(()=>({order:[...document.querySelectorAll('input[name="section_order[]"]')].map(i=>i.value),
            firstUp:document.querySelector('.section-order__item:first-child [data-move="up"]').disabled,
            lastDown:document.querySelector('.section-order__item:last-child [data-move="down"]').disabled}));
          assert.deepEqual(after.order,['social','gallery','categories','project','map'],'Move down/up failed');
          assert(after.firstUp&&after.lastDown,'Edge buttons must be disabled');
        }
        if(process.env.GALLERY_SCREENSHOTS) await page.screenshot({path:path.join(process.env.GALLERY_SCREENSHOTS,`admin-${section}-${width}.png`),fullPage:true});
        await page.close();
      }
    }
    console.log('Admin shell verified: sidebar/tab bar, separate Perfil/Diseño menu entries, section ordering and save bar at seven widths.');
  } finally {await browser.close();}
})().catch(e=>{console.error(e);process.exit(1);});
