const assert=require('node:assert/strict');
const fs=require('node:fs');
const validator=require('../public/assets/js/sus-card.js');
const vectors=JSON.parse(fs.readFileSync(process.argv[2],'utf8'));
for(const value of vectors.valid)assert.equal(validator.isValid(value),true,'Número estruturalmente válido recusado.');
for(const value of vectors.invalid)assert.equal(validator.isValid(value),false,'Número inválido/fictício aceito.');
console.log('Validação SUS no navegador: mesmos casos e resultados do servidor.');
