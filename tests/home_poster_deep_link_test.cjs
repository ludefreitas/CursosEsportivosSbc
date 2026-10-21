const fs=require('fs'),vm=require('vm'),assert=require('assert');
const source=fs.readFileSync('public/assets/js/home.js','utf8');
function run(search){
 const requests=[],opened=[];
 function $(selector){const chain=new Proxy({length:1},{get(target,key){if(key in target)return target[key];if(key==='attr')return function(name,value){if(arguments.length===1)return name==='data-locations'?'[{"id":7,"apelido_local":"Orquídeas"}]':'';return chain;};if(key==='removeClass')return function(value){if(selector==='#home-location-modalities-modal'&&value==='hidden')opened.push(selector);return chain;};return ()=>chain;}});return chain;}
 $.getJSON=(url,params)=>{requests.push({url,params});const request={done(callback){callback({popup:null});return request;},fail(){return request;}};return request;};
 $.ajax=options=>{requests.push({url:options.url,params:options.data});const request={done(){return request;},fail(){return request;},always(){return request;}};return request;};
 const context={window:{App:{core:{buildUrl:p=>p}},jQuery:$,location:{search}},document:{body:{classList:{contains:()=>true}}},navigator:{},URLSearchParams,URL};
 vm.createContext(context);vm.runInContext(source,context);
 context.window.App.home.iniciarLocaisSugeridos();
 assert.strictEqual(requests.length,0,'A lista de locais não deve processar o link do QR.');
 context.window.App.home.iniciarFluxoDeCursosPorLocal();
 return {requests,opened};
}
const qr=run('?local_modalidades=7');
assert(qr.requests.some(r=>r.url==='/cursos/modalidades-por-local'&&Number(r.params.local_id)===7));
assert.strictEqual(qr.opened.length,1);
assert.strictEqual(run('').requests.filter(r=>r.url==='/cursos/modalidades-por-local').length,0);
console.log('QR na home: inicialização sem erro, modal do local correto e visita comum preservada OK.');
