const fs=require('fs'),path=require('path'),net=require('net'),assert=require('assert'),{spawn,execFileSync}=require('child_process');
const {chromium}=require('C:/Users/Aberto/.cache/codex-runtimes/codex-primary-runtime/dependencies/node/node_modules/playwright');
const root=path.resolve(__dirname,'..');
(async()=>{
 const dir=fs.mkdtempSync(path.join(root,'tmp/course-attendance-')),db=path.join(dir,'fixture.sqlite');execFileSync('php',['tests/course_attendance_chart_test.php','--database',db],{cwd:root});
 const port=await new Promise(resolve=>{const s=net.createServer();s.listen(0,'127.0.0.1',()=>{const p=s.address().port;s.close(()=>resolve(p));});});
 const server=spawn('php',['-d','session.save_path='+dir,'-S','127.0.0.1:'+port,'tests/fixtures/admin_charts_router.php'],{cwd:root,env:{...process.env,CHART_TEST_DATABASE:db},stdio:'ignore',windowsHide:true});let browser;
 try{
  const base='http://127.0.0.1:'+port;for(let i=0;i<80;i++){try{await fetch(base);break;}catch{await new Promise(r=>setTimeout(r,100));}}
  browser=await chromium.launch({executablePath:'C:/Program Files/Google/Chrome/Application/chrome.exe',headless:true});const page=await browser.newPage({viewport:{width:1366,height:1000}}),errors=[];page.on('pageerror',e=>errors.push(e.message));
  await page.goto(base+'/admin#admin-graficos');await page.locator('[data-attendance-stats] strong').first().waitFor();
  const wait=()=>page.waitForFunction(()=>window.jQuery&&jQuery.active===0);
  await page.locator('[name=modo]').selectOption('range');await wait();await page.waitForTimeout(500);await wait();
  const list=page.locator('[data-attendance-monitor-list]');
  assert(!(await list.evaluate(e=>e.open)));
  await list.locator(':scope > summary').click();
  const locations=page.locator('[data-attendance-location]');
  assert(await locations.count()>0);
  for(const location of await locations.all()){
   assert(!(await location.evaluate(e=>e.open)));
   await location.locator(':scope > summary').click();
  }
  assert.match(await page.locator('.attendance-monitor-row').first().innerText(), /Segunda-feira|Terça-feira|Quarta-feira|Quinta-feira|Sexta-feira|Sábado|Domingo/);
  assert.equal(await page.locator('[data-attendance-stats] strong').nth(0).innerText(),'6');assert.match(await page.locator('[data-attendance-monitor]').innerText(),/Chamada parcial/);assert.match(await page.locator('[data-attendance-monitor]').innerText(),/Chamada completa/);
  await page.locator('[data-attendance-state]').selectOption('parcial');await wait();assert.equal(await page.locator('.attendance-monitor-row').count(),1);
  await page.locator('[data-attendance-class]').selectOption('1');await wait();assert.match(await page.locator('[data-attendance-monitor]').innerText(),/Futsal <teste>/);assert.equal(await page.locator('[data-attendance-monitor] teste').count(),0);
  const end=await page.locator('[data-attendance-monitor-to]').inputValue(),start=new Date(end+'T12:00:00Z');start.setUTCDate(start.getUTCDate()-6);
  await page.locator('[data-attendance-monitor-from]').fill(start.toISOString().slice(0,10));await page.locator('[data-attendance-state]').selectOption('scheduled');await wait();await page.locator('[data-attendance-monitor-update]').click();await wait();
  assert.equal(await page.locator('.attendance-monitor-row').count(),7);assert.match(await page.locator('[data-attendance-monitor]').innerText(),/Chamada não iniciada/);
  await page.locator('[data-attendance-today-button]').click();await wait();assert.equal(await page.locator('.attendance-monitor-row').count(),1);
  await page.locator('[data-chart-theme-toggle]').click();assert.equal(await page.locator('[data-admin-charts]').getAttribute('data-chart-theme'),'dark');
  for(const width of [1366,820,640,390,320]){await page.setViewportSize({width,height:1000});await page.waitForTimeout(100);assert(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+1),'Overflow '+width);assert(!await page.locator('.attendance-trend-svg').evaluate(e=>e.outerHTML.includes('NaN')));}
  await page.screenshot({path:path.join(dir,'dark-mobile.png'),fullPage:true});
  await page.locator('.attendance-monitor-row').first().screenshot({path:path.join(dir,'dark-monitor.png')});
  await page.reload();await page.locator('[data-attendance-stats] strong').first().waitFor();assert.equal(await page.locator('[data-admin-charts]').getAttribute('data-chart-theme'),'dark');
  await page.locator('[data-chart-theme-toggle]').click();assert.equal(await page.locator('[data-admin-charts]').getAttribute('data-chart-theme'),'light');
  await page.locator('[data-attendance-from]').fill('2027-01-01');await page.locator('[data-attendance-update]').click();await page.locator('[data-chart-error]:not(.hidden)').waitFor();assert(await page.locator('[data-attendance-from]').isVisible());
  assert.deepEqual(errors,[]);console.log('OK: frequência, monitor de hoje, filtro de turma e chamada, XSS, tema persistido, datas e layout 320–1366px.');
 }finally{if(browser)await browser.close();server.kill();}
})().catch(e=>{console.error(e);process.exitCode=1;});
