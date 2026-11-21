const assert = require('node:assert/strict');
const path = require('node:path');
const { chromium } = require('C:/Users/Aberto/.cache/codex-runtimes/codex-primary-runtime/dependencies/node/node_modules/playwright');
(async () => {
    const root = path.resolve(__dirname, '..');
    const browser = await chromium.launch({ executablePath: 'C:/Program Files/Google/Chrome/Application/chrome.exe', headless: true });
    try {
        const page = await browser.newPage();
        const errors = [];
        page.on('pageerror', error => errors.push(error.message));
        await page.setContent(`<form id="admin-training-location-filter-form"></form><form id="admin-training-space-filter-form"></form>
            <form id="admin-training-space-form"><select name="local_treino_id"><option value="">Selecione</option><option value="1" selected>Local livre</option></select></form>
            <table><tbody><tr id="location"><td><button class="admin-training-location-delete" data-record-id="1" data-record-name="Local livre">Excluir local</button></td></tr>
            <tr id="space"><td><button class="admin-training-space-delete" data-record-id="2" data-record-name="Espaço de teste">Excluir espaço</button></td></tr></tbody></table>`);
        await page.addScriptTag({ path: path.join(root, 'tmp/tutorial-realista/jquery.min.js') });
        await page.evaluate(() => {
            window.calls = []; window.messages = []; window.refreshed = []; window.deleteMode = 'success';
            window.App = { core: { buildUrl: path => path, abrirPopup: (kind, message) => messages.push({kind,message}), extrairMensagemErroAjax: xhr => ({mensagem:xhr.responseJSON.message}) } };
            jQuery.post = (url, data) => {
                calls.push({url,data}); const deferred = jQuery.Deferred();
                setTimeout(() => {
                    if (deleteMode === 'failure') deferred.reject({responseJSON:{message:'Falha de conexão simulada.'}});
                    else deferred.resolve(deleteMode === 'blocked' ? {success:false,message:'Este espaço possui vínculos.'} : {success:true,message:'Cadastro excluído.'});
                }, 20);
                return deferred.promise();
            };
            jQuery('form').on('submit', function(event) { event.preventDefault(); refreshed.push(this.id); });
        });
        await page.addScriptTag({ path: path.join(root, 'public/assets/js/admin.js') });
        await page.evaluate(() => App.admin.iniciarBuscaEnderecoCep());
        page.once('dialog', dialog => dialog.dismiss());
        await page.locator('#location button').click();
        assert.equal(await page.evaluate(() => calls.length), 0);
        page.once('dialog', dialog => dialog.accept());
        await page.locator('#location button').click();
        await page.waitForFunction(() => !document.getElementById('location'));
        assert.deepEqual(await page.evaluate(() => calls[0]), {url:'/admin/locais/excluir',data:{local_treino_id:1}});
        assert.equal(await page.locator('select[name=local_treino_id]').inputValue(), '');
        for (const mode of ['blocked','failure']) {
            await page.evaluate(mode => { deleteMode = mode; }, mode);
            page.once('dialog', dialog => dialog.accept());
            await page.locator('#space button').click();
            await page.waitForFunction(() => messages.at(-1)?.kind === 'erro' && !document.querySelector('#space button').disabled);
            assert.equal(await page.locator('#space').count(), 1);
        }
        await page.evaluate(() => { deleteMode = 'success'; });
        page.once('dialog', dialog => dialog.accept());
        await page.locator('#space button').click();
        await page.waitForFunction(() => !document.getElementById('space'));
        assert.deepEqual(await page.evaluate(() => calls.at(-1)), {url:'/admin/espacos/excluir',data:{espaco_treino_id:2}});
        assert.deepEqual(await page.evaluate(() => refreshed), ['admin-training-location-filter-form','admin-training-space-filter-form']);
        assert.deepEqual(errors, []);
        console.log('Exclusão via interface: confirmação, cancelamento, bloqueios, falhas, seleção de local e atualização das duas listas verificados.');
    } finally { await browser.close(); }
})().catch(error => { console.error(error); process.exitCode = 1; });
