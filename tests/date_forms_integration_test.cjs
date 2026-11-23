const fs = require('node:fs'), path = require('node:path'), assert = require('node:assert/strict');
const { execFileSync } = require('node:child_process');
const { chromium } = require('C:/Users/Aberto/.cache/codex-runtimes/codex-primary-runtime/dependencies/node/node_modules/playwright');
const root = path.resolve(__dirname, '..');
const dates = ['data_inicio','data_fim','inscricoes_inicio','inscricoes_fim','matriculas_inicio','matriculas_fim','inscricoes_abertas_inicio','inscricoes_abertas_fim','aulas_inicio','aulas_fim','data_liberacao_multiplas_inscricoes_modalidade'];
const format = value => {
    const [day,time] = String(value).replace(' ','T').split('T');const parts=day.split('-');
    return parts.reverse().join('/')+(time?' '+time.slice(0,5):'');
};
(async () => {
    const output=fs.mkdtempSync(path.join(root,'tmp/date-forms-'));
    const browser=await chromium.launch({executablePath:'C:/Program Files/Google/Chrome/Application/chrome.exe',headless:true});
    try {
        for (const area of ['admin','professor']) {
            const html=execFileSync('php',['tests/fixtures/date_forms_render.php',area],{cwd:root,encoding:'utf8',windowsHide:true});
            assert(!/Warning:|Fatal error:/.test(html));
            const page=await browser.newPage({viewport:{width:1280,height:900}}), errors=[];
            page.on('pageerror',error=>errors.push(error.message));
            await page.setContent(html);
            await page.addStyleTag({path:path.join(root,'public/assets/css/core.css')});
            await page.addStyleTag({path:path.join(root,'public/assets/css/admin.css')});
            await page.addScriptTag({path:path.join(root,'tmp/tutorial-realista/jquery.min.js')});
            await page.addScriptTag({path:path.join(root,'public/assets/js/core.js')});
            await page.evaluate(() => { window.messages=[];App.core.buildUrl=path=>path;App.core.abrirPopup=(kind,message)=>messages.push({kind,message}); });
            await page.addScriptTag({path:path.join(root,'public/assets/js/date-entry.js')});
            await page.addScriptTag({path:path.join(root,'public/assets/js/admin.js')});
            await page.evaluate(() => { App.core.iniciarValidacaoFormularios();App.admin.iniciarGerenciamentoModalidades();if(document.getElementById('course-season-modal'))App.admin.iniciarGerenciamentoTemporadasTurmas(); });
            const source=await page.locator('[data-modality-seasons]').evaluate(node=>JSON.parse(node.dataset.modalitySeasons)[0]);
            const form=page.locator('#admin-modality-schedule-form');
            await page.locator('#admin-modality-schedule-create').click();
            await form.locator('[name=temporada_id]').selectOption('7');
            await form.locator('[name=modalidade_id]').selectOption('2');
            await page.locator('#admin-modality-schedule-use-season').click();
            assert.match(await page.evaluate(()=>messages.at(-1).message),/foi copiado/);
            for(const name of dates){
                const input=form.locator('[name='+name+']');
                assert.equal(await input.inputValue(),source[name].replace(' ','T').slice(0,source[name].length===10?10:16));
                assert.equal(await input.locator('xpath=following-sibling::*[1]').locator('.date-entry-text').inputValue(),format(source[name]),area+' cópia '+name);
            }
            assert.equal(await form.locator('[name=nome]').inputValue(),'Futsal - Temporada de teste 2027');
            assert.equal(await form.locator('[name=numero_edital]').inputValue(),'12/2027');
            assert(await form.evaluate(node=>App.core.validarFormularioInline(node)),area+' formulário copiado deve permanecer válido');
            await page.locator('#admin-modality-schedule-modal .popup-card').screenshot({path:path.join(output,area+'-copia.png')});
            await page.locator('#admin-modality-schedule-use-modality').click();
            await page.locator('[data-modality-schedule-template="100"]').click();
            assert.equal(await form.locator('[name=data_inicio] + .date-entry-tools input').inputValue(),'01/01/2027');
            assert.equal(await form.locator('[name=cronograma_modalidade_id]').inputValue(),'');
            await page.locator('#admin-modality-schedule-modal [data-modality-schedule-close]').first().click();
            await page.locator('.admin-modality-schedule-edit').click();
            assert.equal(await form.locator('[name=inscricoes_inicio] + .date-entry-tools input').inputValue(),'03/01/2027 08:00');
            await page.locator('#admin-modality-schedule-modal [data-modality-schedule-close]').first().click();
            await page.locator('#admin-modality-schedule-create').click();
            assert.equal(await form.locator('[name=inscricoes_inicio] + .date-entry-tools input').inputValue(),'');
            await page.locator('#admin-modality-schedule-modal [data-modality-schedule-close]').first().click();
            if(area==='admin'){
                await page.locator('[data-course-edit=season]').click();
                const seasonForm=page.locator('[data-course-form=season]');
                await page.waitForFunction(()=>document.querySelector('[data-course-form=season] [name=data_liberacao_segunda_inscricao] + .date-entry-tools input')?.value==='01/03/2027 08:00');
                for(const name of [...dates,'data_liberacao_segunda_inscricao','data_liberacao_inscricoes_adicionais'])assert.equal(await seasonForm.locator('[name='+name+'] + .date-entry-tools input').inputValue(),format(source[name]),'edição da temporada '+name);
                assert.equal(await seasonForm.locator('[name=nome]').inputValue(),source.nome);
                assert.equal(await seasonForm.locator('[name=limite_inscricoes_adicionais]').inputValue(),'3');
                await page.locator('#course-season-modal .popup-card').screenshot({path:path.join(output,'temporada-edicao.png')});
                await page.locator('#course-season-modal [data-course-modal-close]').click();
                await page.locator('[data-course-create=season]').click();
                assert.equal(await seasonForm.locator('[name=data_inicio] + .date-entry-tools input').inputValue(),'');
            }
            await page.evaluate(() => {
                document.querySelectorAll('.popup-overlay').forEach(node=>node.classList.add('hidden'));
                const form=document.createElement('form');form.id='optional-date';form.innerHTML='<label>Data opcional<input type="date" name="opcional"></label>';document.body.append(form);
            });
            const optionalDate=page.locator('#optional-date .date-entry-text');
            await optionalDate.fill('31022026');
            assert.equal(await page.locator('#optional-date').evaluate(node=>App.core.validarFormularioInline(node)),false);
            assert.equal(await optionalDate.evaluate(node=>node===document.activeElement),true);
            assert.equal(await optionalDate.getAttribute('aria-invalid'),'true');
            await optionalDate.fill('');
            assert.equal(await page.locator('#optional-date').evaluate(node=>App.core.validarFormularioInline(node)),true);
            await page.evaluate(() => {
                const form=document.createElement('form');form.id='other-fields';
                form.innerHTML='<label>CPF<input name="cpf" value="11111111111"></label><label>E-mail<input type="email" name="email" value="invalido"></label><input type="time" name="hora" value="10:30"><input type="month" name="mes" value="2026-11">';document.body.append(form);
            });
            assert.equal(await page.locator('#other-fields').evaluate(node=>App.core.validarFormularioInline(node)),false);
            await page.locator('#other-fields [name=cpf]').fill('52998224725');
            await page.locator('#other-fields [name=email]').fill('teste@example.test');
            assert.equal(await page.locator('#other-fields').evaluate(node=>App.core.validarFormularioInline(node)),true);
            assert.equal(await page.locator('#other-fields [name=hora]').inputValue(),'10:30');
            assert.equal(await page.locator('#other-fields [name=mes]').inputValue(),'2026-11');
            assert.deepEqual(errors,[]);
            await page.screenshot({path:path.join(output,area+'.png'),fullPage:true});
            await page.close();
        }
        console.log('Formulários reais: cópia da temporada, cópia de modalidade e edição de cronograma no Admin/Professor; edição e limpeza da temporada no Admin. Arquivos em '+output);
    } finally { await browser.close(); }
})().catch(error=>{console.error(error);process.exitCode=1;});
