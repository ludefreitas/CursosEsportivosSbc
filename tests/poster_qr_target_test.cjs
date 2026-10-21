const fs=require('fs'),vm=require('vm'),assert=require('assert');
const source=fs.readFileSync('public/assets/js/posters.js','utf8');
const begin=source.indexOf('    function systemBase('),end=source.indexOf('    async function image(',begin);
function run(base,origin){const context={data:{base},window:{location:{origin}},URL};vm.createContext(context);vm.runInContext(source.slice(begin,end),context);return context;}
const app=run('/','https://cursosesportivos.com.br');
assert.strictEqual(app.target({local_id:12,site:'www.outrosite.com'}),'https://cursosesportivos.com.br/?local_modalidades=12#home-training-locations');
assert.strictEqual(app.target({local_id:12,site:''}),app.target({local_id:12,site:'www.outrosite.com'}));
assert.strictEqual(app.target({local_id:0}), '');
assert.strictEqual(app.url('/admin/cartazes/salvar'),'https://cursosesportivos.com.br/admin/cartazes/salvar');
const sub=run('/sistema','https://exemplo.com');
assert.strictEqual(sub.target({local_id:3}),'https://exemplo.com/sistema/?local_modalidades=3#home-training-locations');
assert.strictEqual(sub.url('/uploads/posters/test.png'),'https://exemplo.com/sistema/uploads/posters/test.png');
console.log('QR automático: domínio atual, local, pasta base e independência do texto do site OK.');
