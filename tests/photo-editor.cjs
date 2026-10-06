const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const { chromium } = require('playwright');
const root = path.join(__dirname, '..');
const admin = fs.readFileSync(path.join(root, 'admin.php'), 'utf8');
const template = admin.match(/\$formTemplate = <<<'HTML'\n([\s\S]*?)\nHTML;/)[1];
const form = template.replace(/__[A-Z_]+__/g, token => ({__CSRF__:'test',__ACTION__:'complete',__FILE_REQUIRED__:'required'}[token] || ''));
const scripts = ['photo-filter-engine.js','photo-editor-presets.js','photo-editor.js'];
// This uses the real administration form without PHP auth or a live server.
const markup = '<!doctype html><html><head><style>:root{--sans:Arial,sans-serif}body{margin:0}' + fs.readFileSync(path.join(root,'assets/css/photo-editor.css'),'utf8') + '</style>' + scripts.map(name => '<script src="/assets/js/'+name+'" defer></script>').join('') + '</head><body>'+form+'</body></html>';
const settle = page => page.evaluate(() => new Promise(resolve => requestAnimationFrame(() => requestAnimationFrame(resolve))));
const snapshot = page => page.evaluate(() => {
  const canvas=document.getElementById('editorCanvas'),data=canvas.getContext('2d').getImageData(0,0,canvas.width,canvas.height).data;
  let hash=2166136261;for(let i=0;i<data.length;i++)hash=Math.imul(hash^data[i],16777619);
  return {hash:hash>>>0,width:canvas.width,height:canvas.height};
});
const setIntensity = async (page,value) => {
  await page.locator('#filterStrength').evaluate((input,v)=>{input.value=String(v);input.dispatchEvent(new Event('input',{bubbles:true}));},value);
  await settle(page);
};
(async()=>{
  const browser = await chromium.launch({headless:true,args:['--no-sandbox']});
  try {
    for(const width of [1280,375]) {
      const page = await browser.newPage({viewport:{width,height:860},deviceScaleFactor:1});
      const errors=[];page.on('pageerror',error=>errors.push(error.message));
      let exported=null;
      await page.route('https://filters.test/**',async route=>{
        const request=route.request(),url=new URL(request.url());
        if(request.method()==='POST') {
          const body=request.postDataBuffer(),text=body.toString('latin1');
          if(text.includes('name="edited_photo"')) {
            const start=text.indexOf('\r\n\r\n',text.indexOf('name="edited_photo"'))+4;
            const boundary=request.headers()['content-type'].match(/boundary=(.+)$/)[1];
            const end=text.indexOf('\r\n--'+boundary,start);exported=body.subarray(start,end);
            await route.fulfill({contentType:'application/json',body:JSON.stringify({ok:false,error:'Export captured by test.'})});
          }else await route.fulfill({contentType:'application/json',body:JSON.stringify({ok:true,gps:null})});
        }else if(url.pathname==='/admin.php')await route.fulfill({contentType:'text/html',body:markup});
        else if(url.pathname.startsWith('/assets/js/'))await route.fulfill({contentType:'text/javascript',body:fs.readFileSync(path.join(root,url.pathname),'utf8')});
        else await route.abort();
      });
      await page.goto('https://filters.test/admin.php');
      const png=await page.evaluate(()=>{
        const c=document.createElement('canvas');c.width=480;c.height=320;const ctx=c.getContext('2d');
        const colors=['#d2946e','#2d6829','#196dad','#b51d37','#24bcab','#ce42ac','#e1bf48','#655039'];
        colors.forEach((color,n)=>{const g=ctx.createLinearGradient(0,0,480,0);g.addColorStop(0,'#080809');g.addColorStop(.65,color);g.addColorStop(1,'#fbf6ed');ctx.fillStyle=g;ctx.fillRect(0,n*40,480,40);});
        ctx.fillStyle='white';ctx.fillRect(240,110,25,70);return c.toDataURL('image/png').split(',')[1];
      });
      await page.setInputFiles('#photoFile',{name:'fixture.png',mimeType:'image/png',buffer:Buffer.from(png,'base64')});
      await page.locator('#startEditingButton').waitFor({state:'visible'});
      assert.equal(await page.locator('#startEditingButton').isDisabled(),false,'Selecting a photo enables the editing step.');
      await page.locator('#startEditingButton').click();
      await page.locator('#photoEditor').waitFor({state:'visible'});await settle(page);
      const baseline=await snapshot(page);
      assert.equal(baseline.width,480);
      await page.locator('[data-tool="filters"]').click();await settle(page);
      assert.equal(await page.locator('#photoEditorPresets button').count(),101);
      assert.equal(await page.locator('#filterCategory option').count(),11);
      const looks=await page.evaluate(()=>window.photoEditorPresetData.presets.map(p=>({id:p.id,name:p.name,category:p.category})));
      const chosen=width>760?looks:looks.filter((_,i)=>i%10===0);
      for(const look of chosen) {
        await page.locator('[data-preset="'+look.id+'"]').click();await settle(page);
        const result=await snapshot(page);
        assert.notEqual(result.hash,baseline.hash,look.name+' must change preview.');
        assert.equal(await page.locator('#uploadFeedback').textContent(),'');
        assert((await page.locator('[data-preset="'+look.id+'"] img').getAttribute('src')).startsWith('data:image/jpeg'),look.name+' needs a real thumbnail.');
        await setIntensity(page,0);
        assert.deepEqual(await snapshot(page),baseline,look.name+': original at 0%, including all spatial effects.');
        await setIntensity(page,100);
        assert.deepEqual(await snapshot(page),result,look.name+': repeated renders must not flicker.');
      }
      for(const category of [...new Set(looks.map(p=>p.category))]){
        await page.selectOption('#filterCategory',category);assert.equal(await page.locator('#photoEditorPresets button').count(),11);
      }
      await page.locator('[data-tool="exposure"]').click();
      await page.locator('#toolSlider').evaluate(input=>{input.value='20';input.dispatchEvent(new Event('input',{bubbles:true}));});await settle(page);
      const manual=await snapshot(page);assert.notEqual(manual.hash,baseline.hash,'Manual exposure must affect the preview.');
      await page.locator('[data-tool="filters"]').click();
      await page.selectOption('#filterCategory','all');
      await page.locator('[data-preset="look-005"]').click();await settle(page);
      await setIntensity(page,0);
      // The currently selected filter must disappear while exposure remains.
      await page.locator('[data-preset="original"]').click();await settle(page);
      const adjustedOriginal=await snapshot(page);
      await page.locator('[data-preset="look-005"]').click();await setIntensity(page,0);
      assert.deepEqual(await snapshot(page),adjustedOriginal,'0% must preserve manual exposure.');
      await setIntensity(page,100);
      const preview=await page.evaluate(()=>{
        const c=document.getElementById('editorCanvas');return Array.from(c.getContext('2d').getImageData(0,0,c.width,c.height).data);
      });
      await page.locator('#editorContinue').click();
      await page.locator('#photoTitle').fill('Browser filter check');
      await page.selectOption('#photoCategoryChoice','__new__');await page.locator('#photoCategory').fill('Test');
      await page.locator('#photoLatitude').fill('42');await page.locator('#photoLongitude').fill('-5');
      await page.locator('#uploadSubmit').click();
      await page.waitForFunction(()=>document.querySelector('#uploadFeedback').textContent.includes('Export captured'));
      assert(exported?.length>1000,'The editor must export a JPEG.');
      const difference=await page.evaluate(async({jpeg,preview})=>{
        const img=new Image();img.src='data:image/jpeg;base64,'+jpeg;await img.decode();
        const c=document.createElement('canvas');c.width=img.naturalWidth;c.height=img.naturalHeight;c.getContext('2d').drawImage(img,0,0);
        const data=c.getContext('2d').getImageData(0,0,c.width,c.height).data;let total=0;
        for(let i=0;i<data.length;i++)if(i%4!==3)total+=Math.abs(data[i]-preview[i]);
        return total/(data.length/4*3);
      },{jpeg:exported.toString('base64'),preview});
      assert(difference<5,'Export and preview must agree apart from JPEG compression: '+difference);
      assert.deepEqual(errors,[]);
      console.log(width+'px: '+chosen.length+' preview/thumbnail/intensity combinations, ten families and JPEG export verified.');
      await page.close();
    }
  } finally { await browser.close(); }
})().catch(error=>{console.error(error);process.exit(1);});
