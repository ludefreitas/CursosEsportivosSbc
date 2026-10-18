const fs=require('node:fs'),vm=require('node:vm'),assert=require('node:assert/strict');
const source=fs.readFileSync('public/assets/js/admin.js','utf8');
const start=source.indexOf('            function buildCourseFormData($form) {');
const end=source.indexOf('            function saveCourseForm($form)',start);
assert(start>=0&&end>start);
class TestFormData extends FormData { constructor(){ super(); this.append('observacao','Valor antigo'); this.append('observacao','Valor duplicado'); } }
const context=vm.createContext({FormData:TestFormData});vm.runInContext(source.slice(start,end),context);
for(const note of ['Observação preenchida pelo cronograma','Observação editada','', '0']) {
    const field={length:1,val(){return note;},first(){return this;}};
    const form={0:{},is(){return true;},find(){return field;}};
    const data=context.buildCourseFormData(form);
    assert.deepEqual(data.getAll('observacao'),[note]);
}
console.log('Envio da turma: observação atual incluída uma única vez, com edição e remoção preservadas.');
