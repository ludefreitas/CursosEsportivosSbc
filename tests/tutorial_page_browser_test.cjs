const fs=require('fs'),path=require('path'),assert=require('assert'),{execFileSync}=require('child_process');
const {chromium}=require('C:/Users/Aberto/.cache/codex-runtimes/codex-primary-runtime/dependencies/node/node_modules/playwright');
const root=path.resolve(__dirname,'..');
(async()=>{
 const editor=execFileSync('php',['tests/fixtures/tutorial_editor_render.php'],{cwd:root,encoding:'utf8'});
 const publicPage=execFileSync('php',['tests/fixtures/tutorial_editor_render.php','public'],{cwd:root,encoding:'utf8'});
 const browser=await chromium.launch({executablePath:'C:/Program Files/Google/Chrome/Application/chrome.exe',headless:true});
 try{
 const page=await browser.newPage({viewport:{width:1366,height:900}});const errors=[];page.on('pageerror',e=>errors.push(e.message));
 await page.route('**/*',route=>{
  const p=new URL(route.request().url()).pathname;
  if(p.startsWith('/assets/'))return route.fulfill({path:path.join(root,'public',p)});
  if(p==='/jquery.js')return route.fulfill({path:path.join(root,'tmp/tutorial-realista/jquery.min.js')});
  if(p==='/public')return route.fulfill({contentType:'text/html',body:'<!doctype html><html lang="pt-BR"><meta charset="utf-8"><link rel="stylesheet" href="/assets/css/core.css">'+publicPage+'</html>'});
  return route.fulfill({contentType:'text/html',body:'<!doctype html><html lang="pt-BR"><meta charset="utf-8"><link rel="stylesheet" href="/assets/css/core.css"><link rel="stylesheet" href="/assets/css/admin.css"><body style="padding:24px"><main>'+editor+'</main><script src="/jquery.js"></script><script>window.App={state:{},core:{abrirPopup:(type,message)=>window.lastPopup={type,message},extrairMensagemErroAjax:()=>({mensagem:"Falha de teste"})}};</script><script src="/assets/js/admin.js"></script><script>App.admin.iniciarEditorPaginaAjuda();App.admin.activateSection=()=>{window.didRefresh=true};</script></body></html>'});
 });
 await page.goto('http://tutorial-test.local/editor');
 const rows=()=>page.locator('[data-tutorial-videos-list] [data-tutorial-video-row]');
 assert.equal(await rows().count(),2);
 await rows().nth(0).locator('[data-tutorial-video-type]').selectOption('local');
 assert(await rows().nth(0).locator('[data-tutorial-source="local"]').isVisible());
 assert(await rows().nth(0).locator('[data-tutorial-field="video_url"]').isDisabled());
 await rows().nth(0).locator('[data-tutorial-video-type]').selectOption('youtube');
 assert(await rows().nth(0).locator('[type=file]').isDisabled());
 await page.locator('[data-tutorial-video-add]').click();
 await rows().nth(2).locator('[data-tutorial-field="video_titulo"]').fill('Atestado dermatológico');
 await rows().nth(2).locator('[data-tutorial-video-type]').selectOption('local');
 await rows().nth(2).locator('[type=file]').setInputFiles({name:'dermatologico.mp4',mimeType:'video/mp4',buffer:Buffer.from('arquivo demonstrativo')});
 await rows().nth(2).locator('[data-tutorial-video-up]').click();
 assert.equal(await rows().nth(1).locator('[type=file]').evaluate(e=>e.files[0].name),'dermatologico.mp4');
 // Captura o FormData produzido pela implementação para conferir índices após ordenar.
 await page.evaluate(()=>{jQuery.ajax=options=>{window.sentFields=Array.from(options.data.entries()).map(([key,value])=>[key,value instanceof File?value.name:value]);return jQuery.Deferred().resolve({success:true,message:'Página salva.'}).promise();};});
 await page.locator('button[type=submit]').click();
 const sent=Object.fromEntries(await page.evaluate(()=>window.sentFields));
 assert.equal(sent['video_tipo[0]'],'youtube');assert.equal(sent['video_arquivo[0]'],undefined);
 assert.equal(sent['video_titulo[1]'],'Atestado dermatológico');assert.equal(sent['video_arquivo[1]'],'dermatologico.mp4');
 assert.equal(sent['video_url[1]'],undefined);assert.equal(sent['video_arquivo_atual[2]'],'/uploads/tutorial-videos/'+'a'.repeat(32)+'.mp4');
 assert(await page.evaluate(()=>window.didRefresh));
 // Um erro conserva o formulário e restaura somente os controles antes habilitados.
 await page.evaluate(()=>{jQuery.ajax=()=>jQuery.Deferred().reject({status:422}).promise();});
 await page.locator('button[type=submit]').click();
 assert(!(await page.locator('button[type=submit]').isDisabled()));
 assert(await rows().nth(0).locator('[type=file]').isDisabled());
 assert(await rows().nth(1).locator('[data-tutorial-field="video_url"]').isDisabled());
 assert.equal(await rows().nth(1).locator('[type=file]').evaluate(e=>e.files[0].name),'dermatologico.mp4');
 await page.screenshot({path:path.join(root,'tmp/tutorial-editor-desktop.png'),fullPage:true});
 await page.setViewportSize({width:390,height:844});
 assert(await page.evaluate(()=>document.documentElement.scrollWidth<=window.innerWidth));
 await page.screenshot({path:path.join(root,'tmp/tutorial-editor-mobile.png'),fullPage:true});
 while(await rows().count()>1)await rows().last().locator('[data-tutorial-video-remove]').click();
 await rows().first().locator('[data-tutorial-video-remove]').click();
 assert.equal(await rows().count(),1);assert.equal(await rows().first().locator('[data-tutorial-field="video_titulo"]').inputValue(),'');
 assert.equal(await rows().first().locator('[data-tutorial-video-type]').inputValue(),'youtube');
 await page.goto('http://tutorial-test.local/public');
 assert.equal(await page.locator('iframe[src*="youtube-nocookie"]').count(),1);
 assert.equal(await page.locator('video[controls] source[type="video/mp4"]').count(),1);
 assert.match(await page.locator('video source').getAttribute('src'),/^\/uploads\/tutorial-videos\//);
 assert.deepEqual(errors,[]);console.log('OK: alternância, arquivo selecionado após ordenar, índices do envio, preservação, falha de envio, remoção, layout móvel e página pública com as duas origens.');
 }finally{await browser.close();}
})().catch(e=>{console.error(e);process.exitCode=1;});
