const fs=require('fs'),path=require('path'),net=require('net'),assert=require('assert'),{spawn,execFileSync}=require('child_process');
const {chromium}=require('C:/Users/Aberto/.cache/codex-runtimes/codex-primary-runtime/dependencies/node/node_modules/playwright');
const root=path.resolve(__dirname,'..');
(async()=>{
 const directory=fs.mkdtempSync(path.join(root,'tmp/agenda-chart-')),database=path.join(directory,'fixture.sqlite');
 execFileSync('php',['tests/agenda_chart_service_test.php','--database',database],{cwd:root});
 const port=await new Promise(resolve=>{const s=net.createServer();s.listen(0,'127.0.0.1',()=>{const n=s.address().port;s.close(()=>resolve(n));});});
 const server=spawn('php',['-d','session.save_path='+directory,'-S','127.0.0.1:'+port,'tests/fixtures/admin_charts_router.php'],{cwd:root,env:{...process.env,CHART_TEST_DATABASE:database},windowsHide:true,stdio:'ignore'});
 let browser;
 try{
  const base='http://127.0.0.1:'+port,url=base+'/admin/graficos-agendamento/dados?from=2026-10-01&to=2026-10-31';
  for(let i=0;i<80;i++){try{await fetch(base);break;}catch{await new Promise(r=>setTimeout(r,100));}}
  for(const [role,status] of [['guest',401],['intern',403],['incomplete',403],['admin',200],['supervisor',200],['coordinator',200],['master_admin',200]]){
   const response=await fetch(url,{headers:{'X-Requested-With':'XMLHttpRequest','X-Test-Role':role}});assert.equal(response.status,status,role);
   if(status===200)assert.match(response.headers.get('cache-control'),/no-store/);
  }
  assert.equal((await fetch(url+'&period=invalid',{headers:{'X-Requested-With':'XMLHttpRequest'}})).status,422);
  browser=await chromium.launch({executablePath:'C:/Program Files/Google/Chrome/Application/chrome.exe',headless:true});
  const page=await browser.newPage({viewport:{width:1366,height:1000}}),errors=[];page.on('pageerror',e=>errors.push(e.message));
  await page.goto(base+'/admin#admin-graficos-agendamento');
  await page.locator('[data-agenda-stats] strong').first().waitFor();
  const form=page.locator('[data-agenda-form]');
  const refresh=async()=>{await form.locator('button[type=submit]').click();await page.waitForFunction(()=>document.querySelector('[data-agenda-charts]')?.getAttribute('aria-busy')===null);assert(await page.locator('[data-agenda-error]').isHidden());};
  await form.locator('[name=from]').fill('2026-10-01');await form.locator('[name=to]').fill('2026-10-31');await refresh();
  assert.equal(await page.locator('[data-agenda-stats] strong').nth(0).innerText(),'66');
  await page.locator('[data-chart-theme-toggle]').click();assert.equal(await page.locator('[data-agenda-charts]').getAttribute('data-chart-theme'),'dark');
  await page.locator('[data-chart-theme-toggle]').click();assert.equal(await page.locator('[data-agenda-charts]').getAttribute('data-chart-theme'),'light');
  await form.locator('[name=compare]').check();await refresh();assert(await page.locator('[data-agenda-comparison]').isVisible());
  await form.locator('[name="locations[]"]').selectOption('1');await form.locator('[name="modalities[]"]').selectOption('1');await refresh();
  assert.equal(await page.locator('[data-agenda-stats] strong').nth(0).innerText(),'42');
  await form.locator('[name="status"]').selectOption('falta');await refresh();assert.equal(await page.locator('[data-agenda-stats] strong').nth(11).innerText(),'1');
  await page.locator('[data-agenda-drill="time"]').first().click();await page.waitForFunction(()=>!document.querySelector('[data-agenda-charts]').hasAttribute('aria-busy'));
  assert.equal(await form.locator('[name=hour]').inputValue(),'08:00');assert(await page.locator('[data-agenda-details]').evaluate(e=>e.open));
  assert.equal(await page.locator('[data-agenda-detail-rows] teste').count(),0);assert.match(await page.locator('[data-agenda-detail-rows]').innerText(),/Natação <teste>/);
  for(const width of [1366,820,640,390,320]){await page.setViewportSize({width,height:1000});await page.waitForTimeout(150);assert(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+1),'Largura '+width);assert(!await page.locator('.agenda-trend-svg').evaluate(e=>e.outerHTML.includes('NaN')));}
  await page.screenshot({path:path.join(directory,'mobile.png'),fullPage:true});
  await form.locator('[name=type]').selectOption('special');await form.locator('[name=hour]').fill('');await refresh();
  assert(await page.locator('[data-agenda-special]').isVisible());assert.equal(await page.locator('[data-agenda-stats] strong').nth(4).innerText(),'—');
  await page.locator('[data-agenda-measure]').selectOption('present');assert.match(await page.locator('[data-agenda-rank="modality"]').innerText(),/—/);
  await form.locator('[name=from]').fill('2027-01-01');await form.locator('button[type=submit]').click();await page.locator('[data-agenda-error]').waitFor();assert(await page.locator('[data-agenda-results]').isHidden());
  assert.deepEqual(errors,[]);console.log('OK: permissões, filtros combinados, comparação, status, detalhamento, especiais, erro de intervalo, XSS e layout 320–1366px.');
 }finally{if(browser)await browser.close();server.kill();}
})().catch(error=>{console.error(error);process.exitCode=1;});
