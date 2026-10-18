const fs = require('node:fs');
const vm = require('node:vm');
const assert = require('node:assert/strict');
const source = fs.readFileSync('public/assets/js/admin.js', 'utf8');
const begin = source.indexOf('            function applyModalityScheduleTemplate($form, source) {');
const end = source.indexOf("            $(document).on('click', '#admin-modality-schedule-use-modality'", begin);
assert(begin >= 0 && end > begin);
const values = { temporada_id: '10', modalidade_id: '20', cronograma_modalidade_id: '30', nome: 'Nome de destino' };
const checks = new Set(['permitir_inscricao_periodo_matricula', 'possui_edital', 'permitir_multiplas_inscricoes_modalidade']);
const form = {
    data() {},
    find(selector) {
        const match = selector.match(/\[name="([^"]+)"\]/);
        const name = match ? match[1] : '';
        return {
            attr() { return checks.has(name) ? 'checkbox' : (/inscricoes_|matriculas_|liberacao_/.test(name) ? 'datetime-local' : 'text'); },
            val(value) { values[name] = value; return this; },
            prop(key, value) { values[name] = value; return this; },
            each(callback) { callback.call({ setCustomValidity() {} }); return this; },
            trigger() { return this; }
        };
    }
};
const context = vm.createContext({ toLocalDateTime(value) { return String(value || '').replace(' ', 'T').slice(0, 16); }, updateModalityRegistrationEnrollmentField() {}, updateModalityMultipleScheduleWarning() {}, validateCoursePeriodChronology() {} });
vm.runInContext(source.slice(begin, end), context);
context.applyModalityScheduleTemplate(form, { id: 999, temporada_id: 99, modalidade_id: 98, nome: 'Não copiar', observacao: 'Orientação da modalidade', inscricoes_inicio: '2026-10-20 08:00:00', possui_edital: 1, numero_edital: '123', permitir_inscricao_periodo_matricula: 0 });
assert.equal(values.temporada_id, '10');
assert.equal(values.modalidade_id, '20');
assert.equal(values.cronograma_modalidade_id, '30');
assert.equal(values.nome, 'Nome de destino');
assert.equal(values.observacao, 'Orientação da modalidade');
assert.equal(values.inscricoes_inicio, '2026-10-20T08:00');
assert.equal(values.possui_edital, true);
assert.equal(values.permitir_inscricao_periodo_matricula, false);
context.applyModalityScheduleTemplate(form, { observacao: '', possui_edital: 0 });
assert.equal(values.observacao, '');
assert.equal(values.possui_edital, false);
assert.equal(values.numero_edital, '');
console.log('Cópia de cronograma: datas, observação, regras e preservação da identidade de destino OK.');
