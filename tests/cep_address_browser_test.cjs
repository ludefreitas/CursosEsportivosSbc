const assert=require('node:assert/strict'),fs=require('node:fs'),path=require('node:path'),{execFileSync}=require('node:child_process');
const {chromium}=require('C:/Users/Aberto/.cache/codex-runtimes/codex-primary-runtime/dependencies/node/node_modules/playwright');
const root=path.resolve(__dirname,'..');
const selector='input[data-person-cep-address="1"], input[data-cep-address-search="1"]';
const delay=ms=>new Promise(resolve=>setTimeout(resolve,ms));
(async()=>{
 const fixtures=Object.fromEntries(['profile','create','edit','admin','location'].map(kind=>[kind,execFileSync('php',['tests/fixtures/cep_forms_render.php',kind],{cwd:root,encoding:'utf8'})]));
 const browser=await chromium.launch({executablePath:'C:/Program Files/Google/Chrome/Application/chrome.exe',headless:true});
 try {
  const page=await browser.newPage({viewport:{width:1366,height:900}}),errors=[],calls=[];
  let retryReady=false,rejectCached=false;
  page.on('pageerror',e=>errors.push(e.message));
  await page.route('**/*',async route=>{
   const url=new URL(route.request().url()),cep=url.searchParams.get('cep'),pathname=url.pathname;
   if(pathname.startsWith('/api/ceps/')){
    calls.push({pathname,cep});
    const validation=pathname.endsWith('/validar');
    await delay(validation?80:cep==='09710090'?6500:cep==='09710091'?1800:1200);
    if(cep==='09710092'&&!validation&&!retryReady)return route.fulfill({status:503,json:{success:false}}).catch(()=>{});
    if(cep==='09710093'&&validation)return route.fulfill({status:503,json:{success:false}}).catch(()=>{});
    const refused=cep==='01001000'||(cep==='09710096'&&rejectCached);
    const data=validation?{aceito:!refused,mensagem:refused?'CEP fora da área atendida.':'CEP aceito.'}
      :cep==='00000000'?{success:false,message:'CEP não encontrado.'}
      :{success:true,address:{cep,logradouro:cep==='09710094'?'':'Rua '+cep,bairro:cep==='09710094'?'':'Centro',cidade:'São Bernardo do Campo',uf:'SP'}};
    return route.fulfill({json:data}).catch(()=>{});
   }
   if(pathname.startsWith('/assets/'))return route.fulfill({path:path.join(root,'public',pathname)});
   const kind=pathname.slice(1)||'profile';
   return route.fulfill({contentType:'text/html; charset=utf-8',body:'<!doctype html><html lang="pt-BR"><meta charset="utf-8"><link rel="stylesheet" href="/assets/css/core.css"><link rel="stylesheet" href="/assets/css/admin.css"><main>'+fixtures[kind]+'</main></html>'});
  });
  async function load(kind){
   await page.goto('http://cep-test.local/'+kind);
   await page.addScriptTag({path:path.join(root,'tmp/tutorial-realista/jquery.min.js')});
   await page.addScriptTag({path:path.join(root,'public/assets/js/core.js')});
   await page.addScriptTag({path:path.join(root,'public/assets/js/cep-address.js')});
   await page.addScriptTag({path:path.join(root,'public/assets/js/admin.js')});
   await page.evaluate(()=>{
    document.querySelectorAll('.hidden').forEach(e=>e.classList.remove('hidden'));
    document.querySelectorAll('.popup-overlay').forEach(e=>{e.style.position='relative';e.style.inset='auto';e.style.display='block';});
    App.core.mascararCep('input[name=zip_code],input[name=cep]');
    App.core.validarCepSbc('input[data-cep-sbc="1"]');
    App.core.iniciarAutocompleteCepPessoa();App.admin.iniciarBuscaEnderecoCep();
   });
  }
  async function settled(input){await input.page().waitForFunction(selector=>!document.querySelector(selector).hasAttribute('aria-busy'),selector);}
  function addressField(name,kind){return page.locator('[name="'+(kind==='location'?{street:'logradouro',neighborhood:'bairro',city:'cidade',state:'uf',address_number:'numero_endereco',address_complement:'complemento'}[name]:name)+'"]').first();}
  for(const [i,kind] of ['profile','create','edit','admin','location'].entries()){
   await load(kind);const input=page.locator(selector).first(),cep='0971000'+i;
   await addressField('street',kind).evaluate(e=>e.value='Endereço antigo');
   await addressField('address_number',kind).fill('1563');await addressField('address_complement',kind).fill('Casa 2');
   const before=calls.length;
   await input.fill(cep.slice(0,7));await delay(100);assert.equal(calls.length,before);
   await input.press('End');await input.pressSequentially(cep.slice(-1));
   const busy=await input.evaluate(e=>{const f=e.form,street=f.elements.street||f.elements.logradouro,number=f.elements.address_number||f.elements.numero_endereco;return {disabled:street.disabled,street:street.value,numberDisabled:number.disabled};});
   assert.deepEqual(busy,{disabled:true,street:'',numberDisabled:false});
   // Mesmo um formulário que faz a própria validação não envia campos bloqueados.
   const allowed=await input.evaluate(e=>{
    const form=e.form;form.setAttribute('data-own-submit-validation','1');
    return form.dispatchEvent(new Event('submit',{bubbles:true,cancelable:true}));
   });assert.equal(allowed,false);
   await settled(input);
   assert.equal(await addressField('street',kind).inputValue(),'Rua '+cep);
   assert.equal(await addressField('city',kind).inputValue(),'São Bernardo do Campo');
   assert.equal(await addressField('state',kind).inputValue(),'SP');
   assert.equal(await addressField('address_number',kind).inputValue(),'1563');assert.equal(await addressField('address_complement',kind).inputValue(),'Casa 2');
   assert(!(await addressField('street',kind).isDisabled()));assert.equal(await page.locator('.cep-address-option').count(),0);
   if(kind!=='location')assert.equal(await page.locator('[name=emergency_contact_name]').locator('..').locator('span').first().innerText(),'Nome de um contato para emergência');
   const queries=calls.slice(before);assert.equal(queries.filter(x=>x.pathname.endsWith('/endereco')).length,1);
   assert.equal(queries.filter(x=>x.pathname.endsWith('/validar')).length,kind==='location'?0:1);
   // Blur não inicia outra consulta nem volta a pedir seleção manual.
   await input.blur();await delay(100);assert.equal(calls.length,before+queries.length);
  }
  await load('create');const input=page.locator(selector).first();
  // Resposta antiga não pode substituir a de outro CEP digitado em seguida.
  await input.fill('09710091');await delay(100);await input.fill('09710095');await settled(input);await delay(700);
  assert.equal(await addressField('street').inputValue(),'Rua 09710095');
  // Um CEP inexistente ou recusado não mantém dados do endereço anterior.
  await input.fill('00000000');await settled(input);assert.equal(await addressField('street').inputValue(),'');assert.match(await input.getAttribute('data-remote-validation-error'),/não encontrado/);
  await input.fill('01001000');await settled(input);assert.match(await input.getAttribute('data-remote-validation-error'),/fora da área/);
  // Limite de espera e alternativa manual preservam a validação do CEP.
  const start=Date.now();await input.fill('09710090');await settled(input);
  assert(Date.now()-start<5700,'O formulário ficou bloqueado além do limite.');
  assert(!(await addressField('street').isDisabled()));assert.equal(await input.getAttribute('data-remote-validation-error'),'');
  await addressField('street').fill('Rua preenchida manualmente');assert(await page.locator('[data-cep-address-retry]').isVisible());
  await input.fill('09710093');await settled(input);assert.match(await input.getAttribute('data-remote-validation-error'),/validar/);assert(!(await addressField('street').isDisabled()));
  await input.fill('09710092');await settled(input);assert(await page.locator('[data-cep-address-retry]').isVisible());
  retryReady=true;await page.locator('[data-cep-address-retry]').click();await settled(input);assert.equal(await addressField('street').inputValue(),'Rua 09710092');
  // Endereço parcial de CEP genérico permite preencher os campos ausentes.
  await input.fill('09710094');await settled(input);await addressField('street').fill('Rua do CEP genérico');assert.equal(await addressField('city').inputValue(),'São Bernardo do Campo');
  // Reiniciar um formulário durante a consulta cancela a resposta pendente.
  await input.fill('09710091');await input.evaluate(e=>e.form.reset());await delay(1400);assert(!(await addressField('street').isDisabled()));assert.notEqual(await addressField('street').inputValue(),'Rua 09710091');
  await input.evaluate(e=>{e.value='09710091';e.dispatchEvent(new Event('input',{bubbles:true}));e.value='09710095';e.form.elements.street.value='Endereço importado';});
  await delay(300);assert(!(await addressField('street').isDisabled()));assert.notEqual(await input.getAttribute('data-remote-validation-error'),'Aguarde a consulta do CEP.');assert.equal(await addressField('street').inputValue(),'Endereço importado');
  // Dois formulários não compartilham cancelamentos nem bloqueios.
  await page.evaluate(()=>{const form=document.querySelector('input[data-person-cep-address]').form;const copy=form.cloneNode(true);copy.id='segundo-form';form.after(copy);});
  const inputs=page.locator(selector);await inputs.nth(0).fill('09710096');await inputs.nth(1).fill('09710097');
  await page.waitForFunction(selector=>Array.from(document.querySelectorAll(selector)).every(e=>!e.hasAttribute('aria-busy')),selector);
  assert.equal(await inputs.nth(0).evaluate(e=>e.form.elements.street.value),'Rua 09710096');assert.equal(await inputs.nth(1).evaluate(e=>e.form.elements.street.value),'Rua 09710097');
  const beforeCache=calls.length;await inputs.nth(0).fill('09710096');await settled(input);
  assert.deepEqual(calls.slice(beforeCache),[{pathname:'/api/ceps/validar',cep:'09710096'}]);
  rejectCached=true;await inputs.nth(0).fill('09710096');await settled(input);assert.match(await inputs.nth(0).getAttribute('data-remote-validation-error'),/fora da área/);
  assert.equal(await inputs.nth(0).evaluate(e=>e.form.elements.street.value),'');
  // Faixas e exceções de CEP não são formulários de endereço.
  await page.evaluate(()=>{document.body.insertAdjacentHTML('beforeend','<form><input id="range" name="cep_inicio" value=""><input name="logradouro" value="Não alterar"></form>');});
  const beforeRange=calls.length;await page.locator('#range').fill('09710000');await delay(150);assert.equal(calls.length,beforeRange);
  assert.deepEqual(errors,[]);
  console.log('CEP: cinco formulários reais, último dígito, preenchimento sem clique, bloqueio temporário, número/complemento preservados, prazo de 5s, falhas, CEP inexistente/recusado, resposta atrasada, reset, formulários simultâneos e títulos de emergência verificados.');
 }finally{await browser.close();}
})().catch(error=>{console.error(error);process.exitCode=1;});
