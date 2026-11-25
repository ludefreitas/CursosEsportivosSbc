const fs=require('node:fs'),assert=require('node:assert/strict');
const {chromium}=require('C:/Users/Aberto/.cache/codex-runtimes/codex-primary-runtime/dependencies/node/node_modules/playwright');
(async()=>{
 const browser=await chromium.launch({executablePath:'C:/Program Files/Google/Chrome/Application/chrome.exe',headless:true});
 try{
  const page=await browser.newPage({viewport:{width:1366,height:900}}),errors=[];page.on('pageerror',e=>errors.push(e.message));
  await page.setContent('<!doctype html><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><body><button data-rematriculation-open="519">Rematrícula</button>');
  await page.addStyleTag({path:'public/assets/css/core.css'});await page.addStyleTag({path:'public/assets/css/admin.css'});await page.addScriptTag({path:'tmp/tutorial-realista/jquery.min.js'});
  await page.evaluate(()=>{
   window.calls=[];window.popups=[];window.mode='failure';
   window.campaign={id:1,destino_turma_id:519,prazo_dias:14,prazo_final:'2027-02-16 10:00:00',prazo_final_formatado:'16/02/2027 às 10:00',inscricoes_inicio:'2027-02-02 10:00:00',origem_temporada_nome:'2026',origem_local_nome:'Local',origem_modalidade_nome:'Judô',origem_turma_nome:'Turma origem',pessoas:[
    {id:1,pessoa_nome:'Aluno com inscrição removida',idade:6,status:'token_gerado',status_label:'Inscrição removida — novo convite disponível',pode_reenviar:true,reenvio_apos_exclusao:true},
    {id:2,pessoa_nome:'Aluno com inscrição ativa',idade:6,status:'inscrita',status_label:'Inscrito na turma',pode_reenviar:false},
    {id:3,pessoa_nome:'Convite aguardando resposta',idade:6,status:'aguardando_resposta',status_label:'Aguardando resposta',pode_reenviar:false}
   ]};
   window.App={core:{buildUrl:p=>p,abrirPopup:(kind,message)=>popups.push({kind,message})}};
   jQuery.getJSON=()=>jQuery.Deferred().resolve({success:true,campaign:campaign}).promise();
   jQuery.post=(url,data)=>{
    calls.push({url,data});const deferred=jQuery.Deferred();
    setTimeout(()=>{
     if(mode==='failure')return deferred.reject({responseJSON:{message:'Falha de conexão simulada.'}});
     campaign.pessoas[0].status='aguardando_resposta';campaign.pessoas[0].status_label='Aguardando resposta';campaign.pessoas[0].pode_reenviar=false;
     deferred.resolve({success:true,message:'Convite reenviado.',campaign:campaign});
    },350);
    return deferred.promise();
   };
  });
  await page.addScriptTag({path:'public/assets/js/admin.js'});
  await page.locator('[data-rematriculation-open]').click();
  assert.equal(await page.locator('[data-rematriculation-resend]').count(),1);
  assert.match(await page.locator('#rematriculation-modal').innerText(),/Inscrição removida/);
  await page.locator('[data-rematriculation-resend]').click();
  const dialog=page.locator('#rematriculation-resend-modal');
  assert(await dialog.isVisible());assert.match(await dialog.innerText(),/notificação anterior será arquivada/);assert.equal(await page.evaluate(()=>calls.length),0);
  await dialog.getByRole('button',{name:'Voltar',exact:true}).click();assert.equal(await dialog.count(),0);assert.equal(await page.evaluate(()=>calls.length),0);
  await page.locator('[data-rematriculation-resend]').click();await dialog.locator('[name=motivo]').fill('   ');await dialog.getByRole('button',{name:'Confirmar reenvio',exact:true}).click();
  assert.match(await dialog.locator('[data-rematriculation-resend-error]').innerText(),/motivo/);assert.equal(await page.evaluate(()=>calls.length),0);
  await dialog.locator('[name=motivo]').fill('Inscrição realizada antes da abertura e excluída.');await dialog.getByRole('button',{name:'Confirmar reenvio',exact:true}).click();
  await page.evaluate(()=>jQuery('[data-rematriculation-resend-form]').trigger('submit'));
  await page.waitForFunction(()=>!document.querySelector('#rematriculation-resend-modal button[type=submit]').disabled);
  assert.equal(await page.evaluate(()=>calls.length),1);assert.match(await dialog.locator('[data-rematriculation-resend-error]').innerText(),/Falha de conexão/);
  assert.equal(await dialog.locator('[name=motivo]').inputValue(),'Inscrição realizada antes da abertura e excluída.');
  await page.setViewportSize({width:390,height:844});assert(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth));
  await page.evaluate(()=>mode='success');await dialog.getByRole('button',{name:'Confirmar reenvio',exact:true}).click();
  await page.waitForSelector('#rematriculation-resend-modal',{state:'detached'});
  assert.equal(await page.locator('[data-rematriculation-resend]').count(),0);
  assert.deepEqual(await page.evaluate(()=>calls.at(-1)),{url:'/rematriculas/reenviar',data:{id:'1',convite_id:'1',motivo:'Inscrição realizada antes da abertura e excluída.'}});
  assert(await page.getByText('Aluno com inscrição removida',{exact:true}).isVisible());
  assert.deepEqual(errors,[]);
  console.log('Reenvio na interface: botão condicionado, confirmação, motivo, cancelamento, falha sem perda de dados, duplicidade bloqueada, atualização para aguardando resposta e celular verificados.');
 }finally{await browser.close();}
})().catch(error=>{console.error(error);process.exitCode=1;});
