const {chromium} = require('playwright');
const {execFileSync} = require('child_process');
const fs = require('fs');
const path = require('path');
const assert = require('assert/strict');
const root = path.join(__dirname,'..');
// Render the real PHP page and tabs without loading auth, sessions or private config.
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
foreach (['uploadEscape', 'uploadPage', 'adminNavigation'] as $name) eval(extractFunction($source,$name));
$section = getenv('ADMIN_TEST_SECTION') ?: 'design';
$start = strpos($source, '$tabs =');
$end = strpos($source, 'uploadPage($section', $start);
eval(substr($source,$start,$end-$start));
uploadPage($section === 'design' ? 'Diseño' : 'Perfil', adminNavigation('test-token') . $tabs . site_settings_form('test-token',site_settings_defaults(),$section),200,true);
`;
(async()=>{
  const browser=await chromium.launch({headless:true,executablePath:process.env.TEST_BROWSER||undefined,args:['--no-sandbox']});
  try {
    for(const section of ['design','profile']) {
      const html=execFileSync('php',['-r',fixture],{cwd:root,encoding:'utf8',env:{...process.env,ADMIN_TEST_SECTION:section}});
      for(const width of [320,375,768,1280,1920]) {
        const page=await browser.newPage({viewport:{width,height:1000}});
        await page.route('**/*',route=>route.abort());
        await page.setContent(html);
        for(const name of ['style','photo-editor','photo-manager']) await page.addStyleTag({content:fs.readFileSync(path.join(root,`assets/css/${name}.css`),'utf8')});
        // Inline admin styles belong after external CSS, exactly as in the actual page.
        await page.addStyleTag({content:html.match(/<style>([\s\S]*?)<\/style>/)[1]});
        await page.addScriptTag({content:fs.readFileSync(path.join(root,'assets/js/pwa.js'),'utf8')});
        for(const installed of [true,false]) {
          const result=await page.evaluate(installed=>{
            const install=document.querySelector('#installAppButton');install.hidden=installed;
            const tabs=document.querySelector('.admin-settings-tabs'),form=document.querySelector('.site-settings');
            const links=[...tabs.querySelectorAll('a')].map(e=>e.getBoundingClientRect().toJSON());
            return {nested:tabs.contains(form),tabs:tabs.getBoundingClientRect().toJSON(),form:form.getBoundingClientRect().toJSON(),links,install:install.getBoundingClientRect().toJSON(),installParent:install.parentElement===tabs,overflow:document.documentElement.scrollWidth>innerWidth+1};
          },installed);
          assert(!result.nested,'Settings form must not be inside navigation');
          assert(result.tabs.bottom<=result.form.top+1,'Tabs must be above, not beside the form');
          assert(Math.abs(result.links[0].top-result.links[1].top)<1,'Perfil and Diseño must align');
          assert(!result.overflow,`Admin overflow ${width}`);
          assert(result.installParent,'Install belongs with the settings actions');
          if(!installed) assert(result.install.bottom<=result.form.top+1,'Install button must remain above the form');
        }
        if(process.env.GALLERY_SCREENSHOTS) await page.screenshot({path:path.join(process.env.GALLERY_SCREENSHOTS,`admin-${section}-${width}.png`),fullPage:true});
        await page.close();
      }
    }
    console.log('Admin PHP rendering verified: Perfil/Diseño and install controls above both forms at five widths.');
  } finally {await browser.close();}
})().catch(e=>{console.error(e);process.exit(1);});
