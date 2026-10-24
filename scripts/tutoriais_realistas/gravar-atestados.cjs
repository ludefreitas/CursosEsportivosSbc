// Grava apenas fixtures locais renderizadas das views reais; nenhuma requisição chega ao banco.
const fs = require('fs'), path = require('path'), assert = require('assert');
process.env.PLAYWRIGHT_BROWSERS_PATH = path.join(process.cwd(), 'tmp/playwright-browsers');
const {chromium} = require('C:/Users/Aberto/.cache/codex-runtimes/codex-primary-runtime/dependencies/node/node_modules/playwright');
const slug = process.argv[2] || 'clinico';
assert(['clinico', 'dermatologico'].includes(slug));
const fast = process.argv.includes('--preflight');
const tmp = path.resolve('tmp/tutorial-atestado-' + slug);
const fixture = path.resolve('tmp/tutorial-atestados');
const scenes = JSON.parse(fs.readFileSync(`scripts/tutoriais_realistas/atestado-${slug}-cenas.json`, 'utf8'));
function duration(file) {
  const b = fs.readFileSync(file); let rate = 0, size = 0;
  for (let p = 12; p + 8 < b.length;) {
    const id = b.toString('ascii', p, p + 4), n = b.readUInt32LE(p + 4);
    if (id === 'fmt ') rate = b.readUInt32LE(p + 16);
    if (id === 'data') {size = n; break;}
    p += 8 + n + n % 2;
  }
  assert(rate && size); return size / rate;
}
let browser;
(async () => {
  browser = await chromium.launch({executablePath:'C:/Program Files/Google/Chrome/Application/chrome.exe', headless:true});
  const context = await browser.newContext({viewport:{width:1600,height:900}, locale:'pt-BR', ...(fast ? {} : {recordVideo:{dir:tmp,size:{width:1600,height:900}}})});
  const videoBegin = Date.now(), page = await context.newPage(), errors = [], blocked = [];
  let saved = false, posts = 0;
  page.on('pageerror', e => errors.push(e.message));
  await page.route('**/*', async route => {
    const r = route.request(), u = new URL(r.url()), p = u.pathname;
    if (p.includes('jquery-3.7.1.min.js')) return route.fulfill({contentType:'application/javascript',body:fs.readFileSync('tmp/tutorial-realista/jquery.min.js')});
    if (u.hostname !== 'tutorial.local') return route.fulfill({body:'',contentType:'application/javascript'});
    if (p === '/dashboard') return route.fulfill({contentType:'text/html; charset=utf-8',body:fs.readFileSync(path.join(fixture,'dashboard.html'))});
    if (p.startsWith('/assets/')) {
      const file = path.resolve('public' + p);
      if (file.startsWith(path.resolve('public') + path.sep) && fs.existsSync(file) && fs.statSync(file).isFile()) return route.fulfill({path:file});
    }
    if (p === '/perfil/atestados/modal') return route.fulfill({json:{success:true,html:fs.readFileSync(path.join(fixture,`modal-${saved ? slug : 'inicial'}.html`),'utf8')}});
    if (p === '/perfil/atestados/salvar' && r.method() === 'POST') {
      const body = r.postDataBuffer().toString('utf8');
      assert(body.includes(`name="${slug}_arquivo"`));
      assert(body.includes(`atestado-${slug}-exemplo.pdf`));
      assert(body.includes('2026-10-20') && body.includes('CRM 123456'));
      saved = true; posts++;
      return route.fulfill({json:{success:true,message:'Atestados atualizados com sucesso.',html:fs.readFileSync(path.join(fixture,`modal-${slug}.html`),'utf8')}});
    }
    if (p === '/avaliacoes/modalidade') return route.fulfill({json:{success:true,evaluations:[],certificates:[]}});
    if (p === '/api/pessoas-externas/registros') return route.fulfill({json:{success:true,registros:[]}});
    blocked.push(r.method()+' '+p);
    return route.fulfill({status:404,json:{success:false,message:'Demonstração local: rota desativada.'}});
  });
  await page.goto('http://tutorial.local/dashboard',{waitUntil:'networkidle'});
  await page.addStyleTag({content:`body{padding-top:60px!important}.popup-overlay{padding-top:65px!important;padding-bottom:110px!important}.popup-card{max-height:calc(100dvh - 180px)!important}.popup-card{display:flex!important;flex-direction:column!important}.popup-body{overflow-y:auto!important;min-height:0!important;max-height:calc(100dvh - 335px)!important}.demo-heading{pointer-events:none;position:fixed;top:0;left:0;right:0;height:55px;background:#113f52;color:white;padding:15px 25px;box-sizing:border-box;font:700 22px Arial;z-index:2147483646;display:flex;justify-content:space-between}.demo-heading small{font:15px Arial}.demo-caption{position:fixed;bottom:8px;left:20px;right:20px;z-index:2147483646;background:#103444f5;color:white;padding:13px 24px;border-radius:12px;font:20px/1.3 Arial;pointer-events:none}.demo-pointer{position:fixed;width:27px;height:27px;border-radius:50%;border:3px solid #ffb922;background:#ffb92244;z-index:2147483647;pointer-events:none;transition:left .5s,top .5s;box-shadow:0 0 0 5px #fff8}.demo-click{background:#ffb922}.demo-focus{outline:4px solid #ffba29!important;outline-offset:4px!important}`});
  await page.evaluate(() => document.body.insertAdjacentHTML('beforeend','<div class="demo-heading"><span id="demo-title"></span><small>Demonstração local · dados fictícios · envio simulado</small></div><div class="demo-caption" id="demo-caption"></div><div class="demo-pointer" id="demo-pointer"></div>'));
  const sleep = ms => page.waitForTimeout(fast ? Math.min(ms,50) : ms);
  async function aim(selector) {
    const el = page.locator(selector).first(); await el.waitFor({state:'visible'});
    await el.evaluate(e => e.scrollIntoView({behavior:'smooth',block:'center'})); await sleep(700);
    await page.evaluate(() => document.querySelectorAll('.demo-focus').forEach(e => e.classList.remove('demo-focus')));
    await el.evaluate(e => e.classList.add('demo-focus'));
    const b = await el.boundingBox();
    await page.evaluate(({x,y}) => {const p=document.querySelector('#demo-pointer');p.style.left=x+'px';p.style.top=y+'px';},{x:b.x+Math.min(b.width/2,140),y:b.y+b.height/2});
    await sleep(650); return el;
  }
  async function click(selector) {const el=await aim(selector);await page.locator('#demo-pointer').evaluate(e=>e.classList.add('demo-click'));await el.click();await sleep(350);await page.locator('#demo-pointer').evaluate(e=>e.classList.remove('demo-click'));}
  async function fill(selector, value) {const el=await aim(selector);await el.click();await el.fill('');if(fast)await el.fill(value);else await el.pressSequentially(value,{delay:90});await el.press('Tab');await sleep(400);}
  const timeline=[],videoZero=Date.now();
  async function scene(i,action) {
    const sc=scenes[i],start=Date.now();
    await page.evaluate(s=>{document.querySelector('#demo-title').textContent=s.title;document.querySelector('#demo-caption').textContent=s.speech;},sc);
    console.log(slug+' '+i+': '+sc.title);await action();
    const audioDuration=duration(path.join(tmp,sc.id+'.wav'));
    await sleep(Math.max(800,audioDuration*1000+1000-(Date.now()-start)));
    await page.screenshot({path:path.join(tmp,`cena-${i}.png`)});
    timeline.push({...sc,start:(start-videoZero)/1000,end:(Date.now()-videoZero)/1000,audioDuration});
  }
  const section=`[data-health-certificate-section="${slug}"]`, field=n=>section+` [name="${slug}_${n}"]`;
  const button=`#documentacao-atestado-999998-${slug}`;
  await scene(0,()=>aim('.dashboard-hero'));
  await scene(1,()=>aim('#dependente-999998 td:first-child'));
  await scene(2,async()=>{await click(button);await page.waitForSelector('#dashboard-health-certificates-modal:not(.hidden)');});
  await scene(3,()=>aim('[data-health-certificate-guidance="1"]'));
  await scene(4,async()=>{const el=await aim(field('data_emissao'));await el.fill('2026-10-20');await el.press('Tab');});
  await scene(5,()=>fill(field('crm_medico'),'CRM 123456'));
  await scene(6,async()=>{
    const el=await aim(field('local_atendimento'));await el.focus();
    // Abrir o menu nativo e percorrer as opções antes de confirmar a escolha.
    await el.press('Space');await sleep(1800);await el.press('ArrowDown');await sleep(1000);await el.press('Enter');
    await el.selectOption('servico_publico');await el.press('Tab');
  });
  await scene(7,()=>fill(field('observacoes'),'Exemplo de envio para demonstração.'));
  await scene(8,async()=>{await aim(field('arquivo'));await sleep(2200);await page.locator(field('arquivo')).setInputFiles({name:`atestado-${slug}-exemplo.pdf`,mimeType:'application/pdf',buffer:fs.readFileSync('tmp/certificado-nivel.pdf')});});
  await scene(9,async()=>{assert.equal(await page.locator(field('arquivo')).evaluate(e=>e.files[0].name),`atestado-${slug}-exemplo.pdf`);await aim(field('arquivo'));await sleep(1200);await aim(field('data_emissao'));await sleep(1200);await aim(field('crm_medico'));});
  await scene(10,async()=>{await click('button[form="dashboard-health-certificate-form"][type="submit"]');await page.waitForSelector('#popup-mensagem:not(.hidden)');});
  await scene(11,async()=>{await aim('#popup-texto');assert.match(await page.locator('#popup-texto').innerText(),/Atestados atualizados com sucesso/);await sleep(1600);await click('#popup-fechar');});
  await scene(12,async()=>{await aim(section+' .dashboard-health-status-badge');assert.match(await page.locator(section+' .dashboard-health-status-badge').innerText(),/Pendente de validação/);});
  assert.equal(posts,1);assert.deepEqual(errors,[]);assert.deepEqual(blocked,[]);
  fs.writeFileSync(path.join(tmp,fast?'preflight.json':'timeline.json'),JSON.stringify({scenes:timeline,lead:(videoZero-videoBegin)/1000,posts,errors,blocked,simulation:true},null,2));
  if(!fast){const video=page.video();await context.close();fs.writeFileSync(path.join(tmp,'video-path.txt'),await video.path());}else await context.close();
  await browser.close();console.log('Concluído: '+JSON.stringify({posts,errors,blocked}));
})().catch(async e=>{console.error(e);if(browser)await browser.close();process.exitCode=1;});
