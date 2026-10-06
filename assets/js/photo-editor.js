(() => {
    'use strict';
    const form = document.getElementById('photoUploadForm');
    if (!form) return;
    const $ = id => document.getElementById(id);
    const fileInput=$('photoFile'),editor=$('photoEditor'),details=$('photoDetails'),stage=$('editorStage');
    const canvas=$('editorCanvas'),backdrop=$('editorBackdrop'),cropSelection=$('cropSelection');
    const toolRail=$('toolRail'),toolPanel=$('toolPanel'),toolSlider=$('toolSlider'),toolName=$('toolName'),toolValue=$('toolValue');
    const filterPanel=$('filterPanel'),filterList=$('photoEditorPresets'),filterCategory=$('filterCategory'),filterStrength=$('filterStrength'),filterStrengthValue=$('filterStrengthValue');
    const cropPanel=$('cropPanel'),cropRatios=$('cropRatios'),cropApply=$('cropApply'),cropCancel=$('cropCancel'),cropReset=$('cropReset');
    const zoomReset=$('editorZoomReset'),reviewPreview=$('reviewPreview'),feedback=$('uploadFeedback'),submitButton=$('uploadSubmit');
    const csrf=form.querySelector('[name="csrf"]').value,maxBytes=15*1024*1024;
    const startEditingButton=$('startEditingButton');
    let selectedUploadFile=null;
    const categoryChoice=$('photoCategoryChoice'),categoryInput=$('photoCategory');
    const editFile=form.dataset.editFile||'',isEditing=editFile!=='',hasStoredEdit=form.dataset.hasEdited==='1';
    let sourceVariant='published';
    categoryChoice.addEventListener('change',()=>{
        const custom=categoryChoice.value==='__new__';
        categoryInput.disabled=categoryChoice.value==='';
        categoryInput.hidden=!custom;
        categoryInput.value=custom?'':categoryChoice.value;
        if(custom)categoryInput.focus();
    });
    const {presets,families}=window.photoEditorPresetData;
    const byId=new Map(presets.map(p=>[p.id,p]));
    const presetCountByFamily=new Map(families.map(f=>[f.id,presets.filter(p=>p.category===f.id).length]));
    const defaults={exposure:0,highlights:0,shadows:0,contrast:0,temperature:0,tint:0,saturation:0,vibrance:0,sharpness:0,structure:0,perspectiveVertical:0,perspectiveHorizontal:0,fade:0,vignette:0};
    let adjustments={...defaults},image=null,sourceUrl='',ready=false,submitting=false,saving=false;
    let activePreset=null,filterMix=100,rotation=0,appliedCrop=null,draftCrop=null,draftRatio=null,cropEditing=false,activeTool='exposure';
    let zoom=1,panX=0,panY=0,zoomGesture=null,renderQueued=false,renderVersion=0,displayBox={left:0,top:0,width:1,height:1};
    const pointers=new Map(),thumbnailCache=new Map();
    let thumbnailObserver=null,thumbnailBase=null;
    const clamp=(v,a,b)=>Math.max(a,Math.min(b,v));
    const setMessage=(message,error=false)=>{feedback.textContent=message;feedback.classList.toggle('upload-message--error',error);editorProgress.textContent=message;editorProgress.hidden=!message||editor.hidden;editorProgress.classList.toggle('is-error',error);};
    const readExifGps=async file=>{
        if(!/\\.(?:jpe?g)$/i.test(file.name||''))return null;
        try{
            const bytes=await file.slice(0,Math.min(file.size,4*1024*1024)).arrayBuffer();
            const view=new DataView(bytes);
            const textAt=(offset,length)=>{let value='';for(let n=0;n<length;n++)value+=String.fromCharCode(view.getUint8(offset+n));return value;};
            if(view.byteLength<4||view.getUint16(0,false)!==0xffd8)return null;
            let markerOffset=2;
            while(markerOffset+4<=view.byteLength){
                if(view.getUint8(markerOffset)!==0xff)break;
                const marker=view.getUint8(markerOffset+1);
                if(marker===0xda||marker===0xd9)break;
                if(marker===0x01||(marker>=0xd0&&marker<=0xd8)){markerOffset+=2;continue;}
                const length=view.getUint16(markerOffset+2,false),dataOffset=markerOffset+4,end=markerOffset+2+length;
                if(length<2||end>view.byteLength)break;
                if(marker===0xe1&&length>=14&&textAt(dataOffset,4)==='Exif'&&view.getUint16(dataOffset+4,false)===0){
                    const tiff=dataOffset+6,order=textAt(tiff,2),little=order==='II';
                    if(!little&&order!=='MM')return null;
                    const u16=offset=>view.getUint16(tiff+offset,little),u32=offset=>view.getUint32(tiff+offset,little);
                    const tiffLength=end-tiff;
                    if(u16(2)!==42)return null;
                    const ifd0=u32(4);
                    if(ifd0+2>tiffLength)return null;
                    const readEntry=(ifd,tag)=>{
                        const count=u16(ifd);
                        if(count>4096||ifd+2+count*12>tiffLength)return null;
                        for(let n=0;n<count;n++){
                            const entry=ifd+2+n*12;
                            if(u16(entry)!==tag)continue;
                            const type=u16(entry+2),items=u32(entry+4),unit=type===2?1:type===4?4:type===5?8:0;
                            if(!unit)return null;
                            const size=items*unit,ptr=size<=4?entry+8:u32(entry+8);
                            if(ptr<0||ptr+size>tiffLength)return null;
                            return {type,items,ptr,value:type===4?u32(entry+8):null};
                        }
                        return null;
                    };
                    const gpsPointer=readEntry(ifd0,0x8825);
                    if(!gpsPointer||gpsPointer.type!==4)return null;
                    const gpsIfd=gpsPointer.value;
                    const readAscii=tag=>{
                        const item=readEntry(gpsIfd,tag);
                        return item&&item.type===2?textAt(tiff+item.ptr,item.items).split(String.fromCharCode(0))[0].trim():'';
                    };
                    const readDms=tag=>{
                        const item=readEntry(gpsIfd,tag);
                        if(!item||item.type!==5||item.items<3)return null;
                        const values=[];
                        for(let n=0;n<3;n++){
                            const numerator=u32(item.ptr+n*8),denominator=u32(item.ptr+n*8+4);
                            if(!denominator)return null;
                            values.push(numerator/denominator);
                        }
                        return values[0]+values[1]/60+values[2]/3600;
                    };
                    let latitude=readDms(2),longitude=readDms(4);
                    if(latitude===null||longitude===null)return null;
                    if(readAscii(1).toUpperCase()==='S')latitude*=-1;
                    if(readAscii(3).toUpperCase()==='W')longitude*=-1;
                    if(Math.abs(latitude)>90||Math.abs(longitude)>180)return null;
                    return {latitude:Number(latitude.toFixed(6)),longitude:Number(longitude.toFixed(6))};
                }
                markerOffset=end;
            }
        }catch{/* Las imágenes sin EXIF GPS siguen siendo válidas. */}
        return null;
    };
    const prepareUploadFile=async file=>{
        if(file.size<=maxBytes)return file;
        setMessage('La foto supera 15 MB. Preparando una copia optimizada en este dispositivo…');
        let bitmap=null,objectUrl='';
        try{
            if('createImageBitmap' in window)bitmap=await createImageBitmap(file);
            else{
                objectUrl=URL.createObjectURL(file);
                bitmap=new Image();bitmap.src=objectUrl;await bitmap.decode();
            }
            const longest=Math.max(bitmap.width,bitmap.height),scale=Math.min(1,4096/longest);
            let width=Math.max(1,Math.round(bitmap.width*scale)),height=Math.max(1,Math.round(bitmap.height*scale));
            const canvas=document.createElement('canvas');
            for(let attempt=0;attempt<12;attempt++){
                canvas.width=width;canvas.height=height;
                const context=canvas.getContext('2d',{alpha:false});
                context.fillStyle='#fff';context.fillRect(0,0,width,height);context.drawImage(bitmap,0,0,width,height);
                const quality=Math.max(.62,.92-attempt*.035);
                const blob=await new Promise(resolve=>canvas.toBlob(resolve,'image/jpeg',quality));
                if(blob&&blob.size<=maxBytes-128*1024){
                    const name=file.name.replace(/\.[^.]+$/,'')+'.jpg';
                    canvas.width=canvas.height=1;
                    return new File([blob],name,{type:'image/jpeg',lastModified:file.lastModified});
                }
                width=Math.max(1,Math.round(width*.88));height=Math.max(1,Math.round(height*.88));
            }
            throw new Error('No se pudo optimizar la foto por debajo del límite de subida.');
        }finally{
            if(bitmap&&typeof bitmap.close==='function')bitmap.close();
            if(objectUrl)URL.revokeObjectURL(objectUrl);
        }
    };
    const postUploadForm=async body=>{
        let response;
        try{response=await fetch('/admin.php',{method:'POST',body,credentials:'same-origin'});}
        catch{throw new Error('No se pudo conectar con el servidor. Comprueba tu conexión.');}
        let data=null;try{data=await response.json();}catch{}
        if(response.status===413)throw new Error('El servidor rechaza esta foto por tamaño. Revisa el límite de Nginx y PHP.');
        if(!response.ok||!data?.ok)throw new Error(data?.error||'El servidor no pudo procesar la fotografía.');
        return data;
    };

    const updateGpsSummary=()=>{
        const latitude=$('photoLatitude').value.trim(),longitude=$('photoLongitude').value.trim();
        $('photoGpsSummary').textContent=latitude&&longitude?'Ubicación · '+latitude+', '+longitude
            :latitude||longitude?'Completa ambas coordenadas':'Sin ubicación GPS';
    };
    $('photoLatitude').addEventListener('input',updateGpsSummary);
    $('photoLongitude').addEventListener('input',updateGpsSummary);
    const cancelButtons=[$('editorCancel'),$('detailsCancel')];
    const cancelEditing=async()=>{
        if(saving||submitting)return;
        if(image&&!window.confirm('¿Cancelar la edición y volver al inicio? Los cambios sin guardar se perderán.'))return;
        cancelButtons.forEach(button=>button.disabled=true);
        const payload=new FormData();
        payload.append('action','cancel_edit');
        payload.append('csrf',csrf);
        try{await postUploadForm(payload);}catch{/* La subida temporal caducará; la salida no debe bloquearse. */}
        window.location.assign('/');
    };
    cancelButtons.forEach(button=>button.addEventListener('click',cancelEditing));

    const glyphs={
        crop:'<path d="M5 2v15a3 3 0 0 0 3 3h14M2 5h15a3 3 0 0 1 3 3v14"/>',
        rotate:'<path d="M21 8a9 9 0 1 0 1 8M21 3v5h-5"/>',
        exposure:'<circle cx="12" cy="12" r="4"/><path d="M12 2v2m0 16v2M2 12h2m16 0h2M5 5l1.5 1.5m11 11L19 19M19 5l-1.5 1.5m-11 11L5 19"/>',
        highlights:'<circle cx="12" cy="12" r="8"/><path d="M12 4v16m-7-8h14"/>',
        shadows:'<path d="M20 15.5A8 8 0 0 1 8.5 4 8 8 0 1 0 20 15.5Z"/>',
        contrast:'<circle cx="12" cy="12" r="9"/><path d="M12 3v18"/>',
        temperature:'<path d="M10 14V5a2 2 0 0 1 4 0v9a4 4 0 1 1-4 0Z"/><path d="M12 9v8"/>',
        tint:'<path d="M12 2c4 5 7 8 7 12a7 7 0 0 1-14 0c0-4 3-7 7-12Z"/><path d="M8 17c1 2 3 3 5 3"/>',
        saturation:'<circle cx="12" cy="12" r="9"/><path d="M12 3a9 9 0 0 1 0 18Z"/>',
        vibrance:'<path d="m12 2 2.5 7.5L22 12l-7.5 2.5L12 22l-2.5-7.5L2 12l7.5-2.5Z"/>',
        sharpness:'<path d="m12 2 2 8 8 2-8 2-2 8-2-8-8-2 8-2Z"/>',
        structure:'<path d="M3 18 9 6l4 8 3-6 5 10H3Z"/>',
        perspectiveVertical:'<path d="M4 3h16l-4 18H8L4 3Z"/><path d="M12 3v18"/>',
        perspectiveHorizontal:'<path d="M3 4v16l18-4V8L3 4Z"/><path d="M3 12h18"/>',
        fade:'<path d="M4 17h16M4 12h12M4 7h8"/>',
        vignette:'<rect x="3" y="3" width="18" height="18" rx="3"/><circle cx="12" cy="12" r="5"/>',
        filters:'<path d="M4 5h16M4 12h16M4 19h16"/><circle cx="8" cy="5" r="2" fill="currentColor"/><circle cx="15" cy="12" r="2" fill="currentColor"/><circle cx="11" cy="19" r="2" fill="currentColor"/>'
    };
    const toolDefs=[
        ['crop','Recorte'],['rotate','Girar'],['exposure','Luz',-100,100],['highlights','Altas luces',-100,100],
        ['shadows','Sombras',-100,100],['contrast','Contraste',-100,100],['temperature','Temperatura',-100,100],
        ['tint','Matiz',-100,100],['saturation','Saturación',-100,100],['vibrance','Intensidad',-100,100],
        ['sharpness','Nitidez',0,100],['structure','Estructura',0,100],['perspectiveVertical','Verticales',-100,100],
        ['perspectiveHorizontal','Horizontales',-100,100],['fade','Desvanecer',0,100],['vignette','Viñeta',-100,100],
        ['filters','Filtros']
    ];
    const toolMap=new Map(toolDefs.map(item=>[item[0],item]));
    const icon=key=>'<svg viewBox="0 0 24 24" width="23" height="23" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'+glyphs[key]+'</svg>';
    function updateSliderTrack(input){
        const min=Number(input.min),max=Number(input.max),value=Number(input.value);
        const percent=clamp((value-min)/(max-min)*100,0,100);
        const zero=clamp((0-min)/(max-min)*100,0,100);
        input.style.setProperty('--fill-start',Math.min(percent,zero)+'%');
        input.style.setProperty('--fill-end',Math.max(percent,zero)+'%');
    }
    function updateToolUI(){
        toolRail.querySelectorAll('[data-tool]').forEach(button=>{
            const current=button.dataset.tool===activeTool;
            button.classList.toggle('is-active',current);
            button.setAttribute('aria-pressed',current?'true':'false');
        });
        filterPanel.hidden=activeTool!=='filters';
        cropPanel.hidden=!cropEditing;
        toolPanel.hidden=activeTool==='filters'||cropEditing;
        if(activeTool==='filters'){
            filterStrength.value=String(filterMix);
            filterStrengthValue.textContent=filterMix+'%';
            updateSliderTrack(filterStrength);
            renderCatalog();
        }else if(!cropEditing){
            const tool=toolMap.get(activeTool);
            if(tool&&tool.length>2){
                toolName.textContent=tool[1];
                toolSlider.min=String(tool[2]);toolSlider.max=String(tool[3]);
                toolSlider.value=String(adjustments[activeTool]);
                toolValue.textContent=(adjustments[activeTool]>0?'+':'')+adjustments[activeTool]+'%';
                updateSliderTrack(toolSlider);
            }
        }
        $('editorContinue').disabled=cropEditing;
    }
    function cancelCrop(){
        cropEditing=false;draftCrop=null;draftRatio=null;
        cropSelection.hidden=true;
        activeTool='exposure';
        updateToolUI();scheduleRender();
    }
    function startCrop(){
        cropEditing=true;draftCrop=appliedCrop?{...appliedCrop}:{x:.03,y:.03,w:.94,h:.94};draftRatio=null;
        zoom=1;panX=panY=0;
        activeTool='crop';
        cropRatios.querySelectorAll('[data-ratio]').forEach(b=>b.classList.toggle('is-active',b.dataset.ratio==='free'));
        updateToolUI();scheduleRender();
    }
    function selectTool(key){
        if(!image)return;
        if(cropEditing&&key!=='crop')cancelCrop();
        if(key==='rotate'){
            rotation=(rotation+90)%360;
            if(appliedCrop){const c=appliedCrop;appliedCrop={x:1-c.y-c.h,y:c.x,w:c.h,h:c.w};}
            zoom=1;panX=panY=0;thumbnailCache.clear();scheduleRender();
            return;
        }
        if(key==='crop'){if(!cropEditing)startCrop();return;}
        activeTool=key;
        updateToolUI();
        if(key==='filters')filterList.scrollLeft=0;
    }
    toolDefs.forEach(([key,label])=>{
        const button=document.createElement('button');
        button.type='button';button.className='photo-editor__tool';button.dataset.tool=key;
        button.setAttribute('aria-label',label);button.innerHTML=icon(key)+'<span>'+label+'</span>';
        button.addEventListener('click',()=>{selectTool(key);button.scrollIntoView({block:'nearest',inline:'center',behavior:'smooth'});});
        toolRail.append(button);
    });
    toolSlider.addEventListener('input',()=>{
        adjustments[activeTool]=Number(toolSlider.value);
        toolValue.textContent=(adjustments[activeTool]>0?'+':'')+adjustments[activeTool]+'%';
        updateSliderTrack(toolSlider);
        scheduleRender();
    });
    $('toolReset').addEventListener('click',()=>{
        adjustments[activeTool]=0;updateToolUI();scheduleRender();
    });
    filterStrength.addEventListener('input',()=>{
        filterMix=Number(filterStrength.value);
        filterStrengthValue.textContent=filterMix+'%';
        updateSliderTrack(filterStrength);
        scheduleRender();
    });
    filterCategory.replaceChildren(new Option('Todos los estilos · '+presets.length,'all'),...families.map(f=>new Option(f.title+' · '+presetCountByFamily.get(f.id),f.id)));
    filterCategory.addEventListener('change',()=>{filterList.scrollLeft=0;renderCatalog();});
    const filterPrev=$('filterPrev'),filterNext=$('filterNext');
    const updateFilterNavigation=()=>{
        const max=filterList.scrollWidth-filterList.clientWidth;
        filterPrev.disabled=filterList.scrollLeft<=2;
        filterNext.disabled=filterList.scrollLeft>=max-2;
    };
    filterPrev.addEventListener('click',()=>filterList.scrollBy({left:-filterList.clientWidth*.8,behavior:'smooth'}));
    filterNext.addEventListener('click',()=>filterList.scrollBy({left:filterList.clientWidth*.8,behavior:'smooth'}));
    filterList.addEventListener('scroll',updateFilterNavigation,{passive:true});
    filterList.addEventListener('wheel',event=>{
        if(window.matchMedia('(max-width:760px)').matches||filterList.scrollWidth<=filterList.clientWidth)return;
        if(Math.abs(event.deltaY)>Math.abs(event.deltaX)){
            filterList.scrollLeft+=event.deltaY;
            event.preventDefault();
        }
    },{passive:false});
    window.addEventListener('resize',updateFilterNavigation);
    let filterDrag=null;
    let suppressFilterClickUntil=0;
    filterList.addEventListener('pointerdown',event=>{
        if(event.pointerType!=='mouse'||event.button!==0||!event.target.closest('.photo-editor__preset'))return;
        filterDrag={pointerId:event.pointerId,startX:event.clientX,startScroll:filterList.scrollLeft,dragging:false};
    });
    window.addEventListener('pointermove',event=>{
        if(!filterDrag||event.pointerId!==filterDrag.pointerId)return;
        const distance=event.clientX-filterDrag.startX;
        if(!filterDrag.dragging&&Math.abs(distance)>5){
            filterDrag.dragging=true;
            filterList.setPointerCapture(event.pointerId);
            filterList.classList.add('is-dragging');
        }
        if(filterDrag.dragging){
            filterList.scrollLeft=filterDrag.startScroll-distance;
            event.preventDefault();
        }
    });
    const endFilterDrag=event=>{
        if(!filterDrag||event.pointerId!==filterDrag.pointerId)return;
        if(filterDrag.dragging)suppressFilterClickUntil=Date.now()+250;
        filterDrag=null;
        filterList.classList.remove('is-dragging');
    };
    window.addEventListener('pointerup',endFilterDrag);
    window.addEventListener('pointercancel',endFilterDrag);
    filterList.addEventListener('click',event=>{
        if(Date.now()<suppressFilterClickUntil){event.preventDefault();event.stopImmediatePropagation();}
    },true);
    function enableComfortableSlider(input){
        let activePointer=null,startX=0,startY=0,moved=false,lastTapAt=0,lastTapX=0,lastTapY=0;
        input.title='Doble clic o doble toque para volver a 0';
        input.setAttribute('aria-description','Doble clic o doble toque para restablecer a cero');
        const resetToZero=()=>{
            input.value='0';
            input.dispatchEvent(new Event('input',{bubbles:true}));
        };
        const fromPointer=event=>{
            const rect=input.getBoundingClientRect(),thumb=30;
            const width=Math.max(1,rect.width-thumb);
            const fraction=clamp((event.clientX-rect.left-thumb/2)/width,0,1);
            const min=Number(input.min),max=Number(input.max),step=Number(input.step)||1;
            const value=clamp(min+Math.round((fraction*(max-min))/step)*step,min,max);
            if(Number(input.value)!==value){
                input.value=String(value);
                input.dispatchEvent(new Event('input',{bubbles:true}));
            }
        };
        input.addEventListener('pointerdown',event=>{
            if(event.pointerType==='mouse'&&event.button!==0)return;
            activePointer=event.pointerId;
            startX=event.clientX;startY=event.clientY;moved=false;
            input.setPointerCapture(event.pointerId);
            fromPointer(event);
            event.preventDefault();
        });
        input.addEventListener('pointermove',event=>{
            if(activePointer!==event.pointerId)return;
            if(Math.hypot(event.clientX-startX,event.clientY-startY)>8)moved=true;
            fromPointer(event);
        });
        input.addEventListener('pointerup',event=>{
            if(activePointer!==event.pointerId)return;
            activePointer=null;
            if(moved){lastTapAt=0;return;}
            const now=performance.now();
            if(lastTapAt!==0&&now-lastTapAt<400&&Math.hypot(event.clientX-lastTapX,event.clientY-lastTapY)<32){
                lastTapAt=0;
                resetToZero();
            }else{
                lastTapAt=now;lastTapX=event.clientX;lastTapY=event.clientY;
            }
        });
        input.addEventListener('pointercancel',event=>{
            if(activePointer===event.pointerId){activePointer=null;lastTapAt=0;}
        });
        input.addEventListener('dblclick',event=>{event.preventDefault();lastTapAt=0;resetToZero();});
    }
    [toolSlider,filterStrength].forEach(enableComfortableSlider);
    
    function renderCatalog(){
        if(activeTool!=='filters'||!image)return;
        if(thumbnailObserver)thumbnailObserver.disconnect();
        const list=filterCategory.value==='all'?presets:presets.filter(p=>p.category===filterCategory.value);
        filterList.replaceChildren();
        const entries=[null,...list];
        for(const preset of entries){
            const button=document.createElement('button');button.type='button';
            button.className='photo-editor__preset'+((!preset&&!activePreset)||(preset&&activePreset?.id===preset.id)?' is-active':'');
            button.dataset.preset=preset?.id||'original';
            button.setAttribute('aria-label',preset?('Filtro '+preset.name+'. '+preset.description):'Sin filtro');
            button.title=preset?(preset.name+' — '+preset.description):'Original, sin filtro';
            const thumb=document.createElement('img');thumb.className='photo-editor__thumb';thumb.alt='';thumb.loading='lazy';
            const label=document.createElement('span');label.textContent=preset?.name||'Original';label.title=preset?.name||'Original';
            button.append(thumb,label);
            button.addEventListener('click',()=>{
                activePreset=preset;filterMix=100;filterStrength.value='100';filterStrengthValue.textContent='100%';updateSliderTrack(filterStrength);
                filterList.querySelectorAll('.photo-editor__preset').forEach(b=>b.classList.toggle('is-active',b===button));
                scheduleRender();
            });
            filterList.append(button);
        }
        const showThumb=button=>{
            const id=button.dataset.preset,thumb=button.querySelector('img');
            if(!thumbnailCache.has(id)){
                const preset=id==='original'?null:byId.get(id);
                const frame=renderFrame(120,preset,100,true);
                thumbnailCache.set(id,frame.toDataURL('image/jpeg',.86));
                frame.width=frame.height=1;
            }
            thumb.src=thumbnailCache.get(id);
        };
        if('IntersectionObserver' in window){
            thumbnailObserver=new IntersectionObserver(entries=>{
                entries.forEach(entry=>{if(entry.isIntersecting){showThumb(entry.target);thumbnailObserver.unobserve(entry.target);}});
            },{root:filterList,rootMargin:'160px'});
            filterList.querySelectorAll('.photo-editor__preset').forEach(button=>thumbnailObserver.observe(button));
        }else filterList.querySelectorAll('.photo-editor__preset').forEach(showThumb);
        requestAnimationFrame(updateFilterNavigation);
    }
    const aspectOfSource=()=>{
        const w=rotation%180===0?image.naturalWidth:image.naturalHeight;
        const h=rotation%180===0?image.naturalHeight:image.naturalWidth;
        return w/h;
    };
    function setDraftRatio(value){
        if(!image||!cropEditing)return;
        if(value==='free'){draftRatio=null;updateCropOverlay();}
        else if(value==='original'){draftRatio=aspectOfSource();draftCrop={x:0,y:0,w:1,h:1};updateCropOverlay();}
        else{
            draftRatio=Number(value);
            const aspect=aspectOfSource(),margin=.92;
            const w=aspect>=draftRatio?margin*draftRatio/aspect:margin;
            const h=aspect>=draftRatio?margin:margin*aspect/draftRatio;
            draftCrop={x:(1-w)/2,y:(1-h)/2,w,h};
            updateCropOverlay();
        }
        cropRatios.querySelectorAll('[data-ratio]').forEach(b=>b.classList.toggle('is-active',b.dataset.ratio===value));
    }
    cropRatios.querySelectorAll('[data-ratio]').forEach(button=>button.addEventListener('click',()=>setDraftRatio(button.dataset.ratio)));
    cropReset.addEventListener('click',()=>setDraftRatio('original'));
    cropApply.addEventListener('click',()=>{
        if(!cropEditing||!draftCrop)return;
        appliedCrop={...draftCrop};
        if(appliedCrop.x<.001&&appliedCrop.y<.001&&appliedCrop.w>.998&&appliedCrop.h>.998)appliedCrop=null;
        cropEditing=false;draftCrop=null;draftRatio=null;activeTool='exposure';
        cropSelection.hidden=true;zoom=1;panX=panY=0;thumbnailCache.clear();
        updateToolUI();scheduleRender();
    });
    cropCancel.addEventListener('click',cancelCrop);
    function updateCropOverlay(){
        cropSelection.hidden=!cropEditing||!draftCrop;
        if(cropSelection.hidden)return;
        const box=displayBox,c=draftCrop,cx=stage.clientWidth/2,cy=stage.clientHeight/2;
        const left=box.left+c.x*box.width,top=box.top+c.y*box.height;
        cropSelection.style.left=(cx+panX+(left-cx)*zoom)+'px';
        cropSelection.style.top=(cy+panY+(top-cy)*zoom)+'px';
        cropSelection.style.width=(c.w*box.width*zoom)+'px';
        cropSelection.style.height=(c.h*box.height*zoom)+'px';
    }
    function photoPoint(event){
        const bounds=stage.getBoundingClientRect(),box=displayBox;
        const sx=event.clientX-bounds.left-stage.clientLeft,sy=event.clientY-bounds.top-stage.clientTop;
        const px=(sx-stage.clientWidth/2-panX)/zoom+stage.clientWidth/2;
        const py=(sy-stage.clientHeight/2-panY)/zoom+stage.clientHeight/2;
        const x=(px-box.left)/box.width,y=(py-box.top)/box.height;
        return {x:clamp(x,0,1),y:clamp(y,0,1),inside:x>=0&&x<=1&&y>=0&&y<=1};
    }
    function clampPan(){
        const maxX=Math.max(0,(displayBox.width*zoom-stage.clientWidth)/2);
        const maxY=Math.max(0,(displayBox.height*zoom-stage.clientHeight)/2);
        panX=clamp(panX,-maxX,maxX);panY=clamp(panY,-maxY,maxY);
        zoomReset.hidden=zoom<=1.001;zoomReset.textContent='Ajustar · '+zoom.toFixed(1)+'×';
    }
    function updateViewTransform(){
        clampPan();
        canvas.style.transform='translate('+panX+'px,'+panY+'px) scale('+zoom+')';
        updateCropOverlay();
    }
    function changeZoom(next,anchorX,anchorY,previous=zoom,oldPanX=panX,oldPanY=panY){
        const target=clamp(next,1,4);
        panX=anchorX-(anchorX-oldPanX)*target/previous;
        panY=anchorY-(anchorY-oldPanY)*target/previous;
        zoom=target;
        if(zoom<=1.001){zoom=1;panX=panY=0;}
        updateViewTransform();
    }
    function resetZoom(){zoom=1;panX=panY=0;zoomGesture=null;pointers.clear();updateViewTransform();}
    zoomReset.addEventListener('click',resetZoom);

    let cropDrag=null;
    stage.addEventListener('pointerdown',event=>{
        if(event.target.closest('#editorZoomReset'))return;
        if(event.pointerType==='touch'){
            pointers.set(event.pointerId,{x:event.clientX,y:event.clientY});
            if(pointers.size>=2){
                cropDrag=null;
                const ids=Array.from(pointers.keys()).slice(0,2),[a,b]=ids.map(id=>pointers.get(id));
                const r=stage.getBoundingClientRect(),cx=r.left+stage.clientLeft+stage.clientWidth/2,cy=r.top+stage.clientTop+stage.clientHeight/2;
                zoomGesture={kind:'pinch',ids,distance:Math.max(1,Math.hypot(a.x-b.x,a.y-b.y)),zoom,panX,panY,
                    anchorX:(a.x+b.x)/2-cx,anchorY:(a.y+b.y)/2-cy};
                ids.forEach(id=>{if(!stage.hasPointerCapture(id))stage.setPointerCapture(id);});
                event.preventDefault();return;
            }
        }
        const onCrop=cropEditing&&cropSelection.contains(event.target);
        if(zoom>1.001&&!onCrop&&(event.pointerType==='touch'||event.button===0)){
            zoomGesture={kind:'pan',id:event.pointerId,x:event.clientX,y:event.clientY,panX,panY};
            stage.setPointerCapture(event.pointerId);event.preventDefault();return;
        }
        if(!cropEditing||event.button>0)return;
        const point=photoPoint(event),handle=event.target.closest('[data-crop-handle]')?.dataset.cropHandle;
        if(!point.inside&&!handle)return;
        const inside=cropSelection.contains(event.target);
        cropDrag={id:event.pointerId,kind:handle?'resize':inside?'move':'draw',handle:handle||'',start:point,crop:{...draftCrop}};
        if(cropDrag.kind==='draw')draftCrop={x:point.x,y:point.y,w:.035,h:.035};
        stage.setPointerCapture(event.pointerId);updateCropOverlay();event.preventDefault();
    });
    stage.addEventListener('pointermove',event=>{
        if(pointers.has(event.pointerId))pointers.set(event.pointerId,{x:event.clientX,y:event.clientY});
        if(zoomGesture?.kind==='pinch'&&zoomGesture.ids.includes(event.pointerId)){
            const [a,b]=zoomGesture.ids.map(id=>pointers.get(id));if(!a||!b)return;
            const r=stage.getBoundingClientRect(),cx=r.left+stage.clientLeft+stage.clientWidth/2,cy=r.top+stage.clientTop+stage.clientHeight/2;
            const x=(a.x+b.x)/2-cx,y=(a.y+b.y)/2-cy;
            changeZoom(zoomGesture.zoom*Math.hypot(a.x-b.x,a.y-b.y)/zoomGesture.distance,x,y,zoomGesture.zoom,zoomGesture.panX,zoomGesture.panY);
            event.preventDefault();return;
        }
        if(zoomGesture?.kind==='pan'&&zoomGesture.id===event.pointerId){
            panX=zoomGesture.panX+event.clientX-zoomGesture.x;
            panY=zoomGesture.panY+event.clientY-zoomGesture.y;
            updateViewTransform();event.preventDefault();return;
        }
        if(!cropDrag||cropDrag.id!==event.pointerId)return;
        const point=photoPoint(event),start=cropDrag,old=start.crop,min=.035;
        if(start.kind==='move'){
            draftCrop={...old,x:clamp(old.x+point.x-start.start.x,0,1-old.w),y:clamp(old.y+point.y-start.start.y,0,1-old.h)};
        }else if(start.kind==='draw'||start.kind==='resize'){
            const west=start.kind==='draw'?point.x<start.start.x:start.handle.includes('w');
            const north=start.kind==='draw'?point.y<start.start.y:start.handle.includes('n');
            const anchorX=start.kind==='draw'?start.start.x:(west?old.x+old.w:old.x);
            const anchorY=start.kind==='draw'?start.start.y:(north?old.y+old.h:old.y);
            let width=Math.abs(point.x-anchorX),height=Math.abs(point.y-anchorY);
            if(draftRatio!==null){
                const normalized=draftRatio/aspectOfSource();
                const maxW=Math.min(west?anchorX:1-anchorX,(north?anchorY:1-anchorY)*normalized);
                width=clamp(Math.max(width,height*normalized),Math.min(min,maxW),maxW);
                height=width/normalized;
            }else{
                width=clamp(width,min,west?anchorX:1-anchorX);
                height=clamp(height,min,north?anchorY:1-anchorY);
            }
            draftCrop={x:west?anchorX-width:anchorX,y:north?anchorY-height:anchorY,w:width,h:height};
        }
        updateCropOverlay();event.preventDefault();
    });
    const endPointer=event=>{
        pointers.delete(event.pointerId);
        if(cropDrag?.id===event.pointerId)cropDrag=null;
        if(zoomGesture?.kind==='pan'&&zoomGesture.id===event.pointerId)zoomGesture=null;
        else if(zoomGesture?.kind==='pinch'&&zoomGesture.ids.includes(event.pointerId)){
            zoomGesture=null;
            if(pointers.size===1&&zoom>1.001){
                const [id,p]=pointers.entries().next().value;
                zoomGesture={kind:'pan',id,x:p.x,y:p.y,panX,panY};
            }
        }
        if(stage.hasPointerCapture(event.pointerId))stage.releasePointerCapture(event.pointerId);
    };
    stage.addEventListener('pointerup',endPointer);stage.addEventListener('pointercancel',endPointer);
    stage.addEventListener('wheel',event=>{
        if(!image)return;event.preventDefault();
        const r=stage.getBoundingClientRect(),x=event.clientX-r.left-stage.clientLeft-stage.clientWidth/2,y=event.clientY-r.top-stage.clientTop-stage.clientHeight/2;
        changeZoom(zoom*Math.exp(-event.deltaY*.0015),x,y);
    },{passive:false});
    cropSelection.addEventListener('keydown',event=>{
        if(!cropEditing||!draftCrop)return;
        const step=event.shiftKey?.04:.01;
        if(event.key==='ArrowLeft')draftCrop.x=clamp(draftCrop.x-step,0,1-draftCrop.w);
        else if(event.key==='ArrowRight')draftCrop.x=clamp(draftCrop.x+step,0,1-draftCrop.w);
        else if(event.key==='ArrowUp')draftCrop.y=clamp(draftCrop.y-step,0,1-draftCrop.h);
        else if(event.key==='ArrowDown')draftCrop.y=clamp(draftCrop.y+step,0,1-draftCrop.h);
        else return;
        event.preventDefault();updateCropOverlay();
    });

const solvePerspectiveAffine = (src,dst) => {
        const [[x0,y0],[x1,y1],[x2,y2]]=src,det=x0*(y1-y2)-y0*(x1-x2)+x1*y2-x2*y1;
        if(Math.abs(det)<1e-8)return null;
        const solve=v=>[(v[0]*(y1-y2)-y0*(v[1]-v[2])+(v[1]*y2-v[2]*y1))/det,(x0*(v[1]-v[2])-v[0]*(x1-x2)+x1*v[2]-x2*v[1])/det,(x0*(y1*v[2]-y2*v[1])-y0*(x1*v[2]-x2*v[1])+v[0]*(x1*y2-x2*y1))/det];
        const [a,c,e]=solve(dst.map(p=>p[0])),[b,d,f]=solve(dst.map(p=>p[1]));return [a,b,c,d,e,f];
    };
    const unitSquareToPerspectiveQuad = q => {
        const [[x0,y0],[x1,y1],[x2,y2],[x3,y3]]=q,dx1=x1-x2,dx2=x3-x2,dx3=x0-x1+x2-x3,dy1=y1-y2,dy2=y3-y2,dy3=y0-y1+y2-y3;
        let g=0,h=0;const den=dx1*dy2-dx2*dy1;
        if(Math.abs(dx3)>1e-9||Math.abs(dy3)>1e-9){if(Math.abs(den)>1e-9){g=(dx3*dy2-dx2*dy3)/den;h=(dx1*dy3-dx3*dy1)/den;}}
        const a=x1-x0+g*x1,b=x3-x0+h*x3,c=x0,d=y1-y0+g*y1,e=y3-y0+h*y3,f=y0;
        return (u,v)=>{const w=g*u+h*v+1;return [(a*u+b*v+c)/w,(d*u+e*v+f)/w];};
    };
    const warpPerspective = (source,q,outW,outH,mesh=10) => {
        const canvas=document.createElement('canvas');canvas.width=outW;canvas.height=outH;
        const ctx=canvas.getContext('2d',{alpha:false});ctx.fillStyle='#f4f1eb';ctx.fillRect(0,0,outW,outH);
        const map=unitSquareToPerspectiveQuad(q),src=[],dst=[];
        for(let j=0;j<=mesh;j++)for(let i=0;i<=mesh;i++){const u=i/mesh,v=j/mesh;src.push([u*source.width,v*source.height]);dst.push(map(u,v));}
        const at=(i,j)=>j*(mesh+1)+i;
        for(let j=0;j<mesh;j++)for(let i=0;i<mesh;i++){
            const s00=src[at(i,j)],s10=src[at(i+1,j)],s01=src[at(i,j+1)],s11=src[at(i+1,j+1)],d00=dst[at(i,j)],d10=dst[at(i+1,j)],d01=dst[at(i,j+1)],d11=dst[at(i+1,j+1)];
            for(const [s,d] of [[[s00,s10,s11],[d00,d10,d11]],[[s00,s11,s01],[d00,d11,d01]]]){
                const m=solvePerspectiveAffine(s,d);if(!m)continue;
                const cx=(d[0][0]+d[1][0]+d[2][0])/3,cy=(d[0][1]+d[1][1]+d[2][1])/3;
                const clip=d.map(([x,y])=>{const dx=x-cx,dy=y-cy,len=Math.hypot(dx,dy)||1;return [x+dx/len*.55,y+dy/len*.55];});
                ctx.save();ctx.beginPath();ctx.moveTo(clip[0][0],clip[0][1]);ctx.lineTo(clip[1][0],clip[1][1]);ctx.lineTo(clip[2][0],clip[2][1]);ctx.closePath();ctx.clip();ctx.transform(...m);ctx.drawImage(source,0,0);ctx.restore();
            }
        }return canvas;
    };
        const insideQuad=(p,q)=>{
        let sign=0;
        for(let i=0;i<4;i++){
            const a=q[i],b=q[(i+1)%4],cross=(b[0]-a[0])*(p[1]-a[1])-(b[1]-a[1])*(p[0]-a[0]);
            if(Math.abs(cross)<1e-7)continue;
            const current=cross>0?1:-1;if(sign&&sign!==current)return false;sign=current;
        }return true;
    };
    const perspectiveQuad=(w,h)=>{
        const v=adjustments.perspectiveVertical/100*.28,hor=adjustments.perspectiveHorizontal/100*.28;
        const q=[[-v*w/2,-hor*h/2],[w+v*w/2,hor*h/2],[w-v*w/2,h-hor*h/2],[v*w/2,h+hor*h/2]];
        const scale=k=>q.map(([x,y])=>[w/2+(x-w/2)*k,h/2+(y-h/2)*k]);
        const covers=k=>[[0,0],[w,0],[w,h],[0,h]].every(p=>insideQuad(p,scale(k)));
        if(covers(1))return q;
        let lo=1,hi=8;
        for(let i=0;i<24;i++){const mid=(lo+hi)/2;if(covers(mid))hi=mid;else lo=mid;}
        return scale(hi);
    };

    const clamp16=v=>Math.max(0,Math.min(65535,v));
    const lumaMap=(pixels,w,h)=>{
        const map=new Uint8Array(w*h);
        for(let i=0,p=0;i<map.length;i++,p+=4)map[i]=(pixels[p]*54+pixels[p+1]*183+pixels[p+2]*19+128)>>8;
        return map;
    };
    const detailAmount=(m,w,h,x,y,sharp,structure)=>{
        const xm=x>0?x-1:0,xp=x+1<w?x+1:w-1,ym=y>0?y-1:0,yp=y+1<h?y+1:h-1;
        const x3m=x>3?x-3:0,x3p=x+3<w?x+3:w-1,y3m=y>3?y-3:0,y3p=y+3<h?y+3:h-1;
        const center=m[y*w+x];let detail=0;
        if(sharp)detail+=(center-(m[y*w+xm]+m[y*w+xp]+m[ym*w+x]+m[yp*w+x])*.25)*sharp*.009;
        if(structure)detail+=(center-(m[y3m*w+x3m]+m[y3m*w+x]+m[y3m*w+x3p]+m[y*w+x3m]+m[y*w+x3p]+m[y3p*w+x3m]+m[y3p*w+x]+m[y3p*w+x3p])*.125)*structure*.0072;
        return detail*257;
    };
    const prepareGrade=p=>{
        if(!p)return null;
        return {...p,exposureScale:Math.pow(2,(p.exposure||0)*.012),
            contrastScale:1+(p.contrast||0)*.009,
            saturationScale:1+(p.saturation||0)*.01,
            vibranceScale:(p.vibrance||0)*.008,
            warmthShift:(p.warmth??p.temperature??0)*.00135*65535,
            tintShift:(p.tint||0)*.0009*65535,
            fadeMix:Math.max(0,(p.fade||0)*.0025),
            shadowShift:(p.shadows||0)*.0032*65535,
            highlightShift:(p.highlights||0)*.0032*65535};
    };
    function gradePixel16(r,g,b,p,out){
        if(!p){out[0]=r;out[1]=g;out[2]=b;return;}
        if(p.mono){
            const mix=p.monoMix||[.29,.59,.12],gray=r*mix[0]+g*mix[1]+b*mix[2];
            r=g=b=gray;
        }
        r*=p.exposureScale;g*=p.exposureScale;b*=p.exposureScale;
        const lum=clamp16(r*.2126+g*.7152+b*.0722)/65535;
        const shadow=(1-lum)*(1-lum),highlight=lum*lum;
        const lift=shadow*p.shadowShift+highlight*p.highlightShift;
        r+=lift;g+=lift;b+=lift;
        r+=p.warmthShift+p.tintShift*.45;
        g-=p.tintShift;
        b-=p.warmthShift+p.tintShift*.45;
        const gray=r*.2126+g*.7152+b*.0722;
        const chroma=(Math.max(r,g,b)-Math.min(r,g,b))/65535;
        const saturation=p.saturationScale+p.vibranceScale*(1-clamp(chroma,0,1));
        if(!p.mono){r=gray+(r-gray)*saturation;g=gray+(g-gray)*saturation;b=gray+(b-gray)*saturation;}
        r=(r-32768)*p.contrastScale+32768;
        g=(g-32768)*p.contrastScale+32768;
        b=(b-32768)*p.contrastScale+32768;
        if(p.fadeMix){
            r=r*(1-p.fadeMix)+65535*.16*p.fadeMix;
            g=g*(1-p.fadeMix)+65535*.16*p.fadeMix;
            b=b*(1-p.fadeMix)+65535*.16*p.fadeMix;
        }
        out[0]=r;out[1]=g;out[2]=b;
    }
    function toneFrame(frame,preset,mix,ignoreManual){
        const w=frame.width,h=frame.height,ctx=frame.getContext('2d',{alpha:false,willReadFrequently:true});
        const data=ctx.getImageData(0,0,w,h),pixels=data.data;
        const manual=ignoreManual?null:prepareGrade(adjustments);
        const sharp=ignoreManual?0:adjustments.sharpness,structure=ignoreManual?0:adjustments.structure;
        const detail=(sharp||structure)?lumaMap(pixels,w,h):null;
        const base=new Float64Array(3);
        for(let y=0;y<h;y++){
            for(let x=0;x<w;x++){
                const i=(y*w+x)*4;
                gradePixel16(pixels[i]*257,pixels[i+1]*257,pixels[i+2]*257,manual,base);
                let r=base[0],g=base[1],b=base[2];
                if(detail){
                    const add=detailAmount(detail,w,h,x,y,sharp,structure);
                    r+=add;g+=add;b+=add;
                }
                pixels[i]=Math.round(clamp16(r)/257);
                pixels[i+1]=Math.round(clamp16(g)/257);
                pixels[i+2]=Math.round(clamp16(b)/257);
                pixels[i+3]=255;
            }
        }
        if(preset)PhotoFilterEngine.apply(pixels,w,h,preset.grade,mix/100,Number(preset.id.slice(-3)));
        const vignette=ignoreManual?0:adjustments.vignette,strength=Math.abs(vignette)/100;
        if(strength)for(let y=0;y<h;y++)for(let x=0;x<w;x++){
            const nx=(x/w-.5)*2,ny=(y/h-.5)*2,edge=clamp((nx*nx+ny*ny-.1)/1.25,0,1);
            const amount=edge*edge*(3-2*edge)*strength,i=(y*w+x)*4;
            for(let c=0;c<3;c++)pixels[i+c]=vignette<0?pixels[i+c]+(255-pixels[i+c])*amount:pixels[i+c]*(1-amount);
        }
        ctx.putImageData(data,0,0);
    }
    function renderFrame(maxEdge,preset=activePreset,mix=filterMix,ignoreManual=false){
        const originalW=image.naturalWidth,originalH=image.naturalHeight,turned=rotation%180!==0;
        const orientedW=turned?originalH:originalW,orientedH=turned?originalW:originalH;
        const scale=Math.min(1,maxEdge/Math.max(orientedW,orientedH),Math.sqrt(8000000/(orientedW*orientedH)));
        const w=Math.max(1,Math.round(orientedW*scale)),h=Math.max(1,Math.round(orientedH*scale));
        const source=document.createElement('canvas');source.width=w;source.height=h;
        const sctx=source.getContext('2d',{alpha:false});
        if(!sctx)throw new Error('No hay memoria suficiente para preparar esta foto.');
        sctx.fillStyle='#161616';sctx.fillRect(0,0,w,h);
        sctx.translate(w/2,h/2);sctx.rotate(rotation*Math.PI/180);
        sctx.drawImage(image,-(turned?h:w)/2,-(turned?w:h)/2,turned?h:w,turned?w:h);
        let warped=source;
        if(!ignoreManual&&(adjustments.perspectiveVertical||adjustments.perspectiveHorizontal)){
            warped=warpPerspective(source,perspectiveQuad(w,h),w,h,maxEdge>1600?22:10);
        }
        const crop=!cropEditing?appliedCrop:null;
        const sx=crop?Math.floor(crop.x*w):0,sy=crop?Math.floor(crop.y*h):0;
        const sw=crop?Math.max(1,Math.min(w-sx,Math.round(crop.w*w))):w;
        const sh=crop?Math.max(1,Math.min(h-sy,Math.round(crop.h*h))):h;
        const output=document.createElement('canvas');output.width=sw;output.height=sh;
        const ctx=output.getContext('2d',{alpha:false,willReadFrequently:true});
        if(!ctx)throw new Error('No hay memoria suficiente para editar esta foto.');
        ctx.drawImage(warped,sx,sy,sw,sh,0,0,sw,sh);
        source.width=source.height=1;if(warped!==source)warped.width=warped.height=1;
        if(preset||(!ignoreManual&&Object.values(adjustments).some(v=>v!==0)))toneFrame(output,preset,mix,ignoreManual);
        return output;
    }

    function scheduleRender(){
        if(!image||editor.hidden||renderQueued)return;
        renderQueued=true;
        requestAnimationFrame(()=>{renderQueued=false;renderPreview();});
    }
    function renderPreview(){
        if(!image)return;
        try{
            const edge=Math.min(1200,Math.max(540,Math.round(Math.max(stage.clientWidth,stage.clientHeight)*Math.min(window.devicePixelRatio||1,2.5))));
            const result=renderFrame(edge);
            canvas.width=result.width;canvas.height=result.height;
            canvas.getContext('2d',{alpha:false}).drawImage(result,0,0);
            const scale=Math.min(stage.clientWidth/result.width,stage.clientHeight/result.height);
            const width=result.width*scale,height=result.height*scale;
            displayBox={left:(stage.clientWidth-width)/2,top:(stage.clientHeight-height)/2,width,height};
            canvas.style.left=displayBox.left+'px';canvas.style.top=displayBox.top+'px';
            canvas.style.width=width+'px';canvas.style.height=height+'px';
            result.width=result.height=1;
            updateViewTransform();
        }catch(error){
            const w=Math.max(1,Math.min(900,image.naturalWidth)),h=Math.max(1,Math.round(w*image.naturalHeight/image.naturalWidth));
            canvas.width=w;canvas.height=h;
            canvas.getContext('2d',{alpha:false}).drawImage(image,0,0,w,h);
            const scale=Math.min(stage.clientWidth/w,stage.clientHeight/h);
            displayBox={left:(stage.clientWidth-w*scale)/2,top:(stage.clientHeight-h*scale)/2,width:w*scale,height:h*scale};
            canvas.style.left=displayBox.left+'px';canvas.style.top=displayBox.top+'px';canvas.style.width=displayBox.width+'px';canvas.style.height=displayBox.height+'px';
            updateViewTransform();setMessage(error.message||'No se pudo mostrar un retoque; se mantiene visible la fotografía original.',true);
        }
    }
    function resetControls(){
        adjustments={...defaults};activePreset=null;filterMix=100;rotation=0;appliedCrop=null;draftCrop=null;draftRatio=null;cropEditing=false;
        activeTool='exposure';zoom=1;panX=panY=0;zoomGesture=null;pointers.clear();
        thumbnailCache.clear();if(thumbnailObserver)thumbnailObserver.disconnect();
        filterCategory.value='all';filterStrength.value='100';updateSliderTrack(filterStrength);
        updateToolUI();
    }
    $('editorContinue').addEventListener('click',()=>{
        if(!ready||cropEditing)return;
        reviewPreview.src=canvas.toDataURL('image/jpeg',.9);
        reviewPreview.style.filter='none';
        details.hidden=false;editor.hidden=true;
        $('gpsStatus').hidden=true;
        $('photoTitle').focus({preventScroll:true});
        details.scrollIntoView({behavior:'smooth',block:'start'});
    });
    $('editorBack').addEventListener('click',()=>{
        details.hidden=true;editor.hidden=false;scheduleRender();
        editor.scrollIntoView({behavior:'smooth',block:'start'});
    });
    function hasVisualChanges(){
        return rotation!==0||appliedCrop!==null||activePreset!==null
            ||Object.values(adjustments).some(value=>value!==0);
    }
    async function loadExisting(variant){
        ready=false;submitButton.disabled=true;details.hidden=true;editor.hidden=true;
        setMessage('Cargando la fotografía publicada…');
        try{
            if(sourceUrl.startsWith('blob:'))URL.revokeObjectURL(sourceUrl);
            sourceVariant=variant;
            sourceUrl='/admin.php?source='+encodeURIComponent(editFile)+'&variant='+variant;
            const loaded=new Image();loaded.src=sourceUrl;await loaded.decode();
            image=loaded;
            backdrop.style.backgroundImage='url("'+sourceUrl+'")';
            resetControls();editor.hidden=false;
            $('fileSourceField').hidden=true;$('gpsStatus').hidden=true;
            $('gpsFields').hidden=false;$('photoLatitude').required=false;$('photoLongitude').required=false;
            const lat=$('photoLatitude').value,lng=$('photoLongitude').value;
            $('photoGpsSummary').textContent=lat&&lng?'Ubicación · '+lat+', '+lng:'Ubicación sin coordenadas';
            $('gpsStatusReview').textContent='Puedes cambiar las coordenadas o dejar ambas vacías.';
            const changeLabel=variant==='published'?'Usar original':hasStoredEdit?'Ver versión publicada':'Restablecer original';
            $('editorChangePhoto').textContent=changeLabel;
            $('detailsChangePhoto').textContent=changeLabel;
            ready=true;submitButton.disabled=false;setMessage('');scheduleRender();
        }catch(error){
            setMessage('No se pudo abrir la fotografía. Recarga la página e inténtalo de nuevo.',true);
        }
    }
    const choosePhoto=()=>{
        if(isEditing){
            if(hasVisualChanges()&&!window.confirm('Se perderán los ajustes que aún no has guardado. ¿Continuar?'))return;
            loadExisting(sourceVariant==='published'?'original':hasStoredEdit?'published':'original');
            return;
        }
        fileInput.disabled=false;$('fileSourceField').hidden=false;fileInput.value='';fileInput.click();
    };
    $('editorChangePhoto').addEventListener('click',choosePhoto);
    $('detailsChangePhoto').addEventListener('click',()=>{details.hidden=true;editor.hidden=false;choosePhoto();});
    fileInput.addEventListener('change',()=>{
        fileInput.removeAttribute('capture');
        ready=false;submitButton.disabled=true;editor.hidden=true;details.hidden=true;
        $('fileSourceField').hidden=false;$('gpsStatus').hidden=false;
        if(sourceUrl.startsWith('blob:'))URL.revokeObjectURL(sourceUrl);sourceUrl='';image=null;reviewPreview.removeAttribute('src');
        selectedUploadFile=fileInput.files?.[0]||null;
        startEditingButton.disabled=!selectedUploadFile;
        if(selectedUploadFile){
            $('gpsStatus').textContent='Foto seleccionada. Pulsa «Continuar a la edición» para abrirla.';
            setMessage('');
        }else{
            $('gpsStatus').textContent='Elige una imagen para detectar si incluye coordenadas GPS.';
            setMessage('');
        }
    });
    $('cameraSourceButton').addEventListener('click',()=>{
        fileInput.setAttribute('capture','environment');
        fileInput.click();
    });
    fileInput.addEventListener('cancel',()=>fileInput.removeAttribute('capture'));
    startEditingButton.addEventListener('click',async()=>{
        const file=selectedUploadFile;
        if(!file||saving)return;
        saving=true;startEditingButton.disabled=true;fileInput.disabled=true;
        $('gpsStatus').textContent='Preparando la fotografía…';
        $('gpsFields').hidden=true;$('photoLatitude').required=false;$('photoLongitude').required=false;
        const body=new FormData();body.append('action','inspect');body.append('csrf',csrf);
        try{
            const originalGps=file.size>maxBytes?await readExifGps(file):null;
            const uploadFile=await prepareUploadFile(file);
            body.append('photo',uploadFile);
            const data=await postUploadForm(body);
            sourceUrl=URL.createObjectURL(uploadFile);
            image=new Image();image.src=sourceUrl;await image.decode();
            backdrop.style.backgroundImage='url("'+sourceUrl+'")';
            resetControls();editor.hidden=false;$('fileSourceField').hidden=true;$('gpsStatus').hidden=true;
            $('gpsFields').hidden=false;
            $('photoLatitude').required=false;$('photoLongitude').required=false;
            const detectedGps=data.gps||originalGps;
            $('photoLatitude').value=detectedGps?.latitude??'';
            $('photoLongitude').value=detectedGps?.longitude??'';
            if(detectedGps){
                $('gpsStatusReview').textContent='GPS detectado. Puedes editar las coordenadas o borrar ambos campos para no compartir la ubicación.';
                $('photoGpsSummary').textContent='Ubicación · '+detectedGps.latitude+', '+detectedGps.longitude;
            }else{
                $('gpsStatusReview').textContent='Sin GPS. La ubicación es opcional; completa ambas coordenadas solo si quieres mostrar la foto en el mapa.';
                $('photoGpsSummary').textContent='Sin ubicación GPS';
            }
            ready=true;submitButton.disabled=false;saving=false;scheduleRender();editor.scrollIntoView({behavior:'smooth',block:'start'});
        }catch(error){
            saving=false;startEditingButton.disabled=false;fileInput.disabled=false;
            $('gpsStatus').textContent='No se pudo preparar la fotografía.';
            setMessage(error.message||'No se pudo preparar la fotografía.',true);
        }
    });
    window.addEventListener('resize',()=>{if(image&&!editor.hidden)scheduleRender();});
    async function renderEditedPhoto(){
        const output=renderFrame(4096),qualities=[.95,.92,.88,.84];
        for(let attempt=0;attempt<8;attempt++){
            const quality=qualities[Math.min(attempt,qualities.length-1)];
            const blob=await new Promise(resolve=>output.toBlob(resolve,'image/jpeg',quality));
            if(!blob){output.width=output.height=1;throw new Error('El navegador no pudo exportar la fotografía editada.');}
            if(blob.size<=maxBytes){output.width=output.height=1;return blob;}
            const next=document.createElement('canvas');next.width=Math.max(1,Math.round(output.width*.88));next.height=Math.max(1,Math.round(output.height*.88));
            next.getContext('2d',{alpha:false}).drawImage(output,0,0,next.width,next.height);
            output.width=next.width;output.height=next.height;output.getContext('2d',{alpha:false}).drawImage(next,0,0);
            next.width=next.height=1;
        }
        output.width=output.height=1;throw new Error('La versión editada supera los 15 MB. Reduce la resolución de la imagen.');
    }
    form.addEventListener('submit',async event=>{
        event.preventDefault();
        if(submitting||saving)return;
        if(!ready||!image){setMessage('Selecciona primero una fotografía.',true);return;}
        if(cropEditing){setMessage('Aplica o cancela el recorte antes de continuar.',true);return;}
        saving=true;
        cancelButtons.forEach(button=>button.disabled=true);
        submitButton.disabled=true;submitButton.textContent='Preparando fotografía…';
        setMessage(isEditing?'Guardando cambios…':'Exportando la fotografía con los ajustes elegidos…');
        await new Promise(resolve=>requestAnimationFrame(()=>setTimeout(resolve,0)));
        try{
            if(isEditing){
                const payload=new FormData(form);
                payload.set('action','update_existing');
                payload.delete('photo');
                if(hasVisualChanges()){
                    const blob=await renderEditedPhoto();
                    payload.append('edited_photo',blob,'foto-editada.jpg');
                }else if(sourceVariant==='original'){
                    payload.set('restore_original','1');
                }
                await postUploadForm(payload);
                submitting=true;setMessage('Fotografía actualizada. Volviendo a la biblioteca…');
                const editedFile=form.querySelector('input[name="file"]')?.value||'';
                window.location.assign('/admin.php?library=1&published='+encodeURIComponent(editedFile));
                return;
            }
            const blob=await renderEditedPhoto();
            const payload=new FormData();payload.append('action','save_edit');payload.append('csrf',csrf);payload.append('edited_photo',blob,'foto-editada.jpg');
            await postUploadForm(payload);
            submitting=true;setMessage('Edición lista. Publicando la fotografía…');
            fileInput.disabled=true;
            HTMLFormElement.prototype.submit.call(form);
        }catch(error){saving=false;cancelButtons.forEach(button=>button.disabled=false);submitButton.disabled=false;submitButton.textContent=isEditing?'Guardar cambios':'Compartir fotografía';setMessage(error.message||'No se pudo guardar la edición.',true);}
    });
    updateToolUI();
    if(isEditing){
        categoryInput.value=categoryChoice.value||'';
        categoryInput.disabled=!categoryInput.value;
        categoryInput.hidden=true;
        loadExisting('published');
    }
})();
