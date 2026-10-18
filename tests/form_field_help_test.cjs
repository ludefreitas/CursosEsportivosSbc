const fs=require('node:fs'),vm=require('node:vm'),assert=require('node:assert/strict');
let source=fs.readFileSync('public/assets/js/form-field-help.js','utf8');source=source.replace('    function enhance(root) {','    window.testHelpMessage = message;\n    function enhance(root) {');
const win={jQuery:function(callback){}};vm.runInNewContext(source,{window:win});
const markup=fs.readFileSync('app/Views/admin/partials/section_content.php','utf8');
for(const id of ['admin-modality-form','admin-modality-schedule-form','admin-training-location-form','admin-training-space-form']) {
 const pos=markup.indexOf('id="'+id+'"'),start=markup.lastIndexOf('<form',pos),end=markup.indexOf('</form>',pos);
 const form=markup.slice(start,end);
 for(const field of form.matchAll(/<(?:input|select|textarea)\b[^>]*\bname="([^"]+)"[^>]*>/g)) {
  if(/type="hidden"/.test(field[0]))continue;
  let name=field[1];
  if(name.startsWith('acessibilidade_barreiras['))name='acessibilidade_barreiras[fisica][]';
  const text=win.testHelpMessage({id},{name});
  assert.equal(typeof text,'string',id+': '+name);assert(text.length>30);
 }
}
for(const kind of ['clinico','dermatologico'])for(const suffix of ['data_emissao','crm_medico','local_atendimento','observacoes','arquivo'])assert(win.testHelpMessage({id:'dashboard-health-certificate-form'},{name:kind+'_'+suffix}).length>30);
console.log('Ajudas: cobertura dos campos visíveis de modalidade, cronograma, local, espaço e ambos os atestados OK.');
