const fs = require('node:fs');
const path = require('node:path');
const assert = require('node:assert/strict');
const { execFileSync } = require('node:child_process');
const { chromium } = require('C:/Users/Aberto/.cache/codex-runtimes/codex-primary-runtime/dependencies/node/node_modules/playwright');

const root = path.resolve(__dirname, '..');
const source = fs.readFileSync(path.join(root, 'public/assets/js/admin.js'), 'utf8');
const initializer = source.indexOf('iniciarValidacaoCondicoesAdmin: function () {');
const start = source.indexOf('let filterRequest', initializer);
const end = source.indexOf('function getModal()', start);
assert(start > initializer && end > start);
const panel = execFileSync('php', ['-r',
    "function e($s){return htmlspecialchars($s,ENT_QUOTES,'UTF-8');} require 'app/Views/admin/partials/condition_validation_panel.php';"
], { cwd: root, encoding: 'utf8', windowsHide: true });

(async () => {
    const browser = await chromium.launch({
        executablePath: 'C:/Program Files/Google/Chrome/Application/chrome.exe',
        headless: true
    });
    try {
        const page = await browser.newPage({ viewport: { width: 1366, height: 900 } });
        const requests = [];
        const errors = [];
        page.on('pageerror', error => errors.push(error.message));
        await page.route('http://validation.test/**', async route => {
            const url = new URL(route.request().url());
            if (url.pathname !== '/professor/secao') {
                await route.fulfill({
                    contentType: 'text/html',
                    body: '<html lang="pt-BR"><body><main style="max-width:1200px;margin:auto"><div data-admin-section-host data-admin-section-url="/professor/secao">' + panel + '</div></main></body></html>'
                });
                return;
            }
            const values = Object.fromEntries(url.searchParams);
            requests.push(values);
            const query = url.searchParams.get('validation_search') || '';
            await new Promise(resolve => setTimeout(resolve, query === 'm' ? 200 : 30));
            const filtered = query !== '' || [...url.searchParams.keys()].some(key => key.includes('['));
            const html = '<section><article id="admin-condition-validation-panel"><div data-condition-validation-results>'
                + (filtered ? '<p data-last-query="' + query + '">Resultado filtrado</p>' : '<p>Digite um nome, CPF ou telefone, ou selecione um filtro para consultar.</p>')
                + '</div></article></section>';
            await route.fulfill({ contentType: 'application/json', body: JSON.stringify({ success: true, html }) }).catch(() => {});
        });
        await page.goto('http://validation.test/');
        await page.addStyleTag({ path: path.join(root, 'public/assets/css/core.css') });
        await page.addStyleTag({ path: path.join(root, 'public/assets/css/admin.css') });
        await page.addScriptTag({ path: path.join(root, 'tmp/tutorial-realista/jquery.min.js') });
        await page.addScriptTag({ content: `
            window.App = { core: {
                abrirPopup() { throw new Error('Erro ao atualizar os filtros'); },
                extrairMensagemErroAjax() { return { mensagem: 'Falha' }; }
            } };
            (function ($) { ${source.slice(start, end)} })(jQuery);
        ` });
        assert.equal(requests.length, 0, 'A abertura não deve buscar todos os registros.');
        assert.equal(await page.locator('[data-condition-validation-filter] button').count(), 0);
        const search = page.locator('[name=validation_search]');
        await search.pressSequentially('maria', { delay: 60 });
        await page.locator('[data-last-query="maria"]').waitFor();
        assert.deepEqual(requests.slice(0, 5).map(row => row.validation_search), ['m', 'ma', 'mar', 'mari', 'maria']);
        assert.equal(await search.inputValue(), 'maria');
        assert(await search.evaluate(element => document.activeElement === element), 'A busca deve manter o foco.');
        await page.locator('[name="validation_conditions[]"][value=pcd]').check();
        await page.waitForFunction(() => window.jQuery.active === 0);
        assert.equal(requests.at(-1)['validation_conditions[]'], 'pcd');
        await page.locator('[name="validation_statuses[]"][value=pendente]').check();
        await page.waitForFunction(() => window.jQuery.active === 0);
        assert.equal(requests.at(-1)['validation_statuses[]'], 'pendente');
        const boxes = page.locator('.condition-validation-filter-row fieldset');
        const conditionBox = await boxes.nth(0).boundingBox();
        const statusBox = await boxes.nth(1).boundingBox();
        assert(Math.abs(conditionBox.y - statusBox.y) < 2, 'Condição e status devem ficar na mesma linha.');
        for (const width of [820, 390, 320]) {
            await page.setViewportSize({ width, height: 900 });
            assert(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1), 'Conteúdo excedeu a tela em ' + width);
        }
        await page.locator('[name="validation_conditions[]"][value=pcd]').uncheck();
        await page.waitForFunction(() => window.jQuery.active === 0);
        await page.locator('[name="validation_statuses[]"][value=pendente]').uncheck();
        await page.waitForFunction(() => window.jQuery.active === 0);
        await search.fill('');
        await page.waitForFunction(() => window.jQuery.active === 0);
        assert.match(await page.locator('[data-condition-validation-results]').innerText(), /Digite um nome/);
        assert.deepEqual(errors, []);
        console.log('Busca AJAX por letra, cancelamento de consultas antigas, foco, filtros automáticos, lista inicial vazia e layout 320–1366px verificados.');
    } finally {
        await browser.close();
    }
})().catch(error => {
    console.error(error);
    process.exitCode = 1;
});
