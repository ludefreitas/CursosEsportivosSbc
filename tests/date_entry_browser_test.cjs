const fs = require('node:fs'), path = require('node:path'), assert = require('node:assert/strict');
const { chromium } = require('C:/Users/Aberto/.cache/codex-runtimes/codex-primary-runtime/dependencies/node/node_modules/playwright');
const inventory = require('./fixtures/date_input_inventory.cjs');
const root = path.resolve(__dirname, '..');
(async () => {
    const entries = inventory();
    const output = fs.mkdtempSync(path.join(root,'tmp/date-entry-'));
    fs.writeFileSync(path.join(output,'inventario.json'),JSON.stringify(entries,null,2));
    assert(entries.some(row => row.file.includes('auth/register.php')));
    assert(entries.some(row => row.type === 'datetime-local'));
    const browser = await chromium.launch({executablePath:'C:/Program Files/Google/Chrome/Application/chrome.exe',headless:true});
    try {
        const page = await browser.newPage();
        const errors = []; page.on('pageerror', error => errors.push(error.message));
        await page.setContent(`<style>.date-entry-native-hidden{display:none!important}.date-entry-tools[hidden]{display:none}</style>
            <form id="test" novalidate><label for="birth">Nascimento</label><input id="birth" type="date" name="birth_date" required>
            <label><span>Data e hora</span><input id="datetime" type="datetime-local" name="datetime"></label>
            <input id="other" type="text" name="cpf" value="12345678901"><input id="month" type="month" value="2026-01"><input id="time" type="time" value="10:30">
            <button id="submit" type="submit">Salvar</button><button id="reset" type="reset">Limpar</button></form><div id="dynamic"></div>`);
        await page.addScriptTag({path:path.join(root,'tmp/tutorial-realista/jquery.min.js')});
        await page.addScriptTag({path:path.join(root,'public/assets/js/date-entry.js')});
        await page.evaluate(() => {
            window.sent = 0; window.events = [];
            document.getElementById('test').addEventListener('submit', event => { event.preventDefault(); sent++; });
            for (const type of ['input','change']) document.getElementById('birth').addEventListener(type, event => events.push(type));
        });
        const birth = page.locator('#birth + .date-entry-tools input');
        const datetime = page.locator('#datetime + .date-entry-tools input');
        await birth.pressSequentially('22012026');
        assert.equal(await birth.inputValue(),'22/01/2026');
        await page.locator('#other').focus();
        assert.equal(await page.locator('#birth').inputValue(),'2026-01-22');
        assert((await page.evaluate(() => events)).includes('change'));
        await datetime.pressSequentially('220120261435');
        assert.equal(await datetime.inputValue(),'22/01/2026 14:35');
        await page.locator('#other').focus();
        assert.equal(await page.locator('#datetime').inputValue(),'2026-01-22T14:35');
        await birth.evaluate(node => { node.focus();node.setSelectionRange(3,5); });
        await page.keyboard.type('02');
        assert.equal(await birth.inputValue(),'22/02/2026');
        await birth.evaluate(node => node.setSelectionRange(2,2));
        await page.keyboard.press('Delete');
        assert.equal(await birth.inputValue(),'22/22/026');
        await birth.fill('22012026');
        await birth.evaluate(node => node.setSelectionRange(3,3));
        await page.keyboard.press('Backspace');
        assert.equal(await birth.inputValue(),'20/12/026');
        for (const [raw,expected] of [['2026-01-22','22/01/2026'],['22/01/2026','22/01/2026'],['29022024','29/02/2024']]) {
            await birth.fill(raw); await page.locator('#other').focus(); assert.equal(await birth.inputValue(),expected);
        }
        for (const raw of ['31022026','29022025','2201']) {
            await birth.fill(raw); await page.locator('#submit').click();
            assert.equal(await page.evaluate(() => sent),0);
            assert.equal(await page.locator('#birth').inputValue(),'');
            assert(await page.locator('#birth + .date-entry-tools .date-entry-error').isVisible());
        }
        await birth.fill('22012026');
        await page.locator('#submit').click();
        assert.equal(await page.evaluate(() => sent),1);
        const data = await page.evaluate(() => [...new FormData(document.getElementById('test'))]);
        assert.deepEqual(data.filter(([key]) => key === 'birth_date'),[['birth_date','2026-01-22']]);
        await page.evaluate(() => { document.getElementById('birth').min='2026-01-01'; document.getElementById('birth').max='2026-12-31'; });
        await birth.fill('01012025');await page.locator('#submit').click();assert.equal(await page.evaluate(() => sent),1);
        await page.evaluate(() => { const field=document.getElementById('birth');field.min='';field.max='';field.value='2027-05-03'; });
        assert.equal(await birth.inputValue(),'03/05/2027');
        await birth.focus();
        await page.evaluate(() => jQuery('#birth').val('2028-06-04'));
        assert.equal(await birth.inputValue(),'04/06/2028');
        await page.evaluate(() => jQuery('#datetime').val('2028-06-04T12:40'));
        assert.equal(await datetime.inputValue(),'04/06/2028 12:40');
        await page.evaluate(() => { const field=document.getElementById('birth');field.value='2026-01-22';field.setCustomValidity('Regra externa preservada.'); });
        await page.locator('#submit').click();assert.equal(await page.evaluate(() => sent),1);
        assert.equal(await page.locator('#birth').evaluate(node => node.validationMessage),'Regra externa preservada.');
        await page.evaluate(() => document.getElementById('birth').setCustomValidity(''));
        await page.locator('#reset').click();
        await page.waitForFunction(() => !document.querySelector('#birth + .date-entry-tools input').value);
        await page.evaluate(() => { const field=document.getElementById('birth');field.value='2000-03-04';field.readOnly=true; });
        await page.waitForFunction(() => document.querySelector('#birth + .date-entry-tools input').readOnly);
        assert.equal(await birth.inputValue(),'04/03/2000');assert(await birth.isVisible());
        await page.evaluate(() => { const field=document.getElementById('birth');field.readOnly=false;field.disabled=true; });
        await page.waitForFunction(() => document.querySelector('#birth + .date-entry-tools input').disabled);
        assert(await birth.isVisible());
        assert.equal(await page.locator('#other').inputValue(),'12345678901');
        assert.equal(await page.locator('#month').inputValue(),'2026-01');assert.equal(await page.locator('#time').inputValue(),'10:30');
        assert.equal(await page.locator('#month + .date-entry-tools,#time + .date-entry-tools,#other + .date-entry-tools').count(),0);
        await page.evaluate(() => {
            const field=document.getElementById('birth');field.disabled=false;
            field.showPicker=function(){this.value='2026-07-08';this.dispatchEvent(new Event('change',{bubbles:true}));};
        });
        await page.locator('#birth + .date-entry-tools button').click();
        assert.equal(await birth.inputValue(),'08/07/2026');
        await page.evaluate(() => { const field=document.getElementById('birth');field.type='text';field.value='123.456.789-01'; });
        await page.waitForFunction(() => !document.getElementById('birth').classList.contains('date-entry-native-hidden'));
        assert(await page.locator('#birth').isVisible());
        assert.equal(await page.locator('#birth').inputValue(),'123.456.789-01');
        await page.evaluate(() => { const field=document.getElementById('birth');field.type='date';field.value='2026-01-22'; });
        await page.waitForFunction(() => document.getElementById('birth').classList.contains('date-entry-native-hidden'));
        assert.equal(await birth.inputValue(),'22/01/2026');
        await page.evaluate(() => { const field=document.createElement('input');field.type='date';field.id='attribute-date';field.setAttribute('value','2026-03-04');document.body.append(field); });
        await page.waitForFunction(() => document.querySelector('#attribute-date + .date-entry-tools input')?.value==='04/03/2026');
        await page.evaluate(() => document.getElementById('attribute-date').setAttribute('value','2026-04-05'));
        await page.waitForFunction(() => document.querySelector('#attribute-date + .date-entry-tools input').value==='05/04/2026');
        // Todos os pontos de declaração encontrados usam o mesmo componente, inclusive os incluídos por AJAX.
        await page.evaluate(entries => {
            document.getElementById('dynamic').innerHTML=entries.map((entry,index)=>`<label>${index}<input id="audit-${index}" type="${entry.type}"></label>`).join('');
        }, entries);
        const dates = entries.filter(entry => ['date','datetime-local'].includes(entry.type));
        await page.waitForFunction(count => document.querySelectorAll('#dynamic .date-entry-tools').length === count,dates.length);
        const failures = await page.evaluate(entries => entries.flatMap((entry,index) => {
            const field=document.getElementById('audit-'+index);
            if (!['date','datetime-local'].includes(entry.type)) return field.nextElementSibling?.classList.contains('date-entry-tools') ? [entry] : [];
            const text=field.nextElementSibling.querySelector('input');
            field.value=entry.type==='date'?'2026-01-22':'2026-01-22T14:35';
            if(text.value!==(entry.type==='date'?'22/01/2026':'22/01/2026 14:35'))return [entry];
            text.value=entry.type==='date'?'22012026':'220120261435';text.dispatchEvent(new Event('input',{bubbles:true}));
            return field.value===(entry.type==='date'?'2026-01-22':'2026-01-22T14:35')?[]:[entry];
        }),entries);
        assert.deepEqual(failures,[]);
        assert.deepEqual(errors,[]);
        console.log(`${dates.length} declarações de data/data e hora em ${new Set(dates.map(entry=>entry.file)).size} arquivos verificadas; digitação, edição, colagem, limites, submissão, preenchimento JS/jQuery, reset e campos não relacionados preservados. Inventário em ${output}`);
    } finally { await browser.close(); }
})().catch(error => { console.error(error);process.exitCode=1; });
