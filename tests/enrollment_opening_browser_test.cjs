const fs=require('node:fs'),path=require('node:path'),assert=require('node:assert/strict');
const {chromium}=require('C:/Users/Aberto/.cache/codex-runtimes/codex-primary-runtime/dependencies/node/node_modules/playwright');
const source=fs.readFileSync(path.resolve(__dirname,'../public/assets/js/home.js'),'utf8');
const extract=(from,to)=>source.slice(source.indexOf('            function '+from),source.indexOf('            function '+to));
(async()=>{
 const browser=await chromium.launch({executablePath:'C:/Program Files/Google/Chrome/Application/chrome.exe',headless:true});
 try {
  const page=await browser.newPage({viewport:{width:1280,height:900}}),errors=[];
  page.on('pageerror',error=>errors.push(error.message));
  await page.setContent('<!doctype html><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><body><div id="home-course-cpf-modal" class="hidden"></div><div id="home-course-enrollment-modal" class="popup-overlay hidden" aria-hidden="true"><div class="popup-card"><p id="home-course-enrollment-subtitle"></p><div id="home-course-enrollment-content"></div></div></div>');
  await page.addStyleTag({path:'public/assets/css/core.css'});await page.addStyleTag({path:'public/assets/css/home.css'});
  await page.addScriptTag({path:'tmp/tutorial-realista/jquery.min.js'});
  await page.evaluate(()=>{
   window.App={core:{buildUrl:p=>p,formatBirthDateWithAge:()=>'01/01/2020 · 6 anos'}};window.classesById={};window.selectedModality=null;
   window.classAgeCriterionText=()=> '4 a 7 anos';window.appendClassAgeExceptions=()=>{};window.appendClassPublicNotices=()=>{};window.appendEnrollmentNoticeAcceptance=()=>{};
   window.block='As inscrições iniciais desta turma começam em 02/02/2027 às 10:00. Aguarde a abertura para realizar a inscrição, inclusive com token de rematrícula.';
   window.token={id:1,pessoa_id:1,numero_token:'1234',nome_completo:'Pessoa fictícia',turma_id:524,turma_nome:'Turma 524',token_rematricula:1,exigir_token_inscricao:1,validade:'2027-02-16 10:00:00',inscricao_bloqueio_prazo:block};
   window.details={class:{id:524,nome:'Turma 524',exigir_token_inscricao:1,inscricao_bloqueio_prazo:block},people:[{id:1,nome_completo:'Pessoa fictícia',elegivel:true,elegivel_por_token:true,possui_token_ativo:true,documentacao_pendente:false}]};
  });
  await page.addScriptTag({content:extract('classSeasonLabel(','renderEnrollmentModal(')+extract('renderEnrollmentModal(','renderVacanciesModal(')+extract('showPendingToken(','openEnrollmentToken(').split('            const dismissedTokens')[0]});
  await page.evaluate(()=>showPendingToken(token));
  assert(await page.locator('[data-pending-token-enroll]').isDisabled());assert.match(await page.locator('#home-token-alert-modal').innerText(),/aguardando abertura/);assert.match(await page.locator('#home-token-alert-modal').innerText(),/02\/02\/2027 às 10:00/);
  // Abrir por uma notificação/URL também deve bloquear, mesmo com elegibilidade antiga no navegador.
  await page.evaluate(()=>{document.querySelector('#home-token-alert-modal').remove();renderEnrollmentModal(details,token);});
  assert(await page.locator('#home-course-enrollment-modal').isVisible());assert.equal(await page.locator('.home-course-enrollment-form').count(),0);
  assert.match(await page.locator('#home-course-enrollment-content').innerText(),/02\/02\/2027 às 10:00/);
  // Depois da abertura, o formulário volta ao fluxo normal de uso do token.
  await page.evaluate(()=>{details.class.inscricao_bloqueio_prazo=null;token.inscricao_bloqueio_prazo=null;renderEnrollmentModal(details,token);});
  assert(await page.getByRole('button',{name:'Confirmar inscrição',exact:true}).isEnabled());assert.equal(await page.locator('[name=token]').inputValue(),'1234');
  await page.evaluate(()=>{document.querySelector('#home-course-enrollment-modal').classList.add('hidden');showPendingToken(token);});
  assert(await page.locator('[data-pending-token-enroll]').isEnabled());
  await page.evaluate(()=>{token.token_rematricula=0;token.inscricao_bloqueio_prazo=block;showPendingToken(token);});
  assert(await page.locator('[data-pending-token-enroll]').isDisabled());
  await page.setViewportSize({width:390,height:844});assert(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth));
  assert.deepEqual(errors,[]);
  console.log('Tela de inscrição: alerta de abertura, tokens comuns/rematrícula, bloqueio do botão e do formulário antecipado, dados antigos de elegibilidade, fluxo após abertura e celular verificados.');
 }finally{await browser.close();}
})().catch(error=>{console.error(error);process.exitCode=1;});
