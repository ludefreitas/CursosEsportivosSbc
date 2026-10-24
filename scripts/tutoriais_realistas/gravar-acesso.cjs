process.env.PLAYWRIGHT_BROWSERS_PATH=require('path').join(process.cwd(),'tmp/playwright-browsers');
const fs=require('fs'),path=require('path'),{chromium}=require('C:/Users/Aberto/.cache/codex-runtimes/codex-primary-runtime/dependencies/node/node_modules/playwright');
const type=process.argv[2]||'login',fast=process.argv.includes('--preflight'),TMP=path.join(process.cwd(),'tmp/tutorial-'+type),scenes=JSON.parse(fs.readFileSync('scripts/tutoriais_realistas/'+type+'-cenas.json','utf8').replace(/^\uFEFF/,''));
function wavDuration(file){const b=fs.readFileSync(file);let rate=0,size=0;for(let p=12;p+8<b.length;){const id=b.toString('ascii',p,p+4),n=b.readUInt32LE(p+4);if(id==='fmt ')rate=b.readUInt32LE(p+16);if(id==='data'){size=n;break;}p+=8+n+n%2;}return size/rate;}
let browser;
(async()=>{
browser=await chromium.launch({executablePath:'C:/Program Files/Google/Chrome/Application/chrome.exe',headless:true,args:['--host-resolver-rules=MAP cursosesportivos.com.br 127.0.0.1']});
const context=await browser.newContext({viewport:{width:1600,height:900},...(fast?{}:{recordVideo:{dir:TMP,size:{width:1600,height:900}}})});
const videoBegin=Date.now(),page=await context.newPage();await page.route('**/jquery-3.7.1.min.js',r=>r.fulfill({contentType:'application/javascript',body:fs.readFileSync('tmp/tutorial-realista/jquery.min.js','utf8')}));let loginPosts=0,dependentPosts=0,recoverPosts=0;const errors=[];page.on('pageerror',e=>errors.push(e.message));
await page.goto('http://cursosesportivos.com.br/',{waitUntil:'networkidle'});const homeHtml=await page.content();
const dash=homeHtml.replace(/<main class="page-content">[\s\S]*?<\/main>/,'<main class="page-content">'+fs.readFileSync('tmp/tutorial-realista/dashboard.html','utf8').replace(/^\uFEFF/,'')+'</main>').replace('pagina-home','pagina-dashboard');
await page.route('**/*',async route=>{const r=route.request(),u=new URL(r.url()),p=u.pathname;
if(r.url().includes('jquery-3.7.1.min.js'))return route.fulfill({contentType:'application/javascript',body:fs.readFileSync('tmp/tutorial-realista/jquery.min.js','utf8')});
if(p==='/login'&&r.method()==='POST'){loginPosts++;return route.fulfill({json:type==='login'&&loginPosts===1?{success:false,message:'CPF ou senha inválidos.'}:{success:true,message:'Login realizado com sucesso.',redirect:'/dashboard'}});}
if(p==='/recuperar-senha'&&r.method()==='POST'){recoverPosts++;return route.fulfill({json:{success:true,message:'Senha alterada com sucesso. Você já pode entrar usando a nova senha.'}});}
if(p.startsWith('/assets/')&&/\.(css|js)$/.test(p)){const f=path.join(process.cwd(),'public',p);if(fs.existsSync(f))return route.fulfill({contentType:p.endsWith('.css')?'text/css':'application/javascript',body:fs.readFileSync(f)});}

if(p==='/dashboard')return route.fulfill({contentType:'text/html; charset=utf-8',body:dash});
if(p==='/dependentes/salvar'&&r.method()==='POST'){dependentPosts++;return route.fulfill({json:dependentPosts===1?{success:false,message:'O telefone do contato de emergência deve ser diferente do WhatsApp do usuário responsável.'}:{success:true,message:'Dependente salvo com sucesso.',row_html:fs.readFileSync('tmp/tutorial-realista/dependente-linha.html','utf8'),option_html:'<option value="999998">Lucas Oliveira Santos</option>'}});}
if(p==='/avaliacoes/modalidade')return route.fulfill({json:{success:true,evaluations:[],certificates:[]}});
if(p==='/api/pessoas-externas/registros')return route.fulfill({json:{success:true,registros:[]}});
if(p==='/api/cpf/cadastro-status')return route.fulfill({json:{success:true,status:'disponivel',pode_criar_conta:true,mensagem_helper:'CPF disponível.'}});
if(p==='/api/ceps/validar')return route.fulfill({json:{success:true,permitido:true,aceito:true,mensagem:"CEP de São Bernardo do Campo aceito."}});
if(p==='/api/ceps/endereco')return route.fulfill({json:{success:true,address:{cep:'09710000',logradouro:'Rua Marechal Deodoro',bairro:'Centro',cidade:'São Bernardo do Campo',uf:'SP'}}});
if(r.method()!=='GET'&&r.method()!=='HEAD')return route.fulfill({status:403,json:{success:false,message:'Demonstração: gravação desativada.'}});
return route.continue();});
let activeScene=scenes[0];
const css='body{padding-top:60px!important}.popup-overlay{padding-top:65px!important;padding-bottom:95px!important}.popup-card{max-height:calc(100dvh - 170px)!important}.popup-route-card{max-width:1000px!important}.popup-card{display:flex!important;flex-direction:column!important}.popup-body{overflow-y:auto!important;min-height:0!important;max-height:calc(100dvh - 335px)!important}.demo-heading{pointer-events:none;position:fixed;top:0;left:0;right:0;height:55px;background:#113f52;color:white;padding:15px 25px;box-sizing:border-box;font:700 22px Arial;z-index:2147483646;display:flex;justify-content:space-between}.demo-heading small{font:15px Arial}.demo-caption{position:fixed;bottom:8px;left:20px;right:20px;z-index:2147483646;background:#103444f5;color:white;padding:15px 24px;border-radius:12px;font:21px/1.3 Arial;pointer-events:none}.demo-pointer{position:fixed;width:27px;height:27px;border-radius:50%;border:3px solid #ffb922;background:#ffb92244;z-index:2147483647;pointer-events:none;transition:left .5s,top .5s;box-shadow:0 0 0 5px #fff8}.demo-click{background:#ffb922}.demo-focus{outline:4px solid #ffba29!important;outline-offset:4px!important}';
async function decorate(){await page.addStyleTag({content:css});await page.evaluate(()=>{document.querySelectorAll('.demo-heading,.demo-caption,.demo-pointer').forEach(e=>e.remove());document.body.insertAdjacentHTML('beforeend','<div class="demo-heading"><span id="demo-title"></span><small>Demonstração local · dados fictícios</small></div><div class="demo-caption" id="demo-caption"></div><div class="demo-pointer" id="demo-pointer"></div>');});await page.evaluate(s=>{document.querySelector('#demo-title').textContent=s.title;document.querySelector('#demo-caption').textContent=s.speech;},activeScene);}
await page.goto('http://cursosesportivos.com.br/',{waitUntil:'networkidle'});if(await page.locator('#home-course-cpf-modal:not(.hidden)').count())await page.locator('[data-home-cpf-close="1"]').first().click();await decorate();
const sleep=ms=>page.waitForTimeout(fast?Math.min(ms,50):ms);
 async function aim(selector){const element=page.locator(selector).first();await element.waitFor({state:'visible'});await element.evaluate(node=>node.scrollIntoView({behavior:'smooth',block:'center'}));await sleep(650);await page.evaluate(()=>document.querySelectorAll('.demo-focus').forEach(node=>node.classList.remove('demo-focus')));await element.evaluate(node=>node.classList.add('demo-focus'));const box=await element.boundingBox();await page.evaluate(({x,y})=>{const pointer=document.querySelector('#demo-pointer');pointer.style.left=x+'px';pointer.style.top=y+'px';},{x:box.x+Math.min(box.width/2,140),y:box.y+box.height/2});await sleep(650);return element;}
 async function click(selector){const element=await aim(selector);await page.locator('#demo-pointer').evaluate(node=>node.classList.add('demo-click'));await element.click();await sleep(250);if(await page.locator('#demo-pointer').count())await page.locator('#demo-pointer').evaluate(node=>node.classList.remove('demo-click'));}
 async function fill(selector,value){const element=await aim(selector);await element.click();await element.fill('');if(fast&&!process.argv.includes('--typed-preflight'))await element.fill(value);else await element.pressSequentially(value,{delay:60});await element.press('Tab');await sleep(400);}
const form='#popup-route-modal form',input=name=>form+' [name="'+name+'"]';const timeline=[];const videoZero=Date.now();
async function scene(i,action){const sc=scenes[i],start=Date.now();activeScene=sc;await page.evaluate(s=>{document.querySelector('#demo-title').textContent=s.title;document.querySelector('#demo-caption').textContent=s.speech;},sc);console.log(type+' '+i+': '+sc.title);await action();const duration=wavDuration(path.join(TMP,sc.id+'.wav'));await sleep(Math.max(600,duration*1000+1000-(Date.now()-start)));await page.screenshot({path:path.join(TMP,'cena-'+i+'.png')});timeline.push({...sc,start:(start-videoZero)/1000,end:(Date.now()-videoZero)/1000,audioDuration:duration});}
const openLogin=async()=>{if(await page.locator('#popup-route-modal form[action$="/login"]:visible').count())return aim('#popup-route-modal form');await click('nav a[href*="/login"]');};
async function credentials(password){await fill(input('cpf'),'52998224725');await fill(input('password'),password);await click(form+' button[type=submit]');await page.waitForSelector('#popup-mensagem:not(.hidden)');}
async function dashboard(){await click('#popup-fechar');await page.waitForURL('**/dashboard');await page.waitForLoadState('networkidle');await decorate();}
if(type==='login'){
await scene(0,openLogin);
await scene(1,()=>credentials('SenhaIncorreta'));
await scene(2,()=>aim('#popup-texto'));
await scene(3,async()=>{await click('#popup-fechar');await click('#popup-route-modal a[href*="/recuperar-senha"]');});
await scene(4,async()=>{await fill(input('cpf'),'52998224725');await fill(input('birth_date'),'15051990');});
await scene(5,async()=>{await fill(input('password'),'NovaSenha!2026');await fill(input('password_confirmation'),'NovaSenha!2026');});
await scene(6,async()=>{await click(input('human_verification'));await click(form+' button[type=submit]');await page.waitForSelector('#popup-mensagem:not(.hidden)');});
await scene(7,async()=>{await aim('#popup-texto');await sleep(1800);await click('#popup-fechar');await openLogin();});
await scene(8,()=>credentials('NovaSenha!2026'));
await scene(9,async()=>{await aim('#popup-texto');await sleep(1000);await dashboard();});
await scene(10,()=>aim('.dashboard-hero'));
}else{
await scene(0,openLogin);
await scene(1,()=>credentials('Exemplo!2026'));
await scene(2,dashboard);
await scene(3,async()=>{await click('[data-open-dependent-create-modal="1"]');});
await scene(4,()=>aim('#dashboard-dependent-create-modal .dashboard-dependent-attention'));
const f='#dashboard-dependent-create-form',d=name=>f+' [name="'+name+'"]';
await scene(5,async()=>{await fill(d('full_name'),'Lucas Oliveira Santos');await fill(d('cpf'),'11144477735');const el=await aim(d('birth_date'));await el.fill('2015-06-15');await el.press('Tab');});
await scene(6,async()=>{const el=await aim(d('sexo'));await el.selectOption('masculino');await aim(d('phone_whatsapp'));await sleep(1200);await fill(d('email'),'mariana.exemplo@example.com');});
await scene(7,async()=>{await aim(d('numero_cartao_sus'));await sleep(1300);await aim('#dashboard-dependent-create-modal [data-condition-helper="1"]');});
await scene(8,async()=>{await fill(d('zip_code'),'09710000');await sleep(1000);if(await page.locator('.cep-address-option:visible').count())await click('.cep-address-option:visible');for(const[n,v]of Object.entries({street:'Rua Marechal Deodoro',address_number:'100',address_complement:'Apto 12',neighborhood:'Centro',city:'São Bernardo do Campo',state:'SP'})){if(!(await page.locator(d(n)).inputValue()))await fill(d(n),v);}});
await scene(9,async()=>{await fill(d('responsavel1_nome'),'Mariana Oliveira Santos');await fill(d('responsavel1_cpf'),'52998224725');});
await scene(10,async()=>{await fill(d('emergency_contact_name'),'Ana Oliveira Santos');await fill(d('emergency_contact_phone'),'11987654321');await click('button[form="dashboard-dependent-create-form"][type=submit]');await page.waitForTimeout(500);await page.screenshot({path:path.join(TMP,'submit-debug.png')});console.log('VALIDATION',await page.locator('#dashboard-dependent-create-form :invalid').evaluateAll(es=>es.map(e=>({name:e.name,value:e.value,error:e.validationMessage}))));await page.waitForSelector('#popup-mensagem:not(.hidden)');});
await scene(11,async()=>{await aim('#popup-texto');await sleep(1500);await click('#popup-fechar');await fill(d('emergency_contact_phone'),'11976543210');});
await scene(12,async()=>{await click('button[form="dashboard-dependent-create-form"][type=submit]');await page.waitForSelector('#popup-mensagem:not(.hidden)');});
await scene(13,async()=>{await aim('#popup-texto');await sleep(1200);await click('#popup-fechar');});
await scene(14,()=>aim('section.dashboard-main-grid .data-table'));
}
fs.writeFileSync(path.join(TMP,fast?'preflight.json':'timeline.json'),JSON.stringify({scenes:timeline,lead:(videoZero-videoBegin)/1000,loginPosts,dependentPosts,recoverPosts,errors},null,2));if(!fast){const video=page.video();await context.close();fs.writeFileSync(path.join(TMP,'video-path.txt'),await video.path());}else await context.close();await browser.close();console.log('Concluído: '+JSON.stringify({loginPosts,dependentPosts,recoverPosts,errors}));
})().catch(async e=>{console.error(e);if(browser)await browser.close();process.exitCode=1;});
