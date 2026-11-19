const fs = require('node:fs');
const path = require('node:path');
const net = require('node:net');
const assert = require('node:assert/strict');
const { spawn, execFileSync } = require('node:child_process');
const { chromium } = require('C:/Users/Aberto/.cache/codex-runtimes/codex-primary-runtime/dependencies/node/node_modules/playwright');

const root = path.resolve(__dirname, '..');
(async () => {
    const dir = fs.mkdtempSync(path.join(root, 'tmp/people-charts-'));
    const db = path.join(dir, 'fixture.sqlite');
    execFileSync('php', ['tests/people_chart_service_test.php', '--database', db], { cwd: root, windowsHide: true });
    const port = await new Promise(resolve => {
        const server = net.createServer();
        server.listen(0, '127.0.0.1', () => {
            const value = server.address().port;
            server.close(() => resolve(value));
        });
    });
    const server = spawn('php', ['-S', '127.0.0.1:' + port, 'tests/fixtures/people_charts_router.php'], {
        cwd: root, env: { ...process.env, PEOPLE_CHART_TEST_DATABASE: db }, stdio: 'ignore', windowsHide: true
    });
    let browser;
    try {
        const base = 'http://127.0.0.1:' + port;
        for (let attempt = 0; attempt < 80; attempt++) {
            try { await fetch(base); break; } catch (error) { await new Promise(resolve => setTimeout(resolve, 100)); }
        }
        browser = await chromium.launch({ executablePath: 'C:/Program Files/Google/Chrome/Application/chrome.exe', headless: true });
        const page = await browser.newPage({ viewport: { width: 1440, height: 1080 } });
        const errors = [];
        page.on('pageerror', error => errors.push(error.message));
        await page.goto(base + '/admin');
        await page.locator('[data-people-chart-results]:not([hidden])').waitFor();
        assert.equal(await page.locator('.people-chart-stat strong').first().innerText(), '5');
        const count = await page.locator('.people-slide-thumbnail').count();
        assert(count >= 34);
        assert.equal(await page.locator('.people-slide:not([hidden])').count(), 1);
        assert.equal(await page.locator('[data-people-chart-type]').inputValue(), 'pie');
        assert(await page.locator('.people-slide.is-active svg path, .people-slide.is-active svg circle').count() > 0);
        await page.locator('[data-people-slide-next]').click();
        assert.match(await page.locator('.people-slide:not([hidden]) h3').innerText(), /Evolução/);
        await page.locator('.people-slide-thumbnail').nth(2).click();
        assert.match(await page.locator('.people-slide:not([hidden]) h3').innerText(), /Faixas etárias/);
        await page.locator('[data-people-chart-view]').selectOption('table');
        assert(!(await page.locator('.people-slide.is-active .people-slide-chart').isVisible()));
        assert(await page.locator('.people-slide.is-active table').isVisible());
        await page.locator('[data-people-chart-view]').selectOption('both');
        await page.locator('[data-people-chart-type]').selectOption('pie');
        assert(await page.locator('.people-slide.is-active svg path, .people-slide.is-active svg circle').count() > 0);
        await page.locator('[data-people-chart-type]').selectOption('line');
        assert.equal(await page.locator('.people-slide.is-active svg polyline').count(), 1);
        await page.locator('[data-people-slide-next]').click();
        assert.equal(await page.locator('[data-people-chart-type]').inputValue(), 'pie');
        await page.locator('[data-people-slide-prev]').click();
        assert.equal(await page.locator('[data-people-chart-type]').inputValue(), 'line');
        await page.locator('[data-people-presentation]').focus();
        await page.keyboard.press('ArrowRight');
        assert.match(await page.locator('.people-slide.is-active h3').innerText(), /Sexo/);
        await page.locator('[data-people-chart-type]').selectOption('pie');
        await page.locator('[data-chart-theme-toggle]').click();
        assert.equal(await page.locator('[data-people-charts]').getAttribute('data-chart-theme'), 'light');
        await page.screenshot({ path: path.join(dir, 'desktop.png'), fullPage: true });
        await page.locator('[data-people-presentation]').screenshot({ path: path.join(dir, 'pizza.png') });
        const fields = page.locator('[data-people-chart-form]');
        await page.locator('.people-chart-filters summary').click();
        await fields.locator('[name=condition]').selectOption('pcd');
        await fields.locator('[type=submit]').click();
        await page.waitForFunction(() => document.querySelector('.people-chart-stat strong').textContent === '1');
        await fields.locator('[type=reset]').click();
        await page.waitForFunction(() => document.querySelector('.people-chart-stat strong').textContent === '5');
        await fields.locator('[name=age_min]').fill('99');
        await fields.locator('[type=submit]').click();
        await page.waitForFunction(() => document.querySelector('.people-chart-stat strong').textContent === '0');
        assert.match(await page.locator('.people-slide.is-active').innerText(), /Nenhuma pessoa|Sem registros/);
        await fields.locator('[type=reset]').click();
        await page.waitForFunction(() => document.querySelector('.people-chart-stat strong').textContent === '5');
        await page.locator('.people-chart-filters summary').click();
        for (const width of [820, 640, 390, 320]) {
            await page.setViewportSize({ width, height: 950 });
            const overflow = await page.evaluate(() => [...document.querySelectorAll('body *')].filter(node => node.getBoundingClientRect().right > innerWidth + 1 && node.getBoundingClientRect().width).slice(0, 8).map(node => node.tagName + '.' + node.className + ' ' + Math.round(node.getBoundingClientRect().right)));
            assert(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1), 'Layout excede a tela em ' + width + ': ' + overflow.join(', '));
        }
        await page.screenshot({ path: path.join(dir, 'mobile.png'), fullPage: true });
        await page.setViewportSize({ width: 1440, height: 1080 });
        await page.getByRole('button', { name: /Abrir slide.*Atestados clínicos · Local do atendimento/ }).click();
        assert.match(await page.locator('.people-slide.is-active table').innerText(), /Serviço público[\s\S]*Clínica de convênio|Clínica de convênio[\s\S]*Serviço público/);
        await page.locator('[data-people-presentation]').screenshot({ path: path.join(dir, 'atestados.png') });
        await page.evaluate(() => {
            document.querySelector('[data-people-charts]').dataset.printMode = 'all';
            document.body.classList.add('printing-people-charts');
        });
        const pdf = await page.pdf({ path: path.join(dir, 'apresentacao.pdf'), preferCSSPageSize: true, printBackground: true });
        const pages = (pdf.toString('latin1').match(/\/Type \/Page\b/g) || []).length;
        assert.equal(pages, count, 'Cada slide deve ocupar uma página na impressão padrão.');
        await page.evaluate(() => {
            document.querySelector('[data-people-charts]').dataset.printMode = 'current';
            document.body.classList.add('printing-people-charts');
        });
        const singlePdf = await page.pdf({ path: path.join(dir, 'slide.pdf'), preferCSSPageSize: true, printBackground: true });
        assert.equal((singlePdf.toString('latin1').match(/\/Type \/Page\b/g) || []).length, 1);
        await page.goto(base + '/professor');
        await page.locator('[data-people-chart-results]:not([hidden])').waitFor();
        assert.equal(await page.locator('.people-chart-stat strong').first().innerText(), '5');
        assert.deepEqual(errors, []);
        console.log('Slides, miniaturas, teclado, filtros, tabela, tema, layout 320–1440px e impressão de ' + pages + ' páginas verificados. Arquivos em ' + dir);
    } finally {
        if (browser) await browser.close();
        server.kill();
    }
})().catch(error => { console.error(error); process.exitCode = 1; });
