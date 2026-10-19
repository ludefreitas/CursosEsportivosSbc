const fs=require('node:fs'),vm=require('node:vm'),assert=require('node:assert/strict');
const source=fs.readFileSync('public/assets/js/admin.js','utf8');
const start=source.indexOf('            function chooseClassCopyObservation('),end=source.indexOf("            $(document).on('click', '[data-class-copy-class]'",start);
assert(start>=0&&end>start);
let nodes=[],selected=0;
function $(tag,attrs){const node={tag,attrs:attrs||{},handlers:{},append(){return this;},appendTo(){return this;},on(event,fn){this.handlers[event]=fn;return this;},remove(){return this;},find(){return {val(){return String(selected);},first(){return this;},trigger(){return this;}};}};nodes.push(node);return node;}
const context=vm.createContext({$});vm.runInContext(source.slice(start,end),context);
let result;
context.chooseClassCopyObservation('','',v=>result=v);assert.equal(result,'');
for(const [current,origin,choice,expected] of [['Observação do cronograma','',0,'Observação do cronograma'],['Observação do cronograma','',2,''],['','Observação de origem',1,'Observação de origem']]) {
 nodes=[];result=undefined;selected=choice;
 context.chooseClassCopyObservation(current,origin,v=>result=v);
 assert.equal(result,undefined,'Não deve pular o alerta se houver texto em qualquer origem.');
 nodes.find(n=>n.tag==='<form>').handlers.submit({preventDefault(){},stopPropagation(){}});
 assert.equal(result,expected);
}
for(selected=0;selected<3;selected++){
 nodes=[];result=undefined;
 context.chooseClassCopyObservation('Texto atual <teste>','Texto da turma de origem',v=>result=v);
 assert.equal(result,undefined,'Não deve copiar antes da escolha.');
 const form=nodes.find(n=>n.tag==='<form>');assert(form);
 assert.equal(nodes.filter(n=>n.tag==='<input>').length,3);
 form.handlers.submit({preventDefault(){},stopPropagation(){}});
 assert.equal(result,['Texto atual <teste>','Texto da turma de origem',''][selected]);
}
nodes=[];result=undefined;context.chooseClassCopyObservation('Atual','Origem',v=>result=v);
nodes.find(n=>n.attrs.text==='Cancelar cópia').handlers.click();assert.equal(result,undefined,'Cancelar não deve aplicar a cópia.');
console.log('Cópia de turma: observação atual preservada, três escolhas e cancelamento OK.');
