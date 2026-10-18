const fs=require('node:fs'),vm=require('node:vm'),assert=require('node:assert/strict');
const text=fs.readFileSync('public/assets/js/admin.js','utf8');
const start=text.indexOf('            function preserveClassCopySchedule('),end=text.indexOf("            $(document).on('click', '[data-class-copy-class]'",start);
const schedules=[{id:10,temporada_id:2,modalidade_id:3},{id:11,temporada_id:2,modalidade_id:3},{id:12,temporada_id:1,modalidade_id:3}];
const context=vm.createContext({classScheduleData(){return schedules;}});vm.runInContext(text.slice(start,end),context);
const record={temporada_id:2,modalidade_id:3,cronograma_modalidade_id:0,observacao:'Texto da origem',campos_obrigatorios_pendentes:[{campo:'cronograma_modalidade_id'},{campo:'nome'}]};
function run(id,r=record){return context.preserveClassCopySchedule({find(){return {val(){return id;}};}},r);}
for(const id of ['10','11']){
 const copied=run(id);assert.equal(copied.cronograma_modalidade_id,id);assert.equal(copied.observacao,'Texto da origem');
 assert.equal(copied.campos_obrigatorios_pendentes.length,1);assert.equal(copied.campos_obrigatorios_pendentes[0].campo,'nome');
}
assert.equal(record.cronograma_modalidade_id,0,'A origem não deve ser alterada.');
assert.equal(run('12').cronograma_modalidade_id,0,'Cronograma de outra temporada não deve ser preservado.');
assert.equal(run('10',{...record,modalidade_id:9}).cronograma_modalidade_id,0,'Não misturar modalidades.');
assert.equal(run('').cronograma_modalidade_id,0);
assert.equal(run('',{...record,cronograma_modalidade_id:10}).cronograma_modalidade_id,10,'Preservar sugestão da cópia quando não houver escolha.');
console.log('Cópia da turma: cronograma de destino preservado, múltiplos cronogramas e pendências validados.');
