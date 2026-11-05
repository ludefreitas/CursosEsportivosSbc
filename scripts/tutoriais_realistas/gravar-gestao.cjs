// Navegador isolado: somente assets locais e respostas simuladas, sem servidor/banco.
const fs=require('fs'),path=require('path'),assert=require('assert');
process.env.PLAYWRIGHT_BROWSERS_PATH=path.resolve('tmp/playwright-browsers');
const {chromium}=require('C:/Users/Aberto/.cache/codex-runtimes/codex-primary-runtime/dependencies/node/node_modules/playwright');
const kind=process.argv[2],fast=process.argv.includes('--preflight'),inspect=process.argv.includes('--inspect');
assert(['criar-temporada','criar-modalidade','criar-cronograma','criar-turma'].includes(kind));
const dir=path.resolve('tmp/tutorial-'+kind);fs.mkdirSync(dir,{recursive:true});
const base=path.resolve('tmp/tutorial-gestao'),read=n=>fs.readFileSync(path.join(base,n+'.html'),'utf8');
let browser;
(async()=>{
browser=await chromium.launch({executablePath:'C:/Program Files/Google/Chrome/Application/chrome.exe',headless:true});
const context=await browser.newContext({viewport:{width:1600,height:900},locale:'pt-BR',...((fast||inspect)?{}:{recordVideo:{dir,size:{width:1600,height:900}}})});
const page=await context.newPage(),errors=[],blocked=[],posts=[];page.on('pageerror',e=>errors.push(e.message));page.setDefaultTimeout(14000);
const begin=Date.now();await page.route('**/*',async route=>{const req=route.request(),u=new URL(req.url()),p=u.pathname;
 if(p.includes('jquery-3.7.1.min.js'))return route.fulfill({path:path.resolve('tmp/tutorial-realista/jquery.min.js'),contentType:'application/javascript'});
 if(u.hostname!=='tutorial.local')return route.fulfill({body:'',contentType:'application/javascript'});
 if(p==='/admin'&&req.method()==='GET')return route.fulfill({body:read('admin'),contentType:'text/html; charset=utf-8'});
 if(p.startsWith('/assets/')){const f=path.resolve('public'+p);if(f.startsWith(path.resolve('public')+path.sep)&&fs.existsSync(f)&&fs.statSync(f).isFile())return route.fulfill({path:f});}
 if(p==='/admin/secao')return route.fulfill({json:{success:true,html:read(u.searchParams.get('nome'))}});
 if(p==='/admin/turmas/filtro')return route.fulfill({json:{success:true,items:[],html:'',total:0}});
 if(p==='/admin/modalidades/lista')return route.fulfill({json:{success:true,html:'<tr><td>Alongamento demonstrativo — Terrestre — Ativa</td></tr>'}});
 if(req.method()==='POST'&&['/admin/temporadas','/admin/turmas','/admin/modalidades','/admin/modalidades/cronogramas','/admin/modalidades/cronograma'].includes(p)){posts.push({path:p,body:req.postData()});return route.fulfill({json:{success:true,message:kind==='criar-temporada'?'Temporada criada com sucesso.':kind==='criar-modalidade'?'Modalidade criada com sucesso.':kind==='criar-cronograma'?'Cronograma salvo com sucesso.':'Turma criada com sucesso.',html:read(kind==='criar-temporada'?'temporadas':kind==='criar-turma'?'turmas':'modalidades'),modalities:[],rows_html:'<tr><td>Alongamento demonstrativo — criação simulada</td></tr>'}});}
 if(p.includes('notificac')||p.includes('online')||p.includes('class-copy')||p.includes('copia'))return route.fulfill({json:{success:true,items:[],total:0,notifications:[]}});
 blocked.push(req.method()+' '+p);return route.fulfill({status:404,json:{success:false,message:'Requisição não prevista na demonstração.'}});
});
await page.goto('http://tutorial.local/admin',{waitUntil:'networkidle'});
await page.addStyleTag({content:`body{padding-top:62px!important}.popup-overlay{padding-top:66px!important;padding-bottom:100px!important}.popup-admin-card{max-height:calc(100vh - 175px)!important}.demo-head{position:fixed;inset:0 0 auto;height:55px;background:#123f52;color:white;z-index:2147483646;padding:15px 24px;font:700 22px Arial;display:flex;justify-content:space-between;pointer-events:none}.demo-head small{font:14px Arial}.demo-caption{position:fixed;bottom:12px;left:22px;right:22px;z-index:2147483646;background:#103444f5;color:white;padding:17px 24px;border-radius:12px;font:22px/1.35 Arial;pointer-events:none}.demo-focus{outline:4px solid #ffba24!important;outline-offset:4px!important}`});
await page.evaluate(()=>document.body.insertAdjacentHTML('beforeend','<div class="demo-head"><span id="demo-title">Tutorial de gestão</span><small>Dados fictícios · salvamento simulado</small></div><div class="demo-caption" id="demo-caption"></div>'));
const sleep=ms=>page.waitForTimeout(fast||inspect?30:ms);
async function aim(sel){const e=page.locator(sel).first();await e.waitFor({state:'visible'});await e.evaluate(e=>e.scrollIntoView({block:'center'}));await page.evaluate(()=>document.querySelectorAll('.demo-focus').forEach(e=>e.classList.remove('demo-focus')));await e.evaluate(e=>e.classList.add('demo-focus'));await sleep(500);return e;}
async function click(sel){await(await aim(sel)).click();await sleep(350);}
async function field(form,name,value){const e=await aim(form+' [name="'+name+'"]');const tag=await e.evaluate(e=>e.tagName);if(tag==='SELECT')await e.selectOption(value);else await e.fill(value);await e.dispatchEvent('change');await e.press('Tab');await sleep(250);}
const section=kind==='criar-temporada'?'temporadas':kind==='criar-turma'?'turmas':'modalidades';
const form=kind==='criar-temporada'?'[data-course-form="season"]':kind==='criar-turma'?'[data-course-form="class"]':kind==='criar-modalidade'?'#admin-modality-form':'#admin-modality-schedule-form';
const open=kind==='criar-temporada'?'[data-course-create="season"]':kind==='criar-turma'?'[data-course-create="class"]':kind==='criar-modalidade'?'#admin-modality-create':'#admin-modality-schedule-create';
const navigate=async()=>{await click('[data-admin-nav-target="'+section+'"]');if(inspect)console.log('NAV',await page.locator('#popup-texto').innerText(),blocked,errors);};
if(inspect){await navigate();await click(open);console.log(await page.locator(form).evaluate(e=>Array.from(e.querySelectorAll('input,select,textarea')).map(x=>({name:x.name,type:x.type,required:x.required,visible:!!x.getClientRects().length,value:x.value,options:x.tagName==='SELECT'?Array.from(x.options).map(o=>[o.value,o.text]):undefined}))));await page.screenshot({path:path.join(dir,'inspect.png')});console.log({errors,blocked});await browser.close();return;}
const scenes=JSON.parse(fs.readFileSync('scripts/tutoriais_realistas/'+kind+'-cenas.json','utf8'));
const actions={inicio:()=>aim('.admin-nav-card'),navegar:navigate,abrir:()=>click(open)};
for(const scene of scenes)if(scene.fields)actions[scene.id]=async()=>{for(const [name,value] of scene.fields)await field(form,name,value);};else if(scene.selector)actions[scene.id]=()=>aim(scene.selector.replaceAll('$form',form));
actions.copiar=async()=>{await click('#admin-modality-schedule-use-season');await page.waitForSelector('#popup-mensagem:not(.hidden)');await sleep(1200);await click('#popup-fechar');};
actions.dias=async()=>{await click(form+' [name="dias_semana[]"][value="2"]');await click(form+' [name="dias_semana[]"][value="4"]');};
actions.salvar=async()=>{await click(form+' button[type="submit"]');await page.waitForSelector('#popup-mensagem:not(.hidden)');assert.equal(posts.length,1,JSON.stringify({posts,blocked,message:await page.locator('#popup-texto').innerText()}));await aim('#popup-texto');};
actions.fim=async()=>{await aim('#popup-texto');};
const zero=Date.now(),timeline=[];
function wavDuration(file){const b=fs.readFileSync(file);let rate,size;for(let p=12;p+8<b.length;){const n=b.readUInt32LE(p+4);if(b.toString('ascii',p,p+4)==='fmt ')rate=b.readUInt32LE(p+16);if(b.toString('ascii',p,p+4)==='data'){size=n;break;}p+=8+n+n%2;}assert(rate&&size);return size/rate;}
for(let i=0;i<scenes.length;i++){const s=scenes[i],start=Date.now();await page.evaluate(s=>{document.querySelector('#demo-title').textContent=s.title;document.querySelector('#demo-caption').textContent=s.caption;},s);console.log(kind+' '+s.id);assert(actions[s.id],s.id);await actions[s.id]();const audioDuration=wavDuration(path.join(dir,s.id+'.wav'));await sleep(Math.max(800,audioDuration*1000+1100-(Date.now()-start)));await page.screenshot({path:path.join(dir,'cena-'+i+'.png')});timeline.push({...s,start:(start-zero)/1000,end:(Date.now()-zero)/1000,audioDuration});}
assert.deepEqual(errors,[]);assert.deepEqual(blocked,[]);fs.writeFileSync(path.join(dir,fast?'preflight.json':'timeline.json'),JSON.stringify({scenes:timeline,lead:(zero-begin)/1000,posts,errors,blocked,simulation:true},null,2));if(!fast){const video=page.video();await context.close();fs.writeFileSync(path.join(dir,'video-path.txt'),await video.path());}else await context.close();await browser.close();console.log('OK '+kind);
})().catch(async e=>{console.error(e);if(browser)await browser.close();process.exitCode=1;});
