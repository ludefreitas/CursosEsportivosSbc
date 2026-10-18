const fs = require('node:fs');
const vm = require('node:vm');
const assert = require('node:assert/strict');
const source = fs.readFileSync('public/assets/js/admin.js', 'utf8');
function run(selector, values, parent, edited = false, previousSchedule = values.cronograma_modalidade_id || '') {
    const marker = "$(document).on('change', '" + selector + "', function () {";
    const start = source.indexOf(marker);
    assert(start >= 0);
    const bodyStart = start + marker.length;
    const end = source.indexOf('\n            });', bodyStart);
    const form = {
        find(key) {
            const name = key.match(/name="([^"]+)"/)[1];
            return {
                val(value) { if (arguments.length) { values[name] = value; return this; } return values[name]; },
                attr() { return parent && parent.observacao; },
                trigger() { return this; }
            };
        },
        data(key, value) {
            if (arguments.length > 1) { if (key === 'observation-edited') edited = value; else previousSchedule = value; return this; }
            return key === 'observation-edited' ? edited : previousSchedule;
        }
    };
    vm.runInNewContext('(function(){' + source.slice(bodyStart, end) + '}).call({})', {
        $() { return { closest() { return form; } }; },
        scheduleData() { return parent ? [parent] : []; },
        classScheduleData() { return parent ? [parent] : []; },
        renderClassScheduleCatalog() {}
    });
    return values.observacao;
}
for (const kind of ['schedule', 'class']) {
    const schedule = kind === 'schedule';
    const selector = schedule ? '#admin-modality-schedule-form [name="temporada_id"]' : '[data-course-form="class"] [name="cronograma_modalidade_id"]';
    function values(note, edit = false) { return schedule ? { temporada_id: '1', cronograma_modalidade_id: edit ? '2' : '', observacao: note } : { cronograma_modalidade_id: '1', id: edit ? '2' : '', observacao: note }; }
    assert.equal(run(selector, values(''), { id: 1, observacao: 'Orientação com acentuação' }), 'Orientação com acentuação');
    assert.equal(run(selector, values('Anterior'), { id: 1, observacao: null }), '');
    assert.equal(run(selector, values(''), null), '');
    if (!schedule) {
        const zeroId = values(''); zeroId.id = '0';
        assert.equal(run(selector, zeroId, { id: 1, observacao: 'Texto do cronograma' }), 'Texto do cronograma');
    }
    assert.equal(run(selector, values('Personalizada'), { id: 1, observacao: 'Origem' }, true), 'Personalizada');
    assert.equal(run(selector, values(''), { id: 1, observacao: 'Origem' }, true), '');
    if (!schedule) {
        assert.equal(run(selector, values('Texto editado'), { id: 1, observacao: 'Novo cronograma' }, true, '2'), 'Novo cronograma');
        assert.equal(run(selector, values('Texto antigo'), { id: 1, observacao: null }, true, '2'), '');
    }
    assert.equal(run(selector, values('Registro existente', true), { id: 1, observacao: 'Origem alterada' }), 'Registro existente');
}
console.log('Observações: cópia inicial, origem vazia, edição própria, remoção e preservação de registros existentes OK.');
