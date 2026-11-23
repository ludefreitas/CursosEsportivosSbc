const fs = require('node:fs');
const path = require('node:path');
const { execFileSync } = require('node:child_process');
const root = path.resolve(__dirname, '../..');
module.exports = function inventory() {
    const files = execFileSync('rg', ['--files', 'app', 'public/assets/js'], {cwd:root,encoding:'utf8'}).trim().split(/\r?\n/);
    const entries = [];
    for (const file of files) {
        if (!/\.(php|js)$/.test(file) || file.endsWith('date-entry.js')) continue;
        const source = fs.readFileSync(path.join(root,file),'utf8');
        // Campos HTML estáticos, modelos em strings JS e objetos jQuery criados em tempo de execução.
        const pattern = /\btype\s*(?:=|:)\s*["'](date|datetime-local|month|time)["']/g;
        for (const match of source.matchAll(pattern)) {
            const line = source.slice(0,match.index).split('\n').length;
            const context = source.slice(Math.max(0,match.index-40),match.index+260);
            const name = context.match(/\bname\s*(?:=|:)\s*["']([^"']+)["']/)?.[1] || '(dinâmico)';
            entries.push({file:file.replaceAll('\\','/'),line,type:match[1],name});
        }
        for (const match of source.matchAll(/\.type\s*=\s*[^;\n]*?(?:\?|:)\s*["'](date|datetime-local)["']/g)) {
            entries.push({file:file.replaceAll('\\','/'),line:source.slice(0,match.index).split('\n').length,type:match[1],name:'(tipo alternado em tempo de execução)'});
        }
    }
    return entries;
};
if (require.main === module) console.log(JSON.stringify(module.exports(), null, 2));
