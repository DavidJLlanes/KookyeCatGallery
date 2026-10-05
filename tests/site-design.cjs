const {chromium}=require('playwright');
const fs=require('fs');
const path=require('path');
const {execFileSync}=require('child_process');
const root=path.join(__dirname,'..');
(async()=>{
  const browser=await chromium.launch({headless:true,args:['--no-sandbox']});
  const page=await browser.newPage();
  await page.route('**/*',route=>route.abort());
  const rendered=execFileSync('php',['index.php'],{cwd:root,encoding:'utf8'});
  const css=fs.readFileSync(path.join(root,'assets/css/style.css'),'utf8')+'\n'+fs.readFileSync(path.join(root,'assets/css/site-design.css'),'utf8')+'\n'+fs.readFileSync(path.join(root,'assets/css/interface-worlds.css'),'utf8');
  const cards=Array.from({length:12},(_,i)=>`<article class="card is-revealed" style="--aspect:${i%2?0.7:1.8}"><button class="card__btn"><picture class="card__picture"><img class="card__img" alt="" src="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='180' height='100'%3E%3Crect width='180' height='100' fill='%239b7862'/%3E%3C/svg%3E"></picture></button></article>`).join('');
  await page.setContent(rendered.replace('</head>',`<style>${css}</style></head>`));
  await page.addStyleTag({content:'*,*::before,*::after{transition:none!important;animation:none!important}'});
  await page.evaluate(cards=>{document.querySelector('.gallery-section').innerHTML=`<section id="masonry" class="masonry">${cards}</section>`;document.querySelector('#preloader')?.remove();},cards);
  let checks=0;
  for(const width of [375,768,769,1280]){
    await page.setViewportSize({width,height:900});
    const max=width<=768?4:10;
    for(const mode of ['adaptive','masonry','square','landscape','portrait'])for(let cols=1;cols<=max;cols++){
      const result=await page.evaluate(({mode,cols,width})=>{
        const body=document.body;body.dataset.grid=mode;body.style.setProperty(width<=768?'--columns-mobile':'--columns-desktop',cols);
        const style=getComputedStyle(document.querySelector('#masonry'));
        const columns=style.display==='grid'?style.gridTemplateColumns.split(' ').length:Number(style.columnCount);
        const picture=document.querySelector('.card__picture').getBoundingClientRect();
        return {columns,ratio:picture.width/picture.height,overflow:document.documentElement.scrollWidth>innerWidth};
      },{mode,cols,width});
      if(result.columns!==cols||result.overflow)throw new Error(JSON.stringify({width,mode,cols,result}));
      const expected=mode==='square'?1:mode==='landscape'?4/3:mode==='portrait'?3/4:mode==='masonry'?1.8:null;
      if(expected&&Math.abs(result.ratio-expected)>.02)throw new Error('Aspect '+JSON.stringify({width,mode,cols,result,expected}));
      checks++;
    }
  }
  for(const width of [375,1280]){
    await page.setViewportSize({width,height:900});
    const signatures=[];
    for(const mode of ['current','centered','compact','editorial']){
      const signature=await page.evaluate(({width,mode})=>{
        document.body.dataset[width<=768?'headerMobile':'headerDesktop']=mode;
        const el=document.querySelector(width<=768?'.mobile-profile':'.hero');
        const s=getComputedStyle(el);
        return [s.height,s.padding,s.borderTopWidth].join('|');
      },{width,mode});signatures.push(signature);
    }
    if(new Set(signatures).size!==4)throw new Error('Headers not distinct '+JSON.stringify({width,signatures}));
  }
  // Desktop hero headers fill the viewport at common and unusually wide/short ratios.
  for(const [width,height] of [[1024,768],[1280,900],[1366,600],[1920,1080]]){
    await page.setViewportSize({width,height});
    for(const mode of ['current','centered','compact','editorial','museum','observatory','newsroom','noir','blueprint']){
      const box=await page.evaluate(mode=>{
        document.body.dataset.headerDesktop=mode;
        const rect=document.querySelector('.hero').getBoundingClientRect();
        return {width:rect.width,height:rect.height,left:rect.left,top:rect.top};
      },mode);
      if(Math.abs(box.width-width)>1||Math.abs(box.height-height)>1||Math.abs(box.left)>1||Math.abs(box.top)>1)
        throw new Error('Desktop hero must match viewport '+JSON.stringify({width,height,mode,box}));
    }
  }
  // Every template must inherit the same palette, grid mode and column settings.
  const worlds={
    mobile:['reel','atlas','studio','orbit','scrapbook'],
    desktop:['museum','observatory','newsroom','noir','blueprint']
  };
  await page.evaluate(()=>{
    document.querySelectorAll('#masonry .card__btn').forEach((button,i)=>{
      if(i%4!==0)button.insertAdjacentHTML('beforeend','<div class="card__title-base"><h3 class="card__title">Luz y paisaje felino</h3></div>');
    });
  });
  const rgb=value=>value.match(/[0-9.]+/g).slice(0,3).map(Number).map(n=>{
    n/=255;return n<=.04045?n/12.92:((n+.055)/1.055)**2.4;
  });
  const luminance=value=>rgb(value).reduce((sum,n,i)=>sum+n*[.2126,.7152,.0722][i],0);
  const contrast=(a,b)=>{const x=luminance(a),y=luminance(b);return (Math.max(x,y)+.05)/(Math.min(x,y)+.05)};
  const expectedBg={black:'rgb(0, 0, 0)',white:'rgb(255, 255, 255)',cyberpunk:'rgb(9, 6, 25)',japanese:'rgb(247, 242, 232)'};
  let worldChecks=0;
  const mapAppearances={};
  for(const [device,modes] of Object.entries(worlds)){
    const width=device==='mobile'?375:1280;
    await page.setViewportSize({width,height:900});
    for(const mode of modes){
      for(const palette of ['current','black','white','cyberpunk','japanese']){
        for(const gridMode of ['adaptive','masonry','square','landscape','portrait']){
          for(const cols of device==='mobile'?[2,4]:[2,5]){
            const audit=await page.evaluate(({device,mode,palette,gridMode,cols})=>{
              const body=document.body;
              body.dataset.headerMobile=device==='mobile'?mode:'current';
              body.dataset.headerDesktop=device==='desktop'?mode:'current';
              body.dataset.palette=palette;
              body.dataset.grid=gridMode;
              body.style.setProperty(device==='mobile'?'--columns-mobile':'--columns-desktop',cols);
              const gallery=document.querySelector('#masonry');
              const galleryStyle=getComputedStyle(gallery);
              const columns=galleryStyle.display==='grid'?galleryStyle.gridTemplateColumns.split(' ').length:Number(galleryStyle.columnCount);
              const cards=Array.from(gallery.querySelectorAll('.card')).slice(0,3).map(card=>{
                const button=card.querySelector('.card__btn').getBoundingClientRect();
                const picture=card.querySelector('.card__picture').getBoundingClientRect();
                return {cardHeight:card.getBoundingClientRect().height,buttonHeight:button.height,pictureHeight:picture.height,
                  pictureRatio:card.querySelector('.card__picture').offsetWidth/card.querySelector('.card__picture').offsetHeight,buttonAspect:getComputedStyle(card.querySelector('.card__btn')).aspectRatio};
              });
              const footer=document.querySelector('.site-footer');
              const link=footer.querySelector('a');
              return {columns,display:galleryStyle.display,cards,
                bg:getComputedStyle(body).backgroundColor,
                accent:getComputedStyle(body).getPropertyValue('--accent').trim(),
                galleryBg:getComputedStyle(document.querySelector('.gallery-section')).backgroundColor,
                headerBg:getComputedStyle(document.querySelector(device==='mobile'?'.mobile-profile':'.hero')).backgroundColor,
                footerBg:getComputedStyle(footer).backgroundColor,
                footerColor:getComputedStyle(footer).color,
                linkColor:getComputedStyle(link).color,
                footerHeight:footer.getBoundingClientRect().height,
                mapAppearance:(()=>{const section=document.querySelector('.map-section'),container=document.querySelector('.map-container'),title=document.querySelector('.map-section__title'),select=document.querySelector('#mapCategory'),map=document.querySelector('#map');const s=getComputedStyle(section),c=getComputedStyle(container),t=getComputedStyle(title),f=getComputedStyle(select),m=getComputedStyle(map);return [s.backgroundColor,s.backgroundImage,s.color,s.borderTop,s.getPropertyValue('--bg').trim(),s.getPropertyValue('--fg').trim(),s.getPropertyValue('--accent').trim(),t.fontFamily,t.fontSize,c.border,c.borderRadius,c.transform,f.backgroundColor,f.color,f.border,m.height,m.borderRadius].join('|')})(),
                overflow:document.documentElement.scrollWidth>innerWidth};
            },{device,mode,palette,gridMode,cols});
            const appearanceKey=device;
            mapAppearances[appearanceKey]??=audit.mapAppearance;
            if(audit.mapAppearance!==mapAppearances[appearanceKey])throw new Error('Map appearance changed with a theme or template '+JSON.stringify({device,mode,palette,gridMode,cols,expected:mapAppearances[appearanceKey],actual:audit.mapAppearance}));
            if(audit.columns!==cols||audit.overflow)throw new Error('World grid '+JSON.stringify({device,mode,palette,gridMode,cols,audit}));
            if(expectedBg[palette]&&audit.bg!==expectedBg[palette])throw new Error('World palette ignored '+JSON.stringify({device,mode,palette,audit}));
            const expected=gridMode==='square'?1:gridMode==='landscape'?4/3:gridMode==='portrait'?3/4:gridMode==='masonry'?1.8:null;
            if(expected&&Math.abs(audit.cards[0].pictureRatio-expected)>.04)
              throw new Error('World aspect '+JSON.stringify({device,mode,palette,gridMode,cols,expected,audit}));
            for(const card of audit.cards){
              if(card.buttonHeight<card.pictureHeight-3||card.buttonHeight>card.pictureHeight+75||card.cardHeight>card.buttonHeight+85)
                throw new Error('World card has empty space '+JSON.stringify({device,mode,palette,gridMode,cols,card}));
            }
            if(device==='desktop'&&(audit.footerHeight<40||contrast(audit.footerBg,audit.footerColor)<4.5||contrast(audit.footerBg,audit.linkColor)<4.5))
              throw new Error('Footer missing or illegible '+JSON.stringify({device,mode,palette,gridMode,cols,audit}));
            worldChecks++;
          }
        }
      }
    }
  }

  await page.evaluate(()=>{document.body.dataset.headerMobile='current';document.body.dataset.headerDesktop='current';});
  for(const palette of ['current','black','white','cyberpunk','japanese']){
    const colors=await page.evaluate(palette=>{
      document.body.dataset.palette=palette;
      const s=getComputedStyle(document.body);
      const project=getComputedStyle(document.querySelector('.project'));
      return {bg:s.backgroundColor,fg:s.color,projectBg:project.backgroundColor,projectGradient:project.backgroundImage};
    },palette);
    if(colors.bg===colors.fg)throw new Error('Palette contrast '+palette);
    if(palette!=='current'&&(colors.projectBg!==colors.bg||colors.projectGradient!=='none'))throw new Error('Project did not adopt palette '+palette);
  }
  await page.screenshot({path:path.join(root,'site-design-preview.png'),fullPage:false});
  console.log(`${checks} standard combinations and ${worldChecks} standard template combinations verified in Chromium.`);
  await browser.close();
})().catch(error=>{console.error(error);process.exit(1)});
