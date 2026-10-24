(() => {
'use strict';
const base=()=>String(window.APP_BASE_URL||'/').replace(/\/$/,'');
let catalog,modal,form,rows,previewUrl;
const el=(tag,text)=>{const e=document.createElement(tag);if(text)e.textContent=text;return e;};
async function request(path,options){const r=await fetch(base()+path,options);if(!r.ok){const d=await r.json();throw Error(d.message||'Não foi possível concluir.');}return r;}
function addRow(entry={},index=-1){const row=el('div');row.style.cssText='display:flex;gap:8px;align-items:center;margin:10px 0';row.dataset.existing=String(index);const name=el('input');name.value=entry.institution||'';name.placeholder='Nome da instituição';name.maxLength=80;name.setAttribute('aria-label','Instituição do rodapé');const file=el('input');file.type='file';file.accept='image/png,image/jpeg';file.setAttribute('aria-label','Logo do rodapé');row.append(name,file);if(entry.logo){const img=el('img');img.src=base()+entry.logo;img.style.cssText='max-width:60px;max-height:40px';row.append(img);}const remove=el('button','Remover');remove.type='button';remove.onclick=()=>row.remove();row.append(remove);rows.append(row);}
function payload(){const data=new FormData(form);const entries=[];[...rows.children].forEach((row,i)=>{entries.push({institution:row.querySelector('input').value,existing_index:Number(row.dataset.existing)});const file=row.querySelector('input[type=file]').files[0];if(file)data.append('footer_logo_'+i,file);});data.set('footer_entries',JSON.stringify(entries));return data;}
async function open(id){try{catalog=await (await request('/admin/pdfs')).json();const model=catalog.models.find(m=>m.id===id);if(!model)return;modal=el('dialog');modal.style.cssText='width:min(900px,95vw);max-height:90vh;overflow:auto;border:1px solid #ccc;border-radius:16px;padding:24px';form=el('form');form.innerHTML='<h2></h2><input type="hidden" name="model"><input type="hidden" name="csrf"><p><label>Nome da instituição principal <input name="institution" maxlength="150" style="width:100%"></label></p><p><label>Logo principal (PNG ou JPG, até 2 MB e 1.024 pixels por lado) <input type="file" name="logo" accept="image/png,image/jpeg"></label></p><p><label><input type="checkbox" name="remove_logo" value="1"> Remover logo principal</label></p><p><label><input type="checkbox" name="footer_enabled" value="1"> Exibir logos de rodapé</label></p><p><label>Título do rodapé <input name="footer_title" maxlength="80" placeholder="Patrocinadores, Apoio, Parceiros..."></label></p>';
form.querySelector('h2').textContent=model.name;for(const key of ['model','csrf','institution','footer_title'])form.elements[key].value=key==='model'?id:key==='csrf'?catalog.csrf:model.settings[key];form.elements.footer_enabled.checked=model.settings.footer_enabled;
if(model.settings.logo){const img=el('img');img.src=base()+model.settings.logo;img.style.cssText='max-width:150px;max-height:90px';form.append(img);}rows=el('div');form.append(rows);model.settings.footer_logos.forEach(addRow);const add=el('button','Adicionar logo de rodapé');add.type='button';add.onclick=()=>{if(rows.children.length<12)addRow();};form.append(add);
const msg=el('p');msg.setAttribute('role','status');const save=el('button','Salvar configuração');save.type='submit';const preview=el('button','Pré-visualizar');preview.type='button';const close=el('button','Fechar');close.type='button';close.onclick=()=>modal.close();form.append(msg,save,preview,close);const frame=el('iframe');frame.title='Pré-visualização do PDF';frame.hidden=true;frame.style.cssText='width:100%;height:60vh;margin-top:20px';form.append(frame);
form.onsubmit=async e=>{e.preventDefault();save.disabled=true;try{const result=await (await request('/admin/pdfs/salvar',{method:'POST',body:payload()})).json();msg.textContent=result.message;modal.close();await open(id);}catch(err){msg.textContent=err.message;}finally{save.disabled=false;}};
preview.onclick=async()=>{preview.disabled=true;try{const r=await request('/admin/pdfs/preview',{method:'POST',body:payload()});if(previewUrl)URL.revokeObjectURL(previewUrl);previewUrl=URL.createObjectURL(await r.blob());frame.src=previewUrl;frame.hidden=false;msg.textContent='Prévia das alterações, sem salvar a configuração.';}catch(err){msg.textContent=err.message;}finally{preview.disabled=false;}};
modal.append(form);document.body.append(modal);modal.addEventListener('close',()=>{modal.remove();if(previewUrl){URL.revokeObjectURL(previewUrl);previewUrl=null;}},{once:true});modal.showModal();}catch(err){alert(err.message);}}
document.addEventListener('click',e=>{const b=e.target.closest('[data-pdf-model]');if(b)open(b.dataset.pdfModel);});
// O atalho aguarda a seção administrativa carregar por AJAX.
const requestedModel=new URLSearchParams(window.location.search).get('pdf_model');
if(requestedModel&&/^[a-z0-9_]+$/.test(requestedModel)&&window.location.pathname.replace(/\/$/,'').endsWith('/admin')){
    let observer;
    function openRequestedModel(){
        const button=document.querySelector('[data-pdf-branding-panel] [data-pdf-model="'+requestedModel+'"]');
        if(!button)return;
        if(observer)observer.disconnect();
        const cleanUrl=new URL(window.location.href);cleanUrl.searchParams.delete('pdf_model');
        window.history.replaceState({},document.title,cleanUrl.pathname+cleanUrl.search+cleanUrl.hash);
        button.scrollIntoView({behavior:'smooth',block:'center'});
        open(requestedModel);
    }
    observer=new MutationObserver(openRequestedModel);
    observer.observe(document.body,{childList:true,subtree:true});
    openRequestedModel();
}
// Atalhos nas páginas que oferecem geração de arquivos PDF.
if(document.body.dataset.pdfBrandingAllowed==='1'){
    const models=[
        ['nivel','Certificado de nível',/\/avaliacoes\/certificado\.pdf$/],
        ['chamada','Lista de chamada',/\/turmas\/chamada\.pdf$/],
        ['enderecos','Lista com endereços',/\/turmas\/lista-enderecos\.pdf$/],
        ['declaracao','Declaração',/\/cursos\/declaracao(?:\.pdf|\/emitir)?$/],
        ['frequencia','Frequência mensal',/\/cursos\/frequencia$/]
    ];
    let timer;
    function syncShortcuts(){
        const main=document.querySelector('main');if(!main)return;
        const found=new Set();
        document.querySelectorAll('a[href]:not([data-pdf-brand-link])').forEach(link=>{
            let path;try{path=new URL(link.href,location.href).pathname;}catch(error){return;}
            models.forEach(([id,name,pattern])=>{if(pattern.test(path))found.add(id);});
        });
        const desired=models.filter(([id])=>found.has(id)&&!document.querySelector('[data-pdf-brand-link="'+id+'"]'));
        let region=document.getElementById('pdf-brand-shortcuts');
        const signature=desired.map(([id])=>id).join(',');
        if(region&&region.dataset.models===signature)return;
        if(!desired.length){if(region)region.remove();return;}
        if(!region){region=el('aside');region.id='pdf-brand-shortcuts';region.className='pdf-brand-shortcuts';region.setAttribute('aria-label','Configuração dos documentos PDF');main.prepend(region);}
        region.dataset.models=signature;region.replaceChildren(el('strong','Logos e rodapés dos PDFs: '));
        desired.forEach(([id,name])=>{const link=el('a','Editar logo e rodapé — '+name);link.href=base()+'/admin?pdf_model='+id+'#admin-outras-areas';region.append(link);});
    }
    new MutationObserver(()=>{clearTimeout(timer);timer=setTimeout(syncShortcuts,50);}).observe(document.body,{childList:true,subtree:true});
    syncShortcuts();
}
})();
