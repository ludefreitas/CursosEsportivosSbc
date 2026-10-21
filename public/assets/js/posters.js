(() => {
    'use strict';
    const node=document.getElementById('poster-data'); if(!node)return;
    const data=JSON.parse(node.textContent), form=document.getElementById('poster-editor'), canvas=document.getElementById('poster-canvas');
    const status=document.getElementById('poster-status'), fields=['name','local_id','temporada_id','title','subtitle','address','heading','modalities','site','instructions','free_text','contact','footer_text','primary','accent','background','text'];
    const images=['photo1','photo2','photo3','photo4','logo1','logo2','logo3','bg_header','bg_body','bg_footer'];
    ['photo1','photo2','photo3','photo4'].forEach(photo=>['x','y','zoom'].forEach(axis=>fields.push(photo+'_'+axis)));
    let record={id:''}, revision=0, dirty=false, uploading=0;
    const initial={}; fields.forEach(key=>initial[key]=form.elements[key].value);
    const cache=new Map();
    function note(message,error=false){status.textContent=message;status.style.color=error?'#b32721':'#145d74';}
    function collect(){const result={...record};fields.forEach(key=>result[key]=form.elements[key].value);return result;}
    function systemBase(){return new URL(data.base||'/',window.location.origin).href.replace(/\/?$/,'/');}
    function url(path){return new URL(path.replace(/^\//,''),systemBase()).href;}
    function target(value){if(!Number(value.local_id))return '';const address=new URL(systemBase());address.searchParams.set('local_modalidades',String(value.local_id));address.hash='home-training-locations';return address.href;}
    async function image(path){if(!path)return null;const source=path.startsWith('/uploads/')?url(path):path;if(!cache.has(source))cache.set(source,new Promise((resolve,reject)=>{const img=new Image();img.onload=()=>resolve(img);img.onerror=()=>{cache.delete(source);reject(new Error('Não foi possível carregar a imagem '+path+'. Confira o arquivo ou tente novamente.'));};img.src=source;}));return cache.get(source);}
    function lines(ctx,text,width){const output=[];String(text||'').split('\n').filter(paragraph=>paragraph.trim()).forEach(paragraph=>{let line='';for(const word of paragraph.split(/\s+/)){if(!word)continue;if(ctx.measureText(line+' '+word).width>width&&line){output.push(line);line=word;}else line+=(line?' ':'')+word;}output.push(line);});return output;}
    function text(ctx,value,x,y,width,size,color,bold=false,maxHeight=999){let rows;do{ctx.font=(bold?'700 ':'400 ')+size+'px Arial';rows=lines(ctx,value,width);if(rows.length*size*1.24<=maxHeight)break;size-=1;}while(size>10);ctx.fillStyle=color;ctx.textBaseline='top';rows.forEach((line,index)=>ctx.fillText(line,x,y+index*size*1.24,width));return rows.length*size*1.24;}
    function cover(ctx,img,x,y,w,h,crop={}){if(!img)return;const ratio=Math.max(w/img.width,h/img.height)*(Number(crop.zoom)||100)/100,iw=w/ratio,ih=h/ratio;ctx.drawImage(img,(img.width-iw)*(Number(crop.x??50)/100),(img.height-ih)*(Number(crop.y??50)/100),iw,ih,x,y,w,h);}
    function contain(ctx,img,x,y,w,h){if(!img)return;const ratio=Math.min(w/img.width,h/img.height);ctx.drawImage(img,x+(w-img.width*ratio)/2,y+(h-img.height*ratio)/2,img.width*ratio,img.height*ratio);}
    async function render(out,value,width=1080,branded=true){
        out.width=width;out.height=Math.round(width*1527/1080);const ctx=out.getContext('2d');ctx.scale(width/1080,width/1080);
        const loaded={};await Promise.all(images.map(async key=>{loaded[key]=await image(value[key]);}));
        ctx.fillStyle=value.background;ctx.fillRect(0,0,1080,1527);
        if(loaded.bg_body){cover(ctx,loaded.bg_body,0,240,1080,1160);ctx.fillStyle='rgba(255,255,255,.83)';ctx.fillRect(0,240,1080,1160);}
        ctx.fillStyle=value.primary;ctx.fillRect(0,0,1080,245);cover(ctx,loaded.bg_header,0,0,1080,245);
        if(loaded.bg_header){ctx.fillStyle='rgba(0,0,0,.48)';ctx.fillRect(0,0,1080,245);}
        const branding=data.branding||{};
        if(branded){
            if(branding.logo){ctx.fillStyle='#fff';ctx.fillRect(35,20,125,75);contain(ctx,await image(url(branding.logo)),40,24,115,65);}
            if(branding.institution)text(ctx,branding.institution,175,40,850,22,'#fff',true,55);
        }
        text(ctx,value.title||'Escolha um local',50,105,980,65,'#fff',true,85);
        text(ctx,value.subtitle,52,195,975,25,'#fff',true,42);
        ctx.fillStyle=value.accent;ctx.fillRect(0,245,1080,9);
        ctx.fillStyle=value.primary;ctx.fillRect(45,290,990,95);
        text(ctx,value.heading,66,312,945,35,'#fff',true,60);
        const photos=['photo1','photo2','photo3','photo4'].filter(key=>loaded[key]);
        const listWidth=photos.length?530:945;
        text(ctx,value.modalities||'Nenhuma modalidade cadastrada para esta seleção.',65,420,listWidth,52,value.text,true,490);
        if(photos.length){
            const columns=photos.length>=3?2:1, rows=Math.ceil(photos.length/columns),w=columns===2?213:370,h=(500-(rows-1)*22)/rows;
            photos.forEach((key,index)=>{
                const x=(columns===2?590:650)+(index%columns)*(w+18),y=415+Math.floor(index/columns)*(h+22),slant=w*.22;
                function shape(offset){ctx.beginPath();ctx.moveTo(x+slant+offset,y+offset);ctx.lineTo(x+w+offset,y+offset);ctx.lineTo(x+w-slant+offset,y+h+offset);ctx.lineTo(x+offset,y+h+offset);ctx.closePath();}
                shape(7);ctx.fillStyle=value.accent;ctx.fill();ctx.save();shape(0);ctx.clip();
                cover(ctx,loaded[key],x,y,w,h,{x:value[key+'_x'],y:value[key+'_y'],zoom:value[key+'_zoom']});ctx.restore();
            });
        }
        text(ctx,value.address,65,955,945,26,value.text,false,82);
        ctx.fillStyle=value.primary;ctx.fillRect(40,1070,1000,300);
        const destination=target(value),qx=790,qy=1110;
        ctx.fillStyle='#fff';ctx.fillRect(qx,qy,220,220);
        if(destination){
            const qr=qrcode(0,'M');qr.addData(destination);qr.make();const modules=qr.getModuleCount(),cell=220/(modules+8);
            ctx.fillStyle='#000';for(let row=0;row<modules;row++)for(let column=0;column<modules;column++)if(qr.isDark(row,column))ctx.fillRect(qx+(column+4)*cell,qy+(row+4)*cell,Math.ceil(cell),Math.ceil(cell));
        }else text(ctx,'Selecione um local para gerar o QR code',qx+15,qy+55,190,23,value.text,true,110);
        text(ctx,value.instructions,65,1095,685,29,'#fff',true,108);
        text(ctx,value.site,65,1210,685,24,'#fff',false,55);
        text(ctx,value.free_text,65,1275,685,27,'#fff',true,70);
        text(ctx,value.contact,55,1380,970,23,value.text,true,48);
        ctx.fillStyle=value.primary;ctx.fillRect(0,1440,1080,87);cover(ctx,loaded.bg_footer,0,1440,1080,87);
        if(loaded.bg_footer){ctx.fillStyle='rgba(0,0,0,.5)';ctx.fillRect(0,1440,1080,87);}
        const logos=['logo1','logo2','logo3'].filter(key=>loaded[key]);
        if(logos.length)logos.forEach((key,index)=>{ctx.fillStyle='#fff';ctx.fillRect(40+index*250,1450,230,65);contain(ctx,loaded[key],45+index*250,1454,220,57);});
        else text(ctx,value.footer_text,50,1464,975,26,'#fff',true,45);
        if(branded&&branding.footer_enabled&&(branding.footer_logos||[]).length){
            // Reservar uma faixa adicional mantém todos os parceiros legíveis.
            const entries=branding.footer_logos,rows=Math.ceil(entries.length/4),extra=55+rows*110;
            const copy=document.createElement('canvas');copy.width=out.width;copy.height=out.height;copy.getContext('2d').drawImage(out,0,0);
            out.height=Math.round((1527+extra)*width/1080);const footer=out.getContext('2d');footer.drawImage(copy,0,0);footer.scale(width/1080,width/1080);footer.fillStyle='#fff';footer.fillRect(0,1527,1080,extra);
            text(footer,branding.footer_title,40,1540,1000,22,value.text,true,40);
            for(let index=0;index<entries.length;index++){const entry=entries[index],x=30+(index%4)*265,y=1580+Math.floor(index/4)*110;contain(footer,await image(url(entry.logo)),x,y,235,55);text(footer,entry.institution,x,y+58,235,15,value.text,false,42);}
        }
        return out;
    }
    async function refresh(){const serial=++revision;try{const value=collect();const destination=target(value);const link=document.getElementById('poster-qr-link');link.hidden=!destination;link.href=destination||'#';document.getElementById('poster-qr-target').value=destination||'Selecione um local';const temp=document.createElement('canvas');await render(temp,value);if(serial!==revision)return;canvas.width=temp.width;canvas.height=temp.height;canvas.getContext('2d').drawImage(temp,0,0);}catch(error){note(error.message,true);}}
    function populate(){const value=collect(),location=data.options.locations.find(item=>String(item.id)===String(value.local_id));if(!location)return;
        form.elements.title.value=location.apelido_local||location.nome_local;form.elements.subtitle.value=location.nome_local;
        form.elements.address.value=[location.logradouro,location.numero_endereco,location.bairro,location.cidade,location.uf].filter(Boolean).join(', ');
        form.elements.modalities.value=[...new Set(data.options.modalities.filter(item=>String(item.local_treino_id)===String(location.id)&&(!Number(value.temporada_id)||String(item.temporada_id)===String(value.temporada_id))).map(item=>item.nome))].join('\n');dirty=true;refresh();
    }
    form.addEventListener('input',()=>{dirty=true;refresh();});
    form.elements.local_id.addEventListener('change',populate);form.elements.temporada_id.addEventListener('change',populate);document.getElementById('poster-refresh').onclick=populate;
    function validate(){
        form.querySelectorAll(':invalid').forEach(field=>{let parent=field.parentElement;while(parent&&parent!==form){if(parent.tagName==='DETAILS')parent.open=true;parent=parent.parentElement;}});
        return form.reportValidity();
    }
    async function save(asNew=false){
        if(uploading){note('Aguarde o envio das imagens.',true);return;}
        if(!validate())return;
        const value=collect();if(asNew)value.id='';
        const body=new FormData();body.set('csrf',data.csrf);body.set('data',JSON.stringify(value));
        try{const response=await fetch(url('/admin/cartazes/salvar'),{method:'POST',body,headers:{'X-Requested-With':'XMLHttpRequest'}});const result=await response.json();if(!response.ok||!result.success)throw new Error(result.message||'Não foi possível salvar.');record=result.record;const existing=data.posters.findIndex(item=>item.id===record.id);if(existing>=0)data.posters[existing]=record;else{data.posters.push(record);document.getElementById('poster-saved').add(new Option(record.name,record.id));}document.getElementById('poster-saved').value=record.id;document.querySelector('#poster-saved option:checked').textContent=record.name;dirty=false;history.replaceState(null,'','?id='+record.id);updateHistory();note('Campanha salva — versão '+record.version+'. As versões anteriores foram preservadas.');}catch(error){note(error.message,true);}
    }
    form.addEventListener('submit',event=>{event.preventDefault();save();});document.getElementById('poster-duplicate').onclick=()=>save(true);
    function updateHistory(){const select=document.getElementById('poster-history');select.replaceChildren(new Option('Versão atual',''));(record.history||[]).slice().reverse().forEach((version,index)=>select.add(new Option('Versão '+(version.version||index+1)+' — '+new Date(version.updated_at).toLocaleString('pt-BR'),String((record.history||[]).length-1-index))));}
    document.getElementById('poster-history').onchange=event=>{
        const value=event.target.value;if(value===''){load(record.id);return;}
        if(dirty&&!confirm('Abrir esta versão e descartar as alterações ainda não salvas?')){event.target.value='';return;}
        const campaign=data.posters.find(item=>item.id===record.id),version=campaign&&campaign.history&&campaign.history[Number(value)];if(!version)return;
        record={...version,id:campaign.id,version:campaign.version,history:campaign.history};fields.forEach(key=>form.elements[key].value=record[key]??initial[key]);dirty=true;refresh();note('Versão anterior carregada. Salve para criar uma nova versão sem apagar o histórico.');
    };
    document.getElementById('poster-new').onclick=()=>{if(dirty&&!confirm('Iniciar uma nova campanha e descartar alterações ainda não salvas?'))return;document.getElementById('poster-saved').value='';load('');};
    function load(id){record=data.posters.find(item=>item.id===id)||{id:''};fields.forEach(key=>form.elements[key].value=record[key]??initial[key]);images.forEach(key=>{form.querySelector('[data-poster-image="'+key+'"]').value='';});dirty=false;updateHistory();refresh();}
    document.getElementById('poster-saved').onchange=event=>{if(dirty&&!confirm('Descartar alterações não salvas e abrir outro cartaz?')){event.target.value=record.id||'';return;}load(event.target.value);};
    form.querySelectorAll('[data-poster-image]').forEach(input=>input.onchange=async()=>{if(!input.files[0])return;uploading++;note('Enviando imagem…');const body=new FormData();body.set('csrf',data.csrf);body.set('image',input.files[0]);try{const response=await fetch(url('/admin/cartazes/imagem'),{method:'POST',body,headers:{'X-Requested-With':'XMLHttpRequest'}}),result=await response.json();if(!response.ok||!result.success)throw new Error(result.message||'Falha ao enviar imagem.');record[input.dataset.posterImage]=result.path;dirty=true;note('Imagem adicionada. Salve para manter a alteração.');await refresh();}catch(error){note(error.message,true);}finally{uploading--;}});
    form.querySelectorAll('[data-remove-image]').forEach(button=>button.onclick=()=>{record[button.dataset.removeImage]='';dirty=true;refresh();});
    function ready(){if(uploading)throw new Error('Aguarde o envio das imagens.');if(!validate())throw new Error('Preencha o nome da campanha e selecione um local.');return collect();}
    async function output(){const value=ready(),format=document.getElementById('poster-format').value;return render(document.createElement('canvas'),value,format==='A3'?3508:format==='A4'?2480:1080);}
    async function blob(){return new Promise(async(resolve,reject)=>{try{(await output()).toBlob(result=>result?resolve(result):reject(new Error('Não foi possível gerar a imagem.')),'image/png');}catch(error){reject(error);}});}
    function download(file){const link=document.createElement('a');link.href=URL.createObjectURL(file);link.download='cartaz-divulgacao.png';link.click();setTimeout(()=>URL.revokeObjectURL(link.href),10000);}
    document.getElementById('poster-download').onclick=async()=>{try{download(await blob());}catch(error){note(error.message,true);}};
    document.getElementById('poster-share').onclick=async()=>{try{const file=new File([await blob()],'cartaz-divulgacao.png',{type:'image/png'});if(navigator.canShare&&navigator.canShare({files:[file]}))await navigator.share({files:[file],title:form.elements.title.value});else{download(file);note('Imagem baixada. Anexe o arquivo no WhatsApp ou no aplicativo desejado.');}}catch(error){if(error.name!=='AbortError')note(error.message,true);}};
    document.getElementById('poster-preview-open').onclick=async event=>{event.preventDefault();try{const preview=await render(document.createElement('canvas'),ready());const image=document.getElementById('poster-preview-image');image.src=preview.toDataURL('image/png');document.getElementById('poster-preview-dialog').showModal();}catch(error){note(error.message,true);}};
    document.getElementById('poster-preview-close').onclick=()=>document.getElementById('poster-preview-dialog').close();
    document.getElementById('poster-print').onclick=async()=>{try{const format=document.getElementById('poster-format').value==='A3'?'A3':'A4';const output=await render(document.createElement('canvas'),ready(),format==='A3'?3508:2480,false);let style=document.getElementById('poster-page-size');if(!style){style=document.createElement('style');style.id='poster-page-size';document.head.append(style);}style.textContent='@page{size:'+format+' portrait;margin:8mm}';document.documentElement.style.setProperty('--poster-page-height',format==='A3'?'403mm':'280mm');const img=document.getElementById('poster-print-image');img.src=output.toDataURL('image/png');await img.decode();await Promise.all([...document.querySelectorAll('#poster-print-sheet img')].map(item=>item.decode().catch(()=>{})));window.print();}catch(error){note(error.message,true);}};
    document.getElementById('poster-format').onchange=()=>{document.getElementById('poster-output-help').textContent=document.getElementById('poster-format').value==='social'?'Para celular, baixe a imagem ou compartilhe pelo menu do aparelho.':'Impressão em alta resolução. Na janela de impressão, selecione o mesmo tamanho de papel, desative cabeçalhos e rodapés do navegador e use escala 100%.';};
    window.addEventListener('beforeunload',event=>{if(dirty){event.preventDefault();event.returnValue='';}});
    const requested=data.previewOnly?(data.posters[0]||{}).id:new URLSearchParams(location.search).get('id');if(requested&&data.posters.some(item=>item.id===requested)){document.getElementById('poster-saved').value=requested;load(requested);}else{load('');}
})();
