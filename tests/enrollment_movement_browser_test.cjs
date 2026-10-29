const fs=require('fs'),path=require('path'),net=require('net'),assert=require('assert'),{spawn,execFileSync}=require('child_process');
const {chromium}=require('C:/Users/Aberto/.cache/codex-runtimes/codex-primary-runtime/dependencies/node/node_modules/playwright');
const root=path.resolve(__dirname,'..');
(async()=>{
 const dir=fs.mkdtempSync(path.join(root,'tmp/movement-browser-')),database=path.join(dir,'fixture.sqlite');
 execFileSync('php',['-r',`require 'tests/fixtures/enrollment_movement_database.php'; movementDatabase('sqlite:'.${JSON.stringify(database)});`],{cwd:root});
 const port=await new Promise(resolve=>{const s=net.createServer();s.listen(0,'127.0.0.1',()=>{const p=s.address().port;s.close(()=>resolve(p));});});
 const server=spawn('php',['-d','session.save_path='+dir,'-S','127.0.0.1:'+port,'tests/fixtures/enrollment_movement_router.php'],{cwd:root,env:{...process.env,MOVEMENT_TEST_DATABASE:database},stdio:'ignore',windowsHide:true});let browser;
 try{
  const base='http://127.0.0.1:'+port;for(let i=0;i<80;i++){try{await fetch(base);break;}catch{await new Promise(r=>setTimeout(r,100));}}
  const headers={'X-Requested-With':'XMLHttpRequest'};
  for(const[role,actor,status]of[['guest',10,401],['intern',10,403],['teacher',20,422]])assert.equal((await fetch(base+'/professor/inscricoes/mover?turma_id=1',{headers:{...headers,'X-Test-Role':role,'X-Test-Actor':String(actor)}})).status,status);
  assert.equal((await fetch(base+'/professor/inscricoes/mover',{method:'POST',headers,body:new URLSearchParams({turma_origem_id:'1'})})).status,422);
  browser=await chromium.launch({executablePath:'C:/Program Files/Google/Chrome/Application/chrome.exe',headless:true});
  const context=await browser.newContext({extraHTTPHeaders:{'X-Test-Role':'teacher','X-Test-Actor':'10'}}),page=await context.newPage(),errors=[];page.on('pageerror',e=>errors.push(e.message));
  await page.goto(base+'/professor');await page.locator('[data-enrollment-move]').click();
  const modal=page.locator('#enrollment-movement-modal');await modal.waitFor();
  assert.equal(await modal.locator('.enrollment-movement-row').count(),4);assert.equal(await modal.locator('teste').count(),0);
  assert.match(await modal.innerText(),/chamada registrada/);
  await modal.locator('select').nth(0).selectOption('substituir');assert(await modal.locator('input[value="5"]').isDisabled());
  await modal.locator('input[type=checkbox]').first().check();assert.equal(await modal.locator('.enrollment-movement-row input:checked').count(),3);
  await modal.locator('select').nth(0).selectOption('copiar');await modal.locator('[data-movement-season]').selectOption('1');await modal.locator('[data-movement-location]').selectOption('2');assert.equal(await modal.locator('input[name=movement_destination][value="4"]').count(),1);assert.equal(await modal.locator('input[name=movement_destination][value="2"]').count(),0);await modal.locator('[data-movement-location]').selectOption('1');assert.equal(await modal.locator('input[name=movement_destination][value="4"]').count(),0);await modal.locator('input[name=movement_destination][value="2"]').check();await modal.locator('textarea').fill('Troca de horário');
  await modal.getByRole('button',{name:'Revisar e enviar'}).click();const confirmation=page.locator('#enrollment-movement-confirm');await confirmation.waitFor();
  assert.equal(await confirmation.locator('input[type=checkbox]').count(),4);await confirmation.getByRole('button',{name:'Confirmar envio'}).click();assert.match(await page.locator('#message').innerText(),/confirmações/);
  for(const check of await confirmation.locator('input[type=checkbox]').all())await check.check();
  await confirmation.getByRole('button',{name:'Confirmar envio'}).click();await modal.waitFor({state:'detached'});assert.match(await page.locator('#message').innerText(),/3 inscrições enviadas/);
  await page.locator('[data-enrollment-move]').click();await modal.waitFor();await modal.locator('input[value="5"]').check();await modal.locator('[data-movement-season]').selectOption('1');await modal.locator('[data-movement-location]').selectOption('1');await modal.locator('input[name=movement_destination][value="3"]').check();await modal.locator('textarea').fill('Nova modalidade');await modal.getByRole('button',{name:'Revisar e enviar'}).click();await confirmation.waitFor();assert.match(await confirmation.innerText(),/modalidade do destino é diferente/);
  for(const check of await confirmation.locator('input[type=checkbox]').all())await check.check();await confirmation.getByRole('button',{name:'Confirmar envio'}).click();await modal.waitFor({state:'detached'});
  await page.evaluate(()=>{const b=document.createElement('button');b.dataset.enrollmentCopyHistory='5';b.textContent='Copiada — ver destinos';document.body.append(b);});
  await page.locator('[data-enrollment-copy-history]').click();const copies=page.locator('#enrollment-copies-modal');await copies.waitFor();assert.match(await copies.innerText(),/Futsal de destino/);assert.match(await copies.innerText(),/10:00 às 11:00/);await copies.getByRole('button',{name:'Fechar'}).click();
  await context.setExtraHTTPHeaders({'X-Test-Role':'teacher','X-Test-Actor':'20'});
  const destination=await (await context.request.get(base+'/professor/inscricoes/mover?turma_id=2',{headers})).json();assert.equal(destination.context.inscricoes.length,3);
  const id=destination.context.inscricoes.find(row=>row.nome_completo==='Aluno <teste>').id;
  await page.evaluate(({id,csrf})=>{const b=document.createElement('button');b.dataset.movedEnrollmentDelete=id;b.dataset.movementCsrf=csrf;b.textContent='Excluir movida';document.body.append(b);},{id,csrf:destination.csrf});
  await page.locator('[data-moved-enrollment-delete]').click();const deletion=page.locator('#moved-enrollment-delete-confirm');await deletion.locator('input').check();await deletion.getByRole('button',{name:'Excluir definitivamente'}).click();await deletion.waitFor({state:'detached'});
  const remaining=await (await context.request.get(base+'/professor/inscricoes/mover?turma_id=2',{headers})).json();assert.equal(remaining.context.inscricoes.length,2);
  await context.setExtraHTTPHeaders({'X-Test-Role':'admin','X-Test-Actor':'40'});await page.goto(base+'/admin');await page.locator('[data-enrollment-move]').click();await modal.waitFor();assert.equal(await modal.locator('.enrollment-movement-row').count(),4);
  for(const width of[320,768,1366]){await page.setViewportSize({width,height:900});assert(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+2),'Sem transbordamento no modal em '+width);}
  assert.deepEqual(errors,[]);console.log('OK: permissões HTTP, CSRF, selecionar tudo, substituição bloqueada, confirmação de status, cópia entre modalidades, exclusão pelo principal, XSS e layout.');
 }finally{if(browser)await browser.close();server.kill();}
})().catch(error=>{console.error(error);process.exitCode=1;});
