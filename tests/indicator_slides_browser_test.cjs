const fs = require('node:fs'), path = require('node:path'), net = require('node:net'), assert = require('node:assert/strict');
const { spawn, execFileSync } = require('node:child_process');
const { chromium } = require('C:/Users/Aberto/.cache/codex-runtimes/codex-primary-runtime/dependencies/node/node_modules/playwright');
const root = path.resolve(__dirname, '..');
(async () => {
    const directory = fs.mkdtempSync(path.join(root, 'tmp/indicator-slides-'));
    const browser = await chromium.launch({ executablePath: 'C:/Program Files/Google/Chrome/Application/chrome.exe', headless: true });
    try {
        for (const domain of ['turmas', 'agenda']) {
            const database = path.join(directory, domain + '.sqlite');
            execFileSync('php', ['tests/' + (domain === 'turmas' ? 'admin' : 'agenda') + '_chart_service_test.php', '--database', database], { cwd: root, windowsHide: true });
            const port = await new Promise(resolve => { const s = net.createServer(); s.listen(0, '127.0.0.1', () => { const p = s.address().port; s.close(() => resolve(p)); }); });
            const server = spawn('php', ['-d', 'session.save_path=' + directory, '-S', '127.0.0.1:' + port, 'tests/fixtures/admin_charts_router.php'], { cwd: root, env: { ...process.env, CHART_TEST_DATABASE: database }, stdio: 'ignore', windowsHide: true });
            const page = await browser.newPage({ viewport: { width: 1440, height: 1000 } });
            const errors = [];
            page.on('pageerror', error => { errors.push(error.message); console.error(domain + ': ' + error.stack); });
            try {
                const base = 'http://127.0.0.1:' + port;
                for (let n = 0; n < 80; n++) { try { await fetch(base); break; } catch { await new Promise(resolve => setTimeout(resolve, 100)); } }
                await page.goto(base + '/admin#admin-' + (domain === 'turmas' ? 'graficos' : 'graficos-agendamento'));
                const deck = page.locator('[data-indicator-slides]');
                await deck.waitFor();
                if (domain === 'agenda') {
                    await page.locator('.people-chart-filters > summary').click();
                    const form = page.locator('[data-agenda-form]');
                    await form.locator('[name=from]').fill('2026-10-01');
                    await form.locator('[name=to]').fill('2026-10-31');
                    await form.locator('[name=compare]').check();
                    await form.locator('[type=submit]').click();
                    await page.waitForFunction(() => !document.querySelector('[data-agenda-charts]').hasAttribute('aria-busy'));
                }
                assert(await deck.locator('.people-slide-thumbnail').count() > 20);
                assert.equal(await deck.locator('.people-slide:not([hidden])').count(), 1);
                assert.equal(await page.locator('.indicator-advanced').evaluate(node => node.open), false);
                assert.equal(await deck.locator('.people-chart-stat strong').nth(1).innerText(), domain === 'turmas' ? '20' : '66');
                await deck.locator('[data-people-chart-type]').selectOption('pie');
                assert(await deck.locator('.people-slide.is-active svg path,.people-slide.is-active svg circle').count() > 0);
                await deck.locator('[data-people-chart-type]').selectOption('line');
                assert.equal(await deck.locator('.people-slide.is-active svg polyline').count(), 1);
                await deck.locator('[data-people-slide-next]').click();
                await deck.locator('[data-people-slide-prev]').click();
                assert.equal(await deck.locator('[data-people-chart-type]').inputValue(), 'line');
                await deck.locator('[data-people-chart-view]').selectOption('table');
                assert.equal(await deck.locator('.people-slide.is-active figure').isVisible(), false);
                await deck.locator('[data-people-chart-view]').selectOption('both');
                await deck.locator('[data-people-fullscreen]').click();
                await page.waitForFunction(() => !!document.fullscreenElement);
                await page.evaluate(() => document.exitFullscreen());
                await deck.locator('[data-people-presentation]').focus();
                await page.keyboard.press('ArrowRight');
                assert.match(await deck.locator('[data-people-slide-position]').innerText(), /^2 de/);
                for (const width of [1440, 820, 640, 390, 320]) {
                    await page.setViewportSize({ width, height: 1000 });
                    const overflow = await page.evaluate(() => [...document.querySelectorAll('body *')].filter(node => node.getBoundingClientRect().right > innerWidth + 1 && node.getBoundingClientRect().width).slice(0, 6).map(node => node.tagName + '.' + node.className));
                    assert(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1), domain + ' em ' + width + ': ' + overflow.join(', '));
                }
                await deck.screenshot({ path: path.join(directory, domain + '-mobile.png') });
                await page.setViewportSize({ width: 1440, height: 1000 });
                if (domain === 'agenda') {
                    await deck.locator('.people-slide-thumbnail').filter({ hasText: 'Ocupação por dia e período' }).first().click();
                    await page.waitForFunction(() => document.querySelector('.people-slide.is-active h3')?.textContent.includes('Ocupação por dia e período'));
                    assert.equal(await deck.locator('[data-people-chart-type] option[value=pie]').evaluate(node => node.disabled), true, await deck.locator('[data-people-chart-type]').evaluate(node => node.outerHTML));
                    assert.match(await deck.locator('.people-slide.is-active table').innerText(), /—/);
                }
                await deck.screenshot({ path: path.join(directory, domain + '-desktop.png') });
                const count = await deck.locator('.people-slide-thumbnail').count();
                await deck.evaluate(node => { node.dataset.printMode = 'all'; document.body.classList.add('printing-people-charts'); });
                const all = await page.pdf({ path: path.join(directory, domain + '-apresentacao.pdf'), preferCSSPageSize: true, printBackground: true });
                const pages = (all.toString('latin1').match(/\/Type \/Page\b/g) || []).length;
                assert.equal(pages, count, domain + ': uma página por slide');
                await deck.evaluate(node => { node.dataset.printMode = 'current'; document.body.classList.add('printing-people-charts'); });
                const single = await page.pdf({ path: path.join(directory, domain + '-slide.pdf'), preferCSSPageSize: true, printBackground: true });
                assert.equal((single.toString('latin1').match(/\/Type \/Page\b/g) || []).length, 1);
                await page.locator('.indicator-advanced > summary').click();
                if (domain === 'agenda') {
                    await page.locator('[data-agenda-measure]').selectOption('present');
                    assert.match(await deck.locator('.people-slide.is-active h3').innerText(), /Ocupação/);
                    await deck.locator('.people-slide-thumbnail').first().click();
                    assert.match(await deck.locator('.people-slide.is-active h3').innerText(), /Presenças/);
                } else {
                    await page.locator('[data-chart-professor]').selectOption('101');
                    await deck.locator('.people-slide-thumbnail').filter({ hasText: 'Professor principal · Turmas' }).first().click();
                    assert.match(await deck.locator('.people-slide.is-active table').innerText(), /Professora Ana/);
                    assert.doesNotMatch(await deck.locator('.people-slide.is-active table').innerText(), /Professor Bruno/);
                }
                assert.deepEqual(errors, []);
                console.log(domain + ': ' + count + ' slides, tipos, miniaturas, filtros locais, teclado, tabela, layout e impressão verificados.');
            } finally { await page.close(); server.kill(); }
        }
        console.log('Artefatos em ' + directory);
    } finally { await browser.close(); }
})().catch(error => { console.error(error); process.exitCode = 1; });
