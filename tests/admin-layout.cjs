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
$source = file_get_contents('admin.php');
function extractFunction(string $source, string $name): string {
    $start = strpos($source, 'function ' . $name . '(');
    if ($start === false) throw new RuntimeException('Missing function: ' . $name);
    $tokens = token_get_all('<?php ' . substr($source, $start));
    $code = ''; $depth = 0; $opened = false;
    foreach ($tokens as $token) {
        if (is_array($token)) { if ($token[0] !== T_OPEN_TAG) $code .= $token[1]; continue; }
        $code .= $token;
        if ($token === '{') { $depth++; $opened = true; }
        if ($token === '}' && --$depth === 0 && $opened) return $code;
    }
    throw new RuntimeException('Unclosed function: ' . $name);
}
foreach (['uploadEscape', 'adminIcon', 'adminActiveSection', 'adminShellHtml', 'uploadPage', 'adminNavigation'] as $name) eval(extractFunction($source,$name));
$_GET['settings'] = '1';
$section = getenv('ADMIN_TEST_SECTION') ?: 'design';
$start = strpos($source, '$tabs =');
$end = strpos($source, 'uploadPage($section', $start);
eval(substr($source,$start,$end-$start));
$settings = site_settings_defaults();
$settings['section_order'] = ['social', 'gallery', 'categories', 'map', 'project'];
uploadPage($section === 'design' ? 'Diseño' : 'Perfil', adminNavigation('test-token') . $tabs . site_settings_form('test-token', $settings, $section), 200, true);
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
            const tabs=document.querySelector('.admin-subnav'),form=document.querySelector('.site-settings');
            const links=[...tabs.querySelectorAll('a')].map(box);
            const sidebar=document.querySelector('.admin-sidebar'),tabbar=document.querySelector('.admin-tabbar'),topbar=document.querySelector('.admin-topbar');
            return {nested:tabs.contains(form),tabs:box(tabs),form:box(form),links,install:box(install),installInTopbar:topbar.contains(install),
              sidebar:visible(sidebar),tabbar:visible(tabbar),tabbarBox:box(tabbar),
              savebar:box(document.querySelector('.admin-savebar')),
              fields:[...document.querySelectorAll('.admin-card select, .admin-card input:not([type=hidden]):not([type=checkbox]), .section-order__move button')].filter(visible).map(box).filter(b=>b.width>0),
              overflow:document.documentElement.scrollWidth>innerWidth+1,
              h1:document.querySelectorAll('h1').length,
              order:[...document.querySelectorAll('input[name="section_order[]"]')].map(i=>i.value)};
          },installed);
          assert(!result.nested,'Settings form must not be inside navigation');
          assert(result.tabs.bottom<=result.form.top+1,'Sub-tabs must be above, not beside the form');
          assert(Math.abs(result.links[0].top-result.links[1].top)<1,'Perfil and Diseño must align');
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
    console.log('Admin shell verified: sidebar/tab bar, sub-tabs, section ordering and save bar at seven widths.');
  } finally {await browser.close();}
})().catch(e=>{console.error(e);process.exit(1);});
