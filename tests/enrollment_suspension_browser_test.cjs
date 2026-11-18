const fs = require('node:fs');
const path = require('node:path');
const assert = require('node:assert/strict');
const { execFileSync } = require('node:child_process');
const { chromium } = require('C:/Users/Aberto/.cache/codex-runtimes/codex-primary-runtime/dependencies/node/node_modules/playwright');

const root = path.resolve(__dirname, '..');
const source = fs.readFileSync(path.join(root, 'public/assets/js/admin.js'), 'utf8');
const start = source.indexOf("            $(document).on('click', '[data-class-enrollment-action]'");
const end = source.indexOf("            $(document).on('submit', '[data-course-class-status-form", start);
assert(start > 0 && end > start);
const fields = execFileSync('php', ['-r',
    "require 'app/Services/EnrollmentSuspensionService.php'; function e($s){return htmlspecialchars($s,ENT_QUOTES,'UTF-8');} require 'app/Views/admin/partials/enrollment_suspension_fields.php';"
], { cwd: root, encoding: 'utf8', windowsHide: true });

(async () => {
    const browser = await chromium.launch({
        executablePath: 'C:/Program Files/Google/Chrome/Application/chrome.exe',
        headless: true
    });
    try {
        const page = await browser.newPage({ viewport: { width: 390, height: 900 } });
        const errors = [];
        page.on('pageerror', error => errors.push(error.message));
        await page.setContent(`<html lang="pt-BR"><body>
            <button data-class-enrollment-action="suspensa" data-enrollment-id="1" data-person-name="Aluno">Suspender</button>
            <button data-class-enrollment-action="renovar_suspensao" data-enrollment-id="1" data-person-name="Aluno" data-suspension-end="2026-12-01 10:00:00">Renovar</button>
            <button data-class-enrollment-action="matriculada" data-enrollment-id="1" data-person-name="Aluno">Rematricular</button>
            <div id="course-class-attendance-roster-modal" data-class-id="2"><div data-class-attendance-roster><div data-class-attendance-date="2026-11-18"></div></div></div>
            <div id="class-enrollment-action-modal" class="popup-overlay hidden"><div class="popup-card popup-admin-card class-justification-modal-card"><div class="popup-body">
                <p data-class-enrollment-action-question></p>${fields}
                <button data-class-enrollment-action-confirm="1">Confirmar</button>
                <button data-class-enrollment-action-close="1">Fechar</button>
            </div></div></div></body></html>`);
        await page.addStyleTag({ path: path.join(root, 'public/assets/css/core.css') });
        await page.addStyleTag({ path: path.join(root, 'public/assets/css/admin.css') });
        await page.addScriptTag({ path: path.join(root, 'tmp/tutorial-realista/jquery.min.js') });
        await page.addScriptTag({ content: `
            window.requests = [];
            window.App = { core: { abrirPopup() {}, extrairMensagemErroAjax() { return { mensagem: 'Erro' }; } } };
            function classAttendanceEndpoint() { return '/teste/chamada'; }
            jQuery.ajax = function (options) {
                requests.push(options.data);
                return jQuery.Deferred().resolve({success: true, html: ''}).promise();
            };
            (function ($) { ${source.slice(start, end)} })(jQuery);
        ` });
        const confirm = page.locator('[data-class-enrollment-action-confirm]');
        const form = page.locator('[data-class-suspension-form]');
        await page.locator('[data-class-enrollment-action="suspensa"]').click();
        assert(await form.isVisible());
        await confirm.click();
        assert.equal(await page.evaluate(() => requests.length), 0);
        await form.locator('[name=suspensao_semanas]').selectOption('8');
        await form.locator('[name=suspensao_motivo]').selectOption('Outro motivo');
        assert(await form.locator('[name=suspensao_outro_motivo]').isVisible());
        await form.locator('[name=suspensao_libera_vaga]').selectOption('1');
        await confirm.click();
        assert.equal(await page.evaluate(() => requests.length), 0);
        await form.locator('[name=suspensao_outro_motivo]').fill('Recuperação temporária após procedimento médico');
        await confirm.click();
        let sent = await page.evaluate(() => requests.at(-1));
        assert.equal(sent.suspensao_semanas, '8');
        assert.equal(sent.suspensao_libera_vaga, '1');
        assert.equal(sent.acao_matricula, 'suspensa');
        await page.locator('[data-class-enrollment-action="renovar_suspensao"]').click();
        assert(!(await form.locator('[name=suspensao_libera_vaga]').isVisible()));
        await form.locator('[name=suspensao_semanas]').selectOption('2');
        await form.locator('[name=suspensao_motivo]').selectOption('Problema de saúde');
        await confirm.click();
        sent = await page.evaluate(() => requests.at(-1));
        assert.equal(sent.suspensao_fim_atual, '2026-12-01 10:00:00');
        assert.equal(sent.acao_matricula, 'renovar_suspensao');
        assert(!Object.hasOwn(sent, 'suspensao_libera_vaga'));
        await page.locator('[data-class-enrollment-action="matriculada"]').click();
        assert(!(await form.isVisible()));
        await confirm.click();
        sent = await page.evaluate(() => requests.at(-1));
        assert.equal(sent.acao_matricula, 'matriculada');
        assert(!Object.hasOwn(sent, 'suspensao_semanas'));
        assert.deepEqual(errors, []);
        console.log('Modal verificado no Chrome: prazo, motivo, validação obrigatória, liberação de vaga, renovação e rematrícula.');
    } finally {
        await browser.close();
    }
})().catch(error => {
    console.error(error);
    process.exitCode = 1;
});
