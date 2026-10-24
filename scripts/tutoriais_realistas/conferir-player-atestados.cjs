const fs = require('fs'), path = require('path'), assert = require('assert');
const {chromium} = require('C:/Users/Aberto/.cache/codex-runtimes/codex-primary-runtime/dependencies/node/node_modules/playwright');
(async()=>{
  const browser=await chromium.launch({executablePath:'C:/Program Files/Google/Chrome/Application/chrome.exe',headless:true});
  try {
    for(const slug of ['clinico','dermatologico']) {
      const folder=path.resolve(`output/tutorial-atestado-${slug}-realista`),page=await browser.newPage();
      await page.route('**/*',r=>{
        const pathname=decodeURIComponent(new URL(r.request().url()).pathname),file=path.resolve(folder,'.'+pathname);
        if(!file.startsWith(folder+path.sep)||!fs.existsSync(file))return r.fulfill({status:404,body:''});
        if(file.endsWith('.mp4')) {
          const buffer=fs.readFileSync(file),range=r.request().headers().range;
          if(range) {
            const match=/bytes=(\d+)-(\d*)/.exec(range);assert(match,range);
            const start=Number(match[1]),end=match[2]?Math.min(Number(match[2]),buffer.length-1):buffer.length-1;
            return r.fulfill({status:206,contentType:'video/mp4',headers:{'accept-ranges':'bytes','content-range':`bytes ${start}-${end}/${buffer.length}`},body:buffer.subarray(start,end+1)});
          }
          return r.fulfill({contentType:'video/mp4',headers:{'accept-ranges':'bytes'},body:buffer});
        }
        return r.fulfill({path:file});
      });
      await page.goto('http://player.local/assistir.html');
      await page.waitForFunction(()=>document.querySelector('video').readyState>=2);
      assert.equal(await page.locator('[data-time]').count(),13);
      for(const chapter of [0,4,8,12]) {
        const expected=Number(await page.locator('[data-time]').nth(chapter).getAttribute('data-time'));
        await page.locator('[data-time]').nth(chapter).click();
        await page.waitForFunction(expected=>{const v=document.querySelector('video');return !v.seeking&&v.currentTime>=expected&&v.currentTime<expected+3;},expected);
        await page.waitForTimeout(300);
        const state=await page.locator('video').evaluate(v=>({error:v.error?.message,time:v.currentTime,width:v.videoWidth,height:v.videoHeight,paused:v.paused}));
        assert(!state.error&&state.width===1600&&state.height===900&&!state.paused,JSON.stringify(state));
      }
      await page.screenshot({path:`tmp/tutorial-atestado-${slug}/player-final.png`});
      await page.close();console.log(slug+': reprodução e navegação por capítulos OK');
    }
  } finally {await browser.close();}
})().catch(e=>{console.error(e);process.exitCode=1;});
