const assert=require('node:assert/strict'),{execFileSync}=require('node:child_process');
const {chromium}=require('C:/Users/Aberto/.cache/codex-runtimes/codex-primary-runtime/dependencies/node/node_modules/playwright');
(async()=>{
 const panels=Object.fromEntries(['master_admin','admin','teacher','supervisor','coordinator'].map(role=>[role,execFileSync('php',['tests/fixtures/enrollment_removal_panel.php',role],{encoding:'utf8'})]));
 const browser=await chromium.launch({executablePath:'C:/Program Files/Google/Chrome/Application/chrome.exe',headless:true});
 try{
  const page=await browser.newPage({viewport:{width:1366,height:900}}),errors=[];page.on('pageerror',e=>errors.push(e.message));
  for(const role of ['admin','teacher','supervisor','coordinator']){await page.setContent(panels[role]);assert.equal(await page.locator('[data-enrollment-master-remove]').count(),0,role+' não pode ver Remover');}
  await page.setContent('<!doctype html><meta name="viewport" content="width=device-width,initial-scale=1"><body>'+panels.master_admin);
  await page.addStyleTag({path:'public/assets/css/core.css'});await page.addStyleTag({path:'public/assets/css/admin.css'});
  await page.addScriptTag({path:'tmp/tutorial-realista/jquery.min.js'});await page.addScriptTag({path:'public/assets/js/core.js'});
  await page.evaluate(()=>{
   window.calls=[];window.popups=[];window.refreshes=[];window.mode='failure';
   App.core.iniciarValidacaoFormularios();App.core.buildUrl=p=>p;App.core.abrirPopup=(kind,message)=>popups.push({kind,message});
   App.admin={activateSection:(...args)=>refreshes.push(args)};
   jQuery.ajax=options=>{calls.push(options);const deferred=jQuery.Deferred();setTimeout(()=>mode==='failure'?deferred.reject({responseJSON:{message:'Falha simulada. Nada foi removido.'}}):deferred.resolve({success:true,inscricao_id:5,turma_id:1,message:'Inscrição removida.'}),350);return deferred.promise();};
  });
  await page.addScriptTag({path:'public/assets/js/enrollment-removal.js'});
  const button=page.getByRole('button',{name:'Remover',exact:true});assert.equal(await button.count(),1);
  assert(await button.evaluate(e=>parseFloat(getComputedStyle(e).fontSize)<=13),'O botão deve ser discreto.');
  await button.click();const dialog=page.locator('#enrollment-master-removal-modal');
  assert.match(await dialog.innerText(),/Aluno <teste>/);assert.match(await dialog.innerText(),/Inscrição: #5/);assert.match(await dialog.innerText(),/Turma de teste/);assert.match(await dialog.innerText(),/definitiva/);
  assert.equal(await dialog.locator('script,img').count(),0,'Dados do aluno não podem virar HTML.');
  await dialog.getByRole('button',{name:'Voltar',exact:true}).click();assert.equal(await page.evaluate(()=>calls.length),0);assert.equal(await dialog.count(),0,'Voltar deve fechar a confirmação.');
  await button.click();await dialog.getByRole('button',{name:'Confirmar remoção'}).click();assert.equal(await page.evaluate(()=>calls.length),0);
  await dialog.locator('[name=motivo]').fill('Inscrição indevida.');await dialog.locator('[name=confirmacao]').fill('EXCLUIR');await dialog.getByRole('button',{name:'Confirmar remoção'}).click();assert.equal(await page.evaluate(()=>calls.length),0);
  await dialog.locator('[name=confirmacao]').fill('REMOVER');await dialog.getByRole('button',{name:'Confirmar remoção'}).click();
  await page.evaluate(()=>jQuery('[data-enrollment-master-removal-form]').trigger('submit'));
  await page.waitForFunction(()=>!document.querySelector('#enrollment-master-removal-modal button[type=submit]').disabled);
  assert.equal(await page.evaluate(()=>calls.length),1);assert.match(await dialog.locator('[data-removal-error]').innerText(),/Nada foi removido/);
  assert.equal(await dialog.locator('[name=motivo]').inputValue(),'Inscrição indevida.');assert.equal(await button.count(),1);
  const sent=await page.evaluate(()=>calls[0].data);assert.equal(sent.inscricao_id,'5');assert.equal(sent.confirmacao,'REMOVER');assert.match(sent.csrf,/^[a-f0-9]{64}$/);
  await page.setViewportSize({width:390,height:844});assert(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth));
  await page.screenshot({path:'tmp/enrollment-removal-mobile.png'});
  await page.evaluate(()=>mode='success');await dialog.getByRole('button',{name:'Confirmar remoção'}).click();await page.waitForSelector('#enrollment-master-removal-modal',{state:'detached'});
  assert.equal(await page.locator('[data-enrollment-master-remove]').count(),0);assert.equal(await page.evaluate(()=>refreshes.length),1);
  assert.equal(await page.evaluate(()=>refreshes[0][0]),'inscricoes');assert.equal(await page.evaluate(()=>refreshes[0][1].turma_id),'1');
  assert.deepEqual(errors,[]);
  console.log('Remover na interface: apenas Master, botão pequeno, identificação, confirmação digitada, motivo, CSRF, cancelamento, erro preservado, duplicidade bloqueada, atualização dos filtros e celular verificados.');
 }finally{await browser.close();}
})().catch(error=>{console.error(error);process.exitCode=1;});
